<?php

namespace Tests\Feature;

use App\Models\Lookup;
use App\Models\User;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DictionaryTest extends TestCase
{
    use RefreshDatabase;

    private const LOOKUP = 'query($text: String!, $reverse: Boolean) {
        lookup(text: $text, language: "en", reverse: $reverse) {
            id found term kind part_of_speech translations existing_word_id
            transcriptions { accent ipa } examples { text translation } synonyms
        }
    }';

    public function test_a_lookup_asks_gemini_once_and_is_shared_afterwards(): void
    {
        $this->fakeGemini($this->thorough());
        $this->signIn('ru', ['en']);

        $entry = $this->graphQL(self::LOOKUP, ['text' => '  Thorough '])->json('data.lookup');

        $this->assertTrue($entry['found']);
        $this->assertSame('thorough', $entry['term']);
        $this->assertSame('WORD', $entry['kind']);
        $this->assertSame(['тщательный', 'основательный', 'доскональный'], $entry['translations']);
        $this->assertSame([['accent' => 'uk', 'ipa' => '/ˈθʌr.ə/'], ['accent' => 'us', 'ipa' => '/ˈθɝː.oʊ/']], $entry['transcriptions']);
        $this->assertNull($entry['existing_word_id']);

        // Another user, other spelling: served from the cache.
        $this->signIn('ru', ['en']);
        $this->graphQL(self::LOOKUP, ['text' => 'thorough'])->assertJsonPath('data.lookup.term', 'thorough');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => str_contains($request['contents'][0]['parts'][0]['text'], 'native language is Russian')
            && $request->hasHeader('x-goog-api-key', 'test-key'));
    }

    public function test_a_failed_lookup_reports_the_dictionary_as_unavailable_and_caches_nothing(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response('overloaded', 503)]);
        $this->signIn('ru', ['en']);

        $this->withHeader('Accept-Language', 'ru')
            ->graphQL(self::LOOKUP, ['text' => 'thorough'])
            ->assertGraphQLErrorMessage('Словарь сейчас недоступен. Попробуйте чуть позже.');

        $this->assertSame(0, Lookup::count());
    }

    public function test_only_languages_the_user_learns_can_be_looked_up(): void
    {
        Http::fake();
        $this->signIn('ru', ['de']);

        $this->graphQL(self::LOOKUP, ['text' => 'thorough'])->assertGraphQLErrorMessage('You are not learning this language.');
        Http::assertNothingSent();
    }

    public function test_adding_a_word_keeps_the_chosen_translations_and_the_lookup_details(): void
    {
        $this->fakeGemini($this->thorough());
        $user = $this->signIn('ru', ['en']);
        $lookup = $this->graphQL(self::LOOKUP, ['text' => 'thorough'])->json('data.lookup');

        $word = $this->graphQL(
            'mutation($lookup: ID!) {
                addWord(language: "en", term: "thorough", translations: ["тщательный", " основательный ", "тщательный"], lookup_id: $lookup, source: VOICE) {
                    term kind source state level translations meaning part_of_speech_short
                    transcriptions { accent } examples { text } forms { term } synonyms
                    next_intervals { again good easy }
                }
            }',
            ['lookup' => $lookup['id']],
        )->json('data.addWord');

        $this->assertSame(['тщательный', 'основательный'], $word['translations']);
        $this->assertSame('VOICE', $word['source']);
        $this->assertSame('NEW', $word['state']);
        $this->assertSame(0, $word['level']);
        $this->assertSame('прил.', $word['part_of_speech_short']);
        $this->assertSame('done carefully and completely', $word['meaning']);
        $this->assertCount(2, $word['examples']);
        $this->assertSame(['careful', 'meticulous'], $word['synonyms']);
        $this->assertSame(60, $word['next_intervals']['again']);

        $this->graphQL(self::LOOKUP, ['text' => 'thorough'])
            ->assertJsonPath('data.lookup.existing_word_id', (string) $user->words()->first()->id);

        $this->graphQL('mutation { addWord(language: "en", term: "Thorough", translations: ["полный"]) { id } }')
            ->assertGraphQLErrorMessage('This word is already in your dictionary.');
    }

    public function test_a_word_saved_offline_gets_its_translations_and_details_on_arrival(): void
    {
        $this->fakeGemini($this->thorough());
        $this->signIn('ru', ['en']);

        $word = $this->graphQL(
            'mutation { addWord(language: "en", term: "thorough", translations: []) { translations meaning suggested_translations } }',
        )->json('data.addWord');

        $this->assertSame(['тщательный', 'основательный'], $word['translations']);
        $this->assertSame('done carefully and completely', $word['meaning']);
        $this->assertSame(['доскональный'], $word['suggested_translations'], 'the rest are offered in the edit sheet');
    }

    public function test_a_word_with_its_own_translation_is_saved_while_the_dictionary_is_down(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response('overloaded', 503)]);
        $this->signIn('ru', ['en']);

        $this->graphQL('mutation { addWord(language: "en", term: "thorough", translations: ["тщательный"]) { translations meaning } }')
            ->assertJsonPath('data.addWord', ['translations' => ['тщательный'], 'meaning' => null]);

        $this->graphQL('mutation { addWord(language: "en", term: "reluctant", translations: []) { id } }')
            ->assertGraphQLErrorMessage('Choose a translation or write your own.');
    }

    public function test_a_word_needs_a_translation_or_a_note(): void
    {
        $this->fakeGemini(['found' => false]);
        $this->signIn('ru', ['en']);

        $this->graphQL('mutation { addWord(language: "en", term: "awkward", translations: []) { id } }')
            ->assertGraphQLErrorMessage('Choose a translation or write your own.');

        $this->graphQL('mutation { addWord(language: "en", term: "it\'s up to you", translations: [], note: "решать тебе") { kind note } }')
            ->assertJsonPath('data.addWord.kind', 'PHRASE')
            ->assertJsonPath('data.addWord.note', 'решать тебе');
    }

    public function test_the_list_filters_searches_and_sorts_one_language(): void
    {
        $user = $this->signIn('ru', ['en', 'de']);
        $now = Carbon::now();

        Word::factory()->for($user)->create(['term' => 'awkward', 'translations' => ['неловкий'], 'created_at' => $now->copy()->subDays(3)]);
        Word::factory()->for($user)->reviewing(4, $now->copy()->subHour())->create(['term' => 'reluctant', 'translations' => ['неохотный'], 'created_at' => $now->copy()->subDays(2)]);
        Word::factory()->for($user)->reviewing(40, $now->copy()->addDays(20))->create(['term' => 'meanwhile', 'translations' => ['тем временем'], 'created_at' => $now->copy()->subDay()]);
        Word::factory()->for($user)->phrase()->create(['term' => 'to figure out', 'translations' => ['разобраться']]);
        Word::factory()->for($user)->create(['term' => 'gründlich', 'language' => 'de']);
        Word::factory()->for(User::factory())->create(['term' => 'someone else\'s']);

        $terms = fn (string $args) => array_column(
            $this->graphQL("{ words(language: \"en\" {$args}) { data { term } paginatorInfo { total } } }")->json('data.words.data'),
            'term',
        );

        $this->assertSame(['to figure out', 'meanwhile', 'reluctant', 'awkward'], $terms(''));
        $this->assertSame(['reluctant'], $terms(', filter: DUE'));
        $this->assertSame(['to figure out'], $terms(', filter: PHRASES'));
        $this->assertSame(['meanwhile'], $terms(', filter: LEARNED'));
        $this->assertSame(['reluctant'], $terms(', search: "НЕОХОТ"'), 'translations are searched too');
        $this->assertSame(['awkward', 'meanwhile', 'reluctant', 'to figure out'], $terms(', sort: ALPHABETICAL'));
        $this->assertSame(['reluctant', 'meanwhile', 'awkward', 'to figure out'], $terms(', sort: DUE'));

        $stats = $this->graphQL('{ languageStats { language total due learned new } }')->json('data.languageStats');
        $this->assertSame(['language' => 'en', 'total' => 4, 'due' => 1, 'learned' => 1, 'new' => 2], $stats[0]);
        $this->assertSame(1, $stats[1]['total']);
    }

    public function test_someone_elses_word_cannot_be_read_or_changed(): void
    {
        $theirs = Word::factory()->create();
        $this->signIn('ru', ['en']);

        $this->graphQL('query($id: ID!) { word(id: $id) { id } }', ['id' => $theirs->id])->assertJsonPath('data.word', null);
        $this->graphQL('mutation($id: ID!) { deleteWord(id: $id) }', ['id' => $theirs->id])
            ->assertGraphQLErrorMessage('This word was not found.');
        $this->assertNotNull($theirs->fresh());
    }

    public function test_editing_and_resetting_a_word(): void
    {
        $user = $this->signIn('ru', ['en']);
        $word = Word::factory()->for($user)->reviewing(12, Carbon::now()->addDays(3))->create(['term' => 'thorogh']);

        $edited = $this->graphQL(
            'mutation($id: ID!) { updateWord(id: $id, term: "thorough", translations: ["тщательный"], note: "о проверке") { term translations note level } }',
            ['id' => $word->id],
        )->json('data.updateWord');

        $this->assertSame(['term' => 'thorough', 'translations' => ['тщательный'], 'note' => 'о проверке', 'level' => 4], $edited);

        $this->graphQL('mutation($id: ID!) { resetWordProgress(id: $id) { state level due_at reps } }', ['id' => $word->id])
            ->assertJsonPath('data.resetWordProgress', ['state' => 'NEW', 'level' => 0, 'due_at' => null, 'reps' => 0]);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function fakeGemini(array $entry): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($entry)]]]]],
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function thorough(): array
    {
        return [
            'found' => true,
            'term' => 'thorough',
            'kind' => 'word',
            'part_of_speech' => 'прилагательное',
            'part_of_speech_short' => 'прил.',
            'transcriptions' => [['accent' => 'uk', 'ipa' => 'ˈθʌr.ə'], ['accent' => 'us', 'ipa' => '/ˈθɝː.oʊ/']],
            'translations' => ['тщательный', 'основательный', 'Тщательный', 'доскональный', ''],
            'meaning' => 'done carefully and completely',
            'examples' => [
                ['text' => 'She gave the contract a thorough read.', 'translation' => 'Она тщательно прочитала договор.'],
                ['text' => 'The police carried out a thorough search.', 'translation' => 'Полиция провела тщательный обыск.'],
                ['text' => 'A third one.', 'translation' => null],
            ],
            'forms' => [['term' => 'thoroughly', 'translation' => 'тщательно', 'part_of_speech' => 'нареч.']],
            'synonyms' => ['careful', 'meticulous'],
        ];
    }
}
