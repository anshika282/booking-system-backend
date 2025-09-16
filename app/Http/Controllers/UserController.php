<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserManager; // Import the new service
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserController extends Controller
{
    use AuthorizesRequests;

    protected UserManager $userManager;

    /**
     * Inject the UserManager service into the controller.
     */
    public function __construct(UserManager $userManager)
    {
        $this->userManager = $userManager;
    }

    /**
     * Display a paginated listing of the tenant's users.
     */
    public function index(Request $request): JsonResponse
    {
        // Authorization is handled by the 'can:manage-team' middleware in the routes file.
        $requestingUser = $request->user();
        
        // Delegate the logic to the UserManager service.
        $users = $this->userManager->getUsersForTenant(
            $requestingUser->tenant, 
            $requestingUser
        );

        return UserResource::collection($users)->response();
    }

    /**
     * Store a new user (invite them to the tenant).
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        // Authorization is handled by the 'can:manage-team' middleware in the routes file.
        $owner = $request->user();
        
        // Delegate the core logic to the UserManager service.
        $newUser = $this->userManager->inviteUser(
            $owner->tenant, 
            $request->validated()
        );

        // Return a 201 Created response with the new user's data.
        return (new UserResource($newUser))->response()->setStatusCode(201);
    }
}