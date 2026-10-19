#!/usr/bin/env bash
# Downloads the "Employees and Payroll" dataset CSV files into data/
# Source: https://databasesample.com/datasets/employees-and-payroll (CC0, version 3)
set -euo pipefail

BASE="https://database-sample-website.s3.eu-north-1.amazonaws.com/datasets/employees-and-payroll/v3/csv"
DIR="$(cd "$(dirname "$0")/.." && pwd)/data"
mkdir -p "$DIR"

for f in employees payroll departments positions; do
    echo "Downloading $f.csv ..."
    curl -fsSL -o "$DIR/$f.csv" "$BASE/$f.csv"
done

echo "Done. Row counts (including header line):"
wc -l "$DIR"/*.csv
