<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function registerSchool(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'school.name' => ['required_without:name', 'string', 'max:255'],
            'school.code' => ['required_without:code', 'string', 'max:80', 'alpha_dash', Rule::unique('schools', 'code')],
            'school.email' => ['nullable', 'email', 'max:255'],
            'school.phone' => ['nullable', 'string', 'max:80'],
            'school.address' => ['nullable', 'string', 'max:1000'],
            'name' => ['required_without:school.name', 'string', 'max:255'],
            'code' => ['required_without:school.code', 'string', 'max:80', 'alpha_dash', Rule::unique('schools', 'code')],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:1000'],
            'admin.name' => ['required_without:admin_name', 'string', 'max:255'],
            'admin.email' => ['required_without:admin_email', 'email', 'max:255'],
            'admin.password' => ['required_without:admin_password', 'string', 'min:8', 'max:255'],
            'admin_name' => ['required_without:admin.name', 'string', 'max:255'],
            'admin_email' => ['required_without:admin.email', 'email', 'max:255'],
            'admin_password' => ['required_without:admin.password', 'string', 'min:8', 'max:255'],
        ]);

        $schoolInput = [
            'name' => data_get($payload, 'school.name', $payload['name'] ?? null),
            'code' => data_get($payload, 'school.code', $payload['code'] ?? null),
            'email' => data_get($payload, 'school.email', $payload['email'] ?? null),
            'phone' => data_get($payload, 'school.phone', $payload['phone'] ?? null),
            'address' => data_get($payload, 'school.address', $payload['address'] ?? null),
            'status' => School::STATUS_ACTIVE,
        ];

        $adminInput = [
            'name' => data_get($payload, 'admin.name', $payload['admin_name'] ?? null),
            'email' => data_get($payload, 'admin.email', $payload['admin_email'] ?? null),
            'password' => data_get($payload, 'admin.password', $payload['admin_password'] ?? null),
        ];

        try {
            [$school, $admin] = DB::transaction(function () use ($schoolInput, $adminInput): array {
                $school = School::create($schoolInput);

                $admin = $school->users()->create([
                    'name' => $adminInput['name'],
                    'email' => $adminInput['email'],
                    'password' => Hash::make($adminInput['password']),
                    'role' => User::ROLE_ADMIN,
                    'status' => User::STATUS_ACTIVE,
                ]);

                return [$school, $admin];
            });
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'school.code' => ['This school code is already in use.'],
            ]);
        }

        return response()->json([
            'message' => 'School registered successfully.',
            'school' => $this->schoolPayload($school),
            'user' => $this->userPayload($admin),
        ], 201);
    }

    /**
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'school_code' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $school = School::where('code', $credentials['school_code'])->first();

        if (! $school || ! $school->isActive()) {
            throw ValidationException::withMessages([
                'school_code' => ['The selected school could not be found.'],
            ]);
        }

        $user = User::where('school_id', $school->id)
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->isActive()) {
            return response()->json([
                'message' => 'This account is inactive. Contact your school admin.',
            ], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken('cbt-portal', $user->tokenAbilities())->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function studentAccess(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_code' => ['required', 'string'],
            'matric_no'   => ['required', 'string'],
            'password'    => ['required', 'string'],
        ]);

        $school = School::where('code', strtoupper(trim($validated['school_code'])))
            ->where('status', 'active')
            ->first();

        abort_unless($school, 422, 'School not found or inactive.');

        $student = User::where('school_id', $school->id)
            ->where('matric_no', trim($validated['matric_no']))
            ->where('role', User::ROLE_STUDENT)
            ->where('status', User::STATUS_ACTIVE)
            ->with(['department', 'level'])
            ->first();

        abort_unless($student, 422, 'Student not found. Check your matric number.');
        abort_unless(Hash::check($validated['password'], $student->password), 422, 'Incorrect password.');

        $student->forceFill(['last_login_at' => now()])->save();

        $token = $student->createToken('student-access', $student->tokenAbilities())->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'                    => $student->id,
                'name'                  => $student->name,
                'matric_no'             => $student->matric_no,
                'department_id'         => $student->department_id,
                'level_id'              => $student->level_id,
                'force_password_change' => (bool) $student->force_password_change,
                'department'            => $student->department ? ['id' => $student->department->id, 'name' => $student->department->name] : null,
                'level'                 => $student->level       ? ['id' => $student->level->id,       'name' => $student->level->name]       : null,
            ],
        ]);
    }

    public function studentChangePassword(Request $request): JsonResponse
    {
        $student = $request->user();
        abort_unless($student instanceof User && $student->canTakeExams(), 403);

        $validated = $request->validate([
            'password'              => ['required', 'string', 'min:6'],
            'password_confirmation' => ['required', 'same:password'],
        ]);

        $student->forceFill([
            'password'              => Hash::make($validated['password']),
            'force_password_change' => false,
        ])->save();

        return response()->json(['message' => 'Password updated successfully.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $user->loadMissing('school');

        return [
            'id' => $user->id,
            'school_id' => $user->school_id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'school' => $user->school ? $this->schoolPayload($user->school) : null,
            'capabilities' => [
                'manage_schools' => $user->canManageSchools(),
                'manage_catalog' => $user->canManageCatalog(),
                'manage_users' => $user->canManageUsers(),
                'manage_questions' => $user->canManageQuestions(),
                'manage_assessments' => $user->canManageAssessments(),
                'take_exams' => $user->canTakeExams(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schoolPayload(School $school): array
    {
        return [
            'id' => $school->id,
            'name' => $school->name,
            'code' => $school->code,
            'email' => $school->email,
            'phone' => $school->phone,
            'address' => $school->address,
            'status' => $school->status,
        ];
    }
}
