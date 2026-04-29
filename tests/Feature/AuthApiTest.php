<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_read_profile_and_logout(): void
    {
        $school = School::create([
            'name' => 'Test School',
            'code' => 'TEST',
        ]);

        $user = User::create([
            'school_id' => $school->id,
            'name' => 'School Admin',
            'email' => 'admin@cbt.local',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'school_code' => $school->code,
            'email' => $user->email,
            'password' => 'password',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('user.role', User::ROLE_ADMIN)
            ->assertJsonPath('user.capabilities.manage_catalog', true)
            ->assertJsonStructure(['token']);

        $token = $loginResponse->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.school_id', $school->id)
            ->assertJsonPath('user.school.code', $school->code);

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $school = School::create([
            'name' => 'Test School',
            'code' => 'TEST',
        ]);

        User::create([
            'school_id' => $school->id,
            'name' => 'Inactive Student',
            'email' => 'inactive@cbt.local',
            'password' => Hash::make('password'),
            'role' => User::ROLE_STUDENT,
            'status' => User::STATUS_INACTIVE,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'school_code' => $school->code,
            'email' => 'inactive@cbt.local',
            'password' => 'password',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'This account is inactive. Contact your school admin.');
    }

    public function test_school_registration_creates_school_and_admin(): void
    {
        $this->postJson('/api/v1/auth/register-school', [
            'school' => [
                'name' => 'Benue Polytechnic',
                'code' => 'benpoly',
                'email' => 'info@benpoly.edu',
                'phone' => '08000000000',
                'address' => 'Benue State',
            ],
            'admin' => [
                'name' => 'Benue Admin',
                'email' => 'admin@benpoly.edu',
                'password' => 'password123',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('school.code', 'benpoly')
            ->assertJsonPath('user.role', User::ROLE_ADMIN)
            ->assertJsonPath('user.school.code', 'benpoly');

        $this->assertDatabaseHas('schools', [
            'code' => 'benpoly',
            'status' => School::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@benpoly.edu',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    public function test_login_is_scoped_to_school_code(): void
    {
        $firstSchool = School::create([
            'name' => 'First School',
            'code' => 'FIRST',
        ]);

        $secondSchool = School::create([
            'name' => 'Second School',
            'code' => 'SECOND',
        ]);

        User::create([
            'school_id' => $firstSchool->id,
            'name' => 'First Admin',
            'email' => 'shared@cbt.local',
            'password' => Hash::make('first-password'),
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $secondUser = User::create([
            'school_id' => $secondSchool->id,
            'name' => 'Second Admin',
            'email' => 'shared@cbt.local',
            'password' => Hash::make('second-password'),
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'school_code' => 'SECOND',
            'email' => 'shared@cbt.local',
            'password' => 'second-password',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $secondUser->id)
            ->assertJsonPath('user.school_id', $secondSchool->id);

        $this->postJson('/api/v1/auth/login', [
            'school_code' => 'SECOND',
            'email' => 'shared@cbt.local',
            'password' => 'first-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }
}
