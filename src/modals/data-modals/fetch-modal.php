<?php
// fetch-modal.php
// Shared fetch/processing modals for data uploads. Expects $modalPrefix (e.g. 'mbtc' or 'pd') to be set.
if(!isset($modalPrefix)) $modalPrefix = '';
$prefix = $modalPrefix;
$cssPath = '/autorecon/src/modals/data-modals/fetch-modal.css';
?>
<link rel="stylesheet" href="<?= $cssPath ?>">

<!-- Processing overlay -->
<div id="<?= $prefix ?>Overlay" class="dm-overlay" style="display:none;">
    <div class="dm-box">
        <div class="dm-title">Processing files</div>
        <div id="<?= $prefix ?>ProgressText" class="dm-sub">Extracted 0 of 0 files</div>
        <div class="dm-barwrap"><div id="<?= $prefix ?>ProgressBar" class="dm-bar"></div></div>
        <div style="text-align:right;margin-top:0.5rem"><button id="<?= $prefix ?>CancelBtn" class="material-btn">Cancel</button></div>
    </div>
</div>

<!-- Confirm modal before fetching -->
<div id="<?= $prefix ?>ConfirmModal" class="dm-modal" style="display:none;">
    <div class="dm-box wide">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem">
            <div>
                <div class="dm-title">Confirm Preview</div>
                <div id="<?= $prefix ?>ConfirmMeta" class="dm-sub">Files selected: <span id="<?= $prefix ?>ConfirmCount"></span><span id="<?= $prefix ?>ConfirmCompany" style="display:none"></span></div>
            </div>
            <div><button id="<?= $prefix ?>FetchBtn" class="material-btn material-btn--primary">Preview</button></div>
        </div>
        <div class="dm-listwrap"><ul id="<?= $prefix ?>ConfirmList" class="dm-list"></ul></div>
        <div style="text-align:right;margin-top:0.5rem"><button id="<?= $prefix ?>ConfirmCancel" class="material-btn">Close</button></div>
    </div>
</div>

<!-- Delete confirmation modal -->
<div id="<?= $prefix ?>DeleteConfirmModal" class="dm-modal" style="display:none;">
    <div class="dm-box">
        <div class="dm-title">Confirm delete</div>
        <div id="<?= $prefix ?>DeleteConfirmText" class="dm-sub">Are you sure you want to delete this extracted file?</div>
        <div class="dm-actions">
            <button id="<?= $prefix ?>DeleteCancel" class="material-btn">Cancel</button>
            <button id="<?= $prefix ?>DeleteConfirm" class="material-btn material-btn--danger">Delete</button>
        </div>
    </div>
</div>

<!-- Select company first modal -->
<div id="<?= $prefix ?>SelectCompanyModal" class="dm-modal" style="display:none;">
    <div class="dm-box">
        <div class="dm-title">Select a company first</div>
        <div class="dm-sub">Please select a company from the Company dropdown before uploading or dropping files.</div>
        <div style="text-align:right"><button id="<?= $prefix ?>SelectCompanyClose" class="material-btn">Close</button></div>
    </div>
</div>
