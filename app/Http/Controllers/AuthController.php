<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AuthService;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function register(Request $request)
    {
        try {
            // Validation is now for the tenant and the owner user.
                $validator = Validator::make($request->toArray(), [
                    'tenant_name' => 'required|string|max:255',
                    'user_name' => 'required|string|max:255',
                    'email' => 'required|string|email|max:255|unique:users,email',
                    'password' => 'required|string|min:8|confirmed',
                ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
            return $this->authService->register($validator->validated());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function login(Request $request)
    {   
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',  // Ensure email is provided and has the correct format
            'password' => 'required|string', 
        ]);
    
        // Check if validation fails
        if ($validator->fails()) {
            // If validation fails, return a response with the validation errors
            return response()->json(['error' => $validator->errors()], 400);
        }    
        
        $credentials = $request->only('email', 'password');

        try {
            return $this->authService->login($credentials);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function logout()
    {
        return $this->authService->logout();
    }

    public function refresh()
    {
        
        return $this->authService->refresh();
    }

    public function me()
    {
         $user = auth()->user();
         $user->load('tenant');
        // Return the user data formatted by our API Resource
        return new UserResource($user);
    }
/**
     * Resets the user's password and logs them in by returning a JWT.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $userToLogin = null; // Variable to hold the user instance

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) use (&$userToLogin) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();
                $user->markEmailAsVerified();
                event(new PasswordReset($user));
                
                // Assign the user to our variable so we can use it outside the closure
                $userToLogin = $user; 
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], 400);
        }

        // --- NEW: Generate and return a JWT for the user ---
        if (!$userToLogin) {
            return response()->json(['message' => 'Could not log in user after password reset.'], 500);
        }
        
        // Use the injected AuthService to generate the token response
        $token = JWTAuth::fromUser($userToLogin);
        return $this->authService->respondWithToken($token);
    }
}
