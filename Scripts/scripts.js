document.addEventListener('DOMContentLoaded', () => {
    const themeToggleBtn = document.getElementById('theme-toggle');
    const languageBtns = document.querySelectorAll('.language-btn');
    const welcomeMessage = document.getElementById('welcome-message');

    themeToggleBtn.addEventListener('click', () => {
        document.body.dataset.theme = document.body.dataset.theme === 'dark' ? 'light' : 'dark';
    });

    languageBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const lang = btn.id;
            if (lang === 'hu') {
                welcomeMessage.textContent = 'Üdvözöljük a Neptun Hasonmás rendszerben!';
            } else if (lang === 'en') {
                welcomeMessage.textContent = 'Welcome to the Neptun Clone System!';
            } else if (lang === 'de') {
                welcomeMessage.textContent = 'Willkommen im Neptun-Klon-System!';
            }
        });
    });
})