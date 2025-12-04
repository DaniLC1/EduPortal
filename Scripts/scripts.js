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

    // Slider
    const imgElement = document.getElementById("slider-image");
    if (imgElement) {
        const sliderImages = [
            "slider_pictures/no_copyright1.png",
            "slider_pictures/no_copyright2.jpg",
            "slider_pictures/no_copyright3.jpg",
            "slider_pictures/no_copyright4.jpeg",
            "slider_pictures/no_copyright5.jpg"
        ];

        let currentIndex = 0;
        setInterval(() => {
            currentIndex = (currentIndex + 1) % sliderImages.length;
            imgElement.src = sliderImages[currentIndex];
        }, 3000);
    }
});

//Oldalsó menü lenyitása
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

//Kurzus leírás kinyitás és összecsukás
function toggleDescription(button) {
    const container = button.closest('.collapsible-container');
    const shortText = container.querySelector('.short-description');
    const fullContent = container.querySelector('.collapsible-content');

    const isOpen = container.classList.toggle('open');

    if (isOpen) {
        shortText.classList.add('hidden');
        fullContent.style.maxHeight = fullContent.scrollHeight + "px";
        fullContent.style.opacity = "1";
        button.textContent = button.dataset.lessText || "Kevesebb";
    } else {
        shortText.classList.remove('hidden');
        fullContent.style.maxHeight = "0";
        fullContent.style.opacity = "0";
        button.textContent = button.dataset.moreText || "Bővebben";
    }
}

document.addEventListener('input', function (e) {
    if (e.target.classList.contains('auto-resize-textarea')) {
        e.target.style.height = 'auto';
        e.target.style.height = (e.target.scrollHeight) + 'px';
    }
});

// Szerkesztés űrlap megjelenítése (hirdetmény és fórum hozzászólásnál)
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

//Új ötlet: Az összes filter 1 helyen kezelve a js-ben.
function safeGet(id) {
    return document.getElementById(id) || null;
}

document.addEventListener('DOMContentLoaded', () => {

    // ==========================================
    // SAFE ELEMENT GETTER
    // ==========================================
    const searchInput =
        safeGet('sec_searchInput') ||
        safeGet('tar_searchInput') ||
        safeGet('ss_searchInput') ||
        safeGet('sr_searchInput') ||
        safeGet('tr_searchInput') ||
        safeGet('tsc_searchInput') ||
        safeGet('sco_searchInput') ||
        safeGet('tco_searchInput') ||
        safeGet('ar_searchInput') ||
        safeGet('asr_searchInput') ||
        safeGet('am_searchInput') ||
        safeGet('tm_searchInput') ||
        safeGet('sm_searchInput');

    const semesterFilter =
        safeGet('sec_semesterFilter') ||
        safeGet('tar_semesterFilter') ||
        safeGet('tsc_semesterFilter') ||
        safeGet('ss_semesterFilter');

    const typeFilter =
        safeGet('sec_typeFilter') ||
        safeGet('tar_typeFilter') ||
        safeGet('ss_typeFilter') ||
        safeGet('sco_typeFilter');

    const courseFilter =
        safeGet('sec_courseFilter') ||
        safeGet('tar_courseFilter') ||
        safeGet('tsc_courseFilter') ||
        safeGet('tco_courseFilter');

    const statusFilter =
        safeGet('ss_statusFilter') ||
        safeGet('sco_statusFilter');

    // ==========================================
    // KÁRTYÁK KIVÁLASZTÁSA – oldaltól függően
    // ==========================================
    let cards = [];

    if (document.querySelectorAll('.sec_course-card').length) {
        cards = document.querySelectorAll('.sec_course-card');

    } else if (document.querySelectorAll('.tar_course-card').length) {
        cards = document.querySelectorAll('.tar_course-card');

    } else if (document.querySelectorAll('.ss_subject-card').length) {
        cards = document.querySelectorAll('.ss_subject-card');

    }else if (document.querySelectorAll('.tsc_course-card').length) {
        cards = document.querySelectorAll('.tsc_course-card');

    } else if (document.querySelectorAll('.sr_request-card').length) {
        cards = document.querySelectorAll('.sr_request-card');

    } else if (document.querySelectorAll('.tr_request-card').length) {
        cards = document.querySelectorAll('.tr_request-card');

    } else if (document.querySelectorAll('.sco_course-card').length) {
        cards = document.querySelectorAll('.sco_course-card');

    } else if (document.querySelectorAll('.tco_course-card').length) {
        cards = document.querySelectorAll('.tco_course-card');

    } else if (document.querySelectorAll('.ar_request-card').length) {
        cards = document.querySelectorAll('.ar_request-card');

    } else if (document.querySelectorAll('.asr_request-card').length) {
        cards = document.querySelectorAll('.asr_request-card');

    } else if (document.querySelectorAll('.sm_user-item').length) {
        cards = document.querySelectorAll('.sm_user-item');

    } else if (document.querySelectorAll('.tm_user-item').length) {
        cards = document.querySelectorAll('.tm_user-item');

    } else if (document.querySelectorAll('.am_user-item').length) {
        cards = document.querySelectorAll('.am_user-item');

    }

    // Ha nincs kártya → nincs mit szűrni
    if (!cards.length) return;


    // ==========================================
    // FILTER LOGIKA
    // ==========================================
    function filterCourses() {
        const searchText = searchInput?.value.toLowerCase().trim() || '';
        const selectedSemester = semesterFilter?.value || 'all';
        const selectedType = typeFilter?.value || 'all';
        const selectedCourse = courseFilter?.value || 'all';
        const selectedStatus = statusFilter?.value || 'all';

        cards.forEach(card => {
            // -------- ÚJ TÁRGYFELVÉTEL OLDAL DATA ATTRIBÚTUMAI --------
            const name= (card.dataset.name || '').toLowerCase();
            const code= (card.dataset.code || '').toLowerCase();
            const teacher= (card.dataset.teacher || '').toLowerCase();
            const semester= card.dataset.semester || '';
            const type= card.dataset.type || '';
            const completed= card.dataset.completed || '0';
            const eduid = (card.dataset.eduid || '').toLowerCase()


            // Régi request oldalakhoz még mindig kell
            const title       = (card.dataset.title || '').toLowerCase();
            const description = (card.dataset.description || '').toLowerCase();

            // ---------- MATCH LOGIKA ----------
            const matchesSearch =
                name.includes(searchText) ||
                code.includes(searchText) ||
                teacher.includes(searchText) ||
                title.includes(searchText) ||
                description.includes(searchText)||
                eduid.includes(searchText);

            const matchesSemester = (selectedSemester === 'all' || semester === selectedSemester);
            const matchesType     = (selectedType === 'all' || type === selectedType);
            const matchesStatus   = (selectedStatus === 'all' || completed === selectedStatus);
            const matchesCourse   = (selectedCourse === 'all' || code === selectedCourse);

            // VÉGEREDMÉNY
            if (matchesSearch && matchesSemester && matchesType && matchesStatus && matchesCourse) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }


    // ==========================================
    // ESEMÉNYFIGYELŐK — CSAK AMI LÉTEZIK
    // ==========================================
    if (searchInput) searchInput.addEventListener('input', filterCourses);
    if (semesterFilter) semesterFilter.addEventListener('change', filterCourses);
    if (typeFilter) typeFilter.addEventListener('change', filterCourses);
    if (courseFilter) courseFilter.addEventListener('change', filterCourses);
    if (statusFilter) statusFilter.addEventListener('change', filterCourses);
});

function toggleDetails(id) {
    const element = document.getElementById('details-' + id);
    if (element.style.display === 'none') {
        element.style.display = 'block';
    } else {
        element.style.display = 'none';
    }
}

// --- Dolgozat kérdéskezelő (teacher/assignment.php rész) ---
document.addEventListener("DOMContentLoaded", () => {
    const questionContainer = document.getElementById("question-container");
    const addQuestionBtn = document.querySelector(".add-btn");
    const cancelBtn = document.querySelector(".cancel-btn");

    // 🔹 Új kérdés hozzáadása
    if (addQuestionBtn && questionContainer) {
        addQuestionBtn.addEventListener("click", () => {
            const qid = "new_" + Date.now();
            const newQuestion = document.createElement("div");
            newQuestion.classList.add("question-card");
            newQuestion.dataset.qid = qid;

            newQuestion.innerHTML = `
            <button type="button" class="remove-question">❌</button>
            <textarea class="auto-resize-textarea" id="question-card-question" name="questions[${qid}][text]" placeholder="Kérdés szövege..." rows="1"></textarea>

            <div class="question-meta">
                <label>Típus:</label>
                <select name="questions[${qid}][type]">
                    <option value="multiple_choice">Feleletválasztós</option>
                    <option value="true_false">Igaz / Hamis</option>
                </select>

                <label>Pontszám:</label>
                <input type="number" name="questions[${qid}][score]" value="1" min="1" step="1">
            </div>

            <div class="answers">
                <label>Válaszok:</label>
                <div class="answer-card">
                    <input type="text" name="questions[${qid}][answers][ans_1][text]" placeholder="Válasz...">
                    <label><input type="checkbox" name="questions[${qid}][answers][ans_1][is_correct]"> Helyes</label>
                    <button type="button" class="remove-answer">❌</button>
                </div>
                <button type="button" class="add-answer">➕ Válasz hozzáadása</button>
            </div>
        `;

            questionContainer.appendChild(newQuestion);
        });
    }

    // 🔹 Eseménydelegálás: törlés, válasz hozzáadás, válasz törlés
    document.addEventListener("click", (e) => {
        // ❌ Kérdés törlése
        if (e.target.classList.contains("remove-question")) {
            e.target.closest(".question-card").remove();
        }

        // ➕ Új válasz hozzáadása
        if (e.target.classList.contains("add-answer")) {
            const questionCard = e.target.closest(".question-card");

            // 🔹 QID lekérése a meglévő input/textarea name-jéből
            let qid;
            const firstInput = questionCard.querySelector('textarea[name], input[name], select[name]');
            if (firstInput && firstInput.name) {
                const match = firstInput.name.match(/^questions\[([^\]]+)\]\[/);
                qid = match ? match[1] : "new_" + Date.now();
            } else {
                qid = "new_" + Date.now();
            }

            const uniqueId = "ans_" + Date.now(); // válasz egyedi azonosítója

            const newAnswer = document.createElement("div");
            newAnswer.classList.add("answer-card");
            newAnswer.innerHTML = `
                <input type="text" name="questions[${qid}][answers][${uniqueId}][text]" placeholder="Válasz...">
                <label><input type="checkbox" name="questions[${qid}][answers][${uniqueId}][is_correct]"> Helyes</label>
                <button type="button" class="remove-answer">❌</button>
            `;
            e.target.before(newAnswer);
        }

        // ❌ Válasz törlése
        if (e.target.classList.contains("remove-answer")) {
            e.target.closest(".answer-card").remove();
        }
    });

    // 🚫 Mégse gomb
    if (cancelBtn) {
        cancelBtn.addEventListener("click", () => {
            if (confirm("Biztosan kilépsz mentés nélkül? Minden módosítás elvész.")) {
                window.location.href = "../teacher/courses.php";
            }
        });
    }
});

// --- admin/request.php interaktív logika ---
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('requests-container');
    const newBtn = document.getElementById('new-request-btn');
    if (!container || !newBtn) return;

    // 🔹 Új kérelem létrehozása
    newBtn.addEventListener('click', () => {
        const id = 'new_' + Date.now(); // egyedi azonosító generálása
        const card = document.createElement('div');
        card.classList.add('ar_request-card');
        card.dataset.new = "true"; // ⚙️ megjelöljük, hogy ez új (még nincs mentve az adatbázisban)

        // 🔸 Új kérelem űrlap HTML tartalma
        card.innerHTML = `
            <div class="card-header">
                <h2>Új kérelem</h2>
            </div>
            <div class="card-body" style="display:block;">
                <!-- 🧾 Az űrlap most már POST kérést küld a request_post.php felé -->
                <form method="POST" action="../POST/request_post.php" class="request-edit-form">
                    <!-- A forrás megjelölése a backend számára -->
                    <input type="hidden" name="admin_request">

                    <label>Cím:</label>
                    <input type="text" name="title" placeholder="Kérelem címe..." required>

                    <label>Leírás:</label>
                    <textarea class="auto-resize-textarea" name="description"></textarea>

                    <label>Címzett:</label>
                    <select name="to_who">
                        <option value="hallgato">Hallgató</option>
                        <option value="tanar">Tanár</option>
                    </select>

                    <hr>
                    <h3>Mezők</h3>
                    <div class="fields-container"></div>
                    <button type="button" class="ar_add-field-btn">➕ Mező hozzáadása</button>

                    <div class="form-actions">
                        <button type="submit" class="ar_fill-btn">💾 Mentés</button>
                        <button type="button" class="ar_cancel-btn">🚫 Mégse</button>
                    </div>
                </form>
            </div>
        `;
        // Az új kérelemkártyát az oldal tetejére helyezzük
        container.prepend(card);
    });

    // 🔹 Eseménykezelés delegálva a containerre (így a dinamikusan létrehozott elemek is működnek)
    container.addEventListener('click', (e) => {
        const card = e.target.closest('.ar_request-card');
        if (!card) return;

        // ✏️ Szerkesztés lenyitás / becsukás
        if (e.target.classList.contains('ar_edit-btn')) {
            const body = card.querySelector('.card-body');
            body.style.display = body.style.display === 'none' ? 'block' : 'none';
        }

        // ➕ Mező hozzáadása
        if (e.target.classList.contains('ar_add-field-btn')) {
            const fieldsContainer = card.querySelector('.fields-container');
            const fid = 'new_' + Date.now(); // egyedi azonosító a mezőnek
            const field = document.createElement('div');
            field.classList.add('field-card');

            // 🧩 Új mező HTML tartalma
            field.innerHTML = `
                <input type="text" name="fields[${fid}][label]" placeholder="Címke">
                <select name="fields[${fid}][field_type]">
                    <option value="text">Szöveg</option>
                    <option value="number">Szám</option>
                    <option value="date">Dátum</option>
                    <option value="textarea">Többsoros</option>
                </select>
                <label>
                    <input type="checkbox" name="fields[${fid}][is_required]" value="1">
                    Kötelező
                </label>
                <button type="button" class="ar_remove-field-btn">❌</button>
            `;
            fieldsContainer.appendChild(field);
        }

        // ❌ Egy adott mező törlése
        if (e.target.classList.contains('ar_remove-field-btn')) {
            e.target.closest('.field-card').remove();
        }

        // 🚫 Mégse gomb: új kérelem esetén töröljük a kártyát, meglévőnél csak bezárjuk
        if (e.target.classList.contains('ar_cancel-btn')) {
            if (card.dataset.new === "true") {
                // ❌ Még nem mentett új kérelem → teljesen eltávolítjuk a DOM-ból
                card.remove();
            } else {
                // 🔒 Meglévő kérelem → csak a részleteket csukjuk össze
                const body = card.querySelector('.card-body');
                if (body) body.style.display = 'none';
            }
        }
    });
});

//messages-nél ha sok az üzenet autómatikusan legörget a legfrissebbhez
document.addEventListener("DOMContentLoaded", function () {
    const chatBox = document.getElementById("chat-box");
    if (chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }
});

