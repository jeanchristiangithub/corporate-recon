# Summary Report: WorldCom International Communications Logic

This document explains how the Summary Report behaves when the selected corporate partner is `WORLDCOM INTERNATIONAL COMMUNICATIONS`.

## Entry Point

The Summary Report UI is rendered by:

`src/pages/home/components/summaryreport-section.php`

The report is included inside the Home page through:

`src/pages/home/home.php`

When the user selects `WORLDCOM INTERNATIONAL COMMUNICATIONS` and clicks `View Report`, the browser calls:

`src/controllers/excelcontrol/summary-report.php`

with query parameters like:

```text
partner=WORLDCOM INTERNATIONAL COMMUNICATIONS
start_date=2026-01-01
end_date=2026-01-31
```

## Partner Resolution

The backend treats `WORLDCOM INTERNATIONAL COMMUNICATIONS` and `WIC` as the same report family.

For WorldCom/WIC, these aliases are used:

```php
['WIC', 'WORLDCOM INTERNATIONAL COMMUNICATIONS']
```

The partner data table resolved by the backend is:

```text
wic_partner_data
```

The web data table is:

```text
ml_web_data
```

## Data Sources

### Partner Data

WorldCom partner-side totals come from:

```text
wic_partner_data
```

The important columns are:

```text
date
transaction_id
amount
coin
```

The report groups partner data by `DATE(date)`.

The calculated partner values are:

```text
WIC PHP Vol       = COUNT(*)
WIC PHP Principal = SUM(amount)
```

The `WIC PHP` group intentionally does not show a commission column in the UI.

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
currency
```

The report filters web rows where `partnerName` is either:

```text
WIC
WORLDCOM INTERNATIONAL COMMUNICATIONS
```

The calculated Web KPX values are:

```text
Web KPX Vol        = COUNT(*)
Web KPX Principal  = SUM(amount)
Web KPX Commission = SUM(ctp)
```

## Visible Report Format

The WorldCom report is based on the `wic peso cover only..xlsx` layout.

When `WORLDCOM INTERNATIONAL COMMUNICATIONS` is selected and `View Report` is clicked, the UI shows two report tabs:

```text
WIC PHP
WIC USD
```

Both tabs use the same cover-style layout. The selected tab controls which currency bucket is displayed.

The UI currently displays these groups in each tab:

```text
WIC PHP or WIC USD
WEB KPX
NET WEB REPORT
PARTNER VS. WEB
```

The following groups from the original Excel-style cover are intentionally hidden in the UI:

```text
WEB KP7
CANCELLED/TEST
DUPLICATE TRXNS
```

## How Each Visible Section Is Populated

### WIC PHP

Populated from `wic_partner_data` rows where `coin = PHP`:

```text
Vol       = partner.volume
Principal = partner.principal
```

There is no visible commission column for `WIC PHP`.

### WIC USD

Populated from `wic_partner_data` rows where `coin = USD`:

```text
Vol       = partner.volume
Principal = partner.principal
```

The `WIC USD` tab follows the same table structure as `WIC PHP`, but its partner and web values are filtered to USD.

### WEB KPX

Populated from `ml_web_data` for the selected tab currency:

```text
Vol        = web.volume
Principal  = web.principal
Commission = web.commission
```

For the `WIC PHP` tab, the backend filters `ml_web_data.currency = PHP`.

For the `WIC USD` tab, the backend filters `ml_web_data.currency = USD`.

### NET WEB REPORT

Calculated as:

```text
Web KPX - Cancelled/Test - Duplicate Trxns
```

Although `Cancelled/Test` and `Duplicate Trxns` are hidden in the WorldCom UI, the backend still keeps those buckets available. If they are zero, `Net Web Report` equals `Web KPX`.

### PARTNER VS. WEB

Calculated as:

```text
WIC PHP - Net Web Report
```

Displayed columns:

```text
Vol
Principal
Commission
```

Because `WIC PHP` has no visible commission value, commission variance is usually based on:

```text
0 - Net Web Report Commission
```

## Important Files

```text
src/pages/home/components/summaryreport-section.php
src/controllers/excelcontrol/summary-report.php
src/pages/home/home.php
```

## Notes

If the report shows zero values for a selected month, check whether that month and currency exist in:

```text
wic_partner_data.date
wic_partner_data.coin
ml_web_data.date_claimed
ml_web_data.currency
```

During local testing, the available `wic_partner_data` sample rows were in January, while February returned zero totals.
