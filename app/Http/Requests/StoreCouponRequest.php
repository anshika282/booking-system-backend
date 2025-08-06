<?php

namespace App\Http\Requests;

use App\Services\TenantManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $serviceId = $this->route('service')->id;

        return [
            'code' => ['required', 'string', 'max:255', Rule::unique('coupons')->where('bookable_service_id', $serviceId),],
            'active' => 'required|boolean',
            'discount_type' => ['required', 'string', Rule::in(['percentage', 'fixed', 'buy_x_get_y_free'])],
            'discount_value' => 'required_if:discount_type,percentage,fixed|nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',

            // --- Conditions JSON fields ---
            'conditions' => 'nullable|array',
            'conditions.min_amount' => 'nullable|numeric|min:0',
            'conditions.valid_from' => 'nullable|date',
            'conditions.valid_to' => 'nullable|date|after_or_equal:conditions.valid_from',
            'conditions.days_of_week' => 'nullable|array',
            'conditions.days_of_week.*' => 'integer|between:0,6',
            'conditions.specific_dates' => 'nullable|array',
            'conditions.specific_dates.*' => 'date_format:Y-m-d',
            // 'conditions.applicable_ticket_tiers' => 'nullable|array',
            // 'conditions.applicable_ticket_tiers.*' => ['integer', Rule::exists('ticket_tiers', 'id')->where('tenant_id', $tenantId)],

            // --- Effects JSON fields ---
            'effects' => 'nullable|array',
            'effects.max_discount_amount' => 'required_if:discount_type,percentage|nullable|numeric|min:0',
            // BOGO specific effects
            'effects.buy_quantity' => 'required_if:discount_type,buy_x_get_y_free|nullable|integer|min:1',
            'effects.get_quantity' => 'required_if:discount_type,buy_x_get_y_free|nullable|integer|min:1',
            'effects.ticket_tier_id' => [
                'required_if:discount_type,buy_x_get_y_free',
                'nullable',
                'integer',
                Rule::exists('ticket_tiers', 'id')->where('bookable_service_id', $serviceId),
            ],
        ];
    }
}