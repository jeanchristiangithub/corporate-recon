<?php
// Ensure session is booted and csrf helpers are available
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/csrf.php';
bootSecureSession();
?>
<!-- Use same visual structure as login modal -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

<?php $isForced = !empty($_SESSION['force_password_reset']); ?>
<div id="passwordResetModal" class="login-modal password-modal <?= $isForced ? 'is-open' : '' ?>" aria-hidden="<?= $isForced ? 'false' : 'true' ?>" role="dialog" aria-modal="true" aria-labelledby="passwordResetTitle">
    <div class="login-modal__card password-modal__card">
        <button type="button" class="login-modal__close" aria-label="Close" data-close-password><span class="material-icons">close</span></button>

        <div class="login-modal__grid">
            <div class="login-modal__brand">
                <img src="../../assets/logo2.png" alt="M Lhuillier logo" class="login-modal__logo">
                <h2 id="passwordResetTitle">Set a new password</h2>
                <p class="login-modal__subtitle">For account security, please update your password.</p>
            </div>

            <div class="login-modal__form">
                <?php if (!empty($_SESSION['password_error'])): ?>
                    <div class="login-modal__error" role="alert"><?= htmlspecialchars((string) $_SESSION['password_error'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <form method="post" action="../../config/change-pass-handler.php" class="password-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="change_password">

                    <label for="new_password">New Password</label>
                    <div class="input-wrap">
                        <span class="material-icons input-icon">lock</span>
                        <input id="new_password" name="new_password" type="password" required minlength="8" autocomplete="new-password">
                        <button type="button" class="password-toggle" aria-label="Toggle password"><span class="material-icons">visibility</span></button>
                    </div>

                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-wrap">
                        <span class="material-icons input-icon">lock</span>
                        <input id="confirm_password" name="confirm_password" type="password" required minlength="8" autocomplete="new-password">
                        <button type="button" class="password-toggle" aria-label="Toggle password"><span class="material-icons">visibility</span></button>
                    </div>

                    <div class="password-form__error" style="color:#b00020;display:none;margin-bottom:8px;" role="alert"></div>

                    <button type="submit" style="margin-top: 12px;" class="material-btn material-btn--primary">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Scoped script: toggle visibility and client-side confirm validation
(function(){
    var modal = document.getElementById('passwordResetModal');
    var form = modal && modal.querySelector('.password-form');
    var newPass = modal && modal.querySelector('#new_password');
    var confirmPass = modal && modal.querySelector('#confirm_password');
    var errorBox = modal && modal.querySelector('.password-form__error');

    // Bind toggle directly to each button inside the modal
    modal && modal.querySelectorAll('.password-toggle').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
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

    var closeBtn = modal && modal.querySelector('[data-close-password]');
    if(closeBtn){
        closeBtn.addEventListener('click', function(){
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        });
    }

    var DEFAULT_PASSWORD = 'Mlinc1234';

    if(form){
        form.addEventListener('submit', function(e){
            if(errorBox) errorBox.style.display = 'none';
            if(newPass && confirmPass){
                if(newPass.value === DEFAULT_PASSWORD){
                    e.preventDefault();
                    if(errorBox){ errorBox.textContent = 'Use another password.'; errorBox.style.display = 'block'; }
                    newPass.focus();
                    return false;
                }
                if(newPass.value !== confirmPass.value){
                    e.preventDefault();
                    if(errorBox){ errorBox.textContent = 'Passwords do not match.'; errorBox.style.display = 'block'; }
                    newPass.focus();
                    return false;
                }
            }
            return true;
        });
    }
})();
</script>
