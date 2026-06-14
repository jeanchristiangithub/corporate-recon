<?php
// ml-web-data-helper.php
// Shared normalization utilities for consolidated ML web data uploads

/**
 * Parse and normalize date_claimed values for duplicate checking
 * Handles Excel serial dates, Unix timestamps, and string formats
 * Returns YYYY-MM-DD HH:MM:SS or null if parsing fails
 */
function ml_parse_date_claimed($raw): ?string
{
    if ($raw === null || $raw === '') return null;
    
    $s = trim((string)$raw);
    if ($s === '') return null;
    
    // If it's a pure number (Excel serial or Unix timestamp)
    if (preg_match('/^[0-9]+(\.[0-9]+)?$/', $s)) {
        $serial = (float)$s;
        if (!is_nan($serial)) {
            try {
                // Try Excel serial date
                if ($serial > 0 && $serial < 100000) {
                    $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($serial);
                    return $dt->format('Y-m-d H:i:s');
                }
                // Try Unix timestamp (in seconds)
                if ($serial > 86400 && $serial < 3000000000) {
                    return date('Y-m-d H:i:s', (int)$serial);
                }
            } catch (Throwable $e) {
                // Fall through
            }
        }
    }
    
    // Try native Date parse
    $d = new DateTime($s);
    if (!is_nan($d->getTimestamp())) {
        return $d->format('Y-m-d H:i:s');
    }
    
    // Try common formats
    $formats = ['F d, Y', 'n/j/Y', 'd/m/Y', 'Y-m-d', 'Y-m-d H:i:s'];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $s);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d H:i:s');
        }
    }
    
    // Fallback: return raw string
    return $s;
}

/**
 * Normalize amount values
 * Removes currency symbols, commas, and converts to decimal
 * Returns null string if amount is empty or invalid
 */
function ml_normalize_amount($value): string
{
    if ($value === null || $value === '') return '';
    
    $str = trim((string)$value);
    if ($str === '') return '';
    
    // Remove common currency symbols and formatting
    $str = preg_replace('/[^0-9.\-]/', '', $str);
    
    // Validate it looks like a number
    if (preg_match('/^-?[0-9]+\.?[0-9]*$/', $str)) {
        return $str;
    }
    
    return '';
}
