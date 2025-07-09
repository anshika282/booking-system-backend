<?php

namespace App\Services;

use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthService
{
    public function register($data)
    {
        // Validate the request data
        $validator = Validator::make($data, [
            'tenant_id' => 'required|exists:tenants,id',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        // Create the user in the database
        $user = User::create([
            'tenant_id' => $data['tenant_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'staff', // Default role can be staff, or you can make this configurable
        ]);

        // Generate JWT token for the user
        $token = JWTAuth::fromUser($user);

        return $this->respondWithToken($token);
    }
    
    public function login($credentials)
    {
        if (auth()->attempt($credentials)) {
            $user = auth()->user();
            $token = JWTAuth::fromUser($user);
            return $this->respondWithToken($token);
        }

        throw new \Exception("Invalid credentials");
    }

    public function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }

    public function logout()
    {
        auth()->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    public function refresh()
    {
        try {
            // Parse the token from the Authorization header
            $token = JWTAuth::parseToken(); // This should automatically grab the token from the request
    
            // If the token is expired or invalid, this will throw an exception
            if (!$token) {
                throw new \Exception("Token not provided");
            }
    
            // Refresh the token and get a new one
            $refreshedToken = JWTAuth::refresh($token);
    
            // Return the new token
            return $this->respondWithToken($refreshedToken);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            // Handle missing token, invalid token, or expired token
            return response()->json(['error' => 'Invalid or expired token. Please log in again.'], 401);
        } catch (\Exception $e) {
            // Catch any other errors
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function validateUser($token)
    {
        return JWTAuth::parseToken()->authenticate();
    }
}