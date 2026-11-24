document.addEventListener('DOMContentLoaded', function () {

    // === Registration form validation ===
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function (e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (password !== confirmPassword) {
                e.preventDefault();
                showAlert('Passwords do not match!', 'danger');
                return;
            }
            if (password.length < 8) {
                e.preventDefault();
                showAlert('Password must be at least 8 characters long!', 'danger');
            }
        });
    }

    // === Demo login buttons: fill the login form and submit it ===
    document.querySelectorAll('[data-demo-email]').forEach(function (button) {
        button.addEventListener('click', function () {
            const form = document.getElementById('loginForm');
            if (!form) return;
            form.querySelector('#email').value = button.dataset.demoEmail;
            form.querySelector('#password').value = button.dataset.demoPassword;
            form.submit();
        });
    });

    // === Login form validation ===
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            if (!email || !password) {
                e.preventDefault();
                showAlert('Please fill in all fields!', 'danger');
            }
        });
    }

    // === Confirm destructive actions: <button data-confirm="Are you sure?"> ===
    document.querySelectorAll('[data-confirm]').forEach(function (button) {
        button.addEventListener('click', function (e) {
            if (!window.confirm(button.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // === Progress bars grow in on load ===
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        document.querySelectorAll('.progress-bar').forEach(function (bar) {
            const target = bar.style.width;
            bar.style.width = '0%';
            requestAnimationFrame(function () {
                bar.style.transition = 'width 0.8s ease';
                bar.style.width = target;
            });
        });
    }

    // === Flash messages fade out after a few seconds ===
    document.querySelectorAll('.alert-flash').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 500);
        }, 6000);
    });

    // === Bootstrap tooltips ===
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
});

// Show a dismissible alert at the top of the main content.
function showAlert(message, type = 'info') {
    const alert = document.createElement('div');
    alert.className = 'alert alert-' + type + ' alert-dismissible fade show alert-flash';
    alert.setAttribute('role', 'alert');
    alert.textContent = message;
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close';
    close.setAttribute('data-bs-dismiss', 'alert');
    close.setAttribute('aria-label', 'Close');
    alert.appendChild(close);

    const container = document.querySelector('main .container, main') || document.body;
    container.prepend(alert);
}
