<?php
session_start();
require_once 'connection.php';
global $conn;

$eduportal_id = $_SESSION['eduportal_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ✅ Új üzenet mentése (forum vagy hirdetmény)
    if (isset($_POST['submit_new_message'])) {
        $newMessage = trim($_POST['new_message']);
        $courseOfferingId = intval($_POST['course_offering_id']);
        $currentSemester = 2; // később dinamikusan
        $type = $_POST['noti_type'] ?? 'forum'; // <- hirdetményhez: 'hirdetmeny'

        if (!empty($newMessage) && $courseOfferingId > 0) {
            // 1. Insert a notifications táblába
            $insert_sql = "INSERT INTO notifications 
                (message, noti_type, users_eduportal_ID, course_offering_id, semester, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())";

            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("sssis", $newMessage, $type, $eduportal_id, $courseOfferingId, $currentSemester);
            $stmt->execute();

            $notification_id = $stmt->insert_id;

            // 2. Érintett felhasználók lekérdezése (tanár + diákok)
            $user_query = "
                SELECT DISTINCT u.eduportal_id
                FROM users u
                LEFT JOIN enrollments e ON e.users_eduportal_ID = u.eduportal_id AND e.offering_id = ?
                LEFT JOIN course_offerings co ON co.id = ?
                WHERE (
                    u.eduportal_id = co.teacher_id
                    OR e.users_eduportal_ID IS NOT NULL
                )
                AND u.eduportal_id != ?
            ";

            $stmt_users = $conn->prepare($user_query);
            $stmt_users->bind_param("iis", $courseOfferingId, $courseOfferingId, $eduportal_id);
            $stmt_users->execute();
            $users = $stmt_users->get_result();

            // 3. notification_reads feltöltés
            $insert_read = $conn->prepare("INSERT INTO notification_reads (users_eduportal_ID, notification_id) VALUES (?, ?)");
            while ($u = $users->fetch_assoc()) {
                $uid = $u['eduportal_id'];
                $insert_read->bind_param("si", $uid, $notification_id);
                $insert_read->execute();
            }

            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit;
        } else {
            $_SESSION['error'] = "Az üzenet nem lehet üres, és a kurzusnak léteznie kell.";
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit;
        }
    }

    // ✅ Üzenet szerkesztése
    if (isset($_POST['submit_edit_message'])) {
        $editedMessage = trim($_POST['edited_message']);
        $messageId = intval($_POST['edit_message_id']);

        if (!empty($editedMessage) && $messageId > 0) {
            $update_sql = "UPDATE notifications 
                           SET message = ?, updated_at = NOW() 
                           WHERE id = ? AND users_eduportal_ID = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("sis", $editedMessage, $messageId, $eduportal_id);
            $stmt->execute();

            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit;
        } else {
            $_SESSION['error'] = "Az üzenet nem lehet üres.";
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit;
        }
    }

    // ✅ Üzenet „törlése” (visszavonás)
    if (isset($_POST['submit_delete_message'])) {
        $messageId = intval($_POST['delete_message_id']);
        $type = $_POST['noti_type'];

        if ($messageId > 0) {
            if ($type === 'hirdetmeny') {
                $prefix = "Visszavont hirdetmény:";
            } else {
                $prefix = "Törölt fórum poszt:";
            }

            // 1️⃣ Lekérjük az eredeti üzenetet
            $orig_query = "SELECT message FROM notifications WHERE id = ?";
            $orig_stmt = $conn->prepare($orig_query);
            $orig_stmt->bind_param("i", $messageId);
            $orig_stmt->execute();
            $orig_result = $orig_stmt->get_result();
            $orig = $orig_result->fetch_assoc();

            if ($orig) {
                $orig_message = $orig['message'];

                // 2️⃣ Frissítjük az üzenetet, de nem töröljük
                $update_sql = "UPDATE notifications 
                           SET message = CONCAT(?, ' ', ?),
                               updated_at = NOW()
                           WHERE id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("ssi", $prefix, $orig_message, $messageId);
                $stmt->execute();
            }

            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit;
        }
    }

    $_SESSION['error'] = "Ismeretlen művelet.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}
