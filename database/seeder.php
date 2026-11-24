<?php
declare(strict_types=1);

/**
 * Rebuilds the database from schema.sql and fills it with demo data.
 * All dates are relative to "now" so the demo never looks stale.
 */
function seed_database(PDO $pdo, string $projectRoot): void
{
    seed_reset_schema($pdo, $projectRoot . '/database/schema.sql');
    seed_clear_uploads($projectRoot);

    $tz = new DateTimeZone(getenv('APP_TIMEZONE') ?: 'UTC');
    $now = new DateTimeImmutable('now', $tz);
    $at = static fn (string $modify): string => $now->modify($modify)->format('Y-m-d H:i:s');

    $files = new SeedFiles($projectRoot);
    $hashes = [
        'instructor' => password_hash('Instructor123!', PASSWORD_DEFAULT),
        'student' => password_hash('Student123!', PASSWORD_DEFAULT),
    ];

    // ---------- Users ----------
    $users = [
        ['Dr. Ayesha Khan', 'instructor@edupulse.test', 'instructor', 'Software engineering lecturer with ten years of teaching experience.'],
        ['Prof. Bilal Ahmed', 'instructor2@edupulse.test', 'instructor', 'Algorithms researcher and competitive programming coach.'],
        ['Ali Raza', 'student@edupulse.test', 'student', 'Third-semester BS Computer Science student.'],
        ['Sara Malik', 'student2@edupulse.test', 'student', 'Interested in databases and backend development.'],
        ['Hamza Tariq', 'hamza.tariq@edupulse.test', 'student', null],
        ['Fatima Noor', 'fatima.noor@edupulse.test', 'student', null],
        ['Usman Javed', 'usman.javed@edupulse.test', 'student', null],
        ['Zainab Hussain', 'zainab.hussain@edupulse.test', 'student', null],
        ['Omar Farooq', 'omar.farooq@edupulse.test', 'student', null],
        ['Hira Siddiqui', 'hira.siddiqui@edupulse.test', 'student', null],
    ];
    $userIds = [];
    $insertUser = $pdo->prepare('INSERT INTO users (name, email, password, role, bio) VALUES (?, ?, ?, ?, ?)');
    foreach ($users as [$name, $email, $role, $bio]) {
        $insertUser->execute([$name, strtolower($email), $hashes[$role], $role, $bio]);
        $userIds[$email] = (int) $pdo->lastInsertId();
    }
    $u = static fn (string $email): int => $userIds[$email];

    // ---------- Courses ----------
    $courses = [
        'CS101' => ['Introduction to Programming', 'Programming', 'instructor@edupulse.test', 40,
            'Learn to think like a programmer: variables, control flow, functions and debugging, taught through small weekly projects.'],
        'DB201' => ['Database Systems', 'Database', 'instructor@edupulse.test', 35,
            'Design relational databases, write SQL with confidence and understand normalization, indexing and transactions.'],
        'WD301' => ['Modern Web Development', 'Web Development', 'instructor@edupulse.test', 30,
            'Build responsive, accessible websites with semantic HTML, modern CSS and JavaScript, then connect them to a PHP backend.'],
        'MD401' => ['Mobile App Development', 'Mobile Development', 'instructor@edupulse.test', 25,
            'Design and build cross-platform mobile apps, from layouts and navigation to storing data and publishing.'],
        'CS250' => ['Data Structures & Algorithms', 'Programming', 'instructor2@edupulse.test', 40,
            'Arrays, linked lists, trees, graphs and the algorithms that use them, with a focus on analysing complexity.'],
    ];
    $courseIds = [];
    $insertCourse = $pdo->prepare(
        'INSERT INTO courses (title, course_code, category, description, instructor_id, enrollment_limit, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $daysAgo = 60;
    foreach ($courses as $code => [$title, $category, $owner, $limit, $description]) {
        $insertCourse->execute([$title, $code, $category, $description, $u($owner), $limit, $at("-{$daysAgo} days")]);
        $courseIds[$code] = (int) $pdo->lastInsertId();
        $daysAgo -= 5;
    }
    $c = static fn (string $code): int => $courseIds[$code];

    // ---------- Enrollments ----------
    $enrollments = [
        'CS101' => ['student@', 'student2@', 'hamza.tariq@', 'fatima.noor@', 'usman.javed@', 'zainab.hussain@'],
        'DB201' => ['student@', 'student2@', 'omar.farooq@', 'hira.siddiqui@', 'fatima.noor@'],
        'WD301' => ['student@', 'hamza.tariq@', 'usman.javed@', 'hira.siddiqui@'],
        'MD401' => ['zainab.hussain@', 'omar.farooq@'],
        'CS250' => ['student2@', 'hamza.tariq@', 'fatima.noor@'],
    ];
    $insertEnrollment = $pdo->prepare('INSERT INTO enrollments (user_id, course_id, enrolled_at) VALUES (?, ?, ?)');
    foreach ($enrollments as $code => $emails) {
        foreach ($emails as $i => $prefix) {
            $insertEnrollment->execute([$u($prefix . 'edupulse.test'), $c($code), $at('-' . (40 - $i) . ' days')]);
        }
    }

    // ---------- Modules & materials ----------
    $modules = [
        'CS101' => [
            ['Getting Started', [['Week 1 – Course Overview.pdf', 'lecture-notes.pdf'], ['Setup Guide & Reading List.txt', 'reading-list.txt']]],
            ['Control Flow', [['Week 2 – Conditions and Loops.pdf', 'lecture-notes.pdf'], ['Lab 2 – Loop Exercises.pdf', 'lab-sheet.pdf']]],
            ['Functions', [['Week 3 – Writing Functions.pdf', 'lecture-notes.pdf']]],
        ],
        'DB201' => [
            ['Relational Model', [['Lecture 1 – Tables, Keys and Relations.pdf', 'lecture-notes.pdf']]],
            ['SQL Fundamentals', [['Lecture 2 – SELECT, JOIN and GROUP BY.pdf', 'lecture-notes.pdf'], ['SQL Lab Sheet.pdf', 'lab-sheet.pdf']]],
            ['Normalization', [['Lecture 3 – 1NF to BCNF.pdf', 'lecture-notes.pdf']]],
        ],
        'WD301' => [
            ['Semantic HTML', [['Module 1 – Document Structure.pdf', 'lecture-notes.pdf']]],
            ['Responsive CSS', [['Module 2 – Flexbox and Grid.pdf', 'lecture-notes.pdf'], ['Recommended Reading.txt', 'reading-list.txt']]],
            ['JavaScript and the DOM', [['Module 3 – Events and DOM APIs.pdf', 'lecture-notes.pdf']]],
        ],
        'MD401' => [
            ['Mobile UI Basics', [['Unit 1 – Layouts and Navigation.pdf', 'lecture-notes.pdf']]],
            ['Local Storage', [['Unit 2 – Persisting Data.pdf', 'lecture-notes.pdf']]],
        ],
        'CS250' => [
            ['Complexity Analysis', [['Lecture 1 – Big-O Notation.pdf', 'lecture-notes.pdf']]],
            ['Linear Structures', [['Lecture 2 – Stacks, Queues and Lists.pdf', 'lecture-notes.pdf'], ['Practice Problems.pdf', 'lab-sheet.pdf']]],
            ['Trees', [['Lecture 3 – Binary Search Trees.pdf', 'lecture-notes.pdf']]],
        ],
    ];
    $insertModule = $pdo->prepare('INSERT INTO modules (course_id, title, module_order) VALUES (?, ?, ?)');
    $insertMaterial = $pdo->prepare('INSERT INTO materials (module_id, file_name, file_path, file_type) VALUES (?, ?, ?, ?)');
    foreach ($modules as $code => $list) {
        foreach ($list as $order => [$title, $materials]) {
            $insertModule->execute([$c($code), $title, $order + 1]);
            $moduleId = (int) $pdo->lastInsertId();
            foreach ($materials as [$displayName, $source]) {
                [$key, $mime] = $files->store($source, 'materials');
                $insertMaterial->execute([$moduleId, $displayName, $key, $mime]);
            }
        }
    }

    // ---------- Assignments ----------
    // [course, title, description, due (relative), max points, has brief]
    $assignments = [
        'cs101-a1' => ['CS101', 'Variables and Control Flow', "Write a program that reads a list of marks and prints the grade for each.\nUse if/else and at least one loop.", '-20 days', 20, true],
        'cs101-a2' => ['CS101', 'Functions Lab', "Refactor last week's program into small, well-named functions.\nInclude a short README explaining each function.", '-6 days', 20, false],
        'cs101-a3' => ['CS101', 'Loops Challenge', "Solve the five loop puzzles in the attached brief.\nShow your output for each one.", '+5 days', 25, true],
        'db201-b1' => ['DB201', 'ER Diagram for a Library', "Draw an ER diagram for a small library system with members, books and loans.\nState every key and cardinality.", '-10 days', 50, true],
        'db201-b2' => ['DB201', 'SQL Joins Practice', "Answer the ten query questions using JOINs and GROUP BY.\nSubmit your SQL and the result of each query.", '-2 days', 30, false],
        'db201-b3' => ['DB201', 'Normalization Worksheet', "Normalize the given order table to 3NF and explain each step.", '+9 days', 40, false],
        'wd301-c1' => ['WD301', 'Responsive Landing Page', "Build a one-page site that works from 320px to 1440px.\nUse semantic HTML and CSS Grid or Flexbox.", '-3 days', 100, true],
        'wd301-c2' => ['WD301', 'JavaScript DOM Project', "Add an interactive to-do list to your landing page using only vanilla JavaScript.", '+12 days', 100, false],
        'md401-d1' => ['MD401', 'Wireframe a Mobile App', "Wireframe five screens for a campus events app.", '+7 days', 50, false],
        'cs250-e1' => ['CS250', 'Big-O Problem Set', "Analyse the time complexity of the eight code fragments in the brief.", '-8 days', 40, true],
        'cs250-e2' => ['CS250', 'Implement a Stack and Queue', "Implement both structures with arrays and with linked lists, then compare them.", '+6 days', 60, false],
    ];
    $assignmentIds = [];
    $insertAssignment = $pdo->prepare(
        'INSERT INTO assignments (course_id, title, description, due_date, max_points, file_path, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($assignments as $ref => [$code, $title, $description, $due, $max, $hasBrief]) {
        $brief = $hasBrief ? $files->store('assignment-brief.pdf', 'assignments')[0] : null;
        $insertAssignment->execute([$c($code), $title, $description, $at($due), $max, $brief, $at('-25 days')]);
        $assignmentIds[$ref] = (int) $pdo->lastInsertId();
    }

    // ---------- Submissions ----------
    // [assignment, student, submitted (relative), grade|null, feedback|null]
    $submissions = [
        // Demo student: graded with feedback, late, submitted-ungraded; db201-b2 stays overdue and unsubmitted.
        ['cs101-a1', 'student@', '-21 days', 18, 'Clean, readable code. Watch the edge case when a mark is exactly 50.'],
        ['cs101-a2', 'student@', '-5 days', null, null],
        ['db201-b1', 'student@', '-11 days', 45, 'Excellent diagram. The loan entity needs a due date attribute.'],
        ['wd301-c1', 'student@', '-4 days', null, null],
        // Other students.
        ['cs101-a1', 'student2@', '-20 days -2 hours', 20, 'Perfect score – great structure and comments.'],
        ['cs101-a1', 'hamza.tariq@', '-22 days', 14, 'Works, but please split the logic into smaller steps.'],
        ['cs101-a1', 'fatima.noor@', '-21 days', 17, 'Good work. Add input validation next time.'],
        ['cs101-a2', 'student2@', '-7 days', 19, 'Well-named functions and a helpful README.'],
        ['cs101-a2', 'usman.javed@', '-6 days -3 hours', null, null],
        ['db201-b1', 'student2@', '-12 days', 48, 'Very thorough.'],
        ['db201-b1', 'omar.farooq@', '-10 days -1 hour', 38, 'Some cardinalities are missing.'],
        ['db201-b2', 'student2@', '-3 days', null, null],
        ['db201-b2', 'hira.siddiqui@', '-2 days -4 hours', null, null],
        ['wd301-c1', 'hamza.tariq@', '-3 days -5 hours', 82, 'Nice layout. Check colour contrast on the buttons.'],
        ['wd301-c1', 'hira.siddiqui@', '-2 days', null, null],
        ['cs250-e1', 'student2@', '-9 days', 36, 'Correct except fragment 7.'],
        ['cs250-e1', 'fatima.noor@', '-8 days -6 hours', null, null],
    ];
    $insertSubmission = $pdo->prepare(
        'INSERT INTO submissions (assignment_id, student_id, file_path, submitted_at, grade, feedback, graded_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($submissions as [$ref, $prefix, $submitted, $grade, $feedback]) {
        [$key] = $files->store('student-submission.pdf', 'submissions');
        $insertSubmission->execute([
            $assignmentIds[$ref],
            $u($prefix . 'edupulse.test'),
            $key,
            $at($submitted),
            $grade,
            $feedback,
            $grade === null ? null : $at('-1 day'),
        ]);
    }

    // ---------- Announcements ----------
    $announcements = [
        'CS101' => [['Welcome to CS101! Please read the setup guide in Module 1 before our first lab.', '-35 days'],
                    ['Lab this Thursday is moved to Room 204. Bring your laptop.', '-4 days']],
        'DB201' => [['Grades for the ER diagram assignment are now available.', '-1 day'],
                    ['Reminder: the SQL Joins Practice was due on Sunday. Late submissions lose 10% per day.', '-1 day -2 hours']],
        'WD301' => [['Great landing pages so far! Feedback will be posted by the end of the week.', '-2 days']],
        'MD401' => [['Wireframing tools are listed in Unit 1. Any tool is fine as long as you export a PDF.', '-6 days']],
        'CS250' => [['Office hours are on Wednesdays from 2 to 4 pm.', '-14 days'],
                    ['The Big-O problem set has been graded.', '-1 day']],
    ];
    $insertAnnouncement = $pdo->prepare('INSERT INTO announcements (course_id, instructor_id, message, created_at) VALUES (?, ?, ?, ?)');
    foreach ($announcements as $code => $list) {
        $owner = $courses[$code][2];
        foreach ($list as [$message, $when]) {
            $insertAnnouncement->execute([$c($code), $u($owner), $message, $at($when)]);
        }
    }
}

function seed_reset_schema(PDO $pdo, string $schemaFile): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($schemaFile));
    foreach (preg_split('/;\s*$/m', $sql) as $statement) {
        if (trim($statement) !== '') {
            $pdo->exec($statement);
        }
    }
}

function seed_clear_uploads(string $projectRoot): void
{
    $dirs = [
        $projectRoot . '/storage/uploads/materials',
        $projectRoot . '/storage/uploads/assignments',
        $projectRoot . '/storage/uploads/submissions',
        $projectRoot . '/public/assets/uploads/profiles',
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file) && basename($file) !== '.gitkeep') {
                unlink($file);
            }
        }
        if (!is_file($dir . '/.gitkeep')) {
            touch($dir . '/.gitkeep');
        }
    }
}

/**
 * Copies sample files into private storage under random names, like real uploads.
 */
final class SeedFiles
{
    public function __construct(private string $projectRoot)
    {
    }

    /** @return array{0: string, 1: string} storage key and MIME type */
    public function store(string $source, string $folder): array
    {
        $from = $this->projectRoot . '/database/seed-files/' . $source;
        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        $key = $folder . '/' . bin2hex(random_bytes(16)) . '.' . $ext;

        copy($from, $this->projectRoot . '/storage/uploads/' . $key);

        return [$key, (string) mime_content_type($from)];
    }
}
