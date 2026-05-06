# Invoice OCR Analytics

OCR-Assisted Sales Invoice Processing and Explainable Sales Analytics System for SMEs.

This Laravel FYP project helps SME users upload sales invoices, extract OCR text, verify invoice data, store verified records, and generate dashboards, reports, and explainable sales insights.

## Main Features

- User registration and login
- Company profile onboarding with logo
- Sales invoice upload for PDF, JPG, and PNG
- Python OCR processing using Tesseract
- Editable verification form before invoice saving
- Manual invoice creation when OCR is not needed
- Customer and invoice record management
- Full invoice item rows with quantity, unit price, tax, discount, and total
- Multi-currency invoice storage
- MYR report conversion using a saved exchange rate
- Free exchange-rate lookup using Frankfurter
- Sales dashboard
- Report page with summary, monthly, customer, payment-status, currency, and invoice-detail sections
- CSV export and print/save-PDF report flow
- Rule-based explainable sales insight with supporting evidence
- OCR result and verification status page

## Tech Stack

- Laravel 12
- MySQL
- Laravel Breeze
- Blade, Tailwind CSS, Alpine.js
- Python 3
- Tesseract OCR
- Poppler for PDF conversion

## Local Setup

See [docs/setup-guide.md](docs/setup-guide.md).

Default seeded login:

```text
Email: admin@example.com
Password: password
```

## Run

```powershell
composer install
npm install
php artisan migrate:fresh --seed
npm run build
php artisan serve
```

Open:

```text
http://127.0.0.1:8000
```

