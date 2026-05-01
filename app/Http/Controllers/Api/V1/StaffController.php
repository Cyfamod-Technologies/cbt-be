<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $schoolId = $this->schoolId($request);

        return response()->json([
            'data' => Staff::with(['user', 'department'])
                ->where('school_id', $schoolId)
                ->orderBy('full_name')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $this->requireUserManager($request);
        $validated = $this->validateStaff($request, $actor);
        $temporaryPassword = null;

        [$staff] = DB::transaction(function () use ($validated, $actor, &$temporaryPassword): array {
            $password = $validated['password'] ?? null;
            if (! $password) {
                $temporaryPassword = Str::random(10);
                $password = $temporaryPassword;
            }

            $user = User::create([
                'school_id' => $actor->school_id,
                'name' => $validated['full_name'],
                'email' => $validated['email'],
                'password' => Hash::make($password),
                'role' => User::ROLE_STAFF,
                'phone' => $validated['phone'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'status' => User::STATUS_ACTIVE,
            ]);

            $staff = Staff::create([
                'school_id' => $actor->school_id,
                'user_id' => $user->id,
                'staff_id' => $validated['staff_id'],
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'status' => User::STATUS_ACTIVE,
            ]);

            return [$staff, $user];
        });

        return response()->json([
            'message' => 'Staff created successfully.',
            'temporary_password' => $temporaryPassword,
            'data' => $staff->load(['user', 'department']),
        ], 201);
    }

    public function show(Request $request, Staff $staff): JsonResponse
    {
        abort_unless($staff->school_id === $this->schoolId($request), 404);

        return response()->json([
            'data' => $staff->load([
                'user',
                'department',
                'courseAssignments.session',
                'courseAssignments.semester',
                'courseAssignments.department',
                'courseAssignments.level',
                'courseAssignments.course',
                'examOfficerAssignments.session',
                'examOfficerAssignments.semester',
                'examOfficerAssignments.department',
                'examOfficerAssignments.level',
            ]),
        ]);
    }

    public function update(Request $request, Staff $staff): JsonResponse
    {
        $actor = $this->requireUserManager($request);
        abort_unless($staff->school_id === $actor->school_id, 404);

        $validated = $this->validateStaff($request, $actor, $staff);

        DB::transaction(function () use ($staff, $validated): void {
            $staff->update($validated);
            $staff->user()->update([
                'name' => $validated['full_name'] ?? $staff->full_name,
                'email' => $validated['email'] ?? $staff->email,
                'phone' => array_key_exists('phone', $validated) ? $validated['phone'] : $staff->phone,
                'department_id' => array_key_exists('department_id', $validated) ? $validated['department_id'] : $staff->department_id,
                'status' => $validated['status'] ?? $staff->status,
                ...(! empty($validated['password']) ? ['password' => Hash::make($validated['password'])] : []),
            ]);
        });

        return response()->json([
            'message' => 'Staff updated successfully.',
            'data' => $staff->refresh()->load(['user', 'department']),
        ]);
    }

    public function destroy(Request $request, Staff $staff): JsonResponse
    {
        $actor = $this->requireUserManager($request);
        abort_unless($staff->school_id === $actor->school_id, 404);

        $links = [
            ['table' => 'staff_course_assignments', 'column' => 'staff_id', 'label' => 'course assignments',        'unlinkable' => true],
            ['table' => 'staff_exam_officers',      'column' => 'staff_id', 'label' => 'exam officer assignments',  'unlinkable' => true],
        ];

        $blocked = $this->handleLinkedOrCascade($request, $links, $staff->id, "this staff");
        if ($blocked !== null) {
            return $blocked;
        }

        DB::transaction(function () use ($staff): void {
            $userId = $staff->user_id;
            $staff->delete();
            User::where('id', $userId)->delete();
        });

        return response()->json(null, 204);
    }

    public function activate(Request $request, Staff $staff): JsonResponse
    {
        return $this->setStatus($request, $staff, User::STATUS_ACTIVE);
    }

    public function deactivate(Request $request, Staff $staff): JsonResponse
    {
        return $this->setStatus($request, $staff, User::STATUS_INACTIVE);
    }

    private function setStatus(Request $request, Staff $staff, string $status): JsonResponse
    {
        $actor = $this->requireUserManager($request);
        abort_unless($staff->school_id === $actor->school_id, 404);

        DB::transaction(function () use ($staff, $status): void {
            $staff->update(['status' => $status]);
            $staff->user()->update(['status' => $status]);
        });

        return response()->json([
            'message' => "Staff {$status} successfully.",
            'data' => $staff->refresh()->load(['user', 'department']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateStaff(Request $request, User $actor, ?Staff $staff = null): array
    {
        $required = $staff ? 'sometimes' : 'required';

        return $request->validate([
            'staff_id' => [
                $required,
                'string',
                'max:80',
                Rule::unique('staff', 'staff_id')->where('school_id', $actor->school_id)->ignore($staff?->id),
            ],
            'full_name' => [$required, 'string', 'max:255'],
            'email' => [
                $required,
                'email',
                'max:255',
                Rule::unique('staff', 'email')->where('school_id', $actor->school_id)->ignore($staff?->id),
                Rule::unique('users', 'email')->where('school_id', $actor->school_id)->ignore($staff?->user_id),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:80'],
            'department_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('school_id', $actor->school_id),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255'],
            'status' => ['sometimes', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
        ]);
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
