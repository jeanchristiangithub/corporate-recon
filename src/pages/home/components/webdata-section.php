<?php
// UI-only Web Data Upload Section (loads partner list from DB)
require_once __DIR__ . '/../../../config/db.php';

$partners = [];
$partnerIds = [];
try {
    $pdo = masterDataConnection();
    $stmt = $pdo->query("SELECT partner_name, partner_id FROM corpo_partner_masterfile WHERE partner_name IS NOT NULL AND partner_name <> '' ORDER BY partner_name ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (is_array($rows) && count($rows) > 0) {
        foreach ($rows as $row) {
            $name = trim((string)($row['partner_name'] ?? ''));
            if ($name === '') continue;
            if (!in_array($name, $partners, true)) $partners[] = $name;
            if (!array_key_exists($name, $partnerIds)) {
                $partnerIds[$name] = (string)($row['partner_id'] ?? '');
            }
        }
    }
} catch (Throwable $e) {
    $partners = [];
    $partnerIds = [];
}
?>
<section id="webdataSection" class="webdata-section" aria-label="KPX Web Data Uploader" style="display:none; padding:1rem">
    <div class="webdata-inner">
        <h2 class="webdata-title">KPX Web Data Uploader</h2>

        <div class="webdata-filters">
            <div class="filters-left">
                <label class="wd-filter"><span>Corporate Partner</span>
                    <div class="autocomplete-field">
                        <input id="wdCompany" placeholder="Select corporate partner" autocomplete="off" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:60ch;width:min(100%,72ch);box-sizing:border-box;">
                        <ul class="autocomplete-list" id="wdCompanySuggestions" role="listbox" hidden></ul>
                        <datalist id="wdCompanyList">
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
                <label class="wd-filter"><span>Partner ID</span>
                    <input id="wdPartnerId" type="text" maxlength="4" placeholder="ID" style="padding:8px;border-radius:6px;border:1px solid #e6eef6;min-width:6ch;width:6ch;box-sizing:border-box;background:#fff;color:#111;text-align:center;">
                </label>
                <!-- Month and Year removed: date will be taken from Excel file -->
            </div>
            <div class="filters-actions">
                <button id="wdUpload" class="material-btn material-btn--primary" disabled>Upload</button>
            </div>
        </div>

        <div class="wd-dropwrap">
            <div id="wdDropzone" class="wd-dropzone wd-dropzone--disabled" tabindex="0">
                <div class="wd-drop-inner">
                    <span class="material-icons" aria-hidden="true">cloud_upload</span>
                    <p class="wd-drop-text">Drag and drop files here<br>or<br>Click to browse files</p>
                    <p class="wd-drop-hint">Supports multiple files</p>
                </div>
                <input id="wdFiles" type="file" multiple accept=".xls,.xlsx,.xlsm,.xlsb,.ods,.csv,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel.sheet.macroEnabled,application/vnd.ms-excel.sheet.binary.macroEnabled,application/vnd.oasis.opendocument.spreadsheet" style="display:none" />
            </div>

            <!-- Hidden: file list UI removed (staged files handled in confirm modal; extracted data shown in cards) -->
            <div class="wd-filelist" id="wdFileList" aria-live="polite" style="display:none">
                <div class="wd-empty">No files selected</div>
            </div>

            <!-- upload button moved to filters row -->
        </div>

        <div id="mbtcCards" class="mbtc-cards" aria-live="polite" style="margin-top:1rem"></div>

        <?php
            $modalPrefix = 'mbtc';
            include __DIR__ . '/../../../modals/data-modals/fetch-modal.php';
            include __DIR__ . '/../../../modals/data-modals/check-insert-modal.php';
        ?>
    </div>

    <script>
    (function(){
        const company = document.getElementById('wdCompany');
        const partnerIdInput = document.getElementById('wdPartnerId');
        const partners = <?= json_encode($partners, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
        const partnerIds = <?= json_encode($partnerIds, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
        const MBTC_PARTNER_NAME = 'METROBANK HEAD OFFICE';
        const WIC_PARTNER_NAME = 'WORLDCOM INTERNATIONAL COMMUNICATIONS';
        const RCBC_PARTNER_NAME = 'RCBC';
        const RIA_PARTNER_NAME = 'RIA FINANCIALS';
        const PAYPAL_PARTNER_NAME = 'PAYPAL CORPORATE';
        const XPRESSMONEY_PARTNER_NAME = 'XPRESSMONEY';
        const BDO_PARTNER_NAME = 'BDO';
        const ATINITO_PARTNER_NAME = 'ATIN ITO';
        const BANKOFCOMMERCE_PARTNER_NAME = 'BANK OF COMMERCE';
        const EZREMIT_PARTNER_NAME = 'EZREMIT';
        const VIAMERICAS_PARTNER_NAME = 'VIAMERICAS';
        const BPIREMITTANCE_PARTNER_NAME = 'BPIREMITTANCE';
        const PINOYHATIDPADALA_PARTNER_NAME = 'PINOYHATIDPADALA';
        const PLACIDEXPRESS_PARTNER_NAME = 'PLACIDEXPRESS';
        const WORLDREMIT_PARTNER_NAME = 'WORLDREMIT LIMITED';
        const KABAYANREMITTANCE_PARTNER_NAME = 'KABAYAN REMITTANCE';
        const SKYBRIDGEPAYMENTINC_PARTNER_NAME = 'SKYBRIDGE PAYMENT INC.';
        const JAPANREMITFINANCE_PARTNER_NAME = 'JAPAN REMIT FINANCE';
        const ATSERVICESLIMITED_PARTNER_NAME = 'AT SERVICES LIMITED';
        const INTELEXPRESS_PARTNER_NAME = 'INTELEXPRESS';
        const EEC_PARTNER_NAME = 'EEC/GOLDSTARINC';
        const MONEYGRAM_PARTNER_NAME = 'MONEYGRAM';

        function updatePartnerIdField(){
            if(!partnerIdInput) return;
            const selected = String(company && company.value ? company.value : '').trim();
            let id = '';
            if(selected){
                const exactName = (partners || []).find(name => String(name || '').trim().toLowerCase() === selected.toLowerCase());
                if(exactName && Object.prototype.hasOwnProperty.call(partnerIds, exactName)){
                    id = partnerIds[exactName] || '';
                }
            }
            partnerIdInput.value = id;
        }

        function findPartnerNameById(id){
            const normalizedId = String(id || '').trim();
            if(!normalizedId) return '';
            return (partners || []).find(name => {
                const partnerName = String(name || '').trim();
                const partnerId = Object.prototype.hasOwnProperty.call(partnerIds, partnerName) ? String(partnerIds[partnerName] || '').trim() : '';
                return partnerId !== '' && partnerId === normalizedId;
            }) || '';
        }

        function updateCompanyFromPartnerId(){
            if(!partnerIdInput || !company) return;
            const normalizedId = String(partnerIdInput.value || '').trim();
            if(normalizedId === ''){
                if(String(company.value || '') === '') return;
                company.value = '';
                company.dispatchEvent(new Event('input', { bubbles: true }));
                company.dispatchEvent(new Event('change', { bubbles: true }));
                return;
            }
            const partnerName = findPartnerNameById(normalizedId);
            if(!partnerName) return;
            if(String(company.value || '').trim() === partnerName) return;
            company.value = partnerName;
            company.dispatchEvent(new Event('input', { bubbles: true }));
            company.dispatchEvent(new Event('change', { bubbles: true }));
        }

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
                input.dispatchEvent(new Event('input', { bubbles: true }));
                closeSuggestions();
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

        function isWorldInternationalCommunications(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'WIC' || normalized === WIC_PARTNER_NAME;
        }

        function isRcbc(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'RCBC' || normalized === RCBC_PARTNER_NAME;
        }

        function isRia(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'RIA' || normalized === RIA_PARTNER_NAME || normalized === 'RIA FINANCIALS INC';
        }

        function isPaypal(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'PAYPAL' || normalized === PAYPAL_PARTNER_NAME || normalized === 'PAYPAL PH' || normalized === 'PAYPAL INC';
        }

        function isXpressmoney(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'XPRESSMONEY' || normalized === XPRESSMONEY_PARTNER_NAME || normalized === 'XPRESS MONEY' || normalized === 'XPRESSMONEY CORPORATE';
        }

         function isAtinito(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'ATINITO' || normalized === ATINITO_PARTNER_NAME || normalized === 'ATIN ITO' || normalized === 'ATINITO ITO';
        }

         function isBdo(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'BDO' || normalized === BDO_PARTNER_NAME;
        }

        function isBankOfCommerce(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'BANK OF COMMERCE' || normalized === BANKOFCOMMERCE_PARTNER_NAME;
        }

         function isViamericas(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'VIAMERICAS' || normalized === VIAMERICAS_PARTNER_NAME;
        }

        function isBpiremittance(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'BPIREMITTANCE' || normalized === BPIREMITTANCE_PARTNER_NAME || normalized === 'BPI REMITTANCE' || normalized === 'BPI-REMITTANCE';
        }

        function isIntelexpress(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'INTELEXPRESS' || normalized === 'INTEL EXPRESS' || normalized === INTELEXPRESS_PARTNER_NAME;
        }

        function isEzremit(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'EZREMIT' || normalized === EZREMIT_PARTNER_NAME || normalized === 'EZ REMIT' || normalized === 'EZ-REMIT';
        }

        function isPinoyhatidpadala(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'PINOYHATIDPADALA' || normalized === PINOYHATIDPADALA_PARTNER_NAME || normalized === 'PINOY HATID PADALA' || normalized === 'PINOYHATIDPADALA CORPORATE';
        }

        function isPlacidexpress(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'PLACIDEXPRESS' || normalized === PLACIDEXPRESS_PARTNER_NAME || normalized === 'PLACID EXPRESS' || normalized === 'PLACIDEXPRESS CORPORATE';
        }

        function isWorldRemitLimited(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'WORLDREMIT' || normalized === 'WORLDREMITLIMITED' || normalized === WORLDREMIT_PARTNER_NAME || normalized === 'WORLDREMIT LIMITED' || normalized === 'WORLDREMIT LTD';
        }

        function isKabayanRemittance(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'KABAYANREMITTANCE' || normalized === KABAYANREMITTANCE_PARTNER_NAME;
        }
        
        function isSkybridgePaymentInc(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'SKYBRIDGEPAYMENTINC' || normalized === SKYBRIDGEPAYMENTINC_PARTNER_NAME || normalized === 'SKYBRIDGE PAYMENT INC.' || normalized === 'SKYBRIDGEPAYMENTINC CORPORATE';
        }

        function isJapanRemitFinance(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'JAPANREMITFINANCE' || normalized === 'JAPANREMITFINANCE' || normalized === JAPANREMITFINANCE_PARTNER_NAME || normalized === 'JAPANREMIT FINANCE';
        }

        function isAtserviceslimited(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'ATSERVICESLIMITED' || normalized === ATSERVICESLIMITED_PARTNER_NAME || normalized === 'AT SERVICES LIMITED';
        }

        function isEEC(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'EEC' || normalized === EEC_PARTNER_NAME || normalized === 'EEC/GOLDSTAR INC';
        }

        function isMoneygram(name){
            const normalized = String(name || '').trim().toUpperCase();
            return normalized === 'MONEYGRAM' || normalized === MONEYGRAM_PARTNER_NAME;
        }

        const UPLOADER_TEMPLATE_SIGNATURES = {
            moneygram: {
                partnerFilenameTokens: ['SETTLEMENTDETAIL', 'SETTLEMENT DETAIL', 'SETTLEMENT DETAILS', 'DAILYACTIVITY', 'DAILY ACTIVITY'],
                webFilenameTokens: ['SENDOUT', 'SEND OUT', 'TRANSACTION-REPORT-ALL', 'PAYOUT', 'PAY OUT', 'CLAIMED'],
                partnerHeaders: ['ACCOUNT NUMBER','AGENT NAME','LEGACY ID','TRAN DATE','TRANSACTION ID','REFERENCE ID','PRODUCT','TRAN TYPE','ORIG CNTRY','RCV CNTRY'],
                webHeaders: ['NO','CONTROL SERIES NO','DATE CLAIMED','DATE SEND','KPTN','CCREF NO','CURRENCY','AMOUNT']
            }
        };

        function normalizeHeaderLabel(value){
            return String(value || '').trim().toUpperCase().replace(/[_\-\/]+/g, ' ').replace(/\s+/g, ' ');
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

        function includesAnyToken(text, tokens){
            if(!text || !Array.isArray(tokens)) return false;
            const up = String(text).toUpperCase();
            return tokens.some(token => up.includes(String(token || '').toUpperCase()));
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

        function classifyForWebUploader(payload, fileNameHint){
            if(!isMoneygram(company.value)) return { accepted: true, type: 'other' };
            const type = classifyMoneygramFileType(payload, fileNameHint);
            if(type === 'partner') return { accepted: false, type, message: 'Invalid File Format' };
            return { accepted: true, type };
        }

        function isSendoutPayload(payload){
            if(!payload) return false;
            const filename = String(payload.filename || '').toUpperCase();
            const reportTitle = [payload.title, payload.reportTitle, payload.report_title, payload.sheetName, payload.sheet_name]
                .map(value => String(value || '').trim())
                .filter(Boolean)
                .join(' ')
                .toUpperCase();
            const hasSendoutWord = filename.includes('SENDOUT') || filename.includes('SEND OUT') || reportTitle.includes('SENDOUT') || reportTitle.includes('SEND OUT');
            if(hasSendoutWord) return true;

            const rows = Array.isArray(payload.rows) ? payload.rows : [];
            const sample = rows.find(row => row && typeof row === 'object');
            if(!sample) return false;
            const keys = Object.keys(sample).map(k => normalizeHeaderLabel(k));
            const hasDateSend = keys.some(k => k.includes('DATE SEND'));
            const hasControlSeries = keys.some(k => k.includes('CONTROL SERIES NO'));
            const hasCharge = keys.some(k => k === 'CHARGE' || k.includes('CHARGE'));
            const hasReceiverCountry = keys.some(k => k.includes('RECEIVER COUNTRY'));
            return hasDateSend && (hasControlSeries || hasCharge || hasReceiverCountry);
        }

        function isMoneygramSettlementPayload(payload){
            if(!isMoneygram(company.value) || !payload) return false;
            return classifyMoneygramFileType(payload) === 'partner';
        }

        function resolveWebCompanyKey(name){
            if(isMetrobankHeadOffice(name)) return 'mbtc';
            if(isWorldRemitLimited(name)) return 'worldremitlimited';
            if(isJapanRemitFinance(name)) return 'japanremitfinance';
            if(isWorldInternationalCommunications(name)) return 'wic';
            if(isRcbc(name)) return 'rcbc';
            if(isRia(name)) return 'riafinancials';
            if(isPaypal(name)) return 'paypal';
            if(isXpressmoney(name)) return 'xpressmoney';
            if(isAtinito(name)) return 'atinito';
            if(isBdo(name)) return 'bdo';
            if(isIntelexpress(name)) return 'intelexpress';
            if(isBankOfCommerce(name)) return 'bankofcommerce';
            if(isViamericas(name)) return 'viamericas';
            if(isPlacidexpress(name)) return 'placidexpress';
            if(isEzremit(name)) return 'ezremit';
            if(isPinoyhatidpadala(name)) return 'pinoyhatidpadala';
            if(isAtserviceslimited(name)) return 'atserviceslimited';
            if(isBpiremittance(name)) return 'bpiremittance';
            if(isKabayanRemittance(name)) return 'kabayanremittance';
            if(isSkybridgePaymentInc(name)) return 'skybridgepaymentinc';
            if(isEEC(name)) return 'eec';
            if(isMoneygram(name)) return 'moneygram';
            return '';
        }
        // partner selection uses a datalist (`wdCompany` input)
        const dropzone = document.getElementById('wdDropzone');
        // force pointer cursor so users can click to open file picker even when visually disabled
        if(dropzone) dropzone.style.cursor = 'pointer';
        const fileInput = document.getElementById('wdFiles');
        const fileListEl = document.getElementById('wdFileList');
        const uploadBtn = document.getElementById('wdUpload');
        const cardsEl = document.getElementById('mbtcCards');
        const overlayEl = document.getElementById('mbtcOverlay');
        const progressBar = document.getElementById('mbtcProgressBar');
        const progressText = document.getElementById('mbtcProgressText');
        const cancelBtn = document.getElementById('mbtcCancelBtn');
        
        let files = [];
        let isUploading = false;
        let uploadCancelled = false;
        let uploadController = null;
        let progressTimer = null;
        let uploadSessionId = 0;
        const nativeFetch = window.fetch.bind(window);
        const processedByCompany = Object.create(null); // company -> [{id, filename, dateStr, rows}]

        function isUploadAbortError(err){
            return !!(err && (err.name === 'AbortError' || err.code === 20));
        }

        function createUploadAbortError(){
            try{ return new DOMException('Upload cancelled', 'AbortError'); }
            catch(e){ const err = new Error('Upload cancelled'); err.name = 'AbortError'; return err; }
        }

        function throwIfUploadCancelled(){
            if(uploadCancelled || (uploadController && uploadController.signal.aborted)) throw createUploadAbortError();
        }

        function startUploadRequest(){
            if(uploadController){
                try{ uploadController.abort(); }catch(e){}
            }
            uploadController = new AbortController();
            isUploading = true;
            uploadCancelled = false;
            uploadSessionId += 1;
            return uploadSessionId;
        }

        function stopProgressTimer(){
            if(progressTimer){
                clearInterval(progressTimer);
                progressTimer = null;
            }
        }

        function resetProcessingProgress(){
            if(progressBar) progressBar.style.width = '0%';
            if(progressText) progressText.textContent = 'Analyzing 0 of 0 files';
        }

        function finishUploadRequest(sessionId){
            if(sessionId && sessionId !== uploadSessionId) return;
            uploadController = null;
            isUploading = false;
            uploadCancelled = false;
            stopProgressTimer();
        }

        function cancelUploadRequest(){
            uploadCancelled = true;
            isUploading = false;
            if(uploadController){
                try{ uploadController.abort(); }catch(e){}
            }
            uploadController = null;
            _processing = false;
            clearPendingWebUploads();
            stopProgressTimer();
            hideProcessingOverlay();
            resetProcessingProgress();
            refreshState();
        }

        async function fetch(input, init){
            throwIfUploadCancelled();
            const options = Object.assign({}, init || {});
            if(uploadController && !options.signal) options.signal = uploadController.signal;
            return await nativeFetch(input, options);
        }

        function getCompanyKey(){
            return ((company && company.value) ? company.value : '').trim().toUpperCase();
        }

        function getProcessedList(key){
            const resolved = (typeof key === 'string' ? key : getCompanyKey());
            if(!resolved) return [];
            if(!processedByCompany[resolved]) processedByCompany[resolved] = [];
            return processedByCompany[resolved];
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

        function countPendingWebUploads(){
            if(isUploading || _processing) return Math.max(1, files.length);
            let pending = files.length;
            Object.keys(processedByCompany).forEach(function(key){
                pending += (processedByCompany[key] || []).filter(function(item){
                    return item && !item._uploaded;
                }).length;
            });
            return pending;
        }

        function getPendingProcessedList(){
            return getProcessedList().filter(function(item){
                return item && !item._uploaded;
            });
        }

        function clearPendingWebUploads(){
            files = [];
            if(fileInput) fileInput.value = '';
            Object.keys(processedByCompany).forEach(function(key){
                processedByCompany[key] = (processedByCompany[key] || []).filter(function(item){
                    return item && item._uploaded;
                });
            });
            refreshState();
            updateCards();
        }

        window.AutoReconUploadPending = window.AutoReconUploadPending || {};
        window.AutoReconUploadPending.web = {
            label: 'KPX Web Data Uploader',
            count: countPendingWebUploads,
            clear: clearPendingWebUploads
        };

        // inject small stylesheet for icon buttons, card hover and modal styles
        (function(){
            const css = `
            .icon-btn{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:6px;background:transparent;border:1px solid transparent;cursor:pointer;padding:0;margin:0}
            .icon-btn .material-icons{font-size:18px;line-height:18px;color:#333}
            .icon-btn:hover{background:#f5f5f5;border-color:#e6e6e6;transform:translateY(-1px);transition:all .12s ease}
            .icon-btn:disabled{opacity:.35;cursor:not-allowed;transform:none;pointer-events:none}
            .icon-btn.view:hover{background:#e8f5e9;border-color:#cdeacb}
            .icon-btn.delete:hover{background:#fff0f0;border-color:#f6c7c7}
            .upload-status{display:inline-flex;align-items:center;gap:4px;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:600;line-height:1;border:1px solid #d9dfe8;color:#4b5563;background:#f8fafc}
            .upload-status .material-icons{font-size:14px;line-height:14px}
            .upload-status.is-uploaded{color:#166534;background:#ecfdf5;border-color:#bbf7d0}
            .mbtc-card{transition:all .12s ease;border-radius:8px}
            .mbtc-card:hover{background:#f0f7ff;transform:translateY(-3px);box-shadow:0 6px 18px rgba(33,150,243,0.06)}
            `;
            try{ const s=document.createElement('style'); s.appendChild(document.createTextNode(css)); document.head.appendChild(s); }catch(e){}
        })();

        function refreshState(){
            const ready = !!company.value;
            const currentProcessed = getPendingProcessedList();
            if(ready){
                dropzone.classList.remove('wd-dropzone--disabled');
            } else {
                dropzone.classList.add('wd-dropzone--disabled');
            }
            // enable Upload when company selected AND either staged files exist OR pending processed payloads exist
            uploadBtn.disabled = isUploading || _processing || !(ready && (files.length>0 || currentProcessed.length>0));
            renderFileList();
        }

        function renderFileList(){
            // Show staged files (before fetch). After fetch, extracted data is shown in the cards area.
            fileListEl.innerHTML = '';
            if(files && files.length>0){
                const header = document.createElement('div');
                header.className = 'wd-filecount';
                header.textContent = files.length + ' file' + (files.length>1?'s selected':' selected');
                fileListEl.appendChild(header);
                const ul = document.createElement('ul');
                ul.className = 'wd-files-ul';
                files.forEach((f,i)=>{
                    const li = document.createElement('li');
                    li.className = 'wd-file-item';
                    li.innerHTML = '<span class="name">'+escapeHtml(f.name)+'</span> <button class="wd-remove" data-index="'+i+'" style="float:right">Remove</button>';
                    ul.appendChild(li);
                });
                fileListEl.appendChild(ul);
                return;
            }
            // no staged files
            fileListEl.innerHTML = '<div class="wd-empty">No files selected</div>';
        }

        function escapeHtml(s){ return (s+'').replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

        // Click to open file picker
        // Always allow clicking/pressing Enter to open file picker (even when visually disabled)
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

        // file input change
        fileInput.addEventListener('change', function(e){ if(e.target.files && e.target.files.length){
                // require company selection first
                if(!company.value){
                    showSelectCompanyModal();
                    e.target.value = '';
                    return;
                }
                files = dedupeFilesByName(files.concat(Array.from(e.target.files)));
                refreshState();
                // if mapped companies are selected, process immediately without preview modal
                if(resolveWebCompanyKey(company.value)){
                    const excelFiles = files.filter(isExcelFile);
                    if(excelFiles.length) processMbtcFiles(excelFiles);
                }
            } });

        // drag/drop
        ['dragenter','dragover'].forEach(ev=>{
            dropzone.addEventListener(ev, function(e){ e.preventDefault(); e.stopPropagation(); if(!dropzone.classList.contains('wd-dropzone--disabled')) dropzone.classList.add('wd-dropzone--over'); });
        });
        ['dragleave','drop'].forEach(ev=>{
            dropzone.addEventListener(ev, function(e){ e.preventDefault(); e.stopPropagation(); dropzone.classList.remove('wd-dropzone--over'); });
        });
        dropzone.addEventListener('drop', function(e){
            const dt = e.dataTransfer;
            // if no company selected, instruct user
            if(!company.value){
                e.preventDefault(); e.stopPropagation();
                showSelectCompanyModal();
                return;
            }
            if(dt && dt.files && dt.files.length){
                files = dedupeFilesByName(files.concat(Array.from(dt.files)));
                refreshState();
                // if mapped companies are selected, process immediately without preview modal
                if(resolveWebCompanyKey(company.value)){
                    const excelFiles = files.filter(isExcelFile);
                    if(excelFiles.length) processMbtcFiles(excelFiles);
                }
            }
        });

        // file list click handler: support removing staged files and viewing extracted payloads
        fileListEl.addEventListener('click', function(e){
            const viewBtn = e.target.closest && e.target.closest('.wd-view');
            if(viewBtn){
                const idx = parseInt(viewBtn.dataset.index,10);
                if(!Number.isNaN(idx)){
                    const payload = processed[idx];
                    if(payload) openViewer(payload);
                }
                return;
            }
            const removeBtn = e.target.closest && e.target.closest('.wd-remove');
            if(removeBtn){
                const idx = parseInt(removeBtn.dataset.index,10);
                if(!Number.isNaN(idx)){
                    files.splice(idx,1);
                    fileInput.value = '';
                    refreshState();
                }
                return;
            }
        });

        // filter changes (only company remains)
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
                    const rawDate = (r['DATE CLAIMED'] || r['DATE SEND'] || r['DATE_SEND'] || r['date_send'] || pl.dateStr || '');
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

            const endpoint = window.autoreconBaseUrl + '/src/controllers/recon/check_locked_reconciliation_dates.php';
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

        // upload click - MBTC insert flow
        uploadBtn.addEventListener('click', async function(){
            const currentProcessed = getPendingProcessedList();
            console.log('[mbtc] Upload button clicked', { company: company.value, stagedFiles: files.length, processed: currentProcessed.length });
            if(uploadBtn.disabled) return;
            const activeUploadSession = startUploadRequest();
            refreshState();
            try{
            if(resolveWebCompanyKey(company.value)){
                // If files were already processed (extracted) use `processed` payloads for insertion
                let payloads = [];
                let excelFiles = files.filter(isExcelFile);
                if(currentProcessed.length > 0){
                    payloads = currentProcessed.slice();
                    // show processing overlay for already-extracted payloads
                    try{ showProcessingOverlay(payloads.length || 1); progressText.textContent = 'Preparing upload...'; }catch(e){}
                } else if(excelFiles.length > 0){
                    // gather payloads by extracting each file
                    showProcessingOverlay(excelFiles.length);
                    progressText.textContent = 'Checking files for existing data...';
                    let idx = 0;
                    for(const f of excelFiles){
                        throwIfUploadCancelled();
                        idx++;
                        progressText.textContent = 'Extracting file ' + idx + ' of ' + excelFiles.length + '...';
                        updateProcessing(idx-1, excelFiles.length);
                        try{
                            const precheck = classifyForWebUploader(null, f.name);
                            if(!precheck.accepted){
                                await showAlert(precheck.message || 'Invalid File Format', 'Notice');
                                continue;
                            }
                            const res = await uploadToMbtc(f);
                            throwIfUploadCancelled();
                            if(res && res.success){
                                const classification = classifyForWebUploader(res.payload, f.name);
                                if(!classification.accepted){ await showAlert(classification.message || 'Invalid File Format', 'Notice'); continue; }
                                payloads.push(res.payload);
                            }
                            else { console.warn('Extraction failed for', f.name, res); }
                        }catch(err){ if(isUploadAbortError(err)) throw err; console.error('Extract error', err); }
                    }
                    updateProcessing(excelFiles.length, excelFiles.length);
                } else {
                        await showAlert('No Excel files to upload or processed.');
                    return;
                }

                if(payloads.length === 0){
                    hideProcessingOverlay();
                    return;
                }

                try {
                    throwIfUploadCancelled();
                    const lockCheck = await enforceLockedReconciliationDateCheck(company.value, payloads);
                    throwIfUploadCancelled();
                    if(lockCheck && lockCheck.error){
                        await showAlert(lockCheck.error, 'Notice');
                        hideProcessingOverlay();
                        return;
                    }
                    if(lockCheck && lockCheck.blocked){
                        await showAlert('Upload Blocked. Some transaction dates are already locked by reconciliation.', 'Notice');
                        hideProcessingOverlay();
                        return;
                    }
                } catch (e) {
                    if(isUploadAbortError(e)) throw e;
                    await showAlert('Failed to validate locked reconciliation dates.', 'Notice');
                    hideProcessingOverlay();
                    return;
                }

                // build unique pairs for duplicate check
                const pairMap = new Map();
                payloads.forEach(pl=>{
                    if(isSendoutPayload(pl)) return;
                    (pl.rows||[]).forEach(r=>{
                        const ccref = (r['CCREF NO']||'').toString().trim();
                        // prefer row-level DATE CLAIMED, fallback to file-level dateStr
                        const rawDate = (r['DATE CLAIMED'] || pl.dateStr || '');
                        const date_claimed = normalizeClientDate(rawDate);
                        if(ccref){ pairMap.set(ccref + '|' + date_claimed, { ccref_no: ccref, date_claimed }); }
                    });
                });
                const pairs = Array.from(pairMap.values());

                // perform duplicate check per file so progress is file-based
                const totalFiles = payloads.length || 0;
                progressBar.style.width = '0%';
                progressText.textContent = 'Checking data for duplicates: ' + (totalFiles > 0 ? 1 : 0) + ' of ' + totalFiles;
                try{
                    // Use unified ml-web-data endpoint for all partners
                    const url = window.autoreconBaseUrl + '/src/controllers/excelcontrol/ml-web-data-insert.php';
                    const allDuplicates = [];
                    const sendoutDuplicates = [];
                    for(let i=0;i<totalFiles;i++){
                        throwIfUploadCancelled();
                        const pl = payloads[i];
                        const sendoutPayload = isSendoutPayload(pl);
                        if(sendoutPayload){
                            // Build per-file pairs for SENDOUT (ccref_no + date_send)
                            const filePairs = [];
                            (pl.rows||[]).forEach(r=>{
                                const ccref = (r['CCREF NO']||'').toString().trim();
                                const rawDate = (r['DATE SEND'] || r['DATE_SEND'] || r['date_send'] || pl.dateStr || '');
                                const date_send = normalizeClientDate(rawDate);
                                if(ccref) filePairs.push({ partnerName: company.value, ccref_no: ccref, date_send });
                            });
                            if(filePairs.length === 0){
                                const pct = Math.round(((i+1)/Math.max(1,totalFiles)) * 45);
                                progressBar.style.width = pct + '%';
                                progressText.textContent = 'Checking data for duplicates: ' + (i+1) + ' of ' + totalFiles;
                                continue;
                            }

                            try{
                                const chkResRaw = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'check_sendout', pairs: filePairs }) });
                                const chkTxt = await chkResRaw.text();
                                throwIfUploadCancelled();
                                let chk;
                                try{ chk = JSON.parse(chkTxt); }catch(e){ console.error('Sendout duplicate check returned non-JSON', chkTxt); }
                                if(chkResRaw.ok && chk && Array.isArray(chk.duplicates) && chk.duplicates.length>0){
                                    sendoutDuplicates.push(...chk.duplicates);
                                }
                            }catch(e){ if(isUploadAbortError(e)) throw e; console.error('Sendout duplicate check failed', e); }

                            const pct = Math.round(((i+1)/Math.max(1,totalFiles)) * 45);
                            progressBar.style.width = pct + '%';
                            progressText.textContent = 'Checking data for duplicates: ' + (i+1) + ' of ' + totalFiles;
                            continue;
                        }
                        // build pairs for this file only
                        const filePairs = [];
                        (pl.rows||[]).forEach(r=>{
                            const ccref = (r['CCREF NO']||'').toString().trim();
                            const rawDate = (r['DATE CLAIMED'] || pl.dateStr || '');
                            const date_claimed = normalizeClientDate(rawDate);
                            if(ccref) filePairs.push({ partnerName: company.value, ccref_no: ccref, date_claimed });
                        });
                        if(filePairs.length === 0){
                            // advance file progress even when file has no pairs
                            const pct = Math.round(((i+1)/Math.max(1,totalFiles)) * 45);
                            progressBar.style.width = pct + '%';
                            progressText.textContent = 'Checking data for duplicates: ' + (i+1) + ' of ' + totalFiles;
                            continue;
                        }
                        const chkResRaw = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'check', pairs: filePairs }) });
                        const chkTxt = await chkResRaw.text();
                        throwIfUploadCancelled();
                        let chk;
                        try{ chk = JSON.parse(chkTxt); }catch(e){ console.error('Duplicate check returned non-JSON', chkTxt); await showAlert('Duplicate check failed: '+chkTxt); hideProcessingOverlay(); return; }
                        if(!chkResRaw.ok || !(chk && chk.success)){
                            await showAlert('Duplicate check failed: ' + (chk && chk.error ? chk.error : 'unknown'));
                            hideProcessingOverlay();
                            return;
                        }
                        if(Array.isArray(chk.duplicates) && chk.duplicates.length>0){ allDuplicates.push(...chk.duplicates); }
                        const pct = Math.round(((i+1)/Math.max(1,totalFiles)) * 45);
                        progressBar.style.width = pct + '%';
                        progressText.textContent = 'Checking data for duplicates: ' + (i+1) + ' of ' + totalFiles;
                    }

                    // if SENDOUT duplicates found, ask user once and delete if confirmed
                    if(typeof sendoutDuplicates !== 'undefined' && sendoutDuplicates.length>0){
                        const msgSendout = 'Data with the same CCREF NO and DATE SEND already exists.\nDo you want to overwrite the existing data?';
                        const okSendout = await showConfirm(msgSendout);
                        throwIfUploadCancelled();
                        if(!okSendout){ hideProcessingOverlay(); return; }
                        const delCountSendout = sendoutDuplicates.length;
                        progressBar.style.width = '55%';
                        progressText.textContent = 'Deleting existing records: 0 of ' + delCountSendout;
                        try{
                            const delResRawSendout = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'delete_sendout', pairs: sendoutDuplicates.map(d=>({ partnerName: d.partnerName || company.value, ccref_no: d.ccref_no, date_send: d.date_send })) }) });
                            const delTxtSendout = await delResRawSendout.text();
                            throwIfUploadCancelled();
                            let delSendout;
                            try{ delSendout = JSON.parse(delTxtSendout); }catch(e){ console.error('Sendout delete returned non-JSON', delTxtSendout); await showAlert('Delete failed: '+delTxtSendout); hideProcessingOverlay(); return; }
                            if(!delResRawSendout.ok || !(delSendout && delSendout.success)){ await showAlert('Delete failed: ' + (delSendout && delSendout.error ? delSendout.error : 'unknown')); hideProcessingOverlay(); return; }
                            progressBar.style.width = '70%';
                            const deletedCount = (delSendout && delSendout.deleted) ? delSendout.deleted : delCountSendout;
                            progressText.textContent = 'Deleting existing records: ' + deletedCount + ' of ' + delCountSendout;
                        }catch(e){ if(isUploadAbortError(e)) throw e; console.error('Sendout delete failed', e); await showAlert('Delete failed: ' + (e && e.message)); hideProcessingOverlay(); return; }
                    }

                    // if duplicates found in ml_web_data, ask user once and delete if confirmed
                    if(allDuplicates.length>0){
                        const msg = 'Data with the same CCREF NO and DATE CLAIMED already exists.\nDo you want to overwrite the existing data?';
                        const ok = await showConfirm(msg);
                        throwIfUploadCancelled();
                        if(!ok){ hideProcessingOverlay(); return; }
                        // perform delete (send all duplicate pairs)
                        const delCount = allDuplicates.length;
                        progressBar.style.width = '55%';
                        progressText.textContent = 'Deleting existing records: 0 of ' + delCount;
                        console.log('[mbtc] deleting duplicates', delCount);
                        const delResRaw = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'delete', pairs: allDuplicates.map(d => ({
  // FIX: Map duplicate records to extract partner name and claim details
// The partner name defaults to company.value if d.partnerName is null/undefined
// This resolves the duplicate entry integrity constraint violation by properly 
// associating each duplicate with its corresponding partner information
                        partnerName: d.partnerName || company.value,
  ccref_no: d.ccref_no,
  date_claimed: d.date_claimed
                        })) }) });
                        const delTxt = await delResRaw.text();
                        throwIfUploadCancelled();
                        let del;
                        try{ del = JSON.parse(delTxt); }catch(e){ console.error('Delete returned non-JSON', delTxt); await showAlert('Delete failed: '+delTxt); hideProcessingOverlay(); return; }
                        if(!delResRaw.ok || !(del && del.success)){
                            await showAlert('Delete failed: ' + (del && del.error ? del.error : 'unknown'));
                            hideProcessingOverlay();
                            return;
                        }
                        // reflect deleted count
                        progressBar.style.width = '70%';
                        const deletedCount = (del && del.deleted) ? del.deleted : delCount;
                        progressText.textContent = 'Deleting existing records: ' + deletedCount + ' of ' + delCount;
                    }

                    // perform insert per file so progress is file-based
                    const totalInsertFiles = payloads.length;
                    let totalInserted = 0;
                    let totalInsertedRegular = 0;
                    let totalInsertedSendout = 0;
                    let hasMissingMoneygramLegacyId = false;
                    let missingMoneygramLegacyBranches = [];
                    for(let i=0;i<totalInsertFiles;i++){
                        throwIfUploadCancelled();
                        const pl = payloads[i];
                        progressBar.style.width = Math.round(75 + ((i)/Math.max(1,totalInsertFiles))*25) + '%';
                        progressText.textContent = 'Inserting files: ' + (totalInsertFiles > 0 ? i + 1 : 0) + ' of ' + totalInsertFiles;
                        const insResRaw = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'insert_web', company: company.value, payloads: [pl] }) });
                        const insTxt = await insResRaw.text();
                        throwIfUploadCancelled();
                        let ins;
                        try{ ins = JSON.parse(insTxt); }catch(e){ console.error('Insert returned non-JSON', insTxt); await showAlert('Insert failed: '+insTxt); hideProcessingOverlay(); return; }
                        if(!insResRaw.ok || !(ins && ins.success)){
                            await showAlert('Insert failed: ' + (ins && ins.error ? ins.error : 'unknown'));
                            hideProcessingOverlay();
                            return;
                        }
                        totalInserted += Number(ins.inserted || 0);
                        totalInsertedRegular += Number(ins.inserted_regular || 0);
                        totalInsertedSendout += Number(ins.inserted_sendout || 0);
                        if(ins.moneygram_has_missing_legacy === true){
                            hasMissingMoneygramLegacyId = true;
                            if(Array.isArray(ins.moneygram_missing_legacy_branches)){
                                missingMoneygramLegacyBranches.push(...ins.moneygram_missing_legacy_branches);
                            }
                        }
                        // update per-file insert progress
                        progressBar.style.width = Math.round(75 + ((i+1)/Math.max(1,totalInsertFiles))*25) + '%';
                        progressText.textContent = 'Inserting files: ' + (i+1) + ' of ' + totalInsertFiles;
                    }

                    // all done: update UI and cards
                    addUniqueProcessed(payloads);
                    markPayloadsUploaded(payloads, true);
                    updateCards();
                    if(excelFiles.length>0){ files = files.filter(ff=>!excelFiles.includes(ff)); fileInput.value = ''; }
                    refreshState();
                    throwIfUploadCancelled();
                    // notify user and refresh on confirmation
                    try{
                        await showAlert('Successfully uploaded.', 'Success');
                        if(hasMissingMoneygramLegacyId){
                            await showMoneygramLegacyIdNotice(missingMoneygramLegacyBranches);
                        }
                    }catch(e){}
                    // keep user on the current section after upload

                }catch(e){ if(!isUploadAbortError(e)){ console.error(e); await showAlert('Upload failed: '+ (e && e.message)); } }
                hideProcessingOverlay();
                return;
            }
            console.log('[webdata] upload simulated', {company: company.value, files: files.map(f=>f.name)});
            try{ await showAlert('Upload simulated: '+files.length+' file(s)'); } catch(e){}
            files = [];
            fileInput.value = '';
            refreshState();
            } catch(e) {
                if(!isUploadAbortError(e)){
                    console.error(e);
                    await showAlert('Upload failed: ' + (e && e.message ? e.message : 'Unknown error'));
                }
            } finally {
                finishUploadRequest(activeUploadSession);
                refreshState();
            }
        });

        function isExcelFile(f){
            const name = (f.name||'').toLowerCase();
            return name.endsWith('.xls') || name.endsWith('.xlsx') || name.endsWith('.xlsm') || name.endsWith('.xlsb') || name.endsWith('.ods') || name.endsWith('.csv');
        }

        // Try to normalize various date strings to `YYYY-MM-DD HH:MM:SS` for duplicate checks
        function normalizeClientDate(raw){
            if(raw === null || raw === undefined) return '';
            let s = (''+raw).trim();
            if(s === '') return '';
            // If it's a pure number (Excel serial), convert using JS date base (Excel serial 25569 -> 1970-01-01)
            if(/^[0-9]+(\.[0-9]+)?$/.test(s)){
                // Excel serial -> days since 1899-12-30
                const serial = parseFloat(s);
                if(!isNaN(serial)){
                    const epoch = new Date(Date.UTC(1899,11,30));
                    const ms = serial * 24 * 60 * 60 * 1000;
                    const dt = new Date(epoch.getTime() + ms);
                    if(!isNaN(dt.getTime())){
                        return dt.getFullYear() + '-' + String(dt.getMonth()+1).padStart(2,'0') + '-' + String(dt.getDate()).padStart(2,'0') + ' ' + String(dt.getHours()).padStart(2,'0') + ':' + String(dt.getMinutes()).padStart(2,'0') + ':' + String(dt.getSeconds()).padStart(2,'0');
                    }
                }
            }
            // Try native Date parse
            const d = new Date(s);
            if(!isNaN(d.getTime())){
                return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0') + ' ' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0') + ':' + String(d.getSeconds()).padStart(2,'0');
            }
            // fallback: return raw string (server will try to parse)
            return s;
        }

        // Format numbers with thousands separators (e.g., 3254 -> 3,254)
        function formatNumber(n){
            if(n === null || n === undefined) return '';
            const num = Number(n);
            if(isNaN(num)) return String(n);
            try{ return num.toLocaleString('en-US'); }catch(e){ return String(num); }
        }

        async function uploadToMbtc(file){
            // use absolute path from site root to avoid 404 when page is in subfolder
            const endpointDir = resolveWebCompanyKey(company.value);
            if(!endpointDir) return { success:false, error:'Unsupported company selected' };
            const url = window.autoreconBaseUrl + '/src/controllers/excelcontrol/' + endpointDir + '/' + endpointDir + '-webdata.php';
            const fd = new FormData();
            fd.append('file', file);
            fd.append('filename', file.name);
            const r = await fetch(url, { method: 'POST', body: fd });
            // read body once
            const txt = await r.text();
            if(!r.ok){
                console.error('Upload failed', r.status, txt);
                return { success:false, error:'HTTP '+r.status, raw: txt };
            }
            const ct = r.headers.get('content-type') || '';
            // try JSON parse regardless of header if body looks like JSON
            const maybeJson = (ct.indexOf('application/json') !== -1) || (/^[\s\r\n]*[\{\[]/.test(txt));
            if(maybeJson){
                try{ const json = JSON.parse(txt); return json; } catch(e){ console.error('Invalid JSON response', txt); return { success:false, error:'Invalid JSON', raw: txt }; }
            }
            console.error('Expected JSON response, got:', ct, txt);
            return { success:false, error:'Non-JSON response', raw: txt };
        }

        let _processing = false;
        async function processMbtcFiles(excelFiles){
            if(_processing) return;
            _processing = true;
            const activeUploadSession = startUploadRequest();
            refreshState();
            const targetCompanyKey = getCompanyKey();
            showProcessingOverlay(excelFiles.length);
            let done = 0;
            try{
                for(const f of excelFiles.slice()){
                    throwIfUploadCancelled();
                    try{
                        const precheck = classifyForWebUploader(null, f.name);
                        if(!precheck.accepted){
                            try{ await showAlert(precheck.message || 'Invalid File Format', 'Notice'); }catch(e){}
                            throwIfUploadCancelled();
                            done++;
                            updateProcessing(done, excelFiles.length);
                            continue;
                        }
                        const res = await uploadToMbtc(f);
                        throwIfUploadCancelled();
                        if(res && res.success){
                            const pl = res.payload;
                            const classification = classifyForWebUploader(pl, f.name);
                            if(!classification.accepted){
                                try{ await showAlert(classification.message || 'Invalid File Format', 'Notice'); }catch(e){}
                                throwIfUploadCancelled();
                            } else {
                                const list = getProcessedList(targetCompanyKey);
                                const exists = list.find(x=> (x.filename===pl.filename && x.dateStr===pl.dateStr));
                                if(!exists){
                                    if(typeof pl._uploaded === 'undefined') pl._uploaded = false;
                                    list.push(pl);
                                    updateCards();
                                } else if(exists._uploaded){
                                    // File was already successfully uploaded — allow re-uploading it as a new entry
                                    pl._uploaded = false;
                                    list.push(pl);
                                    updateCards();
                                }
                            }
                        } else if(res && res.locked){
                            try{ await showAlert(res.message || res.error || 'Upload blocked.', 'Notice'); }catch(e){}
                            throwIfUploadCancelled();
                            hideProcessingOverlay();
                            return;
                        } else {
                            console.error('Processing failed', res);
                            if(res && res.error){
                                try{ await showAlert(res.error, 'Notice'); }catch(e){}
                                throwIfUploadCancelled();
                            }
                        }
                    }catch(err){
                        if(isUploadAbortError(err)) throw err;
                        console.error(err);
                    }

                    done++;
                    updateProcessing(done, excelFiles.length);
                }
                hideProcessingOverlay();
                // clear processed files from staging
                files = files.filter(ff=>!excelFiles.includes(ff));
                fileInput.value = '';
                refreshState();
                // clear server-side recent payloads to avoid duplicate restored cards in recon section
                try{
                    await fetch(window.autoreconBaseUrl + '/src/controllers/excelcontrol/clear-recent.php', { method: 'POST' });
                    const recon = document.getElementById('reconCards'); if(recon) recon.innerHTML = '';
                }catch(e){ if(!isUploadAbortError(e)) console.warn('Failed to clear server recent payloads', e); }
            }catch(err){
                if(!isUploadAbortError(err)) console.error(err);
                hideProcessingOverlay();
                refreshState();
            } finally {
                _processing = false;
                finishUploadRequest(activeUploadSession);
                refreshState();
            }
        }

        function updateCards(){
            const processed = getProcessedList();
            // sort by date ascending (oldest first)
            processed.sort((a,b)=>{ const da = Date.parse(a.dateStr||''); const db = Date.parse(b.dateStr||''); return da-db; });
            cardsEl.innerHTML = '';
            if(processed.length===0) return;

            // render as a vertical column of cards
            const list = document.createElement('div');
            list.style.display = 'flex';
            list.style.flexDirection = 'column';
            list.style.gap = '0.5rem';

            processed.forEach((p,idx)=>{
                const cardWrap = document.createElement('div');
                cardWrap.style.display = 'flex';
                cardWrap.style.alignItems = 'center';
                cardWrap.style.justifyContent = 'space-between';
                cardWrap.style.background = '#f5f5f5';
                cardWrap.style.padding = '0.2rem 0.6rem';
                cardWrap.style.borderRadius = '8px';
                cardWrap.style.border = '1px solid #eee';

                const left = document.createElement('div');
                left.style.display = 'flex';
                left.style.flexDirection = 'column';
                left.style.gap = '2px';
                const title = document.createElement('div');
                title.style.fontWeight = '600';
                title.textContent = (p.filename || '');
                const meta = document.createElement('div');
                meta.style.fontSize = '0.85rem';
                meta.style.color = '#666';
                meta.textContent = p.rows ? (formatNumber(p.rows.length) + ' rows') : '';
                left.appendChild(title);
                left.appendChild(meta);

                const right = document.createElement('div');
                right.style.display = 'flex';
                right.style.gap = '0.5rem';

                let status = null;
                if(p._uploaded){
                    status = document.createElement('span');
                    status.className = 'upload-status is-uploaded';
                    status.title = 'Uploaded to database';
                    status.innerHTML = '<span class="material-icons" aria-hidden="true">cloud_done</span><span>Uploaded</span>';
                }

                const viewBtn = document.createElement('button');
                viewBtn.className = 'icon-btn view';
                viewBtn.type = 'button';
                viewBtn.title = 'View';
                viewBtn.setAttribute('aria-label','View');
                viewBtn.innerHTML = '<span class="material-icons" aria-hidden="true">visibility</span>';
                viewBtn.onclick = ()=> openViewer(p);

                const delBtn = document.createElement('button');
                delBtn.className = 'icon-btn delete';
                delBtn.type = 'button';
                delBtn.title = 'Delete';
                delBtn.setAttribute('aria-label','Delete');
                delBtn.innerHTML = '<span class="material-icons" aria-hidden="true">delete</span>';
                if(p._uploaded){
                    delBtn.disabled = true;
                    delBtn.title = 'Uploaded files cannot be deleted';
                    delBtn.setAttribute('aria-label','Delete disabled for uploaded file');
                } else {
                    delBtn.onclick = ()=>{
                        processed.splice(idx, 1);
                        updateCards();
                        renderFileList();
                    };
                }

                if(status) right.appendChild(status);
                right.appendChild(viewBtn);
                right.appendChild(delBtn);

                cardWrap.appendChild(left);
                cardWrap.appendChild(right);
                list.appendChild(cardWrap);
            });

            cardsEl.appendChild(list);
        }

        async function openViewer(payload){
            try{
                const endpointDir = resolveWebCompanyKey(company.value);
                if(!endpointDir){ await showAlert('Unsupported company selected.'); return; }
                const url = window.autoreconBaseUrl + '/src/controllers/excelcontrol/' + endpointDir + '/' + endpointDir + '-viewer.php';
                const res = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({data: payload})});
                const html = await res.text();
                showModal(html);
            }catch(e){ console.error(e); await showAlert('Failed to open viewer'); }
        }

        // minimal modal (full-screen) and wiring for viewer search/count
        let modalEl = null;
        function showModal(html){
            if(!modalEl){
                modalEl = document.createElement('div');
                modalEl.className = 'mbtc-modal';
                modalEl.style.position='fixed'; modalEl.style.left=0; modalEl.style.top=0; modalEl.style.right=0; modalEl.style.bottom=0; modalEl.style.background='rgba(0,0,0,0.6)'; modalEl.style.display='flex'; modalEl.style.alignItems='center'; modalEl.style.justifyContent='center'; modalEl.style.zIndex = 11000;
                modalEl.innerHTML = '<div class="mbtc-modal-inner"> <button class="mbtc-close" aria-label="Close"><span class="material-icons">close</span></button><div class="mbtc-modal-body"></div></div>';
                document.body.appendChild(modalEl);
                modalEl.querySelector('.mbtc-close').addEventListener('click', ()=>{ modalEl.style.display='none'; });
                modalEl.addEventListener('click', function(e){ if(e.target === modalEl){ modalEl.style.display='none'; } });
            }
            const body = modalEl.querySelector('.mbtc-modal-body');
            body.innerHTML = html;
            // apply full-size styles to inner container
            const inner = modalEl.querySelector('.mbtc-modal-inner');
            if(inner){
                inner.style.width = '98%';
                inner.style.height = '96%';
                inner.style.maxWidth = 'none';
                inner.style.maxHeight = 'none';
                inner.style.background = '#fff';
                inner.style.padding = '0.5rem';
                inner.style.borderRadius = '6px';
                inner.style.boxShadow = '0 10px 40px rgba(0,0,0,0.4)';
                inner.style.overflow = 'hidden';
                inner.style.position = 'relative';
            }
            // style close button
            const closeBtn = modalEl.querySelector('.mbtc-close');
            if(closeBtn){ closeBtn.style.position='absolute'; closeBtn.style.right='12px'; closeBtn.style.top='8px'; closeBtn.style.background='transparent'; closeBtn.style.border='none'; closeBtn.style.cursor='pointer'; closeBtn.style.padding='6px'; closeBtn.style.borderRadius='6px'; closeBtn.onmouseover = ()=>{ closeBtn.style.background='#f5f5f5' }; closeBtn.onmouseout = ()=>{ closeBtn.style.background='transparent' }; }

            // wire viewer search/count if elements exist in returned html
            setTimeout(()=>{
                try{
                    const search = modalEl.querySelector('#mbtcViewerSearch');
                    const btn = modalEl.querySelector('#mbtcViewerSearchBtn');
                    const table = modalEl.querySelector('#mbtcViewerTable');
                    const countEl = modalEl.querySelector('#mbtcViewerCount');
                    if(!table) return;
                    function updateCount(){ const rows = table.tBodies[0].rows; let c=0; for(let r of rows){ if(r.style.display !== 'none') c++; } if(countEl){ try{ countEl.textContent = (c).toLocaleString(); }catch(e){ countEl.textContent = String(c); } } }
                    function doSearch(){ const q = (search && search.value||'').trim().toLowerCase(); const rows = table.tBodies[0].rows; for(let r of rows){ let text = r.textContent.toLowerCase(); if(q === '' || text.indexOf(q)!==-1){ r.style.display='table-row'; } else { r.style.display='none'; } } updateCount(); }
                    if(btn) btn.addEventListener('click', doSearch);
                    if(search) search.addEventListener('keyup', function(e){ if(e.key==='Enter') doSearch(); if(search.value==='') doSearch(); });
                    // initial style adjustments for table container
                    const container = modalEl.querySelector('#mbtcViewerContainer'); if(container){ container.style.height = 'calc(100% - 96px)'; container.style.overflow = 'auto'; }
                    // improve table appearance
                    table.style.width = '100%'; table.style.borderCollapse = 'collapse'; table.querySelectorAll('th,td').forEach(td=>{ td.style.padding='8px'; td.style.border='1px solid #e6e6e6'; td.style.fontSize='13px'; td.style.verticalAlign='top'; });
                    // allow horizontal scroll
                    table.style.tableLayout = 'auto';
                    updateCount();
                }catch(e){ console.warn('viewer wiring failed', e); }
            }, 20);

            modalEl.style.display = 'flex';
        }

        // confirmation modal for fetching
        const confirmModal = document.getElementById('mbtcConfirmModal');
        const confirmCompanyEl = document.getElementById('mbtcConfirmCompany');
        const confirmCountEl = document.getElementById('mbtcConfirmCount');
        const confirmListEl = document.getElementById('mbtcConfirmList');
        const fetchBtn = document.getElementById('mbtcFetchBtn');
        const confirmCancel = document.getElementById('mbtcConfirmCancel');

        function showConfirmModal(fileArray){
            if(!confirmModal) return;
            confirmCompanyEl.textContent = company.value || '';
            confirmCountEl.textContent = fileArray.length;
            confirmListEl.innerHTML = '';
            fileArray.forEach(f => {
                const li = document.createElement('li');
                li.style.padding = '6px 0';
                li.style.borderBottom = '1px solid #f1f1f1';
                li.textContent = f.name;
                confirmListEl.appendChild(li);
            });
            confirmModal.style.display = 'flex';

            fetchBtn.onclick = ()=>{
                confirmModal.style.display = 'none';
                processMbtcFiles(fileArray);
            };
            confirmCancel.onclick = ()=>{ confirmModal.style.display = 'none'; };
        }

        // show select-company-first modal
        const selectCompanyModal = document.getElementById('mbtcSelectCompanyModal');
        const selectCompanyClose = document.getElementById('mbtcSelectCompanyClose');
        function showSelectCompanyModal(){
            if(!selectCompanyModal) return;
            selectCompanyModal.style.display = 'flex';
        }
        if(selectCompanyClose) selectCompanyClose.onclick = ()=>{ if(selectCompanyModal) selectCompanyModal.style.display='none'; };

        // processing overlay functions
        // Generic modal helpers (replace alert/confirm)
        const dialogEl = document.getElementById('mbtcDialog');
        function formatLockedDateUploadMessage(message){
            const text = String(message || '');
            if(!/Upload blocked/i.test(text) || !/Reconciled dates are locked/i.test(text)) return text;
            const dates = Array.from(new Set((text.match(/\d{4}-\d{2}-\d{2}/g) || [])));
            if(dates.length === 0) return text;
            const formattedDates = dates.map(date => {
                const parts = date.split('-').map(Number);
                const d = new Date(parts[0], parts[1] - 1, parts[2]);
                return d.toLocaleDateString('en-US', { month: 'long', day: '2-digit', year: 'numeric' });
            }).join('\n');
            return 'Transaction already locked:\n' + formattedDates;
        }
        function _closeDialog(){ if(dialogEl) dialogEl.style.display='none'; }
        function showAlert(message, title){
            return new Promise((resolve)=>{
                const displayMessage = formatLockedDateUploadMessage(message);
                const displayTitle = title || 'Notice';
                if(!dialogEl){ console.warn('Dialog not available:', message); return resolve(); }
                dialogEl.querySelector('.mbtcDialogTitle').textContent = displayTitle;
                const msgEl = dialogEl.querySelector('.mbtcDialogMessage');
                msgEl.textContent = displayMessage;
                msgEl.style.whiteSpace = 'pre-line';
                const ok = dialogEl.querySelector('.mbtcDialogOk');
                const cancel = dialogEl.querySelector('.mbtcDialogCancel');
                if(ok) ok.textContent = 'OK';
                if(cancel) cancel.textContent = 'Cancel';
                cancel.style.display = 'none';
                ok.style.display = '';
                dialogEl.style.display = 'flex';
                ok.onclick = function(){ _closeDialog(); resolve(); };
            });
        }
        function escapeHtml(value){
            return String(value == null ? '' : value).replace(/[&<>"']/g, function(ch){
                return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[ch] || ch;
            });
        }
        function buildMissingMoneygramBranchListHtml(branches){
            const seen = new Set();
            const items = (Array.isArray(branches) ? branches : []).map(function(item){
                const branchName = String(item && item.branch_name ? item.branch_name : '').trim();
                const branchId = String(item && item.branch_id ? item.branch_id : '').trim();
                const key = branchId || branchName;
                if(!key || seen.has(key)) return null;
                seen.add(key);
                const label = branchName || (branchId ? ('Branch ID ' + branchId) : 'Unknown branch');
                return { label, branchId };
            }).filter(Boolean);

            if(items.length === 0){
                return '<div style="text-align:left;color:#4b5563;">No branch details returned.</div>';
            }

            return '<div style="text-align:left;color:#4b5563;font-weight:600;margin:0 0 0.75rem;">Total branches: ' + items.length + '</div>'
                + '<div style="max-height:340px;overflow:auto;text-align:left;">'
                + '<ul style="margin:0;padding-left:1.1rem;">'
                + items.map(function(item){
                    const idText = item.branchId ? ' <span style="color:#6b7280;font-size:0.85em;">(' + escapeHtml(item.branchId) + ')</span>' : '';
                    return '<li style="margin:0.35rem 0;">' + escapeHtml(item.label) + idText + '</li>';
                }).join('')
                + '</ul></div>';
        }
        async function showMoneygramLegacyIdNotice(missingBranches){
            const message = 'Legacy ID not yet registered, Contact Administrator.';
            if(window.Swal && typeof window.Swal.fire === 'function'){
                const setSwalZIndex = function(){
                    const container = document.querySelector('.swal2-container');
                    if(container) container.style.zIndex = '12020';
                };
                const result = await window.Swal.fire({
                    title: 'Notice',
                    text: message,
                    confirmButtonText: 'View',
                    confirmButtonColor: '#DC3545',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    allowEnterKey: false,
                    showCloseButton: false,
                    didOpen: setSwalZIndex
                });
                if(result && result.isConfirmed){
                    await window.Swal.fire({
                        title: 'Branches with unregistered Legacy ID',
                        html: buildMissingMoneygramBranchListHtml(missingBranches),
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#DC3545',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        allowEnterKey: false,
                        showCloseButton: false,
                        width: 640,
                        didOpen: setSwalZIndex
                    });
                }
                return;
            }
            return showAlert(message, 'Notice');
        }
        function showConfirm(message, title){
            return new Promise((resolve)=>{
                if(!dialogEl){ console.warn('Dialog not available (confirm):', message); return resolve(false); }
                dialogEl.querySelector('.mbtcDialogTitle').textContent = title || 'Confirm';
                dialogEl.querySelector('.mbtcDialogMessage').textContent = message;
                const ok = dialogEl.querySelector('.mbtcDialogOk');
                const cancel = dialogEl.querySelector('.mbtcDialogCancel');
                if(ok) ok.textContent = 'Yes';
                if(cancel) cancel.textContent = 'No';
                cancel.style.display = '';
                ok.style.display = '';
                dialogEl.style.display = 'flex';
                ok.onclick = function(){ _closeDialog(); resolve(true); };
                cancel.onclick = function(){ _closeDialog(); resolve(false); };
            });
        }
        function showProcessingOverlay(total){
            if(!overlayEl) return;
            const safeTotal = Math.max(0, Number(total || 0));
            progressBar.style.width = '0%';
            progressText.textContent = 'Analyzing ' + (safeTotal > 0 ? 1 : 0) + ' of ' + safeTotal + ' files';
            overlayEl.style.display = 'flex';
            cancelBtn.onclick = ()=>{ cancelUploadRequest(); };
        }
        function updateProcessing(done,total){
            if(!overlayEl) return;
            const safeTotal = Math.max(0, Number(total || 0));
            const rawDone = Math.max(0, Number(done || 0));
            const displayDone = safeTotal > 0 ? Math.min(Math.max(1, rawDone), safeTotal) : 0;
            const pct = Math.round((rawDone/Math.max(1, safeTotal))*100);
            progressBar.style.width = pct + '%';
            progressText.textContent = 'Analyzing ' + displayDone + ' of ' + safeTotal + ' files';
        }
        function hideProcessingOverlay(){ if(overlayEl) overlayEl.style.display='none'; }

        if(company){
            company.addEventListener('input', updatePartnerIdField);
            company.addEventListener('change', updatePartnerIdField);
        }
        if(partnerIdInput){
            partnerIdInput.addEventListener('input', updateCompanyFromPartnerId);
            partnerIdInput.addEventListener('change', updateCompanyFromPartnerId);
        }
        attachPartnerAutocomplete(company, partners);
        updatePartnerIdField();
        // initialize
        refreshState();
        updateCards();
    })();
    </script>
</section>
