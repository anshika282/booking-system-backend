<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class FinalizeBookingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'session_id' => 'required|string|exists:booking_intents,session_id',
            'payment_token' => 'required|string', // The token from your payment provider
        ];
    }
}