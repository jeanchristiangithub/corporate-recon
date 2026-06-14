<?php
?>
<section id="maintenanceSection" class="maintenance-section" aria-label="Maintenance Upload Center" style="display:none; padding:1rem">
    <div class="maintenance-inner">
        <div class="maintenance-card">
            <div class="maintenance-head">
                <h2 class="maintenance-title">Legacy ID Maintenance</h2>
                <p class="maintenance-description">For update and synchronization of legacy IDs.</p>
            </div>

            <div class="maintenance-grid">
                <div class="maintenance-sync">
                    <h3 class="maintenance-sync__title">Legacy ID Sync Tool</h3>
                    <p class="maintenance-sync__desc">Sync <strong>legacy_id</strong> from <em>MONEYGRAM partner records into branch profiles using matching branch_id</em>. Updates only missing or changed values.</p>
                    <div style="text-align:center;margin-top:18px;">
                        <button id="legacySyncBtn" type="button" class="material-btn material-btn--primary" style="min-width:220px;padding:12px 20px;font-size:16px;">Run Legacy ID Sync</button>
                    </div>
                    <p class="maintenance-sync__note" style="text-align:center;margin-top:10px;color:var(--muted);font-size:13px;">Updates only blank or different values. Matches by <code>branch_id</code>. Uses a safe transaction.</p>
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
    </style>

    <script>
    (function(){
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
            fileCount.textContent = selectedFiles.length + (selectedFiles.length === 1 ? ' file' : ' files');
            uploadBtn.disabled = busy || selectedFiles.length === 0;
            exportBtn.disabled = busy || !exportUrl;
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
            const confirmed = await showConfirmModal();
            if(!confirmed) return;
            legacyBtn.disabled = true;
            const orig = legacyBtn.textContent;
            legacyBtn.textContent = 'Updating...';
            try{
                const res = await fetch('../../controllers/maintenance/update_legacyid_moneygram.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type':'application/json' }, body: JSON.stringify({}) });
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

        syncUi();
    })();
    </script>
</section>
