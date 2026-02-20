(function () {
    const form = document.getElementById('registerForm');
    if (!form) return;

    const emailInput = document.getElementById('email');
    const status = document.getElementById('emailStatus');
    let emailAvailable = false;

    async function checkEmail() {
        const email = emailInput.value.trim();
        emailAvailable = false;
        status.textContent = '';

        if (!email || !/^\S+@\S+\.\S+$/.test(email)) {
            return;
        }

        try {
            const response = await fetch(`../ajax/check_email.php?email=${encodeURIComponent(email)}`);
            const data = await response.json();
            emailAvailable = data.valid && !data.exists;
            status.textContent = data.message || '';
            status.style.color = emailAvailable ? '#166534' : '#b91c1c';
        } catch (error) {
            status.textContent = 'Could not verify email right now.';
            status.style.color = '#b91c1c';
        }
    }

    emailInput.addEventListener('blur', checkEmail);

    form.addEventListener('submit', async (event) => {
        let errors = [];

        const fullName = document.getElementById('full_name').value.trim();
        const password = document.getElementById('password').value;
        const phone = document.getElementById('phone').value.trim();
        const gender = document.getElementById('gender').value;
        const course = document.getElementById('course').value;
        const address = document.getElementById('address').value.trim();
        const about = document.getElementById('about').value.trim();

        if (fullName.length < 3) errors.push('Full name must be at least 3 characters.');
        if (password.length < 8) errors.push('Password must be at least 8 characters.');
        if (!/^[0-9+\-() ]{7,20}$/.test(phone)) errors.push('Please provide a valid phone number.');
        if (!gender) errors.push('Please select a gender.');
        if (!course) errors.push('Please select a course.');
        if (!address) errors.push('Address is required.');
        if (!about) errors.push('About is required.');

        await checkEmail();
        if (!emailAvailable) errors.push('Email is already registered or invalid.');

        if (errors.length > 0) {
            event.preventDefault();
            alert(errors.join('\n'));
        }
    });
})();