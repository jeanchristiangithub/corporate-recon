<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../../../config/db.php';

$settlementDailyPartners = [];
$settlementDailyPartnerIds = [];
$settlementUploaderMode = $settlementUploaderMode ?? 'daily';
try {
    $statement = masterDataConnection()->query("SELECT partner_name, partner_id FROM corpo_partner_masterfile WHERE partner_name IS NOT NULL AND partner_name <> '' ORDER BY partner_name");
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $name = trim((string)($row['partner_name'] ?? ''));
        if ($name === '') continue;
        if (!in_array($name, $settlementDailyPartners, true)) $settlementDailyPartners[] = $name;
        if (!array_key_exists($name, $settlementDailyPartnerIds)) $settlementDailyPartnerIds[$name] = (string)($row['partner_id'] ?? '');
    }
} catch (Throwable $exception) {
    $settlementDailyPartners = [];
    $settlementDailyPartnerIds = [];
}
?>
<section id="settlementDailySection" class="settlement-daily-section" aria-label="Settlement Detail - Per Daily Uploader" style="display:none; padding:1rem">
    <div class="settlement-daily-inner">
        <header class="settlement-daily-header">
            <div>
                <h2>Settlement Detail - Per Daily Uploader</h2>
                <p>Import daily settlement detail files for a corporate partner.</p>
            </div>
        </header>

        <div class="settlement-daily-filters">
            <label class="settlement-daily-field settlement-daily-field--partner">
                <span>Corporate Partner</span>
                <div class="settlement-daily-autocomplete">
                    <input id="settlementDailyPartner" type="text" placeholder="Select corporate partner" autocomplete="off">
                    <ul id="settlementDailyPartnerSuggestions" role="listbox" hidden></ul>
                </div>
            </label>

            <label class="settlement-daily-field">
                <span>Partner ID</span>
                <input id="settlementDailyPartnerId" type="text" maxlength="4" placeholder="ID">
            </label>

            <button id="settlementDailyUpload" class="material-btn material-btn--primary" type="button" disabled>
                <span class="material-icons" aria-hidden="true">file_upload</span>
                Upload
            </button>
        </div>

        <div id="settlementDailyDropzone" class="settlement-daily-dropzone is-disabled" tabindex="0" role="button" aria-controls="settlementDailyFiles" aria-disabled="true">
            <div>
                <span class="material-icons" aria-hidden="true">cloud_upload</span>
                <p>Drag and drop files here<br>or<br><strong>Click to browse files</strong></p>
                <small>Accepts .xls, .xlsx, and .csv files only</small>
            </div>
            <input id="settlementDailyFiles" type="file" multiple accept=".xls,.xlsx,.csv">
        </div>

        <div id="settlementDailyFileList" class="settlement-daily-filelist" aria-live="polite">
            <div class="settlement-daily-empty">No files selected</div>
        </div>
    </div>

    <div id="settlementDailyCheckingModal" class="settlement-daily-checking-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="settlementDailyCheckingTitle">
        <div class="settlement-daily-checking-dialog">
            <h3 id="settlementDailyCheckingTitle">Checking data rows...</h3>
            <p id="settlementDailyCheckingMessage">Checking 0 of 0 files.</p>
            <div class="settlement-daily-checking-progress"><div id="settlementDailyCheckingBar"></div></div>
        </div>
    </div>

    <script>
    (function () {
        const root = document.getElementById('settlementDailySection');
        if (!root || root.dataset.initialized === '1') return;
        root.dataset.initialized = '1';

        const partner = document.getElementById('settlementDailyPartner');
        const partnerId = document.getElementById('settlementDailyPartnerId');
        const suggestions = document.getElementById('settlementDailyPartnerSuggestions');
        const dropzone = document.getElementById('settlementDailyDropzone');
        const input = document.getElementById('settlementDailyFiles');
        const fileList = document.getElementById('settlementDailyFileList');
        const upload = document.getElementById('settlementDailyUpload');
        const checkingModal = document.getElementById('settlementDailyCheckingModal');
        const checkingMessage = document.getElementById('settlementDailyCheckingMessage');
        const checkingBar = document.getElementById('settlementDailyCheckingBar');
        const allowedExtensions = ['xls', 'xlsx', 'csv'];
        const batchHeaders = ['Tran FX Rate', 'FX Date', 'Base Amt', 'Fee Amt', 'Fx Rev Share Amt', 'Comm Amt'];
        const uploaderMode = <?= json_encode($settlementUploaderMode, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const partners = <?= json_encode($settlementDailyPartners, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const partnerIds = <?= json_encode($settlementDailyPartnerIds, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        let files = [];
        const fileRowCounts = new Map();

        function normalize(value) { return String(value || '').trim().toLowerCase(); }
        function selectedPartner() { return partners.find(name => normalize(name) === normalize(partner.value)) || ''; }
        function isMoneyGramPartner() { return normalize(selectedPartner()).includes('moneygram'); }
        function readyForFiles() { return selectedPartner() !== ''; }
        function fileKey(file) { return [file.name, file.size, file.lastModified].join(':'); }
        function refreshState() {
            const enabled = readyForFiles();
            dropzone.classList.toggle('is-disabled', !enabled);
            dropzone.setAttribute('aria-disabled', enabled ? 'false' : 'true');
            upload.disabled = !(enabled && files.length);
        }
        function renderFiles() {
            fileList.innerHTML = '';
            if (!files.length) {
                fileList.innerHTML = '<div class="settlement-daily-empty">No files selected</div>';
                refreshState();
                return;
            }
            const heading = document.createElement('div');
            heading.className = 'settlement-daily-filecount';
            heading.innerHTML = '<span>' + files.length + ' file' + (files.length === 1 ? '' : 's') + ' ready</span>';
            const clear = document.createElement('button');
            clear.type = 'button'; clear.textContent = 'Remove All';
            clear.addEventListener('click', () => { files = []; fileRowCounts.clear(); input.value = ''; renderFiles(); });
            heading.appendChild(clear);
            clear.hidden = files.length < 2;
            fileList.appendChild(heading);
            const list = document.createElement('ul');
            files.forEach((file, index) => {
                const item = document.createElement('li');
                const meta = document.createElement('div');
                meta.innerHTML = '<strong></strong><small></small>';
                meta.querySelector('strong').textContent = file.name;
                const rowCount = fileRowCounts.get(file) || 0;
                meta.querySelector('small').textContent = rowCount.toLocaleString() + ' ' + (rowCount === 1 ? 'row' : 'rows');
                const remove = document.createElement('button');
                remove.type = 'button'; remove.title = 'Remove file'; remove.setAttribute('aria-label', 'Remove ' + file.name);
                remove.innerHTML = '<span class="material-icons" aria-hidden="true">delete</span>';
                remove.addEventListener('click', () => { fileRowCounts.delete(file); files.splice(index, 1); renderFiles(); });
                const view = document.createElement('button');
                view.type = 'button'; view.className = 'settlement-daily-view'; view.title = 'View file'; view.setAttribute('aria-label', 'View ' + file.name);
                view.innerHTML = '<span class="material-icons-outlined" aria-hidden="true">remove_red_eye</span>';
                view.addEventListener('click', () => previewFile(file));
                const actions = document.createElement('div');
                actions.className = 'settlement-daily-file-actions';
                actions.append(view, remove);
                item.append(meta, actions); list.appendChild(item);
            });
            fileList.appendChild(list); refreshState();
        }
        function ensureSheetJs() {
            if (window.XLSX) return Promise.resolve(window.XLSX);
            if (window.settlementDailySheetJsPromise) return window.settlementDailySheetJsPromise;
            window.settlementDailySheetJsPromise = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js';
                script.onload = () => resolve(window.XLSX);
                script.onerror = () => reject(new Error('Unable to load the Excel file reader.'));
                document.head.appendChild(script);
            });
            return window.settlementDailySheetJsPromise;
        }
        async function previewFile(file) {
            try {
                const XLSX = await ensureSheetJs();
                const workbook = XLSX.read(await file.arrayBuffer(), { type: 'array', cellDates: true });
                const sheet = workbook.Sheets[workbook.SheetNames[0]];
                const normalRows = sheet ? XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '', raw: false }).slice(0, 101) : [];
                const rawDeveloperRows = sheet ? XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '', raw: true }).slice(0, 101) : [];
                const developerRows = rawDeveloperRows.map((row, rowIndex) => [rowIndex === 0 ? 'Data Source' : 'From Excel', ...row]);
                const content = document.createElement('div');
                const modes = document.createElement('div');
                modes.className = 'settlement-daily-preview-modes';
                modes.innerHTML = '<label><input type="radio" name="settlementDailyPreviewMode" value="normal" checked><span>Normal Mode</span></label><label><input type="radio" name="settlementDailyPreviewMode" value="developer"><span>Developer Mode</span></label>';
                const payoutTabs = document.createElement('div');
                payoutTabs.className = 'settlement-daily-payout-tabs';
                payoutTabs.hidden = true;
                payoutTabs.innerHTML = '<button type="button" class="is-active" data-view="existing">Existed Data</button><button type="button" data-view="non-existing">Non-existed Data</button>';
                const wrap = document.createElement('div');
                wrap.className = 'settlement-daily-preview-wrap';
                content.append(modes, payoutTabs, wrap);
                const developerNumericHeaders = new Set(['margin', 'base tran amt', 'fee tran amt', 'fx rev share tran amt', 'comm tran amt', 'total tran amt']);
                let activeDeveloperView = 'existing';
                function developerRowsForView() { return developerRows; }
                function renderPreview(rows, developerMode) {
                    wrap.innerHTML = '';
                    if (!rows.length) {
                        wrap.textContent = 'No data rows found.';
                        return;
                    }
                    const table = document.createElement('table');
                    const firstEmptyHeader = rows[0].findIndex(value => String(value ?? '').trim() === '');
                    const columnCount = firstEmptyHeader >= 0 ? firstEmptyHeader : rows[0].length;
                    const normalizedHeaders = rows[0].map(value => normalize(value));
                    rows.forEach((row, rowIndex) => {
                        const tr = document.createElement('tr');
                        Array.from({ length: columnCount }, (_, columnIndex) => row[columnIndex] ?? '').forEach((value, columnIndex) => {
                            const cell = document.createElement(rowIndex === 0 ? 'th' : 'td');
                            const numericText = String(value ?? '').trim().replace(/,/g, '');
                            const shouldFormat = developerMode && rowIndex > 0 && developerNumericHeaders.has(normalizedHeaders[columnIndex]) && numericText !== '' && Number.isFinite(Number(numericText));
                            cell.textContent = shouldFormat ? Number(numericText).toFixed(2) : String(value ?? '');
                            tr.appendChild(cell);
                        });
                        table.appendChild(tr);
                    });
                    wrap.appendChild(table);
                }
                renderPreview(normalRows, false);
                modes.querySelectorAll('input[name="settlementDailyPreviewMode"]').forEach(input => {
                    input.addEventListener('change', () => {
                        const developerMode = input.value === 'developer';
                        payoutTabs.hidden = !developerMode;
                        renderPreview(developerMode ? developerRowsForView(activeDeveloperView) : normalRows, developerMode);
                    });
                });
                payoutTabs.querySelectorAll('button[data-view]').forEach(button => {
                    button.addEventListener('click', () => {
                        activeDeveloperView = button.dataset.view || 'existing';
                        payoutTabs.querySelectorAll('button').forEach(item => item.classList.toggle('is-active', item === button));
                        renderPreview(developerRowsForView(activeDeveloperView), true);
                    });
                });
                if (window.Swal && typeof window.Swal.fire === 'function') {
                    await window.Swal.fire({ title: file.name, html: content, width: '92vw', confirmButtonText: 'Close', confirmButtonColor: '#dc3545' });
                }
            } catch (error) {
                if (window.Swal) await window.Swal.fire({ icon: 'error', title: 'Unable to Preview File', text: error.message || 'The selected file could not be opened.' });
            }
        }
        function isLastDayOfMonth(value, XLSX) {
            let dateValue = null;
            if (value instanceof Date && !Number.isNaN(value.getTime())) {
                dateValue = value;
            } else if (typeof value === 'number') {
                const parsed = XLSX.SSF.parse_date_code(value);
                if (parsed) dateValue = new Date(parsed.y, parsed.m - 1, parsed.d);
            } else {
                const text = String(value || '').trim().replace(/^'/, '');
                const match = text.match(/^(\d{1,4})[\/-](\d{1,2})[\/-](\d{1,4})/);
                if (match) {
                    const yearFirst = match[1].length === 4;
                    const year = Number(yearFirst ? match[1] : match[3]);
                    const month = Number(yearFirst ? match[2] : match[1]);
                    const day = Number(yearFirst ? match[3] : match[2]);
                    const parsedDate = new Date(year, month - 1, day);
                    if (parsedDate.getFullYear() === year && parsedDate.getMonth() === month - 1 && parsedDate.getDate() === day) {
                        dateValue = parsedDate;
                    }
                }
            }
            if (!dateValue || Number.isNaN(dateValue.getTime())) return false;
            return dateValue.getDate() === new Date(dateValue.getFullYear(), dateValue.getMonth() + 1, 0).getDate();
        }
        async function inspectMoneyGramFile(file) {
            const XLSX = await ensureSheetJs();
            const workbook = XLSX.read(await file.arrayBuffer(), { type: 'array', cellDates: true });
            const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
            if (!firstSheet) return { isBatchPartnerDaily: false, hasEndMonthTranDate: false, dataRowCount: 0 };
            const rows = XLSX.utils.sheet_to_json(firstSheet, { header: 1, range: 0, blankrows: false, defval: '', raw: true });
            const firstRow = (rows[0] || []).map(value => String(value || '').trim().toLowerCase());
            const tranDateIndex = firstRow.indexOf('tran date');
            return {
                isBatchPartnerDaily: batchHeaders.every(header => firstRow.includes(header.toLowerCase())),
                hasEndMonthTranDate: tranDateIndex >= 0 && rows.slice(1).some(row => isLastDayOfMonth(row[tranDateIndex], XLSX)),
                dataRowCount: rows.slice(1).filter(row => row.some(value => String(value ?? '').trim() !== '')).length
            };
        }
        function showBatchFileAlert() {
            const message = 'Not allowed to batch Excel file partner daily, only upload is settlement detail per daily Excel file.';
            if (window.Swal && typeof window.Swal.fire === 'function') {
                return window.Swal.fire({ icon: 'warning', title: 'Invalid Excel File', text: message, confirmButtonText: 'OK', confirmButtonColor: '#dc3545' });
            }
            window.alert(message);
            return Promise.resolve();
        }
        function showEndMonthFileAlert() {
            const message = 'Not allowed to batch Excel file with last day of the month. Please upload it using the Settlement Detail - End Month Uploader.';
            if (window.Swal && typeof window.Swal.fire === 'function') {
                return window.Swal.fire({ icon: 'warning', title: 'End Month File Detected', text: message, confirmButtonText: 'OK', confirmButtonColor: '#dc3545' });
            }
            window.alert(message);
            return Promise.resolve();
        }
        function showMissingEndMonthFileAlert() {
            const message = 'This is not an end-month settlement file. At least one value under the Tran Date column must be the last calendar day of its month (for example, 03/31/2026).';
            if (window.Swal && typeof window.Swal.fire === 'function') {
                return window.Swal.fire({ icon: 'warning', title: 'End Month Date Required', text: message, confirmButtonText: 'OK', confirmButtonColor: '#dc3545' });
            }
            window.alert(message);
            return Promise.resolve();
        }
        function showCheckingModal(checked, total) {
            const safeTotal = Math.max(0, Number(total || 0));
            const safeChecked = Math.max(0, Math.min(safeTotal, Number(checked || 0)));
            checkingMessage.textContent = 'Checking ' + safeChecked + ' of ' + safeTotal + ' files.';
            checkingBar.style.width = (safeTotal ? (safeChecked / safeTotal) * 100 : 0) + '%';
            checkingModal.setAttribute('aria-hidden', 'false');
        }
        function hideCheckingModal() {
            checkingModal.setAttribute('aria-hidden', 'true');
        }
        async function addFiles(selected) {
            const incoming = Array.from(selected || []).filter(file => {
                const extension = file.name.split('.').pop().toLowerCase();
                return allowedExtensions.includes(extension) && !files.some(current => fileKey(current) === fileKey(file));
            });
            if (!incoming.length) { input.value = ''; return; }

            const rejectedBatchFiles = [];
            const rejectedEndMonthFiles = [];
            const rejectedMissingEndMonthFiles = [];
            const acceptedFiles = [];
            showCheckingModal(0, incoming.length);
            await new Promise(resolve => window.setTimeout(resolve, 0));
            for (let index = 0; index < incoming.length; index++) {
                const file = incoming[index];
                try {
                    const inspection = await inspectMoneyGramFile(file);
                    fileRowCounts.set(file, inspection.dataRowCount || 0);
                    if (isMoneyGramPartner()) {
                        if (inspection.isBatchPartnerDaily) {
                            fileRowCounts.delete(file);
                            rejectedBatchFiles.push(file.name);
                            continue;
                        }
                        if (uploaderMode === 'endMonth') {
                            if (!inspection.hasEndMonthTranDate) {
                                fileRowCounts.delete(file);
                                rejectedMissingEndMonthFiles.push(file.name);
                                continue;
                            }
                        } else if (inspection.hasEndMonthTranDate) {
                            fileRowCounts.delete(file);
                            rejectedEndMonthFiles.push(file.name);
                            continue;
                        }
                    }
                } catch (error) {
                    console.error('[settlement-daily] Unable to inspect file:', file.name, error);
                    if (window.Swal) await window.Swal.fire({ icon: 'error', title: 'Unable to Read Excel File', text: error.message || 'The selected file could not be checked.' });
                    continue;
                } finally {
                    showCheckingModal(index + 1, incoming.length);
                }
                acceptedFiles.push(file);
            }
            hideCheckingModal();
            input.value = '';
            files.push(...acceptedFiles);
            renderFiles();
            if (rejectedEndMonthFiles.length) {
                await showEndMonthFileAlert();
            }
            if (rejectedMissingEndMonthFiles.length) {
                await showMissingEndMonthFileAlert();
            }
            if (rejectedBatchFiles.length) await showBatchFileAlert();
        }

        function closeSuggestions() { suggestions.hidden = true; suggestions.innerHTML = ''; }
        function renderSuggestions() {
            const query = normalize(partner.value);
            const matches = partners.filter(name => !query || normalize(name).includes(query)).slice(0, 8);
            suggestions.innerHTML = '';
            matches.forEach(name => {
                const item = document.createElement('li');
                item.textContent = name;
                item.setAttribute('role', 'option');
                item.addEventListener('mousedown', event => { event.preventDefault(); partner.value = name; partnerId.value = partnerIds[name] || ''; closeSuggestions(); refreshState(); });
                suggestions.appendChild(item);
            });
            suggestions.hidden = matches.length === 0;
        }
        partner.addEventListener('input', () => { const name = selectedPartner(); partnerId.value = name ? (partnerIds[name] || '') : ''; renderSuggestions(); refreshState(); });
        partner.addEventListener('focus', renderSuggestions);
        partnerId.addEventListener('input', () => {
            const id = String(partnerId.value || '').trim();
            const name = partners.find(item => String(partnerIds[item] || '').trim() === id);
            partner.value = name || '';
            refreshState();
        });
        document.addEventListener('click', event => { if (!event.target.closest('.settlement-daily-autocomplete')) closeSuggestions(); });
        dropzone.addEventListener('click', () => { if (readyForFiles()) input.click(); });
        dropzone.addEventListener('keydown', event => {
            if (readyForFiles() && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); input.click(); }
        });
        dropzone.addEventListener('dragover', event => { if (readyForFiles()) { event.preventDefault(); dropzone.classList.add('is-over'); } });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('is-over'));
        dropzone.addEventListener('drop', event => { event.preventDefault(); dropzone.classList.remove('is-over'); if (readyForFiles()) addFiles(event.dataTransfer.files); });
        input.addEventListener('change', () => addFiles(input.files));
        upload.addEventListener('click', () => {
            root.dispatchEvent(new CustomEvent('settlementdaily:upload', { bubbles: true, detail: { partner: selectedPartner(), partnerId: partnerId.value, files: files.slice() } }));
        });

        window.AutoReconUploadPending = window.AutoReconUploadPending || {};
        window.AutoReconUploadPending.settlementDaily = {
            label: 'Settlement Detail - Per Daily Uploader',
            count: () => files.length,
            clear: () => { files = []; fileRowCounts.clear(); input.value = ''; renderFiles(); }
        };
        renderFiles();
    })();
    </script>
</section>
