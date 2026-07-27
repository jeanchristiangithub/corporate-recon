<?php
declare(strict_types=1);

/*
 * The end-month uploader intentionally uses the daily uploader's markup and
 * workflow so previews, partner selection, row counts, and future fixes stay
 * consistent between both screens. Only instance names, labels, events, and
 * validation mode differ.
 */
$settlementUploaderMode = 'endMonth';
ob_start();
include __DIR__ . '/../daily/settlementdaily-section.php';
$settlementEndMonthMarkup = (string)ob_get_clean();

$settlementEndMonthMarkup = str_replace(
    [
        'settlementDaily',
        'Settlement Daily',
        'Settlement Detail - Per Daily Uploader',
        'Import daily settlement detail files for a corporate partner.',
        'settlementdaily:upload',
        "'[settlement-daily]",
    ],
    [
        'settlementEndMonth',
        'Settlement End Month',
        'Settlement Detail - End Month Uploader',
        'Import end-month settlement detail files for a corporate partner.',
        'settlementendmonth:upload',
        "'[settlement-end-month]",
    ],
    $settlementEndMonthMarkup
);

echo $settlementEndMonthMarkup;
unset($settlementUploaderMode, $settlementEndMonthMarkup);
?>
