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
$role = $_SESSION['role'];

/* ============================================================
   🔹 Csak POST esetén fusson
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* ================================================================
       🔹 HALLGATÓ / TANÁR kérelmek beküldése
       ================================================================= */
    if ($role === 'hallgato'  || $role === 'tanar') {

        try {
            $conn->begin_transaction();

            /* ============================================================
              🔹 Adatok inicializálása
            ============================================================ */
            $template_id = intval($_POST['template_id'] ?? NULL);
            if (!$template_id) {
                throw new Exception('Nem lett kiválasztva kérelemsablon.');
            }

            // Kitöltött mezők összegyűjtése
            $field_values = [];
            foreach ($_POST as $key => $value) {
                // Kihagyjuk a sablon ID-t és minden nem szám kulcsot
                if ($key === 'template_id') continue;

                $field_id = intval($key); // automatikusan 0 lesz, ha nem szám
                if ($field_id <= 0) continue; // csak a pozitív szám ID-ket vegyük

                $field_values[$field_id] = trim($value);
            }

            // Ha nincs kitöltött mező, dobunk Exception-t
            if (empty($field_values)) {
                throw new Exception('Nincsenek kitöltött mezők.');
            }

            // Kitöltött kérelem beszúrása
            $insert_student_requests_sql = "
            INSERT INTO student_requests (users_eduportal_ID, template_id, status)
            VALUES (?, ?, 'beküldve')";

            $stmt = $conn->prepare($insert_student_requests_sql);
            $stmt->bind_param("si", $eduportal_id, $template_id);
            if (!$stmt->execute()) {
                throw new Exception("Hiba az új kérelem beküldésekor: " . $stmt->error);
            }

            $request_id = $stmt->insert_id;
            $stmt->close();

            // Kitöltött kérelem mezőinek beszúrása
            $insert_student_requests_field_values_sql = "
            INSERT INTO student_request_field_values (request_id, field_id, field_value)
            VALUES (?, ?, ?)
            ";

            $stmt2 = $conn->prepare($insert_student_requests_field_values_sql);

            foreach ($field_values as $field_id => $field_value) {
                $stmt2->bind_param("iis", $request_id, $field_id, $field_value);
                if (!$stmt2->execute()) {
                    throw new Exception("Hiba az új kérelem mezőink beszúrásakor: " . $stmt2->error);
                }
            }

            $stmt2->close();
            $conn->commit();

            /* ============================================================
              🔹 Visszairányításszerepkör alapján
            ============================================================ */
            if ($role === 'tanar') {
                header('Location: ../teacher/request.php?success=1');
            } elseif ($role === 'hallgato') {
                header('Location: ../student/request.php?success=1');
            } else {
                header("Location: ../index.php?error=Ismeretlen role.");
            }
            exit;

        }catch (Exception $e) {
            $conn->rollback();

            $error_message = urlencode("Hiba történt: " . $e->getMessage());
            if ($role === 'tanar') {
                header("Location: ../teacher/request.php?error={$error_message}");
            } elseif ($role === 'hallgato') {
                header("Location: ../student/request.php?error={$error_message}");
            } else {
                header("Location: ../index.php?error={$error_message}");
            }
            exit;
        }
    }

    /* ================================================================
      🔹ADMIN - request.php (kérelemsablon létrehozása / szerkesztése / törlése)
    ================================================================ */
    elseif ($role === 'admin' && isset($_POST['admin_request'])) {

        try {
            $conn->begin_transaction();

            /* ============================================================
            🔹 Adatok inicializálása
            ============================================================ */
            $template_id = intval($_POST['template_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $to_who = $_POST['to_who'] ?? 'hallgato';
            $fields = $_POST['fields'] ?? [];

            if (empty($title)) {
                throw new Exception("A kérelem címe kötelező.");
            }

            /* ============================================================
              🔹 Törlés és deaktiválás
            ============================================================ */
            if (isset($_POST['delete'])) {

                if ($template_id <= 0) {
                    throw new Exception("Hibás sablon azonosító.");
                }

                // 🔹 Ellenőrizzük, hogy van-e beadott kérelem
                $check_sql = "
                SELECT COUNT(*) AS cnt
                FROM student_requests
                WHERE template_id = ?";

                $stmt = $conn->prepare($check_sql);
                $stmt->bind_param("i", $template_id);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $has_submissions = $res['cnt'] > 0;

                if ($has_submissions) {
                    // 🔹 Inaktiváljuk a sablont
                    $inactive_sql = "
                    UPDATE request_templates
                    SET is_active = 0
                    WHERE id = ?";

                    $stmt = $conn->prepare($inactive_sql);
                    $stmt->bind_param("i", $template_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Nem sikerült inaktiválni a sablont: " . $stmt->error);
                    }
                    $stmt->close();

                    $conn->commit();
                    header("Location: ../admin/request.php?success=deactivated");
                    exit;
                }

                // 🔹 Ha nincs beadott kérelem → törlés
                $delete_fields_sql = "
                DELETE FROM request_template_fields 
                WHERE template_id = ?";

                $stmt = $conn->prepare($delete_fields_sql);
                $stmt->bind_param("i", $template_id);
                if (!$stmt->execute()) {
                    throw new Exception("Hiba a kérelem mezőinek törlésekor: " . $stmt->error);
                }
                $stmt->close();

                $delete_template_sql = "
                DELETE FROM request_templates 
                WHERE id = ?";

                $stmt = $conn->prepare($delete_template_sql);
                $stmt->bind_param("i", $template_id);
                if (!$stmt->execute()) {
                    throw new Exception("Hiba a kérelem törlésekor: " . $stmt->error);
                }
                $stmt->close();

                $conn->commit();
                header("Location: ../admin/request.php?success=deleted");
                exit;
            }

            /* ============================================================
               🔹 Új kérelem INSERT
            ============================================================ */
            if ($template_id === 0) {

                $insert_sql = "
                INSERT INTO request_templates (title, description, to_who, version, is_active)
                VALUES (?, ?, ?, 1, 1)";

                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("sss", $title, $description, $to_who);
                if (!$stmt->execute()) {
                    throw new Exception("Hiba az új kérelem beszúrásakor: " . $stmt->error);
                }

                $template_id = $stmt->insert_id;
                $stmt->close();
            }
            /* ============================================================
               🔹 Meglévő kérelem szerkesztése → verziózás ellenőrzés
            ============================================================ */
            else {

                // 1) Ellenőrizzük, hogy ez a sablon használatban van-e
                $check_sql = "
                SELECT COUNT(*) AS cnt
                FROM student_requests
                WHERE template_id = ?";

                $stmt = $conn->prepare($check_sql);
                $stmt->bind_param("i", $template_id);
                $stmt->execute();
                $used = $stmt->get_result()->fetch_assoc()['cnt'];
                $stmt->close();

                /* ============================================================
                   🔹 Ha használatban van → ÚJ verzió létrehozása
                ============================================================ */
                if ($used > 0) {
                    // régi verzió adatainak lekérése a verziószám növeléséhez
                    $version_sql = "
                    SELECT version
                    FROM request_templates
                    WHERE id = ?";

                    $stmt = $conn->prepare($version_sql);
                    $stmt->bind_param("i", $template_id);
                    $stmt->execute();
                    $old_version = $stmt->get_result()->fetch_assoc()['version'];
                    $stmt->close();

                    $new_version = $old_version + 1;

                    // 🔹 Új sablon beszúrása
                    $insert_new_sql = "
                    INSERT INTO request_templates (title, description, to_who, version, previous_version_id, is_active)
                    VALUES (?, ?, ?, ?, ?, 1)";

                    $stmt = $conn->prepare($insert_new_sql);
                    $stmt->bind_param("sssii", $title, $description, $to_who, $new_version, $template_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Hiba az új verzió beszúrásakor: " . $stmt->error);
                    }

                    $new_template_id = $stmt->insert_id;
                    $stmt->close();

                    // 🔹 Régi sablon inaktiválása
                    $deactivate_old_sql = "
                    UPDATE request_templates
                    SET is_active = 0
                    WHERE id = ?";

                    $stmt = $conn->prepare($deactivate_old_sql);
                    $stmt->bind_param("i", $template_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Nem sikerült inaktiválni a régi sablont: " . $stmt->error);
                    }
                    $stmt->close();

                    // 🔹 Új mezők beszúrása az új sablonhoz
                    if (!empty($fields)) {
                        $insert_field_sql = "
                        INSERT INTO request_template_fields (template_id, label, field_type, is_required) 
                        VALUES (?, ?, ?, ?)";

                        $stmt = $conn->prepare($insert_field_sql);
                        foreach ($fields as $f) {
                            $label = trim($f['label'] ?? '');
                            $type = $f['field_type'] ?? 'text';
                            $req = isset($f['is_required']) ? 1 : 0;
                            if ($label === '') continue;
                            $stmt->bind_param("issi", $new_template_id, $label, $type, $req);
                            if (!$stmt->execute()) {
                                throw new Exception("Hiba az új verzió mezőinek beszúrásakor: " . $stmt->error);
                            }
                        }
                        $stmt->close();
                    }

                    $conn->commit();
                    header("Location: ../admin/request.php?success=versioned");
                    exit;
                }
                /* ============================================================
                   🔹 Ha nincs használatban → hagyományos UPDATE
                ============================================================ */
                else {

                    $update_sql = "
                    UPDATE request_templates
                    SET title = ?, description = ?, to_who = ?
                    WHERE id = ?";

                    $stmt = $conn->prepare($update_sql);
                    $stmt->bind_param("sssi", $title, $description, $to_who, $template_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Hiba a kérelem szerkesztésekor: " . $stmt->error);
                    }
                    $stmt->close();

                    // mezők törlése
                    $delete_fields_sql = "
                    DELETE FROM request_template_fields 
                    WHERE template_id = ?";

                    $stmt = $conn->prepare($delete_fields_sql);
                    $stmt->bind_param("i", $template_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Hiba a mezők törlésekor: " . $stmt->error);
                    }
                    $stmt->close();
                }
            }

            /* ============================================================
               🔹 Új mezők INSERT
            ============================================================ */
            if (!empty($fields)) {
                $insert_field_sql = "
                INSERT INTO request_template_fields (template_id, label, field_type, is_required) 
                VALUES (?, ?, ?, ?)";

                $stmt = $conn->prepare($insert_field_sql);
                foreach ($fields as $f) {
                    $label = trim($f['label'] ?? '');
                    $type = $f['field_type'] ?? 'text';
                    $req = isset($f['is_required']) ? 1 : 0;
                    if ($label === '') continue;
                    $stmt->bind_param("issi", $template_id, $label, $type, $req);
                    if (!$stmt->execute()) {
                        throw new Exception("Hiba az új mezők beszúrásakor: " . $stmt->error);
                    }
                }
                $stmt->close();
            }

            $conn->commit();
            header("Location: ../admin/request.php?success=1");
            exit;

        } catch (Exception $e) {
            $conn->rollback();

            $error_message = urlencode("Hiba történt: " . $e->getMessage());
            header("Location: ../admin/request.php?error={$error_message}");
            exit();
        }
    }


    /* ================================================================
       🔹 ADMIN - submitted_request.php (kérelmek elbírálása)
    ================================================================= */
    elseif ($role === 'admin' && isset($_POST['admin_submitted'])) {

        try {
            $conn->begin_transaction();

            /* ============================================================
               🔹 Adatok inicializálása
            ============================================================ */
            $request_id = intval($_POST['request_id'] ?? 0);
            $status = trim($_POST['status'] ?? '');
            $admin_comment = trim($_POST['admin_comment'] ?? '');
            $admin_suggestions = $_POST['admin_suggestion'] ?? [];

            if ($request_id <= 0) {
                throw new Exception("Érvénytelen kérelem azonosító.");
            }

            if ($admin_comment === '') {
                $status = 'beküldve';
            }

            /* ============================================================
               🔹 student_requests UPDATE
            ============================================================ */
            $update_student_request_sql = "
            UPDATE student_requests
            SET status = ?, admin_comment = NULLIF(?, ''), reviewed_at = NOW()
            WHERE id = ?";

            $stmt = $conn->prepare($update_student_request_sql);
            $stmt->bind_param("ssi", $status, $admin_comment, $request_id);
            if (!$stmt->execute()) {
                throw new Exception("Hiba a kérelem kitöltött mezőinek frissítése közben: " . $stmt->error);
            }
            $stmt->close();

            /* ============================================================
               🔹 student_request_field_values UPDATE (admin javaslat)
            ============================================================ */
            if (!empty($admin_suggestions)) {
                $update_student_request_field_values_sql = "
                UPDATE student_request_field_values
                SET admin_suggestion = NULLIF(?, '')
                WHERE request_id = ? AND field_id = ?";

                $stmt = $conn->prepare($update_student_request_field_values_sql);

                foreach ($admin_suggestions as $field_id => $suggestion) {
                    $clean = trim($suggestion);
                    $stmt->bind_param("sii", $clean, $request_id, $field_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Hiba a javaslatok beszúrásakor : " . $stmt->error);
                    }
                }

                $stmt->close();
            }

            $conn->commit();
            header("Location: ../admin/submitted_request.php?success=1");
            exit();

        } catch (Exception $e) {
            $conn->rollback();

            $error_message = urlencode("Hiba történt a kérelem frissítése közben: " . $e->getMessage());
            header("Location: ../admin/submitted_request.php?error={$error_message}");
            exit();
        }
    }
}else {
    /* ============================================================
       🔹 Fallback (ha nem POST) -> kell ha valaki az direktbe akarja megnyitni a _post.php fájlt
    ============================================================ */
    header("Location: ../index.php?error=Fallback");
    exit;
}
