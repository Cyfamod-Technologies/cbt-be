<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptAnswer;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionOption;
use App\Models\Course;
use App\Models\Department;
use App\Models\Level;
use App\Models\QuestionBankItem;
use App\Models\QuestionBankItemOption;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\Semester;
use App\Models\Staff;
use App\Models\StaffCourseAssignment;
use App\Models\StudentCourseEnrollment;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSchoolSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ── School & Academic Structure ──────────────────────────────────────

        $school = School::updateOrCreate(
            ['code' => 'CYFAMOD'],
            ['name' => 'Cyfamod Demo School', 'email' => 'admin@cbt.local', 'phone' => '08000000000', 'status' => 'active'],
        );

        $session = AcademicSession::updateOrCreate(
            ['school_id' => $school->id, 'name' => '2025/2026'],
            ['is_current' => true, 'status' => 'active'],
        );

        $sem1 = Semester::updateOrCreate(
            ['school_id' => $school->id, 'session_id' => $session->id, 'name' => 'First Semester'],
            ['status' => 'active'],
        );

        $sem2 = Semester::updateOrCreate(
            ['school_id' => $school->id, 'session_id' => $session->id, 'name' => 'Second Semester'],
            ['status' => 'active'],
        );

        SchoolSetting::updateOrCreate(
            ['school_id' => $school->id],
            ['current_session_id' => $session->id, 'current_semester_id' => $sem1->id],
        );

        // ── Departments ──────────────────────────────────────────────────────

        $csc = Department::updateOrCreate(
            ['school_id' => $school->id, 'code' => 'CSC'],
            ['name' => 'Computer Science', 'status' => 'active'],
        );
        $bus = Department::updateOrCreate(
            ['school_id' => $school->id, 'code' => 'BUS'],
            ['name' => 'Business Administration', 'status' => 'active'],
        );
        $eee = Department::updateOrCreate(
            ['school_id' => $school->id, 'code' => 'EEE'],
            ['name' => 'Electrical Engineering', 'status' => 'active'],
        );

        // ── Levels ───────────────────────────────────────────────────────────

        $nd1 = Level::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'ND I'],
            ['status' => 'active'],
        );
        $nd2 = Level::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'ND II'],
            ['status' => 'active'],
        );

        foreach ([$csc, $bus, $eee] as $dept) {
            $dept->levels()->syncWithoutDetaching([
                $nd1->id => ['school_id' => $school->id],
                $nd2->id => ['school_id' => $school->id],
            ]);
        }

        // ── Courses (with credit_unit) ────────────────────────────────────────
        // [code, title, dept, level, semester, credit_unit]

        $courseDefinitions = [
            ['CSC101', 'Introduction to Computing',      $csc, $nd1, $sem1, 3],
            ['CSC102', 'Programming Fundamentals',        $csc, $nd1, $sem2, 4],
            ['CSC201', 'Data Structures and Algorithms',  $csc, $nd2, $sem1, 4],
            ['CSC202', 'Database Management Systems',     $csc, $nd2, $sem2, 3],
            ['BUS101', 'Principles of Management',        $bus, $nd1, $sem1, 3],
            ['BUS102', 'Business Communication',          $bus, $nd1, $sem2, 2],
            ['BUS201', 'Business Finance',                $bus, $nd2, $sem1, 3],
            ['EEE101', 'Circuit Theory I',                $eee, $nd1, $sem1, 4],
            ['EEE102', 'Electronics Fundamentals',        $eee, $nd1, $sem2, 3],
            ['EEE201', 'Digital Electronics',             $eee, $nd2, $sem1, 4],
        ];

        $courses = [];
        foreach ($courseDefinitions as [$code, $title, $dept, $level, $sem, $creditUnit]) {
            $courses[$code] = Course::updateOrCreate(
                [
                    'school_id'     => $school->id,
                    'code'          => $code,
                    'department_id' => $dept->id,
                    'level_id'      => $level->id,
                    'semester_id'   => $sem->id,
                ],
                ['title' => $title, 'status' => 'active', 'credit_unit' => $creditUnit],
            );
        }

        // ── Staff (10) ───────────────────────────────────────────────────────

        $staffDefinitions = [
            ['STF-002', 'Dr. Amaka Okonkwo',   'amaka.okonkwo@cbt.local',   '08031000001', $csc],
            ['STF-003', 'Mr. Chukwuemeka Eze', 'chukwuemeka.eze@cbt.local', '08031000002', $csc],
            ['STF-004', 'Mrs. Ngozi Adeyemi',  'ngozi.adeyemi@cbt.local',   '08031000003', $csc],
            ['STF-005', 'Dr. Bola Akinwale',   'bola.akinwale@cbt.local',   '08031000004', $csc],
            ['STF-006', 'Mr. Yusuf Ibrahim',   'yusuf.ibrahim@cbt.local',   '08031000005', $bus],
            ['STF-007', 'Mrs. Fatima Musa',    'fatima.musa@cbt.local',     '08031000006', $bus],
            ['STF-008', 'Mr. Tunde Fashola',   'tunde.fashola@cbt.local',   '08031000007', $bus],
            ['STF-009', 'Engr. Emeka Obi',     'emeka.obi@cbt.local',       '08031000008', $eee],
            ['STF-010', 'Dr. Kemi Adeleke',    'kemi.adeleke@cbt.local',    '08031000009', $eee],
            ['STF-011', 'Mr. Seun Alabi',      'seun.alabi@cbt.local',      '08031000010', $eee],
        ];

        $staffProfiles = [];
        foreach ($staffDefinitions as [$staffId, $fullName, $email, $phone, $dept]) {
            $staffUser = User::updateOrCreate(
                ['school_id' => $school->id, 'email' => $email],
                [
                    'name'              => $fullName,
                    'role'              => User::ROLE_STAFF,
                    'school_id'         => $school->id,
                    'password'          => Hash::make('password'),
                    'status'            => User::STATUS_ACTIVE,
                    'email_verified_at' => now(),
                    'phone'             => $phone,
                    'department_id'     => $dept->id,
                ],
            );

            $staffProfiles[$staffId] = Staff::updateOrCreate(
                ['school_id' => $school->id, 'email' => $email],
                [
                    'user_id'       => $staffUser->id,
                    'staff_id'      => $staffId,
                    'full_name'     => $fullName,
                    'phone'         => $phone,
                    'department_id' => $dept->id,
                    'status'        => 'active',
                ],
            );
        }

        // ── Students (50) ────────────────────────────────────────────────────
        // CSC ND I: 20 (101–120) | CSC ND II: 10 (201–210)
        // BUS ND I: 12 (101–112) | EEE ND I:   8 (101–108)

        $studentGroups = [
            [$csc, $nd1, 'CSC', 101, 20],
            [$csc, $nd2, 'CSC', 201, 10],
            [$bus, $nd1, 'BUS', 101, 12],
            [$eee, $nd1, 'EEE', 101,  8],
        ];

        $firstNames = [
            'Adaeze', 'Babatunde', 'Chidi', 'Damilola', 'Emeka', 'Funmilayo', 'Garba', 'Hassan',
            'Ifeoma', 'Joshua', 'Kelechi', 'Latifat', 'Musa', 'Nkechi', 'Obinna', 'Priscilla',
            'Qudus', 'Rita', 'Samuel', 'Taiwo', 'Ugochi', 'Victor', 'Wuraola', 'Yemi', 'Zainab', 'Adeleke',
        ];
        $lastNames = [
            'Abiodun', 'Bello', 'Chukwu', 'Dada', 'Eze', 'Folarin', 'Garba', 'Haruna',
            'Igwe', 'James', 'Kalu', 'Lawal', 'Mohammed', 'Nwachukwu', 'Okonkwo',
            'Peters', 'Rasheed', 'Salami', 'Thomas', 'Usman',
        ];

        $allStudents = [];
        $nameIndex   = 0;

        foreach ($studentGroups as [$dept, $level, $deptCode, $startSeq, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $seq    = $startSeq + $i;
                $first  = $firstNames[$nameIndex % count($firstNames)];
                $last   = $lastNames[$nameIndex % count($lastNames)];
                $matric = sprintf('CYF/%s/%03d', $deptCode, $seq);
                $email  = strtolower(str_replace('/', '.', $matric)).'@cbt.local';

                $student = User::updateOrCreate(
                    ['school_id' => $school->id, 'email' => $email],
                    [
                        'name'                  => $first.' '.$last,
                        'role'                  => User::ROLE_STUDENT,
                        'school_id'             => $school->id,
                        'password'              => Hash::make('123456'),
                        'force_password_change' => true,
                        'status'                => User::STATUS_ACTIVE,
                        'email_verified_at'     => now(),
                        'matric_no'             => $matric,
                        'student_id_no'         => sprintf('STU%s%03d', $deptCode, $seq),
                        'department_id'         => $dept->id,
                        'level_id'              => $level->id,
                        'phone'                 => sprintf('0804%07d', 1000000 + $nameIndex),
                        'gender'                => $nameIndex % 2 === 0 ? 'male' : 'female',
                    ],
                );

                $allStudents[] = $student;
                $nameIndex++;
            }
        }

        // ── Course Enrollments ────────────────────────────────────────────────

        foreach ($allStudents as $student) {
            foreach ($courses as $course) {
                if (
                    $course->department_id === $student->department_id &&
                    $course->level_id      === $student->level_id &&
                    $course->semester_id   === $sem1->id
                ) {
                    StudentCourseEnrollment::updateOrCreate(
                        [
                            'school_id'  => $school->id,
                            'student_id' => $student->id,
                            'course_id'  => $course->id,
                        ],
                        ['type' => 'compulsory'],
                    );
                }
            }
        }

        // ── Staff Course Assignments ──────────────────────────────────────────

        $staffAssignments = [
            'STF-002' => ['CSC101', 'CSC201'],
            'STF-003' => ['CSC102', 'CSC202'],
            'STF-006' => ['BUS101', 'BUS201'],
            'STF-007' => ['BUS102'],
            'STF-009' => ['EEE101', 'EEE201'],
            'STF-010' => ['EEE102'],
        ];

        foreach ($staffAssignments as $staffId => $courseCodes) {
            $profile = $staffProfiles[$staffId];
            foreach ($courseCodes as $code) {
                $course = $courses[$code];
                StaffCourseAssignment::updateOrCreate(
                    [
                        'school_id'     => $school->id,
                        'staff_id'      => $profile->id,
                        'session_id'    => $session->id,
                        'semester_id'   => $course->semester_id,
                        'department_id' => $course->department_id,
                        'level_id'      => $course->level_id,
                        'course_id'     => $course->id,
                    ],
                    ['status' => 'active'],
                );
            }
        }

        // ── Question Bank ─────────────────────────────────────────────────────
        // 8 questions per course across CSC101, CSC102, CSC201, CSC202,
        // BUS101, BUS102, EEE101, EEE102

        $bankDefinitions = [
            'CSC101' => [
                ['What does CPU stand for?', 'multiple_choice', 2, [
                    ['Central Processing Unit', true], ['Computer Personal Unit', false],
                    ['Central Program Utility', false], ['Control Processing User', false],
                ]],
                ['Which of the following is an input device?', 'multiple_choice', 2, [
                    ['Keyboard', true], ['Monitor', false], ['Printer', false], ['Speaker', false],
                ]],
                ['What is the binary equivalent of decimal 10?', 'multiple_choice', 2, [
                    ['1010', true], ['1100', false], ['0101', false], ['1001', false],
                ]],
                ['RAM stands for?', 'multiple_choice', 2, [
                    ['Random Access Memory', true], ['Read Access Memory', false],
                    ['Rapid Access Module', false], ['Random Application Mode', false],
                ]],
                ['Which computer generation used vacuum tubes?', 'multiple_choice', 2, [
                    ['First generation', true], ['Second generation', false],
                    ['Third generation', false], ['Fourth generation', false],
                ]],
                ['A computer mouse is an example of which type of device?', 'multiple_choice', 2, [
                    ['Input device', true], ['Output device', false],
                    ['Storage device', false], ['Processing unit', false],
                ]],
                ['The operating system is a type of _____ software.', 'multiple_choice', 2, [
                    ['System', true], ['Application', false], ['Programming', false], ['Utility', false],
                ]],
                ['Which of these is NOT a programming language?', 'multiple_choice', 2, [
                    ['HTML', true], ['Python', false], ['Java', false], ['C++', false],
                ]],
            ],
            'CSC102' => [
                ['Which symbol is used for single-line comments in Python?', 'multiple_choice', 2, [
                    ['#', true], ['//', false], ['--', false], ['/*', false],
                ]],
                ['What does the print() function do in Python?', 'multiple_choice', 2, [
                    ['Displays output to the screen', true], ['Reads input from the user', false],
                    ['Stores a value in memory', false], ['Loops through a list', false],
                ]],
                ['Which data type stores whole numbers in most languages?', 'multiple_choice', 2, [
                    ['Integer', true], ['Float', false], ['String', false], ['Boolean', false],
                ]],
                ['What is the result of 5 % 2 in programming?', 'multiple_choice', 2, [
                    ['1', true], ['2', false], ['2.5', false], ['0', false],
                ]],
                ['A variable is best described as?', 'multiple_choice', 2, [
                    ['A named storage location in memory', true],
                    ['A fixed value that cannot change', false],
                    ['A type of loop', false],
                    ['An input device', false],
                ]],
                ['Which of the following is a loop structure?', 'multiple_choice', 2, [
                    ['for loop', true], ['if statement', false], ['switch case', false], ['function call', false],
                ]],
                ['What keyword is used to define a function in Python?', 'multiple_choice', 2, [
                    ['def', true], ['func', false], ['function', false], ['define', false],
                ]],
                ['An array/list stores?', 'multiple_choice', 2, [
                    ['Multiple values in a single variable', true],
                    ['Only one value at a time', false],
                    ['Only string values', false],
                    ['Only numeric values', false],
                ]],
            ],
            'CSC201' => [
                ['Which data structure uses LIFO (Last In, First Out)?', 'multiple_choice', 3, [
                    ['Stack', true], ['Queue', false], ['Linked List', false], ['Tree', false],
                ]],
                ['What is the time complexity of binary search?', 'multiple_choice', 3, [
                    ['O(log n)', true], ['O(n)', false], ['O(n²)', false], ['O(1)', false],
                ]],
                ['A queue follows which principle?', 'multiple_choice', 3, [
                    ['FIFO — First In, First Out', true], ['LIFO — Last In, First Out', false],
                    ['Random access', false], ['Priority based', false],
                ]],
                ['Which sorting algorithm has the worst-case time complexity of O(n²)?', 'multiple_choice', 3, [
                    ['Bubble Sort', true], ['Merge Sort', false], ['Heap Sort', false], ['Quick Sort (best)', false],
                ]],
                ['In a linked list, each node contains?', 'multiple_choice', 3, [
                    ['Data and a pointer to the next node', true],
                    ['Only data', false],
                    ['Only a pointer', false],
                    ['An index and data', false],
                ]],
                ['Which data structure is used to implement recursion internally?', 'multiple_choice', 3, [
                    ['Stack', true], ['Queue', false], ['Array', false], ['Hash Table', false],
                ]],
                ['What is a binary tree?', 'multiple_choice', 3, [
                    ['A tree where each node has at most 2 children', true],
                    ['A tree with exactly 2 levels', false],
                    ['A tree with only leaf nodes', false],
                    ['A tree with exactly 2 nodes', false],
                ]],
                ['Hash tables provide average-case lookup in?', 'multiple_choice', 3, [
                    ['O(1)', true], ['O(n)', false], ['O(log n)', false], ['O(n log n)', false],
                ]],
            ],
            'CSC202' => [
                ['What does SQL stand for?', 'multiple_choice', 2, [
                    ['Structured Query Language', true], ['Standard Query Language', false],
                    ['Sequential Query Logic', false], ['Simple Query Lookup', false],
                ]],
                ['Which SQL command retrieves data from a table?', 'multiple_choice', 2, [
                    ['SELECT', true], ['INSERT', false], ['DELETE', false], ['UPDATE', false],
                ]],
                ['A primary key in a database must be?', 'multiple_choice', 2, [
                    ['Unique and not null', true], ['Null and unique', false],
                    ['Repeated across rows', false], ['A foreign key', false],
                ]],
                ['Which normal form eliminates partial dependencies?', 'multiple_choice', 3, [
                    ['Second Normal Form (2NF)', true], ['First Normal Form (1NF)', false],
                    ['Third Normal Form (3NF)', false], ['Boyce-Codd Normal Form', false],
                ]],
                ['An ER diagram is used to?', 'multiple_choice', 2, [
                    ['Model relationships between entities', true],
                    ['Write SQL queries', false],
                    ['Back up a database', false],
                    ['Create indexes', false],
                ]],
                ['Which SQL clause filters records after grouping?', 'multiple_choice', 3, [
                    ['HAVING', true], ['WHERE', false], ['ORDER BY', false], ['GROUP BY', false],
                ]],
                ['A foreign key is used to?', 'multiple_choice', 2, [
                    ['Link two tables together', true],
                    ['Uniquely identify a record', false],
                    ['Sort query results', false],
                    ['Create a new table', false],
                ]],
                ['Which JOIN returns all records from both tables?', 'multiple_choice', 3, [
                    ['FULL OUTER JOIN', true], ['INNER JOIN', false], ['LEFT JOIN', false], ['RIGHT JOIN', false],
                ]],
            ],
            'BUS101' => [
                ['SWOT analysis stands for?', 'multiple_choice', 2, [
                    ['Strengths, Weaknesses, Opportunities, Threats', true],
                    ['Sales, Workforce, Operations, Technology', false],
                    ['Strategy, Work, Output, Tasks', false],
                    ['Scope, Workload, Objectives, Targets', false],
                ]],
                ['Which theory is associated with Frederick Taylor?', 'multiple_choice', 2, [
                    ['Scientific Management', true], ['Human Relations Theory', false],
                    ['Systems Theory', false], ['Contingency Theory', false],
                ]],
                ['What does ROI stand for in business?', 'multiple_choice', 2, [
                    ['Return on Investment', true], ['Rate of Inflation', false],
                    ['Revenue Over Income', false], ['Return on Inventory', false],
                ]],
                ['In PEST analysis, what does T stand for?', 'multiple_choice', 2, [
                    ['Technological', true], ['Trade', false], ['Taxation', false], ['Transaction', false],
                ]],
                ['Which is NOT a function of management?', 'multiple_choice', 2, [
                    ['Manufacturing', true], ['Planning', false], ['Organizing', false], ['Controlling', false],
                ]],
                ['The process of setting objectives and determining actions is called?', 'multiple_choice', 2, [
                    ['Planning', true], ['Organizing', false], ['Leading', false], ['Controlling', false],
                ]],
                ['Which management style involves employees in decision-making?', 'multiple_choice', 2, [
                    ['Democratic / Participative', true], ['Autocratic', false],
                    ['Laissez-faire', false], ['Bureaucratic', false],
                ]],
                ['Span of control refers to?', 'multiple_choice', 2, [
                    ['The number of subordinates a manager directly supervises', true],
                    ['The range of products a company sells', false],
                    ['The budget allocated to a department', false],
                    ['The number of managers in an organisation', false],
                ]],
            ],
            'BUS102' => [
                ['Which of the following is a formal written communication?', 'multiple_choice', 2, [
                    ['Business letter', true], ['Phone call', false], ['Meeting', false], ['Gossip', false],
                ]],
                ['Effective communication requires a?', 'multiple_choice', 2, [
                    ['Sender, message, channel, receiver, and feedback', true],
                    ['Sender and receiver only', false],
                    ['Message and channel only', false],
                    ['Feedback and noise only', false],
                ]],
                ['A memo is typically used for?', 'multiple_choice', 2, [
                    ['Internal communication within an organisation', true],
                    ['External communication with clients', false],
                    ['Legal agreements', false],
                    ['Financial transactions', false],
                ]],
                ['Which is an example of non-verbal communication?', 'multiple_choice', 2, [
                    ['Body language', true], ['Report writing', false], ['Email', false], ['Speech', false],
                ]],
                ['The executive summary of a report should appear?', 'multiple_choice', 2, [
                    ['At the beginning, before the main body', true],
                    ['At the end of the report', false],
                    ['In the middle', false],
                    ['As a footnote', false],
                ]],
                ['Noise in communication refers to?', 'multiple_choice', 2, [
                    ['Any barrier that distorts the message', true],
                    ['Only sound interference', false],
                    ['The tone of voice used', false],
                    ['The length of the message', false],
                ]],
                ['Which format is most appropriate for a job application?', 'multiple_choice', 2, [
                    ['Formal letter', true], ['Text message', false], ['Casual email', false], ['Memo', false],
                ]],
                ['Feedback in communication is important because?', 'multiple_choice', 2, [
                    ['It confirms the message was understood', true],
                    ['It makes the sender feel good', false],
                    ['It replaces the need for a message', false],
                    ['It adds noise to the channel', false],
                ]],
            ],
            'EEE101' => [
                ["What is Ohm's Law?", 'multiple_choice', 3, [
                    ['V = IR', true], ['V = I + R', false], ['V = I / R', false], ['V = I² × R', false],
                ]],
                ['The SI unit of electrical resistance is?', 'multiple_choice', 2, [
                    ['Ohm (Ω)', true], ['Volt (V)', false], ['Ampere (A)', false], ['Watt (W)', false],
                ]],
                ['In a series circuit, which quantity is the same throughout?', 'multiple_choice', 3, [
                    ['Current', true], ['Voltage', false], ['Resistance', false], ['Power', false],
                ]],
                ['The unit of capacitance is?', 'multiple_choice', 2, [
                    ['Farad (F)', true], ['Henry (H)', false], ['Joule (J)', false], ['Tesla (T)', false],
                ]],
                ['KVL states the algebraic sum of voltages in a closed loop is?', 'multiple_choice', 3, [
                    ['Zero', true], ['Equal to source voltage', false], ['Maximum', false], ['Infinite', false],
                ]],
                ['KCL states the sum of currents entering a node is?', 'multiple_choice', 3, [
                    ['Equal to the sum of currents leaving it', true],
                    ['Always zero', false],
                    ['Greater than currents leaving', false],
                    ['Less than currents leaving', false],
                ]],
                ['The power dissipated in a resistor is given by?', 'multiple_choice', 3, [
                    ['P = I²R', true], ['P = IR', false], ['P = V/I', false], ['P = R/V', false],
                ]],
                ['In a parallel circuit, which quantity is the same across all branches?', 'multiple_choice', 3, [
                    ['Voltage', true], ['Current', false], ['Resistance', false], ['Power', false],
                ]],
            ],
            'EEE102' => [
                ['A diode allows current to flow in?', 'multiple_choice', 2, [
                    ['One direction only', true], ['Both directions', false],
                    ['No direction', false], ['Alternating directions', false],
                ]],
                ['The transistor is primarily used as?', 'multiple_choice', 2, [
                    ['An amplifier or switch', true], ['A power source', false],
                    ['A resistor', false], ['A transformer', false],
                ]],
                ['The p-n junction is found in?', 'multiple_choice', 2, [
                    ['Diodes and transistors', true], ['Resistors', false],
                    ['Capacitors', false], ['Inductors', false],
                ]],
                ['What does an oscilloscope measure?', 'multiple_choice', 2, [
                    ['Voltage waveforms over time', true],
                    ['Resistance', false],
                    ['Current only', false],
                    ['Frequency only', false],
                ]],
                ['In forward bias, the p-n junction?', 'multiple_choice', 2, [
                    ['Conducts current', true], ['Blocks current', false],
                    ['Acts as a capacitor', false], ['Acts as an inductor', false],
                ]],
                ['The unit of frequency is?', 'multiple_choice', 2, [
                    ['Hertz (Hz)', true], ['Farad (F)', false], ['Ohm (Ω)', false], ['Watt (W)', false],
                ]],
                ['An NPN transistor has?', 'multiple_choice', 2, [
                    ['Two N-type and one P-type semiconductor', true],
                    ['Two P-type and one N-type semiconductor', false],
                    ['Only N-type semiconductors', false],
                    ['Only P-type semiconductors', false],
                ]],
                ['The process of converting AC to DC is called?', 'multiple_choice', 2, [
                    ['Rectification', true], ['Amplification', false], ['Modulation', false], ['Inversion', false],
                ]],
            ],
        ];

        $adminUser = User::where('school_id', $school->id)
            ->where('role', User::ROLE_STAFF)
            ->first();

        foreach ($bankDefinitions as $courseCode => $questions) {
            $course = $courses[$courseCode];
            foreach ($questions as $qIdx => [$qText, $qType, $marks, $options]) {
                $bankItem = QuestionBankItem::updateOrCreate(
                    [
                        'school_id'  => $school->id,
                        'course_id'  => $course->id,
                        'sort_order' => $qIdx + 1,
                    ],
                    [
                        'created_by'    => $adminUser?->id,
                        'question_text' => $qText,
                        'question_type' => $qType,
                        'marks'         => $marks,
                    ],
                );

                foreach ($options as $oIdx => [$text, $isCorrect]) {
                    QuestionBankItemOption::updateOrCreate(
                        [
                            'question_id' => $bankItem->id,
                            'sort_order'  => $oIdx + 1,
                        ],
                        [
                            'school_id'   => $school->id,
                            'option_text' => $text,
                            'is_correct'  => $isCorrect,
                        ],
                    );
                }
            }
        }

        // ── Demo Assessments & Questions ──────────────────────────────────────

        $assessmentData = [
            [
                'code'    => 'CSC101-CBT',
                'title'   => 'CSC101 Introduction to Computing',
                'dept'    => $csc,
                'level'   => $nd1,
                'course'  => $courses['CSC101'],
                'creator' => $staffProfiles['STF-002'],
                'questions' => [
                    ['What does CPU stand for?', 'CPU stands for Central Processing Unit.', [
                        ['Central Processing Unit', true], ['Computer Personal Unit', false],
                        ['Central Program Utility', false], ['Control Processing User', false],
                    ]],
                    ['Which of the following is an input device?', 'A keyboard sends input data to the computer.', [
                        ['Keyboard', true], ['Monitor', false], ['Printer', false], ['Speaker', false],
                    ]],
                    ['What is the binary equivalent of decimal 10?', '10 in decimal = 1010 in binary (8 + 2).', [
                        ['1010', true], ['1100', false], ['0101', false], ['1001', false],
                    ]],
                    ['RAM stands for?', 'RAM = Random Access Memory.', [
                        ['Random Access Memory', true], ['Read Access Memory', false],
                        ['Rapid Access Module', false], ['Random Application Mode', false],
                    ]],
                    ['Which computer generation used vacuum tubes?', 'Vacuum tubes were used in first-generation computers (1940s–1950s).', [
                        ['First generation', true], ['Second generation', false],
                        ['Third generation', false], ['Fourth generation', false],
                    ]],
                ],
            ],
            [
                'code'    => 'BUS101-CBT',
                'title'   => 'BUS101 Principles of Management',
                'dept'    => $bus,
                'level'   => $nd1,
                'course'  => $courses['BUS101'],
                'creator' => $staffProfiles['STF-006'],
                'questions' => [
                    ['SWOT analysis stands for?', 'SWOT = Strengths, Weaknesses, Opportunities, Threats.', [
                        ['Strengths, Weaknesses, Opportunities, Threats', true],
                        ['Sales, Workforce, Operations, Technology', false],
                        ['Strategy, Work, Output, Tasks', false],
                        ['Scope, Workload, Objectives, Targets', false],
                    ]],
                    ["Which theory is associated with Frederick Taylor?", 'Taylor developed the Scientific Management theory.', [
                        ['Scientific Management', true], ['Human Relations Theory', false],
                        ['Systems Theory', false], ['Contingency Theory', false],
                    ]],
                    ['What does ROI stand for in business?', 'ROI = Return on Investment.', [
                        ['Return on Investment', true], ['Rate of Inflation', false],
                        ['Revenue Over Income', false], ['Return on Inventory', false],
                    ]],
                    ['In PEST analysis, what does T stand for?', 'PEST = Political, Economic, Social, Technological.', [
                        ['Technological', true], ['Trade', false], ['Taxation', false], ['Transaction', false],
                    ]],
                    ['Which is NOT a function of management?', 'Manufacturing is a production activity, not a management function.', [
                        ['Manufacturing', true], ['Planning', false], ['Organizing', false], ['Controlling', false],
                    ]],
                ],
            ],
            [
                'code'    => 'EEE101-CBT',
                'title'   => 'EEE101 Circuit Theory I',
                'dept'    => $eee,
                'level'   => $nd1,
                'course'  => $courses['EEE101'],
                'creator' => $staffProfiles['STF-009'],
                'questions' => [
                    ["What is Ohm's Law?", "Ohm's Law: V = IR (Voltage = Current × Resistance).", [
                        ['V = IR', true], ['V = I + R', false], ['V = I / R', false], ['V = I² × R', false],
                    ]],
                    ['The SI unit of electrical resistance is?', 'Resistance is measured in Ohms (Ω).', [
                        ['Ohm', true], ['Volt', false], ['Ampere', false], ['Watt', false],
                    ]],
                    ['In a series circuit, which quantity is the same throughout?', 'Current is the same at every point in a series circuit.', [
                        ['Current', true], ['Voltage', false], ['Resistance', false], ['Power', false],
                    ]],
                    ['The unit of capacitance is?', 'Capacitance is measured in Farads (F).', [
                        ['Farad', true], ['Henry', false], ['Joule', false], ['Tesla', false],
                    ]],
                    ['KVL states the algebraic sum of voltages in a closed loop is?', 'KVL: Sum of all voltages around a closed loop = 0.', [
                        ['Zero', true], ['Equal to source voltage', false], ['Maximum', false], ['Infinite', false],
                    ]],
                ],
            ],
        ];

        $builtAssessments = [];

        foreach ($assessmentData as $def) {
            $assessment = Assessment::updateOrCreate(
                ['school_id' => $school->id, 'code' => $def['code']],
                [
                    'created_by'              => $def['creator']->user_id,
                    'session_id'              => $session->id,
                    'semester_id'             => $sem1->id,
                    'department_id'           => $def['dept']->id,
                    'level_id'                => $def['level']->id,
                    'course_id'               => $def['course']->id,
                    'title'                   => $def['title'],
                    'duration_minutes'        => 30,
                    'pass_mark'               => 30,
                    'allow_multiple_attempts' => false,
                    'shuffle_questions'       => true,
                    'shuffle_options'         => true,
                    'allow_review'            => true,
                    'show_score'              => true,
                    'show_answers'            => true,
                    'status'                  => Assessment::STATUS_PUBLISHED,
                    'published_at'            => now(),
                ],
            );

            foreach ($def['questions'] as $qIdx => [$qText, $explanation, $options]) {
                $question = AssessmentQuestion::updateOrCreate(
                    [
                        'school_id'     => $school->id,
                        'assessment_id' => $assessment->id,
                        'sort_order'    => $qIdx + 1,
                    ],
                    [
                        'created_by'    => $def['creator']->user_id,
                        'question_text' => $qText,
                        'question_type' => AssessmentQuestion::TYPE_MULTIPLE_CHOICE,
                        'marks'         => 10,
                        'explanation'   => $explanation,
                    ],
                );

                foreach ($options as $oIdx => [$text, $isCorrect]) {
                    AssessmentQuestionOption::updateOrCreate(
                        [
                            'school_id'   => $school->id,
                            'question_id' => $question->id,
                            'sort_order'  => $oIdx + 1,
                        ],
                        ['option_text' => $text, 'is_correct' => $isCorrect],
                    );
                }
            }

            $assessment->update([
                'total_questions' => $assessment->questions()->count(),
                'total_marks'     => $assessment->questions()->sum('marks'),
            ]);

            $assessment->load('questions.options');
            $builtAssessments[] = $assessment;
        }

        // ── Sample Attempts (10 per assessment, varied pass/fail) ─────────────
        // Scores in marks out of 50: 5 pass (≥30), 5 fail (<30)

        $scoreValues = [50.0, 40.0, 40.0, 30.0, 30.0, 20.0, 20.0, 10.0, 10.0, 0.0];

        foreach ($builtAssessments as $assessment) {
            $matchingStudents = collect($allStudents)
                ->filter(fn (User $s) =>
                    $s->department_id === $assessment->department_id &&
                    $s->level_id      === $assessment->level_id
                )
                ->values()
                ->take(10);

            foreach ($matchingStudents as $idx => $student) {
                $score      = $scoreValues[$idx] ?? 0.0;
                $totalMarks = (float) $assessment->total_marks;
                $percentage = $totalMarks > 0 ? ($score / $totalMarks) * 100 : 0.0;
                $grade      = $score >= (float) $assessment->pass_mark ? 'pass' : 'fail';
                $startedAt  = now()->subDays(3 + $idx)->setTime(9, 0, 0);
                $endedAt    = $startedAt->copy()->addMinutes(30);

                $attempt = AssessmentAttempt::updateOrCreate(
                    [
                        'school_id'     => $school->id,
                        'assessment_id' => $assessment->id,
                        'student_id'    => $student->id,
                    ],
                    [
                        'created_by'  => $student->id,
                        'session_id'  => $session->id,
                        'semester_id' => $sem1->id,
                        'start_time'  => $startedAt,
                        'end_time'    => $endedAt,
                        'score'       => $score,
                        'total_marks' => $totalMarks,
                        'percentage'  => $percentage,
                        'grade'       => $grade,
                        'status'      => 'submitted',
                    ],
                );

                // Assign answers greedily: correct first until score budget is spent
                $remaining = $score;
                foreach ($assessment->questions as $question) {
                    $qMarks    = (float) $question->marks;
                    $correct   = $question->options->firstWhere('is_correct', true);
                    $wrong     = $question->options->firstWhere('is_correct', false);
                    $giveRight = $remaining >= $qMarks && $correct !== null;
                    $chosen    = $giveRight ? $correct : $wrong;

                    if ($chosen) {
                        AssessmentAttemptAnswer::updateOrCreate(
                            ['attempt_id' => $attempt->id, 'question_id' => $question->id],
                            [
                                'school_id'     => $school->id,
                                'option_id'     => $chosen->id,
                                'answer_text'   => $chosen->option_text,
                                'is_correct'    => $giveRight,
                                'marks_awarded' => $giveRight ? $qMarks : 0.0,
                            ],
                        );
                    }

                    if ($giveRight) {
                        $remaining -= $qMarks;
                    }
                }
            }
        }
    }
}
