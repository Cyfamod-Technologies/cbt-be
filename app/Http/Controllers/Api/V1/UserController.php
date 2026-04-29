<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
            'role' => ['required', Rule::in([User::ROLE_STAFF, User::ROLE_STUDENT])],
            'gender' => ['sometimes', 'required_if:role,staff', Rule::in(['male', 'female', 'others'])],
            'employment_start_date' => ['sometimes', 'nullable', 'date'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'qualifications' => ['sometimes', 'nullable', 'string', 'max:255'],
            'photo' => ['sometimes', 'nullable', 'image', 'max:2048'],
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
                'required_if:role,staff',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where('school_id', $actor->school_id),
            ],
            'phone' => ['sometimes', 'required_if:role,staff', 'string', 'max:80'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255'],
            'status' => ['sometimes', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
        ]);

        $name = $validated['name'] ?? $validated['full_name'];
        $email = $validated['email'] ?? null;
        $password = $validated['password'] ?? null;
        $temporaryPassword = null;
        $photoUrl = null;

        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('staff-photos', 'public');
            $photoUrl = Storage::url($photoPath);
        }

        if ($validated['role'] === User::ROLE_STUDENT) {
            if ($email === null || $email === '') {
                $matricSlug = Str::slug($validated['matric_no'] ?? $name, '');
                $schoolCode = Str::slug($actor->school?->code ?? (string) $actor->school_id, '');
                $email = sprintf('%s@%s.local', $matricSlug ?: 'student', $schoolCode ?: 'school');
            }
        }

        if ($password === null || $password === '') {
            $temporaryPassword = Str::random(10);
            $password = $temporaryPassword;
        }

        $user = User::create([
            'school_id' => $actor->school_id,
            'name' => $name,
            'matric_no' => $validated['matric_no'] ?? null,
            'student_id_no' => $validated['student_id_no'] ?? null,
            'department_id' => isset($validated['department_id']) ? (int) $validated['department_id'] : null,
            'level_id' => isset($validated['level_id']) ? (int) $validated['level_id'] : null,
            'phone' => $validated['phone'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'employment_start_date' => $validated['employment_start_date'] ?? null,
            'address' => $validated['address'] ?? null,
            'qualifications' => $validated['qualifications'] ?? null,
            'photo_url' => $photoUrl,
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

        foreach (['name', 'full_name', 'matric_no', 'student_id_no', 'department_id', 'level_id', 'phone', 'gender', 'employment_start_date', 'address', 'qualifications', 'email', 'password'] as $field) {
            if ($request->has($field) && $request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required_without:full_name', 'string', 'max:255'],
            'full_name' => ['sometimes', 'required_without:name', 'string', 'max:255'],
            'matric_no' => ['sometimes', 'nullable', 'string', 'max:80', Rule::unique('users', 'matric_no')->where('school_id', $actor->school_id)->ignore($user->id)],
            'student_id_no' => ['sometimes', 'nullable', 'string', 'max:80', Rule::unique('users', 'student_id_no')->where('school_id', $actor->school_id)->ignore($user->id)],
            'department_id' => ['sometimes', 'nullable', 'integer', Rule::exists('departments', 'id')->where('school_id', $actor->school_id)],
            'level_id' => ['sometimes', 'nullable', 'integer', Rule::exists('levels', 'id')->where('school_id', $actor->school_id)],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'others'])],
            'employment_start_date' => ['sometimes', 'nullable', 'date'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'qualifications' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where('school_id', $actor->school_id)->ignore($user->id),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:80'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255'],
            'photo' => ['sometimes', 'nullable', 'image', 'max:2048'],
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

        if ($request->hasFile('photo')) {
            $previousPath = $user->photo_url ? str_replace('/storage/', '', $user->photo_url) : null;

            if ($previousPath) {
                Storage::disk('public')->delete($previousPath);
            }

            $photoPath = $request->file('photo')->store('staff-photos', 'public');
            $validated['photo_url'] = Storage::url($photoPath);
        }

        $user->update($validated);

        return response()->json(['message' => 'User updated successfully.', 'data' => $user->refresh()]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $actor = $this->requireUserManager($request);
        abort_unless($user->school_id === $actor->school_id, 404);

        if ($user->photo_url) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $user->photo_url));
        }

        $user->delete();

        return response()->json(null, 204);
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