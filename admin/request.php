<?php
session_start();
require_once __DIR__ . '/../connection.php';

if (!isset($_SESSION['eduportal_id'])) {
    header("Location: index.php");
    exit;
}

// 🧑‍🏫 Csak adminok léphetnek be
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php?error=unauthorized");
    exit;
}

$eduportal_id = $_SESSION['eduportal_id'];
global $conn;

// 🔹 Admin adatainak lekérése
$user_sql = "
SELECT 
    name 
FROM users 
WHERE eduportal_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("s", $eduportal_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

$user_name = $user['name'] ?? 'Ismeretlen';
$user_course = "Admin";

/// Keresés lekérdezés
$search = $_GET['search'] ?? '';

// --- Kérelemsablonok lekérése ---
$request_sql = "
    SELECT id, title, description, to_who
    FROM request_templates
    ORDER BY id DESC
";
$request_result = $conn->query($request_sql);

$templates = [];
while ($row = $request_result->fetch_assoc()) {
    $templates[] = $row;
}

// --- Mezők lekérése ---
$fields_sql = "SELECT * FROM request_template_fields ORDER BY template_id, id";
$fields_result = $conn->query($fields_sql);
$template_fields = [];
while ($f = $fields_result->fetch_assoc()) {
    $template_fields[$f['template_id']][] = $f;
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
            <div id="dropdownMenuL" class="dropdown-menu left" hidden="hidden">
            </div>
        </div>
    </div>

    <!-- NAVIGÁCIÓ -->
    <nav class="main-nav">
        <a href="database.php" ><span class="icon">📘</span> Adatbázis</a>
        <a href="submitted_request.php" ><span class="icon">🧑‍🏫</span> Beadott kérelmek</a>
        <a id="active" href="#"><span class="icon">📄</span> Kérelmek szerkesztése</a>
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
    <h1>📄 Kérelmek kezelése</h1>

    <!-- Új kérelem létrehozása -->
    <button id="new-request-btn" class="fill-btn">➕ Új kérelem</button>

    <div class="requests-container" id="requests-container">
        <?php if (empty($templates)): ?>
            <p>Nincs létrehozott kérelemsablon.</p>
        <?php else: ?>
            <?php foreach ($templates as $t): ?>
                <div class="request-card" data-template-id="<?= $t['id'] ?>">
                    <div class="card-header">
                        <h2><?= htmlspecialchars($t['title']) ?></h2>
                        <div class="card-actions">
                            <button class="edit-btn">✏️ Szerkesztés</button>
                            <button class="delete-btn">🗑️ Törlés</button>
                        </div>
                    </div>

                    <div class="card-body" style="display:none;">
                        <form class="request-edit-form">
                            <label>Cím:</label>
                            <input type="text" name="title" value="<?= htmlspecialchars($t['title']) ?>" required>

                            <label>Leírás:</label>
                            <textarea name="description" rows="2"><?= htmlspecialchars($t['description']) ?></textarea>

                            <label>Címzett (to_who):</label>
                            <select name="to_who" required>
                                <option value="hallgato" <?= $t['to_who'] === 'hallgato' ? 'selected' : '' ?>>Hallgató</option>
                                <option value="tanar" <?= $t['to_who'] === 'tanar' ? 'selected' : '' ?>>Tanár</option>
                            </select>

                            <hr>

                            <h3>Mezők</h3>
                            <div class="fields-container">
                                <?php if (!empty($template_fields[$t['id']])): ?>
                                    <?php foreach ($template_fields[$t['id']] as $f): ?>
                                        <div class="field-card">
                                            <input type="text" name="fields[<?= $f['id'] ?>][label]" value="<?= htmlspecialchars($f['label']) ?>" placeholder="Címke">
                                            <select name="fields[<?= $f['id'] ?>][field_type]">
                                                <option value="text" <?= $f['field_type']=='text'?'selected':'' ?>>Szöveg</option>
                                                <option value="number" <?= $f['field_type']=='number'?'selected':'' ?>>Szám</option>
                                                <option value="date" <?= $f['field_type']=='date'?'selected':'' ?>>Dátum</option>
                                                <option value="textarea" <?= $f['field_type']=='textarea'?'selected':'' ?>>Többsoros</option>
                                            </select>
                                            <label><input type="checkbox" name="fields[<?= $f['id'] ?>][is_required]" value="1" <?= $f['is_required']?'checked':'' ?>> Kötelező</label>
                                            <button type="button" class="remove-field">❌</button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="no-fields">Nincsenek mezők.</p>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="add-field-btn">➕ Mező hozzáadása</button>

                            <div class="form-actions">
                                <button type="submit" class="fill-btn">💾 Mentés</button>
                                <button type="button" class="cancel-btn">🚫 Mégse</button>
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