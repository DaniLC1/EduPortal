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

// Felhasználó adatainak lekérdezése (név és szak)
$user_sql = "
SELECT name 
FROM users
WHERE eduportal_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("s", $eduportal_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 1) {
    $user = $user_result->fetch_assoc();
    $user_name = $user['name'];
    $user_course = "Tanár";
} else {
    $user_name = "Ismeretlen";
    $user_course = "N/A";
}

// === 1️⃣ TANÁR KURZUSAI (offerings) ===
$offerings_sql = "
SELECT 
    co.id AS offering_id,
    co.kurzus_kod,
    c.name AS course_name,
    s.label AS semester_label
FROM teacher_courses tc
JOIN course_offerings co ON tc.kurzus_kod = co.kurzus_kod
JOIN courses c ON c.kurzus_kod = co.kurzus_kod
JOIN semesters s ON s.id = co.semester_id
WHERE tc.teacher_id = ?
ORDER BY s.label DESC, c.name ASC
";
$offerings_stmt = $conn->prepare($offerings_sql);
$offerings_stmt->bind_param("s", $eduportal_id);
$offerings_stmt->execute();
$offerings_result = $offerings_stmt->get_result();

$offerings = [];
while ($row = $offerings_result->fetch_assoc()) {
    $offerings[] = $row;
}
$offerings_stmt->close();

$no_offerings = empty($offerings);

// === 2️⃣ DIÁKOK (enrollments) ===
$enrollments_sql = "
SELECT 
    e.offering_id,
    e.users_eduportal_id AS student_id,
    u.name AS student_name,
    c.kurzus_kod AS subject_code,
    c.name AS subject_name,
    s.label AS semester_label,
    e.status AS enrollment_status
FROM enrollments e
JOIN users u ON u.eduportal_id = e.users_eduportal_id
JOIN course_offerings co ON co.id = e.offering_id
JOIN courses c ON c.kurzus_kod = co.kurzus_kod
JOIN semesters s ON s.id = co.semester_id
WHERE co.kurzus_kod IN (
    SELECT kurzus_kod FROM teacher_courses WHERE teacher_id = ?
)
ORDER BY s.label DESC, u.name ASC, c.name ASC
";

$enrollments_stmt = $conn->prepare($enrollments_sql);
$enrollments_stmt->bind_param("s", $eduportal_id);
$enrollments_stmt->execute();
$enrollments_result = $enrollments_stmt->get_result();

$enrollments = [];
while ($r = $enrollments_result->fetch_assoc()) {
    $enrollments[] = $r;
}
$enrollments_stmt->close();

// === 3️⃣ DOLGOZATOK (assignments) ===
$assignments_sql = "
SELECT 
    a.id AS assignment_id,
    a.offering_id,
    a.title,
    a.max_attempts,
    a.due_date
FROM assignments a
JOIN course_offerings co ON co.id = a.offering_id
JOIN teacher_courses tc ON tc.kurzus_kod = co.kurzus_kod
WHERE tc.teacher_id = ?
ORDER BY a.offering_id, a.due_date ASC
";
$assignments_stmt = $conn->prepare($assignments_sql);
$assignments_stmt->bind_param("s", $eduportal_id);
$assignments_stmt->execute();
$assignments_result = $assignments_stmt->get_result();

$assignments = [];
while ($a = $assignments_result->fetch_assoc()) {
    $assignments[$a['offering_id']][] = $a;
}
$assignments_stmt->close();

// === 4️⃣ BENYÚJTÁSOK (submissions) ===
$subs_sql = "
SELECT 
    s.id AS submission_id,
    s.assignment_id,
    s.users_eduportal_id AS student_id,
    s.submitted_at,
    s.score
FROM assignment_submissions s
JOIN assignments a ON a.id = s.assignment_id
JOIN course_offerings co ON co.id = a.offering_id
JOIN teacher_courses tc ON tc.kurzus_kod = co.kurzus_kod
WHERE tc.teacher_id = ?
ORDER BY s.assignment_id, s.submitted_at ASC
";
$subs_stmt = $conn->prepare($subs_sql);
$subs_stmt->bind_param("s", $eduportal_id);
$subs_stmt->execute();
$subs_result = $subs_stmt->get_result();

$submissions = [];
while ($s = $subs_result->fetch_assoc()) {
    $submissions[$s['assignment_id']][] = $s;
}
$subs_stmt->close();

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
                <a href="#" id="active">Eredmények</a>
                <a href="student_complete.php">Lezárások</a>
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
    <h1>📚 Diákok és dolgozataik</h1>
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

    <?php if ($no_offerings): ?>
        <p>Nincs hozzád rendelt kurzus.</p>
    <?php elseif (empty($enrollments)): ?>
        <p>Jelenleg nincsenek beiratkozott hallgatók.</p>
    <?php else: ?>
        <section id="courseList" class="course-grid">
            <?php foreach ($enrollments as $en): ?>
                <div class="course-card"
                     data-semester="<?= htmlspecialchars($en['semester_label']) ?>"
                     data-offering="<?= htmlspecialchars($en['offering_id']) ?>"
                     data-name="<?= strtolower(htmlspecialchars($en['student_name'])) ?>">

                    <div class="card-header">
                        <h3><?= htmlspecialchars($en['student_name']) ?></h3>
                        <span class="code"><?= htmlspecialchars($en['subject_code']) ?></span>
                    </div>

                    <div class="card-body">
                        <p><strong>Tárgy:</strong> <?= htmlspecialchars($en['subject_name']) ?></p>
                        <p><strong>Félév:</strong> <?= htmlspecialchars($en['semester_label']) ?></p>
                        <details>
                            <summary class="assignment-summary">📝 Dolgozatok</summary>
                            <div class="assignment-details">
                                <?php
                                $student_assignments = $assignments[$en['offering_id']] ?? [];
                                if (empty($student_assignments)): ?>
                                    <p>Nincsenek dolgozatok ehhez a kurzushoz.</p>
                                <?php else: ?>
                                    <ul>
                                        <?php foreach ($student_assignments as $a): ?>
                                            <?php
                                            $subs_for_student = array_filter($submissions[$a['assignment_id']] ?? [], function($s) use ($en) {
                                                return $s['student_id'] === $en['student_id'];
                                            });
                                            ?>
                                            <li class="assignment-item">
                                                <details>
                                                    <summary>
                                                        <div class="assignment-header">
                                                            <div class="assignment-title">
                                                                <strong><?= htmlspecialchars($a['title']) ?></strong><br>
                                                                <span class="date-range">
                                                                    Határidő: <?= date('Y.m.d', strtotime($a['due_date'])) ?>
                                                                </span>
                                                            </div>
                                                            <div class="assignment-stats">
                                                                Próbálkozások: <?= count($subs_for_student) ?> / <?= htmlspecialchars($a['max_attempts']) ?>
                                                            </div>
                                                        </div>
                                                    </summary>

                                                    <div class="assignment-details">
                                                        <?php if (!empty($subs_for_student)): ?>
                                                            <ul class="attempt-list">
                                                                <?php foreach ($subs_for_student as $i => $s): ?>
                                                                    <li>
                                                                        <?= $i + 1 ?>. próbálkozás —
                                                                        <?= date('Y.m.d H:i', strtotime($s['submitted_at'])) ?> |
                                                                        Eredmény: <?= $s['score'] ?? '-' ?> pont
                                                                        <form method="get" action="view_submission.php" style="display:inline;">
                                                                            <input type="hidden" name="submission_id" value="<?= (int)$s['submission_id'] ?>">
                                                                            <button type="submit" class="fill-btn">Megnyitás</button>
                                                                        </form>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php else: ?>
                                                            <p>Még nincs beadás.</p>
                                                        <?php endif; ?>
                                                    </div>
                                                </details>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </details>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>

<script src="../Scripts/scripts.js"></script>
</body>
</html>
