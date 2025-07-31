<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Campaign::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_id' => User::factory()->create(['role' => 'brand'])->id,
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'budget' => $this->faker->numberBetween(100, 10000),
            'location' => $this->faker->city(),
            'requirements' => $this->faker->paragraph(),
            'target_states' => [$this->faker->state()],
            'category' => $this->faker->randomElement(['moda', 'beleza', 'tecnologia', 'esporte']),
            'campaign_type' => $this->faker->randomElement(['instagram', 'tiktok', 'youtube', 'video']),
            'status' => 'pending',
            'deadline' => $this->faker->dateTimeBetween('now', '+30 days'),
            'max_bids' => $this->faker->numberBetween(5, 20),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the campaign is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    /**
     * Indicate that the campaign is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
        ]);
    }

    /**
     * Indicate that the campaign is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the campaign is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
} 