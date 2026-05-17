<?php
require_once __DIR__ . '/../PHP_Header/s_courses.php';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduPortál</title>
    <link rel="stylesheet" href="../CSS/site_style.css">
    <link rel="stylesheet" href="../CSS/courses.css">
</head>
    <body>
        <header>
            <!-- BAL MENÜ -->
            <div class="menu">
                <div class="dropdown">
                    <button id="dropdownToggleL" class="dropbtn">☰ Menü </button>
                    <div id="dropdownMenuL" class="dropdown-menu left">
                        <a href="message.php" >Üzenetek</a>
                        <a href="enrolled_courses.php">Felvett kurzusok</a>
                        <a href="studies.php">Tanulmányok</a>
                    </div>
                </div>
            </div>

            <!-- NAVIGÁCIÓ -->
            <nav class="main-nav">
                <a href="course_offering.php"><span class="icon">📘</span> Tárgyfelvétel</a>
                <a href="#" id="active"><span class="icon">🧑‍🏫</span> Kurzusok</a>
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

        <!-- IDE JÖN A FŐ TARTALOM -->
        <main class="layout">
            <!-- BAL OLDALI SÁV -->
            <aside class="sidebar">
                <div class="card notifications">
                    <h3>🔔 Értesítések</h3>
                    <ul>
                        <?php if ($notif_result->num_rows > 0): ?>
                            <?php while ($notif = $notif_result->fetch_assoc()): ?>
                                <?php
                                $icon = '';
                                $text = '';
                                $course = htmlspecialchars($notif['course_name']);
                                $date = date('Y.m.d H:i', strtotime($notif['created_at']));

                                switch ($notif['noti_type']) {
                                    case 'forum':
                                        $icon = '💬';
                                        $text = "Új kurzusfórum hozzászólás: $course";
                                        break;
                                    case 'hirdetmeny':
                                        $icon = '📢';
                                        $text = "Új hirdetmény: $course";
                                        break;
                                    case 'szamonkeres':
                                        $icon = '📝';
                                        $text = "Számonkérés változás: $course";
                                        break;
                                    default:
                                        $icon = '❔';
                                        $text = "Ismeretlen típus: $course";
                                }
                                ?>
                                <li>
                                    <?= $icon ?> <?= $text ?> <span>(<?= $date ?>)</span>
                                    <form method="post" action="../POST/noti_mark_read.php" style="display:inline;">
                                        <input type="hidden" name="notification_id" value="<?= $notif['notification_id'] ?>">
                                        <button type="submit" class="delete-btn" title="Megjelölés olvasottként">❌</button>
                                    </form>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li>Nincs új értesítés.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </aside>

            <!-- FŐ TARTALOM -->
            <section class="main-content">
                <h1>Kurzusok</h1>
                <?php include __DIR__ . '/../feedback.php'; ?>

                <!-- 🔹 Félévválasztó -->
                <form method="get" class="filters">
                    <label for="semester_id">Félév:</label>
                    <select name="semester_id" id="semester_id" onchange="this.form.submit()">
                        <?php foreach ($semesters as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $s['id'] == $selected_semester_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button type="submit">Szűrés</button></noscript>
                </form>
                <hr>
                <?php while ($row = $courses_result->fetch_assoc()): ?>
                    <div class="course-card">
                        <h3><?= htmlspecialchars($row['course_name']) ?></h3>

                        <?php
                        $description = htmlspecialchars($row['description']);
                        $shortDesc = mb_strimwidth($description, 0, 150, '...');
                        ?>
                        <div class="collapsible-container">
                            <p class="short-description"><?= $shortDesc ?></p>
                            <div class="collapsible-content">
                                <p><?= $description ?></p>
                            </div>
                            <?php if (strlen($description) > 150): ?>
                                <button class="toggle-btn"
                                        data-more-text="Bővebben"
                                        data-less-text="Kevesebb"
                                        onclick="toggleDescription(this)">
                                    Bővebben
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Hirdetmények -->
                        <div class="section">
                            <h4>📢 Hirdetmények</h4>
                            <?php
                            $hirds = $hirdsByCourse[$row['course_name']] ?? [];
                            ?>
                            <?php if (!empty($hirds)): ?>
                                <?php $latest = $hirds[0]; ?>
                                <details>
                                    <summary class="forum-message">
                                        📢<?= nl2br(htmlspecialchars($latest['message'])) ?>
                                        <br>
                                        <small class="forum-meta">
                                            Közzétette: <?= htmlspecialchars($latest['user_name']) ?> &middot;
                                            <?= date('Y. m. d. H:i', strtotime($latest['created_at'])) ?>
                                        </small>
                                    </summary>

                                    <ul class="hirdetmeny-list">
                                        <?php foreach ($hirds as $index => $h): ?>
                                            <?php if ($index === 0) {continue;} ?>
                                            <li class="hirdetmeny-item">
                                                <div class="forum-message">
                                                    📢 <?= nl2br(htmlspecialchars($h['message'])) ?>
                                                </div>
                                                <div class="forum-meta">
                                                    Közzétette: <?= htmlspecialchars($h['user_name']) ?> &middot;
                                                    <?= date('Y. m. d. H:i', strtotime($h['created_at'])) ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            <?php else: ?>
                                <p>Nincsenek hirdetmények.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Fórum -->
                        <div class="section">
                            <h4>💬 Kurzus fórum</h4>
                            <?php
                            $forums = $forumsByCourse[$row['course_name']] ?? [];
                            ?>
                            <?php if (!empty($forums)): ?>
                                <?php $latest = $forums[0]; ?>
                                <details>
                                    <summary class="forum-message">
                                        💬 <?= nl2br(htmlspecialchars($latest['message'])) ?>
                                        <br>
                                        <small class="forum-meta">
                                            Írta: <?= htmlspecialchars($latest['user_name']) ?> &middot;
                                            <?= date('Y. m. d. H:i', strtotime($latest['updated_at'])) ?>
                                        </small>

                                        <div class="student_action-buttons">
                                            <?php if ($latest['users_eduportal_id'] === $eduportal_id): ?>
                                                <!-- Szerkesztés form -->
                                                <form method="POST" action="../POST/forum_post.php" class="edit-form hidden"
                                                      onsubmit="return confirm('Biztosan menteni szeretnéd a módosítást?')">
                                                    <textarea name="edited_message" class="auto-resize-textarea"><?= htmlspecialchars($latest['message']) ?></textarea>
                                                    <input type="hidden" name="edit_message_id" value="<?= $latest['id'] ?>">
                                                    <button type="submit" name="submit_edit_message" class="send-btn">💾 Mentés</button>
                                                </form>
                                                <button class="edit-btn" onclick="toggleEditForm(this)">✏️ Szerkesztés</button>
                                            <?php endif; ?>
                                        </div>
                                    </summary>

                                    <ul class="forum-list">
                                        <?php foreach ($forums as $index => $f): ?>
                                            <?php if ($index === 0) {continue;} ?>
                                            <li class="forum-item">
                                                <div class="forum-message">
                                                    💬 <?= nl2br(htmlspecialchars($f['message'])) ?>
                                                </div>
                                                <div class="forum-meta">
                                                    Írta: <?= htmlspecialchars($f['user_name']) ?> &middot;
                                                    <?= date('Y. m. d. H:i', strtotime($f['updated_at'])) ?>
                                                </div>
                                                <div class="student_action-buttons">
                                                    <?php if ($f['users_eduportal_id'] === $eduportal_id): ?>
                                                        <!-- Szerkesztés form -->
                                                        <form method="POST" action="../POST/forum_post.php"
                                                              class="edit-form hidden"
                                                              onsubmit="return confirm('Biztosan menteni szeretnéd a módosítást?')">
                                                            <textarea name="edited_message" class="auto-resize-textarea"><?= htmlspecialchars($f['message']) ?></textarea>
                                                            <input type="hidden" name="edit_message_id" value="<?= $f['id'] ?>">
                                                            <button type="submit" name="submit_edit_message" class="send-btn">💾 Mentés</button>
                                                        </form>
                                                        <button class="edit-btn" onclick="toggleEditForm(this)">✏️ Szerkesztés</button>
                                                    <?php endif; ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            <?php else: ?>
                                <p>Nincs még fórumhozzászólás.</p>
                            <?php endif; ?>

                            <!-- Új hozzászólás -->
                            <div class="forum-reply">
                                <form method="POST" action="../POST/forum_post.php">
                                    <textarea name="new_message" placeholder="Írd be az üzeneted..." class="auto-resize-textarea" required></textarea>
                                    <input type="hidden" name="course_offering_id" value="<?= $row['offering_id'] ?>">
                                    <input type="hidden" name="semester" value="<?= htmlspecialchars($row['semester_label']) ?>">
                                    <button type="submit" name="submit_new_message" class="send-btn">💬 Hozzászólás elküldése</button>
                                </form>
                            </div>
                        </div>

                        <!-- Dolgozatok -->
                        <div class="section">
                            <h4>📝 Dolgozatok</h4>
                            <ul>
                                <?php
                                $assignments = $assignmentsByCourse[$row['course_name']] ?? [];
                                ?>
                                <?php if (!empty($assignments)): ?>
                                    <?php foreach ($assignments as $a): ?>
                                        <?php
                                        $assignment_id = $a['id'];
                                        $student_attempts = $submissions_by_assignment[$assignment_id] ?? [];
                                        $attempt_count = count($student_attempts);
                                        $max_attempts = $student_attempts[0]['max_attempts'] ?? $a['max_attempts'] ?? '∞';
                                        $best_score = 0;
                                        foreach ($student_attempts as $sub) {
                                            if ($sub['score'] !== null && $sub['score'] > $best_score) {
                                                $best_score = $sub['score'];
                                            }
                                        }
                                        $max_score = isset($a['max_score']) ? (int)$a['max_score'] : 0;
                                        $description = htmlspecialchars($a['description']);
                                        ?>
                                        <?php
                                        $today = date('Y-m-d H:i');
                                        $due_date = date('Y-m-d H:i', strtotime($a['due_date']));
                                        $can_attempt = ($attempt_count < $max_attempts || $max_attempts === '∞') && ($today <= $due_date);
                                        ?>
                                        <li class="assignment-item">
                                            <details>
                                                <summary class="assignment-summary">
                                                    <div class="assignment-header">
                                                        <div class="assignment-title">
                                                            <strong><?= htmlspecialchars($a['title']) ?></strong><br>
                                                            <span class="date-range">Indítható: <?= date('Y.m.d H:i', strtotime('-3 days', strtotime($a['due_date']))) ?> – <?= date('Y.m.d H:i', strtotime($a['due_date'])) ?></span>
                                                        </div>
                                                        <div class="assignment-stats">
                                                            Próbálkozás: <?= $attempt_count ?> / <?= $max_attempts ?> |
                                                            Eredmény: <?= $best_score ?> / <?= $max_score ?> pont
                                                        </div>
                                                        <div class="assignment-action">
                                                            <?php if ($can_attempt): ?>
                                                                <form method="GET" action="assignment.php">
                                                                    <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">
                                                                    <button type="submit" class="fill-btn">✍️ Kitöltés</button>
                                                                </form>
                                                            <?php else: ?>
                                                                <?php if ($today > $due_date): ?>
                                                                    <em>Határidő lejárt</em>
                                                                <?php else: ?>
                                                                    <em>Maximális próbálkozások száma elérve</em>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </summary>

                                                <div class="assignment-details">
                                                    <p class="assignment-description"><?= nl2br($description) ?></p>
                                                    <?php if (!empty($student_attempts)): ?>
                                                        <ul class="attempt-list">
                                                            <?php foreach ($student_attempts as $index => $sub): ?>
                                                                <li>
                                                                    <?= $index + 1 ?>. próbálkozás – <?= date('Y.m.d H:i', strtotime($sub['submitted_at'])) ?>:
                                                                    <?= $sub['score'] ?? 0 ?> / <?= $max_score ?> pont
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        <p>Még nincs próbálkozás.</p>
                                                    <?php endif; ?>
                                                </div>
                                            </details>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li>Nincs beadandó dolgozat.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                <?php endwhile; ?>
            </section>
        </main>

        <script src="../Scripts/scripts.js"></script>
    </body>
</html>