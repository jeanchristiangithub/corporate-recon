<?php
require_once __DIR__ . '/../../../config/db.php';

$webdataCancellationPartners = [];
try {
    $pdo = masterDataConnection();
    $stmt = $pdo->query("SELECT DISTINCT partner_name FROM corpo_partner_masterfile WHERE partner_name IS NOT NULL AND partner_name <> '' ORDER BY partner_name ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $name = trim((string)($row['partner_name'] ?? ''));
        if ($name !== '') $webdataCancellationPartners[] = $name;
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

        function selectedPartnerIsValid(){
            const value = company.value.trim().toLowerCase();
            return partners.some(function(partner){ return String(partner).toLowerCase() === value; });
        }

        function updateReadyState(){
            const ready = selectedPartnerIsValid();
            dropzone.classList.toggle('wdc-dropzone--disabled', !ready);
            uploadBtn.disabled = !(ready && files.length > 0);
        }

        function closeSuggestions(){
            suggestions.hidden = true;
            suggestions.innerHTML = '';
        }

        function renderSuggestions(){
            const query = company.value.trim().toLowerCase();
            const matches = partners.filter(function(partner){
                return !query || String(partner).toLowerCase().includes(query);
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
                item.textContent = partner;
                item.addEventListener('mousedown', function(event){
                    event.preventDefault();
                    company.value = partner;
                    closeSuggestions();
                    updateReadyState();
                });
                suggestions.appendChild(item);
            });
            suggestions.hidden = false;
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
        company.addEventListener('focus', renderSuggestions);
        company.addEventListener('blur', function(){ setTimeout(closeSuggestions, 120); });

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

        uploadBtn.addEventListener('click', function(){
            if(uploadBtn.disabled) return;
            console.log('[webdata-cancellation] upload pending implementation', {
                company: company.value,
                files: files.map(function(file){ return file.name; })
            });
        });

        updateReadyState();
    })();
    </script>
</section>
