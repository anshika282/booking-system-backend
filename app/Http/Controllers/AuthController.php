<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AuthService;
use App\Http\Resources\UserResource;
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
}
