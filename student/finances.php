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

// Szemeszter lista
$semesters_sql = "SELECT * FROM semesters ORDER BY start_date DESC";
$semesters_result = $conn->query($semesters_sql);
$semesters = $semesters_result->fetch_all(MYSQLI_ASSOC);

// Szűrés GET paraméterekkel
$filter_semester = $_GET['semester_id'] ?? '';
$filter_status = $_GET['status'] ?? ''; // 'paid' | 'unpaid' | ''

// Lekérdezés önköltségeseknek
$check_financing = "SELECT financing_type FROM users WHERE eduportal_id = ?";
$stmt = $conn->prepare($check_financing);
$stmt->bind_param("s", $eduportal_id);
$stmt->execute();
$type_result = $stmt->get_result()->fetch_assoc();

if ($type_result['financing_type'] === 'önköltséges') {
    $sql = "
        SELECT 
            sf.id,
            sf.semester_id,
            s.label AS semester_label,
            sf.amount_due,
            sf.due_date,
            IFNULL(SUM(pi.amount_paid), 0) AS total_paid
        FROM student_financing sf
        JOIN semesters s ON sf.semester_id = s.id
        LEFT JOIN payment_installments pi ON pi.financing_id = sf.id
        WHERE sf.users_eduportal_ID = ?
        " . ($filter_semester ? " AND sf.semester_id = ?" : "") . "
        GROUP BY sf.id
        HAVING " . ($filter_status === 'paid' ? "total_paid >= amount_due" : ($filter_status === 'unpaid' ? "total_paid < amount_due" : "1=1")) . "
        ORDER BY sf.due_date ASC";

    $stmt = $conn->prepare($sql);
    if ($filter_semester) {
        $stmt->bind_param("si", $eduportal_id, $filter_semester);
    } else {
        $stmt->bind_param("s", $eduportal_id);
    }
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $results = [];
}

$payment_details = [];

if (!empty($results)) {
    $ids = implode(',', array_column($results, 'id'));
    $installments_sql = "
        SELECT financing_id, paid_at, amount_paid
        FROM payment_installments
        WHERE financing_id IN ($ids)
        ORDER BY paid_at ASC
    ";
    $installments_result = $conn->query($installments_sql);

    while ($row = $installments_result->fetch_assoc()) {
        $payment_details[$row['financing_id']][] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="hu">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>EduPortál</title>
        <link rel="stylesheet" href="../CSS/site_style.css">
        <link rel="stylesheet" href="../CSS/finances.css">
    </head>
    <body>
        <header>
            <!-- BAL MENÜ -->
            <div class="menu">
                <div class="dropdown">
                    <button id="dropdownToggleL" class="dropbtn">☰ Menü </button>
                    <div id="dropdownMenuL" class="dropdown-menu left">
                        <a id="active" href="#">Pénzügyek</a>
                        <a href="enrolled_courses.php">Felvett kurzusok</a>
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
            <h1>Pénzügyek</h1>
            <form method="get" class="filters">
                <label for="semester_id">Félév:</label>
                <select name="semester_id" id="semester_id" onchange="this.form.submit()">
                    <option value="">Összes</option>
                    <?php foreach ($semesters as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($filter_semester == $s['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="status">Státusz:</label>
                <select name="status" id="status" onchange="this.form.submit()">
                    <option value="">Összes</option>
                    <option value="paid" <?= $filter_status === 'paid' ? 'selected' : '' ?>>Befizetett</option>
                    <option value="unpaid" <?= $filter_status === 'unpaid' ? 'selected' : '' ?>>Tartozás</option>
                </select>
            </form>
            <?php if (empty($results)): ?>
                <p>Nincs megjelenítendő adat.</p>
            <?php else: ?>

                <div class="finance-cards">
                    <?php foreach ($results as $r):
                        $remaining = $r['amount_due'] - $r['total_paid'];
                        $is_overdue = strtotime($r['due_date']) < time();
                        $status_class = $remaining <= 0 ? 'paid' : ($is_overdue ? 'overdue' : 'partial');
                        ?>
                        <div class="finance-card <?= $status_class ?>">
                            <div class="card-body">
                                <h3><?= htmlspecialchars($r['semester_label']) ?></h3>
                                <div class="card-content">
                                    <p><strong>Teljes összeg:</strong> <?= number_format($r['amount_due'], 0, ',', ' ') ?> Ft</p>
                                    <p><strong>Határidő:</strong> <?= $r['due_date'] ?></p>
                                    <p><strong>Hátralévő:</strong> <?= number_format($remaining, 0, ',', ' ') ?> Ft</p>
                                </div>

                                <?php if (!empty($payment_details[$r['id']])): ?>
                                    <button class="toggle-details" onclick="toggleDetails(<?= $r['id'] ?>)">🧾 Befizetések megtekintése</button>
                                    <div class="payment-details" id="details-<?= $r['id'] ?>" style="display: none; margin-top: 1rem;">
                                        <ul>
                                            <?php foreach ($payment_details[$r['id']] as $payment): ?>
                                                <li><?= $payment['paid_at'] ?> – <?= number_format($payment['amount_paid'], 0, ',', ' ') ?> Ft</li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <?php if ($remaining > 0): ?>
                                    <button onclick="openPaymentModal(<?= $r['id'] ?>, <?= $remaining ?>)">Befizetés</button>
                                <?php else: ?>
                                    <span class="paid-label">✔ Teljesítve</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Felugró ablak -->
            <div id="paymentModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeModal()">×</span>
                    <h2>Befizetés</h2>
                    <form action="../POST/finances_post.php" method="post">
                        <input type="hidden" name="financing_id" id="modalFinancingId">
                        <label for="amount">Összeg (min. 1.000 Ft):</label>
                        <input type="number" name="amount" id="paymentAmount" min="1000" required>
                        <button type="submit">Befizetés véglegesítése</button>
                    </form>
                </div>
            </div>
        </main>
        <script src="../Scripts/scripts.js"></script>
    </body>
</html>


