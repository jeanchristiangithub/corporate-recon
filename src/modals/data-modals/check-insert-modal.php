<?php
// check-insert-modal.php
// Shared dialog modal used for alerts/confirms. Expects $modalPrefix set.
if(!isset($modalPrefix)) $modalPrefix = '';
$prefix = $modalPrefix;
$cssPath = '/autorecon/src/modals/data-modals/check-insert-modal.css';
?>
<link rel="stylesheet" href="<?= $cssPath ?>">

<!-- Generic dialog modal (replaces alert/confirm) -->
<div id="<?= $prefix ?>Dialog" class="dm-dialog" style="display:none; z-index:12010;">
    <div class="dm-box wide">
        <div class="dm-title <?= $prefix ?>DialogTitle">Message</div>
        <div class="dm-sub <?= $prefix ?>DialogMessage">...</div>
        <div class="dm-actions" style="justify-content:flex-end">
            <button class="<?= $prefix ?>DialogCancel material-btn" style="display:none">Cancel</button>
            <button class="<?= $prefix ?>DialogOk material-btn material-btn--primary">OK</button>
        </div>
    </div>
</div>
