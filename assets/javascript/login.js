(function () {
    const form = document.getElementById('loginForm');
    if (!form) return;

    form.addEventListener('submit', function (event) {
        const email = document.getElementById('login_email').value.trim();
        const password = document.getElementById('login_password').value;

        if (!/^\S+@\S+\.\S+$/.test(email) || password.length === 0) {
            event.preventDefault();
            alert('Please enter a valid email and password.');
        }
    });
})();