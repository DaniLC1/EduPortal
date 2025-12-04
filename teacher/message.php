<?php
require_once __DIR__ . '/../PHP_Header/t_message.php';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduPortál</title>
    <link rel="stylesheet" href="../CSS/site_style.css">
    <link rel="stylesheet" href="../CSS/message.css">
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
<main>
<h1> Üzenetek</h1>
    <!-- ============================================================
         🔹 BAL OLDAL: Felhasználó lista, akinek üzenhetsz
    ============================================================ -->
    <div class="message-container">

        <!-- BAL OSZLOP: címzett kiválasztása -->
        <div class="user-list-container">
            <details class="user-selector">
                <summary>Címzett kiválasztása</summary>
                <input type="text" id="tm_searchInput" placeholder="Keresés név vagy ID alapján...">
                <ul id="userList" >
                    <?php while ($u = $users_result->fetch_assoc()): ?>
                        <li class="tm_user-item"
                            data-name="<?= strtolower($u['name']) ?>"
                            data-eduid="<?= strtolower($u['eduportal_id']) ?>">

                            <a href="message.php?to=<?= htmlspecialchars($u['eduportal_id']) ?>"
                               class="<?= ($selected_user == $u['eduportal_id']) ? 'active' : '' ?>">
                                <?= htmlspecialchars($u['name']) ?>
                                (<?= htmlspecialchars($u['eduportal_id']) ?>)
                            </a>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </details>
        </div>

        <!-- JOBB OSZLOP: beszélgetés -->
        <div class="chat-container">
            <?php if (!$selected_user): ?>
                <p class="no-user-selected">Válassz ki valakit bal oldalt!</p>
            <?php else: ?>
                <h3>Beszélgetés: <?= htmlspecialchars($selected_user) ?></h3>
                <div class="chat-box" id="chat-box">
                    <?php if ($messages->num_rows === 0): ?>
                        <p class="no-messages">Még nincs üzenet köztetek.</p>
                    <?php else: ?>
                        <?php while ($m = $messages->fetch_assoc()): ?>
                            <div class="chat-message
                            <?= ($m['from_eduportal_id'] === $eduportal_id) ? 'me' : 'other' ?>">
                                <div class="chat-author">
                                    <?= htmlspecialchars($m['from_name']) ?>:
                                </div>
                                <div class="chat-text">
                                    <?= nl2br(htmlspecialchars($m['message'])) ?>
                                </div>
                                <div class="chat-time">
                                    <?= $m['created_at'] ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
                <!-- ÚJ ÜZENET -->
                <form action="../POST/message_post.php" method="POST" class="chat-form">
                    <input type="hidden" name="to" value="<?= htmlspecialchars($selected_user) ?>">

                    <textarea class="auto-resize-textarea" name="message" required placeholder="Írj üzenetet..."></textarea>

                    <button type="submit">Küldés</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>
<script src="../Scripts/scripts.js"></script>
</body>
</html>
