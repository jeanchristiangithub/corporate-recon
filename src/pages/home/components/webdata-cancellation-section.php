<?php
require_once __DIR__ . '/../../../config/db.php';

$webdataCancellationPartners = [];
try {
    $pdo = masterDataConnection();
    $stmt = $pdo->query("SELECT partner_id, partner_name FROM corpo_partner_masterfile WHERE partner_name IS NOT NULL AND partner_name <> '' ORDER BY partner_name ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $id = trim((string)($row['partner_id'] ?? ''));
        $name = trim((string)($row['partner_name'] ?? ''));
        if ($name === '') continue;
        $label = ($id !== '' ? $id . ' - ' : '') . $name;
        $webdataCancellationPartners[] = [
            'id' => $id,
            'name' => $name,
            'label' => $label,
        ];
    }
} catch (Throwable $e) {
    $webdataCancellationPartners = [];
}
?>
<section id="webdataCancellationSection" class="webdata-cancellation-section" aria-label="KPX Web Data Cancellation" style="display:none; padding:1rem">
    <div class="webdata-cancellation-inner">
        <h2 class="webdata-cancellation-title">KPX Web Data (CANCELLATION)</h2>

        <div class="webdata-cancellation-filters">
            <div class="webdata-cancellation-filters-left">
                <label class="wdc-filter"><span>Corporate Partner</span>
                    <div class="wdc-autocomplete-field">
                        <input id="wdcCompany" placeholder="Select corporate partner" autocomplete="off">
                        <ul class="wdc-autocomplete-list" id="wdcCompanySuggestions" role="listbox" hidden></ul>
                    </div>
                </label>
            </div>
            <div class="webdata-cancellation-actions">
                <button id="wdcUpload" class="material-btn material-btn--primary" disabled>Upload</button>
            </div>
        </div>

        <div class="wdc-dropwrap">
            <div id="wdcDropzone" class="wdc-dropzone wdc-dropzone--disabled" tabindex="0">
                <div class="wdc-drop-inner">
                    <span class="material-icons" aria-hidden="true">cloud_upload</span>
                    <p class="wdc-drop-text">Drag and drop files here<br>or<br>Click to browse files</p>
                    <p class="wdc-drop-hint">Supports multiple files</p>
                </div>
                <input id="wdcFiles" type="file" multiple accept=".xls,.xlsx,.xlsm,.xlsb,.ods,.csv,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel.sheet.macroEnabled,application/vnd.ms-excel.sheet.binary.macroEnabled,application/vnd.oasis.opendocument.spreadsheet" style="display:none" />
            </div>

            <div class="wdc-filelist" id="wdcFileList" aria-live="polite" style="display:none">
                <div class="wdc-empty">No files selected</div>
            </div>
        </div>
    </div>

    <div id="wdcPreviewModal" class="wdc-preview-modal" role="dialog" aria-modal="true" aria-labelledby="wdcPreviewModalTitle" aria-hidden="true">
        <div class="wdc-preview-modal__panel">
            <div class="wdc-preview-modal__header">
                <h3 id="wdcPreviewModalTitle" class="wdc-preview-modal__title"></h3>
                <button type="button" id="wdcPreviewModalClose" class="wdc-preview-modal__close" aria-label="Close">
                    <span class="material-icons" aria-hidden="true">close</span>
                </button>
            </div>
            <div class="wdc-preview-modal__body"></div>
        </div>
    </div>

    <script>
    (function(){
        const company = document.getElementById('wdcCompany');
        const suggestions = document.getElementById('wdcCompanySuggestions');
        const uploadBtn = document.getElementById('wdcUpload');
        const dropzone = document.getElementById('wdcDropzone');
        const fileInput = document.getElementById('wdcFiles');
        const fileList = document.getElementById('wdcFileList');
        const previewModal = document.getElementById('wdcPreviewModal');
        const previewModalTitle = document.getElementById('wdcPreviewModalTitle');
        const previewModalClose = document.getElementById('wdcPreviewModalClose');
        const previewModalBody = previewModal.querySelector('.wdc-preview-modal__body');
        const partners = <?= json_encode($webdataCancellationPartners, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
        let files = [];
        let fileRowCounts = new Map();
        let companyFocused = false;

        function selectedPartnerIsValid(){
            const value = company.value.trim().toLowerCase();
            return partners.some(function(partner){
                return String(partner.label || '').toLowerCase() === value;
            });
        }

        function selectedPartner(){
            const value = company.value.trim().toLowerCase();
            return partners.find(function(partner){
                return String(partner.label || '').toLowerCase() === value;
            }) || null;
        }

        function updateReadyState(){
            const ready = selectedPartnerIsValid();
            const hasInput = companyFocused || company.value.trim() !== '';
            dropzone.classList.toggle('wdc-dropzone--disabled', !ready);
            dropzone.classList.toggle('wdc-dropzone--input-active', hasInput);
            uploadBtn.classList.toggle('wdc-upload--input-active', hasInput);
            uploadBtn.disabled = !(ready && files.length > 0);
        }

        function closeSuggestions(){
            suggestions.hidden = true;
            suggestions.innerHTML = '';
        }

        function renderSuggestions(){
            const query = company.value.trim().toLowerCase();
            const matches = partners.filter(function(partner){
                const id = String(partner.id || '').toLowerCase();
                const name = String(partner.name || '').toLowerCase();
                const label = String(partner.label || '').toLowerCase();
                return !query || id.includes(query) || name.includes(query) || label.includes(query);
            }).slice(0, 30);

            suggestions.innerHTML = '';
            if(matches.length === 0){
                closeSuggestions();
                return;
            }

            matches.forEach(function(partner){
                const item = document.createElement('li');
                item.className = 'wdc-autocomplete-item';
                item.setAttribute('role', 'option');
                item.textContent = partner.label;
                item.addEventListener('mousedown', function(event){
                    event.preventDefault();
                    company.value = partner.label;
                    closeSuggestions();
                    updateReadyState();
                });
                suggestions.appendChild(item);
            });
            suggestions.hidden = false;
        }

        function escapeHtml(value){
            return String(value).replace(/[&<>"']/g, function(character){
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[character];
            });
        }

        function formatFileSize(bytes){
            if(!Number.isFinite(bytes) || bytes <= 0) return '0 KB';
            const units = ['B', 'KB', 'MB', 'GB'];
            let size = bytes;
            let unitIndex = 0;
            while(size >= 1024 && unitIndex < units.length - 1){
                size = size / 1024;
                unitIndex++;
            }
            return (unitIndex === 0 ? size : size.toFixed(size >= 10 ? 1 : 2)) + ' ' + units[unitIndex];
        }

        function renderFileList(){
            if(files.length === 0){
                fileList.style.display = 'none';
                fileList.innerHTML = '<div class="wdc-empty">No files selected</div>';
                updateReadyState();
                return;
            }

            fileList.style.display = '';
            fileList.innerHTML = '';
            const list = document.createElement('ul');
            list.className = 'wdc-files-ul';
            files.forEach(function(file, index){
                const item = document.createElement('li');
                item.className = 'wdc-file-item';
                item.innerHTML =
                    '<div class="wdc-file-main">' +
                        '<span class="material-icons" aria-hidden="true">description</span>' +
                        '<div class="wdc-file-meta">' +
                            '<span class="wdc-file-name">' + escapeHtml(file.name) + '</span>' +
                            '<span class="wdc-file-size">' + getFileRowCountLabel(index, file) + '</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="wdc-file-actions">' +
                        '<button type="button" class="wdc-view" data-index="' + index + '" aria-label="View ' + escapeHtml(file.name) + '" title="View file">' +
                            '<span class="material-icons" aria-hidden="true">visibility</span>' +
                        '</button>' +
                        '<button type="button" class="wdc-remove" data-index="' + index + '" aria-label="Remove ' + escapeHtml(file.name) + '">Remove</button>' +
                    '</div>';
                list.appendChild(item);
            });
            fileList.appendChild(list);
            updateReadyState();
        }

        function setFiles(fileCollection){
            files = Array.from(fileCollection || []);
            fileRowCounts = new Map();
            renderFileList();
            files.forEach(function(file, index){
                detectCancellationRowCount(file).then(function(count){
                    if(files[index] !== file) return;
                    fileRowCounts.set(file, count);
                    renderFileList();
                }).catch(function(error){
                    console.warn('[webdata-cancellation] failed to detect row count', error);
                });
            });
        }

        function getFileRowCountLabel(index, file){
            if(fileRowCounts.has(file)){
                const count = fileRowCounts.get(file);
                return count === 1 ? '1 row' : count + ' rows';
            }
            return 'Counting rows...';
        }

        function parseCsvLine(line, delimiter){
            const result = [];
            let current = '';
            let inQuotes = false;
            for(let i = 0; i < line.length; i++){
                const character = line[i];
                const next = line[i + 1];
                if(character === '"'){
                    if(inQuotes && next === '"'){
                        current += '"';
                        i++;
                    } else {
                        inQuotes = !inQuotes;
                    }
                } else if(character === delimiter && !inQuotes){
                    result.push(current);
                    current = '';
                } else {
                    current += character;
                }
            }
            result.push(current);
            return result;
        }

        function detectCsvDelimiter(text){
            const delimiters = [',', ';', '\t', '|'];
            const counts = new Map(delimiters.map(function(delimiter){ return [delimiter, 0]; }));
            text.split(/\r\n|\r|\n/).slice(0, 10).forEach(function(line){
                if(!line.trim()) return;
                delimiters.forEach(function(delimiter){
                    counts.set(delimiter, counts.get(delimiter) + (line.split(delimiter).length - 1));
                });
            });
            return delimiters.sort(function(a, b){ return counts.get(b) - counts.get(a); })[0] || ',';
        }

        async function detectCancellationRowCount(file){
            if(!/\.csv$/i.test(file.name || '')) return 0;
            const text = await file.text();
            const delimiter = detectCsvDelimiter(text);
            const lines = text.split(/\r\n|\r|\n/);
            const rowFour = parseCsvLine(lines[3] || '', delimiter);
            const detectedHeader = String(rowFour[3] || '').trim().toUpperCase();
            if(detectedHeader !== 'DATE CLAIMED' && detectedHeader !== 'DATE SEND') return 0;

            let count = 0;
            for(let index = 4; index < lines.length; index++){
                const values = parseCsvLine(lines[index] || '', delimiter);
                const hasValue = values.some(function(value){ return String(value || '').trim() !== ''; });
                if(!hasValue) break;
                count++;
            }
            return count;
        }

        async function openPreviewModal(file){
            previewModalTitle.textContent = file && file.name ? file.name : '';
            previewModalBody.innerHTML = '<div class="wdc-preview-modal__loading">Loading system table...</div>';
            previewModal.classList.add('is-open');
            previewModal.setAttribute('aria-hidden', 'false');
            try{ document.body.style.overflow = 'hidden'; }catch(e){}
            previewModalClose.focus();

            try{
                const formData = new FormData();
                formData.append('file', file);
                const url = (window.autoreconBaseUrl || '') + '/src/controllers/excelcontrol/cancellation/moneygram/moneygram-cancellation-viewer.php';
                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });
                previewModalBody.innerHTML = await response.text();
            }catch(error){
                console.error('[webdata-cancellation] failed to load cancellation viewer', error);
                previewModalBody.innerHTML = '<div class="wdc-preview-modal__error">Failed to load system table result.</div>';
            }
        }

        function closePreviewModal(){
            previewModal.classList.remove('is-open');
            previewModal.setAttribute('aria-hidden', 'true');
            previewModalTitle.textContent = '';
            previewModalBody.innerHTML = '';
            try{ document.body.style.overflow = ''; }catch(e){}
        }

        company.addEventListener('input', function(){
            renderSuggestions();
            updateReadyState();
        });
        company.addEventListener('focus', function(){
            companyFocused = true;
            renderSuggestions();
            updateReadyState();
        });
        company.addEventListener('blur', function(){
            companyFocused = false;
            updateReadyState();
            setTimeout(closeSuggestions, 120);
        });

        dropzone.addEventListener('click', function(){
            if(!selectedPartnerIsValid()) return;
            fileInput.click();
        });
        dropzone.addEventListener('dragover', function(event){
            if(!selectedPartnerIsValid()) return;
            event.preventDefault();
            dropzone.classList.add('wdc-dropzone--over');
        });
        dropzone.addEventListener('dragleave', function(){
            dropzone.classList.remove('wdc-dropzone--over');
        });
        dropzone.addEventListener('drop', function(event){
            if(!selectedPartnerIsValid()) return;
            event.preventDefault();
            dropzone.classList.remove('wdc-dropzone--over');
            setFiles(event.dataTransfer.files);
        });
        fileInput.addEventListener('change', function(){
            setFiles(fileInput.files);
        });
        fileList.addEventListener('click', function(event){
            const viewBtn = event.target.closest && event.target.closest('.wdc-view');
            if(viewBtn){
                const index = parseInt(viewBtn.dataset.index, 10);
                if(Number.isNaN(index) || !files[index]) return;
                openPreviewModal(files[index]);
                return;
            }

            const removeBtn = event.target.closest && event.target.closest('.wdc-remove');
            if(!removeBtn) return;
            const index = parseInt(removeBtn.dataset.index, 10);
            if(Number.isNaN(index)) return;
            files.splice(index, 1);
            fileInput.value = '';
            renderFileList();
        });

        function showUploadLoading(){
            if(window.Swal){
                Swal.fire({
                    title: 'Uploading...',
                    html: '<div class="wdc-upload-progress"><div></div></div>',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: function(){ Swal.showLoading(); }
                });
            }
        }

        function showUploadComplete(inserted){
            if(window.Swal){
                Swal.fire({
                    icon: 'success',
                    title: 'Completed Successfully',
                    text: inserted + (inserted === 1 ? ' row uploaded.' : ' rows uploaded.'),
                    confirmButtonText: 'OK'
                });
            } else {
                alert('Completed Successfully');
            }
        }

        function showUploadFailed(message){
            if(window.Swal){
                Swal.fire({
                    icon: 'error',
                    title: 'Upload Failed',
                    text: message || 'Please try again.',
                    confirmButtonText: 'OK'
                });
            } else {
                alert('Upload Failed: ' + (message || 'Please try again.'));
            }
        }

        async function uploadCancellationFiles(){
            if(uploadBtn.disabled) return;
            const partner = selectedPartner();
            if(!partner){
                showUploadFailed('Please select a valid corporate partner.');
                return;
            }

            const formData = new FormData();
            formData.append('partner_id', partner.id || '');
            formData.append('partnerName', partner.name || '');
            files.forEach(function(file){ formData.append('files[]', file); });

            uploadBtn.disabled = true;
            showUploadLoading();
            try{
                const response = await fetch((window.autoreconBaseUrl || '') + '/src/controllers/excelcontrol/cancellation/moneygram/moneygram-cancellation-upload.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });
                const json = await response.json().catch(function(){ return null; });
                if(!response.ok || !json || !json.success){
                    throw new Error((json && json.error) ? json.error : 'Upload failed.');
                }
                files = [];
                fileInput.value = '';
                fileRowCounts = new Map();
                renderFileList();
                showUploadComplete(Number(json.inserted || 0));
            }catch(error){
                console.error('[webdata-cancellation] upload failed', error);
                showUploadFailed(error && error.message ? error.message : 'Upload failed.');
            }finally{
                updateReadyState();
            }
        }

        uploadBtn.addEventListener('click', function(){
            uploadCancellationFiles();
        });
        previewModalClose.addEventListener('click', closePreviewModal);
        previewModal.addEventListener('click', function(event){
            if(event.target === previewModal) closePreviewModal();
        });
        document.addEventListener('keydown', function(event){
            if(event.key === 'Escape' && previewModal.classList.contains('is-open')) closePreviewModal();
        });

        updateReadyState();
    })();
    </script>
</section>
