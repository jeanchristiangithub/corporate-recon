<?php
// wic-usd-view-modal.php
// Minimal frontend modal for viewing WORLD INTERNATIONAL COMMUNICATIONS USD summary (Partner / Web)
?>
<link rel="stylesheet" href="/autorecon/src/modals/wic-view/wic-usd-view-modal.css">
<div id="wicUsdModal" class="wic-usd-modal" style="display:none;">
    <div class="wic-usd-modal__box">
        <button type="button" class="wic-usd-modal__export" data-action="export-usd" aria-label="Export">Export</button>
        <button type="button" class="wic-usd-modal__close" data-action="close-usd" aria-label="Close">×</button>
        <h3>WORLD INTERNATIONAL COMMUNICATIONS USD — Summary</h3>
        <div class="wic-usd-modal__summary">
            <div class="summary-item">Principal (Partner): <span data-role="principal-partner">0 USD</span></div>
            <div class="summary-item">Principal (Web): <span data-role="principal-web">0 USD</span></div>
            <div class="summary-item">Commission (Partner): <span data-role="commission-partner">0 USD</span></div>
            <div class="summary-item">Commission (Web): <span data-role="commission-web">0 USD</span></div>
        </div>

            <div class="wic-usd-modal__tables">
            <h4>USD Daily Summary</h4>
            <div class="wic-usd-large-table-wrap">
                <table class="wic-usd-large" role="table">
                    <thead>
                        <tr>
                            <th rowspan="2">Date</th>
                            <th colspan="2">WORLD INTERNATIONAL COMMUNICATIONS USD</th>
                            <th colspan="3">WEB KPX</th>
                            <th colspan="3">NET WEB REPORT</th>
                            <th colspan="3">PARTNER VS. WEB</th>
                        </tr>
                        <tr>
                            <th>Vol</th><th>Principal</th>
                            <th>Vol</th><th>Principal</th><th>COMMISSION</th>
                            <th>Vol</th><th>Principal</th><th>COMMISSION</th>
                            <th>Vol</th><th>Principal</th><th>COMMISSION</th>
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
                            <th colspan="2" data-role="total-wic">0 / 0</th>
                            <th colspan="3" data-role="total-webkpi">0 / 0 / 0</th>
                            <th colspan="3" data-role="total-netweb">0 / 0 / 0</th>
                            <th colspan="3" data-role="total-pvsw">0 / 0 / 0</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const modal = document.getElementById('wicUsdModal');
    if(!modal) return;
    const close = modal.querySelector('[data-action="close-usd"]');
    if(close) close.addEventListener('click', function(){ modal.style.display='none'; try{ document.body.style.overflow=''; }catch(e){} });

    // simple close on backdrop
    modal.addEventListener('click', function(ev){ if(ev.target === modal){ modal.style.display='none'; try{ document.body.style.overflow=''; }catch(e){} } });
    document.addEventListener('keydown', function(ev){ if(ev.key === 'Escape'){ if(modal.style.display === 'block'){ modal.style.display='none'; try{ document.body.style.overflow=''; }catch(e){} } } });

    // export handler: XLSX export using SheetJS to match grouped header layout
    const exportBtn = modal.querySelector('[data-action="export-usd"]');
    if(exportBtn) exportBtn.addEventListener('click', async function(){
        const table = modal.querySelector('.wic-usd-large'); if(!table) return;

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

            // top grouping headers (row 0) and sub headers (row 1)
            const top = ['Date','WORLD INTERNATIONAL COMMUNICATIONS USD','','WEB KPX','','','NET WEB REPORT','','','PARTNER VS. WEB','',''];
            const sub = ['Date','Vol','Principal','Vol','Principal','COMMISSION','Vol','Principal','COMMISSION','Vol','Principal','COMMISSION'];

            const aoa = [];
            aoa.push(top);
            aoa.push(sub);

            // body rows
            const bodyRows = Array.from(table.querySelectorAll('tbody tr'));
            bodyRows.forEach(tr => {
                const cols = Array.from(tr.querySelectorAll('td')).map(td => td.textContent.trim());
                while(cols.length < 12) cols.push('');
                aoa.push(cols.slice(0,12));
            });

            // footer
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

            // merges for grouped top header
            ws['!merges'] = [
                {s:{r:0,c:1}, e:{r:0,c:2}}, // WORLD INTERNATIONAL COMMUNICATIONS USD (cols 1-2)
                {s:{r:0,c:3}, e:{r:0,c:5}}, // WEB KPX (cols 3-5)
                {s:{r:0,c:6}, e:{r:0,c:8}}, // NET WEB REPORT (cols 6-8)
                {s:{r:0,c:9}, e:{r:0,c:11}} // PARTNER VS. WEB (cols 9-11)
            ];

            // header styling
            const hdrFill = { fgColor: { rgb: 'F3F4F6' } };
            const headerStyle = { font: { bold: true }, alignment: { horizontal: 'center', vertical: 'center' }, fill: hdrFill };
            for(let C=0; C<12; C++){
                const topAddr = XLSX.utils.encode_cell({r:0,c:C}); if(ws[topAddr]) ws[topAddr].s = Object.assign({}, ws[topAddr].s || {}, headerStyle);
                const subAddr = XLSX.utils.encode_cell({r:1,c:C}); if(ws[subAddr]) ws[subAddr].s = Object.assign({}, ws[subAddr].s || {}, headerStyle);
            }

            // convert numeric-looking cells to numbers for rows starting at R=2
            const totalRows = aoa.length;
            for(let R=2; R<totalRows; R++){
                for(let C=0; C<12; C++){
                    const addr = XLSX.utils.encode_cell({r:R,c:C});
                    const cell = ws[addr]; if(!cell || cell.v == null) continue;
                    const text = String(cell.v).trim();
                    const n = parseNum(text);
                    if(n !== null){ cell.v = n; cell.t = 'n'; cell.z = '#,##0.00'; }
                }
            }

            // column widths
            ws['!cols'] = [
                {wch:6}, // Date
                {wch:10},{wch:14}, // WORLD INTERNATIONAL COMMUNICATIONS USD
                {wch:8},{wch:14},{wch:12}, // WEB KPX
                {wch:8},{wch:14},{wch:12}, // NET WEB REPORT
                {wch:8},{wch:14},{wch:12} // PARTNER VS. WEB
            ];

            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'WORLD INTL COMM USD');
            // name file using selected month/year when available: 'WORLD INTERNATIONAL COMMUNICATIONS USD — Summary - <MonthName> <Year>.xlsx'
            (function writeFileWithMonthLabel(){
                const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                const mEl = document.getElementById('hsMonth');
                const yEl = document.getElementById('hsYear');
                const now = new Date();
                const mVal = mEl ? mEl.value : String(now.getMonth()+1);
                const yVal = yEl ? yEl.value : String(now.getFullYear());
                const mm = parseInt(mVal,10);
                const monthLabel = (!Number.isNaN(mm) && mm >=1 && mm <=12) ? (monthNames[mm-1] + ' ' + yVal) : ((mVal || yVal) ? (mVal + ' ' + yVal) : now.toISOString().slice(0,10));
                const fname = 'WORLD INTERNATIONAL COMMUNICATIONS USD — Summary - ' + monthLabel + '.xlsx';
                XLSX.writeFile(wb, fname);
            })();
        }catch(err){ console.error('Export failed', err); try{ alert('Export failed: ' + err.message); }catch(e){} }
    });

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

            const totalWic = modal.querySelector('[data-role="total-wic"]');
            const totalWeb = modal.querySelector('[data-role="total-webkpi"]');
            const totalNet = modal.querySelector('[data-role="total-netweb"]');
            const totalPvsw = modal.querySelector('[data-role="total-pvsw"]');

            if(totalWic) totalWic.textContent = fmt(sums.wicVol) + ' / ' + fmt(sums.wicPrincipal);
            if(totalWeb) totalWeb.textContent = fmt(sums.webVol) + ' / ' + fmt(sums.webPrincipal) + ' / ' + fmt(sums.webCommission);
            if(totalNet) totalNet.textContent = fmt(sums.netVol) + ' / ' + fmt(sums.netPrincipal) + ' / ' + fmt(sums.netCommission);
            if(totalPvsw) totalPvsw.textContent = fmt(sums.pvswVol) + ' / ' + fmt(sums.pvswPrincipal) + ' / ' + fmt(sums.pvswCommission);

            // update summary box
            try{ populateSummary(); }catch(e){}
        }catch(e){ console.warn('computeTotals error', e); }
    }

    // Observe tbody mutations to recompute totals when data is populated dynamically
    try{
        const tbodyObs = modal.querySelector('tbody');
        if(tbodyObs){
            const mo = new MutationObserver(function(){ computeTotals(); });
            mo.observe(tbodyObs, { childList: true, subtree: true, characterData: true });
        }
    }catch(e){ }

    // Populate summary box from footer totals
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
            const totalWic = modal.querySelector('[data-role="total-wic"]');

            const webParts = totalWeb ? parseGroupRaw(totalWeb.textContent) : [0,0,0];
            const pvswParts = totalPvsw ? parseGroupRaw(totalPvsw.textContent) : [0,0,0];
            const wicParts = totalWic ? parseGroupRaw(totalWic.textContent) : [0,0];

            if(elPrincipalWeb) elPrincipalWeb.textContent = webParts[1].toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' USD';
            if(elCommissionWeb) elCommissionWeb.textContent = webParts[2].toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' USD';

            if(elPrincipalPartner) elPrincipalPartner.textContent = wicParts[1].toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' USD';
            // partner commission shown as negative when missing on partner side
            if(elCommissionPartner) elCommissionPartner.textContent = (pvswParts[2] ? (-pvswParts[2]) : 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' USD';

            // Variance removed per UI spec
        }catch(e){ /* ignore */ }
    }

    // The USD modal no longer fetches data from the WORLD INTERNATIONAL COMMUNICATIONS recon API directly.
    // Use `populateWicUsdModal(json)` to populate the table programmatically.

    // Populate table from a supplied JSON payload (same shape as the recon API response)
    window.populateWicUsdModal = function(json){
        try{
            if(!json || !Array.isArray(json.days)) return;
            const tbody = modal.querySelector('tbody');
            if(!tbody) return;

            json.days.forEach(d => {
                const day = Number(d.day) || 0;
                const tr = tbody.querySelector('tr[data-day="' + day + '"]');
                if(!tr) return;
                const set = (cls, val) => { const td = tr.querySelector('.' + cls); if(td) td.textContent = (val == null ? '' : (typeof val === 'number' ? fmt(val) : String(val))); };

                // base values
                const wicVol = Number(d.vol || 0);
                const wicPrincipal = Number(d.total_partner_amount ?? d.principal ?? 0);
                const wicCommission = Number(d.commission ?? 0);

                const webVol = Number(d.vol || 0);
                const webPrincipal = Number(d.web_principal || 0);
                const webCommission = Number(d.web_commission ?? d.commission ?? 0);

                // duplicate amounts: if day-level rows are present, sum duplicate web rows
                let dupVolWeb = 0, dupPWeb = 0, dupCWeb = 0;
                try{
                    if(Array.isArray(d.rows) && d.rows.length){
                        d.rows.forEach(r => {
                            const rWebAmt = Number(r.web_amount || r.web_principal || r.webAmount || r.amount || 0);
                            const rWebCtp = Number(r.web_ctp || r.ctp || r.webCtp || 0);
                            if(rWebAmt || rWebCtp){ dupVolWeb += 1; dupPWeb += rWebAmt; dupCWeb += rWebCtp; }
                        });
                    }
                }catch(e){ /* ignore parsing errors */ }

                const netVol = (isFinite(webVol) ? (webVol - dupVolWeb) : '');
                const netP = webPrincipal - dupPWeb;
                const netC = webCommission - dupCWeb;

                const pvswVol = (isFinite(wicVol) && netVol !== '' && !isNaN(Number(netVol))) ? (wicVol - Number(netVol)) : '';
                const pvswP = wicPrincipal - netP;
                const pvswC = wicCommission - netC;

                // populate columns
                set('wic-vol', wicVol || '');
                set('wic-principal', wicPrincipal || 0);

                set('webkpi-vol', webVol || '');
                set('webkpi-principal', webPrincipal || 0);
                set('webkpi-commission', webCommission || 0);

                // NET WEB REPORT: show the PARTNER VS. WEB Vol and Principal (per requested layout)
                set('netweb-vol', (pvswVol !== '' && !isNaN(Number(pvswVol))) ? Number(pvswVol) : '');
                set('netweb-principal', pvswP || 0);
                // keep showing the WEB commission in NET WEB REPORT
                set('netweb-commission', webCommission || 0);

                // PARTNER VS. WEB: clear Vol and Principal (moved to NET WEB REPORT), keep partner commission as negative web commission
                set('pvsw-vol', '');
                set('pvsw-principal', '');
                set('pvsw-commission', (webCommission ? -Number(webCommission) : 0));
            });

            try{ computeTotals(); }catch(e){}
            try{ populateSummary(); }catch(e){}
        }catch(err){ console.error('populateWicUsdModal failed', err); }
    };

    // Helper: read selected month/year from the page
    function getSelectedMonthYear(){
        const mEl = document.getElementById('hsMonth');
        const yEl = document.getElementById('hsYear');
        const now = new Date();
        const month = mEl ? mEl.value : String(now.getMonth()+1);
        const year = yEl ? yEl.value : String(now.getFullYear());
        return { month: String(month), year: String(year) };
    }

    // Fetch recon API and populate only rows that are denominated in USD.
    async function fetchUsdFromApi(){
        try{
            const sel = getSelectedMonthYear();
            const baseUrl = location.origin + '/autorecon/src/controllers/recon/wic-recon.php';
            const partnerEl = document.getElementById('hsCompany');
            const selectedPartner = partnerEl && partnerEl.value ? String(partnerEl.value) : 'WORLDCOM INTERNATIONAL COMMUNICATIONS';
            // initial fetch to obtain days and daysInMonth
            const listRes = await fetch(baseUrl + '?month='+encodeURIComponent(sel.month)+'&year='+encodeURIComponent(sel.year)+'&partnerName='+encodeURIComponent(selectedPartner), {cache:'no-store'});
            if(!listRes.ok) throw new Error('Network response was not ok');
            const listJson = await listRes.json();
            if(!listJson || !listJson.success || !Array.isArray(listJson.days)) return;

            const aggregated = [];

            // For each day, request detail (detail=1&day=N) so we can inspect row-level `coin` fields.
            for(const d of listJson.days){
                try{
                    const dayNum = Number(d.day) || 0;
                    const detailUrl = baseUrl + '?month='+encodeURIComponent(sel.month)+'&year='+encodeURIComponent(sel.year)+'&detail=1&day='+encodeURIComponent(dayNum)+'&partnerName='+encodeURIComponent(selectedPartner);
                    const detailRes = await fetch(detailUrl, {cache:'no-store'});
                    if(!detailRes.ok){ aggregated.push({ day: dayNum }); continue; }
                    const detailJson = await detailRes.json();
                    if(!detailJson || !detailJson.success || !Array.isArray(detailJson.days)) { aggregated.push({ day: dayNum }); continue; }

                    const detailDay = detailJson.days.find(x=>Number(x.day)===dayNum) || {};
                    const rows = Array.isArray(detailDay.rows) ? detailDay.rows : [];

                    // filter rows that indicate USD currency
                    const usdRows = rows.filter(r => {
                        try{
                            const candidates = [r.partner_coin, r.coin, r.web_currency, r.currency, r.partner_currency];
                            for(const c of candidates){ if(c && String(c).trim().toUpperCase() === 'USD') return true; }
                        }catch(e){}
                        return false;
                    });

                    if(usdRows.length === 0){
                        // no USD rows for this day -> push empty payload (will leave row blank)
                        aggregated.push({ day: dayNum });
                        continue;
                    }

                    // aggregate USD rows into day-level sums
                    let wicVol = 0, wicPrincipal = 0, wicCommission = 0;
                    let webVol = 0, webPrincipal = 0, webCommission = 0;
                    usdRows.forEach(r => {
                        const pPrincipal = Number(r.partner_principal ?? r.partner_amount ?? r.partner_amount ?? 0) || 0;
                        const wAmt = Number(r.web_amount ?? r.web_amount ?? r.web_amount ?? 0) || 0;
                        const wCtp = Number(r.web_ctp ?? r.web_ctp ?? r.web_ctp ?? 0) || 0;
                        wicVol += 1;
                        webVol += (wAmt ? 1 : 0);
                        wicPrincipal += pPrincipal;
                        webPrincipal += wAmt;
                        webCommission += wCtp;
                    });

                    // compute NET WEB and PVSW using same logic as populateWicUsdModal
                    let dupVolWeb = 0, dupPWeb = 0, dupCWeb = 0;
                    // treat each usdRows item as unique; duplicates are not specially marked here
                    const netVol = (isFinite(webVol) ? (webVol - dupVolWeb) : '');
                    const netP = webPrincipal - dupPWeb;
                    const netC = webCommission - dupCWeb;

                    const pvswVol = (isFinite(wicVol) && netVol !== '' && !isNaN(Number(netVol))) ? (wicVol - Number(netVol)) : '';
                    const pvswP = wicPrincipal - netP;
                    const pvswC = wicCommission - netC;

                    aggregated.push({
                        day: dayNum,
                        vol: webVol,
                        total_partner_amount: wicPrincipal,
                        commission: wicCommission,
                        web_principal: webPrincipal,
                        web_commission: webCommission,
                        rows: usdRows,
                        // include computed fields so populateWicUsdModal can consume them
                        _computed: { netVol, netP, netC, pvswVol, pvswP, pvswC }
                    });

                }catch(err){ console.warn('detail fetch failed for day', d.day, err); aggregated.push({ day: Number(d.day) || 0 }); }
            }

            // Build a synthetic json matching expected shape and call population
            const out = { days: aggregated };
            // populate modal using existing helper
            try{ window.populateWicUsdModal(out); }catch(e){ console.error('populate helper failed', e); }
        }catch(err){ console.error('fetchUsdFromApi failed', err); }
    }

    // call fetchUsdFromApi when modal becomes visible
    try{
        const mo2 = new MutationObserver(function(){ if(modal.style.display && modal.style.display !== 'none'){ fetchUsdFromApi(); } });
        mo2.observe(modal, { attributes: true, attributeFilter: ['style'] });
    }catch(e){}
})();
</script>
