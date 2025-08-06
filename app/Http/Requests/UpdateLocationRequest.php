<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
       $tenantId = $this->user()->tenant_id;
        $locationId = $this->route('location')->id;

        return [
            'name' => [
            'sometimes', // 'sometimes' allows for partial updates
            'required',
            'string',
            'max:255',
            Rule::unique('locations')->where('tenant_id', $tenantId)->ignore($locationId),],
            'address' => 'sometimes|nullable|string|max:65535',
            'city' => 'sometimes|nullable|string|max:255',
            'state' => 'sometimes|nullable|string|max:255',
            'postal_code' => 'sometimes|nullable|string|max:255',
            'country' => 'sometimes|nullable|string|max:255',
        ];  
    }
}
