<?php
require_once __DIR__ . '/bootstrap.php';
$teacher_id = page_role('teacher');
require_once __DIR__ . '/database.php';
function student_query(PDO $pdo, string $sql, array $params = []): PDOStatement {
    $stmt = $pdo->prepare($sql); $stmt->execute($params); return $stmt;
}
$showInactive = isset($_GET['show_inactive']);
$showAddStudentForm = isset($_GET['add_student_form']);
$showEditStudentForm = isset($_GET['edit_student_form']);
$selectedStudentId = isset($_GET['student_id']) ? input_id($_GET, 'student_id') : null;
$selectedConsultationId = isset($_GET['consultation_id']) ? input_id($_GET, 'consultation_id') : null;
$showFormConsultation = isset($_GET['add_consultation']) && $selectedStudentId;
$showHomepage = !$_GET && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
$studentAddError = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_post();
    if (isset($_POST['deactivate_id']) || isset($_POST['activate_id'])) {
        $active = isset($_POST['activate_id']) ? 1 : 0;
        $id = input_id($_POST, $active ? 'activate_id' : 'deactivate_id');
        require_student_access($pdo, $id);
        student_query($pdo, 'UPDATE students SET active = ? WHERE id = ?', [$active, $id]);
        header('Location: /studevid/students.php'); exit;
    } elseif (isset($_POST['increment_semester'])) {
        $id = input_id($_POST, 'student_id'); require_student_access($pdo, $id);
        student_query($pdo, 'UPDATE students SET semestr=LEAST(COALESCE(semestr,0)+1,100) WHERE id=?', [$id]);
        header('Location: /studevid/students.php?student_id=' . $id); exit;
    } elseif (isset($_POST['add_student']) || isset($_POST['edit_student'])) {
        $edit = isset($_POST['edit_student']);
        $id = $edit ? input_id($_POST, 'student_id') : null;
        if ($edit) require_student_access($pdo, $id);
        $values = [];
        foreach (['name','uco','semestr','uni','prace','email','degree'] as $key) $values[$key] = input_text($_POST, $key);
        extract($values, EXTR_SKIP);
        $semester = filter_var($values['semestr'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        if ($values['name'] === '' || $values['uco'] === '' || $semester === false ||
            !filter_var($values['email'], FILTER_VALIDATE_EMAIL) ||
            !in_array($values['uni'], ['muni','jamu','upol','jiná'], true) ||
            !in_array($values['degree'], ['bc','mgr','phd','jiná'], true)) {
            $studentAddError = 'Please fill in name, Student ID, platný e-mail, školu, studium a nezáporný počet semestrů.';
            $showEditStudentForm = $edit; $showAddStudentForm = !$edit; $selectedStudentId = $id;
            http_response_code(422);
        } else {
            $pdo->beginTransaction();
            try {
                if ($edit) {
                    student_query($pdo, 'UPDATE students SET name=?, uco=?, semestr=?, uni=?, prace=?, email=?, degree=? WHERE id=?', [...array_values($values), $id]);
                } else {
                    student_query($pdo, 'INSERT INTO students (name,uco,semestr,uni,prace,email,degree,active) VALUES (?,?,?,?,?,?,?,1)', array_values($values));
                    $id = (int)$pdo->lastInsertId();
                    student_query($pdo, 'INSERT INTO student_teacher (student_id,teacher_id) VALUES (?,?)', [$id,$teacher_id]);
                }
                $pdo->commit();
            } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
            header('Location: /studevid/students.php?student_id=' . $id); exit;
        }
    } elseif (isset($_POST['add_consultation'])) {
        $id = input_id($_POST, 'student_id'); require_student_access($pdo, $id);
        $content = input_text($_POST, 'content'); $tasks = input_text($_POST, 'tasks');
        if ($content === '' || $tasks === '') app_error(422, 'Please fill in content consultations a úkoly.');
        student_query($pdo, 'INSERT INTO consultations (student_id,content,tasks,date) VALUES (?,?,?,NOW())', [$id,$content,$tasks]);
        header('Location: /studevid/students.php?student_id=' . $id); exit;
    } else { app_error(400, 'Unknown action.'); }
}
$selectedStudentName = null; $selectedStudentActive = 0; $universityLabel = ''; $degreeLabel = '';
if ($selectedStudentId) {
    require_student_access($pdo, $selectedStudentId);
    $selectedStudent = student_query($pdo, 'SELECT * FROM students WHERE id=?', [$selectedStudentId])->fetch();
    if (!$selectedStudent) app_error(404, 'Student was not found.');
    if ($studentAddError && $showEditStudentForm) $selectedStudent = array_replace($selectedStudent, $values);
    foreach (['Name'=>'name','Uni'=>'uni','Active'=>'active','UCO'=>'uco','Prace'=>'prace','Email'=>'email','Degree'=>'degree','Semestr'=>'semestr'] as $suffix=>$column) {
        ${'selectedStudent'.$suffix} = $selectedStudent[$column];
    }
    $universityLabel = ['muni'=>'Masarykova university','jamu'=>'Janáčkova akademie múzických umění','upol'=>'University Palackého v Olomouci','jiná'=>'Jiná university'][$selectedStudentUni] ?? '';
    $degreeLabel = ['phd'=>'PhD','bc'=>'Bc','mgr'=>'Mgr','jiná'=>'Jiné'][$selectedStudentDegree] ?? '';
}
$listSql = 'SELECT s.id,s.name,s.uco,s.email,s.degree,s.uni,MAX(c.date) AS last_consultation FROM students s LEFT JOIN consultations c ON c.student_id=s.id WHERE s.active=? AND EXISTS (SELECT 1 FROM student_teacher st WHERE st.student_id=s.id AND st.teacher_id=?) GROUP BY s.id,s.name,s.uco,s.email,s.degree,s.uni ORDER BY s.name';
$students = student_query($pdo, $listSql, [$showInactive ? 0 : 1,$teacher_id])->fetchAll();
$activeStudents = $showInactive ? student_query($pdo, $listSql, [1,$teacher_id])->fetchAll() : $students;
$studentsToContact = []; $studentsToContactUnbooked = []; $studentsToContactBooked = []; $studentsEmails = []; $studentsEmailsUnbooked = []; $studentsEmailsBooked = []; $oneMonthAgo = new DateTimeImmutable('-1 month');
$normalizeBookingName = static function ($name): string {
    $name = trim((string)$name);
    $ascii = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) : $name;
    return strtolower(trim((string)$ascii));
};
$bookedEmails = []; $bookedNames = [];
try {
    try {
        $bookingWhere = "date >= CURRENT_DATE AND COALESCE(active,1)=1";
        $pdo->query("SELECT 1 FROM scheduler WHERE COALESCE(active,1)=1 LIMIT 1");
    } catch (Throwable $schemaError) {
        // Starší databáze nemají sloupec active; booking jsou v ní implicitně active.
        $bookingWhere = "date >= CURRENT_DATE";
    }
    $bookedEmails = array_fill_keys(array_filter(array_map(static fn($email) => strtolower(trim((string)$email)), $pdo->query("SELECT DISTINCT LOWER(TRIM(email)) FROM scheduler WHERE {$bookingWhere} AND email IS NOT NULL AND TRIM(email) <> ''")->fetchAll(PDO::FETCH_COLUMN))), true);
    $bookedNames = array_fill_keys(array_filter(array_map($normalizeBookingName, $pdo->query("SELECT DISTINCT name FROM scheduler WHERE {$bookingWhere} AND name IS NOT NULL AND TRIM(name) <> ''")->fetchAll(PDO::FETCH_COLUMN))), true);
} catch (Throwable $e) { error_log('Students scheduler booking lookup: ' . $e->getMessage()); }
foreach ($activeStudents as $student) {
    if (empty($student['last_consultation']) || new DateTimeImmutable($student['last_consultation']) < $oneMonthAgo) {
        $email = (string)($student['email'] ?? '');
        $studentsToContact[] = $student['name']; $studentsEmails[] = $email;
        $isBooked = isset($bookedEmails[strtolower(trim($email))]) || isset($bookedNames[$normalizeBookingName($student['name'])]);
        if (!$isBooked) { $studentsToContactUnbooked[] = $student['name']; $studentsEmailsUnbooked[] = $email; }
        else { $studentsToContactBooked[] = $student['name']; $studentsEmailsBooked[] = $email; }
    }
}
$studentsToContactString = implode(', ', $studentsToContact); $studentsEmailsString = implode(',', $studentsEmails);
$studentsToContactUnbookedString = implode(', ', $studentsToContactUnbooked);
$studentsToContactBookedString = implode(', ', $studentsToContactBooked);
$studentsEmailsUnbookedString = implode(',', array_filter($studentsEmailsUnbooked));
$studentsEmailsBookedString = implode(',', array_filter($studentsEmailsBooked));
$selectedDiaryEntry = null; $selectedPublications = [];
if ($selectedStudentId && (($selectedStudent['degree'] ?? '') === 'phd')) {
    try {
        $selectedDiaryEntry = student_query($pdo, 'SELECT * FROM diary_entries WHERE student_id=? AND visible_to_teacher=1 ORDER BY week_start DESC,id DESC LIMIT 1', [$selectedStudentId])->fetch() ?: null;
    } catch (Throwable $e) { error_log('Students diary detail: ' . $e->getMessage()); }
    try { $selectedPublications = student_query($pdo, 'SELECT id,title,type,status,created_at,updated_at FROM publications WHERE student_id=? ORDER BY created_at DESC,id DESC', [$selectedStudentId])->fetchAll(); } catch (Throwable $e) { error_log('Students publication detail: ' . $e->getMessage()); }
}
$consultations = $selectedStudentId ? student_query($pdo, 'SELECT id,date,tasks FROM consultations WHERE student_id=? ORDER BY date DESC', [$selectedStudentId])->fetchAll() : [];
$consultationDetail = null;
if ($selectedConsultationId) {
    if (!$selectedStudentId) app_error(400, 'Vyberte student.');
    $consultationDetail = student_query($pdo, 'SELECT date,content,tasks FROM consultations WHERE id=? AND student_id=?', [$selectedConsultationId,$selectedStudentId])->fetch();
    if (!$consultationDetail) app_error(404, 'Consultations nebyla nalezena.');
}
$consultationStatYear = []; $adhocConsultationStatYear = []; $adhocStudentsSummary = []; $schedulerData = []; $taskMessages = []; $messagesUnavailable = false; $consultationRanking = [];
if ($showHomepage) {
    $consultationStatYear = student_query($pdo, 'SELECT YEAR(date) AS year, MONTH(date) AS month, COUNT(*) AS consultation_count FROM consultations c WHERE EXISTS (SELECT 1 FROM student_teacher st WHERE st.student_id=c.student_id AND st.teacher_id=?) GROUP BY YEAR(date),MONTH(date) ORDER BY YEAR(date),MONTH(date)', [$teacher_id])->fetchAll();
    $consultationRanking = student_query($pdo, 'SELECT s.id,s.name,COUNT(c.id) AS consultation_count,MIN(c.date) AS first_consultation,ROUND(COUNT(c.id) / GREATEST(COALESCE(DATEDIFF(CURRENT_DATE,DATE(MIN(c.date))) / 30.4375,0),1),2) AS consultation_rate FROM students s LEFT JOIN consultations c ON c.student_id=s.id WHERE s.active=1 AND EXISTS (SELECT 1 FROM student_teacher st WHERE st.student_id=s.id AND st.teacher_id=?) GROUP BY s.id,s.name ORDER BY consultation_rate DESC,s.name', [$teacher_id])->fetchAll();
    try {
        $adhocConsultationStatYear = $pdo->query('SELECT YEAR(consulted_at) AS year,MONTH(consulted_at) AS month,COUNT(*) AS consultation_count FROM adhoc_consultations GROUP BY YEAR(consulted_at),MONTH(consulted_at) ORDER BY YEAR(consulted_at),MONTH(consulted_at)')->fetchAll();
        $adhocStudentsSummary = $pdo->query('SELECT s.id,s.name,s.university,s.status,COUNT(c.id) AS consultation_count,MAX(c.consulted_at) AS last_consultation FROM adhoc_students s LEFT JOIN adhoc_consultations c ON c.student_id=s.id WHERE s.status IN ("unresolved","ongoing") GROUP BY s.id,s.name,s.university,s.status ORDER BY FIELD(s.status,"unresolved","ongoing"),last_consultation DESC,s.name LIMIT 8')->fetchAll();
    } catch (Throwable $e) { error_log('Students ad hoc consultations: '.$e->getMessage()); }
    // These two integrations are shared across teachers in the existing data model.
    try {
        $schedulerData = $pdo->query('SELECT * FROM scheduler WHERE date >= CURRENT_DATE AND COALESCE(active,1)=1 ORDER BY date,time')->fetchAll();
    } catch (Throwable $schemaError) {
        $schedulerData = $pdo->query('SELECT * FROM scheduler WHERE date >= CURRENT_DATE ORDER BY date,time')->fetchAll();
    }
    try {
        $taskMessages = $pdo->query("SELECT m.id,m.created_at,COALESCE(s.surname,'') AS surname,m.form_slug,m.message FROM `e-learning-messages` m LEFT JOIN `e-learning-students` s ON m.student_id=s.id ORDER BY m.created_at DESC, m.id DESC LIMIT 2")->fetchAll();
    } catch (PDOException $e) { $messagesUnavailable = true; error_log('Students e-learning: ' . $e->getMessage()); }
}
