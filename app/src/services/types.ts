import type { LanguageCode } from '@/utils/languages';

export type WordKind = 'WORD' | 'PHRASE';
export type WordState = 'NEW' | 'LEARNING' | 'REVIEW' | 'RELEARNING';
export type WordSource = 'TEXT' | 'VOICE';
export type Rating = 'AGAIN' | 'HARD' | 'GOOD' | 'EASY';
export type WordFilter = 'ALL' | 'DUE' | 'WORDS' | 'PHRASES' | 'LEARNED';
export type WordSort = 'RECENT' | 'ALPHABETICAL' | 'DUE';

export interface Transcription {
  accent: string | null;
  ipa: string;
}

export interface Example {
  text: string;
  translation: string | null;
}

export interface WordForm {
  term: string;
  translation: string | null;
  part_of_speech: string | null;
}

/** Seconds until the next review for each answer. */
export interface NextIntervals {
  again: number;
  hard: number;
  good: number;
  easy: number;
}

/** A row of the dictionary list. */
export interface WordSummary {
  id: string;
  language: LanguageCode;
  term: string;
  kind: WordKind;
  transcriptions: Transcription[];
  translations: string[];
  note: string | null;
  state: WordState;
  level: number;
  due_at: string | null;
  created_at: string;
}

export interface Word extends WordSummary {
  translation_language: LanguageCode;
  part_of_speech: string | null;
  part_of_speech_short: string | null;
  suggested_translations: string[];
  meaning: string | null;
  examples: Example[];
  forms: WordForm[];
  synonyms: string[];
  source: WordSource;
  interval: number;
  reps: number;
  lapses: number;
  last_reviewed_at: string | null;
}

/** A card in a study session. */
export interface StudyCard extends WordSummary {
  part_of_speech_short: string | null;
  examples: Example[];
  next_intervals: NextIntervals;
}

export interface LookupResult {
  id: string;
  found: boolean;
  term: string | null;
  language: LanguageCode;
  kind: WordKind;
  part_of_speech: string | null;
  part_of_speech_short: string | null;
  transcriptions: Transcription[];
  translations: string[];
  meaning: string | null;
  examples: Example[];
  existing_word_id: string | null;
}

export interface Dashboard {
  language: LanguageCode;
  due: number;
  new: number;
  total: number;
  minutes: number;
  studied_today: boolean;
  next_review_at: string | null;
  next_review_count: number;
}

export interface LanguageStats {
  language: LanguageCode;
  total: number;
  due: number;
  learned: number;
  new: number;
}

export interface ReminderTime {
  time: string;
  enabled: boolean;
}

export interface Reminders {
  enabled: boolean;
  times: ReminderTime[];
  /** ISO weekdays, 1 = Monday. */
  days: number[];
  only_when_due: boolean;
  skip_if_studied: boolean;
}

export interface ReminderForecast {
  at: string;
  count: number;
  minutes: number;
  first_term: string | null;
}

export interface Paginated<T> {
  data: T[];
  paginatorInfo: { currentPage: number; lastPage: number; total: number; hasMorePages: boolean };
}
