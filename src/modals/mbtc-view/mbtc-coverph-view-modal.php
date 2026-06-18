<?php
// mbtc-coverph-view-modal.php
// Simple frontend modal for viewing Cover PH summary (Partner / Web)
?>
<link rel="stylesheet" href="<?= htmlspecialchars((string)($appBaseUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>/src/modals/mbtc-view/mbtc-coverph-view-modal.css">
<div id="mbtcCoverPhModal" class="mbtc-coverph-modal" style="display:none;">
    <div class="mbtc-coverph-modal__box">
    <button type="button" class="mbtc-coverph-modal__export" data-action="export-coverph" aria-label="Export">Export</button>
    <button type="button" class="mbtc-coverph-modal__close" data-action="close-coverph" aria-label="Close">×</button>
        <h3>METROBANK HEAD OFFICE Cover PHP — Summary</h3>
        <div class="mbtc-coverph-modal__summary">
            <div class="summary-item">Principal (Partner): <span data-role="principal-partner">0 pesos</span></div>
            <div class="summary-item">Principal (Web): <span data-role="principal-web">0 pesos</span></div>
            <div class="summary-item">Commission (Partner): <span data-role="commission-partner">0 pesos</span></div>
            <div class="summary-item">Commission (Web): <span data-role="commission-web">0 pesos</span></div>
            <div class="summary-item">Variance: <span data-role="variance">0 pesos</span></div>
            <div class="summary-item">VAT-exclusive commission × 2%: <span data-role="ag">0 pesos</span></div>
            <div class="summary-item">Net commission after deducting the amount: <span data-role="ah">0 pesos</span></div>
        </div>

        <div class="mbtc-coverph-modal__tables">
            <h4>Cover PHP Daily Summary</h4>
            <div class="mbtc-coverph-large-table-wrap">
                <table class="mbtc-coverph-large" role="table">
                    <thead>
                        <tr>
                            <th rowspan="2">Date</th>
                            <th colspan="3">METROBANK HEAD OFFICE</th>
                            <th colspan="3">WEB KPI</th>
                            <th colspan="3">DUPLICATE TERMS</th>
                            <th colspan="3">NET WEB REPORT</th>
                            <th colspan="3">PARTNER VS. WEB</th>
                            <th colspan="3">DEPOSIT VS. WEB</th>
                            <th colspan="2">VAT</th>
                        </tr>
                        <tr>
                            <th>Vol</th><th>Principal</th><th>Commission</th>
                            <th>Vol</th><th>Principal</th><th>Commission</th>
                            <th>Vol</th><th>Principal</th><th>Commission</th>
                            <th>Vol</th><th>Principal</th><th>Commission</th>
                            <th>Vol</th><th>Principal</th><th>Commission</th>
                            <th>Debit</th><th>Credit</th><th>Variance</th><th>VAT-exclusive commission × 2%:</th><th>Net commission after deducting the amount:</th>
                        </tr>
                    </thead>
                    <tbody>
<?php for($i=1;$i<=31;$i++): ?>
                        <tr data-day="<?= $i ?>">
                            <td class="col-date"><?= $i ?></td>
                            <td class="mbtc-vol"></td>
                            <td class="mbtc-principal"></td>
                            <td class="mbtc-commission"></td>

                            <td class="webkpi-vol"></td>
                            <td class="webkpi-principal"></td>
                            <td class="webkpi-commission"></td>

                            <td class="dup-vol"></td>
                            <td class="dup-principal"></td>
                            <td class="dup-commission"></td>

                            <td class="netweb-vol"></td>
                            <td class="netweb-principal"></td>
                            <td class="netweb-commission"></td>

                            <td class="pvsw-vol"></td>
                            <td class="pvsw-principal"></td>
                            <td class="pvsw-commission"></td>

                            <td class="deposit-debit"></td>
                            <td class="deposit-credit"></td>
                            <td class="deposit-variance"></td>
                            <td class="deposit-ag"></td>
                            <td class="deposit-ah"></td>
                        </tr>
<?php endfor; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>TOTAL</th>
                            <th colspan="3" data-role="total-mbtc">0 / 0 / 0</th>
                            <th colspan="3" data-role="total-webkpi">0 / 0 / 0</th>
                            <th colspan="3" data-role="total-dup">0 / 0 / 0</th>
                            <th colspan="3" data-role="total-netweb">0 / 0 / 0</th>
                            <th colspan="3" data-role="total-pvsw">0 / 0 / 0</th>
                            <th colspan="3" data-role="total-deposit">0 / 0 / 0</th>
                            <th data-role="total-ag">0</th>
                            <th data-role="total-ah">0</th>
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
    const modal = document.getElementById('mbtcCoverPhModal');
    if(!modal) return;
    const close = modal.querySelector('[data-action="close-coverph"]');
    if(close) close.addEventListener('click', function(){ modal.style.display='none'; try{ document.body.style.overflow=''; }catch(e){} });
    const exportBtn = modal.querySelector('[data-action="export-coverph"]');
    if(exportBtn) exportBtn.addEventListener('click', async function(){
        const table = modal.querySelector('.mbtc-coverph-large');
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
            // build header rows (two rows to match UI grouping)
            const top = [
                'Date','METROBANK HEAD OFFICE','','','WEB KPI','','','DUPLICATE TERMS','','','NET WEB REPORT','','','PARTNER VS. WEB','','','DEPOSIT VS. WEB','','','VAT',''
            ];
            const sub = ['Date','Vol','Principal','Commission','Vol','Principal','Commission','Vol','Principal','Commission','Vol','Principal','Commission','Vol','Principal','Commission','Debit','Credit','Variance','VAT-exclusive commission × 2%','Net commission after deducting the amount'];

            const aoa = [];
            aoa.push(top);
            aoa.push(sub);

            // body rows
            const bodyRows = Array.from(table.querySelectorAll('tbody tr'));
            bodyRows.forEach(tr => {
                const cols = Array.from(tr.querySelectorAll('td')).map(td => td.textContent.trim());
                // ensure length 21
                while(cols.length < 21) cols.push('');
                aoa.push(cols);
            });

            // footer
            const tfoot = table.querySelector('tfoot tr');
            if(tfoot){
                const footerCells = [];
                // iterate through expected 21 output columns; some footer cells are th with colspan
                const children = Array.from(tfoot.querySelectorAll('th,td'));
                // flatten considering colspan; if a colspan cell contains " / " separated totals, split into parts
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
                while(footerCells.length < 21) footerCells.push('');
                aoa.push(footerCells.slice(0,21));
            }

            const ws = XLSX.utils.aoa_to_sheet(aoa);

            // apply merges for the top header grouping (row 0)
            ws['!merges'] = [
                {s:{r:0,c:1}, e:{r:0,c:3}}, // METROBANK HEAD OFFICE
                {s:{r:0,c:4}, e:{r:0,c:6}}, // WEB KPI
                {s:{r:0,c:7}, e:{r:0,c:9}}, // DUPLICATE TERMS
                {s:{r:0,c:10}, e:{r:0,c:12}}, // NET WEB REPORT
                {s:{r:0,c:13}, e:{r:0,c:15}}, // PARTNER VS. WEB
                {s:{r:0,c:16}, e:{r:0,c:18}}, // DEPOSIT VS. WEB
                {s:{r:0,c:19}, e:{r:0,c:20}} // VAT
            ];

            // style headers: bold, centered, gray fill
            const hdrFill = { fgColor: { rgb: 'F3F4F6' } };
            const headerStyle = { font: { bold: true }, alignment: { horizontal: 'center', vertical: 'center' }, fill: hdrFill };
            // top row (r=0) and sub row (r=1)
            for(let C=0; C<=20; C++){
                const topAddr = XLSX.utils.encode_cell({r:0,c:C}); if(ws[topAddr]) ws[topAddr].s = Object.assign({}, ws[topAddr].s || {}, headerStyle);
                const subAddr = XLSX.utils.encode_cell({r:1,c:C}); if(ws[subAddr]) ws[subAddr].s = Object.assign({}, ws[subAddr].s || {}, headerStyle);
            }

            // detect numeric cells and convert types for body and footer rows
            const totalRows = aoa.length;
            for(let R=2; R<totalRows; R++){
                for(let C=0; C<=20; C++){
                    const addr = XLSX.utils.encode_cell({r:R,c:C});
                    const cell = ws[addr];
                    if(!cell || cell.v == null) continue;
                    const text = String(cell.v).trim();
                    const n = parseNum(text);
                    if(n !== null){ cell.v = n; cell.t = 'n'; cell.z = '#,##0.00'; }
                }
            }

            // set column widths (approx in characters)
            ws['!cols'] = [
                {wch:6}, // Date
                {wch:8},{wch:14},{wch:12}, // MBTC
                {wch:8},{wch:14},{wch:12}, // WEB KPI
                {wch:8},{wch:14},{wch:12}, // DUP
                {wch:8},{wch:14},{wch:12}, // NET WEB
                {wch:8},{wch:14},{wch:12}, // PVS W
                {wch:10},{wch:12},{wch:12}, // Deposit
                {wch:14},{wch:14} // VAT
            ];

            // append and write
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'CoverPH');
            // Name file using selected month/year if available: 'METROBANK HEAD OFFICE Cover PHP — Summary - <MonthName> <Year>.xlsx'
            (function writeFileWithMonthLabel(){
                const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                const mEl = document.getElementById('hsMonth');
                const yEl = document.getElementById('hsYear');
                const now = new Date();
                const mVal = mEl ? mEl.value : String(now.getMonth()+1);
                const yVal = yEl ? yEl.value : String(now.getFullYear());
                const mm = parseInt(mVal,10);
                const monthLabel = (!Number.isNaN(mm) && mm >=1 && mm <=12) ? (monthNames[mm-1] + ' ' + yVal) : ((mVal || yVal) ? (mVal + ' ' + yVal) : now.toISOString().slice(0,10));
                const fname = 'METROBANK HEAD OFFICE Cover PHP — Summary - ' + monthLabel + '.xlsx';
                XLSX.writeFile(wb, fname);
            })();
        }catch(err){ console.error('Export failed', err); try{ alert('Export failed: ' + err.message); }catch(e){} }
    });
    // close on backdrop click
    modal.addEventListener('click', function(ev){ if(ev.target === modal){ modal.style.display='none'; try{ document.body.style.overflow=''; }catch(e){} } });
    // close on Escape
    document.addEventListener('keydown', function keyHandler(ev){ if(ev.key === 'Escape'){ if(modal.style.display === 'block'){ modal.style.display='none'; try{ document.body.style.overflow=''; }catch(e){} } } });

    // allow wheel inside modal to scroll tablesContainer when pointer is over it (native scrolling)
    const tablesContainer = modal.querySelector('.mbtc-coverph-modal__tables');
    if(tablesContainer){ tablesContainer.addEventListener('wheel', function(e){ /* native */ }, { passive: true }); }

    // compute AG/AH from Commission when commission text changes or modal opens
    (function computeAgAh(){
        // prefer partner commission if present (separate partner/web display)
        const commissionPartnerEl = modal.querySelector('[data-role="commission-partner"]');
        const commissionWebEl = modal.querySelector('[data-role="commission-web"]');
        const agEl = modal.querySelector('[data-role="ag"]');
        const ahEl = modal.querySelector('[data-role="ah"]');
        const commissionEl = commissionPartnerEl || commissionWebEl;
        if(!commissionEl || (!agEl && !ahEl)) return;

        function parseFirstNumber(text){
            if(!text) return 0;
            const m = text.match(/-?[\d,]+(?:\.\d+)?/);
            if(!m) return 0;
            const n = m[0].replace(/,/g,'');
            const v = Number(n);
            return Number.isFinite(v) ? v : 0;
        }

        function update(){
            const txt = commissionEl.textContent || '';
            const d = parseFirstNumber(txt);
            // AG = D / 1.12 * 0.02
            const ag = d ? (d / 1.12 * 0.02) : 0;
            const ah = d - ag;
            if(agEl) agEl.textContent = ag.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:3}) + ' pesos';
            if(ahEl) ahEl.textContent = ah.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:3}) + ' pesos';
        }

        // initial compute
        update();

        // observe changes to commission element(s)
        try{
            const mo = new MutationObserver(function(){ update(); });
            if(commissionPartnerEl) mo.observe(commissionPartnerEl, { childList: true, subtree: true, characterData: true });
            if(commissionWebEl) mo.observe(commissionWebEl, { childList: true, subtree: true, characterData: true });
            // also recompute when modal is shown via style change
            const obsModal = new MutationObserver(function(){ if(modal.style.display && modal.style.display !== 'none') update(); });
            obsModal.observe(modal, { attributes: true, attributeFilter: ['style'] });
        }catch(e){ /* ignore in old browsers */ }
    })();
})();
</script>
