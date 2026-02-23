#!/usr/bin/env python3
"""Print actual column names from LMS_INDIVIDUAL_CRM_VIEW. Run from erp-clients-api folder."""
import os
from pathlib import Path

_env = Path(__file__).parent / ".env"
if _env.exists():
    for line in _env.read_text().splitlines():
        line = line.strip()
        if line and not line.startswith("#") and "=" in line:
            k, _, v = line.partition("=")
            os.environ.setdefault(k.strip(), v.strip().strip('"').strip("'"))

import oracledb

DSN = os.environ.get("ORACLE_DSN", "10.1.4.101:18032/PDBTQUEST")
USER = os.environ.get("ORACLE_USER", "TQ_LMS")
PASSWORD = os.environ.get("ORACLE_PASSWORD", "")
VIEW_SCHEMA = os.environ.get("ERP_VIEW_SCHEMA", "TQ_LMS")
VIEW_NAME = os.environ.get("ERP_CLIENTS_VIEW", "LMS_INDIVIDUAL_CRM_VIEW")

if not PASSWORD:
    print("Set ORACLE_PASSWORD in .env")
    exit(1)

conn = oracledb.connect(user=USER, password=PASSWORD, dsn=DSN)
cursor = conn.cursor()
cursor.execute("""
    SELECT column_name FROM all_tab_columns
    WHERE table_name = :tname AND owner = :owner
    ORDER BY column_id
""", {"tname": VIEW_NAME.upper(), "owner": VIEW_SCHEMA.upper()})
cols = [r[0] for r in cursor.fetchall()]
cursor.close()
conn.close()

print(f"Columns in {VIEW_SCHEMA}.{VIEW_NAME}:")
print(", ".join(cols))
print(f"\nTotal: {len(cols)} columns")
print("\nAdd to erp-clients-api/.env ERP_CLIENTS_LIST_COLUMNS:")
print(",".join(cols))
