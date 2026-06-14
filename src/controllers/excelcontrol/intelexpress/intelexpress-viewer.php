<?php
// intelexpress-viewer.php
// Expects JSON POST with { data: { filename, dateStr, rows } }

$raw = file_get_contents('php://input');
if (!$raw) { echo '<div>No data provided</div>'; exit; }

$payload = json_decode($raw, true);
if (!$payload || !isset($payload['data'])) { echo '<div>Invalid data</div>'; exit; }

$data = $payload['data'];
$rows = isset($data['rows']) && is_array($data['rows']) ? $data['rows'] : [];
$dateStr = isset($data['dateStr']) ? htmlspecialchars($data['dateStr']) : '';
$partnerName = isset($data['partnerName']) ? htmlspecialchars($data['partnerName']) : 'IntelExpress';

ob_start();
?>
<style>
.intelexpress-viewer{padding:14px 10px;color:#1f2937}
.intelexpress-viewer #intelexpressViewerContainer{overflow:auto;max-height:78vh}
.intelexpress-view-table{border-collapse:collapse;width:auto;min-width:1400px;table-layout:fixed;font-size:13px;border-spacing:0}
.intelexpress-view-table th,.intelexpress-view-table td{border:1px solid #e5e7eb;padding:8px 10px;text-align:left;vertical-align:middle;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;background:#fff}
.intelexpress-view-table thead th{position:sticky;top:0;z-index:6;background:#f9fafb;font-weight:700}
.intelexpress-view-table th:first-child,.intelexpress-view-table td:first-child{position:sticky;left:0;z-index:7;background:#fff}
.intelexpress-view-table tbody tr:nth-child(even) td{background:rgba(240,244,247,0.6)}
.intelexpress-view-table tbody tr.search-match td{background:#fff7ed}

/* Header layout: left = title/subtitle, right = rows + search + button. */
.intelexpress-header{border-bottom:0;margin-bottom:8px;padding-bottom:6px;display:flex;align-items:center;justify-content:space-between;position:relative}
.intelexpress-header .ie-left{min-width:0}
.intelexpress-header .ie-right{display:flex;align-items:center;gap:12px;min-width:0}

/* Reserve space on the right so that an externally positioned modal close button (the 'X')
    at the very top-right does not visually collide with the search controls. */
.intelexpress-header{padding-right:56px}

.intelexpress-header .ie-rows{font-size:0.95rem;color:#666;flex:0 0 auto}
.intelexpress-header .ie-search{flex:1;min-width:0;max-width:360px;padding:8px 10px;border:1px solid #ddd;border-radius:8px}
.intelexpress-header .ie-search-btn{white-space:nowrap}
</style>
<div class="intelexpress-viewer" style="font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;">
    <div class="intelexpress-header">
        <div class="ie-left">
            <h3 style="margin:0;font-size:1.05rem;color:#1f2937;font-weight:700;"><?php echo $partnerName . ' - ' . $dateStr; ?></h3>
            <div style="font-size:0.92rem;color:#6b7280;margin-top:6px;">Web Data</div>
        </div>
        <div class="ie-right">
            <div class="ie-rows">Rows: <strong id="intelexpressViewerCount"><?php echo number_format(count($rows)); ?></strong></div>
            <input id="intelexpressViewerSearch" class="ie-search" placeholder="Search" />
            <button id="intelexpressViewerSearchBtn" class="material-btn ie-search-btn">Search</button>
        </div>
    </div>
    <div id="intelexpressViewerContainer">
        <table id="intelexpressViewerTable" class="intelexpress-view-table" border="1" cellpadding="8" cellspacing="0">
            <thead><tr>
<?php
$headers = [];
if (count($rows) > 0) {
    foreach ($rows as $r) {
        foreach (array_keys((array) $r) as $k) $headers[$k] = true;
    }
    $headers = array_keys($headers);
}
$ordered = ['NO','CONTROL SERIES NO','DATE CLAIMED','KPTN','CCREF NO','CURRENCY','AMOUNT','CTC','CTP','SENDER NAME','SENDER COUNTRY','BENEFICIARY/RECEIVER','RECEIVER KYC','RECEIVER PHONE','OPERATOR','BRANCH','REMOTE OPERATOR','REMOTE BRANCH'];
$display = [];
foreach ($ordered as $col) if (in_array($col, $headers, true)) $display[$col] = $col;
foreach ($headers as $h) if (!isset($display[$h])) $display[$h] = $h;
if (empty($display)) echo '<th>No data</th>';
else foreach ($display as $hk => $orig) echo '<th>' . htmlspecialchars($hk) . '</th>';
?>
            </tr></thead>
            <tbody>
<?php
foreach ($rows as $r) {
    echo '<tr data-visible="1">';
    if (empty($display)) {
        echo '<td></td>';
    } else {
        foreach (array_keys($display) as $hk) {
            $v = is_array($r) && array_key_exists($hk, $r) ? $r[$hk] : '';
            echo '<td>' . htmlspecialchars((string) $v) . '</td>';
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
;(function(){
    const container = document.getElementById('intelexpressViewerContainer');
    const table = document.getElementById('intelexpressViewerTable');
    const rows = container ? container.querySelectorAll('tbody tr') : [];
    const countEl = document.getElementById('intelexpressViewerCount');
    const search = document.getElementById('intelexpressViewerSearch');
    const btn = document.getElementById('intelexpressViewerSearchBtn');
    function doFilter(){
        const q = (search && search.value || '').trim().toLowerCase();
        let cnt = 0;
        let firstMatch = null;
        if(q === ''){
            rows.forEach(r=>{ r.style.display=''; r.classList.remove('search-match'); cnt++; });
        } else {
            rows.forEach(r=>{ r.style.display='none'; r.classList.remove('search-match'); });
            for(let i=0;i<rows.length;i++){
                const r = rows[i];
                const txt = (r.textContent || '').toLowerCase();
                if(txt.indexOf(q) !== -1){ firstMatch = r; cnt = 1; break; }
            }
            if(firstMatch){ firstMatch.style.display=''; firstMatch.classList.add('search-match'); }
        }
        if(countEl) countEl.textContent = String(cnt);
    }
    if(search) search.addEventListener('input', doFilter);
    if(btn) btn.addEventListener('click', doFilter);
})();
</script>
<?php
echo ob_get_clean();