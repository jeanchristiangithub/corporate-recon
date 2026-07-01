<?php
require_once __DIR__ . '/../../../config/db.php';

$partners = [];
$partnerIds = [];
try {
    $pdo = masterDataConnection();
    $stmt = $pdo->query("SELECT partner_name, partner_id FROM corpo_partner_masterfile WHERE partner_name IS NOT NULL AND partner_name <> '' ORDER BY partner_name ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $name = trim((string)($row['partner_name'] ?? ''));
        if ($name === '') continue;
        if (!in_array($name, $partners, true)) $partners[] = $name;
        if (!array_key_exists($name, $partnerIds)) {
            $partnerIds[$name] = (string)($row['partner_id'] ?? '');
        }
    }
} catch (Throwable $e) {
    $partners = [];
    $partnerIds = [];
}
?>
<section id="maintenanceSection" class="maintenance-section" aria-label="Maintenance Upload Center" style="display:none; padding:1rem">
    <div class="maintenance-inner">
        <div class="maintenance-selector">
            <label class="maintenance-filter"><span>Corporate Partner</span>
                <div class="maintenance-autocomplete">
                    <input id="maintenanceCompany" placeholder="Select corporate partner" autocomplete="off">
                    <ul class="maintenance-autocomplete-list" id="maintenanceCompanySuggestions" role="listbox" hidden></ul>
                    <datalist id="maintenanceCompanyList">
                        <?php foreach ($partners as $partner): ?>
                            <option value="<?= htmlspecialchars((string)$partner, ENT_QUOTES, 'UTF-8') ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </label>
            <label class="maintenance-filter maintenance-filter--id"><span>Partner ID</span>
                <input id="maintenancePartnerId" type="text" maxlength="4" placeholder="ID">
            </label>
            <div class="maintenance-duration" aria-label="Date duration">
                <div class="maintenance-duration__field">
                    <label for="maintenanceStartDate" class="maintenance-duration__label">Start date <span aria-hidden="true">*</span></label>
                    <input id="maintenanceStartDate" name="start_date" type="date" class="maintenance-duration__input" aria-label="Start date">
                </div>
                <span class="maintenance-duration__sep" aria-hidden="true">-</span>
                <div class="maintenance-duration__field">
                    <label for="maintenanceEndDate" class="maintenance-duration__label">End date <span aria-hidden="true">*</span></label>
                    <input id="maintenanceEndDate" name="end_date" type="date" class="maintenance-duration__input" aria-label="End date">
                </div>
            </div>
        </div>

        <div class="maintenance-card" id="moneygramLegacyMaintenanceCard" hidden>
            <div class="maintenance-head">
                <h2 class="maintenance-title">Legacy ID for MONEYGRAM</h2>
                <p class="maintenance-description">For update and synchronization of legacy IDs.</p>
            </div>

            <div class="maintenance-grid">
                <div class="maintenance-sync">
                    <h3 class="maintenance-sync__title">Legacy ID Sync Tool</h3>
                    <p class="maintenance-sync__desc">Sync <strong>legacy_id</strong> from <em>MONEYGRAM partner records into branch profiles using matching branch_id</em>. Updates only missing or changed values.</p>
                    <div style="text-align:center;margin-top:18px;">
                        <button id="legacySyncBtn" type="button" class="material-btn material-btn--primary" style="min-width:220px;padding:12px 20px;font-size:16px;">Run Legacy ID Sync</button>
                    </div>
                    <p class="maintenance-sync__note" style="text-align:center;margin-top:10px;color:var(--muted);font-size:13px;"></p>
                </div>
            </div>
        </div>
    </div>

    <div id="maintenanceUploadModal" class="maintenance-modal" role="dialog" aria-modal="true" aria-labelledby="maintenanceUploadModalTitle" aria-hidden="true">
        <div class="maintenance-modal__card">
            <h3 id="maintenanceUploadModalTitle" class="maintenance-modal__title">Upload Complete</h3>
            <p id="maintenanceUploadModalBody" class="maintenance-modal__body">0 files uploaded successfully.</p>
            <div class="maintenance-modal__actions">
                <button id="maintenanceUploadModalOk" type="button" class="material-btn material-btn--primary">OK</button>
            </div>
        </div>
    </div>

    <!-- Custom Confirm Modal -->
    <div id="maintenanceConfirmModal" class="mg-confirm-modal" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="mg-confirm-modal__overlay"></div>
        <div class="mg-confirm-modal__card" role="document">
            <div class="mg-confirm-modal__accent"></div>
            <div class="mg-confirm-modal__body">
                <h3 class="mg-confirm-modal__title">Confirm Legacy ID Sync</h3>
                <p class="mg-confirm-modal__msg">This action will update <strong>branch_profile.legacyid_moneygram</strong> using matching <em>branch_id</em> records from <em>moneygram_partner_data</em>.<br><br>Only blank or different values will be updated.</p>
                <div class="mg-confirm-modal__actions">
                    <button id="mgConfirmCancel" class="material-btn" type="button">Cancel</button>
                    <button id="mgConfirmOk" class="material-btn mg-btn-primary" type="button">Confirm Update</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="mgToast" class="mg-toast" aria-hidden="true"></div>

    <style>
    /* Confirm modal styles */
    .mg-confirm-modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:200010}
    .mg-confirm-modal.is-open{display:flex}
    .mg-confirm-modal__overlay{position:absolute;inset:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(4px)}
    .mg-confirm-modal__card{position:relative;width:min(720px,94vw);border-radius:18px;background:#fff;box-shadow:0 12px 40px rgba(15,23,42,0.15);transform:scale(.98);opacity:0;transition:all .22s cubic-bezier(.2,.9,.2,1)}
    .mg-confirm-modal.is-open .mg-confirm-modal__card{transform:scale(1);opacity:1}
    .mg-confirm-modal__accent{height:6px;background:linear-gradient(90deg,#e11d48,#ef4444);border-top-left-radius:18px;border-top-right-radius:18px}
    .mg-confirm-modal__body{padding:22px}
    .mg-confirm-modal__title{margin:0 0 8px 0;font-size:20px}
    .mg-confirm-modal__msg{margin:0 0 18px 0;color:#374151;line-height:1.4}
    .mg-confirm-modal__actions{display:flex;justify-content:flex-end;gap:12px}
    .mg-confirm-modal__actions .material-btn{min-width:120px;padding:8px 14px;border-radius:10px}
    .mg-confirm-modal__actions .mg-btn-primary{background:linear-gradient(180deg,#ef4444,#e11d48);color:#fff;border:none}

    /* Toast */
    .mg-toast{position:fixed;right:18px;bottom:18px;min-width:220px;padding:12px 16px;border-radius:8px;color:#fff;font-weight:600;display:none;z-index:200020}
    .mg-toast.show{display:block;opacity:1}
    .mg-toast.success{background:linear-gradient(90deg,#10b981,#059669)}
    .mg-toast.error{background:linear-gradient(90deg,#ef4444,#dc2626)}
    .maintenance-selector{max-width:980px;margin:12px auto 18px;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap}
    .maintenance-filter{display:flex;flex-direction:column;gap:6px;font-weight:700;color:#111827}
    .maintenance-filter span{font-size:13px}
    .maintenance-filter input{padding:8px;border-radius:6px;border:1px solid #e6eef6;box-sizing:border-box;background:#fff;color:#111}
    .maintenance-filter .maintenance-autocomplete input{min-width:60ch;width:min(100%,72ch)}
    .maintenance-filter--id input{min-width:6ch;width:6ch;text-align:center;background:#fff !important}
    #maintenancePartnerId,
    #maintenancePartnerId:hover,
    #maintenancePartnerId:focus,
    #maintenancePartnerId:active,
    #maintenancePartnerId:disabled,
    #maintenancePartnerId:-webkit-autofill{
        background:#fff !important;
        background-color:#fff !important;
        -webkit-box-shadow:0 0 0 1000px #fff inset !important;
        box-shadow:0 0 0 1000px #fff inset !important;
    }
    .maintenance-duration{display:flex;align-items:flex-end;gap:.5rem;flex-wrap:wrap}
    .maintenance-duration__field{display:flex;flex-direction:column;gap:0.25rem}
    .maintenance-duration__label{font-size:.75rem;color:#6b7280;white-space:nowrap;font-weight:700}
    .maintenance-duration__label span{color:#dc2626;margin-left:4px}
    .maintenance-duration__input{padding:8px;border-radius:6px;border:1px solid #e6eef6;background:#fff;min-width:12ch;box-sizing:border-box;font-size:.95rem;color:#111}
    .maintenance-duration__sep{color:#6b7280;font-weight:600;margin-bottom:8px}
    .maintenance-autocomplete{position:relative}
    .maintenance-autocomplete-list{position:absolute;left:0;right:0;top:calc(100% + 4px);z-index:20;margin:0;padding:4px 0;list-style:none;background:#fff;border:1px solid #e6eef6;border-radius:8px;box-shadow:0 12px 28px rgba(15,23,42,.12);max-height:220px;overflow:auto}
    .maintenance-autocomplete-item{padding:8px 10px;cursor:pointer;font-weight:500}
    .maintenance-autocomplete-item:hover,.maintenance-autocomplete-item.is-active{background:#f3f6fb}
    </style>

    <script>
    (function(){
        const company = document.getElementById('maintenanceCompany');
        const partnerIdInput = document.getElementById('maintenancePartnerId');
        const startDateInput = document.getElementById('maintenanceStartDate');
        const endDateInput = document.getElementById('maintenanceEndDate');
        const partners = <?= json_encode($partners, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
        const partnerIds = <?= json_encode($partnerIds, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
        const moneygramCard = document.getElementById('moneygramLegacyMaintenanceCard');
        const section = document.getElementById('maintenanceSection');
        const fileInput = document.getElementById('maintenanceFiles');
        const dropzone = document.getElementById('maintenanceDropzone');
        const fileList = document.getElementById('maintenanceFilesList');
        const emptyState = document.getElementById('maintenanceEmpty');
        const fileCount = document.getElementById('maintenanceFileCount');
        const uploadBtn = document.getElementById('maintenanceUploadBtn');
        const exportBtn = document.getElementById('maintenanceExportBtn');
        const clearBtn = document.getElementById('maintenanceClearBtn');
        const confirmModal = document.getElementById('maintenanceConfirmModal') || document.getElementById('maintenanceConfirmModal');
        const mgConfirmModal = document.getElementById('maintenanceConfirmModal') || document.getElementById('maintenanceConfirmModal');
        const mgToast = document.getElementById('mgToast');
        const modal = document.getElementById('maintenanceUploadModal');
        const modalBody = document.getElementById('maintenanceUploadModalBody');
        const modalOk = document.getElementById('maintenanceUploadModalOk');

        let selectedFiles = [];
        let busy = false;
        let exportUrl = '';
        let exportName = '';

        function isMoneygramPartner(value){
            return String(value || '').trim().toUpperCase() === 'MONEYGRAM';
        }

        function updateMaintenanceCardVisibility(){
            if(!moneygramCard || !company) return;
            moneygramCard.hidden = !isMoneygramPartner(company.value);
        }

        function updatePartnerIdField(){
            if(!partnerIdInput || !company) return;
            const selected = String(company.value || '').trim();
            let id = '';
            if(selected){
                const exactName = (partners || []).find(name => String(name || '').trim().toLowerCase() === selected.toLowerCase());
                if(exactName && Object.prototype.hasOwnProperty.call(partnerIds, exactName)){
                    id = partnerIds[exactName] || '';
                }
            }
            partnerIdInput.value = id;
            updateMaintenanceCardVisibility();
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
            const partnerName = findPartnerNameById(partnerIdInput.value);
            company.value = partnerName || '';
            updateMaintenanceCardVisibility();
        }

        function syncEndDateFromStart(){
            if(!startDateInput || !endDateInput) return;
            endDateInput.value = startDateInput.value || '';
        }

        function attachPartnerAutocomplete(input, suggestions){
            const container = input ? input.closest('.maintenance-autocomplete') : null;
            const list = container ? container.querySelector('.maintenance-autocomplete-list') : null;
            if(!input || !container || !list) return;

            let activeIndex = -1;
            function normalize(value){ return String(value || '').trim().toLowerCase(); }
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
                updatePartnerIdField();
            }
            function renderSuggestions(){
                const matches = getMatches(input.value);
                if(matches.length === 0){ closeSuggestions(); return; }
                list.innerHTML = '';
                matches.forEach((match, index) => {
                    const item = document.createElement('li');
                    item.className = 'maintenance-autocomplete-item';
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
            input.addEventListener('input', function(){ renderSuggestions(); updatePartnerIdField(); });
            input.addEventListener('focus', renderSuggestions);
            input.addEventListener('keydown', function(event){
                const items = Array.from(list.querySelectorAll('.maintenance-autocomplete-item'));
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

        attachPartnerAutocomplete(company, partners);
        if(partnerIdInput) partnerIdInput.addEventListener('input', updateCompanyFromPartnerId);
        if(startDateInput) startDateInput.addEventListener('change', syncEndDateFromStart);
        updateMaintenanceCardVisibility();

        function formatSize(bytes){
            const size = Number(bytes || 0);
            if(!isFinite(size) || size < 1024) return size + ' B';
            const units = ['KB', 'MB', 'GB'];
            let value = size / 1024;
            let index = 0;
            while(value >= 1024 && index < units.length - 1){
                value /= 1024;
                index++;
            }
            return value.toFixed(value >= 10 || index === 0 ? 0 : 1) + ' ' + units[index];
        }

        function openModal(message){
            if(modalBody){
                modalBody.textContent = message;
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
            } else {
                showToast('error', message);
            }
        }

        function closeModal(){
            if(modal) modal.classList.remove('is-open');
            if(modal) modal.setAttribute('aria-hidden', 'true');
        }

        function showToast(type, message){
            if(!mgToast) return;
            mgToast.textContent = message;
            mgToast.className = 'mg-toast show ' + (type === 'success' ? 'success' : 'error');
            mgToast.setAttribute('aria-hidden', 'false');
            setTimeout(() => { if(mgToast) mgToast.classList.remove('show'); mgToast.setAttribute('aria-hidden', 'true'); }, 4000);
        }

        function showRequiredDurationAlert(){
            if(window.Swal && typeof window.Swal.fire === 'function'){
                window.Swal.fire({
                    icon: 'warning',
                    title: 'Duration date required',
                    text: 'Please select the start date and end date before running the legacy ID sync.',
                    confirmButtonColor: '#dc3545'
                });
                return;
            }
            showToast('error', 'Duration date is required.');
        }

        function showInvalidDurationAlert(){
            if(window.Swal && typeof window.Swal.fire === 'function'){
                window.Swal.fire({
                    icon: 'warning',
                    text: 'Start Date cannot be greater than End Date.',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#dc3545'
                });
                return;
            }
            showToast('error', 'Start Date cannot be greater than End Date.');
        }

        function showConfirmModal(){
            return new Promise((resolve) => {
                const wrapper = document.getElementById('maintenanceConfirmModal');
                if(!wrapper) return resolve(false);
                const cancelBtn = document.getElementById('mgConfirmCancel');
                const okBtn = document.getElementById('mgConfirmOk');
                wrapper.classList.add('is-open');
                wrapper.setAttribute('aria-hidden', 'false');
                function cleanup(){
                    wrapper.classList.remove('is-open');
                    wrapper.setAttribute('aria-hidden', 'true');
                    if(cancelBtn) cancelBtn.removeEventListener('click', onCancel);
                    if(okBtn) okBtn.removeEventListener('click', onConfirm);
                }
                function onCancel(){ cleanup(); resolve(false); }
                function onConfirm(){ cleanup(); resolve(true); }
                if(cancelBtn) cancelBtn.addEventListener('click', onCancel);
                if(okBtn) okBtn.addEventListener('click', onConfirm);
            });
        }

        function syncUi(){
            if(fileCount) fileCount.textContent = selectedFiles.length + (selectedFiles.length === 1 ? ' file' : ' files');
            if(uploadBtn) uploadBtn.disabled = busy || selectedFiles.length === 0;
            if(exportBtn) exportBtn.disabled = busy || !exportUrl;
            if(!emptyState || !fileList) return;
            if(selectedFiles.length === 0){
                emptyState.hidden = false;
                fileList.hidden = true;
                fileList.innerHTML = '';
                return;
            }

            emptyState.hidden = true;
            fileList.hidden = false;
            fileList.innerHTML = '';

            selectedFiles.forEach((file, index) => {
                const item = document.createElement('li');
                item.className = 'maintenance-file';
                item.innerHTML = [
                    '<div class="maintenance-file__meta">',
                    '<div class="maintenance-file__name" title="' + file.name.replace(/"/g, '&quot;') + '">' + file.name + '</div>',
                    '<div class="maintenance-file__size">' + formatSize(file.size) + '</div>',
                    '</div>',
                    '<button type="button" class="maintenance-file__remove" aria-label="Remove ' + file.name.replace(/"/g, '&quot;') + '">',
                    '<span class="material-icons" aria-hidden="true">close</span>',
                    '</button>'
                ].join('');
                item.querySelector('.maintenance-file__remove').addEventListener('click', function(){
                    selectedFiles.splice(index, 1);
                    syncUi();
                });
                fileList.appendChild(item);
            });
        }

        function addFiles(files){
            if(!fileList || !emptyState) return;
            const incoming = Array.from(files || []);
            if(incoming.length === 0) return;
            exportUrl = '';
            exportName = '';
            const allowed = ['xlsx', 'xls', 'csv', 'txt'];
            incoming.forEach((file) => {
                const ext = String(file.name || '').split('.').pop().toLowerCase();
                if(allowed.indexOf(ext) === -1) return;
                const duplicate = selectedFiles.some(existing => existing.name === file.name && existing.size === file.size && existing.lastModified === file.lastModified);
                if(!duplicate) selectedFiles.push(file);
            });
            syncUi();
        }

        async function uploadFiles(){
            if(!fileInput || !fileList || !emptyState) return;
            if(busy || selectedFiles.length === 0) return;
            busy = true;
            syncUi();

            const fd = new FormData();
            selectedFiles.forEach((file) => fd.append('files[]', file, file.name));

            try{
                const res = await fetch('../../controllers/maintenance_upload.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd
                });
                const text = await res.text();
                let json = null;
                try{ json = JSON.parse(text); }catch(e){ json = null; }

                if(!res.ok || !json || !json.success){
                    const message = (json && json.error) ? json.error : 'Upload failed.';
                    openModal(message);
                    return;
                }

                const uploaded = Number(json.uploaded || 0);
                const failed = Number(json.failed || 0);
                exportUrl = json.download_url || '';
                exportName = json.download_name || '';
                const details = [
                    'Files processed successfully.',
                    'Branch ID column added.',
                    uploaded + ' file' + (uploaded === 1 ? '' : 's') + ' uploaded successfully.'
                ];
                if(failed > 0) details.push(failed + ' file' + (failed === 1 ? '' : 's') + ' failed.');
                details.push('Ready for export.');
                openModal(details.join(' '));
                selectedFiles = [];
                syncUi();
            }catch(err){
                console.error('Maintenance upload failed', err);
                openModal('Upload failed. ' + (err && err.message ? err.message : 'Please try again.'));
            }finally{
                busy = false;
                syncUi();
            }
        }

        // Legacy ID Sync handler
        const legacyBtn = document.getElementById('legacySyncBtn');
        async function runLegacySync(){
            if(!legacyBtn) return;
            if(!startDateInput || !endDateInput || !startDateInput.value || !endDateInput.value){
                showRequiredDurationAlert();
                return;
            }
            if(startDateInput.value > endDateInput.value){
                showInvalidDurationAlert();
                return;
            }
            const confirmed = await showConfirmModal();
            if(!confirmed) return;
            legacyBtn.disabled = true;
            const orig = legacyBtn.textContent;
            legacyBtn.textContent = 'Updating...';
            try{
                const res = await fetch('../../controllers/maintenance/update_legacyid_moneygram.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type':'application/json' },
                    body: JSON.stringify({
                        start_date: startDateInput.value,
                        end_date: endDateInput.value
                    })
                });
                const json = await res.json().catch(()=>null);
                if(!res.ok || !json || !json.success){
                    const msg = (json && json.error) ? json.error : 'Update failed.';
                    showToast('error', msg);
                } else {
                    if((json.rows_updated || 0) > 0){
                        showToast('success', 'Legacy IDs updated successfully. Rows affected: ' + (json.rows_updated || 0));
                    } else {
                        showToast('success', 'No legacy IDs needed updating.');
                    }
                }
            }catch(err){
                showToast('error', 'Update failed. ' + (err && err.message ? err.message : 'Please try again.'));
            }finally{
                legacyBtn.disabled = false;
                legacyBtn.textContent = orig;
            }
        }
        if(legacyBtn) legacyBtn.addEventListener('click', runLegacySync);

        if(dropzone){
            dropzone.addEventListener('click', function(){
                if(fileInput) fileInput.click();
            });
            dropzone.addEventListener('keydown', function(event){
                if(event.key === 'Enter' || event.key === ' '){
                    event.preventDefault();
                    if(fileInput) fileInput.click();
                }
            });
            ['dragenter', 'dragover'].forEach(function(eventName){
                dropzone.addEventListener(eventName, function(event){
                    event.preventDefault();
                    event.stopPropagation();
                    dropzone.classList.add('is-dragover');
                });
            });
            ['dragleave', 'drop'].forEach(function(eventName){
                dropzone.addEventListener(eventName, function(event){
                    event.preventDefault();
                    event.stopPropagation();
                    dropzone.classList.remove('is-dragover');
                });
            });
            dropzone.addEventListener('drop', function(event){
                if(event.dataTransfer && event.dataTransfer.files){
                    addFiles(event.dataTransfer.files);
                }
            });
        }

        if(uploadBtn){
            uploadBtn.addEventListener('click', uploadFiles);
        }

        if(clearBtn){
            clearBtn.addEventListener('click', function(){
                selectedFiles = [];
                exportUrl = '';
                exportName = '';
                syncUi();
            });
        }

        if(exportBtn){
            exportBtn.addEventListener('click', function(){
                if(!exportUrl || busy) return;
                const link = document.createElement('a');
                link.href = exportUrl;
                if(exportName) link.download = exportName;
                document.body.appendChild(link);
                link.click();
                link.remove();
            });
        }

        if(modalOk){
            modalOk.addEventListener('click', closeModal);
        }

        if(modal){
            modal.addEventListener('click', function(event){
                if(event.target === modal) closeModal();
            });
        }

        if(fileCount || fileList || emptyState || uploadBtn || exportBtn){
            syncUi();
        }
    })();
    </script>
</section>
