<?php
// UI-only Partner Data Upload Section (loads partner list from DB)
require_once __DIR__ . '/../../../config/db.php';

$partners = [];
try {
    // Use master data connection and corpo_partner_masterfile for the canonical partner list
    $pdo = masterDataConnection();
    $stmt = $pdo->query("SELECT DISTINCT partner_name FROM corpo_partner_masterfile WHERE partner_name IS NOT NULL AND partner_name <> '' ORDER BY partner_name ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (is_array($rows) && count($rows) > 0) {
        $partners = $rows;
    }
} catch (Throwable $e) {
    // If DB access fails, fall back to an empty partners list (UI will still render)
    $partners = [];
}
?>
<section id="partnerdataSection" class="partnerdata-section" aria-label="Partner Data Uploader">
    <div class="partnerdata-inner">
        <h2 class="partnerdata-title">Partner Data Uploader</h2>

        <div class="partnerdata-filters">
            <div class="filters-left">
                <label class="pd-filter"><span>Corporate Partner</span>
                    <div class="autocomplete-field">
                        <input id="pdCompany" placeholder="Select corporate partner" autocomplete="off" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:60ch;width:min(100%,72ch);box-sizing:border-box;">
                        <ul class="autocomplete-list" id="pdCompanySuggestions" role="listbox" hidden></ul>
                        <datalist id="pdCompanyList">
                            <?php if (empty($partners)): ?>
                                <option value=""></option>
                            <?php else: ?>
                                <?php foreach ($partners as $p): ?>
                                    <option value="<?= htmlspecialchars((string)$p, ENT_QUOTES, 'UTF-8') ?>"></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </datalist>
                    </div>
                </label>
                <!-- Month and Year removed: detected automatically from Excel `Date` column -->
            </div>
            <div class="filters-actions">
                <button id="pdUpload" class="material-btn material-btn--primary" disabled>Upload</button>
            </div>
        </div>

        <div class="pd-dropwrap">
            <div id="pdDropzone" class="pd-dropzone pd-dropzone--disabled" tabindex="0">
                <div class="pd-drop-inner">
                    <span class="material-icons" aria-hidden="true">cloud_upload</span>
                    <p class="pd-drop-text">Drag and drop files here<br>or<br>Click to browse files</p>
                    <p class="pd-drop-hint">Supports multiple files</p>
                </div>
                <input id="pdFiles" type="file" multiple accept=".xls,.xlsx,.xlsm,.xlsb,.ods,.csv,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel.sheet.macroEnabled,application/vnd.ms-excel.sheet.binary.macroEnabled,application/vnd.oasis.opendocument.spreadsheet" style="display:none" />
            </div>

            <div class="pd-filelist" id="pdFileList" aria-live="polite" style="display:none">
                <div class="pd-empty">No files selected</div>
            </div>
        </div>

        <div id="pdCards" class="pd-cards" aria-live="polite" style="margin-top:1rem"></div>

        <!-- Processing overlay -->
        <?php
            $modalPrefix = 'pd';
            include __DIR__ . '/../../../modals/data-modals/fetch-modal.php';
            include __DIR__ . '/../../../modals/data-modals/check-insert-modal.php';
        ?>
    </div>

    <script>
    (function(){
        const company = document.getElementById('pdCompany');
        const partners = <?= json_encode($partners, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
        const MBTC_PARTNER_NAME = 'METROBANK HEAD OFFICE';
        const WIC_PARTNER_NAME = 'WORLDCOM INTERNATIONAL COMMUNICATIONS';
        const RCBC_PARTNER_NAME = 'RIZAL COMMERCIAL BANKING CORPORATION';
        const SKYBRIDGE_PARTNER_NAME = 'SKYBRIDGE PAYMENT INC.';
        const MONEYGRAM_PARTNER_NAME = 'MONEYGRAM';

        function attachPartnerAutocomplete(input, suggestions){
            const container = input ? input.closest('.autocomplete-field') : null;
            const list = container ? container.querySelector('.autocomplete-list') : null;
            if(!input || !container || !list) return;

            let activeIndex = -1;

            function normalize(value){
                return String(value || '').trim().toLowerCase();
            }

            function getMatches(value){
                const query = normalize(value);
                const options = Array.from(new Set((suggestions || []).map(item => String(item || '').trim()).filter(Boolean)));
                if(!query) return options.slice(0, 8);

                const startsWith = [];
                const contains = [];
                options.forEach(option => {
                    const normalizedOption = normalize(option);
                    if(normalizedOption.startsWith(query)) startsWith.push(option);
                    else if(normalizedOption.includes(query)) contains.push(option);
                });

                return startsWith.concat(contains).slice(0, 8);
            }

            function closeSuggestions(){
                list.hidden = true;
                list.innerHTML = '';
                activeIndex = -1;
            }

            function applyActiveItem(items){
                items.forEach((item, index) => item.classList.toggle('is-active', index === activeIndex));
            }

            function selectSuggestion(value){
                input.value = value;
                closeSuggestions();
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }

            function renderSuggestions(){
                const matches = getMatches(input.value);
                if(matches.length === 0){
                    closeSuggestions();
                    return;
                }

                list.innerHTML = '';
                matches.forEach((match, index) => {
                    const item = document.createElement('li');
                    item.className = 'autocomplete-item';
                    item.setAttribute('role', 'option');
                    item.textContent = match;
                    item.addEventListener('mousedown', function(event){
                        event.preventDefault();
                        selectSuggestion(match);
                    });
                    item.addEventListener('mouseenter', function(){
                        activeIndex = index;
                        applyActiveItem(Array.from(list.children));
                    });
                    list.appendChild(item);
                });
                activeIndex = -1;
                list.hidden = false;
            }

            input.addEventListener('input', renderSuggestions);
            input.addEventListener('focus', renderSuggestions);
            input.addEventListener('keydown', function(event){
                const items = Array.from(list.querySelectorAll('.autocomplete-item'));
                if(list.hidden || items.length === 0) return;

                if(event.key === 'ArrowDown'){
                    event.preventDefault();
                    activeIndex = (activeIndex + 1) % items.length;
                    applyActiveItem(items);
                } else if(event.key === 'ArrowUp'){
                    event.preventDefault();
                    activeIndex = activeIndex <= 0 ? items.length - 1 : activeIndex - 1;
                    applyActiveItem(items);
                } else if(event.key === 'Enter'){
                    if(activeIndex >= 0 && activeIndex < items.length){
                        event.preventDefault();
                        selectSuggestion(items[activeIndex].textContent || '');
                    }
                } else if(event.key === 'Escape'){
                    closeSuggestions();
                }
            });

            document.addEventListener('click', function(event){
                if(!container.contains(event.target)) closeSuggestions();
            });
        }

        function isMetrobankHeadOffice(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'MBTC' || normalized === MBTC_PARTNER_NAME;
        }

        function isWorldcomInternationalCommunications(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'WIC' || normalized === WIC_PARTNER_NAME;
        }

        function isRcbc(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'RCBC' || normalized === RCBC_PARTNER_NAME;
        }

        function isSkybridgePaymentInc(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'SKYBRIDGE' || normalized === SKYBRIDGE_PARTNER_NAME;
        }

        function isMoneygram(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'MONEYGRAM' || normalized === MONEYGRAM_PARTNER_NAME;
        }

        const UPLOADER_TEMPLATE_SIGNATURES = {
            moneygram: {
                partnerFilenameTokens: ['SETTLEMENTDETAIL', 'SETTLEMENT DETAIL', 'SETTLEMENT DETAILS'],
                webFilenameTokens: ['SENDOUT', 'SEND OUT', 'TRANSACTION-REPORT-ALL', 'PAYOUT', 'PAY OUT', 'CLAIMED'],
                partnerHeaders: ['ACCOUNT NUMBER','AGENT NAME','LEGACY ID','TRAN DATE','TRANSACTION ID','REFERENCE ID','PRODUCT','TRAN TYPE','ORIG CNTRY','RCV CNTRY'],
                webHeaders: ['NO','CONTROL SERIES NO','DATE CLAIMED','DATE SEND','KPTN','CCREF NO','CURRENCY','AMOUNT']
            }
        };

        function normalizeHeaderLabel(value){
            return String(value || '').trim().toUpperCase().replace(/[_\-\/]+/g, ' ').replace(/\s+/g, ' ');
        }

        function includesAnyToken(text, tokens){
            if(!text || !Array.isArray(tokens)) return false;
            const up = String(text).toUpperCase();
            return tokens.some(token => up.includes(String(token || '').toUpperCase()));
        }

        function headerMatchCount(keys, signatures){
            if(!Array.isArray(keys) || !Array.isArray(signatures)) return 0;
            let count = 0;
            for(const sig of signatures){
                const normalizedSig = normalizeHeaderLabel(sig);
                if(keys.some(k => normalizeHeaderLabel(k).indexOf(normalizedSig) !== -1)) count++;
            }
            return count;
        }

        function classifyMoneygramFileType(payload, fileNameHint){
            const signatures = UPLOADER_TEMPLATE_SIGNATURES.moneygram;
            const filename = String(fileNameHint || (payload && payload.filename) || '').toUpperCase();

            const hasPartnerFileToken = includesAnyToken(filename, signatures.partnerFilenameTokens);
            const hasWebFileToken = includesAnyToken(filename, signatures.webFilenameTokens);

            if(hasPartnerFileToken && !hasWebFileToken) return 'partner';
            if(hasWebFileToken && !hasPartnerFileToken) return 'web';

            const rows = payload && Array.isArray(payload.rows) ? payload.rows : [];
            const sample = rows.find(row => row && typeof row === 'object');
            if(!sample) return 'unknown';

            const keys = Object.keys(sample || {}).map(k => normalizeHeaderLabel(k));
            const partnerMatches = headerMatchCount(keys, signatures.partnerHeaders);
            const webMatches = headerMatchCount(keys, signatures.webHeaders);

            if(partnerMatches >= 4 && partnerMatches > webMatches) return 'partner';
            if(webMatches >= 4 && webMatches >= partnerMatches) return 'web';
            return 'unknown';
        }

        function classifyForPartnerUploader(payload, fileNameHint){
            if(!isMoneygram(company.value)) return { accepted: true, type: 'other' };
            const type = classifyMoneygramFileType(payload, fileNameHint);
            if(type === 'web') return { accepted: false, type, message: 'Invalid File Format' };
            return { accepted: true, type };
        }

        function resolveCompanyKey(name){
            if(isMetrobankHeadOffice(name)) return 'mbtc';
            if(isWorldcomInternationalCommunications(name)) return 'wic';
            return String(name || '').trim().toLowerCase().replace(/[^a-z0-9]/g,'');
        }

        function normalizePartnerHeaderKey(value){
            return String(value || '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
        }

        function getRowValueByAliases(row, aliases, fallback = ''){
            if(!row || typeof row !== 'object') return fallback;

            for(const alias of aliases){
                if(Object.prototype.hasOwnProperty.call(row, alias) && row[alias] !== null && row[alias] !== '') return row[alias];
            }

            const normalizedRow = Object.create(null);
            Object.keys(row).forEach(function(key){
                const value = row[key];
                if(value === null || value === '') return;
                const normalizedKey = normalizePartnerHeaderKey(key);
                if(normalizedKey && !Object.prototype.hasOwnProperty.call(normalizedRow, normalizedKey)){
                    normalizedRow[normalizedKey] = value;
                }
            });

            for(const alias of aliases){
                const normalizedAlias = normalizePartnerHeaderKey(alias);
                if(normalizedAlias && Object.prototype.hasOwnProperty.call(normalizedRow, normalizedAlias)) return normalizedRow[normalizedAlias];
            }

            return fallback;
        }

        function getSkybridgeReference(row){
            return String(getRowValueByAliases(row, ['Reference No.', 'Reference No', 'reference_no', 'reference no', 'reference', 'ref_no', 'transaction_id', 'transaction id', 'control_series', 'control series', 'control_series_no'], '') || '').trim();
        }

        function getSkybridgeDate(row, payload){
            const fallback = payload && payload.dateStr ? payload.dateStr : '';
            return getRowValueByAliases(row, ['Date', 'date', 'transaction_date', 'transaction date', 'date_claimed', 'date claimed', 'cover_date', 'cover date', 'payout_date', 'payout date'], fallback);
        }

        function getMoneygramTransactionId(row){
            return String(getRowValueByAliases(row, ['transaction_id', 'transaction id', 'tran_id', 'tran id', 'reference_id', 'reference id', 'reference_no', 'reference no'], '') || '').trim();
        }

        function getMoneygramTranDate(row, payload){
            const fallback = payload && payload.dateStr ? payload.dateStr : '';
            return getRowValueByAliases(row, ['tran_date', 'tran date', 'transaction_date', 'transaction date', 'date', 'fx_date_trn', 'fx date trn'], fallback);
        }
        // partner selection uses a datalist (`pdCompany` input)
        const dropzone = document.getElementById('pdDropzone');
        if(dropzone) dropzone.style.cursor = 'pointer';
        const fileInput = document.getElementById('pdFiles');
        const fileListEl = document.getElementById('pdFileList');
        const uploadBtn = document.getElementById('pdUpload');
        const cardsEl = document.getElementById('pdCards');
        const overlayEl = document.getElementById('pdOverlay');
        const progressBar = document.getElementById('pdProgressBar');
        const progressText = document.getElementById('pdProgressText');
        const cancelBtn = document.getElementById('pdCancelBtn');
        let files = [];
        const processedByCompany = Object.create(null);
        const passwordByCompany = Object.create(null);

        function getCompanyKey(){
            return ((company && company.value) ? company.value : '').trim().toUpperCase();
        }

        function getProcessedList(key){
            const resolved = (typeof key === 'string' ? key : getCompanyKey());
            if(!resolved) return [];
            if(!processedByCompany[resolved]) processedByCompany[resolved] = [];
            return processedByCompany[resolved];
        }

        function getCachedWorkbookPassword(){
            const key = getCompanyKey();
            return key && passwordByCompany[key] ? passwordByCompany[key] : '';
        }

        function setCachedWorkbookPassword(password){
            const key = getCompanyKey();
            if(!key) return;
            if(password){
                passwordByCompany[key] = password;
            } else {
                delete passwordByCompany[key];
            }
        }

        function addUniqueProcessed(payloads, key){
            const list = getProcessedList(key);
            (payloads || []).forEach(p=>{
                if(typeof p._uploaded === 'undefined') p._uploaded = false;
                const exists = list.find(x=>x.filename === p.filename && x.dateStr === p.dateStr);
                if(!exists) list.push(p);
                else if(p._uploaded) exists._uploaded = true;
            });
        }

        function markPayloadsUploaded(payloads, uploaded, key){
            const list = getProcessedList(key);
            (payloads || []).forEach(p=>{
                const exists = list.find(x=>x.filename === p.filename && x.dateStr === p.dateStr);
                if(exists) exists._uploaded = !!uploaded;
                p._uploaded = !!uploaded;
            });
        }

        // small styles for icon buttons and cards
        (function(){
            const css = `
            .icon-btn{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:6px;background:transparent;border:1px solid transparent;cursor:pointer;padding:0;margin:0}
            .icon-btn:hover{background:#f5f5f5;border-color:#e6e6e6;transform:translateY(-1px);transition:all .12s ease}
            .upload-status{display:inline-flex;align-items:center;gap:4px;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:600;line-height:1;border:1px solid #d9dfe8;color:#4b5563;background:#f8fafc}
            .upload-status .material-icons{font-size:14px;line-height:14px}
            .upload-status.is-uploaded{color:#166534;background:#ecfdf5;border-color:#bbf7d0}
            .pd-card{transition:all .12s ease;border-radius:8px}
            .pd-password-modal{position:fixed;inset:0;background:rgba(15,23,42,0.55);display:flex;align-items:center;justify-content:center;z-index:12000;padding:1rem}
            .pd-password-modal[hidden]{display:none}
            .pd-password-modal__card{width:min(100%,420px);background:#fff;border-radius:12px;padding:1rem 1rem 0.875rem;box-shadow:0 18px 50px rgba(15,23,42,0.28)}
            .pd-password-modal__title{margin:0 0 0.35rem;font-size:1rem;font-weight:600;color:#111827}
            .pd-password-modal__text{margin:0 0 0.85rem;color:#4b5563;font-size:0.92rem;line-height:1.45}
            .pd-password-modal__input{width:100%;box-sizing:border-box;padding:0.7rem 0.8rem;border:1px solid #d1d5db;border-radius:8px;font-size:0.95rem;outline:none}
            .pd-password-modal__input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,0.12)}
            .pd-password-modal__actions{display:flex;justify-content:flex-end;gap:0.65rem;margin-top:0.9rem}
            `;
            const style = document.createElement('style');
            style.type = 'text/css';
            style.appendChild(document.createTextNode(css));
            document.head.appendChild(style);
        })();

        function refreshState(){
            // enable when any company is selected
            const ready = !!company.value;
            const currentProcessed = getProcessedList();
            if(ready){ dropzone.classList.remove('pd-dropzone--disabled'); }
            else { dropzone.classList.add('pd-dropzone--disabled'); }
            uploadBtn.disabled = !(ready && (files.length>0 || currentProcessed.length>0));
            renderFileList();
        }

        function renderFileList(){
            fileListEl.innerHTML = '';
            if(files && files.length>0){
                const header = document.createElement('div'); header.className='pd-filecount'; header.textContent = files.length + ' file' + (files.length>1?'s selected':' selected'); fileListEl.appendChild(header);
                const ul = document.createElement('ul'); ul.className='pd-files-ul';
                files.forEach((f,i)=>{ const li=document.createElement('li'); li.className='pd-file-item'; li.innerHTML = '<span class="name">'+escapeHtml(f.name)+'</span> <button class="pd-remove" data-index="'+i+'" style="float:right">Remove</button>'; ul.appendChild(li); });
                fileListEl.appendChild(ul); return;
            }
            fileListEl.innerHTML = '<div class="pd-empty">No files selected</div>';
        }

        function escapeHtml(s){ return (s+'').replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

        // Format coverDate (YYYY-MM-DD) or other date-like strings to 'Month DD, YYYY'
        function formatDisplayDate(raw){
            if(!raw) return '';
            // if already like 'February 02, 2026' return as-is
            if(/^\s*[A-Za-z]+\s+\d{1,2},\s*\d{4}\s*$/.test(raw)) return raw.trim();
            // if ISO YYYY-MM-DD
            const m = raw.match(/^\s*(\d{4})-(\d{2})-(\d{2})\s*$/);
            if(m){
                const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                const y = m[1], mo = parseInt(m[2],10), d = parseInt(m[3],10);
                if(mo>=1 && mo<=12) return months[mo-1] + ' ' + String(d).padStart(2,'0') + ', ' + y;
            }
            // fallback to Date parsing (may vary by environment)
            const dt = new Date(raw);
            if(!isNaN(dt.getTime())){
                const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                return months[dt.getMonth()] + ' ' + String(dt.getDate()).padStart(2,'0') + ', ' + dt.getFullYear();
            }
            return String(raw);
        }

        // Company-specific date field mapping. Add more companies as needed.
        const companyDateMap = {
            'MBTC': { field: 'coverDate', type: 'date' },
            'METROBANK HEAD OFFICE': { field: 'coverDate', type: 'date' },
            'DEFAULT': { field: 'dateStr', type: 'date' }
        };

        // Return the raw date string to use for display/sorting based on selected company
        function getPayloadDateRaw(p){
            const companyKey = (company && company.value) ? company.value : '';
            const cfg = companyDateMap[companyKey] || companyDateMap['DEFAULT'];
            let val = '';
            if(cfg && cfg.field && p[cfg.field]) val = p[cfg.field];
            // fallbacks
            if(!val && p.dateStr) val = p.dateStr;
            if(!val && p.rows && p.rows.length>0){
                val = p.rows[0]['Date'] || p.rows[0]['DATE'] || p.rows[0]['date'] || '';
            }
            return (val === null || val === undefined) ? '' : String(val);
        }

        // Produce a numeric timestamp for sorting (milliseconds since epoch)
        function getPayloadTimestamp(p){
            const raw = getPayloadDateRaw(p);
            if(!raw) return 0;
            // Try normalizeClientDate which returns 'YYYY-MM-DD HH:MM:SS' where possible
            try{
                const norm = normalizeClientDate(raw);
                if(norm){
                    const t = Date.parse(norm);
                    if(!isNaN(t)) return t;
                }
            }catch(e){}
            // Try direct Date parse
            const t2 = Date.parse(raw);
            if(!isNaN(t2)) return t2;
            return 0;
        }

        // click to open file picker (always allow if MBTC selected)
        dropzone.addEventListener('click', function(){ fileInput.click(); });
        dropzone.addEventListener('keydown', function(e){ if(e.key==='Enter') fileInput.click(); });

        // dedupe staged files by filename (case-insensitive)
        function dedupeFilesByName(arr){
            const map = new Map();
            arr.forEach(f=>{
                const key = (f && f.name ? f.name.toLowerCase() : '');
                if(!map.has(key)) map.set(key, f);
            });
            return Array.from(map.values());
        }

        fileInput.addEventListener('change', function(e){ if(e.target.files && e.target.files.length){ if(!company.value){ showSelectCompanyModal(); e.target.value=''; return; } files = dedupeFilesByName(files.concat(Array.from(e.target.files))); refreshState(); const excelFiles = files.filter(isExcelFile); if(excelFiles.length) processPartnerFiles(excelFiles); } });

        ['dragenter','dragover'].forEach(ev=>{ dropzone.addEventListener(ev, function(e){ e.preventDefault(); e.stopPropagation(); if(!dropzone.classList.contains('pd-dropzone--disabled')) dropzone.classList.add('pd-dropzone--over'); }); });
        ['dragleave','drop'].forEach(ev=>{ dropzone.addEventListener(ev, function(e){ e.preventDefault(); e.stopPropagation(); dropzone.classList.remove('pd-dropzone--over'); }); });
        dropzone.addEventListener('drop', function(e){ const dt = e.dataTransfer; if(!company.value){ showSelectCompanyModal(); return; } if(dt && dt.files && dt.files.length){ files = dedupeFilesByName(files.concat(Array.from(dt.files))); refreshState(); const excelFiles = files.filter(isExcelFile); if(excelFiles.length) processPartnerFiles(excelFiles); } });

        fileListEl.addEventListener('click', function(e){ const viewBtn = e.target.closest && e.target.closest('.pd-view'); if(viewBtn){ const idx = parseInt(viewBtn.dataset.index,10); if(!Number.isNaN(idx)){ const payload = processed[idx]; if(payload) openViewer(payload); } return; } const removeBtn = e.target.closest && e.target.closest('.pd-remove'); if(removeBtn){ const idx = parseInt(removeBtn.dataset.index,10); if(!Number.isNaN(idx)){ files.splice(idx,1); fileInput.value=''; refreshState(); } return; } });

        company.addEventListener('change', function(){
            files = [];
            fileInput.value = '';
            refreshState();
            updateCards();
        });

        function normalizeLockDate(raw){
            const normalized = normalizeClientDate(raw);
            if(!normalized) return '';
            return normalized.split(' ')[0] || '';
        }

        function collectPayloadDatesForLockCheck(payloads){
            const uniqueDates = new Set();
            (payloads || []).forEach(pl => {
                (pl && pl.rows ? pl.rows : []).forEach(r => {
                    const rawDate = (
                        r['tran_date'] || r['Tran Date'] || r['Transaction Date'] ||
                        r['date'] || r['Date'] || r['DATE'] ||
                        r['fx_date_trn'] || r['FX Date'] ||
                        pl.dateStr || ''
                    );
                    const dateOnly = normalizeLockDate(rawDate);
                    if(dateOnly) uniqueDates.add(dateOnly);
                });
                const fallbackDate = normalizeLockDate(pl && pl.dateStr ? pl.dateStr : '');
                if(fallbackDate) uniqueDates.add(fallbackDate);
            });
            return Array.from(uniqueDates);
        }

        async function enforceLockedReconciliationDateCheck(partnerName, payloads){
            const dates = collectPayloadDatesForLockCheck(payloads);
            if(!partnerName || !dates.length) return { blocked: false };

            const endpoint = location.origin + '/autorecon/src/controllers/recon/check_locked_reconciliation_dates.php';
            const resRaw = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ partner: partnerName, dates: dates })
            });
            const text = await resRaw.text();
            let json = null;
            try { json = JSON.parse(text); } catch (e) { json = null; }

            if(!resRaw.ok || !(json && json.success)){
                return {
                    blocked: false,
                    error: (json && json.error) ? json.error : 'Failed to validate locked reconciliation dates.'
                };
            }

            return {
                blocked: !!json.blocked,
                lockedDates: Array.isArray(json.locked_dates) ? json.locked_dates : []
            };
        }

        // upload click triggers fetch of extracted payloads (per-file extraction) and insertion when MBTC selected
        uploadBtn.addEventListener('click', async function(){
            const currentProcessed = getProcessedList();
            console.log('[pd] upload clicked', {disabled: uploadBtn.disabled, company: company && company.value});
            if(uploadBtn.disabled) return;
            if(!company.value){ await showSelectCompanyModal(); return; }
            let excelFiles = files.filter(isExcelFile);
            if(excelFiles.length === 0 && currentProcessed.length === 0){ await showAlert('No Excel files to upload or processed.'); return; }

            // If there are staged Excel files, extract them first
            if(excelFiles.length > 0){
                showProcessingOverlay(excelFiles.length);
                let idx = 0;
                for(const f of excelFiles){
                    idx++;
                    progressText.textContent = 'Extracting file ' + idx + ' of ' + excelFiles.length + '...';
                    updateProcessing(idx-1, excelFiles.length);
                    try{
                        const precheck = classifyForPartnerUploader(null, f.name);
                        if(!precheck.accepted){
                            await showAlert(precheck.message || 'Invalid File Format', 'Notice');
                            updateProcessing(idx, excelFiles.length);
                            continue;
                        }
                        const res = await uploadToPartnerWithRetry(f);
                        if(res && res.success){
                            const classification = classifyForPartnerUploader(res.payload, f.name);
                            if(!classification.accepted){
                                await showAlert(classification.message || 'Invalid File Format', 'Notice');
                            } else {
                                addUniqueProcessed([res.payload]);
                                updateCards();
                            }
                        }
                        else {
                            console.warn('Extraction failed for', f.name, res);
                            if(res && res.errorCode !== 'password_prompt_cancelled') await showAlert(res.error || ('Processing failed for ' + f.name));
                        }
                    }catch(err){ console.error('Extract error', err); }
                    updateProcessing(idx, excelFiles.length);
                }
                hideProcessingOverlay();
            }

            const payloadsForLockCheck = getProcessedList().slice();
            if(payloadsForLockCheck.length > 0){
                try {
                    const lockCheck = await enforceLockedReconciliationDateCheck(company.value, payloadsForLockCheck);
                    if(lockCheck && lockCheck.error){
                        await showAlert(lockCheck.error, 'Notice');
                        return;
                    }
                    if(lockCheck && lockCheck.blocked){
                        await showAlert('Upload Blocked. Some transaction dates are already locked by reconciliation.', 'Notice');
                        return;
                    }
                } catch (e) {
                    await showAlert('Failed to validate locked reconciliation dates.', 'Notice');
                    return;
                }
            }

            // At this point `processed` contains extracted payloads. If company is MBTC, insert into DB.
            if(isMetrobankHeadOffice(company.value)){
                const payloads = getProcessedList().slice();
                console.log('[pd] payloads count', payloads.length, payloads.map(p=>p.filename));
                if(payloads.length === 0){ await showAlert('No extracted payloads to insert.'); return; }
                try{
                    // build unique pairs for duplicate check (reference_no + date)
                    const pairMap = new Map();
                    payloads.forEach(pl=>{
                        (pl.rows||[]).forEach(r=>{
                            const ref = (r['Reference No.']||'').toString().trim();
                            const rawDate = (r['Date'] || pl.dateStr || '');
                            let dateFull = normalizeClientDate(rawDate);
                            let dateOnly = '';
                            if(dateFull) dateOnly = dateFull.split(' ')[0];
                            if(ref) pairMap.set(ref + '|' + dateOnly, { reference_no: ref, date: dateOnly });
                        });
                    });
                    const pairs = Array.from(pairMap.values());

                    const url = location.origin + '/autorecon/src/controllers/excelcontrol/mbtc/mbtc-insert.php';
                    console.log('[pd] starting duplicate checks against', url);
                    // per-file duplicate check
                    const totalFiles = payloads.length || 0;
                    // Ensure the processing overlay is visible during duplicate-check and insert stages
                    try{ showProcessingOverlay(totalFiles); }catch(e){}
                    progressBar.style.width = '0%';
                    progressText.textContent = 'Checking database for duplicates: 0 of ' + totalFiles;
                    const allDuplicates = [];
                    for(let i=0;i<totalFiles;i++){
                        const pl = payloads[i];
                        console.log('[pd] checking file', i+1, pl.filename);
                        const filePairs = [];
                        (pl.rows||[]).forEach(r=>{
                            const ref = (r['Reference No.']||'').toString().trim();
                            const rawDate = (r['Date'] || pl.dateStr || '');
                            let dateFull = normalizeClientDate(rawDate);
                            let dateOnly = '';
                            if(dateFull) dateOnly = dateFull.split(' ')[0];
                            if(ref) filePairs.push({ reference_no: ref, date: dateOnly });
                        });
                        if(filePairs.length === 0){
                            const pct = Math.round(((i+1)/Math.max(1,totalFiles)) * 45);
                            progressBar.style.width = pct + '%';
                            progressText.textContent = 'Checking database for duplicates: ' + (i+1) + ' of ' + totalFiles;
                            continue;
                        }
                        const chkResRaw = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'check_partner', pairs: filePairs }) });
                        const chkTxt = await chkResRaw.text();
                        let chk;
                        try{ chk = JSON.parse(chkTxt); }catch(e){ console.error('Duplicate check returned non-JSON', chkTxt); await showAlert('Duplicate check failed: '+chkTxt); hideProcessingOverlay(); return; }
                        console.log('[pd] duplicate check response', chk);
                        if(!chkResRaw.ok || !(chk && chk.success)){ await showAlert('Duplicate check failed: ' + (chk && chk.error ? chk.error : 'unknown')); hideProcessingOverlay(); return; }
                        if(Array.isArray(chk.duplicates) && chk.duplicates.length>0){ allDuplicates.push(...chk.duplicates); }
                        const pct = Math.round(((i+1)/Math.max(1,totalFiles)) * 45);
                        progressBar.style.width = pct + '%';
                        progressText.textContent = 'Checking database for duplicates: ' + (i+1) + ' of ' + totalFiles;
                    }

                    // if duplicates found, ask user once and delete if confirmed
                    if(allDuplicates.length>0){
                        console.log('[pd] duplicates found', allDuplicates);
                        const msg = 'Data with the same Reference No. and Date already exists.\nDo you want to overwrite the existing data?';
                        // Await the custom dialog. If the dialog is not present, fallback to native confirm.
                        const dialogPresent = !!document.getElementById('pdDialog');
                        let confirmed = false;
                        try{
                            if(dialogPresent){
                                confirmed = await showConfirm(msg);
                            } else {
                                // no custom dialog available on the page, use native confirm
                                confirmed = confirm(msg);
                            }
                        }catch(e){ console.warn('[pd] showConfirm failed', e); confirmed = false; }
                        if(!confirmed){ hideProcessingOverlay(); return; }
                        const delCount = allDuplicates.length;
                        progressBar.style.width = '55%';
                        progressText.textContent = 'Deleting existing records: 0 of ' + delCount;
                        const delResRaw = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'delete_partner', pairs: allDuplicates.map(d=>({ reference_no: d.reference_no, date: d.date })) }) });
                        const delTxt = await delResRaw.text();
                        let del;
                        try{ del = JSON.parse(delTxt); }catch(e){ console.error('Delete returned non-JSON', delTxt); await showAlert('Delete failed: '+delTxt); hideProcessingOverlay(); return; }
                        console.log('[pd] delete response', del);
                        if(!delResRaw.ok || !(del && del.success)){ await showAlert('Delete failed: ' + (del && del.error ? del.error : 'unknown')); hideProcessingOverlay(); return; }
                        progressBar.style.width = '70%';
                        const deletedCount = (del && del.deleted) ? del.deleted : delCount;
                        progressText.textContent = 'Deleting existing records: ' + deletedCount + ' of ' + delCount;
                    }

                    // perform insert per file
                    const totalInsertFiles = payloads.length;
                    for(let i=0;i<totalInsertFiles;i++){
                        const pl = payloads[i];
                        progressBar.style.width = Math.round(75 + ((i)/Math.max(1,totalInsertFiles))*25) + '%';
                        progressText.textContent = 'Inserting files: ' + (i) + ' of ' + totalInsertFiles;
                        const insResRaw = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'insert_partner', company: company.value, payloads: [pl] }) });
                        const insTxt = await insResRaw.text();
                        let ins;
                        try{ ins = JSON.parse(insTxt); }catch(e){ console.error('Insert returned non-JSON', insTxt); await showAlert('Insert failed: '+insTxt); hideProcessingOverlay(); return; }
                        if(!insResRaw.ok || !(ins && ins.success)){ await showAlert('Insert failed: ' + (ins && ins.error ? ins.error : 'unknown')); hideProcessingOverlay(); return; }
                        progressBar.style.width = Math.round(75 + ((i+1)/Math.max(1,totalInsertFiles))*25) + '%';
                        progressText.textContent = 'Inserting files: ' + (i+1) + ' of ' + totalInsertFiles;
                    }

                    // success
                    addUniqueProcessed(payloads);
                    markPayloadsUploaded(payloads, true);
                    updateCards();
                    files = files.filter(ff=>!excelFiles.includes(ff));
                    fileInput.value = '';
                    refreshState();
                    await showAlert('Successfully uploaded.', 'Success');
                }catch(e){ console.error(e); await showAlert('Insert failed: ' + (e && e.message)); }
                hideProcessingOverlay();
                return;
            }

            // If company is WORLD INTERNATIONAL COMMUNICATIONS, perform duplicate checks and insert into simplified partner table when available
            if(isWorldcomInternationalCommunications(company.value)){
                const payloads = getProcessedList().slice();
                if(payloads.length === 0){ await showAlert('No extracted payloads to insert.'); return; }
                try{
                    // build unique pairs for duplicate check (transaction_id + date)
                    const pairMap = new Map();
                    payloads.forEach(pl=>{
                        (pl.rows||[]).forEach(r=>{
                            const tx = (r['transaction_id'] || r['Transaction Id'] || r['TransactionId'] || r['Reference No.'] || '').toString().trim();
                            const rawDate = (r['date'] || r['Date'] || pl.dateStr || '');
                            let dateFull = normalizeClientDate(rawDate);
                            let dateOnly = '';
                            if(dateFull) dateOnly = dateFull.split(' ')[0];
                            if(tx) pairMap.set(tx + '|' + dateOnly, { transaction_id: tx, date: dateOnly });
                        });
                    });
                    const pairs = Array.from(pairMap.values());

                    const url = location.origin + '/autorecon/src/controllers/excelcontrol/wic/wic-insert.php';
                    // per-file duplicate check
                    const totalFiles = payloads.length || 0;
                    try{ showProcessingOverlay(totalFiles); }catch(e){}
                    progressBar.style.width = '0%';
                    progressText.textContent = 'Checking database for duplicates: 0 of ' + totalFiles;
                    const allDuplicates = [];
                    for(let i=0;i<totalFiles;i++){
                        const pl = payloads[i];
                        const filePairs = [];
                        (pl.rows||[]).forEach(r=>{
                            const tx = (r['transaction_id'] || r['Transaction Id'] || r['TransactionId'] || r['Reference No.'] || '').toString().trim();
                            const rawDate = (r['date'] || r['Date'] || pl.dateStr || '');
                            let dateFull = normalizeClientDate(rawDate);
                            let dateOnly = '';
                            if(dateFull) dateOnly = dateFull.split(' ')[0];
                            if(tx) filePairs.push({ transaction_id: tx, date: dateOnly });
                        });
                        if(filePairs.length === 0){ const pct = Math.round(((i+1)/Math.max(1,totalFiles)) * 45); progressBar.style.width = pct + '%'; progressText.textContent = 'Checking database for duplicates: ' + (i+1) + ' of ' + totalFiles; continue; }
                        const chkResRaw = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'check_partner', pairs: filePairs }) });
                        const chkTxt = await chkResRaw.text();
                        let chk;
                        try{ chk = JSON.parse(chkTxt); }catch(e){ console.error('Duplicate check returned non-JSON', chkTxt); await showAlert('Duplicate check failed: '+chkTxt); hideProcessingOverlay(); return; }
                        if(!chkResRaw.ok || !(chk && chk.success)){ await showAlert('Duplicate check failed: ' + (chk && chk.error ? chk.error : 'unknown')); hideProcessingOverlay(); return; }
                        if(Array.isArray(chk.duplicates) && chk.duplicates.length>0){ allDuplicates.push(...chk.duplicates); }
                        const pct = Math.round(((i+1)/Math.max(1,totalFiles)) * 45);
                        progressBar.style.width = pct + '%';
                        progressText.textContent = 'Checking database for duplicates: ' + (i+1) + ' of ' + totalFiles;
                    }

                    if(allDuplicates.length>0){
                        const msg = 'Data with the same Transaction Id and Date already exists. Do you want to overwrite the existing data?';
                        const dialogPresent = !!document.getElementById('pdDialog');
                        let confirmed = false;
                        try{
                            if(dialogPresent) confirmed = await showConfirm(msg);
                            else confirmed = confirm(msg);
                        }catch(e){ confirmed = false; }
                        if(!confirmed){ hideProcessingOverlay(); return; }
                        const delCount = allDuplicates.length;
                        progressBar.style.width = '55%';
                        progressText.textContent = 'Deleting existing records: 0 of ' + delCount;
                        const delPairs = allDuplicates.map(d=>({ transaction_id: (d.transaction_id||d.reference_no||''), date: d.date }));
                        const delResRaw = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'delete_partner', pairs: delPairs }) });
                        const delTxt = await delResRaw.text();
                        let del;
                        try{ del = JSON.parse(delTxt); }catch(e){ console.error('Delete returned non-JSON', delTxt); await showAlert('Delete failed: '+delTxt); hideProcessingOverlay(); return; }
                        if(!delResRaw.ok || !(del && del.success)){ await showAlert('Delete failed: ' + (del && del.error ? del.error : 'unknown')); hideProcessingOverlay(); return; }
                        progressBar.style.width = '70%';
                        const deletedCount = (del && del.deleted) ? del.deleted : delCount;
                        progressText.textContent = 'Deleting existing records: ' + deletedCount + ' of ' + delCount;
                    }

                    // perform insert per file
                    const totalInsertFiles = payloads.length;
                    for(let i=0;i<totalInsertFiles;i++){
                        const pl = payloads[i];
                        progressBar.style.width = Math.round(75 + ((i)/Math.max(1,totalInsertFiles))*25) + '%';
                        progressText.textContent = 'Inserting files: ' + (i) + ' of ' + totalInsertFiles;
                        const insResRaw = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'insert_partner', company: company.value, payloads: [pl] }) });
                        const insTxt = await insResRaw.text();
                        let ins;
                        try{ ins = JSON.parse(insTxt); }catch(e){ console.error('Insert returned non-JSON', insTxt); await showAlert('Insert failed: '+insTxt); hideProcessingOverlay(); return; }
                        if(!insResRaw.ok || !(ins && ins.success)){ await showAlert('Insert failed: ' + (ins && ins.error ? ins.error : 'unknown')); hideProcessingOverlay(); return; }
                        progressBar.style.width = Math.round(75 + ((i+1)/Math.max(1,totalInsertFiles))*25) + '%';
                        progressText.textContent = 'Inserting files: ' + (i+1) + ' of ' + totalInsertFiles;
                    }

                    // success
                    addUniqueProcessed(payloads);
                    markPayloadsUploaded(payloads, true);
                    updateCards();
                    files = files.filter(ff=>!excelFiles.includes(ff));
                    fileInput.value = '';
                    refreshState();
                    await showAlert('Successfully uploaded.', 'Success');
                }catch(e){ console.error(e); await showAlert('Insert failed: ' + (e && e.message)); }
                hideProcessingOverlay();
                return;
            }

            // If company is RCBC, perform duplicate checks and insert into RCBC partner table
            if(isRcbc(company.value)){
                const payloads = getProcessedList().slice();
                if(payloads.length === 0){ await showAlert('No extracted payloads to insert.'); return; }
                try{
                    // build unique pairs for duplicate check (transaction_id/reference_no + date)
                    const pairMap = new Map();
                    payloads.forEach(pl=>{
                        (pl.rows||[]).forEach(r=>{
                            const tx = (r['transaction_id'] || r['Reference No.'] || r['reference_no'] || r['ref_no'] || r['payout_id'] || r['Transaction Id'] || r['TransactionId'] || '').toString().trim();
                            const rawDate = (r['date'] || r['Date'] || pl.dateStr || '');
                            let dateFull = normalizeClientDate(rawDate);
                            let dateOnly = '';
                            if(dateFull) dateOnly = dateFull.split(' ')[0];
                            if(tx) pairMap.set(tx + '|' + dateOnly, { transaction_id: tx, date: dateOnly });
                        });
                    });

                    const url = location.origin + '/autorecon/src/controllers/excelcontrol/rcbc/rcbc-insert.php';
                    const totalFiles = payloads.length || 0;
                    try{ showProcessingOverlay(totalFiles); }catch(e){}
                    progressBar.style.width = '0%';
                    progressText.textContent = 'Checking database for duplicates: 0 of ' + totalFiles;
                    const allDuplicates = [];

                    for(let i=0;i<totalFiles;i++){
                        const pl = payloads[i];
                        const filePairs = [];
                        (pl.rows||[]).forEach(r=>{
                            const tx = (r['transaction_id'] || r['Reference No.'] || r['reference_no'] || r['ref_no'] || r['payout_id'] || r['Transaction Id'] || r['TransactionId'] || '').toString().trim();
                            const rawDate = (r['date'] || r['Date'] || pl.dateStr || '');
                            let dateFull = normalizeClientDate(rawDate);
                            let dateOnly = '';
                            if(dateFull) dateOnly = dateFull.split(' ')[0];
                            if(tx) filePairs.push({ transaction_id: tx, date: dateOnly });
                        });
                        if(filePairs.length === 0){
                            const pct = Math.round(((i+1)/Math.max(1,totalFiles)) * 45);
                            progressBar.style.width = pct + '%';
                            progressText.textContent = 'Checking database for duplicates: ' + (i+1) + ' of ' + totalFiles;
                            continue;
                        }
                        const chkResRaw = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'check_partner', pairs: filePairs }) });
                        const chkTxt = await chkResRaw.text();
                        let chk;
                        try{ chk = JSON.parse(chkTxt); }catch(e){ console.error('Duplicate check returned non-JSON', chkTxt); await showAlert('Duplicate check failed: '+chkTxt); hideProcessingOverlay(); return; }
                        if(!chkResRaw.ok || !(chk && chk.success)){ await showAlert('Duplicate check failed: ' + (chk && chk.error ? chk.error : 'unknown')); hideProcessingOverlay(); return; }
                        if(Array.isArray(chk.duplicates) && chk.duplicates.length>0){ allDuplicates.push(...chk.duplicates); }
                        const pct = Math.round(((i+1)/Math.max(1,totalFiles)) * 45);
                        progressBar.style.width = pct + '%';
                        progressText.textContent = 'Checking database for duplicates: ' + (i+1) + ' of ' + totalFiles;
                    }

                    if(allDuplicates.length>0){
                        const msg = 'Data with the same Transaction/Reference ID and Date already exists. Do you want to overwrite the existing data?';
                        const dialogPresent = !!document.getElementById('pdDialog');
                        let confirmed = false;
                        try{
                            if(dialogPresent) confirmed = await showConfirm(msg);
                            else confirmed = confirm(msg);
                        }catch(e){ confirmed = false; }
                        if(!confirmed){ hideProcessingOverlay(); return; }

                        const delCount = allDuplicates.length;
                        progressBar.style.width = '55%';
                        progressText.textContent = 'Deleting existing records: 0 of ' + delCount;
                        const delPairs = allDuplicates.map(d=>({ transaction_id: (d.transaction_id||d.reference_no||d.ref_no||d.payout_id||''), date: d.date }));
                        const delResRaw = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'delete_partner', pairs: delPairs }) });
                        const delTxt = await delResRaw.text();
                        let del;
                        try{ del = JSON.parse(delTxt); }catch(e){ console.error('Delete returned non-JSON', delTxt); await showAlert('Delete failed: '+delTxt); hideProcessingOverlay(); return; }
                        if(!delResRaw.ok || !(del && del.success)){ await showAlert('Delete failed: ' + (del && del.error ? del.error : 'unknown')); hideProcessingOverlay(); return; }
                        progressBar.style.width = '70%';
                        const deletedCount = (del && del.deleted) ? del.deleted : delCount;
                        progressText.textContent = 'Deleting existing records: ' + deletedCount + ' of ' + delCount;
                    }

                    // perform insert per file
                    const totalInsertFiles = payloads.length;
                    for(let i=0;i<totalInsertFiles;i++){
                        const pl = payloads[i];
                        progressBar.style.width = Math.round(75 + ((i)/Math.max(1,totalInsertFiles))*25) + '%';
                        progressText.textContent = 'Inserting files: ' + (i) + ' of ' + totalInsertFiles;
                        const insResRaw = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'insert_partner', company: company.value, payloads: [pl] }) });
                        const insTxt = await insResRaw.text();
                        let ins;
                        try{ ins = JSON.parse(insTxt); }catch(e){ console.error('Insert returned non-JSON', insTxt); await showAlert('Insert failed: '+insTxt); hideProcessingOverlay(); return; }
                        if(!insResRaw.ok || !(ins && ins.success)){ await showAlert('Insert failed: ' + (ins && ins.error ? ins.error : 'unknown')); hideProcessingOverlay(); return; }
                        progressBar.style.width = Math.round(75 + ((i+1)/Math.max(1,totalInsertFiles))*25) + '%';
                        progressText.textContent = 'Inserting files: ' + (i+1) + ' of ' + totalInsertFiles;
                    }

                    addUniqueProcessed(payloads);
                    markPayloadsUploaded(payloads, true);
                    updateCards();
                    files = files.filter(ff=>!excelFiles.includes(ff));
                    fileInput.value = '';
                    refreshState();
                    await showAlert('Successfully uploaded.', 'Success');
                }catch(e){ console.error(e); await showAlert('Insert failed: ' + (e && e.message)); }
                hideProcessingOverlay();
                return;
            }

            if(isSkybridgePaymentInc(company.value)){
                const payloads = getProcessedList().slice();
                if(payloads.length === 0){ await showAlert('No extracted payloads to insert.'); return; }
                try{
                    const url = location.origin + '/autorecon/src/controllers/excelcontrol/skybridgepaymentinc/skybridgepaymentinc-insert.php';
                    const totalFiles = payloads.length || 0;
                    try{ showProcessingOverlay(totalFiles); }catch(e){}
                    progressBar.style.width = '0%';
                    progressText.textContent = 'Checking database for duplicates: 0 of ' + totalFiles;
                    const allDuplicates = [];

                    for(let i=0;i<totalFiles;i++){
                        const pl = payloads[i];
                        const filePairs = [];
                        (pl.rows||[]).forEach(r=>{
                            const ref = getSkybridgeReference(r);
                            const rawDate = getSkybridgeDate(r, pl);
                            let dateFull = normalizeClientDate(rawDate);
                            let dateOnly = '';
                            if(dateFull) dateOnly = dateFull.split(' ')[0];
                            if(ref) filePairs.push({ reference_no: ref, date: dateOnly });
                        });
                        if(filePairs.length === 0){
                            const pct = Math.round(((i+1)/Math.max(1,totalFiles)) * 45);
                            progressBar.style.width = pct + '%';
                            progressText.textContent = 'Checking database for duplicates: ' + (i+1) + ' of ' + totalFiles;
                            continue;
                        }
                        const chkResRaw = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'check_partner', pairs: filePairs }) });
                        const chkTxt = await chkResRaw.text();
                        let chk;
                        try{ chk = JSON.parse(chkTxt); }catch(e){ console.error('Duplicate check returned non-JSON', chkTxt); await showAlert('Duplicate check failed: '+chkTxt); hideProcessingOverlay(); return; }
                        if(!chkResRaw.ok || !(chk && chk.success)){ await showAlert('Duplicate check failed: ' + (chk && chk.error ? chk.error : 'unknown')); hideProcessingOverlay(); return; }
                        if(Array.isArray(chk.duplicates) && chk.duplicates.length>0){ allDuplicates.push(...chk.duplicates); }
                        const pct = Math.round(((i+1)/Math.max(1,totalFiles)) * 45);
                        progressBar.style.width = pct + '%';
                        progressText.textContent = 'Checking database for duplicates: ' + (i+1) + ' of ' + totalFiles;
                    }

                    if(allDuplicates.length>0){
                        const msg = 'Data with the same Reference No. and Date already exists. Do you want to overwrite the existing data?';
                        const dialogPresent = !!document.getElementById('pdDialog');
                        let confirmed = false;
                        try{
                            if(dialogPresent) confirmed = await showConfirm(msg);
                            else confirmed = confirm(msg);
                        }catch(e){ confirmed = false; }
                        if(!confirmed){ hideProcessingOverlay(); return; }

                        const delCount = allDuplicates.length;
                        progressBar.style.width = '55%';
                        progressText.textContent = 'Deleting existing records: 0 of ' + delCount;
                        const delResRaw = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'delete_partner', pairs: allDuplicates.map(d=>({ reference_no: d.reference_no, date: d.date })) }) });
                        const delTxt = await delResRaw.text();
                        let del;
                        try{ del = JSON.parse(delTxt); }catch(e){ console.error('Delete returned non-JSON', delTxt); await showAlert('Delete failed: '+delTxt); hideProcessingOverlay(); return; }
                        if(!delResRaw.ok || !(del && del.success)){ await showAlert('Delete failed: ' + (del && del.error ? del.error : 'unknown')); hideProcessingOverlay(); return; }
                        progressBar.style.width = '70%';
                        const deletedCount = (del && del.deleted) ? del.deleted : delCount;
                        progressText.textContent = 'Deleting existing records: ' + deletedCount + ' of ' + delCount;
                    }

                    const totalInsertFiles = payloads.length;
                    for(let i=0;i<totalInsertFiles;i++){
                        const pl = payloads[i];
                        progressBar.style.width = Math.round(75 + ((i)/Math.max(1,totalInsertFiles))*25) + '%';
                        progressText.textContent = 'Inserting files: ' + (i) + ' of ' + totalInsertFiles;
                        const insResRaw = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'insert_partner', company: company.value, payloads: [pl] }) });
                        const insTxt = await insResRaw.text();
                        let ins;
                        try{ ins = JSON.parse(insTxt); }catch(e){ console.error('Insert returned non-JSON', insTxt); await showAlert('Insert failed: '+insTxt); hideProcessingOverlay(); return; }
                        if(!insResRaw.ok || !(ins && ins.success)){ await showAlert('Insert failed: ' + (ins && ins.error ? ins.error : 'unknown')); hideProcessingOverlay(); return; }
                        progressBar.style.width = Math.round(75 + ((i+1)/Math.max(1,totalInsertFiles))*25) + '%';
                        progressText.textContent = 'Inserting files: ' + (i+1) + ' of ' + totalInsertFiles;
                    }

                    addUniqueProcessed(payloads);
                    markPayloadsUploaded(payloads, true);
                    updateCards();
                    files = files.filter(ff=>!excelFiles.includes(ff));
                    fileInput.value = '';
                    refreshState();
                    await showAlert('Successfully uploaded.', 'Success');
                }catch(e){ console.error(e); await showAlert('Insert failed: ' + (e && e.message)); }
                hideProcessingOverlay();
                return;
            }

            if(isMoneygram(company.value)){
                const payloads = getProcessedList().slice();
                if(payloads.length === 0){ await showAlert('No extracted payloads to insert.'); return; }

                try{
                    const url = location.origin + '/autorecon/src/controllers/excelcontrol/moneygram/moneygram-insert.php';
                    const totalFiles = payloads.length || 0;
                    try{ showProcessingOverlay(totalFiles); }catch(e){}
                    progressBar.style.width = '0%';
                    progressText.textContent = 'Checking database for duplicates: 0 of ' + totalFiles;
                    const allDuplicates = [];

                    for(let i=0;i<totalFiles;i++){
                        const pl = payloads[i];
                        const filePairs = [];
                        (pl.rows||[]).forEach(r=>{
                            const transactionId = getMoneygramTransactionId(r);
                            const rawDate = getMoneygramTranDate(r, pl);
                            const dateFull = normalizeClientDate(rawDate);
                            const dateOnly = dateFull ? dateFull.split(' ')[0] : '';
                            if(transactionId) filePairs.push({ transaction_id: transactionId, tran_date: dateOnly });
                        });

                        if(filePairs.length === 0){
                            const pct = Math.round(((i+1)/Math.max(1,totalFiles)) * 45);
                            progressBar.style.width = pct + '%';
                            progressText.textContent = 'Checking database for duplicates: ' + (i+1) + ' of ' + totalFiles;
                            continue;
                        }

                        const chkResRaw = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'check_partner', pairs: filePairs }) });
                        const chkTxt = await chkResRaw.text();
                        let chk;
                        try{ chk = JSON.parse(chkTxt); }catch(e){ console.error('Duplicate check returned non-JSON', chkTxt); await showAlert('Duplicate check failed: '+chkTxt); hideProcessingOverlay(); return; }
                        if(!chkResRaw.ok || !(chk && chk.success)){ await showAlert('Duplicate check failed: ' + (chk && chk.error ? chk.error : 'unknown')); hideProcessingOverlay(); return; }
                        if(Array.isArray(chk.duplicates) && chk.duplicates.length>0){ allDuplicates.push(...chk.duplicates); }

                        const pct = Math.round(((i+1)/Math.max(1,totalFiles)) * 45);
                        progressBar.style.width = pct + '%';
                        progressText.textContent = 'Checking database for duplicates: ' + (i+1) + ' of ' + totalFiles;
                    }

                    if(allDuplicates.length > 0){
                        const msg = 'Data with the same Transaction Id and Transaction Date already exists. Do you want to overwrite the existing data?';
                        const dialogPresent = !!document.getElementById('pdDialog');
                        let confirmed = false;
                        try{
                            if(dialogPresent) confirmed = await showConfirm(msg);
                            else confirmed = confirm(msg);
                        }catch(e){ confirmed = false; }
                        if(!confirmed){ hideProcessingOverlay(); return; }

                        const delCount = allDuplicates.length;
                        progressBar.style.width = '55%';
                        progressText.textContent = 'Deleting existing records: 0 of ' + delCount;

                        const delPairs = allDuplicates.map(d=>(
                            {
                                transaction_id: (d.transaction_id || d.reference_id || d.reference_no || '').toString(),
                                tran_date: d.tran_date || d.date || ''
                            }
                        ));
                        const delResRaw = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'delete_partner', pairs: delPairs }) });
                        const delTxt = await delResRaw.text();
                        let del;
                        try{ del = JSON.parse(delTxt); }catch(e){ console.error('Delete returned non-JSON', delTxt); await showAlert('Delete failed: '+delTxt); hideProcessingOverlay(); return; }
                        if(!delResRaw.ok || !(del && del.success)){ await showAlert('Delete failed: ' + (del && del.error ? del.error : 'unknown')); hideProcessingOverlay(); return; }

                        progressBar.style.width = '70%';
                        const deletedCount = (del && del.deleted) ? del.deleted : delCount;
                        progressText.textContent = 'Deleting existing records: ' + deletedCount + ' of ' + delCount;
                    }

                    const totalInsertFiles = payloads.length;
                    let totalInserted = 0;
                    for(let i=0;i<totalInsertFiles;i++){
                        const pl = payloads[i];
                        progressBar.style.width = Math.round(75 + ((i)/Math.max(1,totalInsertFiles))*25) + '%';
                        progressText.textContent = 'Inserting files: ' + (i) + ' of ' + totalInsertFiles;

                        const insResRaw = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'insert_partner', company: company.value, payloads: [pl] }) });
                        const insTxt = await insResRaw.text();
                        let ins;
                        try{ ins = JSON.parse(insTxt); }catch(e){ console.error('Insert returned non-JSON', insTxt); await showAlert('Insert failed: '+insTxt); hideProcessingOverlay(); return; }

                        if(!insResRaw.ok || !(ins && ins.success)){
                            const detailText = (ins && Array.isArray(ins.error_details) && ins.error_details.length)
                                ? ('\n' + ins.error_details.slice(0, 10).map(function(er){
                                    const rowNo = er && er.row ? ('Row ' + er.row) : 'Row ?';
                                    const reason = er && er.reason ? er.reason : 'Validation error';
                                    return rowNo + ': ' + reason;
                                }).join('\n'))
                                : '';
                            await showAlert('Insert failed: ' + (ins && ins.error ? ins.error : 'unknown') + detailText);
                            hideProcessingOverlay();
                            return;
                        }

                        totalInserted += Number(ins.inserted || 0);
                        progressBar.style.width = Math.round(75 + ((i+1)/Math.max(1,totalInsertFiles))*25) + '%';
                        progressText.textContent = 'Inserting files: ' + (i+1) + ' of ' + totalInsertFiles;
                    }

                    addUniqueProcessed(payloads);
                    markPayloadsUploaded(payloads, true);
                    updateCards();
                    files = files.filter(ff=>!excelFiles.includes(ff));
                    fileInput.value = '';
                    refreshState();
                    await showAlert('Successfully uploaded.', 'Success');
                }catch(e){ console.error(e); await showAlert('Insert failed: ' + (e && e.message)); }
                hideProcessingOverlay();
                return;
            }

            // fallback: if not MBTC or WORLD INTERNATIONAL COMMUNICATIONS, just show extracted results
            refreshState();
        });

        function isExcelFile(f){ const name = (f.name||'').toLowerCase(); if(name.endsWith('.xls') || name.endsWith('.xlsx') || name.endsWith('.xlsm') || name.endsWith('.xlsb') || name.endsWith('.ods')) return true; // legacy Excel
            // allow CSV for WORLD INTERNATIONAL COMMUNICATIONS partner flow
            try{ if(company && isWorldcomInternationalCommunications(company.value) && name.endsWith('.csv')) return true; }catch(e){}
            // allow CSV for RCBC partner flow
            try{ if(company && isRcbc(company.value) && name.endsWith('.csv')) return true; }catch(e){}
            return false; }

        // normalize date strings (Excel serials or common formats)
        function normalizeClientDate(raw){ if(raw === null || raw === undefined) return ''; let s = (''+raw).trim(); if(s === '') return ''; if(/^[0-9]+(\.[0-9]+)?$/.test(s)){ const serial = parseFloat(s); if(!isNaN(serial)){ const epoch = new Date(Date.UTC(1899,11,30)); const ms = serial * 24 * 60 * 60 * 1000; const dt = new Date(epoch.getTime() + ms); if(!isNaN(dt.getTime())) return dt.getFullYear() + '-' + String(dt.getMonth()+1).padStart(2,'0') + '-' + String(dt.getDate()).padStart(2,'0') + ' ' + String(dt.getHours()).padStart(2,'0') + ':' + String(dt.getMinutes()).padStart(2,'0') + ':' + String(dt.getSeconds()).padStart(2,'0'); } } const d = new Date(s); if(!isNaN(d.getTime())){ return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0') + ' ' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0') + ':' + String(d.getSeconds()).padStart(2,'0'); } return s; }

        // Format numbers with thousands separators (e.g., 3254 -> 3,254)
        function formatNumber(n){
            if(n === null || n === undefined) return '';
            const num = Number(n);
            if(isNaN(num)) return String(n);
            try{ return num.toLocaleString('en-US'); }catch(e){ return String(num); }
        }

        function parsePartnerUploadResponse(rawText){
            if(!rawText) return null;
            try{ return JSON.parse(rawText); }catch(e){ return null; }
        }

        function isEncryptedWorkbookResponse(response){
            const errorCode = String((response && response.errorCode) || '');
            const message = String((response && response.error) || '').toLowerCase();
            return errorCode === 'encrypted_password_required'
                || message.includes('encrypted')
                || message.includes('unsupported encryption algorithm');
        }

        function isInvalidWorkbookPasswordResponse(response){
            const errorCode = String((response && response.errorCode) || '');
            const message = String((response && response.error) || '').toLowerCase();
            return errorCode === 'decrypt_failed' || message.includes('provided password');
        }

        let passwordModalState = null;
        let passwordModalEl = null;
        function ensurePasswordModal(){
            if(passwordModalEl) return passwordModalEl;
            passwordModalEl = document.createElement('div');
            passwordModalEl.className = 'pd-password-modal';
            passwordModalEl.hidden = true;
            passwordModalEl.innerHTML = '<div class="pd-password-modal__card" role="dialog" aria-modal="true" aria-label="Workbook password"><h3 class="pd-password-modal__title">Workbook password required</h3><p class="pd-password-modal__text"></p><input class="pd-password-modal__input" type="password" autocomplete="current-password" placeholder="Enter workbook password"><div class="pd-password-modal__actions"><button type="button" class="material-btn material-btn--secondary pd-password-modal__cancel">Cancel</button><button type="button" class="material-btn material-btn--primary pd-password-modal__submit">Continue</button></div></div>';
            document.body.appendChild(passwordModalEl);

            const inputEl = passwordModalEl.querySelector('.pd-password-modal__input');
            const cancelBtnEl = passwordModalEl.querySelector('.pd-password-modal__cancel');
            const submitBtnEl = passwordModalEl.querySelector('.pd-password-modal__submit');

            function closePasswordModal(result){
                if(!passwordModalState) return;
                const resolver = passwordModalState.resolve;
                passwordModalState = null;
                passwordModalEl.hidden = true;
                inputEl.value = '';
                resolver(result);
            }

            cancelBtnEl.addEventListener('click', function(){ closePasswordModal(''); });
            submitBtnEl.addEventListener('click', function(){ closePasswordModal(String(inputEl.value || '').trim()); });
            inputEl.addEventListener('keydown', function(event){
                if(event.key === 'Enter'){
                    event.preventDefault();
                    closePasswordModal(String(inputEl.value || '').trim());
                } else if(event.key === 'Escape'){
                    event.preventDefault();
                    closePasswordModal('');
                }
            });
            passwordModalEl.addEventListener('click', function(event){
                if(event.target === passwordModalEl) closePasswordModal('');
            });

            return passwordModalEl;
        }

        async function requestWorkbookPassword(file, invalidPassword){
            const modal = ensurePasswordModal();
            const textEl = modal.querySelector('.pd-password-modal__text');
            const inputEl = modal.querySelector('.pd-password-modal__input');
            textEl.textContent = invalidPassword
                ? 'The password for "' + file.name + '" was not accepted. Enter the workbook password and try again.'
                : '"' + file.name + '" is encrypted. Enter the workbook password to continue.';
            inputEl.value = getCachedWorkbookPassword() || '';
            modal.hidden = false;
            inputEl.focus();
            inputEl.select();
            return new Promise((resolve)=>{ passwordModalState = { resolve }; });
        }

        async function uploadToPartner(file, options){
            // build endpoint based on selected company while keeping METROBANK HEAD OFFICE mapped to mbtc routes
            const companyKey = resolveCompanyKey(company.value);
            if(!companyKey) return { success:false, error:'No company selected' };
            const url = location.origin + '/autorecon/src/controllers/excelcontrol/' + companyKey + '/' + companyKey + '-partnerdata.php';
            const password = options && typeof options.password === 'string' ? options.password : '';
            const fd = new FormData(); fd.append('file', file); fd.append('filename', file.name); fd.append('company', company.value || ''); fd.append('password', password);
            try{
                const r = await fetch(url, { method: 'POST', body: fd });
                const txt = await r.text();
                const parsed = parsePartnerUploadResponse(txt);
                if(r.status === 404){ return { success:false, error:'No extractor implemented for ' + company.value, raw: txt }; }
                if(!r.ok){
                    if(parsed && (isEncryptedWorkbookResponse(parsed) || isInvalidWorkbookPasswordResponse(parsed))) console.warn('Workbook requires password retry', parsed);
                    else console.error('Upload failed', r.status, txt);
                    return (parsed && typeof parsed === 'object') ? parsed : { success:false, error:'HTTP '+r.status, raw: txt };
                }
                const ct = r.headers.get('content-type') || '';
                const maybeJson = (ct.indexOf('application/json') !== -1) || (/^[\s\r\n]*[\{\[]/.test(txt));
                if(maybeJson){ if(parsed && typeof parsed === 'object') return parsed; console.error('Invalid JSON response', txt); return { success:false, error:'Invalid JSON', raw: txt }; }
                console.error('Expected JSON response, got:', ct, txt);
                return { success:false, error:'Non-JSON response', raw: txt };
            }catch(e){ console.error('Fetch error', e); return { success:false, error: e.message }; }
        }

        async function uploadToPartnerWithRetry(file){
            let response = await uploadToPartner(file, { password: getCachedWorkbookPassword() });
            if(response && response.success) return response;
            if(!isMetrobankHeadOffice(company.value)) return response;

            if(isEncryptedWorkbookResponse(response) || isInvalidWorkbookPasswordResponse(response)){
                const password = await requestWorkbookPassword(file, isInvalidWorkbookPasswordResponse(response));
                if(!password){
                    setCachedWorkbookPassword('');
                    return { success:false, error:'Workbook password entry was cancelled.', errorCode:'password_prompt_cancelled' };
                }

                setCachedWorkbookPassword(password);
                response = await uploadToPartner(file, { password });
                if(response && response.success) return response;

                if(isInvalidWorkbookPasswordResponse(response)){
                    const retryPassword = await requestWorkbookPassword(file, true);
                    if(!retryPassword){
                        setCachedWorkbookPassword('');
                        return response;
                    }
                    setCachedWorkbookPassword(retryPassword);
                    response = await uploadToPartner(file, { password: retryPassword });
                }
            }

            if(isInvalidWorkbookPasswordResponse(response)) setCachedWorkbookPassword('');
            return response;
        }

        function showProcessingOverlay(total){ if(!overlayEl) return; progressBar.style.width='0%'; progressText.textContent = 'Extracted 0 of ' + total + ' files'; overlayEl.style.display='flex'; cancelBtn.onclick = ()=>{ overlayEl.style.display='none'; }; }
        function updateProcessing(done,total){ if(!overlayEl) return; const pct = Math.round((done/total)*100); progressBar.style.width = pct + '%'; progressText.textContent = 'Extracted ' + done + ' of ' + total + ' files'; }
        function hideProcessingOverlay(){ if(overlayEl) overlayEl.style.display='none'; }

        function updateCards(){
            const processed = getProcessedList();
            processed.sort((a,b)=>{ return getPayloadTimestamp(a) - getPayloadTimestamp(b); });
            cardsEl.innerHTML = '';
            if(processed.length===0) return;
            const list = document.createElement('div'); list.style.display='flex'; list.style.flexDirection='column'; list.style.gap='0.5rem';
            processed.forEach((p,idx)=>{
                const cardWrap = document.createElement('div');
                cardWrap.style.display='flex';
                cardWrap.style.alignItems='center';
                cardWrap.style.justifyContent='space-between';
                cardWrap.style.background='#f5f5f5';
                cardWrap.style.padding='0.5rem 0.75rem';
                cardWrap.style.borderRadius='8px';
                cardWrap.style.border='1px solid #eee';
                const left = document.createElement('div');
                left.style.display='flex';
                left.style.flexDirection='column';
                left.style.gap='2px';
                const displayCompany = (company && company.value) ? company.value : 'Partner';
                const displayDateRaw = getPayloadDateRaw(p);
                const displayDate = displayDateRaw ? formatDisplayDate(displayDateRaw) : '';
                const title=document.createElement('div');
                title.style.fontWeight='600';
                // Use company-aware date mapping (e.g., MBTC coverDate) for accurate display.
                title.textContent = '[' + (displayDate || p.dateStr || '') + '] ' + (p.rows ? (formatNumber(p.rows.length) + ' rows') : '');
                const meta = document.createElement('div');
                meta.style.fontSize = '0.9rem';
                meta.style.color = '#666';
                // filename only; rows count shown next to date in the title
                meta.textContent = (p.filename || '');
                left.appendChild(title);
                left.appendChild(meta);
                const right=document.createElement('div');
                right.style.display='flex';
                right.style.gap='0.5rem';
                let status = null;
                if(p._uploaded){
                    status = document.createElement('span');
                    status.className = 'upload-status is-uploaded';
                    status.title = 'Uploaded to database';
                    status.innerHTML = '<span class="material-icons" aria-hidden="true">cloud_done</span><span>Uploaded</span>';
                }
                const viewBtn=document.createElement('button');
                viewBtn.className='icon-btn view';
                viewBtn.type='button';
                viewBtn.title='View';
                viewBtn.setAttribute('aria-label','View');
                viewBtn.innerHTML='<span class="material-icons" aria-hidden="true">visibility</span>';
                viewBtn.onclick = ()=> openViewer(p);
                const delBtn=document.createElement('button');
                delBtn.className='icon-btn delete';
                delBtn.type='button';
                delBtn.title='Delete';
                delBtn.setAttribute('aria-label','Delete');
                delBtn.innerHTML='<span class="material-icons" aria-hidden="true">delete</span>';
                delBtn.onclick = ()=> { processed.splice(idx,1); updateCards(); };
                if(status) right.appendChild(status);
                right.appendChild(viewBtn);
                right.appendChild(delBtn);
                cardWrap.appendChild(left);
                cardWrap.appendChild(right);
                list.appendChild(cardWrap);
            });
            cardsEl.appendChild(list);
        }

        function buildViewerPayload(payload){
            const cloned = Object.assign({}, payload || {});
            const rows = Array.isArray(cloned.rows) ? cloned.rows : [];
            cloned.partnerName = company && company.value ? company.value : '';
            cloned.viewType = 'partner';

            if(isRcbc(company && company.value ? company.value : '')){
                const hiddenKeys = new Set(['Date', 'Reference No.', 'date', 'transaction_id', 'amount', 'coin', 'PHP', 'USD', 'in PHP']);
                cloned.rows = rows.map(function(row){
                    if(!row || typeof row !== 'object') return row;
                    const nextRow = Object.assign({}, row);
                    hiddenKeys.forEach(function(key){ delete nextRow[key]; });
                    return nextRow;
                });
                return cloned;
            }

            cloned.rows = rows;
            return cloned;
        }

        function getViewerUrl(){
            const selectedCompany = company && company.value ? company.value : '';
            if(isRcbc(selectedCompany)) return location.origin + '/autorecon/src/controllers/excelcontrol/rcbc/rcbc-viewer.php';
            if(isWorldcomInternationalCommunications(selectedCompany)) return location.origin + '/autorecon/src/controllers/excelcontrol/wic/wic-viewer.php';
            if(isSkybridgePaymentInc(selectedCompany)) return location.origin + '/autorecon/src/controllers/excelcontrol/skybridgepaymentinc/skybridgepaymentinc-viewer.php';
            if(isMoneygram(selectedCompany)) return location.origin + '/autorecon/src/controllers/excelcontrol/moneygram/moneygram-viewer.php';
            return location.origin + '/autorecon/src/controllers/excelcontrol/mbtc/mbtc-viewer.php';
        }

        async function openViewer(payload){ try{ const url = getViewerUrl(); const res = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ data: buildViewerPayload(payload) }) }); const html = await res.text(); showModal(html); }catch(e){ console.error(e); await showAlert('Failed to open viewer'); } }

        // modal helpers
        let modalEl = null;
        function showModal(html){ if(!modalEl){ modalEl = document.createElement('div'); modalEl.className='pd-modal'; modalEl.style.position='fixed'; modalEl.style.left=0; modalEl.style.top=0; modalEl.style.right=0; modalEl.style.bottom=0; modalEl.style.background='rgba(0,0,0,0.6)'; modalEl.style.display='flex'; modalEl.style.alignItems='center'; modalEl.style.justifyContent='center'; modalEl.style.zIndex=11000; modalEl.innerHTML = '<div class="pd-modal-inner"> <button class="pd-close" aria-label="Close"><span class="material-icons">close</span></button><div class="pd-modal-body"></div></div>'; document.body.appendChild(modalEl); modalEl.querySelector('.pd-close').addEventListener('click', ()=>{ modalEl.style.display='none'; }); modalEl.addEventListener('click', function(e){ if(e.target === modalEl) modalEl.style.display='none'; }); } const body = modalEl.querySelector('.pd-modal-body'); body.innerHTML = html; const inner = modalEl.querySelector('.pd-modal-inner'); if(inner){ inner.style.width='98%'; inner.style.height='96%'; inner.style.maxWidth='none'; inner.style.maxHeight='none'; inner.style.background='#fff'; inner.style.padding='0.5rem'; inner.style.borderRadius='6px'; inner.style.boxShadow='0 10px 40px rgba(0,0,0,0.4)'; inner.style.overflow='hidden'; inner.style.position='relative';
                // style close button to match web viewer modal
                const closeBtn = modalEl.querySelector('.pd-close');
                if(closeBtn){
                    closeBtn.style.position = 'absolute';
                    closeBtn.style.right = '12px';
                    closeBtn.style.top = '8px';
                    closeBtn.style.background = 'transparent';
                    closeBtn.style.border = 'none';
                    closeBtn.style.cursor = 'pointer';
                    closeBtn.style.padding = '6px';
                    closeBtn.style.borderRadius = '6px';
                    closeBtn.onmouseover = ()=>{ closeBtn.style.background = '#f5f5f5'; };
                    closeBtn.onmouseout = ()=>{ closeBtn.style.background = 'transparent'; };
                }
            } modalEl.style.display='flex'; }

        // confirm modal wiring
        const confirmModal = document.getElementById('pdConfirmModal');
        const confirmCompanyEl = document.getElementById('pdConfirmCompany');
        const confirmCountEl = document.getElementById('pdConfirmCount');
        const confirmListEl = document.getElementById('pdConfirmList');
        const fetchBtn = document.getElementById('pdFetchBtn');
        const confirmCancel = document.getElementById('pdConfirmCancel');
        function showConfirmModal(fileArray){ if(!confirmModal) return; confirmCompanyEl.textContent = company.value || ''; confirmCountEl.textContent = fileArray.length; confirmListEl.innerHTML = ''; fileArray.forEach(f=>{ const li=document.createElement('li'); li.style.padding='6px 0'; li.style.borderBottom='1px solid #f1f1f1'; li.textContent = f.name; confirmListEl.appendChild(li); }); confirmModal.style.display='flex'; fetchBtn.onclick = ()=>{ confirmModal.style.display='none'; processPartnerFiles(fileArray); }; confirmCancel.onclick = ()=>{ confirmModal.style.display='none'; }; }

        // select company modal
        const selectCompanyModal = document.getElementById('pdDialog');
        function showSelectCompanyModal(){ if(!selectCompanyModal) return; selectCompanyModal.querySelector('.pdDialogTitle').textContent = 'Select a company first'; selectCompanyModal.querySelector('.pdDialogMessage').textContent = 'Please select a company from the Company dropdown before uploading or dropping files.'; const ok = selectCompanyModal.querySelector('.pdDialogOk'); const cancel = selectCompanyModal.querySelector('.pdDialogCancel'); cancel.style.display='none'; ok.style.display=''; selectCompanyModal.style.display='flex'; ok.onclick = ()=>{ selectCompanyModal.style.display='none'; } }

        // generic alert/confirm (choose an available dialog element)
        function getDialogPrefix(){
            // prefer the local partner dialog first so partner flows always target it when available
            if(document.getElementById('pdDialog')) return 'pd';
            // if partner dialog not available, use mbtc dialog when present (useful on combined pages)
            if(document.getElementById('mbtcDialog')) return 'mbtc';
            return '';
        }
        function _closeDialog(){ const prefix = getDialogPrefix(); if(!prefix) return; const dlg = document.getElementById(prefix + 'Dialog'); if(dlg) dlg.style.display='none'; }
        function showAlert(message, title){ return new Promise((resolve)=>{
            const prefix = getDialogPrefix(); const dlg = document.getElementById(prefix + 'Dialog');
            if(!dlg){ console.warn('Dialog not available:', message); return resolve(); }
            const titleEl = dlg.querySelector('.' + prefix + 'DialogTitle');
            const msgEl = dlg.querySelector('.' + prefix + 'DialogMessage');
            const ok = dlg.querySelector('.' + prefix + 'DialogOk');
            const cancel = dlg.querySelector('.' + prefix + 'DialogCancel');
            if(titleEl) titleEl.textContent = title || 'Notice';
            if(msgEl) msgEl.textContent = message;
            if(cancel) cancel.style.display = 'none';
            if(ok) ok.style.display = '';
            dlg.style.display = 'flex';
            if(ok) ok.onclick = function(){ _closeDialog(); resolve(); };
        }); }
        function showConfirm(message, title){ return new Promise((resolve)=>{
            const prefix = getDialogPrefix(); const dlg = document.getElementById(prefix + 'Dialog');
            if(!dlg){ console.warn('Dialog not available (confirm):', message); return resolve(false); }
            const titleEl = dlg.querySelector('.' + prefix + 'DialogTitle');
            const msgEl = dlg.querySelector('.' + prefix + 'DialogMessage');
            const ok = dlg.querySelector('.' + prefix + 'DialogOk');
            const cancel = dlg.querySelector('.' + prefix + 'DialogCancel');
            if(titleEl) titleEl.textContent = title || 'Confirm';
            if(msgEl) msgEl.textContent = message;
            if(cancel) cancel.style.display = '';
            if(ok) ok.style.display = '';
            dlg.style.display = 'flex';
            if(ok) ok.onclick = function(){ _closeDialog(); resolve(true); };
            if(cancel) cancel.onclick = function(){ _closeDialog(); resolve(false); };
        }); }

        async function processPartnerFiles(excelFiles){
            if(!excelFiles || excelFiles.length===0) return;
            const targetCompanyKey = getCompanyKey();
            showProcessingOverlay(excelFiles.length);
            let done = 0;

            for(const f of excelFiles.slice()){
                try{
                    const precheck = classifyForPartnerUploader(null, f.name);
                    if(!precheck.accepted){
                        await showAlert(precheck.message || 'Invalid File Format', 'Notice');
                        done++;
                        updateProcessing(done, excelFiles.length);
                        continue;
                    }

                    const res = await uploadToPartnerWithRetry(f);
                    if(res && res.success){
                        const pl = res.payload;
                        const classification = classifyForPartnerUploader(pl, f.name);
                        if(!classification.accepted){
                            await showAlert(classification.message || 'Invalid File Format', 'Notice');
                        } else {
                            const list = getProcessedList(targetCompanyKey);
                            const exists = list.find(x=> (x.filename===pl.filename && x.dateStr===pl.dateStr));
                            if(!exists){
                                if(typeof pl._uploaded === 'undefined') pl._uploaded = false;
                                list.push(pl);
                                updateCards();
                            }
                        }
                    } else if(res && res.errorCode !== 'password_prompt_cancelled') {
                        console.error('Processing failed', res);
                        await showAlert(res.error || ('Processing failed for ' + f.name));
                    }
                }catch(err){
                    console.error(err);
                }

                done++;
                updateProcessing(done, excelFiles.length);
            }

            hideProcessingOverlay();
            files = files.filter(ff=>!excelFiles.includes(ff));
            fileInput.value='';
            refreshState();
        }

        attachPartnerAutocomplete(company, partners);
        refreshState();
        updateCards();
    })();
    </script>
</section>
