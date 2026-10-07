<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_session_starts_with_due_reviews_then_a_days_worth_of_new_cards(): void
    {
        $user = $this->signIn('ru', ['en']);
        $now = Carbon::now();

        Word::factory()->for($user)->count(12)->sequence(fn ($sequence) => [
            'term' => "new-{$sequence->index}",
            'created_at' => $now->copy()->subMinutes(100 - $sequence->index),
        ])->create();
        Word::factory()->for($user)->reviewing(3, $now->copy()->subDay())->create(['term' => 'older']);
        Word::factory()->for($user)->reviewing(3, $now->copy()->subHour())->create(['term' => 'recent']);
        Word::factory()->for($user)->reviewing(3, $now->copy()->addDays(2))->create(['term' => 'later']);

        $queue = array_column($this->graphQL('{ studyQueue(language: "en") { term } }')->json('data.studyQueue'), 'term');

        $this->assertSame(['older', 'recent', 'new-0', 'new-1'], array_slice($queue, 0, 4));
        $this->assertCount(2 + 10, $queue, 'ten new cards a day');

        $dashboard = $this->graphQL('{ dashboard(language: "en") { due new total minutes studied_today next_review_count } }')
            ->json('data.dashboard');
        $this->assertSame(['due' => 2, 'new' => 10, 'total' => 12, 'minutes' => 4, 'studied_today' => false, 'next_review_count' => 1], $dashboard);
    }

    public function test_answering_a_card_schedules_it_and_logs_the_review(): void
    {
        $user = $this->signIn('ru', ['en']);
        $word = Word::factory()->for($user)->create();

        $answered = $this->graphQL(
            'mutation($id: ID!) { reviewWord(id: $id, rating: GOOD, duration_ms: 4200) { state reps lapses due_at level } }',
            ['id' => $word->id],
        )->json('data.reviewWord');

        $this->assertSame('LEARNING', $answered['state']);
        $this->assertSame(1, $answered['reps']);
        $this->assertEqualsWithDelta(Carbon::now()->addMinutes(10)->timestamp, Carbon::parse($answered['due_at'])->timestamp, 5);

        $review = Review::sole();
        $this->assertSame(Word::STATE_NEW, $review->state_before);
        $this->assertSame(3, $review->rating);
        $this->assertSame(4200, $review->duration_ms);

        $this->graphQL('mutation($id: ID!) { reviewWord(id: $id, rating: AGAIN) { lapses } }', ['id' => $word->id])
            ->assertJsonPath('data.reviewWord.lapses', 1);

        $this->graphQL('{ dashboard(language: "en") { studied_today new } }')
            ->assertJsonPath('data.dashboard.studied_today', true)
            ->assertJsonPath('data.dashboard.new', 0, 'the only new card was introduced today');
    }

    public function test_introducing_new_cards_uses_up_the_days_budget(): void
    {
        $user = $this->signIn('ru', ['en']);
        Word::factory()->for($user)->count(15)->create();

        foreach ($user->words()->limit(4)->get() as $word) {
            $this->graphQL('mutation($id: ID!) { reviewWord(id: $id, rating: EASY) { id } }', ['id' => $word->id]);
        }

        $this->graphQL('{ dashboard(language: "en") { new } }')->assertJsonPath('data.dashboard.new', 6);
    }

    public function test_today_ends_at_midnight_in_the_users_time_zone(): void
    {
        // 22:30 UTC is already 01:30 the next day in Kyiv.
        Carbon::setTestNow('2026-10-07 22:30:00');
        $user = $this->signIn('ru', ['en']);
        Word::factory()->for($user)->reviewing(3, Carbon::parse('2026-10-08 12:00:00'))->create();

        $due = fn (string $zone) => $this->withHeader('X-Timezone', $zone)
            ->graphQL('{ dashboard(language: "en") { due next_review_count } }')
            ->json('data.dashboard');

        $this->assertSame(['due' => 0, 'next_review_count' => 1], $due('UTC'), 'tomorrow in UTC');
        $this->assertSame(['due' => 1, 'next_review_count' => 0], $due('Europe/Kyiv'), 'already today in Kyiv');
    }

    public function test_the_session_can_be_exactly_the_given_cards(): void
    {
        $user = $this->signIn('ru', ['en']);
        $words = Word::factory()->for($user)->count(3)->create();

        $queue = $this->graphQL(
            'query($ids: [ID!]) { studyQueue(language: "en", ids: $ids) { id } }',
            ['ids' => [(string) $words[2]->id, (string) $words[0]->id, (string) Word::factory()->create()->id]],
        )->json('data.studyQueue');

        $this->assertSame([(string) $words[2]->id, (string) $words[0]->id], array_column($queue, 'id'));
    }

    public function test_the_reminder_forecast_counts_what_will_be_due_at_each_moment(): void
    {
        Carbon::setTestNow('2026-10-07 07:00:00');
        $user = $this->signIn('ru', ['en', 'de']);
        Word::factory()->for($user)->reviewing(3, Carbon::parse('2026-10-07 06:00:00'))->create(['term' => 'thorough']);
        Word::factory()->for($user)->reviewing(3, Carbon::parse('2026-10-07 15:00:00'))->create(['term' => 'reluctant']);
        Word::factory()->for($user)->create(['term' => 'gründlich', 'language' => 'de']);

        $forecast = $this->graphQL(
            'query($at: [DateTime!]!) { reminderForecast(at: $at) { count minutes first_term } }',
            ['at' => ['2026-10-07T09:00:00+00:00', '2026-10-07T20:30:00+00:00']],
        )->json('data.reminderForecast');

        $this->assertSame(['count' => 2, 'minutes' => 1, 'first_term' => 'thorough'], $forecast[0]);
        $this->assertSame(3, $forecast[1]['count']);
    }
}
