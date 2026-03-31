#!/usr/bin/env python3
"""
ERP Clients API - Fetches clients from Oracle LMS_INDIVIDUAL_CRM_VIEW.
Deploy on a machine with reliable Oracle connectivity (e.g. where Toad works).
Laravel CRM fetches clients via this API when CLIENTS_VIEW_SOURCE=erp_http.
"""
import os
import re
from pathlib import Path

_DOTENV_PATH = Path(__file__).parent / ".env"
_dotenv_mtime = None
# ERP_CLIENTS_GROUP_VIEW (updated when .env loads — must stay in sync for Group Life tab)
GROUP_VIEW = ""
_group_view_columns_cache = None
_group_view_cached_for = None


def _sync_group_view_from_env():
    """Set GROUP_VIEW from env; invalidate column cache when the qualified name changes."""
    global GROUP_VIEW, _group_view_columns_cache, _group_view_cached_for
    schema = os.environ.get("ERP_VIEW_SCHEMA", "TQ_LMS")
    gv = os.environ.get("ERP_CLIENTS_GROUP_VIEW") or (
        f"{schema}.LMS_GROUP_CRM_VIEW" if schema else "LMS_GROUP_CRM_VIEW"
    )
    if gv != _group_view_cached_for:
        _group_view_cached_for = gv
        _group_view_columns_cache = None
    GROUP_VIEW = gv


def ensure_env_loaded():
    """
    Load erp-clients-api/.env into os.environ. File values override existing vars
    (fixes empty ERP_* inherited from the shell). Re-reads when the file changes (mtime).
    """
    global _dotenv_mtime
    p = _DOTENV_PATH
    if not p.exists():
        _sync_group_view_from_env()
        return
    try:
        m = p.stat().st_mtime
    except OSError:
        _sync_group_view_from_env()
        return
    if _dotenv_mtime == m:
        return
    _dotenv_mtime = m
    for line in p.read_text(encoding="utf-8", errors="replace").splitlines():
        line = line.strip()
        if line and not line.startswith("#") and "=" in line:
            k, _, v = line.partition("=")
            key = k.strip()
            if not key:
                continue
            os.environ[key] = v.strip().strip('"').strip("'")
    _sync_group_view_from_env()


ensure_env_loaded()

from flask import Flask, request, jsonify

try:
    import oracledb
except ImportError:
    print("Install oracledb: pip install oracledb")
    exit(1)

app = Flask(__name__)


@app.before_request
def _reload_dotenv_if_changed():
    ensure_env_loaded()

DSN = os.environ.get("ORACLE_DSN", "10.1.4.101:18032/PDBTQUEST")
USER = os.environ.get("ORACLE_USER", "TQ_LMS")
PASSWORD = os.environ.get("ORACLE_PASSWORD", "")
VIEW_SCHEMA = os.environ.get("ERP_VIEW_SCHEMA", "TQ_LMS")
VIEW_NAME = os.environ.get("ERP_CLIENTS_VIEW", "LMS_INDIVIDUAL_CRM_VIEW")
# GROUP_VIEW is set by ensure_env_loaded → _sync_group_view_from_env()
_sync_group_view_from_env()
INDIVIDUAL_VIEW = os.environ.get("ERP_CLIENTS_INDIVIDUAL_VIEW") or (f"{VIEW_SCHEMA}.LMS_INDIVIDUAL_CRM_VIEW" if VIEW_SCHEMA else "LMS_INDIVIDUAL_CRM_VIEW")
VIEW = f"{VIEW_SCHEMA}.{VIEW_NAME}" if VIEW_SCHEMA else VIEW_NAME


def _mortgage_view_q():
    """Schema-qualified mortgage view from env (read after ensure_env_loaded / before_request)."""
    return (os.environ.get("ERP_CLIENTS_MORTGAGE_VIEW") or "").strip()


def _mortgage_renewal_date_sql_expr_two_cols(col_a: str, col_b: str) -> str:
    """
    Next renewal date when the view has two candidates (e.g. MENDR vs MATURITY).
    Plain COALESCE(col_a, col_b) fails when col_a is non-null but stale (last cycle)
    while col_b holds the upcoming renewal — e.g. policy due 16-Apr-2026 on MATURITY.
    Prefer, in order: col_a if on/after today, col_b if on/after today, else COALESCE(a,b).
    """
    ca, cb = col_a.strip(), col_b.strip()
    return (
        "COALESCE("
        f"CASE WHEN {ca} IS NOT NULL AND TRUNC({ca}) >= TRUNC(SYSDATE) THEN {ca} END, "
        f"CASE WHEN {cb} IS NOT NULL AND TRUNC({cb}) >= TRUNC(SYSDATE) THEN {cb} END, "
        f"{ca}, {cb})"
    )


def _mortgage_renewal_date_sql_expr():
    """
    SQL expression for filtering/sorting mortgage renewals (default: MENDR_RENEWAL_DATE only).
    Env ERP_CLIENTS_MORTGAGE_RENEWAL_DATE_COLUMN overrides:
      - One column, e.g. MENDR_RENEWAL_DATE
      - Two columns A|B → smart pick (see _mortgage_renewal_date_sql_expr_two_cols)
      - Three or more → COALESCE(...)
    """
    spec = (os.environ.get("ERP_CLIENTS_MORTGAGE_RENEWAL_DATE_COLUMN") or "MENDR_RENEWAL_DATE").strip()
    if "|" in spec:
        parts = [p.strip() for p in spec.split("|") if p.strip()]
        if len(parts) >= 3:
            return "COALESCE(" + ", ".join(parts) + ")"
        if len(parts) == 2:
            return _mortgage_renewal_date_sql_expr_two_cols(parts[0], parts[1])
        return parts[0] if parts else "MENDR_RENEWAL_DATE"
    return spec if spec else "MENDR_RENEWAL_DATE"


def _mortgage_renewal_sql_range_predicate(rexpr):
    """
    Inclusive [start, end] on calendar dates, but never before Oracle SYSDATE (no past renewals).
    rexpr: SQL date expression (column name or COALESCE(...)).
    """
    return (
        f"({rexpr}) IS NOT NULL "
        f"AND TRUNC({rexpr}) >= GREATEST(TO_DATE(:mendr_renewal_from, 'YYYY-MM-DD'), TRUNC(SYSDATE)) "
        f"AND TRUNC({rexpr}) <= TO_DATE(:mendr_renewal_to, 'YYYY-MM-DD')"
    )


def _mortgage_renewal_sql_days_from_today_predicate(rexpr):
    """
    Rows where renewal date is between TRUNC(SYSDATE) and TRUNC(SYSDATE)+N days inclusive end.
    Uses DB server calendar (avoids CRM/PHP vs Oracle date skew).
    """
    return (
        f"({rexpr}) IS NOT NULL "
        f"AND TRUNC({rexpr}) >= TRUNC(SYSDATE) "
        f"AND TRUNC({rexpr}) <= TRUNC(SYSDATE) + :mendr_window_days"
    )


def _group_pension_view_q():
    return (os.environ.get("ERP_CLIENTS_GROUP_PENSION_VIEW") or "").strip()
COLS_BASE = "POLICY_NUMBER,PRODUCT,POL_PREPARED_BY,INTERMEDIARY,STATUS,KRA_PIN"
# Oracle view uses LIFE_ASSURED (with D); LIFE_ASSUR may also exist
COLS_EXTENDED = "POLICY_NUMBER,LIFE_ASSURED,PRODUCT,POL_PREPARED_BY,INTERMEDIARY,STATUS,KRA_PIN,PRP_DOB,MATURITY_DATE,ID_NO,PHONE_NO,CHECKOFF,BAL"
COLS = os.environ.get("ERP_CLIENTS_LIST_COLUMNS", COLS_EXTENDED)
# Group Life: LMS_GROUP_CRM_VIEW columns - discovered at runtime, these are fallback order
# Policy column: IPOL_POLICY_NO (GEMPPP0070) or POL_POLICY_NO - NEVER GRCT_RECEIPT_NO (receipt)
COLS_GROUP = os.environ.get("ERP_CLIENTS_GROUP_COLUMNS") or COLS_BASE
SEARCH_COLS = os.environ.get("ERP_CLIENTS_LIST_SEARCH_COLUMNS", "POLICY_NUMBER,LIFE_ASSURED,POL_PREPARED_BY,INTERMEDIARY,KRA_PIN,ID_NO,PHONE_NO")
SEARCH_COLS_GROUP = os.environ.get("ERP_CLIENTS_GROUP_SEARCH_COLUMNS") or "IPOL_POLICY_NO,POL_POLICY_NO,CLIENT_NAME,LIFE_ASSURED,AGN_NAME,CLIENT_EMAIL,CLIENT_CONTACT"
ORDER_COL = os.environ.get("ERP_CLIENTS_LIST_ORDER", "PRODUCT")
ORDER_COL_GROUP = os.environ.get("ERP_CLIENTS_GROUP_ORDER") or ORDER_COL
POLICY_COL = os.environ.get("ERP_POLICY_COLUMN", "POLICY_NUMBER")
# Group: policy column for display; group_by for aggregation (one row per policy)
POLICY_COL_GROUP = os.environ.get("ERP_GROUP_POLICY_COLUMN", "POL_POLICY_NO")
GROUP_BY_COL = os.environ.get("ERP_GROUP_GROUP_BY_COLUMN")  # default: IPOL_POLICY_NO if exists, else POLICY_COL_GROUP
PRODUCT_COL_GROUP = os.environ.get("ERP_GROUP_PRODUCT_COLUMN", "PROD_DESC")
USE_GROUP_AGGREGATE = os.environ.get("ERP_GROUP_AGGREGATE_BY_POLICY", "true").lower() in ("1", "true", "yes")
# If LMS_INDIVIDUAL_CRM_VIEW.PRODUCT is null for all rows, comma-list Oracle column names to SELECT and map into API "product" (first non-empty wins). Example: MATURITY_STATUS,POLICY_STATUS
ERP_INDIVIDUAL_PRODUCT_FALLBACK = os.environ.get("ERP_INDIVIDUAL_PRODUCT_FALLBACK", "").strip()


def _merge_individual_product_fallback_columns(columns):
    if not ERP_INDIVIDUAL_PRODUCT_FALLBACK:
        return list(columns)
    seen = {c.strip().upper() for c in columns}
    out = list(columns)
    for part in ERP_INDIVIDUAL_PRODUCT_FALLBACK.split(","):
        p = part.strip().upper()
        if p and p not in seen:
            out.append(p)
            seen.add(p)
    return out


def _apply_individual_product_fallback(d):
    if not ERP_INDIVIDUAL_PRODUCT_FALLBACK:
        return
    if d.get("product") and str(d.get("product")).strip():
        return
    for part in ERP_INDIVIDUAL_PRODUCT_FALLBACK.split(","):
        alt = part.strip()
        if not alt:
            continue
        ak = alt.lower().replace(" ", "_").replace("-", "_")
        v = d.get(ak)
        if v is not None and str(v).strip():
            d["product"] = str(v).strip()
            return


def parse_date(val):
    if val is None:
        return None
    if hasattr(val, "strftime"):
        return val.strftime("%Y-%m-%d")
    s = str(val).strip()
    return s[:10] if s and len(s) >= 10 else (s or None)


def _coerce_oracle_scalar_to_str(v):
    """
    Text columns may be CLOB/NCLOB — str(lob) is often empty in Python; .read() returns the text.
    """
    if v is None:
        return None
    if hasattr(v, "strftime"):
        return v.strftime("%Y-%m-%d")
    if isinstance(v, (bytes, bytearray)):
        s = v.decode("utf-8", errors="replace").strip()
        return s if s else None
    read_fn = getattr(v, "read", None)
    if callable(read_fn):
        try:
            chunk = read_fn()
            if chunk is None:
                return None
            if isinstance(chunk, (bytes, bytearray)):
                s = chunk.decode("utf-8", errors="replace").strip()
            else:
                s = str(chunk).strip()
            return s if s else None
        except Exception:
            pass
    try:
        s = str(v).strip()
    except Exception:
        return None
    if not s:
        return None
    if s.startswith("<oracledb.") or s.startswith("<cx_Oracle."):
        return None
    return s


RECEIPT_PATTERN = re.compile(r"^\d+/[A-Za-z]+/\d+$")  # 1/HO/100024 = receipt, NOT policy


def _is_receipt_format(val):
    """True if value looks like receipt (1/HO/100024), not policy (GEMPPP0070)."""
    if not val or not isinstance(val, str):
        return False
    return bool(RECEIPT_PATTERN.match(str(val).strip()))


def row_to_client(row, columns, *, life_system_override=None):
    """Convert Oracle row to API response format (snake_case)."""
    d = {}
    for i, col in enumerate(columns):
        v = row[i] if i < len(row) else None
        key = col.lower().replace(" ", "_").replace("-", "_")
        if key in (
            "prp_dob",
            "maturity",
            "maturity_date",
            "effective_date",
            "authorization_date",
            "mendr_renewal_date",
            "renewal_date",
            "next_renewal_date",
            "ren_date",
        ) and v is not None:
            if callable(getattr(v, "read", None)):
                v = _coerce_oracle_scalar_to_str(v)
            d[key] = parse_date(v)
        else:
            d[key] = _coerce_oracle_scalar_to_str(v)
    # BAL = balance, PRODUCTION_AMT (group view) -> paid_mat_amt for Laravel
    if "bal" in d and d["bal"] is not None:
        d["paid_mat_amt"] = d["bal"]
    elif "production_amt" in d and d["production_amt"] is not None and not d.get("paid_mat_amt"):
        d["paid_mat_amt"] = d["production_amt"]
    # Policy: prefer value that is NOT receipt format (1/HO/xxx). Try ipol_policy_no, pol_policy_no, policy_number.
    candidates = [
        d.get("ipol_policy_no"),
        d.get("pol_policy_no"),
        d.get("policy_number"),
        d.get("policy_no"),
        d.get("contract_no"),
        d.get("scheme_no"),
    ]
    policy_val = None
    for c in candidates:
        if c and str(c).strip() and not _is_receipt_format(c):
            policy_val = str(c).strip()
            break
    if not policy_val:
        for c in candidates:
            if c and str(c).strip():
                policy_val = str(c).strip()
                break
    if not policy_val and row and len(row) >= 2:
        for idx in (0, 1):
            v = row[idx]
            if v is not None and str(v).strip() and not _is_receipt_format(str(v)):
                policy_val = str(v).strip()
                break
    if policy_val:
        d["policy_number"] = policy_val
        d["policy_no"] = policy_val
        d["ipol_policy_no"] = policy_val
        d["pol_policy_no"] = policy_val
    if d.get("grct_receipt_no"):
        d["receipt_number"] = str(d["grct_receipt_no"]).strip()
    # Authorization date -> effective_date for group (date authorized)
    if "authorization_date" in d and d["authorization_date"] and not d.get("effective_date"):
        d["effective_date"] = d["authorization_date"]
    # Normalize maturity_date -> maturity for Laravel
    if "maturity_date" in d and "maturity" not in d:
        d["maturity"] = d["maturity_date"]
    # Renewal date for mortgage lists: LMS often leaves MATURITY_DATE as the next cycle when MENDR_RENEWAL_DATE is null
    if not d.get("mendr_renewal_date"):
        for k in ("renewal_date", "next_renewal_date", "ren_date"):
            if d.get(k):
                d["mendr_renewal_date"] = d[k]
                break
    if not d.get("mendr_renewal_date") and d.get("maturity"):
        d["mendr_renewal_date"] = d["maturity"]
    elif not d.get("mendr_renewal_date") and d.get("maturity_date"):
        d["mendr_renewal_date"] = d["maturity_date"]
    # Normalize life_assured -> life_assur for Laravel (Oracle uses LIFE_ASSURED)
    if "life_assured" in d:
        d["life_assur"] = d["life_assured"]
    elif "life_assur" in d:
        d["life_assured"] = d["life_assur"]
    # Group view may use MEM_SURNAME, MEMBER_NAME, CLIENT_NAME for life assured
    if "mem_surname" in d and not d.get("life_assur") and not d.get("life_assured"):
        d["life_assur"] = d["mem_surname"]
        d["life_assured"] = d["mem_surname"]
    elif "member_name" in d and not d.get("life_assur") and not d.get("life_assured"):
        d["life_assur"] = d["member_name"]
        d["life_assured"] = d["member_name"]
    elif "client_name" in d and not d.get("life_assur") and not d.get("life_assured"):
        d["life_assur"] = d["client_name"]
        d["life_assured"] = d["client_name"]
    # Product: group views prefer PROD_DESC / ERP_GROUP_PRODUCT_COLUMN; individual LMS view uses PRODUCT.
    prod_from_row = d.get("product")
    prod_desc_val = d.get("prod_desc") or d.get(PRODUCT_COL_GROUP.lower().replace(" ", "_"))
    if prod_desc_val and str(prod_desc_val).strip():
        d["product"] = str(prod_desc_val).strip()
    elif prod_from_row and str(prod_from_row).strip():
        d["product"] = str(prod_from_row).strip()
    else:
        d["product"] = d.get("prod_sht_desc") or d.get("scheme_name") or None
    _apply_individual_product_fallback(d)
    if "agn_name" in d and not d.get("intermediary"):
        d["intermediary"] = d["agn_name"]
    # Group view: BRA_MANAGER, UNIT_MANAR can serve as pol_prepared_by
    if "bra_manager" in d and not d.get("pol_prepared_by"):
        d["pol_prepared_by"] = d["bra_manager"]
    elif "unit_manar" in d and not d.get("pol_prepared_by"):
        d["pol_prepared_by"] = d["unit_manar"]
    # Normalize phone_number -> phone_no, id_number -> id_no (group view uses CLIENT_CONTACT)
    if "phone_number" in d and "phone_no" not in d:
        d["phone_no"] = d["phone_number"]
    elif "phone_no" in d and "phone_number" not in d:
        d["phone_number"] = d["phone_no"]
    elif "client_contact" in d and not d.get("phone_no"):
        d["phone_no"] = d["client_contact"]
        d["phone_number"] = d["client_contact"]
    elif "mem_teleph" in d and not d.get("phone_no"):
        d["phone_no"] = d["mem_teleph"]
        d["phone_number"] = d["mem_teleph"]
    if "client_email" in d and not d.get("email_adr"):
        d["email_adr"] = d["client_email"]
        d["email"] = d["client_email"]
    elif "mem_email" in d and not d.get("email_adr"):
        d["email_adr"] = d["mem_email"]
        d["email"] = d["mem_email"]
    if "id_number" in d and "id_no" not in d:
        d["id_no"] = d["id_number"]
    elif "id_no" in d and "id_number" not in d:
        d["id_number"] = d["id_no"]
    # Mortgage: MENDR_STATUS; Group Pension: ENDR_STATUS; then generic policy status
    mendr_st = d.get("mendr_status")
    endr_st = d.get("endr_status")
    if mendr_st is not None and str(mendr_st).strip() != "":
        d["status"] = str(mendr_st).strip()
    elif endr_st is not None and str(endr_st).strip() != "":
        d["status"] = str(endr_st).strip()
    elif d.get("policy_status") and str(d.get("policy_status") or "").strip() != "":
        d["status"] = str(d["policy_status"]).strip()
    if life_system_override:
        d["life_system"] = life_system_override
    return d


def get_connection():
    return oracledb.connect(user=USER, password=PASSWORD, dsn=DSN)


def resolve_view(system):
    """Return the Oracle view to query based on system filter (group|individual|mortgage|group_pension)."""
    if system == "group":
        return GROUP_VIEW
    if system == "individual":
        return INDIVIDUAL_VIEW
    if system == "mortgage":
        return _mortgage_view_q()
    if system == "group_pension":
        return _group_pension_view_q()
    return VIEW


# When true, skip LMS_GROUP_CRM_VIEW and use individual view + filter. Set false to use group view.
# When ERP_GROUP_USE_GROUP_VIEW_ONLY=true, this is ignored – always use actual Group view (no Individual mix).
USE_GROUP_USE_GROUP_VIEW_ONLY = os.environ.get("ERP_GROUP_USE_GROUP_VIEW_ONLY", "true").lower() in ("1", "true", "yes")
USE_GROUP_FROM_INDIVIDUAL = (not USE_GROUP_USE_GROUP_VIEW_ONLY) and os.environ.get("ERP_GROUP_FROM_INDIVIDUAL_ONLY", "false").lower() in ("1", "true", "yes")
# When true, use SELECT * for group view (discovers columns at runtime - works with any schema)
USE_GROUP_SELECT_STAR = os.environ.get("ERP_GROUP_USE_SELECT_STAR", "true").lower() in ("1", "true", "yes")

# ---------------------------------------------------------------------------
# Group Life: canonical column mapping (Oracle -> API output)
# LMS_GROUP_CRM_VIEW has receipt-level rows; we aggregate by policy (IPOL_POLICY_NO)
# ---------------------------------------------------------------------------
GROUP_POLICY_COLS = ["POL_POLICY_NO", "IPOL_POLICY_NO"]  # POL_POLICY_NO first (policy e.g. GEMPPP0070)
GROUP_AGG_SELECT = [
    ("CLIENT_NAME", "MAX", "client_name"),
    ("AUTHORIZATION_DATE", "MAX", "authorization_date"),
    ("PRODUCTION_AMT", "SUM", "production_amt"),
    ("CLIENT_EMAIL", "MAX", "client_email"),
    ("CLIENT_CONTACT", "MAX", "client_contact"),
    ("LIFE_ASSURED", "MAX", "life_assured"),
    ("BRA_MANAGER", "MAX", "bra_manager"),
    ("PROD_DESC", "MAX", "prod_desc"),
    ("PRODUCT", "MAX", "product"),
    ("PROD_SHT_DESC", "MAX", "prod_sht_desc"),
    ("SCHEME_NAME", "MAX", "scheme_name"),
    ("AGN_NAME", "MAX", "agn_name"),
]
# Add ERP_GROUP_PRODUCT_COLUMN if different from above (e.g. PRODUCT, SCHEME_DESC)
def _group_agg_select_cols():
    cols = _get_group_view_columns()
    seen = {c[0] for c in GROUP_AGG_SELECT}
    if PRODUCT_COL_GROUP not in seen and PRODUCT_COL_GROUP in cols:
        return GROUP_AGG_SELECT + [(PRODUCT_COL_GROUP, "MAX", PRODUCT_COL_GROUP.lower().replace(" ", "_"))]
    return GROUP_AGG_SELECT


def _get_group_view_columns():
    """Discover actual columns in ERP_CLIENTS_GROUP_VIEW. Cached until the configured view changes."""
    global _group_view_columns_cache
    if _group_view_columns_cache is not None:
        return _group_view_columns_cache
    try:
        parts = GROUP_VIEW.split(".")
        tname = parts[-1].upper() if parts else "LMS_GROUP_CRM_VIEW"
        owner = parts[0].upper() if len(parts) > 1 else (VIEW_SCHEMA.upper() or "TQ_LMS")
        conn = get_connection()
        cursor = conn.cursor()
        cursor.execute(
            "SELECT column_name FROM all_tab_columns WHERE table_name = :t AND owner = :o ORDER BY column_id",
            {"t": tname, "o": owner},
        )
        cols = {str(r[0]).upper() for r in cursor.fetchall()}
        cursor.close()
        conn.close()
        _group_view_columns_cache = cols
        return cols if cols else {"POL_POLICY_NO", "CLIENT_NAME", "AUTHORIZATION_DATE", "PRODUCTION_AMT", "PROD_DESC", "AGN_NAME"}
    except Exception:
        # Prefer POL_POLICY_NO – many views have it; IPOL_POLICY_NO may not exist (ORA-00904)
        return {"POL_POLICY_NO", "CLIENT_NAME", "AUTHORIZATION_DATE", "PRODUCTION_AMT", "PROD_DESC", "PRODUCT", "AGN_NAME"}


def _get_group_policy_column():
    """Return policy column for display. Prefer ERP_GROUP_POLICY_COLUMN from .env if it exists in view."""
    cols = _get_group_view_columns()
    if POLICY_COL_GROUP in cols:
        return POLICY_COL_GROUP
    for c in GROUP_POLICY_COLS:
        if c in cols:
            return c
    return POLICY_COL_GROUP


def _get_group_by_column():
    """Column for GROUP BY. Use ERP_GROUP_GROUP_BY_COLUMN only if it exists in the view. Fallback to POL_POLICY_NO."""
    cols = _get_group_view_columns()
    # Validate env column exists – many views have POL_POLICY_NO but not IPOL_POLICY_NO
    if GROUP_BY_COL and GROUP_BY_COL in cols:
        return GROUP_BY_COL
    if POLICY_COL_GROUP in cols:
        return POLICY_COL_GROUP
    for c in GROUP_POLICY_COLS:
        if c in cols:
            return c
    return POLICY_COL_GROUP


_view_columns_cache = {}


def _parse_qualified_view(qualified: str):
    """Parse 'SCHEMA.VIEW' into (owner_upper, table_upper)."""
    q = (qualified or "").strip()
    if "." in q:
        parts = q.split(".", 1)
        return parts[0].strip().upper(), parts[1].strip().upper()
    return (VIEW_SCHEMA.upper() or "TQ_LMS", q.upper())


def _get_columns_for_qualified_view(qualified: str):
    """Column set for any schema-qualified Oracle view. Cached per name (e.g. mortgage list view)."""
    global _view_columns_cache
    key = qualified.strip()
    if key in _view_columns_cache:
        return _view_columns_cache[key]
    try:
        owner, tname = _parse_qualified_view(key)
        conn = get_connection()
        cursor = conn.cursor()
        cursor.execute(
            "SELECT column_name FROM all_tab_columns WHERE table_name = :t AND owner = :o ORDER BY column_id",
            {"t": tname, "o": owner},
        )
        cols = {str(r[0]).upper() for r in cursor.fetchall()}
        cursor.close()
        conn.close()
        _view_columns_cache[key] = cols
        return cols
    except Exception:
        fallback = {"POL_POLICY_NO", "POLICY_NUMBER", "CLIENT_NAME", "PROD_DESC", "PRODUCT"}
        _view_columns_cache[key] = fallback
        return fallback


def _get_group_by_column_for_view(qualified: str):
    """Policy column for group-shaped views (e.g. LMS_GROUP_MORTAGE_VIEW), using that view's columns."""
    cols = _get_columns_for_qualified_view(qualified)
    if GROUP_BY_COL and GROUP_BY_COL in cols:
        return GROUP_BY_COL
    if POLICY_COL_GROUP in cols:
        return POLICY_COL_GROUP
    for c in GROUP_POLICY_COLS:
        if c in cols:
            return c
    if "POLICY_NUMBER" in cols:
        return "POLICY_NUMBER"
    return POLICY_COL_GROUP


def _refine_search_columns_for_qualified_view(qualified, base_columns, env_override_key=None):
    """
    Keep only column names that exist on the given view. Avoids ORA-00904 when Group Life search
    lists columns absent from LMS_GROUP_MORTAGE_VIEW (etc.).
    """
    if not qualified or not base_columns:
        return list(base_columns) if base_columns else []
    raw = (os.environ.get(env_override_key) or "").strip() if env_override_key else ""
    if raw:
        candidates = [c.strip() for c in raw.split(",") if c.strip()]
    else:
        candidates = list(base_columns)
    vc = _get_columns_for_qualified_view(qualified)
    out = [c for c in candidates if c.upper() in vc]
    if out:
        return out
    for c in (
        "POL_POLICY_NO",
        "IPOL_POLICY_NO",
        "POLICY_NUMBER",
        "CLIENT_NAME",
        "LIFE_ASSURED",
        "PROD_DESC",
        "PRODUCT",
        "AGN_NAME",
        "KRA_PIN",
        "CLIENT_EMAIL",
        "CLIENT_CONTACT",
    ):
        if c in vc:
            out.append(c)
    return out


def _build_group_aggregate_query(extra_where=""):
    """Build GROUP BY aggregate. GROUP BY IPOL_POLICY_NO (policy). Select both; row_to_client rejects receipt format."""
    cols = _get_group_view_columns()
    group_col = _get_group_by_column()
    selects = [f"base.{group_col} as POLICY_NUMBER"]
    if "IPOL_POLICY_NO" in cols:
        selects.append(f"base.IPOL_POLICY_NO as IPOL_POLICY_NO" if group_col == "IPOL_POLICY_NO" else f"MAX(base.IPOL_POLICY_NO) as IPOL_POLICY_NO")
    if "POL_POLICY_NO" in cols:
        selects.append(f"base.POL_POLICY_NO as POL_POLICY_NO" if group_col == "POL_POLICY_NO" else f"MAX(base.POL_POLICY_NO) as POL_POLICY_NO")
    for ora_col, agg, _ in _group_agg_select_cols():
        if ora_col in cols and ora_col not in ("IPOL_POLICY_NO", "POL_POLICY_NO"):
            selects.append(f"{agg}(base.{ora_col}) as {ora_col}")
    return f"""
        SELECT * FROM (
            SELECT a.*, ROWNUM rnum FROM (
                SELECT {", ".join(selects)}
                FROM {GROUP_VIEW} base
                {extra_where}
                GROUP BY base.{group_col}
                ORDER BY base.{group_col}
            ) a WHERE ROWNUM <= :end_row
        ) WHERE rnum > :start_row
    """


def _get_group_life_clients_flat(limit, offset, search=None, policy=None):
    """
    Direct flat SELECT - same logic as /clients/find-policy (proven to work).
    Tries exact match first, then LIKE. No GROUP BY. Dedupes in Python.
    """
    term = (policy or search or "").strip()
    if not term:
        return [], 0

    cols = _get_group_view_columns()
    conn = None
    try:
        conn = get_connection()
        cursor = conn.cursor()

        # Use SELECT * to get all columns - row_to_client handles mapping
        sel = "*"
        rows = []

        # 1. Exact match: try all known policy columns
        exact_cols = [c for c in ("POL_POLICY_NO", "IPOL_POLICY_NO", "POLICY_NUMBER", "CONTRACT_NO", "SCHEME_NO") if c in cols]
        if exact_cols:
            exact_where = " OR ".join([f"{c} = :term" for c in exact_cols])
            try:
                sql = f"""
                    SELECT * FROM (
                        SELECT a.*, ROWNUM rnum FROM (
                            SELECT {sel} FROM {GROUP_VIEW} WHERE ({exact_where})
                        ) a WHERE ROWNUM <= :end_row
                    ) WHERE rnum > :start_row
                """
                cursor.execute(sql, {"term": term, "end_row": offset + limit, "start_row": offset})
                rows = cursor.fetchall()
            except oracledb.DatabaseError:
                pass

        # 2. LIKE match if exact found nothing: UPPER(col) LIKE %term%
        if not rows:
            like_cols = [c for c in ("POL_POLICY_NO", "IPOL_POLICY_NO", "POLICY_NUMBER", "CONTRACT_NO", "SCHEME_NO", "CLIENT_NAME") if c in cols]
            if not like_cols:
                like_cols = list(cols)[:5]  # fallback to first 5 columns
            if like_cols:
                like_cond = " OR ".join([f"UPPER(NVL(TO_CHAR({c}),'')) LIKE :pat" for c in like_cols])
                try:
                    sql = f"""
                        SELECT * FROM (
                            SELECT a.*, ROWNUM rnum FROM (
                                SELECT {sel} FROM {GROUP_VIEW} WHERE ({like_cond})
                            ) a WHERE ROWNUM <= :end_row
                        ) WHERE rnum > :start_row
                    """
                    cursor.execute(sql, {"pat": f"%{term.upper()}%", "end_row": offset + limit, "start_row": offset})
                    rows = cursor.fetchall()
                except oracledb.DatabaseError:
                    pass

        if not rows:
            cursor.close()
            conn.close()
            return [], 0

        out_cols = [d[0] for d in cursor.description if d[0] and str(d[0]).upper() != "RNUM"] if cursor.description else []
        raw = [row_to_client(r[: len(out_cols)], out_cols) for r in rows]
        seen = set()
        data = []
        for r in raw:
            pk = (r.get("ipol_policy_no") or r.get("pol_policy_no") or r.get("policy_number") or "").strip()
            if not pk or _is_receipt_format(pk):
                pk = (r.get("pol_policy_no") or r.get("ipol_policy_no") or "").strip()
            if pk and pk not in seen:
                seen.add(pk)
                data.append(r)
            elif not pk:
                data.append(r)
        cursor.close()
        conn.close()
        return data[:limit], offset + len(data)
    except Exception:
        if conn:
            try:
                conn.close()
            except Exception:
                pass
        raise


def _get_group_life_clients(limit, offset, search=None, policy=None):
    """
    Fetch Group Life clients from LMS_GROUP_CRM_VIEW.
    When policy/search provided: try flat query first (no GROUP BY) - most reliable for policy lookup.
    Otherwise: use GROUP BY aggregate for listing.
    Returns (data_list, total_estimate) or raises.
    """
    if policy and policy.strip() or (search and search.strip()):
        try:
            data, total = _get_group_life_clients_flat(limit, offset, search, policy)
            if data:
                return data, total
        except Exception:
            pass

    bind = {"end_row": offset + limit, "start_row": offset}
    extra_where = ""
    conditions = []
    group_col = _get_group_by_column()
    cols = _get_group_view_columns()
    policy_upper = policy.strip().upper() if policy and policy.strip() else ""
    search_upper = search.strip() if search and search.strip() else ""

    # Policy match: case-insensitive, trim; check all known policy columns that exist
    policy_column_candidates = ["POL_POLICY_NO", "IPOL_POLICY_NO", "POLICY_NUMBER", "CONTRACT_NO", "SCHEME_NO"]
    policy_cols_in_view = [c for c in policy_column_candidates if c in cols]
    if policy and policy_upper and policy_cols_in_view:
        like_conds = [f"UPPER(NVL(TRIM(TO_CHAR(base.{c})),'')) LIKE :policy_like" for c in policy_cols_in_view]
        exact_conds = [f"UPPER(NVL(TRIM(TO_CHAR(base.{c})),'')) = :policy_upper" for c in policy_cols_in_view]
        all_conds = like_conds + exact_conds
        conditions.append("(" + " OR ".join(all_conds) + ")")
        bind["policy_like"] = f"%{policy_upper}%"
        bind["policy_upper"] = policy_upper
    elif policy and policy_upper:
        conditions.append(f"UPPER(NVL(TRIM(TO_CHAR(base.{group_col})),'')) LIKE :policy_like")
        bind["policy_like"] = f"%{policy_upper}%"

    if search_upper and search_upper.upper() != policy_upper:
        search_cols = [c.strip() for c in SEARCH_COLS_GROUP.split(",") if c.strip()]
        search_cols = [c for c in search_cols if c in cols]
        if search_cols:
            conds = " OR ".join([f"UPPER(NVL(TO_CHAR(base.{c}),'')) LIKE UPPER(:search)" for c in search_cols[:8]])
            conditions.append(f"({conds})")
            bind["search"] = f"%{search_upper}%"
    elif search_upper and not policy_upper:
        search_cols = [c.strip() for c in SEARCH_COLS_GROUP.split(",") if c.strip()]
        search_cols = [c for c in search_cols if c in cols]
        if search_cols:
            conds = " OR ".join([f"UPPER(NVL(TO_CHAR(base.{c}),'')) LIKE UPPER(:search)" for c in search_cols[:8]])
            conditions.append(f"({conds})")
            bind["search"] = f"%{search_upper}%"

    if conditions:
        extra_where = " WHERE " + " AND ".join(conditions)

    sql = _build_group_aggregate_query(extra_where)
    conn = get_connection()
    cursor = conn.cursor()
    try:
        cursor.execute(sql, bind)
    except oracledb.DatabaseError as ex:
        err = str(ex)
        if "ORA-00904" in err or "invalid identifier" in err.lower():
            if group_col == "IPOL_POLICY_NO" and "POL_POLICY_NO" in _get_group_view_columns():
                pass
            raise
        raise
    rows = cursor.fetchall()
    out_cols = [d[0] for d in cursor.description if d[0] and str(d[0]).upper() != "RNUM"] if cursor.description else []
    cursor.close()
    conn.close()
    data = [row_to_client(r[: len(out_cols)], out_cols) for r in rows]

    # When policy/search returns 0, retry with flat query (no GROUP BY) - handles schema/column mismatches
    if len(data) == 0 and (policy_upper or search_upper):
        try:
            data, total_flat = _get_group_life_clients_flat(limit, offset, search, policy)
            if data:
                return data, offset + len(data)
        except Exception:
            pass

    total = int(os.environ.get("ERP_CLIENTS_GROUP_ESTIMATED_TOTAL", "2383"))
    if len(data) < limit:
        total = offset + len(data)
    return data, total


def _get_distinct_products():
    """Return distinct product names from both Individual (PRODUCT) and Group (PROD_DESC) views. Used for ticket dropdown."""
    if not PASSWORD:
        return jsonify({"error": "ORACLE_PASSWORD not set"}), 503
    seen = set()
    products = []
    try:
        conn = get_connection()
        cursor = conn.cursor()
        # Individual view: PRODUCT (may be CLOB — TO_CHAR in SQL avoids TRIM/CLOB quirks)
        try:
            cursor.execute(f"""
                SELECT * FROM (
                    SELECT DISTINCT TO_CHAR(PRODUCT) AS P FROM {INDIVIDUAL_VIEW}
                    WHERE PRODUCT IS NOT NULL AND LENGTH(TRIM(TO_CHAR(PRODUCT))) > 0
                ) WHERE ROWNUM <= 100
            """)
        except Exception:
            cursor.execute(f"""
                SELECT * FROM (
                    SELECT DISTINCT PRODUCT FROM {INDIVIDUAL_VIEW} WHERE PRODUCT IS NOT NULL
                ) WHERE ROWNUM <= 100
            """)
        for r in cursor.fetchall():
            v = (_coerce_oracle_scalar_to_str(r[0]) or "").strip()
            if v and v not in seen:
                seen.add(v)
                products.append(v)
        # Group view: PROD_DESC (per user: product from PROD_DESC)
        cols = _get_group_view_columns()
        if "PROD_DESC" in cols:
            cursor.execute(f"""
                SELECT * FROM (
                    SELECT DISTINCT PROD_DESC FROM {GROUP_VIEW} WHERE PROD_DESC IS NOT NULL AND TRIM(PROD_DESC) != ''
                ) WHERE ROWNUM <= 100
            """)
            for r in cursor.fetchall():
                v = (r[0] or "").strip()
                if v and v not in seen:
                    seen.add(v)
                    products.append(v)
        cursor.close()
        conn.close()
        products.sort(key=lambda x: (x or "").upper())
        kw = [k.strip() for k in os.environ.get("ERP_GROUP_LIFE_KEYWORDS", "GROUP,GL,SCHEME").split(",") if k.strip()]
        suggested = [p for p in products if any(k.upper() in (p or "").upper() for k in kw)]
        return jsonify({"products": products, "suggested_group": suggested, "keywords_used": kw})
    except Exception as e:
        return jsonify({"error": str(e)}), 500


def get_group_product_filter():
    """Return WHERE conditions for group life. Uses product keywords OR checkoff (employer deduction = group)."""
    mode = os.environ.get("ERP_GROUP_FILTER_MODE", "checkoff").lower()
    # checkoff = policies with employer deduction (GROUP, STAFF etc) - most reliable for group life
    # Oracle: '' is NULL, so use LENGTH > 0 instead of != ''
    if mode == "checkoff":
        return " (NVL(LENGTH(TRIM(CHECKOFF)), 0) > 0) ", {}
    # product = match product name keywords
    default_kw = "GROUP,GL,CREDIT LIFE,SCHEME,FUNERAL,EMPLOYER,STAFF"
    kw = [k.strip() for k in os.environ.get("ERP_GROUP_LIFE_KEYWORDS", default_kw).split(",") if k.strip()]
    if not kw:
        return " (UPPER(NVL(PRODUCT,'')) LIKE '%GROUP%' OR UPPER(NVL(PRODUCT,'')) LIKE '%SCHEME%') ", {}
    conds = []
    bind = {}
    for i, k in enumerate(kw):
        key = f"gf{i}"
        bind[key] = f"%{k}%"
        conds.append(f"UPPER(NVL(PRODUCT,'')) LIKE :{key}")
    return " (" + " OR ".join(conds) + ") ", bind


# Root-level find-policy (no prefix - always works)
@app.route("/find-policy", methods=["GET"])
def find_policy_root():
    """Policy lookup in GROUP view. ?policy=GEMPPP0334"""
    return _do_find_policy()

# Register find-policy BEFORE /clients so specific path matches first
@app.route("/clients/find-policy", methods=["GET"])
@app.route("/api/clients/find-policy", methods=["GET"])
def clients_find_policy():
    """DIAGNOSTIC: Search for a policy in GROUP view. Use ?policy=GEMPPP0334"""
    return _do_find_policy()


def _do_find_policy():
    """Shared logic for policy lookup in GROUP view."""
    policy = (request.args.get("policy") or "").strip()
    if not policy:
        return jsonify({"error": "Add ?policy=GEMPPP0334 to search"}), 400
    if not PASSWORD:
        return jsonify({"error": "ORACLE_PASSWORD not set"}), 503
    cols = _get_group_view_columns()
    result = {"policy": policy, "exact_match": [], "like_match": [], "columns_in_view": list(cols)}
    try:
        conn = get_connection()
        cursor = conn.cursor()
        col_names = [c for c in ("POL_POLICY_NO", "IPOL_POLICY_NO", "CLIENT_NAME", "PROD_DESC") if c in cols]
        if not col_names:
            col_names = list(cols)[:8]
        sel = ", ".join(col_names)
        exact_cols = [c for c in ("POL_POLICY_NO", "IPOL_POLICY_NO", "POLICY_NUMBER", "CONTRACT_NO", "SCHEME_NO") if c in cols]
        if exact_cols:
            exact_where = " OR ".join([f"{c} = :policy" for c in exact_cols])
            cursor.execute(f"SELECT * FROM (SELECT {sel} FROM {GROUP_VIEW} WHERE ({exact_where}) AND ROWNUM <= 5)", {"policy": policy})
            rows = cursor.fetchall()
            desc = cursor.description
            if rows and desc:
                names = [d[0] for d in desc]
                result["exact_match"] = [dict(zip(names, [str(v) if v is not None else None for v in r])) for r in rows]
        like_cols = [c for c in ("POL_POLICY_NO", "IPOL_POLICY_NO", "POLICY_NUMBER", "CLIENT_NAME") if c in cols]
        if like_cols:
            like_cond = " OR ".join([f"UPPER(NVL(TO_CHAR({c}),'')) LIKE :pat" for c in like_cols])
            cursor.execute(f"SELECT * FROM (SELECT {sel} FROM {GROUP_VIEW} WHERE ({like_cond}) AND ROWNUM <= 5)", {"pat": f"%{policy.upper()}%"})
            rows = cursor.fetchall()
            desc = cursor.description
            if rows and desc:
                names = [d[0] for d in desc]
                result["like_match"] = [dict(zip(names, [str(v) if v is not None else None for v in r])) for r in rows]
        cursor.close()
        conn.close()
        result["found"] = bool(result["exact_match"] or result["like_match"])
        return jsonify(result)
    except Exception as e:
        return jsonify({"policy": policy, "error": str(e)}), 500


@app.route("/maturities", methods=["GET"])
@app.route("/clients/maturities", methods=["GET"])
@app.route("/api/clients/maturities", methods=["GET"])
def get_maturities():
    """
    Fetch policies maturing between two dates. Optimized for maturities page.
    GET /clients/maturities?from=2026-03-05&to=2026-06-03&product=EDUCATION ENDOWMENT POLICY
    """
    if not PASSWORD:
        return jsonify({"data": [], "error": "ORACLE_PASSWORD not set"}), 503
    date_from = (request.args.get("from") or "").strip()
    date_to = (request.args.get("to") or "").strip()
    product_filter = (request.args.get("product") or "").strip()
    if not date_from or not date_to:
        return jsonify({"data": [], "error": "Missing from or to (YYYY-MM-DD)"}), 400
    try:
        conn = get_connection()
        cursor = conn.cursor()
        cols = "POLICY_NUMBER,LIFE_ASSURED,PRODUCT,MATURITY_DATE"
        where_clause = """
            WHERE MATURITY_DATE IS NOT NULL
            AND TRUNC(MATURITY_DATE) >= TO_DATE(:df, 'YYYY-MM-DD')
            AND TRUNC(MATURITY_DATE) <= TO_DATE(:dt, 'YYYY-MM-DD')
        """
        bind = {"df": date_from, "dt": date_to}
        if product_filter:
            where_clause += " AND TRIM(PRODUCT) = :product"
            bind["product"] = product_filter
        sql = f"""
            SELECT {cols} FROM {INDIVIDUAL_VIEW}
            {where_clause}
            ORDER BY MATURITY_DATE, POLICY_NUMBER
        """
        cursor.execute(sql, bind)
        rows = cursor.fetchall()
        col_names = [d[0] for d in cursor.description] if cursor.description else cols.split(",")
        data = [row_to_client(r[: len(col_names)], col_names) for r in rows]
        cursor.close()
        conn.close()
        return jsonify({"data": data, "total": len(data)})
    except Exception as e:
        return jsonify({"data": [], "error": str(e)}), 500


@app.route("/clients", methods=["GET"])
@app.route("/api/clients", methods=["GET"])
def get_clients():
    """GET /clients? ... mortgage: mendr_window_days=N (MENDR_RENEWAL_DATE from TRUNC(SYSDATE), inclusive), or mendr_renewal_from/to, or mendr_renewal_on."""
    products_only = request.args.get("products", "").strip() in ("1", "true", "yes")
    if products_only:
        return _get_distinct_products()
    count_only = request.args.get("count_only", "").strip() in ("1", "true", "yes")
    limit = min(int(request.args.get("limit", 50)), 100)
    offset = max(0, int(request.args.get("offset", 0)))
    search = (request.args.get("search") or "").strip()
    policy = (request.args.get("policy") or "").strip()
    system = (request.args.get("system") or "").strip().lower()
    debug_mode = request.args.get("debug", "").strip() in ("1", "true", "yes")
    mendr_renewal_on = (request.args.get("mendr_renewal_on") or "").strip()
    if mendr_renewal_on and not re.match(r"^\d{4}-\d{2}-\d{2}$", mendr_renewal_on):
        mendr_renewal_on = ""
    mendr_renewal_from = (request.args.get("mendr_renewal_from") or "").strip()
    mendr_renewal_to = (request.args.get("mendr_renewal_to") or "").strip()
    if mendr_renewal_from and not re.match(r"^\d{4}-\d{2}-\d{2}$", mendr_renewal_from):
        mendr_renewal_from = ""
    if mendr_renewal_to and not re.match(r"^\d{4}-\d{2}-\d{2}$", mendr_renewal_to):
        mendr_renewal_to = ""
    if mendr_renewal_from and mendr_renewal_to and mendr_renewal_from > mendr_renewal_to:
        mendr_renewal_from, mendr_renewal_to = mendr_renewal_to, mendr_renewal_from
    mendr_renewal_range = bool(mendr_renewal_from and mendr_renewal_to)
    _mwd_raw = (request.args.get("mendr_window_days") or "").strip()
    mendr_window_days_int = None
    if _mwd_raw.isdigit():
        mendr_window_days_int = max(1, min(120, int(_mwd_raw)))
    mortgage_upcoming = request.args.get("mortgage_upcoming_renewals", "").strip().lower() in ("1", "true", "yes")
    if (
        system == "mortgage"
        and mortgage_upcoming
        and not mendr_renewal_range
        and mendr_window_days_int is None
        and not policy
        and not search
    ):
        return jsonify({
            "data": [],
            "total": 0,
            "error": (
                "Upcoming renewals only: pass mendr_window_days (1–120) or mendr_renewal_from and mendr_renewal_to. "
                "The full mortgage register is not returned in this mode."
            ),
        })
    if system == "mortgage" and not _mortgage_view_q():
        return jsonify({
            "data": [],
            "total": 0,
            "error": "ERP_CLIENTS_MORTGAGE_VIEW missing in erp-clients-api/.env — add e.g. TQ_LMS.LMS_GROUP_MORTGAGE_VIEW and save (API reloads .env automatically).",
        })
    if system == "group_pension" and not _group_pension_view_q():
        return jsonify({
            "data": [],
            "total": 0,
            "error": "ERP_CLIENTS_GROUP_PENSION_VIEW missing in erp-clients-api/.env.",
        })
    target_view = resolve_view(system)
    life_system_tag = system if system in ("mortgage", "group_pension") else None
    is_group = system == "group"
    is_mortgage = system == "mortgage"
    # LMS_GROUP_MORTAGE_VIEW etc.: same column/search shape as Group Life, not individual CRM view
    use_group_columns = is_group or is_mortgage
    columns = [c.strip() for c in (COLS_GROUP if use_group_columns else COLS).split(",")]
    if system == "individual":
        columns = _merge_individual_product_fallback_columns(columns)
    search_columns = [c.strip() for c in (SEARCH_COLS_GROUP if use_group_columns else SEARCH_COLS).split(",") if c.strip()]
    if is_mortgage and _mortgage_view_q():
        search_columns = _refine_search_columns_for_qualified_view(
            _mortgage_view_q(), search_columns, "ERP_CLIENTS_MORTGAGE_SEARCH_COLUMNS"
        )
    if system == "group_pension" and _group_pension_view_q():
        search_columns = _refine_search_columns_for_qualified_view(
            _group_pension_view_q(), search_columns, "ERP_CLIENTS_GROUP_PENSION_SEARCH_COLUMNS"
        )
    order_col = ORDER_COL_GROUP if use_group_columns else ORDER_COL

    if not PASSWORD:
        return jsonify({"data": [], "total": 0, "error": "ORACLE_PASSWORD not set"}), 503

    # Mortgage: count rows with MENDR_RENEWAL_DATE within N days from TRUNC(SYSDATE) (takes precedence over from/to)
    if count_only and not search and not policy and mendr_window_days_int is not None and is_mortgage and _mortgage_view_q():
        try:
            conn = get_connection()
            cursor = conn.cursor()
            rexpr = _mortgage_renewal_date_sql_expr()
            wh = " WHERE " + _mortgage_renewal_sql_days_from_today_predicate(rexpr)
            cursor.execute(
                f"SELECT COUNT(*) FROM {target_view}{wh}",
                {"mendr_window_days": mendr_window_days_int},
            )
            total = cursor.fetchone()[0]
            cursor.close()
            conn.close()
            return jsonify({"data": [], "total": total})
        except Exception as e:
            return jsonify({"data": [], "total": 0, "error": str(e)})

    # Mortgage: count rows with renewal in [from, to] (inclusive calendar dates)
    if (
        count_only
        and not search
        and not policy
        and mendr_renewal_range
        and mendr_window_days_int is None
        and is_mortgage
        and _mortgage_view_q()
    ):
        try:
            conn = get_connection()
            cursor = conn.cursor()
            rexpr = _mortgage_renewal_date_sql_expr()
            wh = " WHERE " + _mortgage_renewal_sql_range_predicate(rexpr)
            cursor.execute(
                f"SELECT COUNT(*) FROM {target_view}{wh}",
                {"mendr_renewal_from": mendr_renewal_from, "mendr_renewal_to": mendr_renewal_to},
            )
            total = cursor.fetchone()[0]
            cursor.close()
            conn.close()
            return jsonify({"data": [], "total": total})
        except Exception as e:
            return jsonify({"data": [], "total": 0, "error": str(e)})

    # Mortgage: count rows with renewal on a single day (date picker / legacy mendr_renewal_on)
    if count_only and not search and not policy and mendr_renewal_on and is_mortgage and _mortgage_view_q():
        try:
            conn = get_connection()
            cursor = conn.cursor()
            rexpr = _mortgage_renewal_date_sql_expr()
            wh = f" WHERE TRUNC({rexpr}) = TO_DATE(:mendr_renewal_on, 'YYYY-MM-DD')"
            cursor.execute(f"SELECT COUNT(*) FROM {target_view}{wh}", {"mendr_renewal_on": mendr_renewal_on})
            total = cursor.fetchone()[0]
            cursor.close()
            conn.close()
            return jsonify({"data": [], "total": total})
        except Exception as e:
            return jsonify({"data": [], "total": 0, "error": str(e)})

    # Count-only request: return accurate total for dashboard (no rows fetched)
    if count_only and not search and not policy and not mendr_renewal_on and not mendr_renewal_range and mendr_window_days_int is None:
        try:
            conn = get_connection()
            cursor = conn.cursor()
            if is_group and USE_GROUP_AGGREGATE:
                gcol = _get_group_by_column()
                cursor.execute(f"SELECT COUNT(DISTINCT {gcol}) FROM {target_view}")
            else:
                cursor.execute(f"SELECT COUNT(*) FROM {target_view}")
            total = cursor.fetchone()[0]
            cursor.close()
            conn.close()
            return jsonify({"data": [], "total": total})
        except Exception:
            est = int(os.environ.get("ERP_CLIENTS_GROUP_ESTIMATED_TOTAL", "2383") if is_group else os.environ.get("ERP_CLIENTS_ESTIMATED_TOTAL", "10536"))
            return jsonify({"data": [], "total": est})

    import time
    max_retries = 3
    _fallback_env = os.environ.get("ERP_GROUP_FALLBACK_TO_INDIVIDUAL", "false").lower() in ("1", "true", "yes")
    # Only fall back when user searches a policy (policy may be in either view). NEVER fall back for list view
    # – otherwise Individual policies (e.g. IL-AEP-*) get mixed into Group Life and count is wrong.
    use_group_fallback = is_group and _fallback_env and bool(policy or search)
    use_policy_search_fallback = is_group and bool(policy or search)
    tried_fallback = [False]  # use list to allow mutation in nested scope
    policy_search_fallback = [False]  # policy search returned 0 from Group; try Individual with no product filter

    def build_query_state():
        use_ind = (USE_GROUP_FROM_INDIVIDUAL or (use_group_fallback and tried_fallback[0]))
        if is_group and use_ind:
            av = INDIVIDUAL_VIEW
            ac = _merge_individual_product_fallback_columns([c.strip() for c in COLS.split(",")])
            ao = ORDER_COL
            if policy_search_fallback[0]:
                return av, ac, ao, {}, None
            grp_where, grp_bind = get_group_product_filter()
            return av, ac, ao, grp_bind, grp_where
        return target_view, list(columns), order_col, {}, None

    for attempt in range(max_retries):
        conn = None
        try:
            actual_view, actual_columns, actual_order, extra_bind, grp_where_str = build_query_state()
            # When Group Life uses Individual view, use Individual search columns (POLICY_NUMBER etc)
            if is_group and actual_view == INDIVIDUAL_VIEW:
                scols = [c.strip() for c in SEARCH_COLS.split(",") if c.strip()]
            elif policy_search_fallback[0]:
                scols = [c.strip() for c in SEARCH_COLS.split(",") if c.strip()]
            else:
                scols = search_columns

            conn = get_connection()
            cursor = conn.cursor()

            where_clause = ""
            bind = dict(extra_bind)
            conditions = []
            if policy:
                if is_group and actual_view == target_view:
                    conditions.append(f"{_get_group_by_column()} = :policy")
                elif is_mortgage and actual_view == target_view:
                    conditions.append(f"{_get_group_by_column_for_view(target_view)} = :policy")
                else:
                    conditions.append(f"{POLICY_COL} = :policy")
                bind["policy"] = policy
            if search and scols:
                search_conds = [f"{c} LIKE :search" for c in scols]
                conditions.append("(" + " OR ".join(search_conds) + ")")
                bind["search"] = f"%{search}%"
            if is_mortgage and mendr_window_days_int is not None and actual_view == target_view:
                rexpr = _mortgage_renewal_date_sql_expr()
                conditions.append(_mortgage_renewal_sql_days_from_today_predicate(rexpr))
                bind["mendr_window_days"] = mendr_window_days_int
            elif is_mortgage and mendr_renewal_range and actual_view == target_view:
                rexpr = _mortgage_renewal_date_sql_expr()
                conditions.append(_mortgage_renewal_sql_range_predicate(rexpr))
                bind["mendr_renewal_from"] = mendr_renewal_from
                bind["mendr_renewal_to"] = mendr_renewal_to
            elif is_mortgage and mendr_renewal_on and actual_view == target_view:
                rexpr = _mortgage_renewal_date_sql_expr()
                conditions.append(f"TRUNC({rexpr}) = TO_DATE(:mendr_renewal_on, 'YYYY-MM-DD')")
                bind["mendr_renewal_on"] = mendr_renewal_on
            if grp_where_str:
                conditions.append(grp_where_str.strip())
            if conditions:
                where_clause = " WHERE " + " AND ".join(conditions)

            if (
                mortgage_upcoming
                and is_mortgage
                and not policy
                and not search
                and mendr_window_days_int is None
                and not mendr_renewal_range
                and not mendr_renewal_on
            ):
                cursor.close()
                conn.close()
                return jsonify({
                    "data": [],
                    "total": 0,
                    "error": "Upcoming renewals require mendr_window_days or mendr_renewal_from / mendr_renewal_to.",
                })

            # Accurate total when no search (avoids wrong estimates on Clients page)
            accurate_total = None
            if not search and not policy:
                try:
                    if is_group and USE_GROUP_AGGREGATE and actual_view == target_view:
                        gcol = _get_group_by_column()
                        cursor.execute(
                            f"SELECT COUNT(DISTINCT {gcol}) FROM {actual_view}{where_clause}",
                            bind,
                        )
                    else:
                        cursor.execute(f"SELECT COUNT(*) FROM {actual_view}{where_clause}", bind)
                    accurate_total = cursor.fetchone()[0]
                except Exception:
                    pass

            # Use ROWNUM for Oracle 11g compatibility (OFFSET FETCH requires 12c+)
            bind["end_row"] = offset + limit
            bind["start_row"] = offset

            # Group Life: dedicated path - aggregate by policy, use discovered columns
            use_aggregate = is_group and USE_GROUP_AGGREGATE and actual_view == target_view
            use_mortgage_select_star = (
                is_mortgage
                and bool(_mortgage_view_q())
                and os.environ.get("ERP_CLIENTS_MORTGAGE_USE_SELECT_STAR", "true").lower() in ("1", "true", "yes")
            )
            if use_aggregate:
                try:
                    data, total = _get_group_life_clients(limit, offset, search, policy)
                    if is_group and not tried_fallback[0] and len(data) == 0 and (policy or search) and (use_group_fallback or use_policy_search_fallback):
                        tried_fallback[0] = True
                        policy_search_fallback[0] = True
                        continue
                    total = accurate_total if accurate_total is not None else total
                    resp = {"data": data, "total": total}
                    if debug_mode and len(data) == 0 and (policy or search):
                        resp["_debug"] = {
                            "group_view": GROUP_VIEW,
                            "columns_in_view": sorted(_get_group_view_columns()),
                            "find_policy_url": f"/clients/find-policy?policy={policy or search}",
                        }
                    return jsonify(resp)
                except oracledb.DatabaseError as e:
                    if is_group and use_group_fallback and not tried_fallback[0]:
                        tried_fallback[0] = True
                        continue
                    raise
            # Group view: SELECT * discovers columns at runtime. Group-shaped mortgage view uses the same pattern.
            use_select_star = (
                (is_group and USE_GROUP_SELECT_STAR and actual_view == target_view and not USE_GROUP_AGGREGATE)
                or (use_mortgage_select_star and actual_view == target_view)
            )
            if use_select_star:
                try:
                    _inner_order = "1"
                    if is_mortgage and actual_view == target_view and (
                        mendr_window_days_int is not None or mendr_renewal_range or mendr_renewal_on
                    ):
                        _inner_order = f"TRUNC({_mortgage_renewal_date_sql_expr()})"
                    sql = f"""
                        SELECT * FROM (
                            SELECT a.*, ROWNUM rnum FROM (
                                SELECT * FROM {actual_view}{where_clause} ORDER BY {_inner_order}
                            ) a WHERE ROWNUM <= :end_row
                        ) WHERE rnum > :start_row
                    """
                    cursor.execute(sql, bind)
                    rows = cursor.fetchall()
                    if cursor.description:
                        actual_columns = [d[0] for d in cursor.description if d[0] and str(d[0]).upper() != "RNUM"]
                    else:
                        actual_columns = []
                    data = [row_to_client(r[:len(actual_columns)], actual_columns, life_system_override=life_system_tag) for r in rows]
                    est = int(os.environ.get("ERP_CLIENTS_GROUP_ESTIMATED_TOTAL", os.environ.get("ERP_CLIENTS_ESTIMATED_TOTAL", "1000")))
                    total = accurate_total if accurate_total is not None else (est if len(data) == limit else offset + len(data))
                    cursor.close()
                    conn.close()
                    # If group view returned 0 rows, fall back to individual view + filter
                    if is_group and use_group_fallback and not tried_fallback[0] and len(data) == 0 and not search and not policy:
                        tried_fallback[0] = True
                        continue
                    return jsonify({"data": data, "total": total})
                except oracledb.DatabaseError as e:
                    err_str = str(e)
                    if is_group and use_group_fallback and not tried_fallback[0]:
                        tried_fallback[0] = True
                        continue
                    raise
            else:
                cols_unquoted = ",".join(actual_columns)
                try:
                    sql = f"""
                        SELECT * FROM (
                            SELECT a.*, ROWNUM rnum FROM (
                                SELECT {cols_unquoted} FROM {actual_view}{where_clause} ORDER BY {actual_order}
                            ) a WHERE ROWNUM <= :end_row
                        ) WHERE rnum > :start_row
                    """
                    cursor.execute(sql, bind)
                except oracledb.DatabaseError as e:
                    err_str = str(e)
                    if "ORA-00904" in err_str or "invalid identifier" in err_str.lower():
                        # Fallback: try column sets; group view may use different names.
                        # Never use a set without PRODUCT for Individual (and most) views — last resort
                        # ("...POL_PREPARED_BY,INTERMEDIARY" only) caused full lists with blank PRODUCT.
                        fallbacks = [
                            ("POLICY_NUMBER,LIFE_ASSURED,PRODUCT,POL_PREPARED_BY,INTERMEDIARY,STATUS,KRA_PIN,PRP_DOB,MATURITY_DATE,ID_NO,PHONE_NO,CHECKOFF,BAL", "PRODUCT"),
                            ("POLICY_NUMBER,LIFE_ASSUR,PRODUCT,POL_PREPARED_BY,INTERMEDIARY,STATUS,KRA_PIN,PRP_DOB,MATURITY_DATE,ID_NO,PHONE_NO,CHECKOFF,BAL", "PRODUCT"),
                            ("POLICY_NUMBER,LIFE_ASSURED,PRODUCT,POL_PREPARED_BY,INTERMEDIARY,STATUS,KRA_PIN,PRP_DOB,MATURITY_DATE,ID_NO,PHONE_NO", "PRODUCT"),
                            ("POLICY_NUMBER,LIFE_ASSURED,PRODUCT,POL_PREPARED_BY,INTERMEDIARY,STATUS,KRA_PIN", "PRODUCT"),
                            ("POLICY_NUMBER,SCHEME_NAME,POL_PREPARED_BY,INTERMEDIARY,STATUS,KRA_PIN", "SCHEME_NAME"),
                            ("POLICY_NUMBER,MEMBER_NAME,PRODUCT,POL_PREPARED_BY,INTERMEDIARY,STATUS,KRA_PIN", "PRODUCT"),
                            ("POLICY_NUMBER,PRODUCT,POL_PREPARED_BY,INTERMEDIARY,STATUS,KRA_PIN", "PRODUCT"),
                            ("POLICY_NUMBER,PRODUCT,POL_PREPARED_BY,INTERMEDIARY", "PRODUCT"),
                        ]
                        if use_group_columns:
                            fallbacks.append(("POLICY_NUMBER,POL_PREPARED_BY,INTERMEDIARY", "POLICY_NUMBER"))
                        for fb in fallbacks:
                            try:
                                col_list = fb[0]
                                fb_order = fb[1] if len(fb) > 1 else actual_order
                                actual_columns = [c.strip() for c in col_list.split(",")]
                                cols_unquoted = ",".join(actual_columns)
                                order_str = fb_order
                                sql = f"""
                                    SELECT * FROM (
                                        SELECT a.*, ROWNUM rnum FROM (
                                            SELECT {cols_unquoted} FROM {actual_view}{where_clause} ORDER BY {order_str}
                                        ) a WHERE ROWNUM <= :end_row
                                    ) WHERE rnum > :start_row
                                """
                                cursor.execute(sql, bind)
                                break
                            except oracledb.DatabaseError:
                                continue
                        else:
                            if search and search_columns:
                                group_search = [["POLICY_NUMBER", "POL_PREPARED_BY", "INTERMEDIARY", "KRA_PIN"], ["POLICY_NUMBER"]]
                                ind_search = [["POLICY_NUMBER", "LIFE_ASSURED", "POL_PREPARED_BY", "INTERMEDIARY", "KRA_PIN"], ["POLICY_NUMBER", "LIFE_ASSURED"]]
                                for search_cols_try in (group_search if use_group_columns else ind_search):
                                    try:
                                        conds = [f"{c} LIKE :search" for c in search_cols_try]
                                        where_alt = " WHERE (" + " OR ".join(conds) + ")"
                                        sql = f"""
                                            SELECT * FROM (
                                                SELECT a.*, ROWNUM rnum FROM (
                                                    SELECT {cols_unquoted} FROM {actual_view}{where_alt} ORDER BY {actual_order}
                                                ) a WHERE ROWNUM <= :end_row
                                            ) WHERE rnum > :start_row
                                        """
                                        cursor.execute(sql, bind)
                                        break
                                    except oracledb.DatabaseError:
                                        continue
                                else:
                                    pass
                            try:
                                where_star = where_clause if not search else ""
                                sql = f"""
                                    SELECT * FROM (
                                        SELECT a.*, ROWNUM rnum FROM (
                                            SELECT * FROM {actual_view}{where_star} ORDER BY 1
                                        ) a WHERE ROWNUM <= :end_row
                                    ) WHERE rnum > :start_row
                                """
                                cursor.execute(sql, bind)
                                if cursor.description:
                                    actual_columns = [d[0] for d in cursor.description if d[0] and str(d[0]).upper() != "RNUM"]
                                else:
                                    actual_columns = []
                            except oracledb.DatabaseError:
                                raise
                    else:
                        raise

            rows = cursor.fetchall()
            # If policy lookup returned no rows, try POLICY_NO (view may use numeric id)
            if policy and not rows and POLICY_COL == "POLICY_NUMBER":
                try:
                    where_clause = " WHERE POLICY_NO = :policy"
                    cols_retry = ",".join(actual_columns)
                    sql = f"""
                        SELECT * FROM (
                            SELECT a.*, ROWNUM rnum FROM (
                                SELECT {cols_retry} FROM {actual_view}{where_clause} ORDER BY {actual_order}
                            ) a WHERE ROWNUM <= :end_row
                        ) WHERE rnum > :start_row
                    """
                    cursor.execute(sql, bind)
                    rows = cursor.fetchall()
                except oracledb.DatabaseError:
                    pass

            # ROWNUM subquery adds rnum column; use only data columns
            data = [row_to_client(r[:len(actual_columns)], actual_columns, life_system_override=life_system_tag) for r in rows]
            # For policy lookup: use requested policy in response
            if policy and data:
                for rec in data:
                    rec["policy_number"] = policy
                    rec["policy_no"] = policy
                    break

            # Total count - use accurate total when available (no search), else estimate
            est = int(os.environ.get("ERP_CLIENTS_ESTIMATED_TOTAL", "10536"))
            total = accurate_total if accurate_total is not None else (est if len(data) == limit else offset + len(data))

            cursor.close()
            conn.close()

            # When system=group and group view returned empty, fallback to individual view + product filter
            if is_group and use_group_fallback and not tried_fallback[0] and len(data) == 0:
                tried_fallback[0] = True
                continue
            resp = {"data": data, "total": total}
            if policy_search_fallback[0] and data:
                resp["_fallback_individual"] = True
            return jsonify(resp)

        except oracledb.DatabaseError as e:
            err_str = str(e)
            if conn:
                try:
                    conn.close()
                except Exception:
                    pass
            # When system=group and group view failed (e.g. ORA-00942), fallback to individual + filter
            if is_group and use_group_fallback and not tried_fallback[0]:
                tried_fallback[0] = True
                continue
            # Retry on ORA-03113 (end-of-file on communication channel)
            if ("ORA-03113" in err_str or "03113" in err_str) and attempt < max_retries - 1:
                time.sleep(2 * (attempt + 1))
                continue
            return jsonify({
                "data": [],
                "total": 0,
                "error": f"Oracle error: {err_str}"
            }), 503

    # Should not reach here; fallback if loop exits without return
    return jsonify({"data": [], "total": 0, "error": "Request failed after retries"}), 503


@app.route("/columns", methods=["GET"])
@app.route("/clients/columns", methods=["GET"])
def view_columns():
    """Return actual column names from the view (helps debug null data). ?view=group|mortgage|group_pension or default individual."""
    if not PASSWORD:
        return jsonify({"error": "ORACLE_PASSWORD not set"}), 503
    v = (request.args.get("view") or "").strip().lower()
    if v == "group":
        target = GROUP_VIEW
        # Extract table name for all_tab_columns (e.g. TQ_LMS.LMS_GROUP_CRM_VIEW -> LMS_GROUP_CRM_VIEW)
        parts = target.split(".")
        tname = parts[-1].upper() if parts else "LMS_GROUP_CRM_VIEW"
        owner = parts[0].upper() if len(parts) > 1 else VIEW_SCHEMA.upper()
    elif v == "mortgage" and _mortgage_view_q():
        target = _mortgage_view_q()
        parts = target.split(".")
        tname = parts[-1].upper() if parts else ""
        owner = parts[0].upper() if len(parts) > 1 else VIEW_SCHEMA.upper()
    elif v == "group_pension" and _group_pension_view_q():
        target = _group_pension_view_q()
        parts = target.split(".")
        tname = parts[-1].upper() if parts else ""
        owner = parts[0].upper() if len(parts) > 1 else VIEW_SCHEMA.upper()
    else:
        target = VIEW
        tname = VIEW_NAME.upper()
        owner = VIEW_SCHEMA.upper()
    try:
        conn = get_connection()
        cursor = conn.cursor()
        cursor.execute("""
            SELECT column_name FROM all_tab_columns
            WHERE table_name = :tname AND owner = :owner
            ORDER BY column_id
        """, {"tname": tname, "owner": owner})
        cols = [r[0] for r in cursor.fetchall()]
        cursor.close()
        conn.close()
        return jsonify({"view": target, "columns": cols})
    except Exception as e:
        return jsonify({"error": str(e), "view": target}), 500


@app.route("/clients/debug", methods=["GET"])
def clients_debug():
    """Debug: return raw API response for a policy (no auth). Use ?policy=XXX to verify data."""
    policy = (request.args.get("policy") or "").strip()
    if not policy:
        return jsonify({"error": "Add ?policy=090807694 to see raw data for that policy"}), 400
    limit = 1
    offset = 0
    if not PASSWORD:
        return jsonify({"data": [], "error": "ORACLE_PASSWORD not set"}), 503
    try:
        want_raw = (request.args.get("raw") or "").strip().lower() in ("1", "true", "yes")
        conn = get_connection()
        cursor = conn.cursor()
        if want_raw:
            cursor.execute(
                "SELECT SYS_CONTEXT('USERENV','SESSION_USER'), SYS_CONTEXT('USERENV','CURRENT_SCHEMA') FROM dual"
            )
            sess = cursor.fetchone()
            oracle_user = str(sess[0]) if sess and sess[0] is not None else None
            oracle_schema = str(sess[1]) if sess and sess[1] is not None else None
            where_clause = f" WHERE {POLICY_COL} = :policy"
            sql = f"SELECT * FROM {INDIVIDUAL_VIEW}{where_clause}"
            cursor.execute(sql, {"policy": policy})
            desc = cursor.description or []
            cols = [d[0] for d in desc]
            row = cursor.fetchone()
            raw_non_null = {}
            product_probe = {}
            if row:
                for i, c in enumerate(cols):
                    if not c or str(c).upper() == "RNUM":
                        continue
                    v = row[i] if i < len(row) else None
                    coerced = _coerce_oracle_scalar_to_str(v)
                    if str(c).strip().upper() == "PRODUCT":
                        product_probe = {
                            "python_type": type(v).__name__ if v is not None else None,
                            "coerced": coerced,
                        }
                    if coerced:
                        raw_non_null[str(c)] = coerced[:400]
            cursor.close()
            conn.close()
            note = (
                "If Toad shows PRODUCT text but coerced is null, compare CONNECTION user/schema here "
                "to Toad (synonym may point at a different view). CLOB columns need .read() — now handled in API."
            )
            return jsonify(
                {
                    "policy": policy,
                    "view": INDIVIDUAL_VIEW,
                    "oracle_session_user": oracle_user,
                    "oracle_current_schema": oracle_schema,
                    "product_column_probe": product_probe,
                    "raw_non_null": raw_non_null,
                    "note": note,
                }
            )

        columns = [c.strip() for c in COLS.split(",")]
        columns = _merge_individual_product_fallback_columns(columns)
        cols_unquoted = ",".join(columns)
        where_clause = f" WHERE {POLICY_COL} = :policy"
        sql = f"SELECT {cols_unquoted} FROM {INDIVIDUAL_VIEW}{where_clause}"
        cursor.execute(sql, {"policy": policy})
        rows = cursor.fetchall()
        data = [row_to_client(r, columns) for r in rows] if rows else []
        cursor.close()
        conn.close()
        return jsonify({"policy": policy, "data": data, "columns_used": columns})
    except Exception as e:
        return jsonify({"policy": policy, "data": [], "error": str(e)}), 500


@app.route("/products", methods=["GET"])
@app.route("/clients/products", methods=["GET"])
def distinct_products():
    """Return distinct PRODUCT values. Same as /clients?products=1"""
    return _get_distinct_products()


@app.route("/group-schema", methods=["GET"])
def group_schema():
    """
    DIAGNOSTIC: Returns raw column names and first row from LMS_GROUP_CRM_VIEW.
    Use this to find the CORRECT column for policy number and product.
    Open: http://localhost:5000/group-schema
    Then set ERP_GROUP_POLICY_COLUMN and ERP_GROUP_PRODUCT_COLUMN in .env to match your view.
    """
    if not PASSWORD:
        return jsonify({"error": "ORACLE_PASSWORD not set"}), 503
    try:
        conn = get_connection()
        cursor = conn.cursor()
        cursor.execute(f"SELECT * FROM (SELECT * FROM {GROUP_VIEW} WHERE ROWNUM <= 1)")
        rows = cursor.fetchall()
        col_names = [d[0] for d in cursor.description] if cursor.description else []
        first_row = {}
        if rows and col_names:
            for i, name in enumerate(col_names):
                v = rows[0][i] if i < len(rows[0]) else None
                first_row[name] = str(v) if v is not None else None
        cursor.close()
        conn.close()
        return jsonify({
            "view": GROUP_VIEW,
            "columns": col_names,
            "first_row_sample": first_row,
            "config_used": {"ERP_GROUP_POLICY_COLUMN": POLICY_COL_GROUP, "ERP_GROUP_PRODUCT_COLUMN": PRODUCT_COL_GROUP},
            "how_to_fix": "Look at first_row_sample. Find which column has policy (e.g. GEMPPP0070) and which has product. Add to erp-clients-api/.env: ERP_GROUP_POLICY_COLUMN=<column>, ERP_GROUP_PRODUCT_COLUMN=<column>",
        })
    except Exception as e:
        return jsonify({"error": str(e), "view": GROUP_VIEW}), 500


@app.route("/group-test", methods=["GET"])
def group_test():
    """Debug: test group view and fallback (individual+checkoff)."""
    if not PASSWORD:
        return jsonify({"error": "ORACLE_PASSWORD not set"}), 503
    result = {"group_view": None, "fallback_checkoff": None, "group_view_count": None}
    try:
        conn = get_connection()
        cursor = conn.cursor()
        # 1. Count from group view
        try:
            cursor.execute(f"SELECT COUNT(*) FROM {GROUP_VIEW}")
            result["group_view_count"] = cursor.fetchone()[0]
        except Exception as e:
            result["group_view_error"] = str(e)
        # 2. Sample from group view
        try:
            cursor.execute(f"SELECT * FROM (SELECT * FROM {GROUP_VIEW} WHERE ROWNUM <= 3)")
            rows = cursor.fetchall()
            result["group_view"] = len(rows)
            if rows and cursor.description:
                result["group_columns"] = [d[0] for d in cursor.description]
        except Exception as e:
            result["group_view_sample_error"] = str(e)
        # 3. Group Life aggregate (one row per policy, SUM PRODUCTION_AMT) - canonical path
        try:
            data, total = _get_group_life_clients(5, 0, None, None)
            result["main_query_rows"] = len(data)
            result["main_query_columns"] = list(data[0].keys()) if data else []
            result["main_query_sample"] = data
            result["group_view_columns_discovered"] = list(_get_group_view_columns())
            result["group_by_column_used"] = _get_group_by_column()
        except Exception as e:
            result["main_query_error"] = str(e)
        # 4. Fallback: individual + checkoff (fixed LENGTH>0 for Oracle)
        try:
            grp_where, _ = get_group_product_filter()
            cursor.execute(f"""
                SELECT POLICY_NUMBER, LIFE_ASSURED, PRODUCT, CHECKOFF
                FROM {INDIVIDUAL_VIEW} WHERE {grp_where.strip()}
                AND ROWNUM <= 5
            """)
            rows = cursor.fetchall()
            result["fallback_checkoff"] = len(rows)
            result["fallback_sample"] = [list(r) for r in rows] if rows else []
        except Exception as e:
            result["fallback_error"] = str(e)
        cursor.close()
        conn.close()
    except Exception as e:
        result["error"] = str(e)
    return jsonify(result)


@app.route("/routes", methods=["GET"])
def list_routes():
    """List all registered routes - use to debug 404s."""
    rules = []
    for rule in app.url_map.iter_rules():
        rules.append({"rule": str(rule), "methods": list(rule.methods - {"HEAD", "OPTIONS"})})
    return jsonify({"routes": sorted(rules, key=lambda x: x["rule"])})


@app.route("/health", methods=["GET"])
def health():
    """Health check - tests Oracle connectivity."""
    if not PASSWORD:
        return jsonify({"status": "error", "message": "ORACLE_PASSWORD not set"}), 503
    try:
        conn = get_connection()
        conn.close()
        return jsonify({"status": "ok", "oracle": "connected"})
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 503


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    app.run(host="0.0.0.0", port=port, debug=os.environ.get("FLASK_DEBUG", "false").lower() == "true")
