<?php

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use App\Notifications\InviteUserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserManager
{
    /**
     * Retrieves a paginated list of users for a specific tenant.
     * Excludes the user making the request.
     *
     * @param Tenant $tenant The tenant whose users are to be fetched.
     * @param User $requestingUser The user making the request, to exclude them from the list.
     * @param int $perPage The number of users per page.
     * @return LengthAwarePaginator
     */
    public function getUsersForTenant(Tenant $tenant, User $requestingUser, int $perPage = 15): LengthAwarePaginator
    {
        // Start a query on the tenant's users relationship,
        // exclude the current user, and order by creation date.
        return $tenant->users()
                      ->where('id', '!=', $requestingUser->id)
                      ->latest()
                      ->paginate($perPage);
    }

    /**
     * Invites a new user to a tenant.
     * This involves creating the user record and sending them a "set password" notification.
     * This entire operation is wrapped in a database transaction for data integrity.
     *
     * @param Tenant $tenant The tenant the user is being invited to.
     * @param array $data The validated data for the new user (name, email, role).
     * @return User The newly created User object.
     */
    public function inviteUser(Tenant $tenant, array $data): User
    {
        // A transaction ensures that if sending the email fails, the user is not created.
        return DB::transaction(function () use ($tenant, $data) {
            
            // Step 1: Create the new User record and associate it with the tenant.
            $newUser = $tenant->users()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
                // Set a highly secure, random, and temporary password.
                // This password is never used by anyone and will be overwritten.
                'password' => Hash::make(Str::random(40)),
            ]);

            // Step 2: Trigger Laravel's built-in Password Reset Flow.
            // This generates a secure token, stores its hash in the 'password_reset_tokens' table,
            // and sends the user the standard "Reset Password Notification" email.
            // This is the most secure, out-of-the-box way to handle invitations.
            $token = Password::broker()->createToken($newUser);
            $newUser->notify(new InviteUserNotification($token));
            
            return $newUser;
        });
    }
}