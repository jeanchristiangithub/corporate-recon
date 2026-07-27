(function () {
    const openButtons = document.querySelectorAll('[data-open-login]');
    const modal = document.getElementById('loginModal');
    const closeButton = document.querySelector('[data-close-login]');

    if (!modal) {
        return;
    }

    const openModal = () => {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    };

    const closeModal = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    };

    openButtons.forEach((button) => {
        button.addEventListener('click', openModal);
    });

    if (closeButton) {
        closeButton.addEventListener('click', closeModal);
    }

    if (window.autoreconLoginError) {
        openModal();
    }
})();
