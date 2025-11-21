<?php
session_start();
require_once __DIR__ . '/../connection.php'; // Adatbáziskapcsolat betöltése

if (!isset($_SESSION['eduportal_id']) || $_SESSION['role'] !== 'tanar') {
    header("Location: ../index.php");
    exit;
}

$eduportal_id = $_SESSION['eduportal_id'];
global $conn;

// Felhasználó adatainak lekérdezése (név és szak)
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

/// Keresés lekérdezés
$search = $_GET['search'] ?? '';

// Kérelemsablonok lekérdezése (címre vagy leírásra)
$request_sql = "
    SELECT rt.id,
           rt.title,
           rt.description,
           rt.to_who
    FROM request_templates rt
    WHERE ( rt.title LIKE CONCAT('%', ?, '%')
    OR rt.description LIKE CONCAT('%', ?, '%') )
    AND rt.to_who = 'tanar'
    ORDER BY rt.created_at DESC
";
$request_stmt = $conn->prepare($request_sql);
$request_stmt->bind_param("ss", $search, $search);
$request_stmt->execute();
$request_result = $request_stmt->get_result();

$templates = [];
while ($row = $request_result->fetch_assoc()) {
    $templates[] = $row;
}

// Mezők lekérdezése az összes sablonhoz
$fields_sql = "
    SELECT f.id,
           f.template_id,
           f.label,
           f.field_type,
           f.is_required
    FROM request_template_fields f
    ORDER BY f.template_id, f.id
";
$fields_result = $conn->query($fields_sql);

$template_fields = [];
while ($row = $fields_result->fetch_assoc()) {
    $template_fields[$row['template_id']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduPortál</title>
    <link rel="stylesheet" href="../CSS/site_style.css">
    <link rel="stylesheet" href="../CSS/request.css">
</head>
<body>
<header>
    <!-- BAL MENÜ -->
    <div class="menu">
        <div class="dropdown">
            <button id="dropdownToggleL" class="dropbtn">☰ Menü </button>
            <div id="dropdownMenuL" class="dropdown-menu left">
                <a href="assignment_result.php">Eredmények</a>
                <a href="student_complete.php">Lezárások</a>
            </div>
        </div>
    </div>

    <!-- NAVIGÁCIÓ -->
    <nav class="main-nav">
        <a href="course_offering.php"><span class="icon">📘</span> Tárgyfelvétel</a>
        <a href="courses.php"><span class="icon">🧑‍🏫</span> Kurzusok</a>
        <a id="active" href="#"><span class="icon">📄</span> Kérelmek</a>
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

<!-- IDE JÖN A FŐ TARTALOM -->
<main class="layout">
    <h1>📄 Kérelmek</h1>
    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
        <div class="success-message">
            ✅ A kérelem sikeresen beadva!
        </div>
    <?php endif; ?>

    <!-- Kereső -->
    <form method="get" action="request.php" class="search-form">
        <input type="text" name="search" placeholder="Keresés címre vagy leírásra..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit">Keresés</button>
    </form>

    <!-- Kérelmek listája -->
    <div class="requests-container">
        <?php if (empty($templates)): ?>
            <p>Nincs találat a megadott keresésre.</p>
        <?php else: ?>
            <?php foreach ($templates as $template): ?>
                <div class="request-card" data-template-id="<?= $template['id'] ?>">
                    <div class="card-header">
                        <h2><?= htmlspecialchars($template['title']) ?></h2>
                        <div class="card-actions">
                            <button class="toggle-desc-btn">Több</button>
                            <button class="fill-request-btn">Kitöltés</button>
                        </div>
                    </div>
                    <div class="card-description" style="display: none;">
                        <p><?= nl2br(htmlspecialchars($template['description'])) ?></p>

                        <form method="post" action="../POST/request_post.php" class="request-form"">
                            <input type="hidden" name="template_id" value="<?= $template['id'] ?>">

                            <fieldset disabled>
                                <?php if (!empty($template_fields[$template['id']])): ?>
                                    <?php foreach ($template_fields[$template['id']] as $field): ?>
                                        <label>
                                            <?= htmlspecialchars($field['label']) ?><?= $field['is_required'] ? ' *' : '' ?><br>
                                            <?php if ($field['field_type'] === 'textarea'): ?>
                                                <textarea class="auto-resize-textarea" name="field_<?= $field['id'] ?>" rows="3"></textarea>
                                            <?php else: ?>
                                                <input
                                                        type="<?= htmlspecialchars($field['field_type']) ?>"
                                                        name="field_<?= $field['id'] ?>"
                                                >
                                            <?php endif; ?>
                                        </label>
                                        <br><br>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>Nincsenek kitölthető mezők ehhez a kérelemhez.</p>
                                <?php endif; ?>
                            </fieldset>

                            <div class="form-actions" style="display: none;">
                                <button type="button" class="cancel-btn">Mégse</button>
                                <button type="submit" class="submit-btn">Kérelem beküldése</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<script src="../Scripts/scripts.js"></script>
</body>
</html>