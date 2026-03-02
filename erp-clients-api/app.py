#!/usr/bin/env python3
"""
ERP Clients API - Fetches clients from Oracle LMS_INDIVIDUAL_CRM_VIEW.
Deploy on a machine with reliable Oracle connectivity (e.g. where Toad works).
Laravel CRM fetches clients via this API when CLIENTS_VIEW_SOURCE=erp_http.
"""
import os
from pathlib import Path

# Load .env if present
_env = Path(__file__).parent / ".env"
if _env.exists():
    for line in _env.read_text().splitlines():
        line = line.strip()
        if line and not line.startswith("#") and "=" in line:
            k, _, v = line.partition("=")
            os.environ.setdefault(k.strip(), v.strip().strip('"').strip("'"))

from flask import Flask, request, jsonify

try:
    import oracledb
except ImportError:
    print("Install oracledb: pip install oracledb")
    exit(1)

app = Flask(__name__)

DSN = os.environ.get("ORACLE_DSN", "10.1.4.101:18032/PDBTQUEST")
USER = os.environ.get("ORACLE_USER", "TQ_LMS")
PASSWORD = os.environ.get("ORACLE_PASSWORD", "")
VIEW_SCHEMA = os.environ.get("ERP_VIEW_SCHEMA", "TQ_LMS")
VIEW_NAME = os.environ.get("ERP_CLIENTS_VIEW", "LMS_INDIVIDUAL_CRM_VIEW")
VIEW = f"{VIEW_SCHEMA}.{VIEW_NAME}" if VIEW_SCHEMA else VIEW_NAME
COLS_BASE = "POLICY_NUMBER,PRODUCT,POL_PREPARED_BY,INTERMEDIARY,STATUS,KRA_PIN"
# Oracle view uses LIFE_ASSURED (with D); LIFE_ASSUR may also exist
COLS_EXTENDED = "POLICY_NUMBER,LIFE_ASSURED,PRODUCT,POL_PREPARED_BY,INTERMEDIARY,STATUS,KRA_PIN,PRP_DOB,MATURITY_DATE,ID_NO,PHONE_NO,CHECKOFF,BAL"
COLS = os.environ.get("ERP_CLIENTS_LIST_COLUMNS", COLS_EXTENDED)
SEARCH_COLS = os.environ.get("ERP_CLIENTS_LIST_SEARCH_COLUMNS", "POLICY_NUMBER,LIFE_ASSURED,POL_PREPARED_BY,INTERMEDIARY,KRA_PIN,ID_NO,PHONE_NO")
ORDER_COL = os.environ.get("ERP_CLIENTS_LIST_ORDER", "PRODUCT")
POLICY_COL = os.environ.get("ERP_POLICY_COLUMN", "POLICY_NUMBER")


def parse_date(val):
    if val is None:
        return None
    if hasattr(val, "strftime"):
        return val.strftime("%Y-%m-%d")
    s = str(val).strip()
    return s[:10] if s and len(s) >= 10 else (s or None)


def row_to_client(row, columns):
    """Convert Oracle row to API response format (snake_case)."""
    d = {}
    for i, col in enumerate(columns):
        v = row[i] if i < len(row) else None
        key = col.lower().replace(" ", "_").replace("-", "_")
        if key in ("prp_dob", "maturity", "maturity_date", "effective_date") and v is not None:
            d[key] = parse_date(v)
        else:
            d[key] = str(v).strip() if v is not None else None
    # BAL = balance, map to paid_mat_amt for Laravel
    if "bal" in d and d["bal"] is not None:
        d["paid_mat_amt"] = d["bal"]
    # Normalize maturity_date -> maturity for Laravel
    if "maturity_date" in d and "maturity" not in d:
        d["maturity"] = d["maturity_date"]
    # Normalize life_assured -> life_assur for Laravel (Oracle uses LIFE_ASSURED)
    if "life_assured" in d:
        d["life_assur"] = d["life_assured"]
    elif "life_assur" in d:
        d["life_assured"] = d["life_assur"]
    # Normalize phone_number -> phone_no, id_number -> id_no
    if "phone_number" in d and "phone_no" not in d:
        d["phone_no"] = d["phone_number"]
    elif "phone_no" in d and "phone_number" not in d:
        d["phone_number"] = d["phone_no"]
    if "id_number" in d and "id_no" not in d:
        d["id_no"] = d["id_number"]
    elif "id_no" in d and "id_number" not in d:
        d["id_number"] = d["id_no"]
    return d


def get_connection():
    return oracledb.connect(user=USER, password=PASSWORD, dsn=DSN)


@app.route("/clients", methods=["GET"])
@app.route("/api/clients", methods=["GET"])
def get_clients():
    """GET /clients?limit=50&offset=0&search=term&policy=XXX&count_only=1 (exact policy lookup)"""
    count_only = request.args.get("count_only", "").strip() in ("1", "true", "yes")
    limit = min(int(request.args.get("limit", 50)), 100)
    offset = max(0, int(request.args.get("offset", 0)))
    search = (request.args.get("search") or "").strip()
    policy = (request.args.get("policy") or "").strip()

    if not PASSWORD:
        return jsonify({"data": [], "total": 0, "error": "ORACLE_PASSWORD not set"}), 503

    # Count-only request: return accurate total for dashboard (no rows fetched)
    if count_only and not search and not policy:
        try:
            conn = get_connection()
            cursor = conn.cursor()
            cursor.execute(f"SELECT COUNT(*) FROM {VIEW}")
            total = cursor.fetchone()[0]
            cursor.close()
            conn.close()
            return jsonify({"data": [], "total": total})
        except Exception as e:
            # Fallback to estimate on Oracle timeout/slow COUNT
            est = int(os.environ.get("ERP_CLIENTS_ESTIMATED_TOTAL", "10536"))
            return jsonify({"data": [], "total": est})

    columns = [c.strip() for c in COLS.split(",")]
    search_columns = [c.strip() for c in SEARCH_COLS.split(",") if c.strip()]

    import time
    max_retries = 3

    for attempt in range(max_retries):
        conn = None
        try:
            conn = get_connection()
            cursor = conn.cursor()

            cols_str = ",".join(f'"{c}"' for c in columns)
            order_col = ORDER_COL or "PRODUCT"

            where_clause = ""
            bind = {}
            if policy:
                where_clause = f" WHERE {POLICY_COL} = :policy"
                bind["policy"] = policy
            elif search and search_columns:
                conditions = [f"{c} LIKE :search" for c in search_columns]
                where_clause = " WHERE (" + " OR ".join(conditions) + ")"
                bind["search"] = f"%{search}%"

            # Use ROWNUM for Oracle 11g compatibility (OFFSET FETCH requires 12c+)
            # Use unquoted identifiers (Oracle folds to uppercase) - more compatible with views
            try:
                cols_unquoted = ",".join(columns)
                sql = f"""
                    SELECT * FROM (
                        SELECT a.*, ROWNUM rnum FROM (
                            SELECT {cols_unquoted} FROM {VIEW}{where_clause} ORDER BY {order_col}
                        ) a WHERE ROWNUM <= :end_row
                    ) WHERE rnum > :start_row
                """
                bind["end_row"] = offset + limit
                bind["start_row"] = offset
                cursor.execute(sql, bind)
            except oracledb.DatabaseError as e:
                err_str = str(e)
                if "ORA-00904" in err_str or "invalid identifier" in err_str.lower():
                    # Fallback: try column sets, then try simpler search columns if search is used
                    fallbacks = [
                        ("POLICY_NUMBER,LIFE_ASSURED,PRODUCT,POL_PREPARED_BY,INTERMEDIARY,STATUS,KRA_PIN,PRP_DOB,MATURITY_DATE,ID_NO,PHONE_NO,CHECKOFF,BAL", False),
                        ("POLICY_NUMBER,LIFE_ASSUR,PRODUCT,POL_PREPARED_BY,INTERMEDIARY,STATUS,KRA_PIN,PRP_DOB,MATURITY_DATE,ID_NO,PHONE_NO,CHECKOFF,BAL", False),
                        COLS_BASE,
                    ]
                    for fb in fallbacks:
                        try:
                            if isinstance(fb, tuple):
                                col_list, use_quotes = fb
                            else:
                                col_list, use_quotes = fb, True
                            columns = [c.strip() for c in col_list.split(",")]
                            cols_str = ",".join(f'"{c}"' for c in columns) if use_quotes else ",".join(columns)
                            order_str = f'"{order_col}"' if use_quotes else order_col
                            sql = f"""
                                SELECT * FROM (
                                    SELECT a.*, ROWNUM rnum FROM (
                                        SELECT {cols_str} FROM {VIEW}{where_clause} ORDER BY {order_str}
                                    ) a WHERE ROWNUM <= :end_row
                                ) WHERE rnum > :start_row
                            """
                            cursor.execute(sql, bind)
                            break
                        except oracledb.DatabaseError:
                            continue
                    else:
                        # Search may fail on ID_NO/PHONE_NO - retry with core search columns only
                        if search and search_columns:
                            for search_cols_try in [
                                ["POLICY_NUMBER", "LIFE_ASSURED", "POL_PREPARED_BY", "INTERMEDIARY", "KRA_PIN"],
                                ["POLICY_NUMBER", "LIFE_ASSURED"],
                            ]:
                                try:
                                    conds = [f"{c} LIKE :search" for c in search_cols_try]
                                    where_alt = " WHERE (" + " OR ".join(conds) + ")"
                                    sql = f"""
                                        SELECT * FROM (
                                            SELECT a.*, ROWNUM rnum FROM (
                                                SELECT {cols_unquoted} FROM {VIEW}{where_alt} ORDER BY {order_col}
                                            ) a WHERE ROWNUM <= :end_row
                                        ) WHERE rnum > :start_row
                                    """
                                    cursor.execute(sql, bind)
                                    break
                                except oracledb.DatabaseError:
                                    continue
                            else:
                                raise
                        else:
                            raise
                else:
                    raise

            rows = cursor.fetchall()
            # If policy lookup returned no rows, try POLICY_NO (view may use numeric id)
            if policy and not rows and POLICY_COL == "POLICY_NUMBER":
                try:
                    where_clause = " WHERE POLICY_NO = :policy"
                    cols_retry = ",".join(columns)
                    sql = f"""
                        SELECT * FROM (
                            SELECT a.*, ROWNUM rnum FROM (
                                SELECT {cols_retry} FROM {VIEW}{where_clause} ORDER BY {order_col}
                            ) a WHERE ROWNUM <= :end_row
                        ) WHERE rnum > :start_row
                    """
                    cursor.execute(sql, bind)
                    rows = cursor.fetchall()
                except oracledb.DatabaseError:
                    pass

            # ROWNUM subquery adds rnum column; use only data columns
            data = [row_to_client(r[:len(columns)], columns) for r in rows]
            # For policy lookup: use requested policy in response
            if policy and data:
                for rec in data:
                    rec["policy_number"] = policy
                    rec["policy_no"] = policy
                    break

            # Total count - skip slow COUNT on large views; use estimate (faster)
            # Set ERP_CLIENTS_ESTIMATED_TOTAL to match actual LMS_INDIVIDUAL_CRM_VIEW row count
            est = int(os.environ.get("ERP_CLIENTS_ESTIMATED_TOTAL", "10536"))
            total = est if len(data) == limit else offset + len(data)

            cursor.close()
            conn.close()

            return jsonify({"data": data, "total": total})

        except oracledb.DatabaseError as e:
            err_str = str(e)
            if conn:
                try:
                    conn.close()
                except Exception:
                    pass
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
    """Return actual column names from LMS_INDIVIDUAL_CRM_VIEW (helps debug null data)."""
    if not PASSWORD:
        return jsonify({"error": "ORACLE_PASSWORD not set"}), 503
    try:
        conn = get_connection()
        cursor = conn.cursor()
        cursor.execute("""
            SELECT column_name FROM all_tab_columns
            WHERE table_name = :tname AND owner = :owner
            ORDER BY column_id
        """, {"tname": VIEW_NAME.upper(), "owner": VIEW_SCHEMA.upper()})
        cols = [r[0] for r in cursor.fetchall()]
        cursor.close()
        conn.close()
        return jsonify({"view": VIEW, "columns": cols})
    except Exception as e:
        return jsonify({"error": str(e), "view": VIEW}), 500


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
        conn = get_connection()
        cursor = conn.cursor()
        columns = [c.strip() for c in COLS.split(",")]
        cols_unquoted = ",".join(columns)
        where_clause = f" WHERE {POLICY_COL} = :policy"
        sql = f"SELECT {cols_unquoted} FROM {VIEW}{where_clause}"
        cursor.execute(sql, {"policy": policy})
        rows = cursor.fetchall()
        data = [row_to_client(r, columns) for r in rows] if rows else []
        cursor.close()
        conn.close()
        return jsonify({"policy": policy, "data": data, "columns_used": columns})
    except Exception as e:
        return jsonify({"policy": policy, "data": [], "error": str(e)}), 500


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
