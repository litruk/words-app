import type { Composer } from 'vue-i18n';
import type { WordSummary } from '@/services/types';

type Translate = Composer['t'];

const DAY = 86400;

/** The time an answer puts a card away for, as on the rating buttons: "< 1 min", "3 d". */
export function formatInterval(t: Translate, seconds: number): string {
  if (seconds <= 60) return t('study.lessThanMinute');
  if (seconds < 3600) return t('study.minutes', { n: Math.round(seconds / 60) });
  if (seconds < DAY) return t('study.hours', { n: Math.round(seconds / 3600) });
  if (seconds < 30 * DAY) return t('study.days', { n: Math.round(seconds / DAY) });
  if (seconds < 365 * DAY) return t('study.months', { n: Math.round(seconds / (30 * DAY)) });
  return t('study.years', { n: Math.round(seconds / (365 * DAY)) });
}

export function startOfDay(date: Date): Date {
  const day = new Date(date);
  day.setHours(0, 0, 0, 0);
  return day;
}

/** Whole calendar days from today to `date`; 0 for today, negative for the past. */
export function daysFrom(date: Date, now = new Date()): number {
  return Math.round((startOfDay(date).getTime() - startOfDay(now).getTime()) / (DAY * 1000));
}

export type DueStatus = 'new' | 'learned' | 'today' | 'later';

/** What a dictionary row says on its right: new, today, in N days, learned. */
export function dueStatus(word: Pick<WordSummary, 'state' | 'level' | 'due_at'>, now = new Date()): { status: DueStatus; days: number } {
  if (word.state === 'NEW' || !word.due_at) return { status: 'new', days: 0 };

  const days = daysFrom(new Date(word.due_at), now);

  if (word.level >= 5 && days > 0) return { status: 'learned', days };
  if (days <= 0) return { status: 'today', days: 0 };
  return { status: 'later', days };
}

export function greetingKey(date = new Date()): 'morning' | 'afternoon' | 'evening' | 'night' {
  const hour = date.getHours();
  if (hour >= 5 && hour < 12) return 'morning';
  if (hour >= 12 && hour < 18) return 'afternoon';
  if (hour >= 18 && hour < 23) return 'evening';
  return 'night';
}

/** "сб, 10 окт" */
export function formatShortDate(date: Date, locale: string): string {
  return new Intl.DateTimeFormat(locale, { weekday: 'short', day: 'numeric', month: 'short' }).format(date);
}

/** "2 окт" */
export function formatDayMonth(date: Date, locale: string): string {
  return new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'short' }).format(date);
}

/** "English, Deutsch" / "09:00 и 20:30" */
export function formatList(items: string[], locale: string, type: 'conjunction' | 'unit' = 'conjunction'): string {
  try {
    return new Intl.ListFormat(locale, { style: 'long', type }).format(items);
  } catch {
    return items.join(', ');
  }
}

/**
 * "tomorrow", "tomorrow at 09:00", "on Sat, 10 Oct". `time` is an optional
 * "HH:mm" to name, e.g. the first reminder of that day.
 */
export function formatWhen(t: Translate, date: Date, locale: string, time?: string | null): string {
  const days = daysFrom(date);

  if (days <= 0) return t('when.today');
  if (days === 1) return time ? t('when.tomorrowAt', { time }) : t('when.tomorrow');

  const label = formatShortDate(date, locale);
  return time ? t('when.onDateAt', { date: label, time }) : t('when.onDate', { date: label });
}

/** Short weekday names Monday first, capitalised: ["Пн", "Вт", …]. */
export function weekdayNames(locale: string): string[] {
  const monday = new Date(2024, 0, 1); // a Monday
  const format = new Intl.DateTimeFormat(locale, { weekday: 'short' });

  return Array.from({ length: 7 }, (_, i) => {
    const day = new Date(monday);
    day.setDate(monday.getDate() + i);
    const name = format.format(day).replace('.', '');
    return name.charAt(0).toUpperCase() + name.slice(1);
  });
}

/** ISO weekday, 1 = Monday … 7 = Sunday. */
export function isoWeekday(date: Date): number {
  return date.getDay() === 0 ? 7 : date.getDay();
}

/** The badge letter for a name: "Анна" → "А". */
export function initial(name: string | null | undefined): string {
  return (name ?? '').trim().charAt(0).toUpperCase() || '?';
}

/**
 * "09:00 и 20:30, пн–пт": the enabled times and the chosen days, a run of
 * days written as a range.
 */
export function reminderSummary(
  t: Translate,
  locale: string,
  reminders: { enabled: boolean; times: Array<{ time: string; enabled: boolean }>; days: number[] },
): string {
  const times = reminders.times.filter((time) => time.enabled).map((time) => time.time);
  if (!reminders.enabled || times.length === 0 || reminders.days.length === 0) return t('settings.remindersOff');

  const names = weekdayNames(locale).map((name) => name.toLowerCase());
  const days = [...reminders.days].sort((a, b) => a - b);
  const consecutive = days.every((day, i) => i === 0 || day === days[i - 1]! + 1);

  let dayText: string;
  if (days.length === 7) dayText = t('settings.everyDay');
  else if (consecutive && days.length > 2) dayText = `${names[days[0]! - 1]}–${names[days[days.length - 1]! - 1]}`;
  else dayText = days.map((day) => names[day - 1]).join(', ');

  return `${formatList(times, locale)}, ${dayText}`;
}

/**
 * A sentence split around the word, so the word can be set in bold; "to" of an
 * infinitive is left out of the match ("to come across" → "come across").
 */
export function highlight(sentence: string, term: string): Array<{ text: string; match: boolean }> {
  const needle = term.replace(/^to\s+/i, '').trim();
  if (!needle) return [{ text: sentence, match: false }];

  const escaped = needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return sentence
    .split(new RegExp(`(${escaped})`, 'i'))
    .filter((part) => part !== '')
    .map((part) => ({ text: part, match: part.toLowerCase() === needle.toLowerCase() }));
}
