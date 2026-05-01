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

        // ── Courses ──────────────────────────────────────────────────────────

        $courseDefinitions = [
            ['CSC101', 'Introduction to Computing',      $csc, $nd1, $sem1],
            ['CSC102', 'Programming Fundamentals',        $csc, $nd1, $sem2],
            ['CSC201', 'Data Structures and Algorithms',  $csc, $nd2, $sem1],
            ['CSC202', 'Database Management Systems',     $csc, $nd2, $sem2],
            ['BUS101', 'Principles of Management',        $bus, $nd1, $sem1],
            ['BUS102', 'Business Communication',          $bus, $nd1, $sem2],
            ['BUS201', 'Business Finance',                $bus, $nd2, $sem1],
            ['EEE101', 'Circuit Theory I',                $eee, $nd1, $sem1],
            ['EEE102', 'Electronics Fundamentals',        $eee, $nd1, $sem2],
            ['EEE201', 'Digital Electronics',             $eee, $nd2, $sem1],
        ];

        $courses = [];
        foreach ($courseDefinitions as [$code, $title, $dept, $level, $sem]) {
            $courses[$code] = Course::updateOrCreate(
                [
                    'school_id'     => $school->id,
                    'code'          => $code,
                    'department_id' => $dept->id,
                    'level_id'      => $level->id,
                    'semester_id'   => $sem->id,
                ],
                ['title' => $title, 'status' => 'active'],
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
                        'name'              => $first.' '.$last,
                        'role'              => User::ROLE_STUDENT,
                        'school_id'         => $school->id,
                        'password'          => Hash::make('password'),
                        'status'            => User::STATUS_ACTIVE,
                        'email_verified_at' => now(),
                        'matric_no'         => $matric,
                        'student_id_no'     => sprintf('STU%s%03d', $deptCode, $seq),
                        'department_id'     => $dept->id,
                        'level_id'          => $level->id,
                        'phone'             => sprintf('0804%07d', 1000000 + $nameIndex),
                        'gender'            => $nameIndex % 2 === 0 ? 'male' : 'female',
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
