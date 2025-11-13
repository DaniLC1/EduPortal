-- users tábla:
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    eduportal_id VARCHAR(20) NOT NULL UNIQUE, -- ez lesz az egyedi azonosító
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL, -- mindig titkosított formában tároljuk!
    postal_code VARCHAR(10),
    city VARCHAR(100),
    address VARCHAR(255),
    birth_date DATE,
    mothers_name VARCHAR(100),
    role ENUM('hallgato', 'tanar', 'admin') NOT NULL DEFAULT 'hallgato',
    course_code VARCHAR(20),
	financing_type ENUM('állami', 'önköltséges') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
-- Szakok:
CREATE TABLE programs (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    szak_szam VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
	facultative_credit INT,
	freely_selectable_credit INT,
	cost DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
-- Kurzusok:
CREATE TABLE courses (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    kurzus_kod VARCHAR(20) UNIQUE NOT NULL, -- üzleti kulcs
    name VARCHAR(255) NOT NULL,
    credit INTEGER NOT NULL,
    leiras TEXT,
    tematika TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
-- Szak-Kurzus kapcsolat:
CREATE TABLE program_courses (
	id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    szak_szam VARCHAR(20) NOT NULL,
    kurzus_kod VARCHAR(20) NOT NULL,
    tipus VARCHAR(20) CHECK (tipus IN ('kotelezo', 'valaszthato')),
    FOREIGN KEY (szak_szam) REFERENCES programs(szak_szam) ON DELETE CASCADE,
    FOREIGN KEY (kurzus_kod) REFERENCES courses(kurzus_kod) ON DELETE CASCADE
);

-- Szemeszterek:
CREATE TABLE semesters (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(50) NOT NULL UNIQUE,         -- pl. '2024/25 1. félév'
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Kurzus hirdetések:
CREATE TABLE course_offerings (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    
    kurzus_kod VARCHAR(20) NOT NULL,                    -- hivatkozás a courses-ra
    semester_id INTEGER,                                -- NULL is lehet
    teacher_id VARCHAR(20) NOT NULL,                    -- hivatkozás a users-re
    
    course_type ENUM('eloadas', 'gyakorlat') DEFAULT 'eloadas',-- kurzus típusa
    day_of_week ENUM('H', 'K', 'Sz', 'Cs', 'P', 'Szo', 'V'),
    start_time TIME,
    room VARCHAR(50),
    end_date DATETIME,  
    max_students INTEGER,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (kurzus_kod) REFERENCES courses(kurzus_kod),
    FOREIGN KEY (semester_id) REFERENCES semesters(id),
    FOREIGN KEY (teacher_id) REFERENCES users(eduportal_id)
);


-- NOTIFICATIONS Tábla:
CREATE TABLE notifications (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  message TEXT NOT NULL,
  course_code VARCHAR(100),
  course_offering_id INT,
  semester VARCHAR(20) NOT NULL,
  noti_type ENUM('hirdetmeny', 'forum', 'szamonkeres') NOT NULL,
  users_eduportal_ID VARCHAR(20) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (users_eduportal_ID) REFERENCES users(eduportal_id),
  FOREIGN KEY (course_offering_id) REFERENCES course_offerings(id)
);
-- NOTIFICATIONS_READS Tábla:
CREATE TABLE notification_reads (
  users_eduportal_ID VARCHAR(20) NOT NULL,
  notification_id INT NOT NULL,
  read_at DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (users_eduportal_ID, notification_id),
  FOREIGN KEY (users_eduportal_ID) REFERENCES users(eduportal_id),
  FOREIGN KEY (notification_id) REFERENCES notifications(id)
);
/*
-- Tábla módosítások:
-- Sor hozzáadás:
ALTER TABLE notifications ADD COLUMN eduportal_id VARCHAR(20);
ALTER TABLE notification_reads ADD COLUMN eduportal_id VARCHAR(20);

-- Ezután az idegen kulcsokat is módosíthatod:
ALTER TABLE notifications ADD CONSTRAINT fk_notifications_users FOREIGN KEY (eduportal_id) REFERENCES users(eduportal_id);
ALTER TABLE notification_reads ADD CONSTRAINT fk_notification_reads_users FOREIGN KEY (eduportal_id) REFERENCES users(eduportal_id);
*/

-- Kurzusra jelentkezettek:
CREATE TABLE enrollments (
    users_eduportal_ID VARCHAR(20) NOT NULL,
    offering_id INTEGER NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    status VARCHAR(20) CHECK (status IN ('enrolled', 'completed', 'failed')),
    grade VARCHAR(10),                          -- lehet A, B, C, vagy % vagy 5-ös skála
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (users_eduportal_ID, offering_id),
    FOREIGN KEY (users_eduportal_ID) REFERENCES users(eduportal_id) ON DELETE CASCADE,
    FOREIGN KEY (offering_id) REFERENCES course_offerings(id) ON DELETE CASCADE
);

-- Tanár-kurzus kapcsolat (tanárok adott kurzushoz rendelése)
CREATE TABLE teacher_courses (
	id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    teacher_id VARCHAR(20) NOT NULL,
    kurzus_kod VARCHAR(20) NOT NULL,
    FOREIGN KEY (teacher_id) REFERENCES users(eduportal_id),
    FOREIGN KEY (kurzus_kod) REFERENCES courses(kurzus_kod)
);

-- Dolgozatok:
CREATE TABLE assignments (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    offering_id INTEGER NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    available_from DATETIME,              -- mikortól lehet kitölteni
    due_date DATETIME,                    -- határidő
    max_attempts INTEGER DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (offering_id) REFERENCES course_offerings(id)
);

-- Dolgozat kérdések:
CREATE TABLE assignment_questions (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    assignment_id INTEGER NOT NULL,
    question_text TEXT NOT NULL,
    question_type VARCHAR(20) NOT NULL CHECK (question_type IN ('true_false', 'multiple_choice')),
    score INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE
);

-- Válaszokpciók egy kérdéshez:
CREATE TABLE question_answers (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    question_id INTEGER NOT NULL,
    answer_text TEXT NOT NULL,
    is_correct BOOLEAN NOT NULL DEFAULT FALSE,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES assignment_questions(id) ON DELETE CASCADE
);

-- beadott dolgozat (diákoknak kiértékeléshez asszem):
CREATE TABLE assignment_submissions (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    assignment_id INTEGER NOT NULL,
    users_eduportal_ID VARCHAR(20) NOT NULL,
    submitted_at DATETIME,
    score DECIMAL,                       
    graded_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id),
    FOREIGN KEY (users_eduportal_ID) REFERENCES users(eduportal_id)
);

-- Kérdésenkénti válasz a dolgozatban:
CREATE TABLE submission_answers (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    submission_id INTEGER NOT NULL,
    question_id INTEGER NOT NULL,
    selected_answer_id INTEGER,              -- választott opció
    FOREIGN KEY (submission_id) REFERENCES assignment_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES assignment_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (selected_answer_id) REFERENCES question_answers(id) ON DELETE CASCADE
);

-- Pénzügyi infó:
CREATE TABLE student_financing (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    users_eduportal_ID VARCHAR(20) NOT NULL,
    semester_id int NOT NULL,
    amount_due DECIMAL(10,2),
    due_date DATE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (semester_id) REFERENCES semesters(id),
    FOREIGN KEY (users_eduportal_ID) REFERENCES users(eduportal_id)
);

-- Befizetés információk:
CREATE TABLE payment_installments (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    financing_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (financing_id) REFERENCES student_financing(id)
);

-- Kérelmek:
CREATE TABLE request_templates (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
	to_who TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Beadott kérelmek:
CREATE TABLE student_requests (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    users_eduportal_ID VARCHAR(20) NOT NULL,
    template_id INT NOT NULL,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('beküldve', 'elfogadva', 'elutasítva', 'módosításra visszaküldve') DEFAULT 'beküldve',
    reviewed_at DATETIME,
    admin_comment TEXT,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (users_eduportal_ID) REFERENCES users(eduportal_id),
    FOREIGN KEY (template_id) REFERENCES request_templates(id)
);

-- Kérvény kitölthető részei:
CREATE TABLE request_template_fields (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    label VARCHAR(255) NOT NULL,
    field_type ENUM('text', 'textarea', 'date', 'number') NOT NULL,
    is_required BOOLEAN DEFAULT TRUE,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (template_id) REFERENCES request_templates(id)
);

-- A hallgató által kitöltött mezők tartalma mezőnként, valamint admini módosítási javaslat:
CREATE TABLE student_request_field_values (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    field_id INT NOT NULL,
    field_value TEXT,
    admin_suggestion TEXT,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (request_id) REFERENCES student_requests(id),
    FOREIGN KEY (field_id) REFERENCES request_template_fields(id)
);

