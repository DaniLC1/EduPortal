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

    /* ============================================================================
       🔹DIÁK – dolgozat beküldése
    ============================================================================ */
    if ($role === 'hallgato' && $_POST['action'] === 'student_submit') {

        try {
            $conn->begin_transaction();

            /* ============================================================
            🔹 Adatok inicializálása
            ============================================================ */
            $assignment_id = $_POST['assignment_id'] ?? null;
            $answers = $_POST['answers'] ?? [];
            $total_score = 0;
            $question_type = '';
            $question_score = 0;
            $is_correct = 0;
            $due_date = null;

            if (!$assignment_id || empty($answers)) {
                throw new Exception("Hiányzó adatok a beküldéshez.");
            }

            /* ============================================================
               🔹 Dolgozat határidejének ellenőrzése
            ============================================================ */
            $assignment_check_sql = "
            SELECT due_date 
            FROM assignments 
            WHERE id = ?";

            $stmt = $conn->prepare($assignment_check_sql);
            $stmt->bind_param("i", $assignment_id);
            $stmt->execute();
            $stmt->bind_result($due_date);
            if (!$stmt->fetch()) {
                $stmt->close();
                throw new Exception("Nem található a dolgozat!");
            }
            $stmt->close();

            //  Aktuális dátum-óra
            $now = date('Y-m-d H:i:s');
            // Ha a határidő lejárt
            if ($now > $due_date) {
                throw new Exception("A dolgozat határideje lejárt, nem lehet beküldeni.");
            }


            /* ============================================================
                🔹 Beadás rögzítése
            ============================================================ */
            $insert_assignment_submissions_sql = "
            INSERT INTO assignment_submissions (assignment_id, users_eduportal_ID, submitted_at) 
            VALUES (?, ?, NOW())";

            $stmt = $conn->prepare($insert_assignment_submissions_sql);
            $stmt->bind_param("is", $assignment_id, $eduportal_id);
            if (!$stmt->execute()) {
                throw new Exception("Hiba a dolgozat beszúrásakor: " . $stmt->error);
            }

            $submission_id = $stmt->insert_id;
            $stmt->close();

            /* ============================================================
               🔹 Kérdések és válaszok feldolgozása
            ============================================================ */
            foreach ($answers as $question_id => $answer_value) {
                $question_id = (int)$question_id;

                // Kérdés típusának lekérdezése
                $question_type_sql = "
                SELECT 
                    question_type, 
                    score 
                FROM assignment_questions 
                WHERE id = ?";

                $stmt = $conn->prepare($question_type_sql);
                $stmt->bind_param("i", $question_id);
                $stmt->execute();
                $stmt->bind_result($question_type, $question_score);
                $stmt->fetch();
                $stmt->close();

                if ($question_type === 'true_false') {
                    $selected_answer_id = (int)$answer_value;

                    // Válasz mentése
                    $insert_assignment_answers_sql = "
                    INSERT INTO submission_answers (submission_id, question_id, selected_answer_id) 
                    VALUES (?, ?, ?)";

                    $stmt = $conn->prepare($insert_assignment_answers_sql);
                    $stmt->bind_param("iii", $submission_id, $question_id, $selected_answer_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Hiba a dolgozat válaszainak beszúrásakor (true_false): " . $stmt->error);
                    }
                    $stmt->close();

                    // Pontszám ellenőrzés
                    $score_sql = "
                    SELECT is_correct 
                    FROM question_answers 
                    WHERE id = ?";

                    $stmt = $conn->prepare($score_sql);
                    $stmt->bind_param("i", $selected_answer_id);
                    $stmt->execute();
                    $stmt->bind_result($is_correct);

                    if ($stmt->fetch() && $is_correct) {
                        $total_score += (float)$question_score;
                    }
                    $stmt->close();

                } elseif ($question_type === 'multiple_choice') {
                    // Több kijelölt válasz kezelése
                    $selected_ids = array_map('intval', array_keys($answer_value));

                    foreach ($selected_ids as $aid) {
                        $insert_submission_answers_sql = "
                        INSERT INTO submission_answers (submission_id, question_id, selected_answer_id) 
                        VALUES (?, ?, ?)";

                        $stmt = $conn->prepare($insert_submission_answers_sql);
                        $stmt->bind_param("iii", $submission_id, $question_id, $aid);
                        if (!$stmt->execute()) {
                            throw new Exception("Hiba a dolgozat válaszainak beszúrásakor (multiple_choice): " . $stmt->error);
                        }
                        $stmt->close();
                    }

                    // Ellenőrzés
                    $correct_ids = [];
                    $correct_answers_sql = "
                    SELECT 
                        id 
                    FROM question_answers 
                    WHERE question_id = ? 
                      AND is_correct = 1";

                    $stmt = $conn->prepare($correct_answers_sql);
                    $stmt->bind_param("i", $question_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    while ($row = $result->fetch_assoc()) {
                        $correct_ids[] = (int)$row['id'];
                    }
                    $stmt->close();

                    sort($selected_ids);
                    sort($correct_ids);

                    if ($selected_ids === $correct_ids) {
                        $total_score += (float)$question_score;
                    }
                }
            }

            // 4. Pontszám mentése
            $update_assignment_submissions_sql = "
            UPDATE assignment_submissions 
            SET score = ?, graded_at = NOW() 
            WHERE id = ?";

            $stmt = $conn->prepare($update_assignment_submissions_sql);
            $stmt->bind_param("di", $total_score, $submission_id);
            if (!$stmt->execute()) {
                throw new Exception("Hiba a dolgozat pontszámának frissítésekor: " . $stmt->error);
            }

            $stmt->close();
            $conn->commit();

            header("Location: ../student/courses.php?success=5");
            exit();

        /* ============================================================
           🔹 Hibakezelés
        ============================================================ */
        } catch (Exception $e) {
            $conn->rollback();

            $error_message = urlencode("Hiba történt: " . $e->getMessage());
            header("Location: ../student/courses.php?error={$error_message}");
            exit();
        }
    }
    /* ============================================================================
       🔹 TANÁR – dolgozat létrehozása vagy szerkesztése
    ============================================================================ */
    if ($role === 'tanar' && $_POST['action'] === 'teacher_save_assignment') {

        try {
            $conn->begin_transaction();

            /* ============================================================
               🔹 Adatok inicializálása
            ============================================================ */
            $assignment_id  = $_POST['assignment_id'] ?? null;
            $title = trim($_POST['title']);
            $description = trim($_POST['description'] ?? "");
            $available_from = $_POST['available_from'] ?? null;
            $due_date = $_POST['due_date'] ?? null;
            $max_attempts = (int)($_POST['max_attempts'] ?? 1);
            $offering_id = $_POST['offering_id'] ?? null;
            $questions = $_POST['questions'] ?? [];

            if (empty($title) || empty($offering_id)) {
                throw new Exception('Hiányzó adatok a mentéshez.');
            }

            /* ============================================================
               🔹 Meglévő dolgozat módosítás
            ============================================================ */
            if ($assignment_id) {
                // Meglévő dolgozat zerkesztése
                $update_assignment_sql = "
                UPDATE assignments 
                SET title = ?, description = ?, available_from = ?, due_date = ?
                WHERE id = ?";

                $stmt = $conn->prepare($update_assignment_sql);
                $stmt->bind_param("ssssi", $title, $description, $available_from, $due_date, $assignment_id);
                if (!$stmt->execute()) {
                    throw new Exception("Hiba a dolgozat adatainak szerkesztésekor: " . $stmt->error);
                }
                $stmt->close();

                // Régi kérdések legyűjtése
                $old_question_sql = "
                SELECT 
                    id 
                FROM assignment_questions 
                WHERE assignment_id = ?";

                $stmt = $conn->prepare($old_question_sql);
                $stmt->bind_param("i", $assignment_id);
                $stmt->execute();
                $result = $stmt->get_result();

                $old_q_ids = [];
                while ($r = $result->fetch_assoc()) {
                    $old_q_ids[] = $r['id'];
                }
                $stmt->close();

                if (!empty($old_q_ids)) {
                    $in = implode(',', array_fill(0, count($old_q_ids), '?'));
                    $types = str_repeat('i', count($old_q_ids));

                    //Régi kérdésekhez tartozó válaszok törlése
                    $delete_question_answers_sql = "
                    DELETE FROM question_answers
                    WHERE question_id IN ($in)";

                    $stmt = $conn->prepare($delete_question_answers_sql);
                    $stmt->bind_param($types, ...$old_q_ids);
                    if (!$stmt->execute()) {
                        throw new Exception("Hiba a dolgozat kérdéseihez tartozó válaszok törlésekor: " . $stmt->error);
                    }
                    $stmt->close();

                    //Régi kérdések törlése
                    $delete_assignment_questions_sql = "
                    DELETE FROM assignment_questions 
                    WHERE assignment_id = ?";

                    $stmt = $conn->prepare($delete_assignment_questions_sql);
                    $stmt->bind_param("i", $assignment_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Hiba a dolgozat kérdéseinek törlésekor: " . $stmt->error);
                    }
                    $stmt->close();
                }

            /* ============================================================
               🔹 Új dolgozat létrehozása
            ============================================================ */
            } else {
                // Új dolgozat beszúrása
                $insert_assignment_sql = "
                INSERT INTO assignments (offering_id, title, description, available_from, due_date, max_attempts)
                VALUES (?, ?, ?, ?, ?, ?)";

                $stmt = $conn->prepare($insert_assignment_sql);
                $stmt->bind_param("issssi", $offering_id, $title, $description, $available_from, $due_date, $max_attempts);
                if (!$stmt->execute()) {
                    throw new Exception("Hiba az új dolgozat létrehozásakor: " . $stmt->error);
                }
                $assignment_id = $stmt->insert_id;
                $stmt->close();
            }

            /* ============================================================
               🔹 Új kérdések + válaszok rögzítése
            ============================================================ */
            foreach ($questions as $q) {
                $question_text = trim($q['text'] ?? "");
                $question_type = $q['type'] ?? " ";
                $score = isset($q['score']) ? (float)$q['score'] : 1.0;
                $answers = $q['answers'] ?? [];

                if (empty($question_type) ) {
                    throw new Exception('A kérdés típusa nem lehet üres!');
                }

                if (empty($question_text)) {
                    throw new Exception('A kérdés mező nem lehet üres!');
                }

                // 🔹 (Új) Kérdés beszúrása
                $insert_assignment_questions_sql = "
                INSERT INTO assignment_questions (assignment_id, question_text, question_type, score)
                VALUES (?, ?, ?, ?)";

                $stmt = $conn->prepare($insert_assignment_questions_sql );
                $stmt->bind_param("issd", $assignment_id, $question_text, $question_type, $score);
                if (!$stmt->execute()) {
                    throw new Exception("Hiba a kérdések beszúrásakor: " . $stmt->error);
                }
                $question_id = $stmt->insert_id;
                $stmt->close();

                // 🔹 Válaszok beszúrása is_correct kezeléssel
                foreach ($answers as $ans) {
                    $answer_text = trim($ans['text'] ?? '');
                    if ($answer_text === '') {
                        continue;
                    }

                    $is_correct = isset($ans['is_correct']) ? 1 : 0;

                    $insert_question_answers_sql = "
                    INSERT INTO question_answers (question_id, answer_text, is_correct)
                    VALUES (?, ?, ?)";

                    $stmt = $conn->prepare($insert_question_answers_sql);
                    $stmt->bind_param("isi", $question_id, $answer_text, $is_correct);
                    if (!$stmt->execute()) {
                        throw new Exception("Hiba a kérdésekhez tartozó válaszok beszúrásakor: " . $stmt->error);
                    }
                    $stmt->close();
                }
            }

            $conn->commit();

            header("Location: ../teacher/courses.php?success=6");
            exit();

        /* ============================================================
           🔹 Hibakezelés
        ============================================================ */
        } catch (Exception $e) {
            $conn->rollback();

            $error_message = urlencode("Hiba történt: " . $e->getMessage());
            header("Location: ../teacher/courses.php?error={$error_message}");
            exit();
        }
    }
} else {
    /* ============================================================
       🔹 Fallback (ha nem POST) -> kell ha valaki az direktbe akarja megnyitni a _post.php fájlt
    ============================================================ */
    header("Location: ../index.php?error=Fallback");
    exit;
}
