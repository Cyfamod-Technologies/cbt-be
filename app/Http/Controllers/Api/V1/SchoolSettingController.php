<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\SchoolSetting;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SchoolSettingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $schoolId = $this->schoolId($request);

        $settings = SchoolSetting::firstOrCreate(['school_id' => $schoolId])
            ->load(['currentSession', 'currentSemester']);

        return response()->json(['data' => $settings]);
    }

    public function setCurrentSession(Request $request): JsonResponse
    {
        $user = $this->requireCatalogManager($request);

        $validated = $request->validate([
            'current_session_id' => [
                'required',
                'integer',
                Rule::exists('sessions', 'id')->where('school_id', $user->school_id),
            ],
        ]);

        $settings = DB::transaction(function () use ($user, $validated): SchoolSetting {
            AcademicSession::where('school_id', $user->school_id)->update(['is_current' => false]);
            AcademicSession::where('school_id', $user->school_id)
                ->whereKey($validated['current_session_id'])
                ->update(['is_current' => true, 'status' => 'active']);

            return SchoolSetting::updateOrCreate(
                ['school_id' => $user->school_id],
                ['current_session_id' => $validated['current_session_id']],
            );
        });

        return response()->json([
            'message' => 'Current session updated successfully.',
            'data' => $settings->load(['currentSession', 'currentSemester']),
        ]);
    }

    public function setCurrentSemester(Request $request): JsonResponse
    {
        $user = $this->requireCatalogManager($request);

        $validated = $request->validate([
            'current_semester_id' => [
                'required',
                'integer',
                Rule::exists('semesters', 'id')->where('school_id', $user->school_id),
            ],
        ]);

        Semester::where('school_id', $user->school_id)
            ->whereKey($validated['current_semester_id'])
            ->update(['status' => 'active']);

        $settings = SchoolSetting::updateOrCreate(
            ['school_id' => $user->school_id],
            ['current_semester_id' => $validated['current_semester_id']],
        );

        return response()->json([
            'message' => 'Current semester updated successfully.',
            'data' => $settings->load(['currentSession', 'currentSemester']),
        ]);
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
}
