<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Tenant::class;
    public function definition(): array
    {
        return [
            'name' => $this->faker->company,  // Generate a fake company name
            'domain' => $this->faker->unique()->domainName,  // Generate a unique domain name
            'status' => $this->faker->randomElement(['active', 'inactive']),  // Random status
        ];
    }
}
