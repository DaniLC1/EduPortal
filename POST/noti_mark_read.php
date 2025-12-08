<?php
// Globális session és connection
session_start();
require_once __DIR__ . '/../connection.php';
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
        $notification_id = intval($_POST['notification_id'] ?? null);

        $sql = "UPDATE notification_reads 
                SET read_at = NOW() 
                WHERE users_eduportal_ID = ? 
                AND notification_id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $eduportal_id, $notification_id);

        if (!$stmt->execute()) {
            throw new Exception("Mentési hiba: " . $stmt->error);
        }

        $stmt->close();
        $conn->commit();

        /* ============================================================
        🔹 Visszairányítás szerepkör alapján
        ============================================================ */
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'tanar') {
            header("Location: ../teacher/courses.php");
        } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'hallgato') {
            header("Location: ../student/courses.php");
        } else {
            throw new Exception("Nem lehet visszairányítani.");
        }
        exit;

    /* ============================================================
    🔹 Hibakezelés
    ============================================================ */
    }catch (Exception $e){
        $conn->rollback();

        $error_message = urlencode("Hiba történt: " . $e->getMessage());
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'tanar') {
            header("Location: ../teacher/courses.php?error={$error_message}");
        } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'hallgato') {
            header("Location: ../student/courses.php?error={$error_message}");
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



