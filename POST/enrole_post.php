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
$role = $_SESSION['role'];

/* ============================================================
   🔹 Csak POST esetén fusson
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* ============================================
       🧑‍🏫 TANÁR: Új kurzus (offering) létrehozása + kurzusleírás frissítés
    ============================================ */
    if ($role === 'tanar' && isset($_POST['create_offering'])) {

        try {
            $conn->begin_transaction();

            /* ============================================================
            🔹 Adatok inicializálása
            ============================================================ */
            $kurzus_kod = trim($_POST['kurzus_kod'] );
            $semester_id = intval($_POST['semester_id']);
            $course_type = trim($_POST['course_type'] ?? 'eloadas');
            $day_of_week = trim($_POST['day_of_week'] ?? '');
            $start_time = $_POST['start_time'] ?? '';
            $room = trim($_POST['room'] ?? '');
            $max_students = intval($_POST['max_students'] ?? 0);
            $end_date = $_POST['end_date'] ;
            $course_description = trim($_POST['course_description'] ?? '');

            if (empty($kurzus_kod) || empty($semester_id) || empty($end_date)) {
                throw new Exception("Hiányos adatok.");
            }

            // Kurzusleírás frissítése a 'courses' táblában
            if (!empty($course_description)) {
                $update_course_sql = "
                UPDATE courses 
                SET leiras = ? 
                WHERE kurzus_kod = ?";

                $stmt = $conn->prepare($update_course_sql);
                $stmt->bind_param("ss", $course_description, $kurzus_kod);
                if (!$stmt->execute()) {
                    throw new Exception("A kurzus leírásának frissítése sikertelen: " . $stmt->error);
                }
                $stmt->close();
            }

            // Új kurzus (course_offering) létrehozása
            $insert_course_offering_sql = "
            INSERT INTO course_offerings 
            (kurzus_kod, semester_id, teacher_id, course_type, day_of_week, start_time, room, end_date, max_students)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($insert_course_offering_sql);
            $stmt->bind_param("sissssssi",
                $kurzus_kod,
                $semester_id,
                $eduportal_id,
                $course_type,
                $day_of_week,
                $start_time,
                $room,
                $end_date,
                $max_students
            );

            if (!$stmt->execute()) {
                throw new Exception("Hiba az új kurzus létrehozásánál: " . $stmt->error);
            }

            $stmt->close();
            $conn->commit();

            header("Location: ../teacher/course_offering.php?success=1");
            exit;

        } catch (Exception $e) {
            $conn->rollback();

            $error_message = urlencode("Hiba történt: " . $e->getMessage());
            header("Location: ../teacher/courses.php?error={$error_message}");
            exit;
        }
    }

    /* ============================================
       🧑‍🏫 TANÁR: Meglévő kurzus (offering) szerkesztése
    ============================================ */
    if ($role === 'tanar' && isset($_POST['edit_offering'])) {

        try {
            $conn->begin_transaction();

            /* ============================================================
            🔹 Adatok inicializálása
            ============================================================ */
            $kurzus_kod = trim($_POST['kurzus_kod'] );
            $offering_id = intval($_POST['offering_id']);
            $day_of_week = trim($_POST['day_of_week'] ?? '');
            $start_time = $_POST['start_time'] ?? '';
            $room = trim($_POST['room'] ?? '');
            $end_date = $_POST['end_date'] ?? null;
            $max_students = intval($_POST['max_students'] ?? 0);
            $course_description = trim($_POST['course_description'] ?? '');


            // Meglévő kurzus (course_offering) frissítése
            $update_course_offering_sql = "
            UPDATE course_offerings
            SET day_of_week = ?, start_time = ?, room = ?, end_date = ?, max_students = ?
            WHERE id = ?";

            $stmt = $conn->prepare($update_course_offering_sql);
            $stmt->bind_param("ssssii",
                $day_of_week,
                $start_time,
                $room,
                $end_date,
                $max_students,
                $offering_id);

            if (!$stmt->execute()) {
                throw new Exception("Hiba a meglévő kurzus frissítésekor: " . $stmt->error);
            }
            $stmt->close();

            // Kurzusleírás frissítése a 'courses' táblában
            if (!empty($kurzus_kod)) {
                $update_course_sql = "
                UPDATE courses 
                SET leiras = ?
                WHERE kurzus_kod = ?";

                $stmt = $conn->prepare($update_course_sql);
                $stmt->bind_param("ss", $course_description, $kurzus_kod);
                if (!$stmt->execute()) {
                    throw new Exception("Hiba a meglévő kurzus frissítésekor: " . $stmt->error);
                }
                $stmt->close();
            }

            $conn->commit();
            header("Location: ../teacher/course_offering.php?success=2");
            exit;

        /* ============================================================
        🔹 Hibakezelés
        ============================================================ */
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = urlencode("Hiba történt: " . $e->getMessage());
            header("Location: ../teacher/course_offering.php?error={$error_message}");
            exit;
        }
    }

    /* ============================================
       🧑‍🎓 DIÁK: Jelentkezés / lejelentkezés
    ============================================ */
    if ($role === 'hallgato' && isset($_POST['offering_id'])) {

        try {
            $conn->begin_transaction();

            /* ============================================================
            🔹 Adatok inicializálása
            ============================================================ */
            $offering_id  = intval($_POST['offering_id']);
            $kurzus_kod   = trim($_POST['kurzus_kod'] ?? '');
            $semester_id  = intval($_POST['semester_id'] ?? 0);
            $end_date     = $_POST['end_date'] ?? null;
            $action       = $_POST['action'] ?? 'enroll';

            if (!$kurzus_kod || !$semester_id || !$end_date) {
                throw new Exception("Hiányzó vagy hibás POST adatok.");
            }

            // Jelentkezési határidő ellenőrzése
            $now = date('Y-m-d H:i:s');
            if ($now > $end_date) {
                throw new Exception("Lejárt a jelentkezési határidő.");
            }

            /* ============================================================
               🔸 LEJELENTKEZÉS
            ============================================================ */
            if ($action === 'unenroll') {

                $delete_sql = "
                DELETE e FROM enrollments e
                JOIN course_offerings co ON co.id = e.offering_id
                WHERE e.users_eduportal_ID = ?
                AND e.offering_id = ?
                AND co.kurzus_kod = ?
                AND co.semester_id = ?";

                $stmt = $conn->prepare($delete_sql);
                $stmt->bind_param("sisi", $eduportal_id, $offering_id, $kurzus_kod, $semester_id);

                if (!$stmt->execute()) {
                    throw new Exception("Mentési hiba: " . $stmt->error);
                }

                $conn->commit();
                header("Location: ../student/course_offering.php?success=1");
                exit;
            }

            /* ============================================================
               🔸 JELENTKEZÉS
            ============================================================ */
            // Ellenőrzés: van-e már jelentkezés
            $check_sql = "
            SELECT 1
            FROM enrollments e
            JOIN course_offerings co ON co.id = e.offering_id
            WHERE e.users_eduportal_ID = ?
            AND co.kurzus_kod = ?
            AND co.semester_id = ?
            LIMIT 1";

            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("ssi", $eduportal_id, $kurzus_kod, $semester_id);
            $stmt->execute();
            $check = $stmt->get_result();

            if ($check->num_rows > 0) {
                throw new Exception("Már jelentkeztél erre a kurzusra ebben a félévben.");
            }

            // Jelentkezés beszúrása
            $status = 'enrolled';
            $insert_sql = "
            INSERT INTO enrollments (users_eduportal_ID, offering_id, status)
            VALUES (?, ?, ?)";

            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("sis", $eduportal_id, $offering_id, $status);
            $stmt->execute();

            $conn->commit();
            header("Location: ../student/course_offering.php?success=2");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = urlencode("Hiba történt: " . $e->getMessage());
            header("Location: ../student/course_offering.php?error={$error_message}");
            exit;
        }
    }
} else {
    /* ============================================================
       🔹 Fallback (ha nem POST) -> kell ha valaki az direktbe akarja megnyitni a _post.php fájlt
    ============================================================ */
    header("Location: ../index.php?error=Fallback");
    exit;
}
