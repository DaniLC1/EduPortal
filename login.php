<?php
global $conn;
session_start();
require_once 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eduportal_id = trim($_POST['eduportal_id']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE eduportal_id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $eduportal_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // 🔐 Jelszó-ellenőrzés (hash nélkül, ahogy most is van)
            if ($password === $user['password_hash']) {

                // ✅ Session beállítása
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['eduportal_id'] = $user['eduportal_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['program'] = $user['course_code'];

                // 🎯 Role alapján átirányítás
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
                        header("Location: index.php?error=invalid_role");
                        break;
                }
                exit;

            } else {
                header("Location: index.php?error=invalid_credentials");
                exit;
            }
        } else {
            header("Location: index.php?error=user_not_found");
            exit;
        }
    } else {
        die("Hiba a lekérdezés előkészítésekor.");
    }
} else {
    header("Location: index.php");
    exit;
}
?>
