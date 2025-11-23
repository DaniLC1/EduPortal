<?php
// Globális session és connection
session_start();
require_once 'connection.php';
global $conn;

/* ============================================================
   🔹 Index oldal belépés ellenőrzése
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $eduportal_id = trim($_POST['eduportal_id']);
    $password = $_POST['password'];

    try {
        $conn->begin_transaction();


        /* ============================================================
        🔹 Felhasználó lekérdezése
        ============================================================ */
        $sql = "SELECT * FROM users WHERE eduportal_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Hiba a lekérdezés előkészítésekor.");

        $stmt->bind_param("s", $eduportal_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            throw new Exception("Nincs ilyen felhasználó.");
        }

        $user = $result->fetch_assoc();

        /* ============================================================
        🔹 Felhasználó jelszavának ellenőrzése
        ============================================================ */
        if (!password_verify($password, $user['password_hash'])) {
            throw new Exception("Hibás jelszó.");
        }


        /* ============================================================
        🔹 Session beállítása
        ============================================================ */
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['eduportal_id'] = $user['eduportal_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['program'] = $user['course_code'];

        $conn->commit();

        /* ============================================================
        🔹 Role alapján átirányítás
        ============================================================ */
        switch ($user['role']) {
            case 'hallgato':
                header("Location: student/courses.php");
                break;
            case 'tanar':
                header("Location: teacher/courses.php");
                break;
            case 'admin':
                header("Location: admin/database.php");
                break;
            default:
                throw new Exception("Ismeretlen szerepkör: {$user['role']}");
        }
        exit;

    } catch (Exception $e) {
       $conn->rollback();
       $error_message = urlencode("Hiba történt: " . $e->getMessage());
       header("Location: index.php?error={$error_message}");
       exit;
    }

}

/* ============================================================
   🔹 Fallback (ha nem POST) -> kell ha valaki az direktbe akarja megnyitni a _post.php fájlt
============================================================ */
header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit;
?>
