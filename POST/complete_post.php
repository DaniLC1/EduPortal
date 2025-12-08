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
        $student_id = $_POST['student_id'] ?? null;
        $offering_id = $_POST['offering_id'] ?? null;
        $grade = trim($_POST['grade'] ?? '');

        // Logikai feltétel: jegy alapján státusz meghatározás
        if ($grade === '') {
            $status = 'enrolled';
            $grade_value = null;
        } elseif ($grade === 'Elégtelen') {
            $status = 'failed';
            $grade_value = 'Elégtelen';
        } elseif ($grade === 'Elégséges') {
            $status = 'completed';
            $grade_value = $grade;
        } elseif ($grade === 'Közepes') {
            $status = 'completed';
            $grade_value = $grade;
        } elseif ($grade === 'Jó') {
            $status = 'completed';
            $grade_value = $grade;
        } elseif ($grade === 'Kiváló') {
            $status = 'completed';
            $grade_value = $grade;
        } else{
            throw new Exception("Ismeretlen osztályzat.");
        }


        $update_sql = "
            UPDATE enrollments
            SET grade = ?, status = ?, completed_at = NOW()
            WHERE users_eduportal_id = ? AND offering_id = ?
        ";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssss", $grade_value, $status, $student_id, $offering_id);

        if (!$stmt->execute()) {
            throw new Exception("Sikertelen frissítés: " . $stmt->error);
        }

        $conn->commit();
        $stmt->close();

        /* ============================================================
        🔹 Visszairányítás szerepkör alapján
        ============================================================ */
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'tanar') {
            header("Location: ../teacher/student_complete.php?success=10");
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
            header("Location: ../teacher/student_complete.php?error={$error_message}");
        } else {
            header("Location: ../index.php?error={$error_message}");
        }
        exit;
    }
} else {
    /* ============================================================
       🔹 Fallback (ha nem POST) -> kell ha valaki az direktbe akarja megnyitni a _post.php fájlt
    ============================================================ */
    header("Location: ../index.php?error=Fallback");
    exit;
}
