#!/usr/bin/env python3
"""
Sync LMS_INDIVIDUAL_CRM_VIEW from Oracle to Laravel erp_clients_cache.
Run this on a machine with Oracle client installed (e.g. where Toad works).

Requires: pip install oracledb requests

Usage:
  python sync_erp_clients.py

Environment variables:
  ORACLE_DSN     - e.g. 10.1.4.101:18032/PDBTQUEST
  ORACLE_USER    - e.g. TQ_LMS
  ORACLE_PASSWORD
  LARAVEL_URL    - e.g. https://geminialife.co.ke
  ERP_SYNC_TOKEN - from .env ERP_SYNC_TOKEN
"""
import os
import sys
import json

try:
    import oracledb
except ImportError:
    print("Install oracledb: pip install oracledb")
    sys.exit(1)
try:
    import requests
except ImportError:
    print("Install requests: pip install requests")
    sys.exit(1)

DSN = os.environ.get("ORACLE_DSN", "10.1.4.101:18032/PDBTQUEST")
USER = os.environ.get("ORACLE_USER", "TQ_LMS")
PASSWORD = os.environ.get("ORACLE_PASSWORD", "")
LARAVEL_URL = os.environ.get("LARAVEL_URL", "").rstrip("/")
TOKEN = os.environ.get("ERP_SYNC_TOKEN", "")
BATCH = 1000
VIEW = "LMS_INDIVIDUAL_CRM_VIEW"
# Columns - try extended; if Oracle returns invalid identifier, use base only
COLS_BASE = "POLICY_NUMBER,PRODUCT,POL_PREPARED_BY,INTERMEDIARY,STATUS,KRA_PIN"
COLS_EXTENDED = COLS_BASE + ",PRP_DOB,EFFECTIVE_DATE,MATURITY"
COLS = os.environ.get("ERP_CLIENTS_LIST_COLUMNS", COLS_EXTENDED)


def parse_date(val):
    if val is None:
        return None
    if hasattr(val, "strftime"):
        return val.strftime("%Y-%m-%d")
    s = str(val).strip()
    if not s:
        return None
    return s[:10] if len(s) >= 10 else s


def row_to_dict(row, cols):
    out = {}
    for i, c in enumerate(cols):
        v = row[i] if i < len(row) else None
        key = c.lower().replace(" ", "_") if isinstance(c, str) else c
        if key in ("prp_dob", "maturity", "effective_date") and v is not None:
            out[key] = parse_date(v)
        else:
            out[key] = str(v).strip() if v is not None else None
    return out


def main():
    if not PASSWORD:
        print("Set ORACLE_PASSWORD")
        sys.exit(1)
    if not LARAVEL_URL:
        print("Set LARAVEL_URL (e.g. https://geminialife.co.ke)")
        sys.exit(1)
    if not TOKEN:
        print("Set ERP_SYNC_TOKEN (from Laravel .env)")
        sys.exit(1)

    url = f"{LARAVEL_URL}/api/admin/erp-clients-import"
    headers = {"X-API-Key": TOKEN, "Content-Type": "application/json"}

    print(f"Connecting to Oracle {DSN}...")
    conn = oracledb.connect(user=USER, password=PASSWORD, dsn=DSN)
    cursor = conn.cursor()

    cols = COLS
    try:
        print(f"Querying {VIEW}...")
        cursor.execute(f"SELECT {cols} FROM {VIEW} ORDER BY PRODUCT")
    except oracledb.DatabaseError as e:
        err = str(e).upper()
        if "INVALID IDENTIFIER" in err or "ORA-00904" in err:
            print("  Some columns missing in view, trying base columns...")
            cols = COLS_BASE
            cursor.execute(f"SELECT {cols} FROM {VIEW} ORDER BY PRODUCT")
        else:
            raise

    columns = [c.strip() for c in cols.split(",")]
    total = 0

    while True:
        rows = cursor.fetchmany(BATCH)
        if not rows:
            break

        clients = [row_to_dict(r, columns) for r in rows]
        payload = {"replace": total == 0, "clients": clients}

        r = requests.post(url, json=payload, headers=headers, timeout=120)
        if r.status_code != 200:
            print(f"API error {r.status_code}: {r.text}")
            sys.exit(1)

        total += len(rows)
        print(f"  Synced {total} rows...")

    cursor.close()
    conn.close()
    print(f"Done. Total: {total} clients.")


if __name__ == "__main__":
    main()
