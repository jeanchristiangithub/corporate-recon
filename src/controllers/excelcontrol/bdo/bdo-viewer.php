<?php
// bdo-viewer.php
// Expects JSON POST: { data: { filename, dateStr, rows, viewType? } }
// Renders an Excel-like scrollable HTML table for BDO web data.
// Mirrors mbtc-viewer.php — adapted for BDO (BDO UNIBANK)

$raw = file_get_contents('php://input');
if (!$raw) { echo '<div>No data provided</div>'; exit; }

$payload = json_decode($raw, true);
if (!$payload || !isset($payload['data'])) { echo '<div>Invalid data</div>'; exit; }

$data        = $payload['data'];
$rows        = isset($data['rows']) && is_array($data['rows']) ? $data['rows'] : [];
$dateStr     = isset($data['dateStr'])     ? htmlspecialchars($data['dateStr'])     : '';
$filename    = isset($data['filename'])    ? htmlspecialchars($data['filename'])    : '';
$viewType    = isset($data['viewType'])    ? (string) $data['viewType']             : 'web';
$partnerName = isset($data['partnerName']) ? htmlspecialchars($data['partnerName']) : 'BDO';

ob_start();
?>
<style>
/* BDO Excel-like viewer — sticky header and first column, horizontal + vertical scroll */
.bdo-viewer { padding: 14px 10px; color: #1f2937; }
.bdo-viewer h3 { display: block; margin: 0 0 6px 0; color: #1f2937; }
.bdo-view-meta { display: flex; gap: 12px; align-items: center; margin-bottom: 10px; }
.bdo-viewer #bdoViewerContainer { overflow: auto; max-height: 78vh; }
.bdo-view-table {
    border-collapse: collapse;
    width: auto;
    min-width: 1400px;
    table-layout: fixed;
    font-size: 13px;
    border-spacing: 0;
}
.bdo-view-table th,
.bdo-view-table td {
    border: 1px solid #e5e7eb;
    padding: 8px 10px;
    text-align: left;
    vertical-align: middle;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    background: #fff;
}
.bdo-view-table thead th {
    position: sticky;
    top: 0;
    z-index: 6;
    background: #f9fafb;
    font-weight: 700;
}
.bdo-view-table th:first-child,
.bdo-view-table td:first-child {
    position: sticky;
    left: 0;
    z-index: 7;
    background: #fff;
}
.bdo-view-table tbody tr:nth-child(even) td { background: rgba(240, 244, 247, 0.6); }
.bdo-view-table tbody tr.search-match td   { background: #fff7ed; }
.bdo-viewer input#bdoViewerSearch {
    padding: 8px 10px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}
.bdo-viewer .viewer-title { font-weight: 600; color: #1f2937; }
.bdo-recon-modal__head { padding-right: 56px; }
@media (max-width: 720px) {
    .bdo-recon-modal__head { padding-right: 12px; }
    .bdo-viewer input#bdoViewerSearch { max-width: 180px; }
}
</style>

<div class="bdo-viewer" style="font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;">
    <div class="bdo-recon-modal__head" style="border-bottom: 0; margin-bottom: 8px; padding-bottom: 6px; display: flex; align-items: center; justify-content: space-between">
        <div>
            <h3 style="margin: 0; font-size: 1.05rem; color: #1f2937; font-weight: 700;">
                <?php echo ($partnerName !== '' ? $partnerName : 'BDO') . ' - ' . $dateStr; ?>
            </h3>
            <div style="font-size: 0.92rem; color: #6b7280; margin-top: 6px;">
                <?php echo $viewType === 'web' ? 'Web Data' : 'Data'; ?>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 12px; min-width: 0;">
            <div style="font-size: 0.95rem; color: #666; flex: 0 0 auto;">
                Rows: <strong id="bdoViewerCount"><?php echo number_format(count($rows)); ?></strong>
            </div>
            <input id="bdoViewerSearch" placeholder="Search"
                style="flex: 1; min-width: 0; max-width: 360px; padding: 8px 10px; border: 1px solid #ddd; border-radius: 8px;" />
            <button id="bdoViewerSearchBtn" class="material-btn" style="white-space: nowrap;">Search</button>
        </div>
    </div>

    <div style="overflow: auto; max-height: 78vh;" id="bdoViewerContainer">
        <table id="bdoViewerTable" class="bdo-view-table" border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
<?php
// Build header set from the union of keys across all rows
$headers = [];
if (count($rows) > 0) {
    foreach ($rows as $r) {
        if (is_array($r)) {
            foreach (array_keys($r) as $k) $headers[$k] = true;
        } elseif (is_object($r)) {
            foreach (array_keys(get_object_vars($r)) as $k) $headers[$k] = true;
        }
    }
    $headers = array_keys($headers);
}

if (empty($headers)) {
    echo '<th>No data</th>';
} else {
    // Preferred column order for the web-data view type
    $ordered = [
        'NO', 'CONTROL SERIES NO', 'DATE CLAIMED', 'KPTN', 'CCREF NO',
        'CURRENCY', 'AMOUNT', 'CTC', 'CTP', 'SENDER NAME', 'SENDER COUNTRY',
        'BENEFICIARY/RECEIVER', 'RECEIVER KYC', 'RECEIVER PHONE',
        'OPERATOR', 'BRANCH', 'REMOTE OPERATOR', 'REMOTE BRANCH'
    ];

    // Build display array: preferred order first, then any extra columns
    $display = [];
    foreach ($ordered as $col) {
        if (in_array($col, $headers)) $display[$col] = $col;
    }
    foreach ($headers as $h) {
        if (!isset($display[$h])) $display[$h] = $h;
    }

    foreach ($display as $hk => $orig) {
        echo '<th>' . htmlspecialchars($hk) . '</th>';
    }
}
?>
                </tr>
            </thead>
            <tbody>
<?php
foreach ($rows as $r) {
    echo '<tr data-visible="1">';
    if (empty($display)) {
        echo '<td></td>';
    } else {
        foreach (array_keys($display) as $hk) {
            $v = '';
            if (is_array($r) && array_key_exists($hk, $r))       $v = $r[$hk];
            elseif (is_object($r) && property_exists($r, $hk))   $v = $r->$hk;
            if (is_array($v) || is_object($v)) $cell = htmlspecialchars(json_encode($v));
            else $cell = htmlspecialchars((string) $v);
            echo '<td>' . $cell . '</td>';
        }
    }
    echo '</tr>';
}
?>
            </tbody>
        </table>
    </div>
</div>

<script>
;(function () {
    const container = document.getElementById('bdoViewerContainer');
    const table     = document.getElementById('bdoViewerTable');
    const countEl   = document.getElementById('bdoViewerCount');
    const search    = document.getElementById('bdoViewerSearch');
    const btn       = document.getElementById('bdoViewerSearchBtn');

    function findHeaderIndex(candidates) {
        if (!table) return -1;
        const headers   = Array.from(table.querySelectorAll('thead th'));
        const normalized = headers.map(h => String(h.textContent || '').trim().toLowerCase());
        for (let i = 0; i < normalized.length; i++) {
            const label = normalized[i];
            for (let j = 0; j < candidates.length; j++) {
                if (label === candidates[j] || label.indexOf(candidates[j]) !== -1) return i;
            }
        }
        return -1;
    }

    // Preferred search column index: CCREF NO (web data)
    const ccrefIndex = findHeaderIndex(['ccref no', 'ccref']);

    function doFilter() {
        const q    = (search ? search.value : '').trim().toLowerCase();
        const rows = container ? Array.from(container.querySelectorAll('tbody tr')) : [];
        let cnt    = 0;

        if (q === '') {
            rows.forEach(row => { row.style.display = ''; row.classList.remove('search-match'); cnt++; });
        } else {
            rows.forEach(row => { row.style.display = 'none'; row.classList.remove('search-match'); });

            const qNorm = q.replace(/\W/g, '').toLowerCase();
            let found    = null;

            // 1) Exact normalized match on CCREF NO column
            if (ccrefIndex >= 0) {
                for (const row of rows) {
                    const cell     = row.cells[ccrefIndex] ? String(row.cells[ccrefIndex].textContent).trim() : '';
                    const cellNorm = cell.replace(/\W/g, '').toLowerCase();
                    if (qNorm !== '' && cellNorm === qNorm) { found = row; break; }
                }
            }
            // 2) Substring match on CCREF NO column
            if (!found && ccrefIndex >= 0) {
                for (const row of rows) {
                    const cell = row.cells[ccrefIndex] ? String(row.cells[ccrefIndex].textContent).toLowerCase() : '';
                    if (cell.indexOf(q) !== -1) { found = row; break; }
                }
            }
            // 3) Full-row text fallback
            if (!found) {
                for (const row of rows) {
                    if ((row.textContent || '').toLowerCase().indexOf(q) !== -1) { found = row; break; }
                }
            }

            if (found) {
                found.style.display = '';
                found.classList.add('search-match');
                try { found.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
                cnt = 1;
            } else {
                const prev = document.getElementById('bdoNoRecord');
                if (prev) prev.remove();
                const msg       = document.createElement('div');
                msg.id          = 'bdoNoRecord';
                msg.style.marginTop  = '8px';
                msg.style.color      = '#6b7280';
                msg.style.fontSize   = '0.95rem';
                msg.textContent = 'No record found';
                if (container && container.parentNode) container.parentNode.insertBefore(msg, container.nextSibling);
                cnt = 0;
            }
        }

        if (countEl) {
            try { countEl.textContent = cnt.toLocaleString(); } catch (e) { countEl.textContent = String(cnt); }
        }
    }

    if (search) search.addEventListener('input', doFilter);
    if (btn) btn.addEventListener('click', doFilter);
})();
</script>
<?php
$out = ob_get_clean();
echo $out;
