# Summary Report: Metrobank Head Office Logic

This document explains how the Summary Report behaves when the selected corporate partner is `METROBANK HEAD OFFICE`.

## Entry Point

The Summary Report UI is rendered by:

`src/pages/home/components/summaryreport-section.php`

The report is included inside the Home page through:

`src/pages/home/home.php`

When the user selects `METROBANK HEAD OFFICE` and clicks `View Report`, the browser calls:

`src/controllers/excelcontrol/summary-report.php`

with query parameters like:

```text
partner=METROBANK HEAD OFFICE
start_date=2026-02-01
end_date=2026-02-28
```

## Partner Resolution

The backend treats `METROBANK HEAD OFFICE` and `MBTC` as the same report family.

For Metrobank, these aliases are used:

```php
['MBTC', 'METROBANK HEAD OFFICE']
```

The partner data table resolved by the backend is:

```text
mbtc_partner_data
```

The web data table is:

```text
ml_web_data
```

## Data Sources

### Partner Data

Metrobank partner-side totals come from:

```text
mbtc_partner_data
```

The important columns are:

```text
cover_date
php
in_php
partnerName
```

The report groups partner data by `DATE(cover_date)`.

The calculated partner values are:

```text
MBTC Vol        = COUNT(*)
MBTC Principal  = SUM(php)
MBTC Commission = SUM(in_php)
```

### Web KPX Data

Web-side totals come from:

```text
ml_web_data
```

The important columns are:

```text
date_claimed
amount
ctp
partnerName
```

The report filters web rows where `partnerName` is either:

```text
MBTC
METROBANK HEAD OFFICE
```

The calculated Web KPX values are:

```text
Web KPX Vol        = COUNT(*)
Web KPX Principal  = SUM(amount)
Web KPX Commission = SUM(ctp)
```

## Daily Row Construction

The endpoint builds one row per calendar day between the selected start and end date.

Each day contains these groups:

```text
partner
web
cancelled
duplicates
net_web
variance
deposit
```

For Metrobank, the visible table groups are:

```text
MBTC
WEB KPX
DUPLICATE TRXNS
NET WEB REPORT
PARTNER VS. WEB
DEPOSIT VS. WEB
helper commission columns
```

## How Each Visible Section Is Populated

### MBTC

Normally populated from `mbtc_partner_data`:

```text
Vol        = partner.volume
Principal  = partner.principal
Commission = partner.commission
```

### WEB KPX

Populated from `ml_web_data`:

```text
Vol        = web.volume
Principal  = web.principal
Commission = web.commission
```

### DUPLICATE TRXNS

Calculated by checking duplicate web reference numbers for the selected period.

For Metrobank web data, the duplicate reference column is usually:

```text
ccref_no
```

If duplicates are found, this section shows:

```text
Vol
Principal
Commission
```

### NET WEB REPORT

Calculated as:

```text
Web KPX - Cancelled/Test - Duplicate Trxns
```

In the current Metrobank display, `Cancelled/Test` is not shown as a visible column group, but the backend still keeps a `cancelled` bucket.

### PARTNER VS. WEB

Calculated as:

```text
MBTC - Net Web Report
```

### DEPOSIT VS. WEB

The visible columns are:

```text
DEBIT
CREDIT
VARIANCE
```

The two helper columns to the right match the Excel-style calculation:

```text
helper 1 = commission / 56
helper 2 = commission - helper 1
```

The displayed Deposit vs Web variance is:

```text
debit + credit - principal - helper 2
```

Because debit and credit are usually blank or zero, the variance is often negative.

## Example: February 14, 2026

The backend returned:

```text
partner.volume     = 0
partner.principal  = 0
partner.commission = 0

web.volume         = 3,156
web.principal      = 49,995,862.54
web.commission     = 247,890.00
```

Because the MBTC side is blank but Web KPX has values, `Partner vs Web` displays the negative difference:

```text
Vol        = -3,156
Principal  = -49,995,862.54
Commission = -247,890.00
```

## Important Files

```text
src/pages/home/components/summaryreport-section.php
src/controllers/excelcontrol/summary-report.php
src/pages/home/home.php
```
