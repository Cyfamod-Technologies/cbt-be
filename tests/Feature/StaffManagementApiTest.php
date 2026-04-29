<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Level;
use App\Models\School;
use App\Models\Semester;
use App\Models\Staff;
use App\Models\StaffExamOfficer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_staff_with_linked_user_account(): void
    {
        $admin = $this->adminUser();
        $department = Department::create([
            'school_id' => $admin->school_id,
            'name' => 'Computer Science',
            'code' => 'CSC',
        ]);

        Sanctum::actingAs($admin, ['users:manage']);

        $this->postJson('/api/v1/staff', [
            'staff_id' => 'STF001',
            'full_name' => 'Ada Lecturer',
            'email' => 'ada@school.test',
            'phone' => '08030000000',
            'department_id' => $department->id,
            'password' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonPath('data.school_id', $admin->school_id)
            ->assertJsonPath('data.user.role', User::ROLE_STAFF)
            ->assertJsonPath('data.department.name', 'Computer Science');

        $this->assertDatabaseHas('users', [
            'school_id' => $admin->school_id,
            'email' => 'ada@school.test',
            'role' => User::ROLE_STAFF,
        ]);

        $this->assertDatabaseHas('staff', [
            'school_id' => $admin->school_id,
            'staff_id' => 'STF001',
            'email' => 'ada@school.test',
        ]);
    }

    public function test_admin_can_assign_staff_to_course_and_check_permissions(): void
    {
        [$admin, $staff, $session, $semester, $department, $level, $course] = $this->assignmentFixture();
        Sanctum::actingAs($admin, ['users:manage']);

        $assignment = $this->postJson('/api/v1/staff-course-assignments', [
            'staff_id' => $staff->id,
            'session_id' => $session->id,
            'semester_id' => $semester->id,
            'department_id' => $department->id,
            'level_id' => $level->id,
            'course_id' => $course->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.course.code', 'COM111')
            ->json('data');

        $this->postJson('/api/v1/staff-course-assignments', [
            'staff_id' => $staff->id,
            'session_id' => $session->id,
            'semester_id' => $semester->id,
            'department_id' => $department->id,
            'level_id' => $level->id,
            'course_id' => $course->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('course_id');

        Sanctum::actingAs($staff->user, ['questions:manage']);
        $this->getJson('/api/v1/staff-permissions')
            ->assertOk()
            ->assertJsonPath('data.can_manage_assigned_courses', true)
            ->assertJsonPath('data.course_assignments.0.id', $assignment['id']);
    }

    public function test_exam_officer_scope_validation(): void
    {
        [$admin, $staff, $session, $semester, $department, $level] = $this->assignmentFixture();
        Sanctum::actingAs($admin, ['users:manage']);

        $this->postJson('/api/v1/staff-exam-officers', [
            'staff_id' => $staff->id,
            'session_id' => $session->id,
            'semester_id' => $semester->id,
            'department_id' => $department->id,
            'level_id' => $level->id,
            'scope' => StaffExamOfficer::SCOPE_DEPARTMENT,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('level_id');

        $this->postJson('/api/v1/staff-exam-officers', [
            'staff_id' => $staff->id,
            'session_id' => $session->id,
            'semester_id' => $semester->id,
            'department_id' => $department->id,
            'level_id' => $level->id,
            'scope' => StaffExamOfficer::SCOPE_DEPARTMENT_LEVEL,
        ])
            ->assertCreated()
            ->assertJsonPath('data.scope', StaffExamOfficer::SCOPE_DEPARTMENT_LEVEL)
            ->assertJsonPath('data.level.name', 'ND I');
    }

    /**
     * @return array{User, Staff, AcademicSession, Semester, Department, Level, Course}
     */
    private function assignmentFixture(): array
    {
        $admin = $this->adminUser();
        $department = Department::create([
            'school_id' => $admin->school_id,
            'name' => 'Computer Science',
            'code' => 'CSC',
        ]);
        $level = Level::create([
            'school_id' => $admin->school_id,
            'name' => 'ND I',
        ]);
        $session = AcademicSession::create([
            'school_id' => $admin->school_id,
            'name' => '2025/2026',
        ]);
        $semester = Semester::create([
            'school_id' => $admin->school_id,
            'session_id' => $session->id,
            'name' => 'First Semester',
        ]);
        $course = Course::create([
            'school_id' => $admin->school_id,
            'department_id' => $department->id,
            'level_id' => $level->id,
            'semester_id' => $semester->id,
            'code' => 'COM111',
            'title' => 'Introduction to Computing',
        ]);
        $staffUser = User::create([
            'school_id' => $admin->school_id,
            'name' => 'Ada Lecturer',
            'email' => 'ada@school.test',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
        ]);
        $staff = Staff::create([
            'school_id' => $admin->school_id,
            'user_id' => $staffUser->id,
            'staff_id' => 'STF001',
            'full_name' => 'Ada Lecturer',
            'email' => 'ada@school.test',
            'department_id' => $department->id,
            'status' => 'active',
        ]);

        return [$admin, $staff, $session, $semester, $department, $level, $course];
    }

    private function adminUser(): User
    {
        $school = School::create([
            'name' => 'Test School',
            'code' => 'TEST'.fake()->unique()->numberBetween(100, 999),
        ]);

        return User::create([
            'school_id' => $school->id,
            'name' => 'School Admin',
            'email' => 'admin'.$school->id.'@school.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);
    }
}
