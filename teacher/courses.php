<?php
require_once __DIR__ . '/../PHP_Header/t_courses.php';
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
                        <a href="assignment_result.php">Eredmények</a>
                        <a href="student_complete.php">Lezárások</a>
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
                <div class="card calendar">
                    <h3>📅 Naptár</h3>
                    <?php // TODO: naptár  ?>
                    <p>[Naptár ide]</p>
                </div>

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
                                    <?= $icon ?> <?= $text ?> <span >(<?= $date ?>)</span>
                                    <form method="post" action="../POST/noti_mark_read.php">
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
                <h1>Kurzusok(Tanároknak)</h1>
                <?php if (isset($_GET['success']) && $_GET['success'] == 10): ?>
                    <div class="success-message">
                        ✅ Dolgozat beadása sikeres!
                    </div>
                    <hr>
                <?php endif; ?>
                <?php if (isset($_GET['error'])): ?>
                    <div class="error-message">
                        ⚠️ <?= htmlspecialchars($_GET['error']) ?>
                    </div>
                    <hr>
                <?php endif; ?>

                <!-- 🔹 Szűrők -->
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
                                    <summary>
                                        📢 <?= nl2br(htmlspecialchars($latest['message'])) ?>
                                        <br>
                                        <small class="forum-meta">
                                            Közzétette: <?= htmlspecialchars($latest['user_name']) ?> &middot;
                                            <?= date('Y. m. d. H:i', strtotime($latest['created_at'])) ?>
                                        </small>

                                        <!-- SUMMARY-ban is szerkesztés / visszavonás -->
                                        <div class="teacher_action-buttons">
                                            <?php if ($latest['users_eduportal_id'] === $eduportal_id): ?>
                                                <!-- Szerkesztés FORM -->
                                                <form method="POST" action="../POST/forum_post.php"
                                                      class="edit-form hidden"
                                                      onsubmit="return confirm('Biztosan menteni szeretnéd a módosítást?')">
                                                    <textarea name="edited_message" class="auto-resize-textarea"><?= htmlspecialchars($latest['message']) ?></textarea>
                                                    <input type="hidden" name="edit_message_id" value="<?= $latest['id'] ?>">
                                                    <button type="submit" name="submit_edit_message" class="send-btn">💾 Mentés</button>
                                                </form>
                                                <button class="edit-btn" onclick="toggleEditForm(this)">✏️ Szerkesztés</button>
                                                <!-- Visszavonás -->
                                                <form method="POST" action="../POST/forum_post.php"
                                                      onsubmit="return confirm('Biztosan visszavonod a hirdetményt?')">
                                                    <input type="hidden" name="delete_message_id" value="<?= $latest['id'] ?>">
                                                    <input type="hidden" name="noti_type" value="hirdetmeny">
                                                    <button type="submit" name="submit_delete_message" class="delete-btn">🗑️ Visszavonás</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </summary>

                                    <ul class="hirdetmeny-list">
                                        <?php foreach ($hirds as $index => $h): ?>
                                            <?php if ($index === 0) continue;?>
                                            <li class="hirdetmeny-item">
                                                <div class="forum-message">
                                                    📢 <?= nl2br(htmlspecialchars($h['message'])) ?>
                                                </div>
                                                <div class="forum-meta">
                                                    Közzétette: <?= htmlspecialchars($h['user_name']) ?> &middot;
                                                    <?= date('Y. m. d. H:i', strtotime($h['created_at'])) ?>
                                                </div>
                                                <div class="teacher_action-buttons">
                                                    <?php if ($h['users_eduportal_id'] === $eduportal_id): ?>
                                                        <!-- Szerkesztés FORM -->
                                                        <form method="POST" action="../POST/forum_post.php"
                                                              class="edit-form hidden"
                                                              onsubmit="return confirm('Biztosan menteni szeretnéd a módosítást?')">
                                                            <textarea name="edited_message" class="auto-resize-textarea"><?= htmlspecialchars($h['message']) ?></textarea>
                                                            <input type="hidden" name="edit_message_id" value="<?= $h['id'] ?>">
                                                            <button type="submit" name="submit_edit_message" class="send-btn">💾 Mentés</button>
                                                        </form>
                                                        <button class="edit-btn" onclick="toggleEditForm(this)">✏️ Szerkesztés</button>

                                                        <!-- Visszavonás -->
                                                        <form method="POST" action="../POST/forum_post.php"
                                                              onsubmit="return confirm('Biztosan visszavonod a hirdetményt?')">
                                                            <input type="hidden" name="delete_message_id" value="<?= $h['id'] ?>">
                                                            <input type="hidden" name="noti_type" value="hirdetmeny">
                                                            <button type="submit" name="submit_delete_message" class="delete-btn">🗑️ Visszavonás</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php else: ?>
                                <p>Nincsenek hirdetmények.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Új hirdetmény hozzáadása -->
                        <div class="forum-reply">
                            <form method="POST" action="../POST/forum_post.php">
                                <textarea name="new_message" placeholder="Új hirdetmény írása..." class="auto-resize-textarea" required></textarea>
                                <input type="hidden" name="course_offering_id" value="<?= $row['offering_id'] ?>">
                                <input type="hidden" name="noti_type" value="hirdetmeny">
                                <input type="hidden" name="semester" value="<?= htmlspecialchars($row['semester_label']) ?>">
                                <button type="submit" name="submit_new_message" class="send-btn">📢 Hirdetmény közzététele</button>
                            </form>
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
                                        <!-- SUMMARY-ban is szerkesztés és törlés -->
                                        <div class="teacher_action-buttons">
                                            <?php if ($latest['users_eduportal_id'] === $eduportal_id): ?>
                                                <!-- Szerkesztés form -->
                                                <form method="POST" action="../POST/forum_post.php"
                                                      class="edit-form hidden"
                                                      onsubmit="return confirm('Biztosan menteni szeretnéd a módosítást?')">
                                                    <textarea name="edited_message" class="auto-resize-textarea"><?= htmlspecialchars($latest['message']) ?></textarea>
                                                    <input type="hidden" name="edit_message_id" value="<?= $latest['id'] ?>">
                                                    <button type="submit" name="submit_edit_message" class="send-btn">💾 Mentés</button>
                                                </form>
                                                <button class="edit-btn" onclick="toggleEditForm(this)">✏️ Szerkesztés</button>
                                            <?php endif; ?>
                                            <!-- Csak tanár törölhet -->
                                            <?php if ($_SESSION['role'] === 'tanar'): ?>
                                                <form method="POST" action="../POST/forum_post.php"
                                                      onsubmit="return confirm('Biztosan törlöd ezt a hozzászólást?')">
                                                    <input type="hidden" name="delete_message_id" value="<?= $latest['id'] ?>">
                                                    <input type="hidden" name="noti_type" value="forum">
                                                    <button type="submit" name="submit_delete_message" class="delete-btn">🗑️ Törlés</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </summary>

                                    <ul class="forum-list">
                                        <?php foreach ($forums as $index => $f): ?>
                                            <?php if ($index === 0) continue; // 🔥 Ne jelenjen meg újra a legfrissebb ?>
                                            <li class="forum-item">
                                                <div class="forum-message">
                                                    💬 <?= nl2br(htmlspecialchars($f['message'])) ?>
                                                </div>
                                                <div class="forum-meta">
                                                    Írta: <?= htmlspecialchars($f['user_name']) ?> &middot;
                                                    <?= date('Y. m. d. H:i', strtotime($f['updated_at'])) ?>
                                                </div>
                                                <div class="teacher_action-buttons">
                                                    <?php if ($f['users_eduportal_id'] === $eduportal_id): ?>

                                                        <!-- Szerkesztés -->
                                                        <form method="POST" action="../POST/forum_post.php"
                                                              class="edit-form hidden"
                                                              onsubmit="return confirm('Biztosan menteni szeretnéd a módosítást?')">
                                                            <textarea name="edited_message" class="auto-resize-textarea"><?= htmlspecialchars($f['message']) ?></textarea>
                                                            <input type="hidden" name="edit_message_id" value="<?= $f['id'] ?>">
                                                            <button type="submit" name="submit_edit_message" class="send-btn">💾 Mentés</button>
                                                        </form>
                                                        <button class="edit-btn" onclick="toggleEditForm(this)">✏️ Szerkesztés</button>
                                                    <?php endif; ?>
                                                    <!-- Törlés csak tanárnak -->
                                                    <?php if ($_SESSION['role'] === 'tanar'): ?>
                                                        <form method="POST" action="../POST/forum_post.php"
                                                              onsubmit="return confirm('Biztosan törlöd ezt a hozzászólást?')">
                                                            <input type="hidden" name="delete_message_id" value="<?= $f['id'] ?>">
                                                            <input type="hidden" name="noti_type" value="forum">
                                                            <button type="submit" name="submit_delete_message" class="delete-btn">🗑️ Törlés</button>
                                                        </form>
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

                            <!-- Új dolgozat létrehozása -->
                            <div class="new-assignment-btn">
                                <form method="GET" action="assignment.php">
                                    <input type="hidden" name="offering_id" value="<?= $row['offering_id'] ?>">
                                    <button type="submit" class="create-btn">➕ Új dolgozat létrehozása</button>
                                </form>
                            </div>

                            <ul>
                                <?php
                                $assignments = $assignmentsByCourse[$row['course_name']] ?? [];
                                ?>
                                <?php if (!empty($assignments)): ?>
                                    <?php foreach ($assignments as $a): ?>
                                        <?php
                                        $assignment_id = $a['id'];
                                        $max_score = isset($a['max_score']) ? (int)$a['max_score'] : 0;
                                        $description = htmlspecialchars($a['description']);
                                        ?>

                                        <li class="assignment-item">
                                            <details>
                                                <summary class="assignment-summary">
                                                    <div class="assignment-header">
                                                        <div class="assignment-title">
                                                            <strong><?= htmlspecialchars($a['title']) ?></strong>
                                                            <span class="date-range">Határidő: <?= date('Y.m.d', strtotime($a['due_date'])) ?></span>
                                                        </div>
                                                        <div class="assignment-action">
                                                            <form method="GET" action="assignment.php">
                                                                <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">
                                                                <input type="hidden" name="offering_id" value="<?= $row['offering_id'] ?>">
                                                                <button type="submit" class="fill-btn">✏️ Szerkesztés</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </summary>

                                                <div class="assignment-details">
                                                    <p class="assignment-description"><?= nl2br($description) ?></p>
                                                </div>
                                            </details>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li>Nincs még létrehozott dolgozat.</li>
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