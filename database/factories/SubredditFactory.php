<?php

namespace Database\Factories;

use App\Enums\InitialCollectionStatus;
use App\Enums\SubredditStatus;
use App\Models\Subreddit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subreddit>
 */
class SubredditFactory extends Factory
{
    protected $model = Subreddit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->regexify('[A-Za-z][A-Za-z0-9]{4,10}');

        return [
            'name' => $name,
            'display_name' => Str::headline($name),
            'description' => fake()->sentence(),
            'subscribers_count' => fake()->numberBetween(0, 500000),
            'url' => Subreddit::urlForName($name),
            'status' => SubredditStatus::Active,
            'initial_collection_status' => InitialCollectionStatus::Pending,
            'initial_collection_started_at' => null,
            'initial_collection_completed_at' => null,
            'last_collected_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Subreddit $subreddit): void {
            $subreddit->url = Subreddit::urlForName($subreddit->name);
        });
    }

    public function paused(): static
    {
        return $this->state(fn (): array => [
            'status' => SubredditStatus::Paused,
        ]);
    }

    public function initialCollectionCompleted(): static
    {
        return $this->state(fn (): array => [
            'initial_collection_status' => InitialCollectionStatus::Completed,
            'initial_collection_started_at' => now()->subDay(),
            'initial_collection_completed_at' => now(),
        ]);
    }
}
