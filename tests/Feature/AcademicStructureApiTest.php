<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademicStructureApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_school_scoped_academic_structure(): void
    {
        $admin = $this->adminUser();
        Sanctum::actingAs($admin, ['catalog:manage']);

        $session = $this->postJson('/api/v1/sessions', [
            'name' => '2025/2026',
            'is_current' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.school_id', $admin->school_id)
            ->assertJsonPath('data.is_current', true)
            ->json('data');

        $semester = $this->postJson('/api/v1/semesters', [
            'session_id' => $session['id'],
            'name' => 'First Semester',
        ])
            ->assertCreated()
            ->json('data');

        $department = $this->postJson('/api/v1/departments', [
            'name' => 'Computer Science',
            'code' => 'CSC',
        ])
            ->assertCreated()
            ->json('data');

        $level = $this->postJson('/api/v1/levels', ['name' => 'ND I'])
            ->assertCreated()
            ->json('data');

        $this->patchJson("/api/v1/semesters/{$semester['id']}/current")
            ->assertOk()
            ->assertJsonPath('data.id', $semester['id']);

        $this->postJson('/api/v1/courses', [
            'code' => 'COM 111',
            'title' => 'Introduction to Computing',
            'department_id' => $department['id'],
            'level_id' => $level['id'],
            'semester_id' => $semester['id'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.school_id', $admin->school_id)
            ->assertJsonPath('data.department.name', 'Computer Science')
            ->assertJsonPath('data.level.name', 'ND I')
            ->assertJsonPath('data.semester.name', 'First Semester');

        $this->getJson('/api/v1/school-settings')
            ->assertOk()
            ->assertJsonPath('data.current_session.id', $session['id'])
            ->assertJsonPath('data.current_semester.id', $semester['id']);
    }

    public function test_academic_records_are_scoped_to_authenticated_school(): void
    {
        $firstAdmin = $this->adminUser('FIRST');
        $secondAdmin = $this->adminUser('SECOND');
        Sanctum::actingAs($firstAdmin, ['catalog:manage']);

        $session = $this->postJson('/api/v1/sessions', ['name' => '2025/2026'])
            ->assertCreated()
            ->json('data');

        Sanctum::actingAs($secondAdmin, ['catalog:manage']);

        $this->getJson("/api/v1/sessions/{$session['id']}")
            ->assertNotFound();

        $this->postJson('/api/v1/sessions', ['name' => '2025/2026'])
            ->assertCreated()
            ->assertJsonPath('data.school_id', $secondAdmin->school_id);
    }

    public function test_course_code_is_unique_per_school_department_level_and_semester(): void
    {
        $admin = $this->adminUser();
        Sanctum::actingAs($admin, ['catalog:manage']);

        $session = $this->postJson('/api/v1/sessions', ['name' => '2025/2026'])->json('data');
        $semester = $this->postJson('/api/v1/semesters', [
            'session_id' => $session['id'],
            'name' => 'First Semester',
        ])->json('data');
        $department = $this->postJson('/api/v1/departments', ['name' => 'Computer Science'])->json('data');
        $level = $this->postJson('/api/v1/levels', ['name' => 'ND I'])->json('data');

        $payload = [
            'code' => 'COM 111',
            'title' => 'Introduction to Computing',
            'department_id' => $department['id'],
            'level_id' => $level['id'],
            'semester_id' => $semester['id'],
        ];

        $this->postJson('/api/v1/courses', $payload)->assertCreated();
        $this->postJson('/api/v1/courses', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_admin_can_add_and_remove_levels_on_department(): void
    {
        $admin = $this->adminUser();
        Sanctum::actingAs($admin, ['catalog:manage']);

        $department = $this->postJson('/api/v1/departments', [
            'name' => 'Computer Science',
            'code' => 'CSC',
        ])
            ->assertCreated()
            ->json('data');

        $this->postJson("/api/v1/departments/{$department['id']}/levels", [
            'name' => 'ND I',
        ])
            ->assertCreated()
            ->assertJsonPath('data.levels.0.name', 'ND I');

        $levelId = $this->getJson('/api/v1/departments')
            ->assertOk()
            ->json('data.0.levels.0.id');

        $this->deleteJson("/api/v1/departments/{$department['id']}/levels/{$levelId}")
            ->assertOk()
            ->assertJsonPath('data.levels', []);
    }

    public function test_non_admin_cannot_create_academic_structure(): void
    {
        $staff = $this->staffUser();
        Sanctum::actingAs($staff, ['profile:read']);

        $this->postJson('/api/v1/levels', ['name' => 'ND I'])
            ->assertForbidden();
    }

    private function adminUser(string $code = 'TEST'): User
    {
        return $this->userForSchool($code, User::ROLE_ADMIN);
    }

    private function staffUser(string $code = 'TEST'): User
    {
        return $this->userForSchool($code, User::ROLE_STAFF);
    }

    private function userForSchool(string $code, string $role): User
    {
        $school = School::create([
            'name' => "{$code} School",
            'code' => $code,
        ]);

        return User::create([
            'school_id' => $school->id,
            'name' => "{$code} {$role}",
            'email' => strtolower("{$role}@{$code}.local"),
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }
}
