<?php

namespace App\Traits;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Services\TenantManager;

trait TenantAwareRouteBinding
{
    /**
     * Customize route model binding with tenant and related model validation.
     *
     * @param mixed $value The route parameter value
     * @param string|null $field The field to query (defaults to route key)
     * @param string|null $relatedRouteKey The route key for the related model (e.g., 'service')
     * @param string|null $relatedModelClass The related model class (e.g., BookableService::class)
     * @param string|null $foreignKey The foreign key for the related model (e.g., 'bookable_service_id')
     * @param string|null $errorField The field name for error messages (defaults to model name)
     * @return static
     * @throws ValidationException
     */
    public function resolveRouteBinding($value, $field = null, $relatedRouteKey = null, $relatedModelClass = null, $foreignKey = null, $errorField = null)
    {
        $tenantManager = app(TenantManager::class);
        $tenantId = $tenantManager->getCurrentTenantId();

        // Resolve the related model if specified
        $relatedModel = null;
        if ($relatedRouteKey && $relatedModelClass) {
            $relatedId = request()->route($relatedRouteKey);
            if ($relatedId) {
                $relatedModel = $relatedModelClass::where('id', $relatedId)
                    ->where('tenant_id', $tenantId)
                    ->first();

                if (!$relatedModel) {
                    $relatedModelName = class_basename($relatedModelClass);
                    $validator = Validator::make([], [], [
                        $relatedRouteKey => ["The specified {$relatedModelName} does not exist or does not belong to this tenant."],
                    ]);
                    $validator->fails();

                    throw new ValidationException(
                        $validator,
                        response()->json([
                            'message' => 'The given data was invalid.',
                            'errors' => [
                                $relatedRouteKey => ["The specified {$relatedModelName} does not exist or does not belong to this tenant."],
                            ],
                        ], 422)
                    );
                }
            }
        }

        // Build the query for the current model
        $query = $this->newQuery()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->where('tenant_id', $tenantId);

        if ($relatedModel && $foreignKey) {
            $query->where($foreignKey, $relatedModel->id);
        }

        $model = $query->first();

        if (!$model) {
            $errorField = $errorField ?? class_basename($this);
            $validator = Validator::make([], [], [
                $errorField => ["The specified {$errorField} does not exist or does not belong to this tenant or related model."],
            ]);
            $validator->fails();

            throw new ValidationException(
                $validator,
                response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        $errorField => ["The specified {$errorField} does not exist or does not belong to this tenant or related model."],
                    ],
                ], 422)
            );
        }

        return $model;
    }
}