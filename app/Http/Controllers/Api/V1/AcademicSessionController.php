<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AcademicSessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $schoolId = $this->schoolId($request);

        return response()->json([
            'data' => AcademicSession::where('school_id', $schoolId)
                ->orderByDesc('is_current')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->requireCatalogManager($request);
        $schoolId = $user->school_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('sessions')->where('school_id', $schoolId)],
            'is_current' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $session = DB::transaction(function () use ($validated, $schoolId): AcademicSession {
            $isCurrent = (bool) ($validated['is_current'] ?? false);

            if ($isCurrent) {
                AcademicSession::where('school_id', $schoolId)->update(['is_current' => false]);
            }

            $session = AcademicSession::create([
                ...$validated,
                'school_id' => $schoolId,
                'is_current' => $isCurrent,
                'status' => $validated['status'] ?? 'active',
            ]);

            if ($isCurrent) {
                SchoolSetting::updateOrCreate(
                    ['school_id' => $schoolId],
                    ['current_session_id' => $session->id],
                );
            }

            return $session;
        });

        return response()->json(['message' => 'Session created successfully.', 'data' => $session], 201);
    }

    public function show(Request $request, AcademicSession $session): JsonResponse
    {
        $this->abortIfCrossSchool($request, $session->school_id);

        return response()->json(['data' => $session]);
    }

    public function update(Request $request, AcademicSession $session): JsonResponse
    {
        $user = $this->requireCatalogManager($request);
        abort_unless($session->school_id === $user->school_id, 404);

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('sessions')->where('school_id', $user->school_id)->ignore($session->id),
            ],
            'is_current' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        DB::transaction(function () use ($validated, $session, $user): void {
            if (array_key_exists('is_current', $validated) && (bool) $validated['is_current']) {
                AcademicSession::where('school_id', $user->school_id)
                    ->whereKeyNot($session->id)
                    ->update(['is_current' => false]);

                SchoolSetting::updateOrCreate(
                    ['school_id' => $user->school_id],
                    ['current_session_id' => $session->id],
                );
            }

            $session->update($validated);
        });

        return response()->json(['message' => 'Session updated successfully.', 'data' => $session->refresh()]);
    }

    public function activate(Request $request, AcademicSession $session): JsonResponse
    {
        return $this->setStatus($request, $session, 'active');
    }

    public function deactivate(Request $request, AcademicSession $session): JsonResponse
    {
        return $this->setStatus($request, $session, 'inactive');
    }

    public function setCurrent(Request $request, AcademicSession $session): JsonResponse
    {
        $user = $this->requireCatalogManager($request);
        abort_unless($session->school_id === $user->school_id, 404);

        DB::transaction(function () use ($session, $user): void {
            AcademicSession::where('school_id', $user->school_id)->update(['is_current' => false]);
            $session->update(['is_current' => true, 'status' => 'active']);
            SchoolSetting::updateOrCreate(
                ['school_id' => $user->school_id],
                ['current_session_id' => $session->id],
            );
        });

        return response()->json(['message' => 'Current session updated successfully.', 'data' => $session->refresh()]);
    }

    private function setStatus(Request $request, AcademicSession $session, string $status): JsonResponse
    {
        $user = $this->requireCatalogManager($request);
        abort_unless($session->school_id === $user->school_id, 404);

        $session->update(['status' => $status]);

        return response()->json(['message' => "Session {$status} successfully.", 'data' => $session]);
    }

    private function schoolId(Request $request): int
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->school_id, 401);

        return $user->school_id;
    }

    private function requireCatalogManager(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canManageCatalog(), 403);

        return $user;
    }

    private function abortIfCrossSchool(Request $request, int $schoolId): void
    {
        abort_unless($this->schoolId($request) === $schoolId, 404);
    }
}
