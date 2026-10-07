<?php

namespace Tests\Unit;

use App\Models\Word;
use App\Services\Scheduler;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SchedulerTest extends TestCase
{
    private Scheduler $scheduler;

    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scheduler = new Scheduler;
        $this->now = Carbon::parse('2026-10-07 09:00:00');
    }

    public function test_a_new_card_climbs_the_learning_steps_then_graduates(): void
    {
        $word = new Word;

        $first = $this->scheduler->answer($word, Scheduler::GOOD, $this->now);
        $this->assertSame(Word::STATE_LEARNING, $first['state']);
        $this->assertSame(1, $first['step']);
        $this->assertEquals($this->now->copy()->addMinutes(10), $first['due_at']);

        $word->fill($first);
        $graduated = $this->scheduler->answer($word, Scheduler::GOOD, $this->now);
        $this->assertSame(Word::STATE_REVIEW, $graduated['state']);
        $this->assertSame(1.0, (float) $graduated['interval']);
        $this->assertEquals($this->now->copy()->addDay(), $graduated['due_at']);
    }

    public function test_easy_graduates_a_new_card_at_once(): void
    {
        $next = $this->scheduler->answer(new Word, Scheduler::EASY, $this->now);

        $this->assertSame(Word::STATE_REVIEW, $next['state']);
        $this->assertEquals($this->now->copy()->addDays(4), $next['due_at']);
    }

    public function test_the_buttons_of_a_new_card_read_less_than_a_minute_to_four_days(): void
    {
        $preview = $this->scheduler->preview(new Word, $this->now);

        $this->assertSame(60, $preview['again']);
        $this->assertSame(330, $preview['hard'], 'between the first two steps');
        $this->assertSame(600, $preview['good']);
        $this->assertSame(4 * 86400, $preview['easy']);
    }

    public function test_review_intervals_grow_by_ease_and_keep_their_order(): void
    {
        $word = new Word([
            'state' => Word::STATE_REVIEW,
            'interval' => 10,
            'ease' => 2.5,
            'last_reviewed_at' => $this->now->copy()->subDays(10),
        ]);

        $preview = $this->scheduler->preview($word, $this->now);

        $this->assertSame(12 * 86400, $preview['hard']);
        $this->assertSame(25 * 86400, $preview['good']);
        $this->assertSame(33 * 86400, $preview['easy']);
        $this->assertSame(60, $preview['again']);
    }

    public function test_forgetting_a_card_sends_it_to_relearning_and_cuts_interval_and_ease(): void
    {
        $word = new Word([
            'state' => Word::STATE_REVIEW,
            'interval' => 20,
            'ease' => 2.5,
            'last_reviewed_at' => $this->now->copy()->subDays(20),
        ]);

        $lapse = $this->scheduler->answer($word, Scheduler::AGAIN, $this->now);
        $this->assertSame(Word::STATE_RELEARNING, $lapse['state']);
        $this->assertSame(6.0, (float) $lapse['interval']);
        $this->assertEqualsWithDelta(2.3, $lapse['ease'], 0.0001);

        $word->fill($lapse);
        $relearnt = $this->scheduler->answer($word, Scheduler::GOOD, $this->now);
        $this->assertSame(Word::STATE_REVIEW, $relearnt['state']);
        $this->assertEquals($this->now->copy()->addDays(6), $relearnt['due_at'], 'back to the interval kept at the lapse');
    }

    public function test_ease_never_drops_below_the_minimum(): void
    {
        $word = new Word(['state' => Word::STATE_REVIEW, 'interval' => 3, 'ease' => 1.35, 'last_reviewed_at' => $this->now]);

        $this->assertSame(1.3, $this->scheduler->answer($word, Scheduler::AGAIN, $this->now)['ease']);
        $this->assertSame(1.3, $this->scheduler->answer($word, Scheduler::HARD, $this->now)['ease']);
    }

    public function test_levels_follow_the_interval(): void
    {
        $this->assertSame(0, (new Word)->level());
        $this->assertSame(1, (new Word(['state' => Word::STATE_LEARNING]))->level());
        $this->assertSame(3, (new Word(['state' => Word::STATE_REVIEW, 'interval' => 4]))->level());
        $this->assertSame(5, (new Word(['state' => Word::STATE_REVIEW, 'interval' => 45]))->level());
    }
}
