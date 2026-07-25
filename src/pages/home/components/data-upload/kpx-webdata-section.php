<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../config/session.php';
require_once __DIR__ . '/../../../../config/csrf.php';
require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../config/middleware.php';

$kpxPartners = [];
$kpxPartnerIds = [];
$kpxCsrfToken = function_exists('csrfToken') ? csrfToken() : '';
$kpxUserRole = (string)($_SESSION['user']['role'] ?? 'Public');
$kpxCanUpload = in_array(strtolower($kpxUserRole), ['admin', 'user', 'public'], true);

try {
    $pdo = masterDataConnection();
    $stmt = $pdo->query("
        SELECT partner_name, partner_id
        FROM corpo_partner_masterfile
        WHERE partner_name IS NOT NULL
          AND partner_name <> ''
        ORDER BY partner_name ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $name = trim((string)($row['partner_name'] ?? ''));
        if ($name === '') {
            continue;
        }

        if (!in_array($name, $kpxPartners, true)) {
            $kpxPartners[] = $name;
        }

        if (!array_key_exists($name, $kpxPartnerIds)) {
            $kpxPartnerIds[$name] = (string)($row['partner_id'] ?? '');
        }
    }
} catch (Throwable $e) {
    $kpxPartners = [];
    $kpxPartnerIds = [];
}
?>
<section id="kpxWebDataVer2Section" class="kpx-webdata-section" aria-label="KPX Web Data Version 2" style="display:none; padding:1rem">
    <div class="kpx-webdata-inner" data-can-upload="<?= $kpxCanUpload ? '1' : '0' ?>">
        <header class="kpx-webdata-header">
            <h2 class="kpx-webdata-title">KPX Web Data Uploader</h2>
        </header>

        <div class="kpx-webdata-filters">
            <div class="kpx-filters-left">
                <label class="kpx-wd-filter">
                    <span>Corporate Partner</span>
                    <div class="kpx-autocomplete-field">
                        <input id="kpxWdCompany" type="text" placeholder="Select corporate partner" autocomplete="off">
                        <ul class="kpx-autocomplete-list" id="kpxWdCompanySuggestions" role="listbox" hidden></ul>
                        <datalist id="kpxWdCompanyList">
                            <?php if (empty($kpxPartners)): ?>
                                <option value=""></option>
                            <?php else: ?>
                                <?php foreach ($kpxPartners as $partner): ?>
                                    <option value="<?= htmlspecialchars((string)$partner, ENT_QUOTES, 'UTF-8') ?>"></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </datalist>
                    </div>
                </label>

                <label class="kpx-wd-filter kpx-wd-filter--compact">
                    <span>Partner ID</span>
                    <input id="kpxWdPartnerId" type="text" maxlength="4" placeholder="ID">
                </label>
            </div>

            <div class="kpx-filters-actions">
                <button id="kpxWdUpload" class="material-btn material-btn--primary" type="button" disabled>Upload</button>
            </div>
        </div>

        <?php if (!$kpxCanUpload): ?>
            <div class="kpx-webdata-notice" role="status">Your account can view this section, but upload access is restricted.</div>
        <?php endif; ?>

        <div class="kpx-wd-dropwrap">
            <div id="kpxWdDropzone" class="kpx-wd-dropzone kpx-wd-dropzone--disabled" tabindex="0" role="button" aria-controls="kpxWdFiles">
                <div class="kpx-wd-drop-inner">
                    <span class="material-icons" aria-hidden="true">cloud_upload</span>
                    <p class="kpx-wd-drop-text">Drag and drop files here<br>or<br>Click to browse files</p>
                    <p class="kpx-wd-drop-hint">Supports multiple Excel or CSV files</p>
                </div>
                <input id="kpxWdFiles" type="file" multiple accept=".xls,.xlsx,.csv">
            </div>

        <div class="kpx-wd-filelist" id="kpxWdFileList" aria-live="polite">
                <div class="kpx-wd-empty">No files selected</div>
            </div>
        </div>

        <input id="kpxWdCsrfToken" type="hidden" value="<?= htmlspecialchars($kpxCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <div id="kpxWdViewerModal" class="kpx-wd-viewer-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="kpxWdViewerTitle">
        <div class="kpx-wd-viewer-overlay" data-kpx-viewer-close></div>
        <div class="kpx-wd-viewer-dialog" role="document">
            <div class="kpx-wd-viewer-header">
                <div class="kpx-wd-viewer-title-wrap">
                    <h3 id="kpxWdViewerTitle"></h3>
                    <span id="kpxWdViewerRowCount" class="kpx-wd-viewer-row-count"></span>
                </div>
                <button id="kpxWdViewerClose" class="kpx-wd-viewer-close" type="button" aria-label="Close viewer">
                    <span class="material-icons" aria-hidden="true">close</span>
                </button>
            </div>
            <div class="kpx-wd-viewer-body">
                <?php /*
                <div class="kpx-wd-viewer-modes" role="radiogroup" aria-label="Viewer mode">
                    <label class="kpx-wd-viewer-mode">
                        <input type="radio" name="kpxWdViewerMode" value="normal" checked>
                        <span>Normal Mode</span>
                    </label>
                    <label class="kpx-wd-viewer-mode">
                        <input type="radio" name="kpxWdViewerMode" value="developer">
                        <span>Developer Mode</span>
                    </label>
                </div>
                */ ?>
                <div id="kpxWdSystemTableWrap" class="kpx-wd-system-table-wrap"></div>
            </div>
        </div>
    </div>

    <div id="kpxWdUploadModal" class="kpx-wd-upload-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="kpxWdUploadModalTitle">
        <div class="kpx-wd-upload-dialog">
            <h3 id="kpxWdUploadModalTitle">Processing files...</h3>
            <p id="kpxWdUploadModalMessage">Please wait while the files are prepared.</p>
            <div class="kpx-wd-upload-progress"><div id="kpxWdUploadProgressBar"></div></div>
            <div id="kpxWdUploadModalActions" class="kpx-wd-upload-actions" hidden>
                <button id="kpxWdUploadNo" type="button" class="material-btn">No</button>
                <button id="kpxWdUploadYes" type="button" class="material-btn material-btn--primary">Yes</button>
                <button id="kpxWdUploadOk" type="button" class="material-btn material-btn--primary">OK</button>
            </div>
        </div>
    </div>

    <script>
    (function(){
        const root = document.querySelector('#kpxWebDataVer2Section .kpx-webdata-inner');
        if(!root) return;

        const company = document.getElementById('kpxWdCompany');
        const partnerId = document.getElementById('kpxWdPartnerId');
        const uploadBtn = document.getElementById('kpxWdUpload');
        const dropzone = document.getElementById('kpxWdDropzone');
        const fileInput = document.getElementById('kpxWdFiles');
        const fileList = document.getElementById('kpxWdFileList');
        const csrfToken = document.getElementById('kpxWdCsrfToken');
        const viewerModal = document.getElementById('kpxWdViewerModal');
        const viewerTitle = document.getElementById('kpxWdViewerTitle');
        const viewerRowCount = document.getElementById('kpxWdViewerRowCount');
        const viewerClose = document.getElementById('kpxWdViewerClose');
        const viewerTableWrap = document.getElementById('kpxWdSystemTableWrap');
        const viewerModeInputs = Array.from(document.querySelectorAll('input[name="kpxWdViewerMode"]'));
        const uploadModal = document.getElementById('kpxWdUploadModal');
        const uploadModalTitle = document.getElementById('kpxWdUploadModalTitle');
        const uploadModalMessage = document.getElementById('kpxWdUploadModalMessage');
        const uploadModalActions = document.getElementById('kpxWdUploadModalActions');
        const uploadModalYes = document.getElementById('kpxWdUploadYes');
        const uploadModalNo = document.getElementById('kpxWdUploadNo');
        const uploadModalOk = document.getElementById('kpxWdUploadOk');
        const uploadProgressBar = document.getElementById('kpxWdUploadProgressBar');
        const partners = <?= json_encode($kpxPartners, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const partnerIds = <?= json_encode($kpxPartnerIds, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const canUpload = root.dataset.canUpload === '1';
        let files = [];
        let activeViewerFile = null;
        let activeViewerPayload = null;
        const fileRowCounts = new Map();

        function normalize(value){
            return String(value || '').trim().toLowerCase();
        }

        function getSelectedPartner(){
            const selected = normalize(company && company.value);
            return (partners || []).find(name => normalize(name) === selected) || '';
        }

        function syncPartnerId(){
            const selected = getSelectedPartner();
            partnerId.value = selected && Object.prototype.hasOwnProperty.call(partnerIds, selected) ? partnerIds[selected] : '';
            refreshState();
        }

        function syncCompanyFromPartnerId(){
            const id = String(partnerId.value || '').trim();
            if(id === ''){
                company.value = '';
                refreshState();
                return;
            }

            const match = (partners || []).find(name => String(partnerIds[name] || '').trim() === id);
            if(match) company.value = match;
            refreshState();
        }

        function attachAutocomplete(){
            const list = document.getElementById('kpxWdCompanySuggestions');
            if(!company || !list) return;
            let activeIndex = -1;

            function close(){
                list.hidden = true;
                list.innerHTML = '';
                activeIndex = -1;
            }

            function matches(){
                const query = normalize(company.value);
                const options = Array.from(new Set((partners || []).map(item => String(item || '').trim()).filter(Boolean)));
                if(query === '') return options.slice(0, 8);
                return options.filter(option => normalize(option).includes(query)).slice(0, 8);
            }

            function setActive(items){
                items.forEach((item, index) => item.classList.toggle('is-active', index === activeIndex));
            }

            function select(value){
                company.value = value;
                close();
                syncPartnerId();
            }

            function render(){
                const found = matches();
                if(found.length === 0){
                    close();
                    return;
                }

                list.innerHTML = '';
                found.forEach((item, index) => {
                    const option = document.createElement('li');
                    option.className = 'kpx-autocomplete-item';
                    option.setAttribute('role', 'option');
                    option.textContent = item;
                    option.addEventListener('mousedown', event => {
                        event.preventDefault();
                        select(item);
                    });
                    option.addEventListener('mouseenter', () => {
                        activeIndex = index;
                        setActive(Array.from(list.children));
                    });
                    list.appendChild(option);
                });
                list.hidden = false;
            }

            company.addEventListener('input', () => {
                render();
                syncPartnerId();
            });
            company.addEventListener('focus', render);
            company.addEventListener('keydown', event => {
                const items = Array.from(list.querySelectorAll('.kpx-autocomplete-item'));
                if(list.hidden || items.length === 0) return;

                if(event.key === 'ArrowDown'){
                    event.preventDefault();
                    activeIndex = (activeIndex + 1) % items.length;
                    setActive(items);
                } else if(event.key === 'ArrowUp'){
                    event.preventDefault();
                    activeIndex = activeIndex <= 0 ? items.length - 1 : activeIndex - 1;
                    setActive(items);
                } else if(event.key === 'Enter' && activeIndex >= 0){
                    event.preventDefault();
                    select(items[activeIndex].textContent || '');
                } else if(event.key === 'Escape'){
                    close();
                }
            });
            document.addEventListener('click', event => {
                if(!event.target.closest('.kpx-autocomplete-field')) close();
            });
        }

        function refreshState(){
            const hasPartner = getSelectedPartner() !== '';
            const hasFiles = files.length > 0;
            const enabled = canUpload && hasPartner;
            dropzone.classList.toggle('kpx-wd-dropzone--disabled', !enabled);
            uploadBtn.disabled = !(enabled && hasFiles);
        }

        function countPendingKpxWebUploads(){
            return files.length;
        }

        function clearPendingKpxWebUploads(){
            files = [];
            fileRowCounts.clear();
            if(fileInput) fileInput.value = '';
            renderFiles();
        }

        window.AutoReconUploadPending = window.AutoReconUploadPending || {};
        window.AutoReconUploadPending.kpxWebDataVer2 = {
            label: 'KPX Web Data Uploader',
            count: countPendingKpxWebUploads,
            clear: clearPendingKpxWebUploads
        };

        async function readWorkbookHeaderCells(file){
            const formData = new FormData();
            formData.append('file', file);
            if(csrfToken) formData.append('csrf_token', csrfToken.value || '');
            formData.append('partnerName', company ? String(company.value || '').trim() : '');
            formData.append('partner_id', partnerId ? String(partnerId.value || '').trim() : '');

            const response = await fetch(window.autoreconBaseUrl + '/src/controllers/excelcontrol/kpx-webdata-preview.php', {
                method: 'POST',
                body: formData
            });
            const payload = await response.json().catch(() => null);
            if(!response.ok || !payload || !payload.success){
                const error = new Error((payload && payload.error) || 'Unable to read this file.');
                error.invalidExcelFile = response.status === 400 || response.status === 422;
                throw error;
            }

            return payload;
        }

        function formatRowCount(count){
            const safeCount = Math.max(0, Number(count || 0));
            return safeCount.toLocaleString(undefined, { maximumFractionDigits: 0 }) + ' ' + (safeCount === 1 ? 'row' : 'rows');
        }

        async function detectFileRowCount(file){
            const payload = await readWorkbookHeaderCells(file);
            if(!payload.detectedKey || !Array.isArray(payload.headers) || payload.headers.length === 0){
                const error = new Error('The required KPX Web Data header row was not found.');
                error.invalidExcelFile = true;
                throw error;
            }
            return Array.isArray(payload.rows) ? payload.rows.length : 0;
        }

        function renderSystemTable(headers, rows, options){
            if(!viewerTableWrap) return;
            const displayOptions = options || {};
            if(!headers || headers.length === 0){
                viewerTableWrap.innerHTML = '<div class="kpx-wd-viewer-empty">No matching MoneyGram system table detected.</div>';
                return;
            }

            const numericHeaders = new Set(['AMOUNT', 'CTC', 'CTP', 'CHARGE', 'amount', 'ctc', 'ctp', 'charge']);
            const table = document.createElement('table');
            table.className = 'kpx-wd-system-table';
            const thead = document.createElement('thead');
            const tr = document.createElement('tr');

            headers.forEach(label => {
                const th = document.createElement('th');
                th.textContent = label;
                if(numericHeaders.has(label)) th.classList.add('is-numeric');
                tr.appendChild(th);
            });

            thead.appendChild(tr);
            table.appendChild(thead);
            const tbody = document.createElement('tbody');
            const tableRows = Array.isArray(rows) ? rows : [];
            function formatDisplayNumber(value){
                const text = String(value ?? '').trim();
                if(text === '') return '';
                const normalized = text.replace(/,/g, '');
                if(!/^-?\d+(\.\d+)?$/.test(normalized)) return text;
                return Number(normalized).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
            if(tableRows.length === 0){
                const emptyRow = document.createElement('tr');
                const emptyCell = document.createElement('td');
                emptyCell.colSpan = headers.length;
                emptyCell.textContent = 'No data rows found.';
                emptyRow.appendChild(emptyCell);
                tbody.appendChild(emptyRow);
            } else {
                tableRows.forEach(row => {
                    const bodyRow = document.createElement('tr');
                    headers.forEach(label => {
                        const td = document.createElement('td');
                        const value = row && Object.prototype.hasOwnProperty.call(row, label) ? row[label] : '';
                        td.textContent = displayOptions.formatNumeric === true && numericHeaders.has(label) ? formatDisplayNumber(value) : value;
                        if(numericHeaders.has(label)) td.classList.add('is-numeric');
                        bodyRow.appendChild(td);
                    });
                    tbody.appendChild(bodyRow);
                });
            }
            table.appendChild(tbody);
            viewerTableWrap.innerHTML = '';
            viewerTableWrap.appendChild(table);
        }

        function resetViewerContent(message){
            if(!viewerTableWrap) return;
            viewerTableWrap.innerHTML = message ? '<div class="kpx-wd-viewer-empty">' + message + '</div>' : '';
        }

        function showUploadModal(title, message, actionMode, progressPercent){
            if(!uploadModal) return;
            const mode = actionMode === true ? 'confirm' : (actionMode || '');
            uploadModalTitle.textContent = title || 'Processing files...';
            uploadModalMessage.textContent = message || '';
            uploadModalActions.hidden = mode !== 'confirm' && mode !== 'ok';
            if(uploadModalNo) uploadModalNo.hidden = mode !== 'confirm';
            if(uploadModalYes) uploadModalYes.hidden = mode !== 'confirm';
            if(uploadModalOk) uploadModalOk.hidden = mode !== 'ok';
            uploadModal.classList.toggle('is-confirm', mode === 'confirm' || mode === 'ok');
            if(uploadProgressBar){
                const percent = Number.isFinite(Number(progressPercent)) ? Math.max(0, Math.min(100, Number(progressPercent))) : 0;
                uploadProgressBar.style.width = percent + '%';
            }
            uploadModal.setAttribute('aria-hidden', 'false');
        }

        function hideUploadModal(){
            if(uploadModal) uploadModal.setAttribute('aria-hidden', 'true');
        }

        function showFailureModal(title, message){
            showUploadModal(title || 'Upload Failed', message || 'Upload failed.', 'ok', 100);
            if(uploadModalOk) uploadModalOk.onclick = hideUploadModal;
        }

        function showInvalidExcelFileAlert(){
            if(window.Swal && typeof window.Swal.fire === 'function'){
                return window.Swal.fire({
                    icon: 'warning',
                    title: 'Invalid File Format',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#dc3545'
                });
            }
            showFailureModal('Invalid File Format', '');
            return Promise.resolve();
        }

        function askOverwrite(message){
            return new Promise(resolve => {
                showUploadModal('Confirm', message || 'Data already exists. Do you want to overwrite the existing data?', true);
                uploadModalYes.onclick = () => resolve(true);
                uploadModalNo.onclick = () => resolve(false);
            });
        }

        async function saveDeveloperRows(rows, overwrite, file){
            const formData = new FormData();
            if(csrfToken) formData.append('csrf_token', csrfToken.value || '');
            formData.append('payload', JSON.stringify({
                rows: rows,
                overwrite: !!overwrite,
                filename: file && file.name ? file.name : ''
            }));
            const controller = new AbortController();
            const timeout = window.setTimeout(() => controller.abort(), 180000);
            let response;
            try{
                response = await fetch(window.autoreconBaseUrl + '/src/controllers/excelcontrol/kpx-webdata-save.php', {
                    method: 'POST',
                    body: formData,
                    signal: controller.signal
                });
            }catch(error){
                if(error && error.name === 'AbortError'){
                    throw new Error('Database write timed out after 3 minutes. No further files were sent.');
                }
                throw error;
            }finally{
                window.clearTimeout(timeout);
            }
            const payload = await response.json().catch(() => null);
            if(!response.ok || !payload){
                throw new Error('Upload failed.');
            }
            return payload;
        }

        async function uploadSelectedFiles(){
            if(uploadBtn.disabled) return;
            uploadBtn.disabled = true;
            showUploadModal('Processing files...', 'Preparing records for database write.', false, 0);
            try{
                const developerRows = [];
                const filePayloads = [];
                const queue = files.slice();
                const workerCount = Math.min(4, Math.max(1, queue.length));
                let parsedFiles = 0;
                async function parseWorker(){
                    while(queue.length > 0){
                        const file = queue.shift();
                        if(!file) return;
                        const payload = await readWorkbookHeaderCells(file);
                        const rows = Array.isArray(payload.developerRows) ? payload.developerRows : [];
                        if(rows.length > 0){
                            developerRows.push(...rows);
                            filePayloads.push({ file: file, rows: rows });
                        }
                        parsedFiles++;
                        showUploadModal('Processing files...', 'Preparing records for database write (' + parsedFiles + ' of ' + files.length + ' files).', false, (parsedFiles / files.length) * 100);
                    }
                }
                await Promise.all(Array.from({ length: workerCount }, parseWorker));
                if(developerRows.length === 0) throw new Error('No rows to upload.');

                const duplicatePayloads = [];
                let inserted = 0;
                let updated = 0;
                let writtenFiles = 0;
                for(const payload of filePayloads){
                    showUploadModal('Processing files...', 'Writing data to database (' + writtenFiles + ' of ' + filePayloads.length + ' files).', false, (writtenFiles / filePayloads.length) * 100);
                    const result = await saveDeveloperRows(payload.rows, false, payload.file);
                    if(result.duplicate){
                        duplicatePayloads.push(payload);
                    } else {
                        inserted += Number(result.inserted || 0);
                        updated += Number(result.updated || 0);
                    }
                    writtenFiles++;
                    showUploadModal('Processing files...', 'Writing data to database (' + writtenFiles + ' of ' + filePayloads.length + ' files).', false, (writtenFiles / filePayloads.length) * 100);
                }

                if(duplicatePayloads.length > 0){
                    const confirmed = await askOverwrite('Data with the same CCREF NO and date already exists. Do you want to overwrite the existing data?');
                    if(!confirmed){
                        hideUploadModal();
                        refreshState();
                        return;
                    }
                    let updatedFiles = 0;
                    for(const payload of duplicatePayloads){
                        showUploadModal('Processing files...', 'Updating existing database records (' + updatedFiles + ' of ' + duplicatePayloads.length + ' files).', false, (updatedFiles / duplicatePayloads.length) * 100);
                        const result = await saveDeveloperRows(payload.rows, true, payload.file);
                        inserted += Number(result.inserted || 0);
                        updated += Number(result.updated || 0);
                        updatedFiles++;
                        showUploadModal('Processing files...', 'Updating existing database records (' + updatedFiles + ' of ' + duplicatePayloads.length + ' files).', false, (updatedFiles / duplicatePayloads.length) * 100);
                    }
                }

                showUploadModal('Completed Successfully', 'Inserted ' + inserted + ' row(s), updated ' + updated + ' row(s).', false, 100);
                setTimeout(hideUploadModal, 1400);
                files = [];
                fileRowCounts.clear();
                if(fileInput) fileInput.value = '';
                renderFiles();
            }catch(error){
                console.error(error);
                showFailureModal('Upload Failed', error && error.message ? error.message : 'Upload failed.');
            }finally{
                refreshState();
            }
        }

        async function getActiveViewerPayload(){
            if(activeViewerPayload) return activeViewerPayload;
            if(!activeViewerFile) return null;
            activeViewerPayload = await readWorkbookHeaderCells(activeViewerFile);
            return activeViewerPayload;
        }

        async function renderNormalMode(file){
            if(!file) return;

            resetViewerContent('Reading file...');
            try{
                const payload = await getActiveViewerPayload();
                if(payload.headers && payload.headers.length > 0){
                    renderSystemTable(payload.headers, payload.rows || [], { formatNumeric: true });
                    return;
                }
                const row4 = payload.row4 || {};
                resetViewerContent('No matching MoneyGram system table detected. Found C4: "' + (row4.C || 'blank') + '", D4: "' + (row4.D || 'blank') + '".');
            }catch(error){
                console.error(error);
                resetViewerContent(error && error.message ? error.message : 'Unable to read this file.');
            }
        }

        async function renderDeveloperMode(){
            if(!activeViewerFile) return;

            resetViewerContent('Preparing database records...');
            try{
                const payload = await getActiveViewerPayload();
                if(payload.developerHeaders && payload.developerHeaders.length > 0){
                    renderSystemTable(payload.developerHeaders, payload.developerRows || [], { formatNumeric: false });
                    return;
                }
                resetViewerContent('No developer records prepared.');
            }catch(error){
                console.error(error);
                resetViewerContent(error && error.message ? error.message : 'Unable to prepare developer records.');
            }
        }

        function renderViewerMode(){
            const selectedMode = (viewerModeInputs.find(input => input.checked) || {}).value || 'normal';
            if(selectedMode === 'normal'){
                renderNormalMode(activeViewerFile);
            } else {
                renderDeveloperMode();
            }
        }

        function openViewer(file){
            if(!viewerModal || !viewerTitle) return;
            activeViewerFile = file || null;
            activeViewerPayload = null;
            viewerTitle.textContent = file && file.name ? file.name : 'Selected file';
            if(viewerRowCount){
                viewerRowCount.textContent = file && fileRowCounts.has(file) ? formatRowCount(fileRowCounts.get(file)) : '';
            }
            viewerModal.setAttribute('aria-hidden', 'false');
            const normalMode = viewerModeInputs.find(input => input.value === 'normal');
            if(normalMode) normalMode.checked = true;
            renderViewerMode();
            if(viewerClose) viewerClose.focus();
        }

        function closeViewer(){
            if(viewerModal) viewerModal.setAttribute('aria-hidden', 'true');
            activeViewerFile = null;
            activeViewerPayload = null;
            if(viewerRowCount) viewerRowCount.textContent = '';
            resetViewerContent('');
        }

        function renderFiles(){
            fileList.innerHTML = '';
            if(files.length === 0){
                fileList.innerHTML = '<div class="kpx-wd-empty">No files selected</div>';
                refreshState();
                return;
            }

            const count = document.createElement('div');
            count.className = 'kpx-wd-filecount';
            const countText = document.createElement('span');
            countText.textContent = files.length + ' file' + (files.length === 1 ? '' : 's') + ' ready';
            const removeAll = document.createElement('button');
            removeAll.className = 'kpx-wd-remove-all';
            removeAll.type = 'button';
            removeAll.textContent = 'Remove All';
            removeAll.addEventListener('click', () => {
                files = [];
                fileRowCounts.clear();
                if(fileInput) fileInput.value = '';
                renderFiles();
            });
            count.appendChild(countText);
            if(files.length >= 2){
                count.appendChild(removeAll);
            }

            const list = document.createElement('ul');
            list.className = 'kpx-wd-files-ul';
            files.forEach((file, index) => {
                const item = document.createElement('li');
                item.className = 'kpx-wd-file-item';

                const meta = document.createElement('div');
                meta.className = 'kpx-wd-file-meta';

                const name = document.createElement('span');
                name.className = 'name';
                name.textContent = file.name;

                const rowCount = document.createElement('i');
                rowCount.className = 'kpx-wd-row-count';
                rowCount.textContent = fileRowCounts.has(file) ? formatRowCount(fileRowCounts.get(file)) : 'Counting rows...';

                meta.appendChild(name);
                meta.appendChild(rowCount);

                const actions = document.createElement('div');
                actions.className = 'kpx-wd-file-actions';

                const view = document.createElement('button');
                view.className = 'kpx-wd-view';
                view.type = 'button';
                view.title = 'View file';
                view.setAttribute('aria-label', 'View ' + file.name);
                view.innerHTML = '<span class="material-icons-outlined" aria-hidden="true">remove_red_eye</span>';
                view.addEventListener('click', () => openViewer(file));

                const remove = document.createElement('button');
                remove.className = 'kpx-wd-remove';
                remove.type = 'button';
                remove.title = 'Remove file';
                remove.setAttribute('aria-label', 'Remove ' + file.name);
                remove.innerHTML = '<span class="material-icons" aria-hidden="true">delete</span>';
                remove.addEventListener('click', () => {
                    files.splice(index, 1);
                    renderFiles();
                });

                item.appendChild(meta);
                actions.appendChild(view);
                actions.appendChild(remove);
                item.appendChild(actions);
                list.appendChild(item);
            });

            fileList.appendChild(count);
            fileList.appendChild(list);
            refreshState();
        }

        async function addFiles(selectedFiles){
            const incoming = Array.from(selectedFiles || []);
            const newFiles = [];
            incoming.forEach(file => {
                const exists = files.some(item => item.name === file.name && item.size === file.size && item.lastModified === file.lastModified);
                if(!exists) {
                    files.push(file);
                    newFiles.push(file);
                }
            });
            renderFiles();
            if(newFiles.length === 0) return;

            showUploadModal('Checking data rows...', 'Checking 0 of ' + newFiles.length + ' files.', false, 0);
            const queue = newFiles.slice();
            const workerCount = Math.min(4, Math.max(1, queue.length));
            const invalidFiles = [];
            let checked = 0;

            async function countWorker(){
                while(queue.length > 0){
                    const file = queue.shift();
                    if(!file) return;
                    try{
                        const count = await detectFileRowCount(file);
                        if(files.includes(file)) fileRowCounts.set(file, count);
                    }catch(error){
                        if(error && error.invalidExcelFile){
                            invalidFiles.push(file);
                        }else{
                            console.warn('[kpx-webdata] failed to count rows', error);
                            if(files.includes(file)) fileRowCounts.set(file, 0);
                        }
                    }
                    checked++;
                    renderFiles();
                    showUploadModal('Checking data rows...', 'Checking ' + checked + ' of ' + newFiles.length + ' files.', false, (checked / newFiles.length) * 100);
                }
            }

            await Promise.all(Array.from({ length: workerCount }, countWorker));
            if(invalidFiles.length > 0){
                const invalidSet = new Set(invalidFiles);
                files = files.filter(file => !invalidSet.has(file));
                invalidFiles.forEach(file => fileRowCounts.delete(file));
                if(fileInput) fileInput.value = '';
            }
            renderFiles();
            hideUploadModal();
            if(invalidFiles.length > 0){
                await showInvalidExcelFileAlert();
            }
        }

        partnerId.addEventListener('input', syncCompanyFromPartnerId);
        dropzone.addEventListener('click', () => {
            if(!dropzone.classList.contains('kpx-wd-dropzone--disabled')) fileInput.click();
        });
        dropzone.addEventListener('dragover', event => {
            if(dropzone.classList.contains('kpx-wd-dropzone--disabled')) return;
            event.preventDefault();
            dropzone.classList.add('kpx-wd-dropzone--over');
        });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('kpx-wd-dropzone--over'));
        dropzone.addEventListener('drop', event => {
            if(dropzone.classList.contains('kpx-wd-dropzone--disabled')) return;
            event.preventDefault();
            dropzone.classList.remove('kpx-wd-dropzone--over');
            addFiles(event.dataTransfer.files).catch(error => {
                console.error(error);
                showFailureModal('Fetching Failed', error && error.message ? error.message : 'Failed to check selected files.');
            });
        });
        fileInput.addEventListener('change', () => {
            addFiles(fileInput.files).catch(error => {
                console.error(error);
                showFailureModal('Fetching Failed', error && error.message ? error.message : 'Failed to check selected files.');
            });
        });
        viewerModeInputs.forEach(input => input.addEventListener('change', renderViewerMode));
        if(viewerClose) viewerClose.addEventListener('click', closeViewer);
        if(viewerModal){
            viewerModal.addEventListener('click', event => {
                if(event.target && event.target.hasAttribute('data-kpx-viewer-close')) closeViewer();
            });
        }
        document.addEventListener('keydown', event => {
            if(event.key === 'Escape' && viewerModal && viewerModal.getAttribute('aria-hidden') === 'false'){
                closeViewer();
            }
        });
        uploadBtn.addEventListener('click', uploadSelectedFiles);

        attachAutocomplete();
        syncPartnerId();
        renderFiles();
    })();
    </script>
</section>
