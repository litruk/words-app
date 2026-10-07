/**
 * Languages the app knows (the server's list is config/languages.php).
 * How each is labelled, spoken (text-to-speech) and heard (speech recognition).
 */
export type LanguageCode = 'en' | 'de' | 'ru' | 'uk';

export interface Accent {
  /** As the server tags transcriptions: `uk`, `us`; null for a language with one. */
  id: string | null;
  label: string;
  /** BCP 47 voice for text-to-speech. */
  voice: string;
}

export interface Language {
  code: LanguageCode;
  /** The round badge, e.g. "UA" for Ukrainian as on the design boards. */
  badge: string;
  /** Its own name for itself. */
  endonym: string;
  /** Recognition locale for voice input. */
  speech: string;
  accents: Accent[];
}

export const LANGUAGES: Language[] = [
  {
    code: 'en',
    badge: 'EN',
    endonym: 'English',
    speech: 'en-US',
    accents: [
      { id: 'uk', label: 'UK', voice: 'en-GB' },
      { id: 'us', label: 'US', voice: 'en-US' },
    ],
  },
  { code: 'de', badge: 'DE', endonym: 'Deutsch', speech: 'de-DE', accents: [{ id: null, label: 'DE', voice: 'de-DE' }] },
  { code: 'ru', badge: 'RU', endonym: 'Русский', speech: 'ru-RU', accents: [{ id: null, label: 'RU', voice: 'ru-RU' }] },
  {
    code: 'uk',
    badge: 'UA',
    endonym: 'Українська',
    speech: 'uk-UA',
    accents: [{ id: null, label: 'UA', voice: 'uk-UA' }],
  },
];

export const LANGUAGE_CODES = LANGUAGES.map((language) => language.code);

export function language(code: string): Language {
  return LANGUAGES.find((candidate) => candidate.code === code) ?? LANGUAGES[0]!;
}

export function isLanguageCode(code: unknown): code is LanguageCode {
  return typeof code === 'string' && (LANGUAGE_CODES as string[]).includes(code);
}

/**
 * The device's language when the app has a translation for it, else English.
 */
export function deviceLanguage(): LanguageCode {
  const preferred = typeof navigator === 'undefined' ? [] : (navigator.languages ?? [navigator.language]);

  for (const tag of preferred) {
    const code = tag.toLowerCase().split('-')[0];
    if (isLanguageCode(code)) return code;
  }

  return 'en';
}
