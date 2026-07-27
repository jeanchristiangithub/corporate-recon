<?php
require_once __DIR__ . '/../../config/csrf.php';
$csrfInput = csrfField();
?>
<!-- Material icons for small inline icons -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

<div id="loginModal" class="login-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="loginModalTitle">
    <div class="login-modal__card">
        <button type="button" class="login-modal__close" aria-label="Close" data-close-login>&times;</button>

        <div class="login-modal__grid">
            <div class="login-modal__brand">
                <img src="../../assets/12.png" alt="M Lhuillier logo" class="login-modal__logo">
                <h2 id="loginModalTitle">Sign in</h2>
                <p class="login-modal__subtitle">Access your reconciliation dashboard securely.</p>
            </div>

            <div class="login-modal__form">
                <?php if (!empty($loginError)): ?>
                    <div class="login-modal__error" role="alert"><?= htmlspecialchars((string) $loginError, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <form method="post" action="../../config/login-handler.php" class="login-form">
                    <?= $csrfInput ?>

                    <label for="username">Username</label>
                    <div class="input-wrap">
                        <span class="material-icons input-icon">person</span>
                        <input id="username" name="username" type="text" required autocomplete="username">
                    </div>

                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <span class="material-icons input-icon">lock</span>
                        <input id="password" name="password" type="password" required autocomplete="current-password">
                        <button type="button" class="password-toggle" aria-label="Toggle password"><span class="material-icons">visibility</span></button>
                    </div>

                    <div class="login-form__remember" style="margin:10px 0;display:flex;align-items:center;gap:8px">
                        <input type="checkbox" id="save-login" />
                        <label for="save-login" style="font-size:0.95rem;color:inherit">Save login</label>
                    </div>

                    <button type="submit" class="material-btn material-btn--primary login-form__submit">Sign in</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    var modal = document.getElementById('loginModal');
    if (!modal) return;

    var key = 'autorecon_saved_login_v1';
    var username = modal.querySelector('#username');
    var password = modal.querySelector('#password');
    var saveCheckbox = modal.querySelector('#save-login');
    var form = modal.querySelector('.login-form');

    // Bind reveal/hide directly to each toggle button in this modal.
    modal.querySelectorAll('.password-toggle').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var wrap = btn.closest('.input-wrap');
            var input = wrap && wrap.querySelector('input');
            if (!input) return;

            var icon = btn.querySelector('.material-icons');
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) icon.textContent = 'visibility_off';
            } else {
                input.type = 'password';
                if (icon) icon.textContent = 'visibility';
            }
        });
    });

    // Save login (testing convenience) using localStorage.
    try {
        var stored = localStorage.getItem(key);
        if (stored) {
            var data = JSON.parse(stored);
            if (data.username && username) username.value = String(data.username).toUpperCase();
            if (data.password && password) password.value = data.password;
            if (saveCheckbox) saveCheckbox.checked = true;
        }
    } catch (e) { /* ignore JSON/storage errors */ }

    // Force uppercase while typing.
    if (username) {
        username.addEventListener('input', function(){
            var pos = this.selectionStart;
            this.value = String(this.value || '').toUpperCase();
            try { this.setSelectionRange(pos, pos); } catch (e) { /* ignore */ }
        });
    }

    if (form) {
        form.addEventListener('submit', function(){
            try {
                if (saveCheckbox && saveCheckbox.checked) {
                    localStorage.setItem(key, JSON.stringify({
                        username: username ? username.value : '',
                        password: password ? password.value : ''
                    }));
                } else {
                    localStorage.removeItem(key);
                }
            } catch (e) { /* ignore storage errors */ }
        });
    }
})();
</script>
