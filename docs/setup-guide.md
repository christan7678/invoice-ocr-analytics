# FYP Application Setup Guide

Project: OCR-Assisted Sales Invoice Processing and Explainable Sales Analytics System for SMEs

## Current Local Setup

- PHP: 8.2.29
- Composer: 2.9.5
- Laravel Framework: 12.58.0
- Node.js: 24.12.0
- NPM: 11.6.2
- Python: 3.11.3
- Git: 2.53.0
- MySQL: available through WAMP at `C:\wamp64\bin\mysql\mysql8.4.7`
- Tesseract OCR: 5.5.0.20241111

## Why Laravel 12

Laravel 13 requires PHP 8.3 or newer. This computer currently has PHP 8.2.29, so this project uses Laravel 12, which supports PHP 8.2.

## Run The Application

From the project folder:

```powershell
cd C:\Users\chris\Desktop\FYP\invoice-ocr-analytics
php artisan serve
```

Open:

```text
http://127.0.0.1:8000
```

## Installed So Far

- Laravel 12 project
- Laravel Breeze authentication
- Blade views
- Tailwind/Vite frontend build
- Default Laravel/Breeze test suite
- Git repository initialized
- Company profile onboarding
- Sales invoice upload with OCR processing
- Manual/verified invoice form with full line items
- Customer record creation from invoice entry
- Invoice search, filter, view, edit, and delete
- Dashboard calculations using verified invoice data
- Report page with MYR conversion, CSV export, and print/save PDF
- Free rule-based explainable sales insight

## Verify The Application

```powershell
php artisan --version
php artisan test
```

Current seeded login:

```text
Email: admin@example.com
Password: password
```

## Main Workflow

1. Register or log in.
2. Create a company profile with company name, address, contact details, default currency, and optional logo.
3. Create an invoice manually, or upload PDF/JPG/PNG for OCR-assisted extraction.
4. Verify and correct invoice fields.
5. Add complete invoice item rows with quantity, unit price, tax, discount, and total.
6. Save verified invoice data.
7. View dashboard, reports, and explainable insights.

## Currency Handling

Invoices preserve the original invoice currency, but reports convert totals into MYR using the exchange rate saved during verification.

The system can fetch a free latest exchange rate from Frankfurter when the user enters a non-MYR invoice currency. If the API is unavailable, the user can still enter the rate manually.

On this WAMP setup, PHP may not have a configured CA certificate bundle for cURL. The local `.env` uses `EXCHANGE_RATE_VERIFY_SSL=false` for development. For deployment, configure PHP CA certificates and set it back to `true`.

## Database

The local app is configured for MySQL through WAMP.

Current `.env` setting:

```text
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=invoice_ocr_analytics
DB_USERNAME=root
DB_PASSWORD=
```

Create the database in phpMyAdmin or MySQL if it does not exist:

```sql
CREATE DATABASE invoice_ocr_analytics;
```

Then run:

```powershell
php artisan migrate:fresh --seed
```

## OCR Setup

Tesseract OCR was installed through Windows Package Manager:

```powershell
winget install --source winget --exact --id tesseract-ocr.tesseract --accept-package-agreements --accept-source-agreements
```

Tesseract was added to the user PATH:

```text
C:\Program Files\Tesseract-OCR
```

Poppler was installed through Windows Package Manager for PDF-to-image conversion:

```powershell
winget install --source winget --exact --id oschwartz10612.Poppler --accept-package-agreements --accept-source-agreements
```

The local `.env` should point Laravel/Python to OCR executables:

```text
PYTHON_BINARY=python
TESSERACT_CMD="C:/Program Files/Tesseract-OCR/tesseract.exe"
POPPLER_PATH="path/to/poppler/Library/bin"
```

The OCR Python packages are listed in:

```text
ocr\requirements.txt
```

Install or reinstall them with:

```powershell
python -m pip install -r ocr\requirements.txt
```

The OCR module is connected to the invoice upload and verification workflow.
