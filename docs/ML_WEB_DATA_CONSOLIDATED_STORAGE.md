# ML Web Data Consolidated Storage

## Overview

This document explains the unified ML web data storage system that consolidates uploads from all corporate partners into a single `ml_web_data` table.

## Table Schema

**Database:** `filerecondb`
**Table:** `ml_web_data`

### Columns

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | INT | NO | Primary key (auto-increment) |
| partnerName | VARCHAR(255) | NO | Corporate partner name (e.g., METROBANK HEAD OFFICE, PAYPAL CORPORATE) |
| no | VARCHAR(100) | NO | Reference number |
| control_series_no | VARCHAR(100) | YES | Control series number |
| date_claimed | DATETIME | YES | Date claimed |
| kptn | VARCHAR(255) | YES | KPTN code |
| ccref_no | VARCHAR(100) | NO | Clear credit reference number |
| currency | VARCHAR(10) | YES | Currency code (e.g., PHP, USD) |
| amount | DECIMAL(12, 2) | YES | Transaction amount |
| ctc | VARCHAR(255) | YES | CTC identifier |
| ctp | VARCHAR(255) | YES | CTP identifier |
| sender_name | VARCHAR(255) | YES | Sender/Remitter name |
| sender_country | VARCHAR(100) | YES | Sender country |
| beneficiary_receiver | VARCHAR(255) | YES | Beneficiary/Receiver name |
| receiver_kyc | VARCHAR(255) | YES | Receiver KYC verification |
| receiver_phone | VARCHAR(20) | YES | Receiver phone number |
| operator | VARCHAR(100) | YES | Operator code |
| branch | VARCHAR(100) | YES | Branch code |
| remote_operator | VARCHAR(100) | YES | Remote operator code |
| remote_branch | VARCHAR(100) | YES | Remote branch code |
| created_at | TIMESTAMP | NO | Record creation timestamp |
| updated_at | TIMESTAMP | NO | Record update timestamp |

### Indexes

- **Primary:** `id`
- **Unique:** `uk_partner_ccref_date` on (partnerName, ccref_no, date_claimed)
- **Keys:** 
  - idx_partner_name (partnerName)
  - idx_ccref_no (ccref_no)
  - idx_date_claimed (date_claimed)
  - idx_created_at (created_at)

## API Endpoint

**URL:** `/autorecon/src/controllers/excelcontrol/ml-web-data-insert.php`
**Methods:** POST (JSON)

### Actions

#### 1. check - Duplicate Detection

**Request:**
```json
{
  "action": "check",
  "pairs": [
    {
      "partnerName": "METROBANK HEAD OFFICE",
      "ccref_no": "ABC123",
      "date_claimed": "2024-03-25 10:30:00"
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "duplicates": [
    {
      "partnerName": "METROBANK HEAD OFFICE",
      "ccref_no": "ABC123",
      "date_claimed": "2024-03-25 10:30:00",
      "cnt": 1
    }
  ]
}
```

The check action performs multi-level duplicate detection:
1. Exact normalized datetime match
2. Date-only match (ignoring time)
3. Loose match (partnerName + ccref_no only)

#### 2. delete - Remove Duplicates

**Request:**
```json
{
  "action": "delete",
  "pairs": [
    {
      "partnerName": "METROBANK HEAD OFFICE",
      "ccref_no": "ABC123",
      "date_claimed": "2024-03-25 10:30:00"
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "deleted": 1
}
```

#### 3. insert_web - Insert Data

**Request:**
```json
{
  "action": "insert_web",
  "company": "METROBANK HEAD OFFICE",
  "payloads": [
    {
      "filename": "mbtc_20240325.xlsx",
      "dateStr": "2024-03-25",
      "rows": [
        {
          "NO": "001",
          "CONTROL SERIES NO": "CS001",
          "DATE CLAIMED": "2024-03-25",
          "KPTN": "KPT001",
          "CCREF NO": "ABC123",
          "CURRENCY": "PHP",
          "AMOUNT": "10000.00",
          "CTC": "CTC001",
          "CTP": "CTP001",
          "SENDER NAME": "John Doe",
          "SENDER COUNTRY": "USA",
          "BENEFICIARY/RECEIVER": "Jane Doe",
          "RECEIVER KYC": "KYC001",
          "RECEIVER PHONE": "09123456789",
          "OPERATOR": "OP001",
          "BRANCH": "BR001",
          "REMOTE OPERATOR": "ROP001",
          "REMOTE BRANCH": "RBR001"
        }
      ]
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "inserted": 1
}
```

## Frontend Integration

### webdata-section.php Updates

The `webdata-section.php` component has been updated to:

1. Use the unified endpoint: `/autorecon/src/controllers/excelcontrol/ml-web-data-insert.php`
2. Include `partnerName` in all API requests (duplicate check, delete, insert)
3. Maintain the same upload flow and UI for all partners

**Key Changes:**
```javascript
// OLD: Company-specific endpoint
const url = location.origin + '/autorecon/src/controllers/excelcontrol/' + endpointDir + '/' + endpointDir + '-insert.php';

// NEW: Unified endpoint
const url = location.origin + '/autorecon/src/controllers/excelcontrol/ml-web-data-insert.php';

// Pairs now include partnerName
filePairs.push({ 
  partnerName: company.value,  // NEW
  ccref_no: ccref, 
  date_claimed 
});
```

## Helper Functions

**File:** `/autorecon/src/controllers/excelcontrol/ml-web-data-helper.php`

### ml_parse_date_claimed($raw): ?string

Normalizes various date formats to `YYYY-MM-DD HH:MM:SS`:
- Excel serial dates
- Unix timestamps
- String formats (F d, Y / n/j/Y / d/m/Y / Y-m-d / Y-m-d H:i:s)

Returns `null` if parsing fails.

### ml_normalize_amount($value): string

Normalizes currency values:
- Removes currency symbols
- Removes commas and formatting
- Returns numeric string or empty string

## Migration

**File:** `migrations/001_create_ml_web_data_table.sql`

To apply the migration:
```sql
-- Execute the SQL file in filerecondb
mysql -u root filerecondb < migrations/001_create_ml_web_data_table.sql
```

## Benefits

1. **Single Source of Truth:** All corporate partner data in one table
2. **Unified Querying:** Simple SQL queries across all partners
3. **Duplicate Detection:** Consistent duplicate checking with partner awareness
4. **Scalability:** Easier to add new partners without creating new tables
5. **Reporting:** Simplified analytics and reporting across all partners

## Migration from Partner-Specific Tables

If migrating from existing partner-specific tables:

```sql
-- Example: Migrate MBTC data
INSERT INTO ml_web_data 
SELECT NULL, 'METROBANK HEAD OFFICE', no, control_series_no, date_claimed, kptn, ccref_no, 
       currency, amount, ctc, ctp, sender_name, sender_country, beneficiary_receiver, 
       receiver_kyc, receiver_phone, operator, branch, remote_operator, remote_branch, 
       created_at, updated_at
FROM mbtc_web_data;

-- Repeat for other partners with appropriate partnerName value
```

## Future Enhancements

- Partner-specific field mapping for non-standard columns
- Automatic partner name normalization
- Archive table for historical data
- Enhanced duplicate detection strategies
