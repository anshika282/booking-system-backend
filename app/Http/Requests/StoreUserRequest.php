<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The authorization is handled by the Gate in the controller.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            // The email must be globally unique across the entire users table.
            'email' => 'required|string|email|max:255|unique:users',
            // We only allow creating 'staff' or 'admin' roles, not another 'owner'.
            'role' => ['required', 'string', Rule::in(['admin', 'staff'])],
        ];
    }
}