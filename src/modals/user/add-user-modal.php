<?php
require_once __DIR__ . '/../../config/csrf.php';
$csrfInput = csrfField();
?>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link rel="stylesheet" href="../../modals/user/add-user-modal.css">

<div id="addUserModal" class="add-user-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="addUserModalTitle">
    <div class="add-user-modal__card">
        <button type="button" class="add-user-modal__close" aria-label="Close" data-close-add-user>&times;</button>

        <h2 id="addUserModalTitle">Add User</h2>

        <form method="post" action="../../config/add-user-handler.php" class="add-user-form">
            <?= $csrfInput ?>

            <label for="id_number">ID Number</label>
            <input id="id_number" name="id_number" type="text" required>

            <label for="username">Username</label>
            <input id="username" name="username" type="text" required autocomplete="off" readonly>

            <label for="firstname">First name</label>
            <input id="firstname" name="firstname" type="text">

            <label for="lastname">Last name</label>
            <input id="lastname" name="lastname" type="text">

            <label for="role">Role</label>
            <select id="role" name="role">
                <option value="Public">Public</option>
                <option value="Admin">Admin</option>
            </select>

            <label for="password">Password</label>
            <input id="password" name="password" type="text" readonly value="Mlinc1234" autocomplete="off" style="width:100%;box-sizing:border-box;background:#f8fafc;border:1px solid #e6eef6;padding:8px;border-radius:6px">

            <div style="margin-top:10px;">
                <button type="submit" class="material-btn material-btn--primary">Create</button>
            </div>
        </form>
    </div>
</div>

<script>
function sanitizeUsernamePart(value) {
    return String(value || '')
    .replace(/[^a-zA-Z0-9]/g, '');
}

function buildUsernameFromLastNameAndId() {
    const lastNameInput = document.getElementById('lastname');
    const idNumberInput = document.getElementById('id_number');
    const usernameInput = document.getElementById('username');
    if (!lastNameInput || !idNumberInput || !usernameInput) return;
    if (usernameInput.dataset.userEdited === '1') return;

    const last = String(lastNameInput.value || '').trim().substring(0, 4);
    const id = String(idNumberInput.value || '').trim();
    const generated = sanitizeUsernamePart(last) + sanitizeUsernamePart(id);
    usernameInput.value = generated;
}

document.addEventListener('input', function (e) {
    const targetId = e.target && e.target.id ? e.target.id : '';

    if (targetId === 'firstname' || targetId === 'lastname') {
        e.target.value = String(e.target.value || '').toUpperCase();
    }

    if (targetId === 'lastname' || targetId === 'id_number') {
        buildUsernameFromLastNameAndId();
        return;
    }

    if (targetId === 'username') {
        const usernameInput = document.getElementById('username');
        if (!usernameInput) return;

        const last = String(document.getElementById('lastname')?.value || '').trim().substring(0,4);
        const id = String(document.getElementById('id_number')?.value || '').trim();
        const generated = sanitizeUsernamePart(last) + sanitizeUsernamePart(id);

        usernameInput.dataset.userEdited = usernameInput.value !== generated ? '1' : '0';
    }
});

document.addEventListener('click', function(e){
    // Open button (anywhere on page)
    const open = e.target.closest('#openAddUserModal');
    if (open) {
        e.preventDefault();
        const m = document.getElementById('addUserModal');
        if (m) m.classList.add('is-open');
        const usernameInput = document.getElementById('username');
        if (usernameInput) {
            usernameInput.value = '';
            usernameInput.dataset.userEdited = '0';
        }
        buildUsernameFromLastNameAndId();
        return;
    }

    // Close button inside modal
    const close = e.target.closest('[data-close-add-user]');
    if (close) {
        const m = document.getElementById('addUserModal');
        if (m) m.classList.remove('is-open');
        return;
    }

    // Click outside the modal card should close the modal
    const overlay = document.getElementById('addUserModal');
    if (overlay && overlay.classList.contains('is-open')) {
        const card = overlay.querySelector('.add-user-modal__card');
        // If the click target is the overlay itself (the backdrop), close
        if (e.target === overlay) {
            overlay.classList.remove('is-open');
            return;
        }
        // Or if click is outside the card (and not on the open button), close
        if (card && !card.contains(e.target)) {
            overlay.classList.remove('is-open');
            return;
        }
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' || e.key === 'Esc') {
        const m = document.getElementById('addUserModal');
        if (m && m.classList.contains('is-open')) m.classList.remove('is-open');
    }
});

// Password field is revealed and readonly by design.
</script>
