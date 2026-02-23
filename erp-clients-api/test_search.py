#!/usr/bin/env python3
"""Test search query - run to verify search works with ID_NO, etc."""
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
VIEW = "TQ_LMS.LMS_INDIVIDUAL_CRM_VIEW"

COLS = "POLICY_NUMBER,LIFE_ASSURED,PRODUCT,POL_PREPARED_BY,INTERMEDIARY,STATUS,KRA_PIN,PRP_DOB,MATURITY_DATE,ID_NO,PHONE_NO,CHECKOFF,BAL"
SEARCH_COLS = ["POLICY_NUMBER", "LIFE_ASSURED", "POL_PREPARED_BY", "INTERMEDIARY", "KRA_PIN", "ID_NO", "PHONE_NO"]

conn = oracledb.connect(user=USER, password=PASSWORD, dsn=DSN)
cursor = conn.cursor()

# Test search by ID number
search = "24185085"
conditions = [f"{c} LIKE :search" for c in SEARCH_COLS]
where_clause = " WHERE (" + " OR ".join(conditions) + ")"
sql = f"SELECT {COLS} FROM {VIEW}{where_clause}"
print("SQL:", sql[:120] + "...")
cursor.execute(sql, {"search": f"%{search}%"})
rows = cursor.fetchall()
print(f"Search for '{search}': {len(rows)} rows")
for r in rows[:3]:
    print("  ", r[0], r[12], r[11])  # policy, id_no, phone_no

cursor.close()
conn.close()
print("SUCCESS - Search query works.")
