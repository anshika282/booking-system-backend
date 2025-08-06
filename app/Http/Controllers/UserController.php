<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Store a new user (staff member) for the current tenant.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        // Authorization using the Gate we just defined.
        // This will throw a 403 Forbidden exception if the user is not an 'owner'.
        $this->authorize('manage-team');
        
        // Get the currently authenticated user (the owner) to access their tenant_id.
        $owner = $request->user();
        $validated = $request->validated();
        
        $user = DB::transaction(function () use ($owner, $validated) {
            // --- Step 1: Create the new User record ---
            $newUser = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
                'tenant_id' => $owner->tenant_id, // Assign to the owner's tenant.
                // Set a highly secure, random, and temporary password.
                // This password is never used by anyone.
                'password' => Hash::make(Str::random(40)),
            ]);

            // --- Step 2: Trigger the Password Reset Flow ---
            // This generates a secure token and sends the user the
            // standard "Reset Password Notification" email.
            $token = Password::broker()->createToken($newUser);
            $newUser->sendPasswordResetNotification($token);
            
            return $newUser;
        });

        // Return a response with the newly created user's data.
        return (new UserResource($user))->response()->setStatusCode(201);
    }
}