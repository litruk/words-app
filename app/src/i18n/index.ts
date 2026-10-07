import { createI18n } from 'vue-i18n';
import en from './en';
import ru from './ru';
import uk from './uk';
import de from './de';
import { deviceLanguage, isLanguageCode, type LanguageCode } from '@/utils/languages';

/**
 * Every interface language has to have exactly the keys English has:
 * `vue-tsc` fails on a missing or extra one.
 */
export type MessageSchema = typeof en;

const LOCALE_KEY = 'words-app:locale';

/**
 * Russian and Ukrainian decline numbers in three forms: 1 карточка,
 * 2 карточки, 5 карточек (and 21 карточка, 22 карточки, 11 карточек).
 */
function slavicPlural(choice: number, choicesLength: number): number {
  if (choicesLength < 3) return choice === 1 ? 0 : 1;

  const n = Math.abs(choice) % 100;
  const last = n % 10;

  if (n > 10 && n < 20) return 2;
  if (last === 1) return 0;
  if (last >= 2 && last <= 4) return 1;

  return 2;
}

function storedLocale(): LanguageCode {
  const stored = localStorage.getItem(LOCALE_KEY);

  return isLanguageCode(stored) ? stored : deviceLanguage();
}

export const i18n = createI18n<[MessageSchema], LanguageCode, false>({
  legacy: false,
  locale: storedLocale(),
  fallbackLocale: 'en',
  messages: { en, ru, uk, de },
  pluralRules: { ru: slavicPlural, uk: slavicPlural },
});

export function currentLocale(): LanguageCode {
  return i18n.global.locale.value;
}

/**
 * The interface follows the user's native language; it is remembered so the
 * next launch starts in it before the account has loaded.
 */
export function setLocale(code: string): void {
  const locale = isLanguageCode(code) ? code : 'en';

  i18n.global.locale.value = locale;
  localStorage.setItem(LOCALE_KEY, locale);
  document.documentElement.lang = locale;
}
