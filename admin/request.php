<?php
require __DIR__. '/../PHP_Header/a_request.php'
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
                <a href="message.php" >Üzenetek</a>
            </div>
        </div>
    </div>

    <!-- NAVIGÁCIÓ -->
    <nav class="main-nav">
        <a href="register.php"><span class="icon">📘</span> Regisztrálás</a>
        <a href="submitted_request.php"><span class="icon">🧑‍🏫</span> Beadott kérelmek</a>
        <a id="active" href="#"><span class="icon">📄</span> Kérelmek szerkesztése</a>
    </nav>

    <!-- JOBB OLDALI MENÜ -->
    <div class="user-menu">
        <div class="dropdown">
            <button id="dropdownToggleR" class="dropbtn">
                <?= htmlspecialchars($user_name) ?> |
                <?= htmlspecialchars($eduportal_id) ?> |
                <?= htmlspecialchars($user_course) ?>
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
    <h1>📄 Kérelmek kezelése</h1>
    <?php include __DIR__ . '/../feedback.php'; ?>

    <button id="new-request-btn" class="ar_fill-btn">➕ Új kérelem</button>

    <!-- Kereső -->
    <div class="search-form">
        <input type="text" id="ar_searchInput" placeholder="Keresés címre vagy leírásra...">
    </div>

    <div class="requests-container" id="requests-container">
        <?php if (empty($templates)): ?>
            <p>Nincs létrehozott kérelemsablon.</p>
        <?php else: ?>
            <?php foreach ($templates as $t): ?>
                <div class="ar_request-card"
                    data-title="<?= strtolower(htmlspecialchars($t['title'])) ?>"
                    data-description="<?= strtolower(htmlspecialchars($t['description'])) ?>">

                    <div class="card-header">
                        <h2><?= htmlspecialchars($t['title']) ?></h2>
                        <div class="card-actions">
                            <button class="ar_edit-btn">✏️ Szerkesztés</button>
                            <form method="POST" action="../POST/request_post.php" class="inline-form">
                                <input type="hidden" name="admin_request">
                                <input type="hidden" name="template_id" value="<?= $t['id'] ?>">
                                <input type="hidden" name="title" value="<?= $t['title'] ?>">
                                <button type="submit" name="delete" class="ar_delete-btn" >🗑️ Törlés</button>
                            </form>
                        </div>
                    </div>

                    <div class="card-body" style="display:none;">
                        <form method="POST" action="../POST/request_post.php" class="request-edit-form">
                            <input type="hidden" name="admin_request">
                            <input type="hidden" name="template_id" value="<?= $t['id'] ?>">

                            <label>Cím:</label>
                            <input type="text" name="title" value="<?= htmlspecialchars($t['title']) ?>" required>

                            <label>Leírás:</label>
                            <textarea class="auto-resize-textarea" name="description" ><?= htmlspecialchars($t['description']) ?></textarea>

                            <label>Címzett:</label>
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
                                            <label>
                                                <input type="checkbox" name="fields[<?= $f['id'] ?>][is_required]" value="1" <?= $f['is_required']?'checked':'' ?>>
                                                Kötelező
                                            </label>
                                            <button type="button" class="ar_remove-field-btn">❌</button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <button type="button" class="ar_add-field-btn">➕ Mező hozzáadása</button>
                            <div class="form-actions">
                                <button type="submit" class="ar_fill-btn">💾 Mentés</button>
                                <button type="button" class="ar_cancel-btn">🚫 Mégse</button>
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