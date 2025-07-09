<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AuthService;
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
            $data = $request->only('tenant_id', 'name', 'role' , 'email', 'password', 'password_confirmation');
            return $this->authService->register($data);
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
        return response()->json(auth()->user());
    }
}
