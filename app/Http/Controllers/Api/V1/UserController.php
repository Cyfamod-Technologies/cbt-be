<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->requireUserManager($request);

        $validated = $request->validate([
            'role' => ['nullable', Rule::in([User::ROLE_ADMIN, User::ROLE_STAFF, User::ROLE_STUDENT])],
        ]);

        $users = User::query()
            ->where('school_id', $this->schoolId($request))
            ->with(['department', 'level'])
            ->when($validated['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $users]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->requireUserManager($request);
        abort_unless($user->school_id === $this->schoolId($request), 404);

        return response()->json(['data' => $user]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $this->requireUserManager($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'required_without:full_name', 'string', 'max:255'],
            'full_name' => ['sometimes', 'required_without:name', 'string', 'max:255'],
            'matric_no' => [
                'sometimes',
                'required_if:role,student',
                'string',
                'max:80',
                Rule::unique('users', 'matric_no')->where('school_id', $actor->school_id),
            ],
            'student_id_no' => [
                'sometimes',
                'required_if:role,student',
                'string',
                'max:80',
                Rule::unique('users', 'student_id_no')->where('school_id', $actor->school_id),
            ],
            'department_id' => [
                'sometimes',
                'required_if:role,student',
                'integer',
                Rule::exists('departments', 'id')->where('school_id', $actor->school_id),
            ],
            'level_id' => [
                'sometimes',
                'required_if:role,student',
                'integer',
                Rule::exists('levels', 'id')->where('school_id', $actor->school_id),
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where('school_id', $actor->school_id),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:80'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255'],
            'role' => ['required', Rule::in([User::ROLE_STAFF, User::ROLE_STUDENT])],
            'status' => ['sometimes', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
        ]);

        $name = $validated['name'] ?? $validated['full_name'];
        $email = $validated['email'] ?? null;
        $password = $validated['password'] ?? null;
        $temporaryPassword = null;

        if ($validated['role'] === User::ROLE_STUDENT) {
            if ($email === null || $email === '') {
                $matricSlug = Str::slug($validated['matric_no'] ?? $name, '');
                $schoolCode = Str::slug($actor->school?->code ?? (string) $actor->school_id, '');
                $email = sprintf('%s@%s.local', $matricSlug ?: 'student', $schoolCode ?: 'school');
            }

            if ($password === null || $password === '') {
                $temporaryPassword = Str::random(10);
                $password = $temporaryPassword;
            }
        }

        if ($validated['role'] === User::ROLE_STAFF) {
            abort_unless(! empty($email), 422, 'Email is required for staff accounts.');
            abort_unless(! empty($password), 422, 'Password is required for staff accounts.');
        }

        $user = User::create([
            'school_id' => $actor->school_id,
            'name' => $name,
            'matric_no' => $validated['matric_no'] ?? null,
            'student_id_no' => $validated['student_id_no'] ?? null,
            'department_id' => isset($validated['department_id']) ? (int) $validated['department_id'] : null,
            'level_id' => isset($validated['level_id']) ? (int) $validated['level_id'] : null,
            'phone' => $validated['phone'] ?? null,
            'email' => $email,
            'password' => Hash::make($password ?? ''),
            'role' => $validated['role'],
            'status' => $validated['status'] ?? User::STATUS_ACTIVE,
        ]);

        return response()->json([
            'message' => 'User created successfully.',
            'temporary_password' => $temporaryPassword,
            'data' => $user,
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $actor = $this->requireUserManager($request);
        abort_unless($user->school_id === $actor->school_id, 404);

        $validated = $request->validate([
            'name' => ['sometimes', 'required_without:full_name', 'string', 'max:255'],
            'full_name' => ['sometimes', 'required_without:name', 'string', 'max:255'],
            'matric_no' => ['sometimes', 'nullable', 'string', 'max:80', Rule::unique('users', 'matric_no')->where('school_id', $actor->school_id)->ignore($user->id)],
            'student_id_no' => ['sometimes', 'nullable', 'string', 'max:80', Rule::unique('users', 'student_id_no')->where('school_id', $actor->school_id)->ignore($user->id)],
            'department_id' => ['sometimes', 'nullable', 'integer', Rule::exists('departments', 'id')->where('school_id', $actor->school_id)],
            'level_id' => ['sometimes', 'nullable', 'integer', Rule::exists('levels', 'id')->where('school_id', $actor->school_id)],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where('school_id', $actor->school_id)->ignore($user->id),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:80'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255'],
            'role' => ['sometimes', Rule::in([User::ROLE_STAFF, User::ROLE_STUDENT])],
            'status' => ['sometimes', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
        ]);

        if (array_key_exists('full_name', $validated)) {
            $validated['name'] = $validated['full_name'];
            unset($validated['full_name']);
        }

        if (array_key_exists('password', $validated) && $validated['password'] === null) {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json(['message' => 'User updated successfully.', 'data' => $user->refresh()]);
    }

    public function activate(Request $request, User $user): JsonResponse
    {
        return $this->setStatus($request, $user, User::STATUS_ACTIVE);
    }

    public function deactivate(Request $request, User $user): JsonResponse
    {
        return $this->setStatus($request, $user, User::STATUS_INACTIVE);
    }

    private function setStatus(Request $request, User $user, string $status): JsonResponse
    {
        $actor = $this->requireUserManager($request);
        abort_unless($user->school_id === $actor->school_id, 404);

        $user->update(['status' => $status]);

        return response()->json(['message' => "User {$status} successfully.", 'data' => $user->refresh()]);
    }

    private function schoolId(Request $request): int
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->school_id, 401);

        return $user->school_id;
    }

    private function requireUserManager(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canManageUsers(), 403);

        return $user;
    }
}