<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../../../config/db.php';
require_once __DIR__ . '/../../../../../../config/csrf.php';

$settlementDailyPartners = [];
$settlementDailyPartnerIds = [];
$settlementDailyCsrfToken = csrfToken();
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

            <label class="settlement-daily-field settlement-daily-field--partner-id">
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
    <input id="settlementDailyCsrfToken" type="hidden" value="<?= htmlspecialchars($settlementDailyCsrfToken, ENT_QUOTES, 'UTF-8') ?>">

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
        const csrfToken = document.getElementById('settlementDailyCsrfToken');
        const allowedExtensions = ['xls', 'xlsx', 'csv'];
        const batchHeaders = ['Tran FX Rate', 'FX Date', 'Base Amt', 'Fee Amt', 'Fx Rev Share Amt', 'Comm Amt'];
        const uploaderMode = <?= json_encode($settlementUploaderMode, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const currentEndMonthExample = <?= json_encode(date('m/t/Y'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
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
        function normalizeLookupDate(value, XLSX) {
            let parts = null;
            if (value instanceof Date && !Number.isNaN(value.getTime())) {
                parts = [value.getFullYear(), value.getMonth() + 1, value.getDate()];
            } else if (typeof value === 'number') {
                const parsed = XLSX.SSF.parse_date_code(value);
                if (parsed) parts = [parsed.y, parsed.m, parsed.d];
            } else {
                const text = String(value || '').trim().replace(/^'/, '');
                const match = text.match(/^(\d{1,4})[\/-](\d{1,2})[\/-](\d{1,4})/);
                if (match) {
                    const yearFirst = match[1].length === 4;
                    parts = [Number(yearFirst ? match[1] : match[3]), Number(yearFirst ? match[2] : match[1]), Number(yearFirst ? match[3] : match[2])];
                }
            }
            if (!parts) return '';
            const checked = new Date(parts[0], parts[1] - 1, parts[2]);
            if (checked.getFullYear() !== parts[0] || checked.getMonth() !== parts[1] - 1 || checked.getDate() !== parts[2]) return '';
            return String(parts[0]).padStart(4, '0') + '-' + String(parts[1]).padStart(2, '0') + '-' + String(parts[2]).padStart(2, '0');
        }
        function settlementDateFromFilename(filename) {
            const months = {
                january: 1, february: 2, march: 3, april: 4, may: 5, june: 6,
                july: 7, august: 8, september: 9, october: 10, november: 11, december: 12
            };
            const match = String(filename || '').match(/\b(january|february|march|april|may|june|july|august|september|october|november|december)\s+(\d{1,2}),\s*(\d{4})\b/i);
            if (!match) return '';
            const month = months[match[1].toLowerCase()];
            const day = Number(match[2]);
            const year = Number(match[3]);
            const checked = new Date(year, month - 1, day);
            if (checked.getFullYear() !== year || checked.getMonth() !== month - 1 || checked.getDate() !== day) return '';
            return String(year).padStart(4, '0') + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
        }
        async function classifyDeveloperRows(rows, XLSX) {
            if (!isMoneyGramPartner() || rows.length < 2) return {};
            const headers = rows[0].map(value => normalize(value));
            const referenceIndex = headers.findIndex(header => ['reference id', 'reference_id', 'reference no', 'reference'].includes(header));
            const dateIndex = headers.findIndex(header => ['tran date', 'tran_date', 'transaction date', 'date'].includes(header));
            const tranTypeIndex = headers.findIndex(header => ['tran type', 'tran_type'].includes(header));
            const baseAmountIndex = headers.findIndex(header => ['base tran amt', 'base_tran_amt', 'base amt', 'base_amt'].includes(header));
            const fxShareAmountIndex = headers.findIndex(header => ['fx rev share tran amt', 'fx_rev_share_tran_amt', 'fx rev share amt', 'fx_rev_share_amt'].includes(header));
            const commissionAmountIndex = headers.findIndex(header => ['comm tran amt', 'comm_tran_amt', 'comm amt', 'comm_amt'].includes(header));
            if (referenceIndex < 0 || dateIndex < 0) return {};
            const pairs = rows.slice(1).map((row, index) => ({
                index: index,
                reference_id: String(row[referenceIndex] ?? '').trim(),
                tran_date: normalizeLookupDate(row[dateIndex], XLSX),
                tran_type: tranTypeIndex >= 0 ? String(row[tranTypeIndex] ?? '').trim() : '',
                base_tran_amt: baseAmountIndex >= 0 ? row[baseAmountIndex] ?? '' : '',
                fx_rev_share_tran_amt: fxShareAmountIndex >= 0 ? row[fxShareAmountIndex] ?? '' : '',
                comm_tran_amt: commissionAmountIndex >= 0 ? row[commissionAmountIndex] ?? '' : ''
            }));
            const results = {};
            const chunks = [];
            for (let offset = 0; offset < pairs.length; offset += 750) chunks.push(pairs.slice(offset, offset + 750));
            let completedRows = 0;
            async function worker() {
                while (chunks.length) {
                    const chunk = chunks.shift();
                    if (!chunk) return;
                    const formData = new FormData();
                    formData.append('csrf_token', csrfToken ? csrfToken.value : '');
                    formData.append('payload', JSON.stringify({ upload_mode: uploaderMode, pairs: chunk }));
                    const response = await fetch(window.autoreconBaseUrl + '/src/controllers/excelcontrol/moneygram/settlement-daily-classify.php', { method: 'POST', body: formData });
                    const payload = await response.json().catch(() => null);
                    if (!response.ok || !payload || !payload.success) throw new Error((payload && payload.error) || 'Unable to classify settlement rows.');
                    Object.assign(results, payload.results || {});
                    completedRows += chunk.length;
                    if (window.Swal && window.Swal.isVisible()) {
                        window.Swal.update({ text: 'Checking system records: ' + completedRows.toLocaleString() + ' of ' + pairs.length.toLocaleString() + ' rows.' });
                        window.Swal.showLoading();
                    }
                }
            }
            await Promise.all(Array.from({ length: Math.min(3, Math.max(1, chunks.length)) }, worker));
            return results;
        }
        async function previewFile(file) {
            if (window.Swal && typeof window.Swal.fire === 'function') {
                window.Swal.fire({
                    title: 'Loading preview...',
                    text: 'Reading Excel data and checking system records.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => window.Swal.showLoading()
                });
            }
            try {
                const XLSX = await ensureSheetJs();
                const workbook = XLSX.read(await file.arrayBuffer(), { type: 'array', cellDates: true });
                const sheet = workbook.Sheets[workbook.SheetNames[0]];
                const normalRows = sheet ? XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '', raw: false }) : [];
                const developerRows = sheet ? XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '', raw: true }) : [];
                const classifications = await classifyDeveloperRows(developerRows, XLSX);
                const sourceColumnCount = developerRows.length ? (() => { const empty = developerRows[0].findIndex(value => String(value ?? '').trim() === ''); return empty >= 0 ? empty : developerRows[0].length; })() : 0;
                const existedHeader = ['Account Number', 'Agent Name', 'Legacy ID', 'Tran Date', 'Transaction ID', 'Reference ID', 'Product', 'Tran Type', 'Orig Cntry', 'Rcv Cntry', 'FX Rate trn', 'Margin', 'Base Tran Amt', 'Fee Tran Amt', 'Fx Rev Share Tran Amt', 'Comm Tran Amt', 'db_tran_date', 'db_fx_rate_trn', 'db_margin', 'db_base_tran_amt', 'db_fee_tran_amt', 'db_fx_rev_share_tran_amt', 'db_comm_tran_amt', 'Total Tran Amt', 'Settlement Currency', 'Transaction Currency'];
                const sourceAliases = [
                    ['account number', 'account_number'], ['agent name', 'agent_name'], ['legacy id', 'legacy_id'], ['tran date', 'tran_date'],
                    ['transaction id', 'transaction_id'], ['reference id', 'reference_id'], ['product'], ['tran type', 'tran_type'],
                    ['orig cntry', 'orig_cntry'], ['rcv cntry', 'rcv_cntry'], ['fx rate trn', 'fx_rate_trn', 'tran fx rate'], ['margin'],
                    ['base tran amt', 'base_tran_amt'], ['fee tran amt', 'fee_tran_amt'], ['fx rev share tran amt', 'fx_rev_share_tran_amt'],
                    ['comm tran amt', 'comm_tran_amt'], ['total tran amt', 'total_tran_amt'], ['settlement currency', 'settlement_currency'],
                    ['transaction currency', 'transaction_currency']
                ];
                const normalizedSourceHeaders = developerRows.length ? developerRows[0].map(value => normalize(value)) : [];
                function sourceValue(row, aliases) {
                    const index = normalizedSourceHeaders.findIndex(header => aliases.includes(header));
                    return index >= 0 ? row[index] ?? '' : '';
                }
                function buildExistedRow(row, database) {
                    const excelValues = sourceAliases.map(aliases => sourceValue(row, aliases));
                    return [
                        ...excelValues.slice(0, 10),
                        ...excelValues.slice(10, 16),
                        database ? database.tran_date ?? '' : '',
                        database ? database.fx_rate_trn ?? '' : '', database ? database.margin ?? '' : '',
                        database ? database.base_tran_amt ?? '' : '', database ? database.fee_tran_amt ?? '' : '',
                        database ? database.fx_rev_share_tran_amt ?? '' : '', database ? database.comm_tran_amt ?? '' : '',
                        ...excelValues.slice(16)
                    ];
                }
                const existedDeveloperRows = developerRows.length ? [existedHeader] : [];
                const nonExistedDeveloperRows = developerRows.length ? [developerRows[0].slice(0, sourceColumnCount)] : [];
                developerRows.slice(1).forEach((row, index) => {
                    const result = classifications[String(index)] || { exists: false, database: null };
                    if (result.exists) {
                        existedDeveloperRows.push(buildExistedRow(row, result.database || null));
                    } else {
                        nonExistedDeveloperRows.push(row.slice(0, sourceColumnCount));
                    }
                });
                const content = document.createElement('div');
                const modes = document.createElement('div');
                modes.className = 'settlement-daily-preview-modes';
                // modes.innerHTML = '<label><input type="radio" name="settlementDailyPreviewMode" value="normal" checked><span>Normal Mode</span></label><label><input type="radio" name="settlementDailyPreviewMode" value="developer"><span>Developer Mode</span></label>';
                const payoutTabs = document.createElement('div');
                payoutTabs.className = 'settlement-daily-payout-tabs';
                payoutTabs.hidden = true;
                payoutTabs.innerHTML = '<button type="button" class="is-active" data-view="existing">Existed Data</button><button type="button" data-view="non-existing">Non-existed Data</button>';
                const wrap = document.createElement('div');
                wrap.className = 'settlement-daily-preview-wrap';
                content.append(modes, payoutTabs, wrap);
                const developerNumericHeaders = new Set(['fx rate trn', 'margin', 'base tran amt', 'fee tran amt', 'fx rev share tran amt', 'comm tran amt', 'total tran amt', 'db_fx_rate_trn', 'db_margin', 'db_base_tran_amt', 'db_fee_tran_amt', 'db_fx_rev_share_tran_amt', 'db_comm_tran_amt']);
                let activeDeveloperView = 'existing';
                function developerRowsForView(view) { return view === 'non-existing' ? nonExistedDeveloperRows : existedDeveloperRows; }
                function comparableNumber(value) {
                    const text = String(value ?? '').trim().replace(/,/g, '');
                    return text !== '' && Number.isFinite(Number(text)) ? Number(text) : null;
                }
                function comparisonClass(row, columnIndex) {
                    if ((columnIndex < 10 || columnIndex > 15) && (columnIndex < 17 || columnIndex > 22)) return '';
                    const pairOffset = columnIndex < 16 ? columnIndex - 10 : columnIndex - 17;
                    const excelValue = comparableNumber(row[10 + pairOffset]);
                    const databaseValue = comparableNumber(row[17 + pairOffset]);
                    const databaseMissing = databaseValue === null || databaseValue === 0;
                    const excelMissing = excelValue === null || excelValue === 0;
                    if (databaseValue !== null && excelValue !== null && Math.abs(databaseValue).toFixed(2) === Math.abs(excelValue).toFixed(2)) return 'is-amount-match';
                    if (databaseMissing && excelMissing) return '';
                    if (databaseMissing !== excelMissing) {
                        const currentMissing = columnIndex < 16 ? excelMissing : databaseMissing;
                        return currentMissing ? 'is-amount-missing' : 'is-amount-present';
                    }
                    return 'is-amount-mismatch';
                }
                function renderPreview(rows, developerMode, existedMode, requestedPage) {
                    wrap.innerHTML = '';
                    if (!rows.length) {
                        wrap.textContent = 'No data rows found.';
                        return;
                    }
                    const table = document.createElement('table');
                    const thead = document.createElement('thead');
                    const tbody = document.createElement('tbody');
                    const firstEmptyHeader = rows[0].findIndex(value => String(value ?? '').trim() === '');
                    const columnCount = firstEmptyHeader >= 0 ? firstEmptyHeader : rows[0].length;
                    const normalizedHeaders = rows[0].map(value => normalize(value));
                    const firstDataRow = 1;
                    const pageSize = 500;
                    const totalDataRows = Math.max(0, rows.length - 1);
                    const totalPages = Math.max(1, Math.ceil(totalDataRows / pageSize));
                    const currentPage = Math.max(0, Math.min(totalPages - 1, Number(requestedPage || 0)));
                    if (existedMode) {
                        const groupRow = document.createElement('tr');
                        rows[0].forEach((header, columnIndex) => {
                            if (columnIndex > 10 && columnIndex < 16) return;
                            if (columnIndex > 16 && columnIndex < 23) return;
                            const th = document.createElement('th');
                            if (columnIndex === 10) { th.colSpan = 6; th.className = 'is-excel-group'; th.textContent = 'FROM SETTLEMENT PARTNER DETAIL EXCEL DATA'; }
                            else if (columnIndex === 16) { th.colSpan = 7; th.className = 'is-database-group'; th.textContent = 'FROM PARTNER DAILY DATA'; }
                            else { th.rowSpan = 2; th.textContent = header; if (developerNumericHeaders.has(normalize(header))) th.classList.add('is-numeric'); }
                            groupRow.appendChild(th);
                        });
                        const childRow = document.createElement('tr');
                        rows[0].slice(10, 23).forEach((header, index) => { const th = document.createElement('th'); th.className = index < 6 ? 'is-excel-group' : 'is-database-group'; if (developerNumericHeaders.has(normalize(header))) th.classList.add('is-numeric'); th.textContent = header; childRow.appendChild(th); });
                        thead.append(groupRow, childRow);
                    } else {
                        const headerRow = document.createElement('tr');
                        rows[0].slice(0, columnCount).forEach((header, columnIndex) => {
                            const th = document.createElement('th');
                            th.textContent = String(header ?? '');
                            if (developerMode && developerNumericHeaders.has(normalizedHeaders[columnIndex])) th.classList.add('is-numeric');
                            headerRow.appendChild(th);
                        });
                        thead.appendChild(headerRow);
                    }
                    const pageStart = firstDataRow + currentPage * pageSize;
                    rows.slice(pageStart, pageStart + pageSize).forEach((row, dataIndex) => {
                        const tr = document.createElement('tr');
                        const rowIndex = pageStart + dataIndex;
                        Array.from({ length: columnCount }, (_, columnIndex) => row[columnIndex] ?? '').forEach((value, columnIndex) => {
                            const cell = document.createElement('td');
                            if (developerMode && developerNumericHeaders.has(normalizedHeaders[columnIndex])) cell.classList.add('is-numeric');
                            if (existedMode && rowIndex > 0) {
                                const amountClass = comparisonClass(row, columnIndex);
                                if (amountClass) cell.classList.add(amountClass);
                            }
                            const numericText = String(value ?? '').trim().replace(/,/g, '');
                            const shouldFormat = developerMode && rowIndex > 0 && developerNumericHeaders.has(normalizedHeaders[columnIndex]) && numericText !== '' && Number.isFinite(Number(numericText));
                            cell.textContent = shouldFormat ? Number(numericText).toFixed(2) : String(value ?? '');
                            tr.appendChild(cell);
                        });
                        tbody.appendChild(tr);
                    });
                    if (developerMode && tbody.children.length === 0) {
                        const remarkRow = document.createElement('tr');
                        remarkRow.className = 'settlement-daily-empty-row';
                        const remarkCell = document.createElement('td');
                        remarkCell.colSpan = columnCount;
                        remarkCell.textContent = existedMode ? 'No existing data found.' : 'No non-existing data found.';
                        remarkRow.appendChild(remarkCell);
                        tbody.appendChild(remarkRow);
                    }
                    table.append(thead, tbody);
                    wrap.appendChild(table);
                    if (totalPages > 1) {
                        const pager = document.createElement('div');
                        pager.className = 'settlement-daily-preview-pager';
                        const previous = document.createElement('button');
                        previous.type = 'button'; previous.textContent = 'Previous'; previous.disabled = currentPage === 0;
                        previous.addEventListener('click', () => renderPreview(rows, developerMode, existedMode, currentPage - 1));
                        const status = document.createElement('span');
                        status.textContent = 'Rows ' + (currentPage * pageSize + 1).toLocaleString() + '–' + Math.min((currentPage + 1) * pageSize, totalDataRows).toLocaleString() + ' of ' + totalDataRows.toLocaleString();
                        const next = document.createElement('button');
                        next.type = 'button'; next.textContent = 'Next'; next.disabled = currentPage >= totalPages - 1;
                        next.addEventListener('click', () => renderPreview(rows, developerMode, existedMode, currentPage + 1));
                        pager.append(previous, status, next);
                        wrap.appendChild(pager);
                    }
                }
                renderPreview(normalRows, false, false);
                modes.querySelectorAll('input[name="settlementDailyPreviewMode"]').forEach(input => {
                    input.addEventListener('change', () => {
                        const developerMode = input.value === 'developer';
                        payoutTabs.hidden = !developerMode;
                        renderPreview(developerMode ? developerRowsForView(activeDeveloperView) : normalRows, developerMode, developerMode && activeDeveloperView === 'existing');
                    });
                });
                payoutTabs.querySelectorAll('button[data-view]').forEach(button => {
                    button.addEventListener('click', () => {
                        activeDeveloperView = button.dataset.view || 'existing';
                        payoutTabs.querySelectorAll('button').forEach(item => item.classList.toggle('is-active', item === button));
                        renderPreview(developerRowsForView(activeDeveloperView), true, activeDeveloperView === 'existing');
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
            if (!firstSheet) return { isBatchPartnerDaily: false, hasRequiredHeaders: false, hasEndMonthTranDate: false, dataRowCount: 0 };
            const rows = XLSX.utils.sheet_to_json(firstSheet, { header: 1, range: 0, blankrows: false, defval: '', raw: true });
            const firstRow = (rows[0] || []).map(value => String(value || '').trim().toLowerCase());
            const requiredHeaders = [
                ['account number', 'account_number'],
                ['agent name', 'agent_name'],
                ['legacy id', 'legacy_id'],
                ['tran date', 'tran_date'],
                ['transaction id', 'transaction_id'],
                ['reference id', 'reference_id'],
                ['product'],
                ['tran type', 'tran_type'],
                ['orig cntry', 'orig_cntry'],
                ['rcv cntry', 'rcv_cntry'],
                ['fx rate trn', 'fx_rate_trn'],
                ['fx date trn', 'fx_date_trn'],
                ['margin'],
                ['base tran amt', 'base_tran_amt'],
                ['fee tran amt', 'fee_tran_amt'],
                ['fx rev share tran amt', 'fx_rev_share_tran_amt'],
                ['comm tran amt', 'comm_tran_amt'],
                ['total tran amt', 'total_tran_amt'],
                ['settlement currency', 'settlement_currency'],
                ['transaction currency', 'transaction_currency']
            ];
            const tranDateIndex = firstRow.indexOf('tran date');
            return {
                isBatchPartnerDaily: batchHeaders.every(header => firstRow.includes(header.toLowerCase())),
                hasRequiredHeaders: requiredHeaders.every(aliases => aliases.some(header => firstRow.includes(header))),
                hasEndMonthTranDate: tranDateIndex >= 0 && rows.slice(1).some(row => isLastDayOfMonth(row[tranDateIndex], XLSX)),
                dataRowCount: rows.slice(1).filter(row => row.some(value => String(value ?? '').trim() !== '')).length
            };
        }
        function showInvalidFileAlert() {
            if (window.Swal && typeof window.Swal.fire === 'function') {
                return window.Swal.fire({ icon: 'error', title: 'Invalid File Format', confirmButtonText: 'OK', confirmButtonColor: '#dc3545' });
            }
            window.alert('Invalid File Format');
            return Promise.resolve();
        }
        function showBatchFileAlert() {
            if (window.Swal && typeof window.Swal.fire === 'function') {
                return window.Swal.fire({ icon: 'warning', title: 'Invalid File Format', confirmButtonText: 'OK', confirmButtonColor: '#dc3545' });
            }
            window.alert('Invalid File Format');
            return Promise.resolve();
        }
        function showEndMonthFileAlert() {
            const message = 'Not allowed to batch Excel file with last day of the month. Please upload it using the Settlement Detail - End Month Uploader.';
            if (window.Swal && typeof window.Swal.fire === 'function') {
                return window.Swal.fire({ icon: 'warning', title: 'File Detected', text: message, confirmButtonText: 'OK', confirmButtonColor: '#dc3545' });
            }
            window.alert(message);
            return Promise.resolve();
        }
        function showMissingEndMonthFileAlert() {
            const message = 'This is not an end-month settlement file. At least one value under the Tran Date column must be the last calendar day of its month (for example, ' + currentEndMonthExample + ').';
            if (window.Swal && typeof window.Swal.fire === 'function') {
                return window.Swal.fire({ icon: 'warning', title: 'Date Required Detected', text: message, confirmButtonText: 'OK', confirmButtonColor: '#dc3545' });
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
            const rejectedInvalidFiles = [];
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
                        if (!inspection.hasRequiredHeaders) {
                            fileRowCounts.delete(file);
                            rejectedInvalidFiles.push(file.name);
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
            if (rejectedInvalidFiles.length) await showInvalidFileAlert();
        }

        async function prepareRowsForUpload(file, XLSX) {
            const workbook = XLSX.read(await file.arrayBuffer(), { type: 'array', cellDates: true });
            const sheet = workbook.Sheets[workbook.SheetNames[0]];
            if (!sheet) return [];
            const rows = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '', raw: true });
            if (rows.length < 2) return [];
            const headers = rows[0].map(value => normalize(value));
            const aliases = {
                account_number: ['account number', 'account_number'], agent_name: ['agent name', 'agent_name'], legacy_id: ['legacy id', 'legacy_id'],
                tran_date: ['tran date', 'tran_date'], transaction_id: ['transaction id', 'transaction_id'], reference_id: ['reference id', 'reference_id'],
                product: ['product'], tran_type: ['tran type', 'tran_type'], orig_cntry: ['orig cntry', 'orig_cntry'], rcv_cntry: ['rcv cntry', 'rcv_cntry'],
                fx_rate_trn: ['fx rate trn', 'fx_rate_trn', 'tran fx rate'], fx_date_trn: ['fx date trn', 'fx_date_trn', 'fx date'], margin: ['margin'],
                base_tran_amt: ['base tran amt', 'base_tran_amt', 'base amt'], fee_tran_amt: ['fee tran amt', 'fee_tran_amt', 'fee amt'],
                fx_rev_share_tran_amt: ['fx rev share tran amt', 'fx_rev_share_tran_amt', 'fx rev share amt'], comm_tran_amt: ['comm tran amt', 'comm_tran_amt', 'comm amt'],
                total_tran_amt: ['total tran amt', 'total_tran_amt'], settlement_currency: ['settlement currency', 'settlement_currency'],
                transaction_currency: ['transaction currency', 'transaction_currency']
            };
            const indexes = {};
            Object.keys(aliases).forEach(key => { indexes[key] = headers.findIndex(header => aliases[key].includes(header)); });
            const classifications = await classifyDeveloperRows(rows, XLSX);
            const filenameSettlementDate = uploaderMode === 'daily' ? settlementDateFromFilename(file.name) : '';
            return rows.slice(1).map((row, index) => {
                if (!row.some(value => String(value ?? '').trim() !== '')) return null;
                const value = key => indexes[key] >= 0 ? row[indexes[key]] ?? '' : '';
                const result = classifications[String(index)] || { exists: false, database: null, matched_by: null };
                const normalizedTranDate = normalizeLookupDate(value('tran_date'), XLSX);
                const excelReferenceId = String(value('reference_id') ?? '').trim();
                const resolvedReferenceId = uploaderMode === 'endMonth' && excelReferenceId === '' && result.exists && result.database
                    ? String(result.database.reference_id || '').trim()
                    : excelReferenceId;
                return {
                    account_number: value('account_number'), agent_name: value('agent_name'), legacy_id: value('legacy_id'),
                    tran_date: normalizedTranDate,
                    settled_date: filenameSettlementDate && normalizedTranDate !== filenameSettlementDate ? filenameSettlementDate : '',
                    transaction_id: value('transaction_id'), reference_id: resolvedReferenceId,
                    reference_id_resolved_from_blank: uploaderMode === 'endMonth' && excelReferenceId === '' && resolvedReferenceId !== '',
                    product: value('product'), tran_type: value('tran_type'), orig_cntry: value('orig_cntry'), rcv_cntry: value('rcv_cntry'),
                    fx_rate_trn: value('fx_rate_trn'), fx_date_trn: normalizeLookupDate(value('fx_date_trn'), XLSX) || value('fx_date_trn'), margin: value('margin'),
                    base_tran_amt: value('base_tran_amt'), fee_tran_amt: value('fee_tran_amt'), fx_rev_share_tran_amt: value('fx_rev_share_tran_amt'),
                    comm_tran_amt: value('comm_tran_amt'), total_tran_amt: value('total_tran_amt'), settlement_currency: value('settlement_currency'),
                    transaction_currency: value('transaction_currency'), exists: !!result.exists,
                    db_tran_date: result.database ? result.database.tran_date || '' : ''
                };
            }).filter(row => row !== null);
        }

        async function createOrUpdateFileLog(file) {
            const formData = new FormData();
            formData.append('csrf_token', csrfToken ? csrfToken.value : '');
            formData.append('payload', JSON.stringify({
                filename: file.name,
                partner_id: partnerId.value,
                partner_name: selectedPartner()
            }));
            const response = await fetch(window.autoreconBaseUrl + '/src/controllers/excelcontrol/moneygram/settlement-file-log.php', { method: 'POST', body: formData });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload || !payload.success || Number(payload.file_log_id) <= 0) {
                throw new Error((payload && payload.error) || 'Unable to create the uploaded file log.');
            }
            return Number(payload.file_log_id);
        }

        async function saveUploadRows(rows, fileLogId) {
            const formData = new FormData();
            formData.append('csrf_token', csrfToken ? csrfToken.value : '');
            formData.append('payload', JSON.stringify({
                partner_id: partnerId.value,
                partner_name: selectedPartner(),
                upload_mode: uploaderMode,
                file_log_id: fileLogId,
                rows: rows
            }));
            const response = await fetch(window.autoreconBaseUrl + '/src/controllers/excelcontrol/moneygram/settlement-daily-save.php', { method: 'POST', body: formData });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload || !payload.success) throw new Error((payload && payload.error) || 'Unable to save settlement data.');
            return payload;
        }

        async function uploadSelectedFiles() {
            if (upload.disabled) return;
            if (!isMoneyGramPartner()) {
                if (window.Swal) await window.Swal.fire({ icon: 'warning', title: 'MoneyGram Only', text: 'This settlement upload workflow is currently available only for MoneyGram.', confirmButtonColor: '#dc3545' });
                return;
            }
            upload.disabled = true;
            const totals = { settlement_inserted: 0, settlement_updated: 0, settlement_amended: 0, written_missing_reference: 0 };
            const totalRows = files.reduce((sum, file) => sum + Number(fileRowCounts.get(file) || 0), 0);
            let processedRows = 0;
            try {
                if (window.Swal) window.Swal.fire({ title: 'Uploading settlement data...', text: 'Preparing Excel records.', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => window.Swal.showLoading() });
                const XLSX = await ensureSheetJs();
                for (const file of files) {
                    if (window.Swal) { window.Swal.update({ text: 'Recording upload log for ' + file.name + '.' }); window.Swal.showLoading(); }
                    const fileLogId = await createOrUpdateFileLog(file);
                    const preparedRows = await prepareRowsForUpload(file, XLSX);
                    for (let offset = 0; offset < preparedRows.length; offset += 750) {
                        const chunk = preparedRows.slice(offset, offset + 750);
                        if (window.Swal) { window.Swal.update({ text: 'Writing ' + processedRows.toLocaleString() + ' of ' + totalRows.toLocaleString() + ' rows.' }); window.Swal.showLoading(); }
                        const result = await saveUploadRows(chunk, fileLogId);
                        Object.keys(totals).forEach(key => { totals[key] += Number(result[key] || 0); });
                        processedRows += chunk.length;
                    }
                }
                files = [];
                fileRowCounts.clear();
                input.value = '';
                renderFiles();
                if (window.Swal) await window.Swal.fire({
                    icon: 'success', title: 'Upload Completed', confirmButtonColor: '#dc3545',
                    html: 'Settlement inserted: <b>' + totals.settlement_inserted.toLocaleString() + '</b><br>' +
                        (uploaderMode === 'endMonth'
                            ? 'Settlement amended: <b>' + totals.settlement_amended.toLocaleString() + '</b>' +
                              (totals.written_missing_reference > 0
                                  ? '<br>Blank Reference ID inserted: <b>' + totals.written_missing_reference.toLocaleString() + '</b>'
                                  : '')
                            : 'Settlement updated: <b>' + totals.settlement_updated.toLocaleString() + '</b>')
                });
            } catch (error) {
                console.error('[settlement-upload]', error);
                if (window.Swal) await window.Swal.fire({ icon: 'error', title: 'Upload Failed', text: error.message || 'Unable to upload settlement data.', confirmButtonColor: '#dc3545' });
            } finally {
                refreshState();
            }
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
        upload.addEventListener('click', uploadSelectedFiles);

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
