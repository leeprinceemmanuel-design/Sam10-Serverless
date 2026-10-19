# SAM10 – Serverless Payroll Management API

A PHP API that runs on **AWS Lambda** (using **Bref**) behind **API Gateway**.
It reads employee and payroll records from **CSV files** (no database), returns **JSON**,
and can generate a **PDF payslip** (Dompdf) that is uploaded to **S3** (AWS SDK for PHP).

## Endpoints

| Method | Endpoint | What it does |
|---|---|---|
| GET | `/api/employees` | All employee records (real CSV columns: `id, full_name, email, position_id, department_id, manager_id`) |
| GET | `/api/reports/payroll` | Totals: payroll records, gross pay, deductions, net pay |
| GET | `/api/payslips/{payroll_id}` | One payslip: employee details, pay period, gross, deductions, net |
| POST | `/api/payslips/{payroll_id}/generate` | Makes a PDF payslip, uploads it to S3 as `payslips/{payroll_id}.pdf`, returns the S3 key |

**Deductions** = gross pay − net pay (the dataset has no separate deductions column).

## Project structure

```
public/index.php          Entry point + routing (Bref sends every request here)
src/CsvReader.php         Reads a CSV file into rows
src/PayrollRepository.php Employee list, payroll report, payslip logic
src/PayslipPdf.php        Builds the PDF with Dompdf
src/PayslipStorage.php    Uploads the PDF to S3 with the AWS SDK
data/                     employees.csv, payroll.csv, departments.csv, positions.csv
scripts/download-data.sh  Downloads the CSV files from the dataset site
serverless.yml            Lambda + API Gateway + IAM permission config
```

---

# Deployment guide (step by step, using AWS CloudShell)

CloudShell is a terminal inside the AWS console. It is already logged in as your AWS user,
so you **don't need access keys**.

## Step 1 — Log in and pick the region

1. Open the class AWS console link and log in.
2. Top-right corner: set the region to **Asia Pacific (Singapore) ap-southeast-1**.
   (If your instructor uses a different region, use that one, and change `region:` in `serverless.yml` to match.)

## Step 2 — Create the S3 bucket

1. Go to **S3 → Create bucket**.
2. Bucket name: `sam10-payslips-yourname` (lowercase, must be unique in all of AWS).
3. Region: same as Step 1.
4. Leave **Block all public access** ON. Click **Create bucket**.

## Step 3 — Open CloudShell and upload the project

1. Click the **CloudShell** icon (the `>_` icon in the top bar).
2. **Actions → Upload file** → choose `sam10-payroll-api.zip`.
3. Unzip it:
   ```bash
   unzip sam10-payroll-api.zip && cd sam10-payroll-api
   ```

## Step 4 — Install PHP, Composer, and the Serverless CLI

```bash
# PHP (Bref 3 needs PHP 8.2 or newer)
sudo dnf install -y php-cli php-mbstring php-xml
php -v
```
If `php -v` shows **lower than 8.2**, install a newer one instead:
```bash
sudo dnf install -y php8.3-cli php8.3-mbstring php8.3-xml
```

```bash
# Composer (PHP package manager)
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Serverless CLI (open-source version used by Bref)
sudo npm install -g osls
serverless --version
```

## Step 5 — Put your names in `serverless.yml`

Replace `yourname` with your own name (lowercase, no spaces). Example for "juan":
```bash
sed -i 's/sam10-payroll-yourname/sam10-payroll-juan/; s/sam10-payslips-yourname/sam10-payslips-juan/' serverless.yml
grep -n "juan" serverless.yml
```
The bucket name must be **exactly** the bucket you made in Step 2.

## Step 6 — Download the CSV files and install the libraries

```bash
bash scripts/download-data.sh
composer install --no-dev --optimize-autoloader
```
You should see employees.csv (501 lines) and payroll.csv (601 lines) — the extra line is the header.

## Step 7 — Deploy

```bash
serverless deploy
```
At the end it prints a URL like `https://abc123.execute-api.ap-southeast-1.amazonaws.com`.
**That is your Deployed API base URL.** Save it:
```bash
export API=https://abc123.execute-api.ap-southeast-1.amazonaws.com   # paste YOUR url
```

> If it says it can't find AWS credentials, run this, then deploy again:
> `eval "$(aws configure export-credentials --format env)"`

## Step 8 — Test all endpoints

```bash
curl $API/api/employees
curl $API/api/reports/payroll
curl $API/api/payslips/1
curl -X POST $API/api/payslips/1/generate
```
Take a screenshot of each response (CloudShell or Postman both work).

## Step 9 — Check the PDF in S3

S3 → your bucket → `payslips/` folder → `1.pdf` → **Open** (screenshot it).

## Step 10 — Push to GitHub

1. On github.com create a new **empty** repository, e.g. `sam10-payroll-api`.
2. In CloudShell:
   ```bash
   git init
   git config user.name "Your Name"
   git config user.email "you@example.com"
   git add .
   git commit -m "SAM10 Serverless Payroll Management API"
   git branch -M main
   git remote add origin https://github.com/YOUR-USERNAME/sam10-payroll-api.git
   git push -u origin main
   ```
   When asked for a password, use a GitHub **Personal Access Token** (GitHub → Settings → Developer settings → Tokens), not your GitHub password.

`vendor/` is not pushed (it's in `.gitignore`); the CSV files in `data/` **are** pushed, as required.

## Troubleshooting

| Problem | Fix |
|---|---|
| `AccessDenied` for `iam:CreateRole` or CloudFormation | Your AWS user lacks a permission — ask your instructor. |
| `/generate` returns `AccessDenied` from S3 | The bucket name in `serverless.yml` doesn't match the real bucket, or the region is different. Fix it and `serverless deploy` again. |
| `CSV file not found` | You skipped `bash scripts/download-data.sh`. Run it, then deploy again. |
| `Internal Server Error` / blank response | Read the logs: `serverless logs -f web` |

## Notes about the dataset

The dataset is synthetic (computer-made). Some rows have **net pay higher than gross pay**, which
gives a negative deduction, and some pay periods end before they start. The API reports the data
as it is; the payroll report also shows `records_with_negative_deductions` so this is visible.

## Removing everything after grading (optional)

```bash
serverless remove
```
