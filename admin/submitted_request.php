<?php
require __DIR__. '/../PHP_Header/a_submitted_request.php'
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
    <div class="search-form">
        <input type="text" id="asr_searchInput" placeholder="Keresés címre vagy leírásra...">
    </div>

    <div class="requests-container">
        <?php if (empty($requests)): ?>
            <p>Nincs találat.</p>
        <?php else: ?>
            <?php foreach ($requests as $req): ?>
                <div class="asr_request-card"
                     data-title="<?= strtolower(htmlspecialchars($req['title'])) ?>"
                     data-name="<?= strtolower(htmlspecialchars($req['student_name'])) ?>"
                     data-description="<?= strtolower(htmlspecialchars($req['description'])) ?>">

                    <h2><?= htmlspecialchars($req['title']) ?> - <?= htmlspecialchars($req['student_name']) ?></h2>
                    <details>
                        <summary>Kattints a részletekért</summary>

                        <!-- Admin komment és státusz -->
                        <form method="post" action="../POST/request_post.php" class="request-edit-form">
                            <input type="hidden" name="admin_submitted">
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