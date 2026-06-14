<?php
// Admin users list
require_once __DIR__ . '/../../../controllers/usercontroller.php';

try {
    $uc = new UserController();
    $users = $uc->getAllUsers();
} catch (Throwable $e) {
    $users = [];
}
?>
<section class="all-users-root">
    <h2>All Users</h2>
    <?php
        $userDelError = $_SESSION['user_delete_error'] ?? '';
        $userDelSuccess = $_SESSION['user_delete_success'] ?? '';
        $userResetError = $_SESSION['user_reset_error'] ?? '';
        $userResetSuccess = $_SESSION['user_reset_success'] ?? '';
        $userStatusError = $_SESSION['user_status_error'] ?? '';
        $userStatusSuccess = $_SESSION['user_status_success'] ?? '';
        unset($_SESSION['user_delete_error'], $_SESSION['user_delete_success'], $_SESSION['user_reset_error'], $_SESSION['user_reset_success'], $_SESSION['user_status_error'], $_SESSION['user_status_success']);
    ?>
    <?php if ($userDelError !== ''): ?>
        <div style="margin:0.6rem 0;padding:0.6rem;background:#ffe6e6;border:1px solid #f5c2c2;border-radius:6px;color:#8b1e1e"><?= htmlspecialchars($userDelError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($userDelSuccess !== ''): ?>
        <div style="margin:0.6rem 0;padding:0.6rem;background:#ecfdf5;border:1px solid #bbf7d0;border-radius:6px;color:#065f46"><?= htmlspecialchars($userDelSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($userResetError !== ''): ?>
        <div style="margin:0.6rem 0;padding:0.6rem;background:#ffe6e6;border:1px solid #f5c2c2;border-radius:6px;color:#8b1e1e"><?= htmlspecialchars($userResetError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($userResetSuccess !== ''): ?>
        <div style="margin:0.6rem 0;padding:0.6rem;background:#ecfdf5;border:1px solid #bbf7d0;border-radius:6px;color:#065f46"><?= htmlspecialchars($userResetSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($userStatusError !== ''): ?>
        <div style="margin:0.6rem 0;padding:0.6rem;background:#ffe6e6;border:1px solid #f5c2c2;border-radius:6px;color:#8b1e1e"><?= htmlspecialchars($userStatusError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($userStatusSuccess !== ''): ?>
        <div style="margin:0.6rem 0;padding:0.6rem;background:#ecfdf5;border:1px solid #bbf7d0;border-radius:6px;color:#065f46"><?= htmlspecialchars($userStatusSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="all-users-table">
        <table>
            <thead>
                <tr>
                    <th>ID Number</th>
                    <th>Username</th>
                    <th>First name</th>
                    <th>Last name</th>
                    <th>Role</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="6">No users found.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($u['id_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($u['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($u['firstname'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($u['lastname'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($u['role'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($u['dateCreated'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if (isset($_SESSION['user']['role']) && strcasecmp((string)$_SESSION['user']['role'], 'Admin') === 0): ?>
                                    <form method="post" action="../../config/reset-password-handler.php" class="confirmable" data-action="reset" data-username="<?= htmlspecialchars((string)($u['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="display:inline;margin-right:8px;">
                                        <?= function_exists('csrfField') ? csrfField() : '' ?>
                                        <input type="hidden" name="id_number" value="<?= htmlspecialchars((string)($u['id_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="material-btn" style="background:#fff;border:1px solid #d1fae5;color:#065f46;padding:6px 8px;border-radius:6px;">Reset Password</button>
                                    </form>
                                    <!-- Inline edit form (button removed) -->
                                    <form method="post" action="../../config/change-role-handler.php" class="edit-role-form" data-id="<?= htmlspecialchars((string)($u['id_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="display:none; margin-right:8px;">
                                        <?= function_exists('csrfField') ? csrfField() : '' ?>
                                        <input type="hidden" name="id_number" value="<?= htmlspecialchars((string)($u['id_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <select name="role" style="padding:6px;border-radius:6px;margin-right:6px;">
                                            <option value="Public" <?= (strcasecmp((string)$u['role'], 'Public') === 0) ? 'selected' : '' ?>>Public</option>
                                            <option value="Admin" <?= (strcasecmp((string)$u['role'], 'Admin') === 0) ? 'selected' : '' ?>>Admin</option>
                                        </select>
                                        <button type="submit" class="material-btn material-btn--primary" style="padding:6px 8px;border-radius:6px;margin-right:6px;">Save</button>
                                        <button type="button" class="material-btn edit-role-cancel" style="padding:6px 8px;border-radius:6px;">Cancel</button>
                                    </form>

                                    <?php
                                        // Determine current status from latest user log (fallback to active)
                                        $currentStatus = 'active';
                                        try {
                                            $latestLog = $uc->latestUserLogByIdNumber((string)($u['id_number'] ?? ''));
                                            if ($latestLog && !empty($latestLog['status'])) {
                                                $currentStatus = $latestLog['status'];
                                            }
                                        } catch (Throwable $e) {
                                            // ignore and keep default
                                        }
                                        $isActive = (strcasecmp((string)$currentStatus, 'active') === 0);
                                        $actionLabel = $isActive ? 'Deactivate' : 'Activate';
                                        $actionStatus = $isActive ? 'inactive' : 'active';
                                        $btnStyle = $isActive ? 'background:#fff;border:1px solid #f5c3c6;color:#b91c1c;padding:6px 8px;border-radius:6px;' : 'background:#fff;border:1px solid #d1fae5;color:#065f46;padding:6px 8px;border-radius:6px;';
                                    ?>

                                    <form method="post" action="../../config/change-status-handler.php" class="confirmable" data-action="status" data-username="<?= htmlspecialchars((string)($u['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="display:inline">
                                        <?= function_exists('csrfField') ? csrfField() : '' ?>
                                        <input type="hidden" name="id_number" value="<?= htmlspecialchars((string)($u['id_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="status" value="<?= $actionStatus ?>">
                                        <button type="submit" class="material-btn" style="<?= $btnStyle ?>"><?= $actionLabel ?></button>
                                    </form>
                                <?php else: ?>
                                    &ndash;
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<style>
.all-users-root { padding: 8px 0; }
.all-users-root h2 { margin: 0 0 8px; color: #0f1720; }
.all-users-table table { width:100%; border-collapse:collapse; background:#fff; }
.all-users-table th, .all-users-table td { text-align:left; padding:8px 10px; border-bottom:1px solid #eee; font-size:0.95rem; }
.all-users-table th { color:#6b7280; font-weight:600; }
.all-users-table tbody tr:hover { background:#fbfbfb; }
</style>

<!-- Confirm modal for reset/delete actions -->
<div id="confirmActionModal" class="confirm-modal" aria-hidden="true">
    <div class="confirm-modal__card" role="dialog" aria-modal="true" aria-labelledby="confirmActionTitle">
        <h3 id="confirmActionTitle">Confirm action</h3>
        <p id="confirmActionMessage">Are you sure?</p>
        <div class="confirm-modal__actions">
            <button id="confirmActionCancel" class="material-btn">Cancel</button>
            <button id="confirmActionConfirm" class="material-btn material-btn--primary">OK</button>
        </div>
    </div>
</div>

<style>
/* Modal styles */
.confirm-modal { position:fixed; inset:0; display:none; align-items:center; justify-content:center; background:rgba(0,0,0,0.35); z-index:1200; }
.confirm-modal[aria-hidden="false"] { display:flex; }
.confirm-modal__card { background:#fff; padding:18px; border-radius:8px; width:320px; box-shadow:0 10px 30px rgba(2,6,23,0.15); }
.confirm-modal__card h3 { margin:0 0 8px; font-size:1.05rem; }
.confirm-modal__card p { margin:0 0 16px; color:#334155; }
.confirm-modal__actions { display:flex; gap:8px; justify-content:flex-end; }
.confirm-modal__actions .material-btn { padding:8px 12px; border-radius:6px; }
.confirm-modal__actions .material-btn--primary { background:#6d28d9; color:#fff; border: none; }
</style>

<script>
// Custom confirm modal for reset/delete forms
(function(){
    const modal = document.getElementById('confirmActionModal');
    const msg = document.getElementById('confirmActionMessage');
    const btnCancel = document.getElementById('confirmActionCancel');
    const btnConfirm = document.getElementById('confirmActionConfirm');
    let pendingForm = null;

    function openModal(message, form) {
        pendingForm = form;
        msg.textContent = message;
        modal.setAttribute('aria-hidden', 'false');
        btnConfirm.focus();
    }

    function closeModal() {
        pendingForm = null;
        modal.setAttribute('aria-hidden', 'true');
    }

    document.addEventListener('submit', function(e){
        const form = e.target;
        if (!form || !form.classList || !form.classList.contains('confirmable')) return;
        e.preventDefault();
        const action = form.dataset.action || '';
        const uname = form.dataset.username || '';
        let message = 'Are you sure?';
        if (action === 'reset') {
            message = `Reset password to default (Mlinc1234) for user "${uname}"?`;
        } else if (action === 'delete') {
            message = `Delete user "${uname}"?`;
        } else if (action === 'status') {
            // For status toggles, read hidden input 'status' to determine label
            var statusInput = form.querySelector('input[name="status"]');
            var target = statusInput ? statusInput.value : '';
            if (target === 'inactive') {
                message = `Deactivate user "${uname}"?`;
            } else if (target === 'active') {
                message = `Activate user "${uname}"?`;
            }
        }
        openModal(message, form);
    }, true);

    btnCancel.addEventListener('click', function(){ closeModal(); });
    btnConfirm.addEventListener('click', function(){
        if (pendingForm) pendingForm.submit();
        closeModal();
    });

    // Close on overlay click
    modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
    // Close on Escape
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') closeModal(); });
})();
</script>

<script>
// Toggle inline edit-role forms
document.addEventListener('click', function(e){
    const openBtn = e.target.closest('.edit-role-open');
    if (openBtn) {
        const id = openBtn.dataset.editRoleId;
        const form = document.querySelector('.edit-role-form[data-id="' + id + '"]');
        if (form) {
            form.style.display = (form.style.display === 'none' || form.style.display === '') ? 'inline-block' : 'none';
        }
        return;
    }

    const cancelBtn = e.target.closest('.edit-role-cancel');
    if (cancelBtn) {
        const form = cancelBtn.closest('.edit-role-form');
        if (form) form.style.display = 'none';
    }
});
</script>
