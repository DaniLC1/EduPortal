<?php
session_start();
require_once __DIR__ . '/../connection.php';

if (!isset($_SESSION['eduportal_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
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

// 🔹 Kitöltött kérelmek lekérdezése
$request_sql = "
    SELECT sr.id AS request_id,
           sr.template_id,
           rt.title,
           rt.description,
           rt.to_who,
           u.name AS student_name,
           sr.submitted_at,
           sr.status,
           sr.admin_comment
    FROM student_requests sr
    JOIN request_templates rt ON sr.template_id = rt.id
    JOIN users u ON sr.users_eduportal_ID = u.eduportal_id
    WHERE rt.title LIKE CONCAT('%', ?, '%')
       OR rt.description LIKE CONCAT('%', ?, '%')
    ORDER BY sr.submitted_at DESC
";
$request_stmt = $conn->prepare($request_sql);
$request_stmt->bind_param("ss", $search, $search);
$request_stmt->execute();
$request_result = $request_stmt->get_result();

$requests = [];
$request_ids = [];
while ($row = $request_result->fetch_assoc()) {
    $requests[$row['request_id']] = $row;
    $request_ids[] = $row['request_id'];
}

// 🔹 Mezők lekérdezése minden sablonhoz és kitöltött értékekkel
$fields_sql = "
    SELECT f.id AS field_id,
           f.template_id,
           f.label,
           f.field_type,
           f.is_required,
           v.field_value,
           v.admin_suggestion,
           v.request_id
    FROM request_template_fields f
    LEFT JOIN student_request_field_values v
      ON f.id = v.field_id
";
$fields_result = $conn->query($fields_sql);

$request_fields = [];
while ($row = $fields_result->fetch_assoc()) {
    if ($row['request_id']) {
        // már kitöltött kérelemhez
        $request_fields[$row['request_id']][] = $row;
    } else {
        // még nem kitöltött sablonmezők template_id alapján
        $request_fields['template_'.$row['template_id']][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduPortál - Beadott kérelmek</title>
    <link rel="stylesheet" href="../CSS/site_style.css">
    <link rel="stylesheet" href="../CSS/request.css">
</head>
<body>
<header>
    <div class="menu">
        <div class="dropdown">
            <div id="dropdownMenuL" class="dropdown-menu left" hidden="hidden"></div>
        </div>
    </div>
    <nav class="main-nav">
        <a href="database.php"><span class="icon">📘</span> Adatbázis</a>
        <a id="active" href="#"><span class="icon">🧑‍🏫</span> Beadott kérelmek</a>
        <a href="request.php"><span class="icon">📄</span> Kérelmek szerkesztése</a>
    </nav>
    <div class="user-menu">
        <div class="dropdown">
            <button id="dropdownToggleR" class="dropbtn">
                <?= htmlspecialchars($user_name) ?> | <?= htmlspecialchars($eduportal_id) ?> | <?= htmlspecialchars($user_course) ?>
            </button>
            <div id="dropdownMenuR" class="dropdown-menu right">
                <a href="profile.php">Beállítások</a>
                <a href="../logout.php">Kijelentkezés</a>
            </div>
        </div>
        <div class="theme-switcher">
            <button id="theme-toggle" class="theme-btn">🌙</button>
        </div>
    </div>
</header>

<main class="layout">
    <h1>📄 Beadott kérelmek</h1>
    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
        <div class="success-message">
            ✅ A kérelem sikeresen mentve!
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="error-message">
            ⚠️ <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>

    <!-- Kereső -->
    <form method="get" action="submitted_request.php" class="search-form">
        <input type="text" name="search" placeholder="Keresés címre vagy leírásra..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit">Keresés</button>
    </form>

    <div class="requests-container">
        <?php if (empty($requests)): ?>
            <p>Nincs találat.</p>
        <?php else: ?>
            <?php foreach ($requests as $req): ?>
                <div class="request-card" data-request-id="<?= $req['request_id'] ?>">
                    <h2><?= htmlspecialchars($req['title']) ?> - <?= htmlspecialchars($req['student_name']) ?></h2>
                    <details>
                        <summary>Kattints a részletekért</summary>

                        <!-- Admin komment és státusz -->
                        <form method="post" action="../request_post.php" class="request-edit-form">
                            <input type="hidden" name="source" value="admin_submitted">
                            <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">

                            <!-- Kérelem leírása -->
                            <p><strong>Leírás:</strong> <?= nl2br(htmlspecialchars($req['description'])) ?></p>

                            <hr>

                            <!-- Kitöltött mezők -->
                            <?php if (!empty($request_fields[$req['request_id']])): ?>
                                <?php foreach ($request_fields[$req['request_id']] as $field): ?>
                                    <div class="field-block">
                                        <p><strong><?= htmlspecialchars($field['label']) ?><?= $field['is_required'] ? ' *' : '' ?></strong></p>

                                        <!-- Tanuló/Tanár válasza (readonly) -->
                                        <div class="field-value">
                                            <?= nl2br(htmlspecialchars($field['field_value'] ?? '')) ?>
                                        </div>

                                        <br>

                                        <!-- Admin javaslat kitölthető mező -->
                                        <label>
                                            Észrevétel/javaslat:<input type="text" name="admin_suggestion[<?= $field['field_id'] ?>]" value="<?= htmlspecialchars($field['admin_suggestion'] ?? '') ?>">
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Nincsenek kitöltött mezők ehhez a kérelemhez.</p>
                            <?php endif; ?>

                            <hr>


                            <label>
                                Állapot:<br>
                                <select name="status">
                                    <option value="beküldve" <?= $req['status'] === 'beküldve' ? 'selected' : '' ?>>Beküldve</option>
                                    <option value="elfogadva" <?= $req['status'] === 'elfogadva' ? 'selected' : '' ?>>Elfogadva</option>
                                    <option value="elutasitva" <?= $req['status'] === 'elutasítva' ? 'selected' : '' ?>>Elutasítva</option>
                                </select>
                            </label><br><br>

                            <label>
                                Megjegyzés:<textarea name="admin_comment" rows="1"><?= htmlspecialchars($req['admin_comment'] ?? '') ?></textarea>
                            </label><br><br>

                            <div class="form-actions">
                                <button type="submit" class="fill-btn">Mentés</button>
                            </div>
                        </form>

                    </details>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<script src="../Scripts/scripts.js"></script>
</body>
</html>