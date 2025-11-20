<?php
session_start();
require_once __DIR__. '/../connection.php'; // Adatbáziskapcsolat betöltése

if (!isset($_SESSION['eduportal_id'])) {
    header("Location: ../index.php");
    exit;
}

// 🧑‍🏫 Csak tanárok léphetnek be
if ($_SESSION['role'] !== 'tanar') {
    header("Location: ../index.php?error=unauthorized");
    exit;
}

$eduportal_id = $_SESSION['eduportal_id'];
global $conn; // Globális változó használata

// 🔹 Tanár adatai
$user_sql = "
SELECT name
FROM users 
WHERE eduportal_id = ?";

$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("s", $eduportal_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

$user_name = $user['name'] ?? 'Ismeretlen';
$user_course = "Tanár";



// === 1️⃣ DIÁKOK ÉS JEGYEK LEKÉRDEZÉSE ===
$students_sql = "
SELECT 
    e.offering_id AS offering_id,
    e.users_eduportal_id AS student_id,
    u.name AS student_name,
    c.kurzus_kod AS subject_code,
    c.name AS subject_name,
    s.label AS semester_label,
    e.status AS enrollment_status,
    e.grade AS current_grade,
    e.enrolled_at,
    e.completed_at
FROM enrollments e
JOIN users u ON u.eduportal_id = e.users_eduportal_id
JOIN course_offerings co ON co.id = e.offering_id
JOIN courses c ON c.kurzus_kod = co.kurzus_kod
JOIN semesters s ON s.id = co.semester_id
JOIN teacher_courses tc ON tc.kurzus_kod = co.kurzus_kod
WHERE tc.teacher_id = ?
GROUP BY e.users_eduportal_id, c.kurzus_kod, s.label, c.name
ORDER BY s.label DESC, u.name ASC, c.name ASC
";

$students_stmt = $conn->prepare($students_sql);
$students_stmt->bind_param("s", $eduportal_id);
$students_stmt->execute();
$students_result = $students_stmt->get_result();

$students = [];
while ($r = $students_result->fetch_assoc()) {
    $students[] = $r;
}
$students_stmt->close();


// === SZŰRŐKHÖZ SZÜKSÉGES ADATOK ===

// 1. Tárgyak (offeringek) a tanárhoz
$filter_courses_sql = "
SELECT 
    co.id AS offering_id,
    CONCAT(c.name, ' (', s.label, ', ', co.day_of_week, ' ', TIME_FORMAT(co.start_time, '%H:%i'), ')') AS label
FROM teacher_courses tc
JOIN course_offerings co ON tc.kurzus_kod = co.kurzus_kod
JOIN courses c ON c.kurzus_kod = co.kurzus_kod
JOIN semesters s ON s.id = co.semester_id
WHERE tc.teacher_id = ?
ORDER BY c.name ASC, s.start_date DESC
";
$filter_courses_stmt = $conn->prepare($filter_courses_sql);
$filter_courses_stmt->bind_param("s", $eduportal_id);
$filter_courses_stmt->execute();
$filter_courses_result = $filter_courses_stmt->get_result();

// 2. Szemeszterek
$semester_sql = "
SELECT DISTINCT s.label
FROM semesters s
JOIN course_offerings co ON s.id = co.semester_id
JOIN teacher_courses tc ON co.kurzus_kod = tc.kurzus_kod
WHERE tc.teacher_id = ?
ORDER BY s.label DESC
";

$semester_stmt = $conn->prepare($semester_sql);
$semester_stmt->bind_param("s", $eduportal_id);
$semester_stmt->execute();
$semester_result = $semester_stmt->get_result();

?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <title>EduPortál</title>
    <link rel="stylesheet" href="../CSS/site_style.css">
    <link rel="stylesheet" href="../CSS/enrolled_courses.css">
</head>
<body>
<header>
    <!-- BAL MENÜ -->
    <div class="menu">
        <div class="dropdown">
            <button id="dropdownToggleL" class="dropbtn">☰ Menü </button>
            <div id="dropdownMenuL" class="dropdown-menu left">
                <a href="assignment_result.php">Eredmények</a>
                <a href="#" id="active">Lezárások</a>
            </div>
        </div>
    </div>

    <!-- NAVIGÁCIÓ -->
    <nav class="main-nav">
        <a href="course_offering.php"><span class="icon">📘</span> Tárgyfelvétel</a>
        <a href="courses.php"><span class="icon">🧑‍🏫</span> Kurzusok</a>
        <a href="request.php"><span class="icon">📄</span> Kérelmek</a>
    </nav>

    <!-- JOBB OLDALI MENÜ -->
    <div class="user-menu">
        <div class="dropdown">
            <button id="dropdownToggleR" class="dropbtn">
                <?php echo htmlspecialchars($user_name); ?> |
                <?php echo htmlspecialchars($eduportal_id); ?> |
                <?php echo htmlspecialchars($user_course); ?>
            </button>
            <div id="dropdownMenuR" class="dropdown-menu right">
                <a href="profile.php">Beállítások</a>
                <a href="../logout.php">Kijelentkezés</a>
            </div>
        </div>
        <!-- TÉMAVÁLTÓ GOMB -->
        <div class="theme-switcher">
            <button id="theme-toggle" class="theme-btn">🌙</button>
        </div>
    </div>
</header>

<main class="layout">
    <h1>Diákok jegyeinek lezárása</h1>
    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
        <div class="success-message">
            ✅ A jegy sikeresen mentve!
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="error-message">
            ⚠️ <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>


    <section class="filters">

        <label for="semesterFilter">Félév:</label>
        <select id="semesterFilter">
            <option value="all">Összes</option>
            <?php
            while ($row = $semester_result->fetch_assoc()) {
                echo "<option value=\"" . htmlspecialchars($row['label']) . "\">" . htmlspecialchars($row['label']) . "</option>";
            }
            ?>
        </select>

        <label for="courseFilter">Tárgy:</label>
        <select id="courseFilter">
            <option value="all">Összes</option>
            <?php while ($row = $filter_courses_result->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($row['offering_id']) ?>">
                    <?= htmlspecialchars($row['label']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <input type="text" id="searchInput" placeholder="Keresés diák nevére...">
    </section>

    <?php if (empty($students)): ?>
        <p>Jelenleg nincsenek beiratkozott hallgatók.</p>
    <?php else: ?>
        <section id="courseList" class="course-grid">
            <?php foreach ($students as $st): ?>
                <div class="course-card"
                     data-semester="<?= htmlspecialchars($st['semester_label']) ?>"
                     data-offering="<?= htmlspecialchars($st['offering_id']) ?>"
                     data-name="<?= strtolower(htmlspecialchars($st['student_name'])) ?>">

                    <div class="card-header">
                        <h3><?= htmlspecialchars($st['student_name']) ?></h3>
                        <span class="code"><?= htmlspecialchars($st['student_id']) ?></span>
                    </div>

                    <div class="card-body">
                        <p><strong>Tárgy:</strong> <?= htmlspecialchars($st['subject_name']) ?></p>
                        <p><strong>Kód:</strong> <?= htmlspecialchars($st['subject_code']) ?></p>
                        <p><strong>Félév:</strong> <?= htmlspecialchars($st['semester_label']) ?></p>
                        <p><strong>Felvétel dátuma:</strong> <?= htmlspecialchars($st['enrolled_at']) ?></p>
                        <p><strong>Státusz:</strong>
                            <?php
                            switch ($st['enrollment_status']) {
                                case 'completed': echo '<span class="status completed">Elvégezte</span>'; break;
                                case 'failed': echo '<span class="status failed">Megbukott</span>'; break;
                                default: echo '<span class="status enrolled">Felvette</span>';
                            }
                            ?>
                        </p>

                        <p><strong>Lezárás dátuma:</strong>
                            <?= $st['current_grade'] !== null ? htmlspecialchars($st['completed_at']) : '<em>-</em>' ?>
                        </p>

                        <p><strong>Jegy:</strong>
                            <?= $st['current_grade'] !== null ? htmlspecialchars($st['current_grade']) : '<em>-</em>' ?>
                        </p>

                        <form method="post" action="../POST/complete_post.php" class="grade-form">
                            <input type="hidden" name="student_id" value="<?= htmlspecialchars($st['student_id']) ?>">
                            <input type="hidden" name="offering_id" value="<?= htmlspecialchars($st['offering_id']) ?>">

                            <label for="grade_<?= htmlspecialchars($st['student_id']) ?>">Új jegy:</label>
                            <select name="grade" id="grade_<?= htmlspecialchars($st['student_id']) ?>" >
                                <option value="" <?= empty($st['current_grade']) ? 'selected' : '' ?>></option>
                                <option value="Elégtelen" <?= ($st['current_grade'] === 'Elégtelen') ? 'selected' : '' ?>>Elégtelen</option>
                                <option value="Elégséges" <?= ($st['current_grade'] === 'Elégséges') ? 'selected' : '' ?>>Elégséges</option>
                                <option value="Közepes" <?= ($st['current_grade'] === 'Közepes') ? 'selected' : '' ?>>Közepes</option>
                                <option value="Jó" <?= ($st['current_grade'] === 'Jó') ? 'selected' : '' ?>>Jó</option>
                                <option value="Kiváló" <?= ($st['current_grade'] === 'Kiváló') ? 'selected' : '' ?>>Kiváló</option>
                            </select>

                            <button type="submit" class="fill-btn">Lezárás</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>


<script src="../Scripts/scripts.js"></script>
</body>
</html>
