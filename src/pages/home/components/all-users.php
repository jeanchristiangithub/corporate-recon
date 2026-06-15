<?php
// Admin users list
require_once __DIR__ . '/../../../controllers/usercontroller.php';

try {
    $uc = new UserController();
    $users = $uc->getAllUsers();
} catch (Throwable $e) {
    $users = [];
}

function formatUserCreatedDate($value): string
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($raw))->format('m-d-Y H:i:s');
    } catch (Throwable $e) {
        return $raw;
    }
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
        <div class="all-users-alert all-users-alert--error"><?= htmlspecialchars($userDelError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($userDelSuccess !== ''): ?>
        <div class="all-users-alert all-users-alert--success"><?= htmlspecialchars($userDelSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($userResetError !== ''): ?>
        <div class="all-users-alert all-users-alert--error"><?= htmlspecialchars($userResetError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($userResetSuccess !== ''): ?>
        <div class="all-users-alert all-users-alert--success"><?= htmlspecialchars($userResetSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($userStatusError !== ''): ?>
        <div class="all-users-alert all-users-alert--error"><?= htmlspecialchars($userStatusError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($userStatusSuccess !== ''): ?>
        <div class="all-users-alert all-users-alert--success"><?= htmlspecialchars($userStatusSuccess, ENT_QUOTES, 'UTF-8') ?></div>
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
                    <tr><td colspan="7" class="all-users-empty">No users found.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td data-label="ID Number"><?= htmlspecialchars((string)($u['id_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Username"><?= htmlspecialchars((string)($u['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="First name"><?= htmlspecialchars((string)($u['firstname'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Last name"><?= htmlspecialchars((string)($u['lastname'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Role"><span class="all-users-role all-users-role--<?= strtolower(htmlspecialchars((string)($u['role'] ?? 'public'), ENT_QUOTES, 'UTF-8')) ?>"><?= htmlspecialchars((string)($u['role'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td data-label="Created"><?= htmlspecialchars(formatUserCreatedDate($u['dateCreated'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Action" class="all-users-actions">
                                <?php if (isset($_SESSION['user']['role']) && strcasecmp((string)$_SESSION['user']['role'], 'Admin') === 0): ?>
                                    <form method="post" action="../../config/reset-password-handler.php" class="confirmable all-users-action-form" data-action="reset" data-username="<?= htmlspecialchars((string)($u['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= function_exists('csrfField') ? csrfField() : '' ?>
                                        <input type="hidden" name="id_number" value="<?= htmlspecialchars((string)($u['id_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="material-btn all-users-btn all-users-btn--success">Reset Password</button>
                                    </form>
                                    <!-- Inline edit form (button removed) -->
                                    <form method="post" action="../../config/change-role-handler.php" class="edit-role-form all-users-action-form" data-id="<?= htmlspecialchars((string)($u['id_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= function_exists('csrfField') ? csrfField() : '' ?>
                                        <input type="hidden" name="id_number" value="<?= htmlspecialchars((string)($u['id_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <select name="role" class="all-users-role-select">
                                            <option value="Public" <?= (strcasecmp((string)$u['role'], 'Public') === 0) ? 'selected' : '' ?>>Public</option>
                                            <option value="Admin" <?= (strcasecmp((string)$u['role'], 'Admin') === 0) ? 'selected' : '' ?>>Admin</option>
                                        </select>
                                        <button type="submit" class="material-btn all-users-btn all-users-btn--primary">Save</button>
                                        <button type="button" class="material-btn edit-role-cancel all-users-btn all-users-btn--neutral">Cancel</button>
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
                                        $statusBtnClass = $isActive ? 'all-users-btn--danger' : 'all-users-btn--success';
                                    ?>

                                    <form method="post" action="../../config/change-status-handler.php" class="confirmable all-users-action-form" data-action="status" data-username="<?= htmlspecialchars((string)($u['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= function_exists('csrfField') ? csrfField() : '' ?>
                                        <input type="hidden" name="id_number" value="<?= htmlspecialchars((string)($u['id_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="status" value="<?= $actionStatus ?>">
                                        <button type="submit" class="material-btn all-users-btn <?= $statusBtnClass ?>"><?= $actionLabel ?></button>
                                    </form>
                                <?php else: ?>
                                    <span class="all-users-no-action">&ndash;</span>
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
.all-users-root {
    padding: 0.35rem 0 1.25rem;
    color: #334155;
}

.all-users-root h2 {
    margin: 0 0 1rem;
    color: #0f172a;
    font-size: clamp(1.35rem, 1.8vw, 1.65rem);
    font-weight: 700;
    letter-spacing: 0;
}

.all-users-alert {
    margin: 0 0 0.85rem;
    padding: 0.75rem 0.9rem;
    border: 1px solid;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
}

.all-users-alert--error {
    background: #fff1f2;
    border-color: #fecdd3;
    color: #9f1239;
}

.all-users-alert--success {
    background: #ecfdf5;
    border-color: #a7f3d0;
    color: #047857;
}

.all-users-table {
    overflow: hidden;
    overflow-x: auto;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
}

.all-users-table table {
    width: 100%;
    min-width: 920px;
    border-collapse: separate;
    border-spacing: 0;
    background: #ffffff;
}

.all-users-table th,
.all-users-table td {
    text-align: left;
    padding: 0.9rem 0.7rem;
    border-bottom: 1px solid #eef2f7;
    font-size: 0.92rem;
    vertical-align: middle;
}

.all-users-table th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #f8fafc;
    color: #64748b;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    white-space: nowrap;
}

.all-users-table tbody tr {
    transition: background 0.16s ease, box-shadow 0.16s ease;
}

.all-users-table tbody tr:hover {
    background: #fff7f7;
}

.all-users-table tbody tr:last-child td {
    border-bottom: 0;
}

.all-users-role {
    display: inline-flex;
    align-items: center;
    min-height: 1.7rem;
    padding: 0.18rem 0.6rem;
    border-radius: 999px;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.78rem;
    font-weight: 700;
}

.all-users-role--admin {
    background: #fee2e2;
    color: #b91c1c;
}

.all-users-actions {
    min-width: 230px;
    white-space: nowrap;
}

.all-users-action-form {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    margin-right: 0.45rem;
}

.edit-role-form {
    display: none;
}

.all-users-btn {
    min-height: 2.1rem;
    padding: 0.48rem 0.75rem;
    border: 1px solid transparent;
    border-radius: 7px;
    background: #ffffff;
    box-shadow: none;
    font-size: 0.82rem;
    line-height: 1;
}

.all-users-btn:hover {
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
}

.all-users-btn--success {
    border-color: #bbf7d0;
    color: #047857;
}

.all-users-btn--danger {
    border-color: #fecaca;
    color: #dc2626;
}

.all-users-btn--primary {
    background: #dc3545;
    color: #ffffff;
}

.all-users-btn--neutral {
    border-color: #cbd5e1;
    color: #475569;
}

.all-users-role-select {
    min-height: 2.1rem;
    padding: 0.35rem 0.55rem;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    background: #ffffff;
    color: #334155;
    font: inherit;
    font-size: 0.85rem;
}

.all-users-empty,
.all-users-no-action {
    color: #94a3b8;
}

.all-users-empty {
    padding: 1.5rem 0.7rem;
    text-align: center;
    font-weight: 600;
}

@media (max-width: 760px) {
    .all-users-table {
        border: 0;
        background: transparent;
        box-shadow: none;
        overflow: visible;
    }

    .all-users-table table,
    .all-users-table thead,
    .all-users-table tbody,
    .all-users-table tr,
    .all-users-table th,
    .all-users-table td {
        display: block;
        width: 100%;
        min-width: 0;
    }

    .all-users-table thead {
        display: none;
    }

    .all-users-table tbody tr {
        margin-bottom: 0.8rem;
        padding: 0.85rem;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: #ffffff;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
    }

    .all-users-table td {
        display: grid;
        grid-template-columns: minmax(7.5rem, 38%) 1fr;
        gap: 0.75rem;
        padding: 0.55rem 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .all-users-table td::before {
        content: attr(data-label);
        color: #64748b;
        font-size: 0.74rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .all-users-table td:last-child {
        border-bottom: 0;
    }

    .all-users-actions {
        min-width: 0;
        white-space: normal;
    }

    .all-users-action-form {
        margin: 0 0.4rem 0.45rem 0;
    }
}
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
