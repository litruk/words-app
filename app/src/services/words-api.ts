import { authed } from '@/services/api';
import type {
  LanguageStats,
  LookupResult,
  Paginated,
  Word,
  WordFilter,
  WordSort,
  WordSource,
  WordSummary,
} from '@/services/types';

export const SUMMARY_FIELDS = `id language term kind transcriptions { accent ipa } translations note state level due_at created_at`;

export const WORD_FIELDS = `${SUMMARY_FIELDS}
  translation_language part_of_speech part_of_speech_short suggested_translations meaning
  examples { text translation } forms { term translation part_of_speech } synonyms
  source interval reps lapses last_reviewed_at`;

const LOOKUP_FIELDS = `id found term language kind part_of_speech part_of_speech_short
  transcriptions { accent ipa } translations meaning examples { text translation } existing_word_id`;

export async function lookup(text: string, language: string, reverse = false): Promise<LookupResult> {
  const data = await authed<{ lookup: LookupResult }>(
    `query Lookup($text: String!, $language: String!, $reverse: Boolean) {
      lookup(text: $text, language: $language, reverse: $reverse) { ${LOOKUP_FIELDS} }
    }`,
    { text, language, reverse },
  );
  return data.lookup;
}

export interface WordsQuery {
  language: string;
  filter?: WordFilter;
  search?: string;
  sort?: WordSort;
  page?: number;
  first?: number;
}

export async function fetchWords(query: WordsQuery): Promise<Paginated<WordSummary>> {
  const data = await authed<{ words: Paginated<WordSummary> }>(
    `query Words($language: String!, $filter: WordFilter, $search: String, $sort: WordSort, $first: Int!, $page: Int) {
      words(language: $language, filter: $filter, search: $search, sort: $sort, first: $first, page: $page) {
        data { ${SUMMARY_FIELDS} }
        paginatorInfo { currentPage lastPage total hasMorePages }
      }
    }`,
    { first: 30, ...query, search: query.search || null },
  );
  return data.words;
}

export async function fetchWord(id: string): Promise<Word | null> {
  const data = await authed<{ word: Word | null }>(`query Word($id: ID!) { word(id: $id) { ${WORD_FIELDS} } }`, { id });
  return data.word;
}

export async function fetchLanguageStats(): Promise<LanguageStats[]> {
  const data = await authed<{ languageStats: LanguageStats[] }>(
    `query LanguageStats { languageStats { language total due learned new } }`,
  );
  return data.languageStats;
}

export interface NewWord {
  language: string;
  term: string;
  translations: string[];
  note?: string | null;
  source?: WordSource;
  lookup_id?: string | null;
}

export async function addWord(word: NewWord): Promise<Word> {
  const data = await authed<{ addWord: Word }>(
    `mutation AddWord($language: String!, $term: String!, $translations: [String!]!, $note: String, $source: WordSource, $lookup_id: ID) {
      addWord(language: $language, term: $term, translations: $translations, note: $note, source: $source, lookup_id: $lookup_id) {
        ${WORD_FIELDS}
      }
    }`,
    { note: null, source: 'TEXT', lookup_id: null, ...word },
  );
  return data.addWord;
}

export async function updateWord(
  id: string,
  changes: { term?: string; translations?: string[]; note?: string | null },
): Promise<Word> {
  const data = await authed<{ updateWord: Word }>(
    `mutation UpdateWord($id: ID!, $term: String, $translations: [String!], $note: String) {
      updateWord(id: $id, term: $term, translations: $translations, note: $note) { ${WORD_FIELDS} }
    }`,
    { id, ...changes },
  );
  return data.updateWord;
}

export async function deleteWord(id: string): Promise<void> {
  await authed<{ deleteWord: boolean }>(`mutation DeleteWord($id: ID!) { deleteWord(id: $id) }`, { id });
}

export async function resetWordProgress(id: string): Promise<Word> {
  const data = await authed<{ resetWordProgress: Word }>(
    `mutation ResetWordProgress($id: ID!) { resetWordProgress(id: $id) { ${WORD_FIELDS} } }`,
    { id },
  );
  return data.resetWordProgress;
}
