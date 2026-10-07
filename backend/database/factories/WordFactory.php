<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Word;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Word>
 */
class WordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'language' => 'en',
            'translation_language' => 'ru',
            'term' => fake()->unique()->word(),
            'kind' => Word::KIND_WORD,
            'translations' => [fake()->word()],
            'source' => Word::SOURCE_TEXT,
        ];
    }

    /**
     * A graduated card with the given interval, due at `$due`.
     */
    public function reviewing(float $interval, Carbon $due): static
    {
        return $this->state(fn () => [
            'state' => Word::STATE_REVIEW,
            'interval' => $interval,
            'due_at' => $due,
            'last_reviewed_at' => $due->copy()->subDays((int) $interval),
            'reps' => 3,
        ]);
    }

    public function phrase(): static
    {
        return $this->state(fn () => ['kind' => Word::KIND_PHRASE, 'term' => 'to '.fake()->unique()->word().' up']);
    }
}
