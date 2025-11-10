<?php
session_start();
require_once __DIR__ . '/../connection.php'; // Adatbáziskapcsolat betöltése

if (!isset($_SESSION['eduportal_id'])) {
    header("Location: index.php"); // vagy login.php
    exit;
}
$eduportal_id = $_SESSION['eduportal_id'];
global $conn; // Globális változó használata

// Felhasználó adatainak lekérdezése (név és szak)
$user_sql = "
SELECT u.name, 
       p.name AS szak_nev 
FROM users u
JOIN programs p ON p.szak_szam = u.course_code 
WHERE eduportal_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("s", $eduportal_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 1) {
    $user = $user_result->fetch_assoc();
    $user_name = $user['name'];
    $user_course = $user['szak_nev'];
} else {
    $user_name = "Ismeretlen";
    $user_course = "N/A";
}

$enrolled_courses_sql = "
SELECT 
    c.kurzus_kod,
    c.name AS subject_name,
    c.credit,
    COALESCE(pc.tipus, 'szv') AS subject_type,
    e.status AS enrollment_status,
    s.label AS semester_label,
    e.grade AS grade
FROM enrollments e
JOIN course_offerings co ON e.offering_id = co.id
JOIN semesters s ON s.id = co.semester_id
JOIN courses c ON co.kurzus_kod = c.kurzus_kod
JOIN users u ON u.eduportal_id = e.users_eduportal_id
LEFT JOIN programs p ON p.szak_szam = u.course_code
LEFT JOIN program_courses pc 
    ON pc.kurzus_kod = c.kurzus_kod AND pc.szak_szam = p.szak_szam
WHERE e.users_eduportal_id = ?
ORDER BY s.label DESC, c.name ASC";

$enrolled_stmt = $conn->prepare($enrolled_courses_sql);
$enrolled_stmt->bind_param("s", $eduportal_id);
$enrolled_stmt->execute();
$enrolled_result = $enrolled_stmt->get_result();
$enrolled_subjects = $enrolled_result->fetch_all(MYSQLI_ASSOC);

$semester_sql = "
SELECT DISTINCT s.label 
FROM enrollments e
JOIN course_offerings co ON co.id = e.offering_id
JOIN semesters s ON s.id = co.semester_id
WHERE e.users_eduportal_id = ?
ORDER BY s.label DESC";

$semester_stmt = $conn->prepare($semester_sql);
$semester_stmt->bind_param("s", $eduportal_id);
$semester_stmt->execute();
$semester_result = $semester_stmt->get_result();

?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                    <a href="finances.php">Pénzügyek</a>
                    <a id="active" href="#">Felvett kurzusok</a>
                    <a href="studies.php" >Tanulmányok</a>
                </div>
            </div>
        </div>

        <!-- NAVIGÁCIÓ -->
        <nav class="main-nav">
            <a href="course_offering.php"><span class="icon">📘</span> Tárgyfelvétel</a>
            <a href="courses.php" ><span class="icon">🧑‍🏫</span> Kurzusok</a>
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
    <main>
        <h1>Felvett kurzusok</h1>
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

            <label for="typeFilter">Típus:</label>
            <select id="typeFilter">
                <option value="all">Összes</option>
                <option value="kotelezo">Kötelező</option>
                <option value="valaszthato">Kötelezően választható</option>
                <option value="szv">Szabadon választható</option>
            </select>

            <input type="text" id="searchInput" placeholder="Keresés tárgy nevére...">
        </section>

        <section id="courseList" class="course-grid">
            <?php foreach ($enrolled_subjects as $subject): ?>
                <div class="course-card"
                     data-semester="<?= htmlspecialchars($subject['semester_label']); ?>"
                     data-type="<?= htmlspecialchars($subject['subject_type']); ?>"
                     data-name="<?= strtolower(htmlspecialchars($subject['subject_name'])); ?>">

                    <div class="card-header">
                        <h3><?= htmlspecialchars($subject['subject_name']); ?></h3>
                        <span class="code"><?= htmlspecialchars($subject['kurzus_kod']); ?></span>
                    </div>

                    <div class="card-body">
                        <p><strong>Kredit:</strong> <?= htmlspecialchars($subject['credit']); ?></p>
                        <p><strong>Típus:</strong>
                            <?php
                            if ($subject['subject_type'] === 'valaszthato') {
                                echo 'Kötelezően választható';
                            } elseif ($subject['subject_type'] === 'szv') {
                                echo 'Szabadon választható';
                            } else {
                                echo 'Kötelező';
                            }
                            ?>
                        </p>
                        <p><strong>Félév:</strong> <?= htmlspecialchars($subject['semester_label']); ?></p>
                        <p class="status <?= $subject['enrollment_status']; ?>">
                            <strong>Státusz:</strong>
                            <?php
                            echo match ($subject['enrollment_status']) {
                                'completed' => '✔️ Teljesítve',
                                'enrolled' => '⏳ Folyamatban',
                                'failed' => '❌ Nem teljesítve',
                                default => 'ℹ️ Ismeretlen',
                            };
                            ?>
                        </p>
                        <p><strong>Eredmény:</strong>
                            <?php
                            if ($subject['enrollment_status'] === 'completed' or $subject['enrollment_status'] === 'failed' ) {
                                echo $subject['grade'];
                            } else {
                                echo '-';
                            }
                            ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
    </main>
    <script src="../Scripts/scripts.js"></script>
</body>
</html>
