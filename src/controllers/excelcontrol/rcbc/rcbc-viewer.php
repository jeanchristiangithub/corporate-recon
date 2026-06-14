<?php
// rcbc-viewer.php
// Expects JSON POST with { data: { filename, dateStr, rows } }

$raw = file_get_contents('php://input');
if(!$raw){ echo '<div>No data provided</div>'; exit; }

$payload = json_decode($raw, true);
if(!$payload || !isset($payload['data'])){ echo '<div>Invalid data</div>'; exit; }

$data = $payload['data'];
$rows = isset($data['rows']) && is_array($data['rows']) ? $data['rows'] : [];
$dateStr = isset($data['dateStr']) ? htmlspecialchars($data['dateStr']) : '';
$filename = isset($data['filename']) ? htmlspecialchars($data['filename']) : '';
$viewType = isset($data['viewType']) ? (string)$data['viewType'] : '';
$partnerName = isset($data['partnerName']) ? htmlspecialchars($data['partnerName']) : '';

ob_start();
?>
<style>
.rcbc-viewer{padding:14px 10px;color:#1f2937}
.rcbc-viewer h3{display:block;margin:0 0 6px 0;color:#1f2937}
.rcbc-view-meta{display:flex;gap:12px;align-items:center;margin-bottom:10px}
.rcbc-viewer #rcbcViewerContainer{overflow:auto;max-height:78vh}
.rcbc-view-table{border-collapse:collapse;width:auto;min-width:1400px;table-layout:fixed;font-size:13px;border-spacing:0}
.rcbc-view-table th,
.rcbc-view-table td{
    border:1px solid #e5e7eb;
    padding:8px 10px;
    text-align:left;
    vertical-align:middle;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    background:#fff;
}
.rcbc-view-table thead th{
    position:sticky;
    top:0;
    z-index:6;
    background:#f9fafb;
    font-weight:700;
}
.rcbc-view-table th:first-child,
.rcbc-view-table td:first-child{
    position:sticky;
    left:0;
    z-index:7;
    background:#fff;
}
.rcbc-view-table tbody tr:nth-child(even) td{background:rgba(240,244,247,0.6)}
.rcbc-view-table tbody tr.search-match td{ background: #fff7ed; }
.rcbc-viewer input#rcbcViewerSearch{padding:8px 10px;border-radius:8px;border:1px solid #e5e7eb}
.rcbc-viewer .viewer-title{font-weight:600;color:#1f2937}
/* Reserve space on the right of the header for a modal close button */
.rcbc-recon-modal__head{padding-right:56px}
@media (max-width:720px){
    .rcbc-recon-modal__head{padding-right:12px}
    .rcbc-viewer input#rcbcViewerSearch{max-width:180px}
}
</style>
<div class="rcbc-viewer" style="font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;">
    <div class="rcbc-recon-modal__head" style="border-bottom:0;margin-bottom:8px;padding-bottom:6px;display:flex;align-items:center;justify-content:space-between">
        <div>
            <h3 style="margin:0;font-size:1.05rem;color:#1f2937;font-weight:700;">
                <?php echo (($partnerName && $partnerName !== '') ? $partnerName : 'RCBC') . ' - ' . $dateStr; ?>
            </h3>
            <div style="font-size:0.92rem;color:#6b7280;margin-top:6px;"><?php echo ($viewType === 'partner' ? 'Partner Data' : ($viewType === 'web' ? 'Web Data' : 'Data')); ?></div>
        </div>
            <div style="display:flex;align-items:center;gap:12px;min-width:0">
            <div style="font-size:0.95rem;color:#666;flex:0 0 auto">Rows: <strong id="rcbcViewerCount"><?php echo number_format(count($rows)); ?></strong></div>
            <input id="rcbcViewerSearch" placeholder="Search" style="flex:1;min-width:0;max-width:360px;padding:8px 10px;border:1px solid #ddd;border-radius:8px" />
            <button id="rcbcViewerSearchBtn" class="material-btn" style="white-space:nowrap">Search</button>
        </div>
    </div>
    <div style="overflow:auto; max-height:78vh;" id="rcbcViewerContainer">
        <table id="rcbcViewerTable" class="rcbc-view-table" border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:auto;min-width:1400px">
            <thead>
                <tr>
<?php
$headers = [];
if(count($rows) > 0){
    foreach($rows as $r){
        if(is_array($r)){
            foreach(array_keys($r) as $k) $headers[$k] = true;
        } elseif(is_object($r)){
            foreach(array_keys(get_object_vars($r)) as $k) $headers[$k] = true;
        }
    }
    $headers = array_values(array_keys($headers));
}
if(empty($headers)){
    echo '<th>No data</th>';
} else {
    $ordered = [];
    if($viewType === 'web'){
        $ordered = ['no','control_series_no','date_claimed','kptn','ccref_no','currency','amount','ctc','ctp','sender_name','sender_country','beneficiary_receiver','receiver_kyc','receiver_phone','operator','branch','remote_operator','remote_branch'];
    } elseif($viewType === 'partner'){
        $ordered = ['date','time','reference_no','rts_tracer_no','provider','beneficiary_name','remitter_name','php','usd','in_php'];
    }

    function pretty_label($k){
        return ucwords(str_replace(['_','-'], [' ',' '], $k));
    }

    $display = [];
    if(!empty($ordered)){
        foreach($ordered as $col){
            if(in_array($col, $headers)) { $display[$col] = $col; }
            elseif(in_array('web_'.$col, $headers)) { $display['web_'.$col] = $col; }
            elseif(in_array('partner_'.$col, $headers)) { $display['partner_'.$col] = $col; }
        }
    }
    foreach($headers as $h){ if(!isset($display[$h])) $display[$h] = $h; }

    $partnerMap = [ 'date'=>'Date','time'=>'Time','reference_no'=>'Reference No.','rts_tracer_no'=>'RTS Tracer No.','provider'=>'Provider','beneficiary_name'=>'Beneficiary Name','remitter_name'=>'Remitter Name','php'=>'PHP','usd'=>'USD','in_php'=>'in PHP','cover_date'=>'Cover Date','partnerName'=>'Partner' ];
    $webMap = [ 'no'=>'NO','control_series_no'=>'CONTROL SERIES NO','date_claimed'=>'DATE CLAIMED','kptn'=>'KPTN','ccref_no'=>'CCREF NO','currency'=>'CURRENCY','amount'=>'AMOUNT','ctc'=>'CTC','ctp'=>'CTP','sender_name'=>'SENDER NAME','sender_country'=>'SENDER COUNTRY','beneficiary_receiver'=>'BENEFICIARY/RECEIVER','receiver_kyc'=>'RECEIVER KYC','receiver_phone'=>'RECEIVER PHONE','operator'=>'OPERATOR','branch'=>'BRANCH','remote_operator'=>'REMOTE OPERATOR','remote_branch'=>'REMOTE BRANCH','partnerName'=>'Partner' ];

    foreach($display as $hk => $orig){
        $label = '';
        if(strpos($hk,'partner_') === 0){ $col = substr($hk,8); $label = $partnerMap[$col] ?? pretty_label($col); }
        elseif(strpos($hk,'web_') === 0){ $col = substr($hk,4); $label = $webMap[$col] ?? pretty_label($col); }
        else { if(isset($partnerMap[$hk])) $label = $partnerMap[$hk]; elseif(isset($webMap[$hk])) $label = $webMap[$hk]; else $label = pretty_label($hk); }
        echo '<th>'.htmlspecialchars($label).'</th>';
    }
}
?>
                </tr>
            </thead>
            <tbody>
<?php
foreach($rows as $r){
    echo '<tr data-visible="1">';
    if(empty($display)){
        echo '<td></td>';
    } else {
        foreach(array_keys($display) as $hk){
            $v = '';
            if(is_array($r) && array_key_exists($hk, $r)) $v = $r[$hk];
            elseif(is_object($r) && property_exists($r, $hk)) $v = $r->$hk;
            if(($v === null || $v === '') && strpos($hk,'partner_')===0){
                $fb = substr($hk,8); if(is_array($r) && array_key_exists($fb,$r)) $v = $r[$fb];
            }
            if(($v === null || $v === '') && strpos($hk,'web_')===0){
                $fb = substr($hk,4); if(is_array($r) && array_key_exists($fb,$r)) $v = $r[$fb];
            }
            if(is_array($v) || is_object($v)) $cell = htmlspecialchars(json_encode($v));
            else $cell = htmlspecialchars((string)$v);
            echo '<td>'.$cell.'</td>';
        }
    }
    echo '</tr>';
}
?>
            </tbody>
        </table>
    </div>
</div>
<?php
    ?>
    <script>
    ;(function(){
        const VIEW_TYPE = <?php echo json_encode($viewType); ?>;
        const root = document.currentScript && document.currentScript.parentNode;
        const container = document.getElementById('rcbcViewerContainer');
        const table = document.getElementById('rcbcViewerTable');
        const rows = container ? container.querySelectorAll('tbody tr') : [];
        const countEl = document.getElementById('rcbcViewerCount');
        const search = document.getElementById('rcbcViewerSearch');
        const btn = document.getElementById('rcbcViewerSearchBtn');

        function findHeaderIndex(candidates){
            if(!table) return -1;
            const headers = Array.from(table.querySelectorAll('thead th'));
            const normalized = headers.map(h => String(h.textContent || '').trim().toLowerCase());
            for(let i=0;i<normalized.length;i++){
                const label = normalized[i];
                for(let j=0;j<candidates.length;j++){
                    if(label === candidates[j] || label.indexOf(candidates[j]) !== -1) return i;
                }
            }
            return -1;
        }

        const webNoIndex = findHeaderIndex(['no']);
        const partnerPartIdIndex = findHeaderIndex(['part id','reference no.','reference no','reference']);

        function doFilter(){
            const q = (search && search.value || '').trim().toLowerCase();
            let cnt = 0;
            let firstMatch = null;
            if(q === ''){
                rows.forEach(r=>{
                    r.style.display = '';
                    r.classList.remove('search-match');
                    cnt++;
                });
            } else {
                rows.forEach(r=>{ r.style.display = 'none'; r.classList.remove('search-match'); });
                for(let i=0;i<rows.length;i++){
                    const r = rows[i];
                    let key = '';
                    if(VIEW_TYPE === 'web'){
                        const NO_RECORD_ID = 'rcbcNoRecord';
                        const idx = webNoIndex >= 0 ? webNoIndex : -1;
                        const qNorm = q.replace(/\W/g,'').toLowerCase();
                        let found = null;
                        for(let i=0;i<rows.length;i++){
                            const r2 = rows[i];
                            if(idx >= 0){
                                const cell = (r2.cells[idx] && r2.cells[idx].textContent) ? String(r2.cells[idx].textContent).trim() : '';
                                const cellNorm = cell.replace(/\W/g,'').toLowerCase();
                                if(qNorm !== '' && cellNorm === qNorm){ found = r2; break; }
                            }
                        }
                        if(!found && idx >= 0){
                            for(let i=0;i<rows.length;i++){
                                const r2 = rows[i];
                                const cell = (r2.cells[idx] && r2.cells[idx].textContent) ? String(r2.cells[idx].textContent).toLowerCase() : '';
                                if(cell.indexOf(q) !== -1){ found = r2; break; }
                            }
                        }
                        if(!found){
                            for(let i=0;i<rows.length;i++){
                                const r2 = rows[i];
                                const txt = (r2.textContent || '').toLowerCase();
                                if(txt.indexOf(q) !== -1){ found = r2; break; }
                            }
                        }

                        if(found){
                            firstMatch = found; firstMatch.style.display = '';
                            try{ firstMatch.classList.add('search-match'); firstMatch.scrollIntoView({behavior:'smooth', block:'center'}); }catch(e){}
                            cnt = 1;
                        } else {
                            const prev = document.getElementById(NO_RECORD_ID); if(prev) prev.remove();
                            const msg = document.createElement('div'); msg.id = NO_RECORD_ID; msg.style.marginTop='8px'; msg.style.color='#6b7280'; msg.style.fontSize='0.95rem'; msg.textContent='No record found'; if(container && container.parentNode) container.parentNode.insertBefore(msg, container.nextSibling);
                            cnt = 0;
                        }
                    } else {
                        if(partnerPartIdIndex >= 0){
                            key = (r.cells[partnerPartIdIndex] && r.cells[partnerPartIdIndex].textContent) ? String(r.cells[partnerPartIdIndex].textContent).toLowerCase() : '';
                        } else {
                            key = (r.textContent || '').toLowerCase();
                        }
                    }
                    if(key.indexOf(q) !== -1){ firstMatch = r; cnt = 1; break; }
                }
                if(firstMatch) firstMatch.style.display = '';
            }
            if(countEl) {
                try{ countEl.textContent = (cnt).toLocaleString(); }catch(e){ countEl.textContent = String(cnt); }
            }
            return firstMatch;
        }

        if(search) search.addEventListener('input', doFilter);
        if(btn) btn.addEventListener('click', doFilter);
    })();
    </script>
    <?php
    $out = ob_get_clean();
    echo $out;
