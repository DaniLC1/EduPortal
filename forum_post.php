<?php
session_start();
require_once 'connection.php';

global $conn;

$eduportal_id = $_SESSION['eduportal_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Új hozzászólás mentése
    if (isset($_POST['submit_new_message'])) {
        $newMessage = trim($_POST['new_message']);
        $courseOfferingId = intval($_POST['course_offering_id']);
        $currentSemester = 2; // vagy dinamikusan töltsd

        if (!empty($newMessage) && $courseOfferingId > 0) {
            // 1. Hozzászólás mentése a notifications táblába
            $insert_sql = "INSERT INTO notifications (message, noti_type, users_eduportal_ID, course_offering_id, semester, created_at, updated_at)
                       VALUES (?, 'forum', ?, ?, ?, NOW(), NOW())";
            $stmt = $conn->prepare($insert_sql);
            if (!$stmt) {
                die("Hiba a prepare-nél: " . $conn->error);
            }
            $stmt->bind_param("ssis", $newMessage, $eduportal_id, $courseOfferingId, $currentSemester);
            if (!$stmt->execute()) {
                die("Hiba az execute-nál: " . $stmt->error);
            }

            // 2. Új notification ID lekérdezése
            $notification_id = $stmt->insert_id;

            // 3. Érintett felhasználók lekérdezése (tanulók + tanár), kivéve a hozzászólót
            $user_query = "
            SELECT DISTINCT u.eduportal_id
            FROM users u
            LEFT JOIN enrollments e ON u.eduportal_id = e.users_eduportal_ID AND e.offering_id = ?
            LEFT JOIN course_offerings co ON co.id = ?
            WHERE (
                u.eduportal_id = co.teacher_id
                OR e.users_eduportal_ID IS NOT NULL
            )
            AND u.eduportal_id != ?
        ";
            $stmt_users = $conn->prepare($user_query);
            if (!$stmt_users) {
                die("Hiba a felhasználók lekérdezésénél: " . $conn->error);
            }
            $stmt_users->bind_param("iis", $courseOfferingId, $courseOfferingId, $eduportal_id);
            $stmt_users->execute();
            $result_users = $stmt_users->get_result();

            // 4. INSERT minden érintett felhasználónak a notification_reads táblába
            $insert_read_sql = "INSERT INTO notification_reads (users_eduportal_ID, notification_id) VALUES (?, ?)";
            $stmt_insert_read = $conn->prepare($insert_read_sql);
            if (!$stmt_insert_read) {
                die("Hiba a notification_reads prepare-nél: " . $conn->error);
            }

            while ($row = $result_users->fetch_assoc()) {
                $userId = $row['eduportal_id'];
                $stmt_insert_read->bind_param("si", $userId, $notification_id);
                $stmt_insert_read->execute();
            }

            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit;
        } else {
            $_SESSION['error'] = "Az üzenet nem lehet üres, és a kurzusnak léteznie kell.";
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit;
        }
    }

    // Hozzászólás szerkesztése
    if (isset($_POST['submit_edit_message'])) {
        $editedMessage = trim($_POST['edited_message']);
        $messageId = intval($_POST['edit_message_id']);

        if (!empty($editedMessage) && $messageId > 0) {
            $update_sql = "UPDATE notifications SET message = ?, updated_at = NOW() WHERE id = ? AND users_eduportal_ID = ?";
            $stmt = $conn->prepare($update_sql);
            if (!$stmt) {
                die("Hiba a prepare-nél (update): " . $conn->error);
            }
            $stmt->bind_param("sis", $editedMessage, $messageId, $eduportal_id);
            if (!$stmt->execute()) {
                die("Hiba az execute-nál (update): " . $stmt->error);
            }

            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit;
        } else {
            $_SESSION['error'] = "Az üzenet nem lehet üres.";
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit;
        }
    }

    // Ha egyik sem ment le
    $_SESSION['error'] = "Ismeretlen művelet.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}
