<?php
require_once __DIR__ . '/../connection.php';
session_start();
global $conn;

/* ============================================================
   🔹 Jogosultság ellenőrzés
============================================================ */
if (!isset($_SESSION['eduportal_id'])) {
    header("Location: ../index.php?error=unauthorized");
    exit;
}

$eduportal_id = $_SESSION['eduportal_id'];

/* ============================================================
   🔹 Csak POST és profile_save_data gomb menyomása esetén fusson
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_save_data'])) {

    // Ezeket nem frissítjük
    $excluded_fields = ['eduportal_id', 'id', 'password', 'created_at', 'profile_save_data'];

    try {
        $conn->begin_transaction();

        $updates = [];
        $values = [];
        $types = "";

        /* ============================================================
        🔹Dinamikus mezőgyűjtés
        ============================================================ */
        foreach ($_POST as $field => $value) {
            if (in_array($field, $excluded_fields)) continue;

            $updates[] = "`$field` = ?";
            $values[] = trim($value);
            $types .= "s";
        }

        if (!empty($updates)) {
            $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE eduportal_id = ?";
            $values[] = $eduportal_id;
            $types .= "s";

            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Hiba előkészítéskor: " . $conn->error);

            $stmt->bind_param($types, ...$values);

            if (!$stmt->execute()) {
                throw new Exception("Mentési hiba: " . $stmt->error);
            }

            $stmt->close();
        } else {
            throw new Exception("Nincs frissítendő adat.");
        }

        $conn->commit();

        /* ============================================================
        🔹Visszairányítás
        ============================================================ */
        $redirect = $_SERVER['HTTP_REFERER'] ?? "../profile.php";
        $redirect .= (parse_url($redirect, PHP_URL_QUERY) ? '&' : '?') . "success=1";

        header("Location: $redirect");
        exit;

    } catch (Exception $e) {
        $conn->rollback();

        $redirect = $_SERVER['HTTP_REFERER'] ?? "../profile.php";
        $redirect .= (parse_url($redirect, PHP_URL_QUERY) ? '&' : '?') . "error=" . urlencode($e->getMessage());

        header("Location: $redirect");
        exit;
    }
}

/* ============================================================
   🔹 Fallback (ha nem POST) -> kell ha valaki az direktbe akarja megnyitni a _post.php fájlt
============================================================ */
header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit;
