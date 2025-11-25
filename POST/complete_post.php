<?php
session_start();
require_once 'connection.php';
global $conn;

if (!isset($_SESSION['eduportal_id']) || $_SESSION['role'] !== 'tanar') {
    header("Location: ../index.php?error=Nincs jogosultságod az oldal megtekintéséhez.");
    exit;
}

$eduportal_id = $_SESSION['eduportal_id'];

// Csak POST kérés esetén működjön
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Beérkező adatok
    $student_id = $_POST['student_id'] ?? null;
    $offering_id = $_POST['offering_id'] ?? null;
    $grade = trim($_POST['grade'] ?? '');

    if (empty($student_id) || empty($offering_id)) {
        die("Hiba: hiányzó paraméterek!");
    }

    // Logikai feltétel: jegy alapján státusz meghatározás
    if ($grade === '') {
        $status = 'enrolled';
        $grade_value = null;
    } elseif ($grade === 'Elégtelen') {
        $status = 'failed';
        $grade_value = 'Elégtelen';
    } else {
        $status = 'completed';
        $grade_value = $grade;
    }

    try {
        $conn->begin_transaction();

        // UPDATE lekérdezés az enrollments táblába
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

        // ✅ Siker esetén visszairányítás
        header("Location: teacher/student_complete.php?success=1");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        // ⚠️ Hiba esetén visszairányítás, a hibaüzenetet GET paraméterben továbbítva
        $error_message = urlencode("Hiba történt: " . $e->getMessage());
        header("Location: teacher/student_complete.php?error={$error_message}");
        exit;
    }
} else {
    header("Location: ../index.php?error=invalid_request");
    exit;
}
?>
