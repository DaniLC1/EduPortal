document.addEventListener('DOMContentLoaded', () => {
// Téma váltás
    const themeToggleBtn = document.getElementById('theme-toggle') || document.getElementById('themeToggleBtn');
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.body.dataset.theme = savedTheme;
    updateThemeVariables(savedTheme);

    if (themeToggleBtn) {
        themeToggleBtn.textContent = savedTheme === 'dark' ? '☀️' : '🌙';

        themeToggleBtn.addEventListener('click', () => {
            const isDark = document.body.dataset.theme === 'dark';
            const newTheme = isDark ? 'light' : 'dark';
            document.body.dataset.theme = newTheme;
            localStorage.setItem('theme', newTheme);
            themeToggleBtn.textContent = newTheme === 'dark' ? '☀️' : '🌙';
            updateThemeVariables(newTheme);
        });
    }

    // Függvény, ami frissíti a CSS változókat
    function updateThemeVariables(theme) {
        if (theme === 'dark') {
            document.documentElement.style.setProperty('--card-background', 'black');
        } else {
            document.documentElement.style.setProperty('--card-background', 'white');
        }
    }

    // Nyelv váltás (csak ha van nyelvváltó gomb)
    const languageBtns = document.querySelectorAll('.language-btn');
    const welcomeMessage = document.getElementById('welcome-message');

    if (languageBtns.length > 0 && welcomeMessage) {
        languageBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const lang = btn.id;
                const messages = {
                    hu: 'Üdvözöljük a Neptun Hasonmás rendszerben!',
                    en: 'Welcome to the Neptun Clone System!',
                    de: 'Willkommen im Neptun-Klon-System!'
                };
                welcomeMessage.textContent = messages[lang] || messages.hu;
            });
        });
    }

    // Slider
    const imgElement = document.getElementById("slider-image");
    if (imgElement) {
        const sliderImages = [
            "slider_pictures/slide1.png",
            "slider_pictures/slider2.jpg",
            "slider_pictures/slider3.jpg",
            "slider_pictures/slider4.jpg",
            "slider_pictures/slider5.jpg"
        ];

        let currentIndex = 0;
        setInterval(() => {
            currentIndex = (currentIndex + 1) % sliderImages.length;
            imgElement.src = sliderImages[currentIndex];
        }, 3000);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const dropdowns = [
        { toggleId: 'dropdownToggleL', menuId: 'dropdownMenuL' },
        { toggleId: 'dropdownToggleR', menuId: 'dropdownMenuR' }
    ];

    dropdowns.forEach(({ toggleId, menuId }) => {
        const toggle = document.getElementById(toggleId);
        const menu = document.getElementById(menuId);

        if (toggle && menu) {
            toggle.addEventListener('click', (e) => {
                e.stopPropagation(); // ne zárja be azonnal
                menu.classList.toggle('show');
            });

            document.addEventListener('click', (e) => {
                if (!toggle.contains(e.target) && !menu.contains(e.target)) {
                    menu.classList.remove('show');
                }
            });
        }
    });
});