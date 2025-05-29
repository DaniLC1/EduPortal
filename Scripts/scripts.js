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
                    hu: 'Üdvözöljük az EduPortal-on!',
                    en: 'Welcome to EduPortal!',
                    de: 'Willkommen im EduPortal!'
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

// Tartalom lenyitása/összecsukása animációval (kurzus leírásnál)
function toggleContent(button) {
    const wrapper = button.closest('.description-container');
    const shortDesc = wrapper.querySelector('.short-description');
    const fullDesc = wrapper.querySelector('.full-description');
    const isExpanded = fullDesc.classList.contains('show');

    if (!isExpanded) {
        shortDesc.classList.add('hidden');
        fullDesc.classList.add('show');

        // Dinamikus magasság beállítása
        fullDesc.style.maxHeight = fullDesc.scrollHeight + 'px';
        fullDesc.style.opacity = '1';

        // Gomb mozgatás
        button.textContent = 'Kevesebb';
    } else {
        shortDesc.classList.remove('hidden');

        // Összecsukás animáció
        fullDesc.style.maxHeight = '0';
        fullDesc.style.opacity = '0';

        fullDesc.classList.remove('show');
        button.textContent = 'Bővebben';
    }
}

// Lista lenyitása/összecsukása (pl. hirdetmények, fórum)
function toggleList(button) {
    const list = button.previousElementSibling;
    const hiddenItems = list.querySelectorAll('.collapsible-item');
    const isHidden = hiddenItems[0].classList.contains('hidden');
    hiddenItems.forEach(item => item.classList.toggle('hidden'));
    button.textContent = isHidden ? 'Kevesebb' : 'További hozásólás';
    const preview = button.previousElementSibling;
    preview.classList.toggle('expanded');
}

document.addEventListener('input', function (e) {
    if (e.target.classList.contains('auto-resize-textarea')) {
        e.target.style.height = 'auto';
        e.target.style.height = (e.target.scrollHeight) + 'px';
    }
});

// Szerkesztés űrlap megjelenítése
function toggleEditForm(button) {
    const li = button.closest('li');
    const form = li.querySelector('.edit-form');
    form.classList.toggle('hidden');
}

// Kredit diagram
document.addEventListener('DOMContentLoaded', () => {
    const dataElement = document.getElementById('credit-data');
    const chartCanvas = document.getElementById('creditChart');

    if (dataElement && chartCanvas) {
        const completedCredits = parseInt(dataElement.dataset.completed, 10);
        const totalCredits = parseInt(dataElement.dataset.total, 10);
        const remainingCredits = totalCredits - completedCredits;

        const ctx = chartCanvas.getContext('2d');
        const creditChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Teljesített', 'Hátralévő'],
                datasets: [{
                    data: [completedCredits, remainingCredits],
                    backgroundColor: ['#007bff', '#ffa500'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.label + ': ' + context.raw + ' kredit';
                            }
                        }
                    }
                }
            }
        });
    }
});

//Tanulmányok szűrő
document.addEventListener("DOMContentLoaded", function () {
    const searchInput = document.getElementById("searchInput");
    const statusFilter = document.getElementById("statusFilter");
    const typeFilter = document.getElementById("typeFilter");
    const subjectCards = document.querySelectorAll(".subject-card");

    function filterCourses() {
        const searchTerm = searchInput.value.toLowerCase();
        const status = statusFilter.value;
        const type = typeFilter.value;

        subjectCards.forEach(card => {
            const name = card.dataset.name;
            const cardStatus = card.dataset.status;
            const cardType = card.dataset.type;

            const matchesSearch = name.includes(searchTerm);
            const matchesStatus = (status === "all" || status === cardStatus);
            const matchesType = (type === "all" || type === cardType);

            if (matchesSearch && matchesStatus && matchesType) {
                card.style.display = "block";
            } else {
                card.style.display = "none";
            }
        });
    }

    searchInput.addEventListener("input", filterCourses);
    statusFilter.addEventListener("change", filterCourses);
    typeFilter.addEventListener("change", filterCourses);
});

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const semesterFilter = document.getElementById('semesterFilter');
    const typeFilter = document.getElementById('typeFilter');
    const cards = document.querySelectorAll('.course-card');

    function filterCourses() {
        const searchText = searchInput.value.toLowerCase().trim();
        const selectedSemester = semesterFilter.value;
        const selectedType = typeFilter.value;

        cards.forEach(card => {
            const name = card.dataset.name;
            const semester = card.dataset.semester;
            const type = card.dataset.type;

            const matchesName = name.includes(searchText);
            const matchesSemester = (selectedSemester === 'all' || semester === selectedSemester);
            const matchesType = (selectedType === 'all' || type === selectedType);

            if (matchesName && matchesSemester && matchesType) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Eseményfigyelők
    searchInput.addEventListener('input', filterCourses);
    semesterFilter.addEventListener('change', filterCourses);
    typeFilter.addEventListener('change', filterCourses);
});

//popup működés:
function openPaymentModal(financingId, maxAmount) {
    document.getElementById('modalFinancingId').value = financingId;
    document.getElementById('paymentAmount').value = maxAmount;
    document.getElementById('paymentAmount').max = maxAmount;
    document.getElementById('paymentModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

function toggleDetails(id) {
    const element = document.getElementById('details-' + id);
    if (element.style.display === 'none') {
        element.style.display = 'block';
    } else {
        element.style.display = 'none';
    }
}

//kérelmek beküldés + stb gomb működése:
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.toggle-desc-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const card = btn.closest('.request-card');
            const desc = card.querySelector('.card-description');
            desc.style.display = desc.style.display === 'none' ? 'block' : 'none';
        });
    });

    document.querySelectorAll('.fill-request-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            // Csukjon be minden más formot
            document.querySelectorAll('.request-form').forEach(f => {
                f.style.display = 'none';
                f.querySelector('fieldset').disabled = true;
                f.querySelector('.form-actions').style.display = 'none';
            });
            const card = btn.closest('.request-card');
            const form = card.querySelector('.request-form');
            const fieldset = form.querySelector('fieldset');
            const actions = form.querySelector('.form-actions');

            // Ne csinálj semmit, ha már nyitva van!
            if (form.style.display === 'block') return;

            form.style.display = 'block';
            fieldset.disabled = false;
            actions.style.display = 'flex';
        });
    });

    document.querySelectorAll('.cancel-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const form = btn.closest('.request-form');
            const fieldset = form.querySelector('fieldset');
            const actions = form.querySelector('.form-actions');

            form.reset();
            fieldset.disabled = true;
            actions.style.display = 'none';
        });
    });

    // Engedélyezzük a fieldsetet submit előtt, hogy elküldje az adatokat
    document.querySelectorAll('.request-form').forEach(form => {
        form.addEventListener('submit', (e) => {
            const fieldset = form.querySelector('fieldset');
            if (fieldset.disabled) {
                fieldset.disabled = false;
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.toggle-fields-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const details = this.closest('.submitted-request-card').querySelector('.submitted-details');
            details.style.display = (details.style.display === 'none' || !details.style.display) ? 'block' : 'none';
            this.textContent = details.style.display === 'block' ? 'Elrejt' : 'Részletek';
        });
    });
});