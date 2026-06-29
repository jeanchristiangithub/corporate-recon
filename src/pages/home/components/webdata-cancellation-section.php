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

    <script>
    (function(){
        const company = document.getElementById('wdcCompany');
        const suggestions = document.getElementById('wdcCompanySuggestions');
        const uploadBtn = document.getElementById('wdcUpload');
        const dropzone = document.getElementById('wdcDropzone');
        const fileInput = document.getElementById('wdcFiles');
        const fileList = document.getElementById('wdcFileList');
        const partners = <?= json_encode($webdataCancellationPartners, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
        let files = [];
        let companyFocused = false;

        function selectedPartnerIsValid(){
            const value = company.value.trim().toLowerCase();
            return partners.some(function(partner){
                return String(partner.label || '').toLowerCase() === value;
            });
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
            fileList.innerHTML = '<div class="wdc-filecount">' + files.length + ' file(s) selected</div>';
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
                            '<span class="wdc-file-size">' + formatFileSize(file.size) + '</span>' +
                        '</div>' +
                    '</div>' +
                    '<button type="button" class="wdc-remove" data-index="' + index + '" aria-label="Remove ' + escapeHtml(file.name) + '">Remove</button>';
                list.appendChild(item);
            });
            fileList.appendChild(list);
            updateReadyState();
        }

        function setFiles(fileCollection){
            files = Array.from(fileCollection || []);
            renderFileList();
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
            const removeBtn = event.target.closest && event.target.closest('.wdc-remove');
            if(!removeBtn) return;
            const index = parseInt(removeBtn.dataset.index, 10);
            if(Number.isNaN(index)) return;
            files.splice(index, 1);
            fileInput.value = '';
            renderFileList();
        });

        uploadBtn.addEventListener('click', function(){
            if(uploadBtn.disabled) return;
            console.log('[webdata-cancellation] upload pending implementation', {
                company: company.value,
                partner: partners.find(function(partner){ return partner.label === company.value; }) || null,
                files: files.map(function(file){ return file.name; })
            });
        });

        updateReadyState();
    })();
    </script>
</section>
