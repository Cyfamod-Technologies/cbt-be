<?php

namespace Database\Seeders;

use App\Models\School;
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
    }
}
