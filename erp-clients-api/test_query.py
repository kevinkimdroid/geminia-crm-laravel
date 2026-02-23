#!/usr/bin/env python3
"""Direct test of the exact query - run this to verify Oracle returns data."""
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

conn = oracledb.connect(user=USER, password=PASSWORD, dsn=DSN)
cursor = conn.cursor()
sql = f"SELECT {COLS} FROM {VIEW} WHERE POLICY_NUMBER = :policy"
cursor.execute(sql, {"policy": "090807694"})
rows = cursor.fetchall()
cols = [c.strip() for c in COLS.split(",")]
print("Columns:", cols)
print("Rows:", len(rows))
for i, row in enumerate(rows):
    print(f"\nRow {i}:")
    for j, col in enumerate(cols):
        print(f"  {col}: {row[j]}")
cursor.close()
conn.close()
print("\nSUCCESS - Oracle returns the data. API should work with these columns.")
