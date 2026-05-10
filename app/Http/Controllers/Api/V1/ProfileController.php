<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'name'                  => ['sometimes', 'required', 'string', 'max:255'],
            'email'                 => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->where('school_id', $user->school_id)->ignore($user->id)],
            'phone'                 => ['sometimes', 'nullable', 'string', 'max:80'],
            'password'              => ['sometimes', 'required', 'string', 'min:6', 'max:255'],
            'password_confirmation' => ['required_with:password', 'same:password'],
        ]);

        unset($validated['password_confirmation']);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data'    => $user->refresh(),
        ]);
    }
}
