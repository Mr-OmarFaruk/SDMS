// Hide notification messages after a short delay.
document.querySelectorAll('.alert').forEach((alert) => {
    window.setTimeout(() => {
        alert.style.transition = 'opacity 400ms';
        alert.style.opacity = '0';
        window.setTimeout(() => alert.remove(), 400);
    }, 4500);
});

// Put a demo account email into the sign-in form when its button is clicked.
document.querySelectorAll('[data-demo-email]').forEach((button) => {
    button.addEventListener('click', () => {
        const emailInput = document.querySelector('input[name="email"]');
        if (emailInput) {
            emailInput.value = button.dataset.demoEmail;
            emailInput.focus();
        }
    });
});