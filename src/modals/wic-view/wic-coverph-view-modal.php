<?php
// wic-coverph-view-modal.php
// Simple frontend modal for viewing Cover PH summary (Partner / Web)
?>
<link rel="stylesheet" href="<?= htmlspecialchars((string)($appBaseUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>/src/modals/wic-view/wic-coverph-view-modal.css">
<div id="wicCoverPhModal" class="wic-coverph-modal" style="display:none;">
    <div class="wic-coverph-modal__box">
    <button type="button" class="wic-coverph-modal__export" data-action="export-coverph" aria-label="Export">Export</button>
    <button type="button" class="wic-coverph-modal__close" data-action="close-coverph" aria-label="Close">×</button>
        <h3>WORLD INTERNATIONAL COMMUNICATIONS PESO — Summary</h3>
        <div class="wic-coverph-modal__summary">
            <div class="summary-item">Principal (Partner): <span data-role="principal-partner">0 pesos</span></div>
            <div class="summary-item">Principal (Web): <span data-role="principal-web">0 pesos</span></div>
            <div class="summary-item">Commission (Partner): <span data-role="commission-partner">0 pesos</span></div>
            <div class="summary-item">Commission (Web): <span data-role="commission-web">0 pesos</span></div>
        </div>

        <div class="wic-coverph-modal__tables">
            <h4>PESO Daily Summary</h4>
            <div class="wic-coverph-large-table-wrap">
                <table class="wic-coverph-large" role="table">
                    <thead>
                        <tr>
                            <th rowspan="2">Date</th>
                            <th colspan="2">WORLD INTERNATIONAL COMMUNICATIONS PHP</th>
                            <th colspan="3">WEB KPX</th>
                            <th colspan="3">NET WEB REPORT</th>
                            <th colspan="3">PARTNER VS. WEB</th>
                        </tr>
                        <tr>
                            <th>Vol</th><th>Principal</th>
                            <th>Vol</th><th>Principal</th><th>Commission</th>
                            <th>Vol</th><th>Principal</th><th>Commission</th>
                            <th>Vol</th><th>Principal</th><th>Commission</th>
                        </tr>
                    </thead>
                    <tbody>
<?php for($i=1;$i<=32;$i++): ?>
                        <tr data-day="<?= $i ?>">
                            <td class="col-date"><?= $i ?></td>
                            <td class="wic-vol"></td>
                            <td class="wic-principal"></td>

                            <td class="webkpi-vol"></td>
                            <td class="webkpi-principal"></td>
                            <td class="webkpi-commission"></td>

                            <td class="netweb-vol"></td>
                            <td class="netweb-principal"></td>
                            <td class="netweb-commission"></td>

                            <td class="pvsw-vol"></td>
                            <td class="pvsw-principal"></td>
                            <td class="pvsw-commission"></td>
                        </tr>
<?php endfor; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>TOTAL</th>
                            <th colspan="2" data-role="total-mbtc">0 / 0</th>
                            <th colspan="3" data-role="total-webkpi">0 / 0 / 0</th>
                            <th colspan="3" data-role="total-netweb">0 / 0 / 0</th>
                            <th colspan="3" data-role="total-pvsw">0 / 0 / 0</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <!-- numbered strip removed per request -->
        </div>
    </div>
</div>
<script>
(function(){
    const modal = document.getElementById('wicCoverPhModal');
    if(!modal) return;
    const close = modal.querySelector('[data-action="close-coverph"]');
    if(close) close.addEventListener('click', function(){ modal.style.display='none'; try{ document.body.style.overflow=''; }catch(e){} });
    const exportBtn = modal.querySelector('[data-action="export-coverph"]');
    if(exportBtn) exportBtn.addEventListener('click', async function(){
        const table = modal.querySelector('.wic-coverph-large');
        if(!table) return;

        function parseNum(s){ if(s==null) return null; const v = String(s).replace(/[^0-9.\-]/g,''); const n = Number(v); return Number.isFinite(n)? n : null; }

        function ensureSheetJs(){
            if(window.XLSX) return Promise.resolve(window.XLSX);
            return new Promise((resolve,reject)=>{
                const s = document.createElement('script');
                s.src = 'https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js';
                s.onload = ()=> resolve(window.XLSX);
                s.onerror = ()=> reject(new Error('Failed to load SheetJS'));
                document.head.appendChild(s);
            });
        }

        try{
            const XLSX = await ensureSheetJs();
            const top = [
                'Date','WORLD INTERNATIONAL COMMUNICATIONS','','WEB KPX','','','NET WEB REPORT','','','PARTNER VS. WEB','',''
            ];
            const sub = ['Date','Vol','Principal','Vol','Principal','Commission','Vol','Principal','Commission','Vol','Principal','Commission'];

            const aoa = [];
            aoa.push(top);
            aoa.push(sub);

            const bodyRows = Array.from(table.querySelectorAll('tbody tr'));
            bodyRows.forEach(tr => {
                const cols = Array.from(tr.querySelectorAll('td')).map(td => td.textContent.trim());
                while(cols.length < 12) cols.push('');
                aoa.push(cols);
            });

            const tfoot = table.querySelector('tfoot tr');
            if(tfoot){
                const footerCells = [];
                const children = Array.from(tfoot.querySelectorAll('th,td'));
                children.forEach(ch => {
                    const raw = ch.textContent.trim();
                    const cs = Number(ch.getAttribute('colspan') || 1);
                    if(cs > 1 && raw.indexOf('/') !== -1){
                        const parts = raw.split('/').map(p=>p.trim());
                        for(let i=0;i<cs;i++) footerCells.push(parts[i] || '');
                    } else {
                        for(let i=0;i<cs;i++) footerCells.push(raw);
                    }
                });
                while(footerCells.length < 12) footerCells.push('');
                aoa.push(footerCells.slice(0,12));
            }

            const ws = XLSX.utils.aoa_to_sheet(aoa);
            ws['!merges'] = [
                {s:{r:0,c:1}, e:{r:0,c:2}},
                {s:{r:0,c:3}, e:{r:0,c:5}},
                {s:{r:0,c:6}, e:{r:0,c:8}},
                {s:{r:0,c:9}, e:{r:0,c:11}}
            ];

            const hdrFill = { fgColor: { rgb: 'F3F4F6' } };
            const headerStyle = { font: { bold: true }, alignment: { horizontal: 'center', vertical: 'center' }, fill: hdrFill };
            for(let C=0; C<=11; C++){
                const topAddr = XLSX.utils.encode_cell({r:0,c:C}); if(ws[topAddr]) ws[topAddr].s = Object.assign({}, ws[topAddr].s || {}, headerStyle);
                const subAddr = XLSX.utils.encode_cell({r:1,c:C}); if(ws[subAddr]) ws[subAddr].s = Object.assign({}, ws[subAddr].s || {}, headerStyle);
            }

            const totalRows = aoa.length;
            for(let R=2; R<totalRows; R++){
                for(let C=0; C<=11; C++){
                    const addr = XLSX.utils.encode_cell({r:R,c:C});
                    const cell = ws[addr];
                    if(!cell || cell.v == null) continue;
                    const text = String(cell.v).trim();
                    const n = parseNum(text);
                    if(n !== null){ cell.v = n; cell.t = 'n'; cell.z = '#,##0.00'; }
                }
            }
            ws['!cols'] = [
                {wch:6},
                {wch:8},{wch:14},
                {wch:8},{wch:14},{wch:12},
                {wch:8},{wch:14},{wch:12},
                {wch:8},{wch:14},{wch:12}
            ];

            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'PESO');
            // prefer month/year selectors if available to name file: 'WORLD INTERNATIONAL COMMUNICATIONS PESO — Summary - <MonthName> <Year>.xlsx'
            (function writeFileWithMonthLabel(){
                const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                const mEl = document.getElementById('hsMonth');
                const yEl = document.getElementById('hsYear');
                const now = new Date();
                const mVal = mEl ? mEl.value : String(now.getMonth()+1);
                const yVal = yEl ? yEl.value : String(now.getFullYear());
                const mm = parseInt(mVal,10);
                const monthLabel = (!Number.isNaN(mm) && mm >=1 && mm <=12) ? (monthNames[mm-1] + ' ' + yVal) : ((mVal || yVal) ? (mVal + ' ' + yVal) : now.toISOString().slice(0,10));
                const fname = 'WORLD INTERNATIONAL COMMUNICATIONS PESO — Summary - ' + monthLabel + '.xlsx';
                XLSX.writeFile(wb, fname);
            })();
        }catch(err){ console.error('Export failed', err); try{ alert('Export failed: ' + err.message); }catch(e){} }
    });
    modal.addEventListener('click', function(ev){ if(ev.target === modal){ modal.style.display='none'; try{ document.body.style.overflow=''; }catch(e){} } });
    document.addEventListener('keydown', function keyHandler(ev){ if(ev.key === 'Escape'){ if(modal.style.display === 'block'){ modal.style.display='none'; try{ document.body.style.overflow=''; }catch(e){} } } });

    const tablesContainer = modal.querySelector('.wic-coverph-modal__tables');
    if(tablesContainer){ tablesContainer.addEventListener('wheel', function(e){ /* native */ }, { passive: true }); }
    // Compute footer totals from tbody data and update summary
    function parseCellNumber(text){ if(!text) return 0; const m = String(text).match(/-?[\d,]+(?:\.\d+)?/); if(!m) return 0; return Number(m[0].replace(/,/g,'')); }
    function fmt(n){ return n.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}); }
    function computeTotals(){
        try{
            const tbody = modal.querySelector('tbody');
            if(!tbody) return;
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const sums = {
                wicVol:0, wicPrincipal:0,
                webVol:0, webPrincipal:0, webCommission:0,
                netVol:0, netPrincipal:0, netCommission:0,
                pvswVol:0, pvswPrincipal:0, pvswCommission:0
            };
            rows.forEach(tr => {
                const get = cls => { const td = tr.querySelector('.' + cls); return td ? td.textContent.trim() : ''; };
                sums.wicVol += parseCellNumber(get('wic-vol'));
                sums.wicPrincipal += parseCellNumber(get('wic-principal'));

                sums.webVol += parseCellNumber(get('webkpi-vol'));
                sums.webPrincipal += parseCellNumber(get('webkpi-principal'));
                sums.webCommission += parseCellNumber(get('webkpi-commission'));

                sums.netVol += parseCellNumber(get('netweb-vol'));
                sums.netPrincipal += parseCellNumber(get('netweb-principal'));
                sums.netCommission += parseCellNumber(get('netweb-commission'));

                sums.pvswVol += parseCellNumber(get('pvsw-vol'));
                sums.pvswPrincipal += parseCellNumber(get('pvsw-principal'));
                sums.pvswCommission += parseCellNumber(get('pvsw-commission'));
            });

            const totalMbtc = modal.querySelector('[data-role="total-mbtc"]');
            const totalWeb = modal.querySelector('[data-role="total-webkpi"]');
            const totalNet = modal.querySelector('[data-role="total-netweb"]');
            const totalPvsw = modal.querySelector('[data-role="total-pvsw"]');

            if(totalMbtc) totalMbtc.textContent = fmt(sums.wicVol) + ' / ' + fmt(sums.wicPrincipal);
            if(totalWeb) totalWeb.textContent = fmt(sums.webVol) + ' / ' + fmt(sums.webPrincipal) + ' / ' + fmt(sums.webCommission);
            if(totalNet) totalNet.textContent = fmt(sums.netVol) + ' / ' + fmt(sums.netPrincipal) + ' / ' + fmt(sums.netCommission);
            if(totalPvsw) totalPvsw.textContent = fmt(sums.pvswVol) + ' / ' + fmt(sums.pvswPrincipal) + ' / ' + fmt(sums.pvswCommission);

            // After totals change, refresh summary
            try{ populateSummary(); }catch(e){ }
        }catch(e){ }
    }

    // Observe tbody mutations to recompute totals when data is populated dynamically
    try{
        const tbody = modal.querySelector('tbody');
        if(tbody){
            const mo = new MutationObserver(function(){ computeTotals(); });
            mo.observe(tbody, { childList: true, subtree: true, characterData: true });
        }
    }catch(e){ }

    // compute once on load
    computeTotals();
    // Populate summary totals from tfoot when modal opens
    function parseGroupRaw(raw){
        if(!raw) return [0,0,0];
        const parts = raw.split('/').map(p=>p.trim());
        const toNum = s=>{ if(!s) return 0; const m = String(s).match(/-?[\d,]+(?:\.\d+)?/); if(!m) return 0; return Number(m[0].replace(/,/g,'')); };
        return [ toNum(parts[0]||''), toNum(parts[1]||''), toNum(parts[2]||'') ];
    }

    function populateSummary(){
        try{
            const elPrincipalPartner = modal.querySelector('[data-role="principal-partner"]');
            const elPrincipalWeb = modal.querySelector('[data-role="principal-web"]');
            const elCommissionPartner = modal.querySelector('[data-role="commission-partner"]');
            const elCommissionWeb = modal.querySelector('[data-role="commission-web"]');

            const totalWeb = modal.querySelector('[data-role="total-webkpi"]');
            const totalPvsw = modal.querySelector('[data-role="total-pvsw"]');
            const totalMbtc = modal.querySelector('[data-role="total-mbtc"]');

            const webParts = totalWeb ? parseGroupRaw(totalWeb.textContent) : [0,0,0];
            const pvswParts = totalPvsw ? parseGroupRaw(totalPvsw.textContent) : [0,0,0];
            const mbtcParts = totalMbtc ? parseGroupRaw(totalMbtc.textContent) : [0,0,0];

            if(elPrincipalWeb) elPrincipalWeb.textContent = webParts[1].toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' pesos';
            if(elCommissionWeb) elCommissionWeb.textContent = webParts[2].toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' pesos';

            // Principal (Partner) should show the WORLD INTERNATIONAL COMMUNICATIONS PHP principal total
            if(elPrincipalPartner) elPrincipalPartner.textContent = mbtcParts[1].toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' pesos';
            if(elCommissionPartner) elCommissionPartner.textContent = pvswParts[2].toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' pesos';
        }catch(e){ /* ignore */ }
    }

    // Fetch and populate table from WORLD INTERNATIONAL COMMUNICATIONS recon API, then refresh summary when modal opens
    function getSelectedMonthYear(){
        const mEl = document.getElementById('hsMonth');
        const yEl = document.getElementById('hsYear');
        const now = new Date();
        const month = mEl ? mEl.value : String(now.getMonth()+1);
        const year = yEl ? yEl.value : String(now.getFullYear());
        return { month: String(month), year: String(year) };
    }

    async function fetchData(){
        try{
            const sel = getSelectedMonthYear();
            const partnerEl = document.getElementById('hsCompany');
            const selectedPartner = partnerEl && partnerEl.value ? String(partnerEl.value) : 'WORLDCOM INTERNATIONAL COMMUNICATIONS';
            const url = window.autoreconBaseUrl + '/src/controllers/recon/wic-recon.php?month='+encodeURIComponent(sel.month)+'&year='+encodeURIComponent(sel.year)+'&partnerName='+encodeURIComponent(selectedPartner);
            const res = await fetch(url, {cache:'no-store'});
            if(!res.ok) throw new Error('Network response was not ok');
            const json = await res.json();
            if(!json || !json.success || !Array.isArray(json.days)) return;

            // clear existing rows first
            const tbody = modal.querySelector('tbody');
            if(!tbody) return;

            // Populate rows by matching data-day attribute
            json.days.forEach(d => {
                const day = Number(d.day) || 0;
                const tr = tbody.querySelector('tr[data-day="' + day + '"]');
                if(!tr) return;
                const set = (cls, val) => { const td = tr.querySelector('.' + cls); if(td) td.textContent = (val == null ? '' : (typeof val === 'number' ? fmt(val) : String(val))); };

                // WORLD INTERNATIONAL COMMUNICATIONS columns
                set('wic-vol', d.vol ?? 0);
                set('wic-principal', d.total_partner_amount ?? d.principal ?? 0);

                // WEB KPX
                set('webkpi-vol', d.vol ?? 0);
                set('webkpi-principal', d.web_principal ?? 0);
                set('webkpi-commission', d.web_commission ?? d.commission ?? 0);

                // NET WEB (mirror web values for now)
                set('netweb-vol', d.vol ?? 0);
                set('netweb-principal', d.web_principal ?? 0);
                set('netweb-commission', d.web_commission ?? d.commission ?? 0);

                // PARTNER VS WEB: show variance and commission.
                // Partner has no commission details in the simplified WORLD INTERNATIONAL COMMUNICATIONS schema,
                // so represent partner-side commission as the negative of the web commission
                // so that partner + web sums cancel when partner lacks commission info.
                set('pvsw-vol', d.vol ?? 0);
                set('pvsw-principal', d.variance ?? ( (d.total_partner_amount ?? 0) - (d.total_web_amount ?? 0) ) ?? 0);
                const webComm = (d.web_commission ?? d.commission ?? 0);
                set('pvsw-commission', -(webComm));
            });

            // recompute totals after population
            try{ computeTotals(); }catch(e){}
            try{ populateSummary(); }catch(e){}
        }catch(err){ console.error('Failed to load WORLD INTERNATIONAL COMMUNICATIONS CoverPH data', err); }
    }

    // Observe modal visibility to fetch data and refresh summary when opened
    try{
        const mo = new MutationObserver(function(){
            if(modal.style.display && modal.style.display !== 'none'){
                fetchData();
                populateSummary();
            }
        });
        mo.observe(modal, { attributes: true, attributeFilter: ['style'] });
    }catch(e){ }

    // run once in case modal already visible
    if(modal.style.display && modal.style.display !== 'none'){
        fetchData();
        populateSummary();
    }
})();
</script>
