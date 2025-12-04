<?php
// Globális session és connection
require_once __DIR__ . '/../connection.php';
session_start();
global $conn;

/* ============================================================
   🔹 Jogosultság ellenőrzés
============================================================ */
if (!isset($_SESSION['eduportal_id'])) {
    header("Location: ../index.php?error=Nincs jogosultságod az oldal megtekintéséhez.");
    exit;
}

$eduportal_id = $_SESSION['eduportal_id'];

/* ============================================================
   🔹 Csak POST esetén fusson
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        $conn->begin_transaction();

        /* ============================================================
        🔹 Adatok inicializálása
        ============================================================ */
        $to_id     = trim($_POST['to'] ?? '');
        $message   = trim($_POST['message'] ?? '');

        if ($to_id === '' || $message === '') {
            throw new Exception("Hiányzó adatok.");
        }

        /* ============================================================
           🔹 Tiltás: saját magadnak nem küldhetsz üzenetet
        ============================================================ */
        if ($to_id === $eduportal_id) {
            throw new Exception("Saját magadnak nem küldhetsz üzenetet.");
        }

        /* ============================================================
          🔹 Ellenőrzés: létezik-e a címzett
       ============================================================ */
        $chk_sql = "
        SELECT 
            eduportal_id 
        FROM users 
        WHERE eduportal_id = ?";
        $chk_stmt = $conn->prepare($chk_sql);
        $chk_stmt->bind_param("s", $to_id);
        $chk_stmt->execute();
        $chk_res = $chk_stmt->get_result();

        if ($chk_res->num_rows === 0) {
            throw new Exception("A címzett nem létezik.");
        }

        /* ============================================================
          🔹 Üzenet beszúrása
       ============================================================ */
        $insert_messages_sql = "
        INSERT INTO messages (from_eduportal_id, to_eduportal_id, message)
        VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_messages_sql);
        $stmt->bind_param("sss", $eduportal_id, $to_id, $message);

        if (!$stmt->execute()) {
            throw new Exception("Üzenet küldési hiba.");
        }

        $stmt->close();
        $conn->commit();

        /* ============================================================
        🔹 Visszairányítás szerepkör alapján
        ============================================================ */
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'hallgato') {
            header("Location: ../student/message.php?to={$to_id}&success=1");
        } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'tanar') {
            header("Location: ../teacher/message.php?to={$to_id}&success=1");
        } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            header("Location: ../admin/message.php?to={$to_id}&success=1");
        } else {
            throw new Exception("Nem lehet visszairányítani.");
        }
        exit;

    /* ============================================================
    🔹 Hibakezelés
    ============================================================ */
    } catch (Exception $e) {
        $conn->rollback();

        $error_message = urlencode("Hiba történt: " . $e->getMessage());
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'tanar') {
            header("Location: ../teacher/profile.php?error={$error_message}");
        } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'hallgato') {
            header("Location: ../student/profile.php?error={$error_message}");
        } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            header("Location: ../admin/profile.php?error={$error_message}");
        } else {
            header("Location: ../index.php?error={$error_message}");
        }
        exit;
    }
}else {
    /* ============================================================
       🔹 Fallback (ha nem POST) -> kell ha valaki az direktbe akarja megnyitni a _post.php fájlt
    ============================================================ */
    header("Location: ../index.php?error=Fallback");
    exit;
}