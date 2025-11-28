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
        $name = trim($_POST['name'] ?? '');
        $birth_date = $_POST['birth_date'] ?? null;
        $mothers_name = trim($_POST['mothers_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $address = trim($_POST['address'] ?? '');

        $sql = "
        UPDATE users 
        SET name=?, birth_date=?, mothers_name=?, email=?, phone=?, postal_code=?, city=?, address=?
        WHERE eduportal_id=?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssss", $name, $birth_date, $mothers_name, $email, $phone, $postal_code, $city, $address, $eduportal_id);

        if (!$stmt->execute()) {
            throw new Exception("Mentési hiba: " . $stmt->error);
        }

        $stmt->close();
        $conn->commit();

        /* ============================================================
        🔹 Visszairányítás szerepkör alapján
        ============================================================ */
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'tanar') {
            header("Location: ../teacher/profile.php?success=1");
        } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'hallgato') {
            header("Location: ../student/profile.php?success=1");
        } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            header("Location: ../admin/profile.php?success=1");
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