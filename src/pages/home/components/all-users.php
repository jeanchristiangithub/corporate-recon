<?php
// Admin users list
require_once __DIR__ . '/../../../controllers/usercontroller.php';
require_once __DIR__ . '/../../../config/csrf.php';

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
        return (new DateTimeImmutable($raw))->format('F d, Y H:i:s A');
    } catch (Throwable $e) {
        return $raw;
    }
}
?>
<section class="all-users-root">
    <h2>All Users</h2>
    <?php
        $userCreateError = $_SESSION['user_create_error'] ?? '';
        $userCreateSuccess = $_SESSION['user_create_success'] ?? '';
        $userDelError = $_SESSION['user_delete_error'] ?? '';
        $userDelSuccess = $_SESSION['user_delete_success'] ?? '';
        $userResetError = $_SESSION['user_reset_error'] ?? '';
        $userResetSuccess = $_SESSION['user_reset_success'] ?? '';
        $userStatusError = $_SESSION['user_status_error'] ?? '';
        $userStatusSuccess = $_SESSION['user_status_success'] ?? '';
        $userUpdateError = $_SESSION['user_update_error'] ?? '';
        $userUpdateSuccess = $_SESSION['user_update_success'] ?? '';
        $userUpdateIdNumber = $_SESSION['user_update_id_number'] ?? '';
        unset($_SESSION['user_create_error'], $_SESSION['user_create_success'], $_SESSION['user_delete_error'], $_SESSION['user_delete_success'], $_SESSION['user_reset_error'], $_SESSION['user_reset_success'], $_SESSION['user_status_error'], $_SESSION['user_status_success'], $_SESSION['user_update_error'], $_SESSION['user_update_success'], $_SESSION['user_update_id_number']);
    ?>
    <?php if ($userCreateError !== ''): ?>
        <div class="all-users-alert all-users-alert--error" data-role="user-create-alert"><?= htmlspecialchars($userCreateError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($userCreateSuccess !== ''): ?>
        <div class="all-users-alert all-users-alert--success" data-role="user-create-alert"><?= htmlspecialchars($userCreateSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
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
    <?php if ($userUpdateError !== ''): ?>
        <div class="all-users-alert all-users-alert--error"><?= htmlspecialchars($userUpdateError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="all-users-controls">
        <div class="all-users-filter-group">
            <div class="all-users-filter all-users-search">
                <label for="allUsersSearch">Search</label>
                <input id="allUsersSearch" type="search" placeholder="Search users" autocomplete="off">
            </div>
            <div class="all-users-filter">
                <label for="allUsersRoleFilter">Role</label>
                <select id="allUsersRoleFilter">
                    <option value="">All roles</option>
                    <option value="admin">Admin</option>
                    <option value="public">Public</option>
                </select>
            </div>
            <div class="all-users-filter">
                <label for="allUsersStatusFilter">Status</label>
                <select id="allUsersStatusFilter">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
        <?php if (isset($_SESSION['user']['role']) && strcasecmp((string)$_SESSION['user']['role'], 'Admin') === 0): ?>
            <button id="openAddUserModal" class="material-btn add-user-btn all-users-add-btn" title="Add user">Add User</button>
        <?php endif; ?>
    </div>

    <div class="all-users-table">
        <table>
            <thead>
                <tr>
                    <th>ID Number</th>
                    <th>Username</th>
                    <th>First name</th>
                    <th>Last name</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="8" class="all-users-empty">No users found.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr data-role="<?= htmlspecialchars(strtolower((string)($u['role'] ?? 'public')), ENT_QUOTES, 'UTF-8') ?>" data-status="<?= htmlspecialchars(strtolower((string)($u['status'] ?? 'active')), ENT_QUOTES, 'UTF-8') ?>">
                            <td data-label="ID Number"><?= htmlspecialchars((string)($u['id_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Username"><?= htmlspecialchars((string)($u['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="First name"><?= htmlspecialchars((string)($u['firstname'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Last name"><?= htmlspecialchars((string)($u['lastname'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Role"><span class="all-users-role all-users-role--<?= strtolower(htmlspecialchars((string)($u['role'] ?? 'public'), ENT_QUOTES, 'UTF-8')) ?>"><?= htmlspecialchars((string)($u['role'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td data-label="Status"><span class="all-users-status all-users-status--<?= strtolower(htmlspecialchars((string)($u['status'] ?? 'active'), ENT_QUOTES, 'UTF-8')) ?>"><?= htmlspecialchars((string)($u['status'] ?? 'active'), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td data-label="Created"><?= htmlspecialchars(formatUserCreatedDate($u['dateCreated'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Action" class="all-users-actions">
                                <?php if (isset($_SESSION['user']['role']) && strcasecmp((string)$_SESSION['user']['role'], 'Admin') === 0): ?>
                                    <button
                                        type="button"
                                        class="material-btn all-users-btn all-users-btn--neutral all-users-edit-open"
                                        data-id-number="<?= htmlspecialchars((string)($u['id_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-username="<?= htmlspecialchars((string)($u['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-firstname="<?= htmlspecialchars((string)($u['firstname'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-lastname="<?= htmlspecialchars((string)($u['lastname'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-role="<?= htmlspecialchars((string)($u['role'] ?? 'Public'), ENT_QUOTES, 'UTF-8') ?>"
                                    >Edit</button>
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
                                        $currentStatus = (string)($u['status'] ?? 'active');
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
                    <tr id="allUsersNoFilterResults" class="all-users-no-filter-results" style="display:none;"><td colspan="8" class="all-users-empty">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if (isset($_SESSION['user']['role']) && strcasecmp((string)$_SESSION['user']['role'], 'Admin') === 0): ?>
<div id="allUsersAddUserModal" class="add-user-modal all-users-add-user-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="allUsersAddUserModalTitle">
    <div class="add-user-modal__card">
        <div class="add-user-modal__header">
            <h2 id="allUsersAddUserModalTitle">Add User</h2>
            <button type="button" class="add-user-modal__close" aria-label="Exit Add User modal" title="Exit" data-close-all-users-add-user>&times;</button>
        </div>

        <form method="post" action="../../config/add-user-handler.php" class="add-user-form">
            <?= function_exists('csrfField') ? csrfField() : '' ?>

            <label for="all_users_id_number">ID Number</label>
            <input id="all_users_id_number" name="id_number" type="text" required>

            <label for="all_users_username">Username</label>
            <input id="all_users_username" name="username" type="text" required autocomplete="off" readonly>

            <label for="all_users_firstname">First name</label>
            <input id="all_users_firstname" name="firstname" type="text">

            <label for="all_users_lastname">Last name</label>
            <input id="all_users_lastname" name="lastname" type="text">

            <label for="all_users_role">Role</label>
            <select id="all_users_role" name="role">
                <option value="Public">Public</option>
                <option value="Admin">Admin</option>
            </select>

            <label for="all_users_password">Password</label>
            <input id="all_users_password" name="password" type="text" readonly value="Mlinc1234" autocomplete="off" style="width:100%;box-sizing:border-box;background:#f8fafc;border:1px solid #e6eef6;padding:8px;border-radius:6px">

            <div class="all-users-add-user-actions">
                <button type="submit" class="material-btn material-btn--primary">Create</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<style>
.all-users-root {
    padding: 0.35rem 0 1.25rem;
    color: #334155;
}

.all-users-root h2 {
    margin: 0;
    color: #0f172a;
    font-size: clamp(1.35rem, 1.8vw, 1.65rem);
    font-weight: 700;
    letter-spacing: 0;
}

.all-users-controls {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 0.75rem;
    margin: 0.85rem 0 1rem;
}

.all-users-filter-group {
    display: flex;
    align-items: flex-end;
    flex: 1 1 auto;
    flex-wrap: nowrap;
    gap: 0.75rem;
    min-width: 0;
}

.all-users-add-btn {
    flex: 0 0 auto;
    min-height: 2.35rem;
    padding: 0.55rem 0.95rem;
    border: 0;
    border-radius: 7px;
    background: #dc3545;
    color: #ffffff;
    font-size: 0.88rem;
    font-weight: 700;
    box-shadow: 0 10px 22px rgba(220, 53, 69, 0.18);
}

.all-users-add-btn:hover {
    background: #c82333;
    box-shadow: 0 12px 26px rgba(220, 53, 69, 0.24);
}

.all-users-add-user-modal {
    position: fixed;
    inset: 0;
    z-index: 1300;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(17, 24, 39, 0.45);
}

.all-users-add-user-modal.is-open {
    display: flex;
}

.all-users-add-user-modal .add-user-modal__card {
    width: min(420px, 100%);
    max-height: calc(100vh - 2rem);
    overflow-y: auto;
    padding: 1.1rem;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.2);
}

.all-users-add-user-modal .add-user-modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.75rem;
}

.all-users-add-user-modal .add-user-modal__header h2 {
    margin: 0;
    color: #0f172a;
    font-size: 1.15rem;
    font-weight: 700;
}

.all-users-add-user-modal .add-user-modal__close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #64748b;
    font-size: 1.5rem;
    line-height: 1;
    cursor: pointer;
}

.all-users-add-user-modal .add-user-modal__close:hover,
.all-users-add-user-modal .add-user-modal__close:focus-visible {
    background: #f1f5f9;
    color: #0f172a;
    outline: none;
}

.all-users-add-user-modal label {
    display: block;
    margin: 0.55rem 0 0.35rem;
    color: #4b5563;
    font-size: 0.95rem;
    font-weight: 500;
}

.all-users-add-user-modal input,
.all-users-add-user-modal select {
    width: 100%;
    min-height: 2.15rem;
    box-sizing: border-box;
    border: 1px solid #d9dee7;
    border-radius: 5px;
    background: #ffffff;
    color: #111827;
    font: inherit;
    padding: 0.4rem 0.55rem;
}

.all-users-add-user-modal input:focus,
.all-users-add-user-modal select:focus {
    border-color: #dc3545;
    box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.12);
    outline: none;
}

.all-users-add-user-modal .material-btn--primary {
    min-height: 2.25rem;
    padding: 0.5rem 1rem;
    border: 0;
    border-radius: 7px;
    background: #dc3545;
    color: #ffffff;
    font-weight: 700;
}

.all-users-add-user-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 10px;
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

.all-users-filter {
    flex: 0 1 180px;
    width: min(180px, 100%);
    margin: 0;
}

.all-users-search {
    flex: 0 1 320px;
    width: min(320px, 100%);
}

.all-users-filter label {
    display: block;
    margin: 0 0 0.35rem;
    color: #475569;
    font-size: 0.85rem;
    font-weight: 700;
}

.all-users-filter input,
.all-users-filter select {
    width: 100%;
    min-height: 2.25rem;
    box-sizing: border-box;
    border: 1px solid #d9dee7;
    border-radius: 7px;
    background: #ffffff;
    color: #111827;
    font: inherit;
    padding: 0.45rem 0.65rem;
}

.all-users-filter input:focus,
.all-users-filter select:focus {
    border-color: #dc3545;
    box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.12);
    outline: none;
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
    text-align: center;
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

.all-users-table tbody td {
    text-align: center;
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

.all-users-status {
    display: inline-flex;
    align-items: center;
    min-height: 1.7rem;
    padding: 0.18rem 0.6rem;
    border-radius: 999px;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: capitalize;
}

.all-users-status--active {
    background: #dcfce7;
    color: #15803d;
}

.all-users-status--inactive {
    background: #fee2e2;
    color: #b91c1c;
}

.all-users-actions {
    min-width: 310px;
    white-space: nowrap;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.all-users-action-form {
    display: inline-flex;
    align-items: center;
    margin: 0;
}

.all-users-edit-open {
    width: 52px;
}

.all-users-action-form[data-action="reset"] {
    width: 130px;
}

.all-users-action-form[data-action="status"] {
    width: 100px;
}

.all-users-action-form[data-action="reset"] .all-users-btn,
.all-users-action-form[data-action="status"] .all-users-btn {
    width: 100%;
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

.all-users-table td.all-users-empty {
    text-align: center;
}

@media (max-width: 760px) {
    .all-users-controls {
        align-items: stretch;
        flex-direction: column;
    }

    .all-users-filter-group {
        align-items: stretch;
        flex-direction: column;
    }

    .all-users-filter,
    .all-users-search {
        flex-basis: auto;
        width: 100%;
    }

    .all-users-add-btn {
        align-self: flex-end;
    }

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
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    .all-users-action-form {
        margin: 0 0 0.45rem;
    }
}
</style>

<div id="allUsersEditModal" class="all-users-edit-modal" aria-hidden="true">
    <div class="all-users-edit-modal__card" role="dialog" aria-modal="true" aria-labelledby="allUsersEditModalTitle">
        <div class="all-users-edit-modal__header">
            <h3 id="allUsersEditModalTitle">Edit</h3>
            <button type="button" class="all-users-edit-modal__close" aria-label="Close Edit modal" title="Close">&times;</button>
        </div>
        <form method="post" action="../../config/update-fullname-handler.php" class="all-users-edit-form">
            <?= function_exists('csrfField') ? csrfField() : '' ?>
            <input id="edit_user_id_number_hidden" name="id_number" type="hidden">
            <input id="edit_user_username_hidden" name="username" type="hidden">
            <div class="all-users-edit-modal__body">
            <label for="edit_user_id_number">ID Number</label>
            <input id="edit_user_id_number" name="id_number" type="text" disabled>

            <label for="edit_user_username">Username</label>
            <input id="edit_user_username" name="username" type="text" disabled>

            <label for="edit_user_firstname">First name</label>
            <input id="edit_user_firstname" name="firstname" type="text">

            <label for="edit_user_lastname">Last name</label>
            <input id="edit_user_lastname" name="lastname" type="text">

            <label for="edit_user_role">Role</label>
            <select id="edit_user_role" name="role">
                <option value="Public">Public</option>
                <option value="Admin">Admin</option>
            </select>
            </div>
            <div class="all-users-edit-modal__footer">
                <button type="submit" class="material-btn all-users-edit-update-btn">Update</button>
            </div>
        </form>
    </div>
</div>

<?php if ($userUpdateSuccess !== ''): ?>
<div id="allUsersUpdatedModal" class="all-users-updated-modal" aria-hidden="false">
    <div class="all-users-updated-modal__card" role="dialog" aria-modal="true" aria-labelledby="allUsersUpdatedTitle">
        <h3 id="allUsersUpdatedTitle">Updated Successfully</h3>
        <i><p>User ID Number: <?= htmlspecialchars((string)$userUpdateIdNumber, ENT_QUOTES, 'UTF-8') ?></p></i>
        <button type="button" class="material-btn all-users-updated-ok">OK</button>
    </div>
</div>
<?php endif; ?>

<style>
.all-users-edit-modal {
    position: fixed;
    inset: 0;
    z-index: 1300;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(15, 23, 42, 0.42);
}

.all-users-edit-modal[aria-hidden="false"] {
    display: flex;
}

.all-users-edit-modal__card {
    width: min(390px, 100%);
    background: #ffffff;
    border-radius: 8px;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.2);
}

.all-users-edit-modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.1rem;
    border-bottom: 1px solid #e5e7eb;
}

.all-users-edit-modal__header h3 {
    margin: 0;
    color: #0f172a;
    font-size: 1.1rem;
    font-weight: 700;
}

.all-users-edit-modal__close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #64748b;
    font-size: 1.5rem;
    line-height: 1;
    cursor: pointer;
}

.all-users-edit-modal__close:hover,
.all-users-edit-modal__close:focus-visible {
    background: #f1f5f9;
    color: #0f172a;
    outline: none;
}

.all-users-edit-modal__body {
    padding: 0.95rem 1.1rem 1.15rem;
}

.all-users-edit-modal__body label {
    display: block;
    margin: 0.55rem 0 0.35rem;
    color: #4b5563;
    font-size: 0.95rem;
    font-weight: 500;
}

.all-users-edit-modal__body label:first-child {
    margin-top: 0;
}

.all-users-edit-modal__body input,
.all-users-edit-modal__body select {
    width: 100%;
    min-height: 2.15rem;
    box-sizing: border-box;
    border: 1px solid #d9dee7;
    border-radius: 5px;
    background: #ffffff;
    color: #111827;
    font: inherit;
    padding: 0.4rem 0.55rem;
}

.all-users-edit-modal__body input:focus,
.all-users-edit-modal__body select:focus {
    border-color: #dc3545;
    box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.12);
    outline: none;
}

.all-users-edit-modal__body input:disabled {
    background: #f8fafc;
    color: #64748b;
    cursor: not-allowed;
}

.all-users-edit-modal__footer {
    display: flex;
    justify-content: flex-end;
    padding: 0 1.1rem 1.15rem;
}

.all-users-edit-update-btn {
    min-height: 2.25rem;
    padding: 0.5rem 1rem;
    border: 0;
    border-radius: 7px;
    background: #dc3545;
    color: #ffffff;
    font-weight: 700;
}

.all-users-edit-update-btn:hover {
    background: #c82333;
}

.all-users-updated-modal {
    position: fixed;
    inset: 0;
    z-index: 1400;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(15, 23, 42, 0.42);
}

.all-users-updated-modal[aria-hidden="true"] {
    display: none;
}

.all-users-updated-modal__card {
    width: min(360px, 100%);
    padding: 1.2rem;
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.2);
    text-align: center;
}

.all-users-updated-modal__card h3 {
    margin: 0 0 0.45rem;
    color: #0f172a;
    font-size: 1.15rem;
}

.all-users-updated-modal__card p {
    margin: 0 0 1rem;
    color: #475569;
    font-weight: 600;
}

.all-users-updated-ok {
    min-height: 2.2rem;
    min-width: 5rem;
    border: 0;
    border-radius: 7px;
    background: #dc3545;
    color: #ffffff;
    font-weight: 700;
}
</style>

<script>
(function(){
    const modal = document.getElementById('allUsersEditModal');
    if (!modal) return;

    const closeButton = modal.querySelector('.all-users-edit-modal__close');
    const idNumberInput = document.getElementById('edit_user_id_number');
    const usernameInput = document.getElementById('edit_user_username');
    const idNumberHidden = document.getElementById('edit_user_id_number_hidden');
    const usernameHidden = document.getElementById('edit_user_username_hidden');
    const firstnameInput = document.getElementById('edit_user_firstname');
    const lastnameInput = document.getElementById('edit_user_lastname');
    const roleSelect = document.getElementById('edit_user_role');
    let activeButton = null;

    function sanitizeUsernamePart(value) {
        return String(value || '').replace(/[^a-zA-Z0-9]/g, '');
    }

    function updateUsernameFromLastName() {
        if (!lastnameInput || !idNumberInput || !usernameInput) return;

        const last = String(lastnameInput.value || '').trim().substring(0, 4);
        const id = String(idNumberInput.value || '').trim();
        usernameInput.value = sanitizeUsernamePart(last) + sanitizeUsernamePart(id);
        if (usernameHidden) usernameHidden.value = usernameInput.value;
    }

    function openModal(button) {
        activeButton = button || null;
        if (idNumberInput) idNumberInput.value = button ? (button.dataset.idNumber || '') : '';
        if (usernameInput) usernameInput.value = button ? (button.dataset.username || '') : '';
        if (idNumberHidden) idNumberHidden.value = button ? (button.dataset.idNumber || '') : '';
        if (usernameHidden) usernameHidden.value = button ? (button.dataset.username || '') : '';
        if (firstnameInput) firstnameInput.value = button ? (button.dataset.firstname || '') : '';
        if (lastnameInput) lastnameInput.value = button ? (button.dataset.lastname || '') : '';
        if (roleSelect) roleSelect.value = button ? (button.dataset.role || 'Public') : 'Public';
        modal.setAttribute('aria-hidden', 'false');
        if (firstnameInput) {
            firstnameInput.focus();
        } else if (closeButton) {
            closeButton.focus();
        }
    }

    function closeModal() {
        modal.setAttribute('aria-hidden', 'true');
        if (activeButton) activeButton.focus();
        activeButton = null;
    }

    document.addEventListener('click', function(event){
        const editButton = event.target.closest('.all-users-edit-open');
        if (editButton) {
            event.preventDefault();
            openModal(editButton);
            return;
        }

        if (event.target === modal || event.target.closest('.all-users-edit-modal__close')) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function(event){
        if (event.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') {
            closeModal();
        }
    });

    if (lastnameInput) {
        lastnameInput.addEventListener('input', function(){
            lastnameInput.value = String(lastnameInput.value || '').toUpperCase();
            updateUsernameFromLastName();
        });
    }
})();
</script>

<script>
(function(){
    const modal = document.getElementById('allUsersUpdatedModal');
    if (!modal) return;

    const okButton = modal.querySelector('.all-users-updated-ok');
    function closeModal() {
        modal.setAttribute('aria-hidden', 'true');
    }

    if (okButton) {
        okButton.addEventListener('click', closeModal);
        okButton.focus();
    }
    modal.addEventListener('click', function(event){
        if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', function(event){
        if (event.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') {
            closeModal();
        }
    });
})();
</script>

<script>
(function(){
    const modal = document.getElementById('allUsersAddUserModal');
    const openButton = document.querySelector('.all-users-root #openAddUserModal');
    if (!modal || !openButton) return;

    const idNumberInput = document.getElementById('all_users_id_number');
    const usernameInput = document.getElementById('all_users_username');
    const firstnameInput = document.getElementById('all_users_firstname');
    const lastnameInput = document.getElementById('all_users_lastname');

    function sanitizeUsernamePart(value) {
        return String(value || '').replace(/[^a-zA-Z0-9]/g, '');
    }

    function buildUsername() {
        if (!lastnameInput || !idNumberInput || !usernameInput) return;
        if (usernameInput.dataset.userEdited === '1') return;

        const last = String(lastnameInput.value || '').trim().substring(0, 4);
        const id = String(idNumberInput.value || '').trim();
        usernameInput.value = sanitizeUsernamePart(last) + sanitizeUsernamePart(id);
    }

    function openModal(event) {
        if (event) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        if (usernameInput) {
            usernameInput.value = '';
            usernameInput.dataset.userEdited = '0';
        }
        buildUsername();
        if (idNumberInput) idNumberInput.focus();
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        openButton.focus();
    }

    openButton.addEventListener('click', openModal, true);
    modal.addEventListener('click', function(event){
        if (event.target === modal || event.target.closest('[data-close-all-users-add-user]')) {
            closeModal();
        }
    });
    document.addEventListener('keydown', function(event){
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    [firstnameInput, lastnameInput].forEach(function(input){
        if (!input) return;
        input.addEventListener('input', function(){
            input.value = String(input.value || '').toUpperCase();
            buildUsername();
        });
    });
    if (idNumberInput) {
        idNumberInput.addEventListener('input', buildUsername);
    }
})();
</script>

<script>
(function(){
    const searchInput = document.getElementById('allUsersSearch');
    const roleFilter = document.getElementById('allUsersRoleFilter');
    const statusFilter = document.getElementById('allUsersStatusFilter');
    const tableBody = document.querySelector('.all-users-table tbody');
    if (!searchInput || !tableBody) return;
    const noResultsRow = document.getElementById('allUsersNoFilterResults');

    const rows = Array.from(tableBody.querySelectorAll('tr')).filter(function(row){
        return !row.querySelector('.all-users-empty') && row.id !== 'allUsersNoFilterResults';
    });

    function applyFilters() {
        const query = searchInput.value.trim().toLowerCase();
        const role = roleFilter ? roleFilter.value : '';
        const status = statusFilter ? statusFilter.value : '';
        let visibleCount = 0;

        rows.forEach(function(row){
            const matchesSearch = row.textContent.toLowerCase().includes(query);
            const matchesRole = role === '' || row.dataset.role === role;
            const matchesStatus = status === '' || row.dataset.status === status;
            const isVisible = matchesSearch && matchesRole && matchesStatus;
            row.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount += 1;
        });

        if (noResultsRow) {
            noResultsRow.style.display = visibleCount === 0 ? '' : 'none';
        }
    }

    searchInput.addEventListener('input', applyFilters);
    if (roleFilter) roleFilter.addEventListener('change', applyFilters);
    if (statusFilter) statusFilter.addEventListener('change', applyFilters);
})();
</script>

<!-- Confirm modal for reset/delete actions -->
<div id="confirmActionModal" class="confirm-modal" aria-hidden="true">
    <div class="confirm-modal__card" role="dialog" aria-modal="true" aria-labelledby="confirmActionTitle">
        <h3 id="confirmActionTitle">Confirm action</h3>
        <p id="confirmActionMessage">Are you sure?</p>
        <div class="confirm-modal__actions">
            <button id="confirmActionConfirm" class="material-btn material-btn--danger">Proceed</button>
            <button id="confirmActionCancel" class="material-btn">Cancel</button>
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
.confirm-modal__actions .material-btn--danger { background:#dc3545; color:#fff; border:none; }
.confirm-modal__actions .material-btn--danger:hover { background:#bb2d3b; }
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
            message = `Reset password to default (Mlinc1234) for username "${uname}"?`;
        } else if (action === 'delete') {
            message = `Delete username "${uname}"?`;
        } else if (action === 'status') {
            // For status toggles, read hidden input 'status' to determine label
            var statusInput = form.querySelector('input[name="status"]');
            var target = statusInput ? statusInput.value : '';
            if (target === 'inactive') {
                message = `Deactivate username "${uname}"?`;
            } else if (target === 'active') {
                message = `Activate username "${uname}"?`;
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
