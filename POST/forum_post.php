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

    /* ============================================================
    🔹 Új üzenet mentése (forum vagy hirdetmény)
    ============================================================ */
    if (isset($_POST['submit_new_message'])) {

        try {
            $conn->begin_transaction();

            /* ============================================================
            🔹 Adatok inicializálása
            ============================================================ */
            $newMessage = trim($_POST['new_message']);
            $courseOfferingId = intval($_POST['course_offering_id']);
            $currentSemester = trim($_POST['semester'] ?? '');
            $type = $_POST['noti_type'] ?? 'forum';

            if (empty($newMessage) || $courseOfferingId <= 0) {
                throw new Exception("Az üzenet nem lehet üres, és érvényes kurzus kell.");
            }

            // 🔹 INSERT notifications
            $insert_notifications_sql = "
            INSERT INTO notifications 
            (message, noti_type, users_eduportal_ID, course_offering_id, semester, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())";

            $stmt = $conn->prepare($insert_notifications_sql);
            $stmt->bind_param("sssis", $newMessage, $type, $eduportal_id, $courseOfferingId, $currentSemester);

            if (!$stmt->execute()) {
                throw new Exception("Hiba az új üzenet mentésekor: " . $stmt->error);
            }

            $notification_id = $stmt->insert_id;
            $stmt->close();

            // 🔹Érintett felhasználók lekérése
            $user_query = "
            SELECT DISTINCT u.eduportal_id
            FROM users u
            LEFT JOIN enrollments e ON e.users_eduportal_ID = u.eduportal_id AND e.offering_id = ?
            LEFT JOIN course_offerings co ON co.id = ?
            WHERE ( u.eduportal_id = co.teacher_id
                   OR e.users_eduportal_ID IS NOT NULL )
            AND u.eduportal_id != ?";

            $stmt_users = $conn->prepare($user_query);
            $stmt_users->bind_param("iis", $courseOfferingId, $courseOfferingId, $eduportal_id);
            $stmt_users->execute();
            $users = $stmt_users->get_result();


            // 🔹INSERT notification_reads
            $insert_notifications_reads_sql = "
            INSERT INTO notification_reads (users_eduportal_ID, notification_id) 
            VALUES (?, ?)";

            $stmt  = $conn->prepare($insert_notifications_reads_sql);

            while ($u = $users->fetch_assoc()) {
                $uid = $u['eduportal_id'];
                $stmt->bind_param("si", $uid, $notification_id);
                if (!$stmt->execute()) {
                    throw new Exception("Hiba az új üzenet mentésekor: " . $stmt->error);
                }
            }

            $stmt->close();
            $conn->commit();

            /* ============================================================
            🔹 Visszairányítás szerepkör alapján
            ============================================================ */
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'tanar') {
                header("Location: ../teacher/courses.php?success=7");
            } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'hallgato') {
                header("Location: ../student/courses.php?success=7");
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
                header("Location: ../teacher/courses.php?error={$error_message}");
            } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'hallgato') {
                header("Location: ../student/courses.php?error={$error_message}");
            } else {
                header("Location: ../index.php?error={$error_message}");
            }
            exit;
        }
    }

    /* ============================================================
       🔹 2) ÜZENET SZERKESZTÉSE
    ============================================================ */
    if (isset($_POST['submit_edit_message'])) {

        try {
            $conn->begin_transaction();

            /* ============================================================
            🔹 Adatok inicializálása
            ============================================================ */
            $editedMessage = trim($_POST['edited_message']);
            $messageId = intval($_POST['edit_message_id']);

            if (empty($editedMessage) || $messageId <= 0) {
                throw new Exception("Az üzenet nem lehet üres.");
            }

            $update_sql = "
            UPDATE notifications
            SET message = ?, updated_at = NOW()
            WHERE id = ? AND users_eduportal_ID = ?";

            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("sis", $editedMessage, $messageId, $eduportal_id);

            if (!$stmt->execute()) {
                throw new Exception("Hiba szerkesztéskor: " . $stmt->error);
            }

            $stmt->close();
            $conn->commit();

            /* ============================================================
            🔹 Visszairányítás szerepkör alapján
            ============================================================ */
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'tanar') {
                header("Location: ../teacher/courses.php?success=8");
            } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'hallgato') {
                header("Location: ../student/courses.php?success=8");
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
                header("Location: ../teacher/courses.php?error={$error_message}");
            } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'hallgato') {
                header("Location: ../student/courses.php?error={$error_message}");
            } else {
                header("Location: ../index.php?error={$error_message}");
            }
            exit;
        }
    }


    /* ============================================================
      🔹 3) ÜZENET VISSZAVONÁSA
   ============================================================ */
    if (isset($_POST['submit_delete_message']) && $_SESSION['role'] === 'tanar') {

        try {
            $conn->begin_transaction();

            /* ============================================================
            🔹 Adatok inicializálása
            ============================================================ */
            $messageId = intval($_POST['delete_message_id']);
            $type = $_POST['noti_type'] ?? 'forum';

            if ($messageId <= 0) {
                throw new Exception("Hiba, nincs kiválasztva üzenet.");
            }

            if ($type === 'hirdetmeny') {
                $prefix = "Visszavont hirdetmény:";
            } else {
                $prefix = "Törölt fórum poszt:";
            }

            // Eredeti üzenet lekérése
            $orig_sql = "
            SELECT message 
            FROM notifications 
            WHERE id = ?";

            $stmt = $conn->prepare($orig_sql);
            $stmt->bind_param("i", $messageId);
            $stmt->execute();
            $orig = $stmt->get_result()->fetch_assoc();

            if (!$orig) {
                throw new Exception("Az üzenet nem létezik.");
            }

            // Nem töröljük — prefix + eredeti tartalom
            $origText = $orig['message'];

            $update_sql = "
            UPDATE notifications
            SET message = CONCAT(?, ' ', ?), updated_at = NOW()
            WHERE id = ?";

            $stmt2 = $conn->prepare($update_sql);
            $stmt2->bind_param("ssi", $prefix, $origText, $messageId);
            if (!$stmt2->execute()) {
                throw new Exception("Hiba törléskor: " . $stmt2->error);
            }

            $stmt2->close();
            $conn->commit();

            /* ============================================================
             🔹 Visszairányítás
             ============================================================ */
            header("Location: ../teacher/courses.php?success=9");
            exit;

        /* ============================================================
        🔹 Hibakezelés
        ============================================================ */
        } catch (Exception $e) {
            $conn->rollback();

            $error_message = urlencode("Hiba történt: " . $e->getMessage());
            header("Location: ../teacher/courses.php?error={$error_message}");
            exit;
        }
    }

}else {
    /* ============================================================
       🔹 Fallback (ha nem POST) -> kell ha valaki az direktbe akarja megnyitni a _post.php fájlt
    ============================================================ */
    header("Location: ../index.php?error=Fallback");
    exit;
}
