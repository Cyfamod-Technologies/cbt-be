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
use App\Models\StaffExamOfficer;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $school = School::updateOrCreate(
            ['code' => 'CYFAMOD'],
            [
                'name' => 'Cyfamod Demo School',
                'email' => 'admin@cbt.local',
                'status' => 'active',
            ],
        );

        $users = [
            [
                'name' => 'School Admin',
                'email' => 'admin@cbt.local',
                'role' => User::ROLE_ADMIN,
                'school_id' => $school->id,
            ],
            [
                'name' => 'Question Staff',
                'email' => 'staff@cbt.local',
                'role' => User::ROLE_STAFF,
                'school_id' => $school->id,
            ],
            [
                'name' => 'Demo Student',
                'email' => 'student@cbt.local',
                'role' => User::ROLE_STUDENT,
                'school_id' => $school->id,
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['school_id' => $school->id, 'email' => $user['email']],
                [
                    ...$user,
                    'password' => Hash::make('password'),
                    'status' => User::STATUS_ACTIVE,
                    'email_verified_at' => now(),
                ],
            );
        }

        $session = AcademicSession::updateOrCreate(
            ['school_id' => $school->id, 'name' => '2025/2026'],
            ['is_current' => true, 'status' => 'active'],
        );

        $semester = Semester::updateOrCreate(
            ['school_id' => $school->id, 'session_id' => $session->id, 'name' => 'First Semester'],
            ['status' => 'active'],
        );

        SchoolSetting::updateOrCreate(
            ['school_id' => $school->id],
            ['current_session_id' => $session->id, 'current_semester_id' => $semester->id],
        );

        $department = Department::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Computer Science'],
            ['code' => 'CSC', 'status' => 'active'],
        );

        $level = Level::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'ND I'],
            ['status' => 'active'],
        );

        $department->levels()->syncWithoutDetaching([
            $level->id => ['school_id' => $school->id],
        ]);

        $course = Course::updateOrCreate(
            [
                'school_id' => $school->id,
                'department_id' => $department->id,
                'level_id' => $level->id,
                'semester_id' => $semester->id,
                'code' => 'CSC101',
            ],
            ['title' => 'Introduction to Computing', 'status' => 'active'],
        );

        $student = User::where('school_id', $school->id)->where('email', 'student@cbt.local')->first();
        $staff = User::where('school_id', $school->id)->where('email', 'staff@cbt.local')->first();

        if ($student) {
            $student->update([
                'matric_no' => 'CYF/CSC/001',
                'student_id_no' => 'STU001',
                'department_id' => $department->id,
                'level_id' => $level->id,
                'phone' => '08030000001',
            ]);
        }

        $staffProfile = null;
        if ($staff) {
            $staff->update([
                'phone' => '08030000000',
            ]);

            $staffProfile = Staff::updateOrCreate(
                ['school_id' => $school->id, 'email' => 'staff@cbt.local'],
                [
                    'user_id' => $staff->id,
                    'staff_id' => 'STF-001',
                    'full_name' => 'Question Staff',
                    'phone' => '08030000000',
                    'department_id' => null,
                    'status' => 'active',
                ],
            );
        }

        $assessment = Assessment::updateOrCreate(
            ['school_id' => $school->id, 'code' => 'CSC101-MOCK'],
            [
                'created_by' => $staff?->id,
                'session_id' => $session->id,
                'semester_id' => $semester->id,
                'department_id' => $department->id,
                'level_id' => $level->id,
                'course_id' => $course->id,
                'title' => 'CSC101 Demo CBT',
                'description' => 'Demo assessment for testing the CBT workflow.',
                'duration_minutes' => 45,
                'pass_mark' => 50,
                'allow_multiple_attempts' => true,
                'max_attempts' => 3,
                'status' => Assessment::STATUS_PUBLISHED,
                'published_at' => now(),
            ],
        );

        $question = AssessmentQuestion::updateOrCreate(
            ['school_id' => $school->id, 'assessment_id' => $assessment->id, 'sort_order' => 1],
            [
                'created_by' => $staff?->id,
                'question_text' => 'What does CPU stand for?',
                'question_type' => AssessmentQuestion::TYPE_MULTIPLE_CHOICE,
                'marks' => 10,
                'explanation' => 'CPU means Central Processing Unit.',
            ],
        );

        $options = [
            ['Central Processing Unit', true],
            ['Computer Personal Unit', false],
            ['Central Program Utility', false],
            ['Control Processing User', false],
        ];

        foreach ($options as $index => [$text, $correct]) {
            AssessmentQuestionOption::updateOrCreate(
                ['school_id' => $school->id, 'question_id' => $question->id, 'sort_order' => $index + 1],
                ['option_text' => $text, 'is_correct' => $correct],
            );
        }

        $assessment->update([
            'total_questions' => $assessment->questions()->count(),
            'total_marks' => $assessment->questions()->sum('marks'),
        ]);

        if ($staffProfile) {
            StaffCourseAssignment::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'staff_id' => $staffProfile->id,
                    'session_id' => $session->id,
                    'semester_id' => $semester->id,
                    'department_id' => $department->id,
                    'level_id' => $level->id,
                    'course_id' => $course->id,
                ],
                ['status' => 'active'],
            );

            StaffExamOfficer::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'staff_id' => $staffProfile->id,
                    'session_id' => $session->id,
                    'semester_id' => $semester->id,
                    'department_id' => $department->id,
                    'scope' => 'department_level',
                    'level_id' => $level->id,
                ],
                ['status' => 'active'],
            );
        }

        if ($student) {
            $attempt = AssessmentAttempt::updateOrCreate(
                ['school_id' => $school->id, 'assessment_id' => $assessment->id, 'student_id' => $student->id, 'status' => 'submitted'],
                [
                    'created_by' => $student->id,
                    'session_id' => $session->id,
                    'semester_id' => $semester->id,
                    'start_time' => now()->subHours(2),
                    'end_time' => now()->subHours(1)->subMinutes(20),
                    'score' => 10,
                    'total_marks' => 10,
                    'percentage' => 100,
                    'grade' => 'pass',
                ],
            );

            $correctOption = $question->options()->where('is_correct', true)->first();
            if ($correctOption) {
                AssessmentAttemptAnswer::updateOrCreate(
                    ['attempt_id' => $attempt->id, 'question_id' => $question->id],
                    [
                        'school_id' => $school->id,
                        'option_id' => $correctOption->id,
                        'answer_text' => $correctOption->option_text,
                        'is_correct' => true,
                        'marks_awarded' => 10,
                    ],
                );
            }
        }
    }
}
