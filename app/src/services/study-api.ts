import { authed } from '@/services/api';
import { SUMMARY_FIELDS } from '@/services/words-api';
import type { Dashboard, Rating, ReminderForecast, StudyCard } from '@/services/types';

const CARD_FIELDS = `${SUMMARY_FIELDS} part_of_speech_short examples { text translation } next_intervals { again hard good easy }`;

export async function fetchDashboard(language: string): Promise<Dashboard> {
  const data = await authed<{ dashboard: Dashboard }>(
    `query Dashboard($language: String!) {
      dashboard(language: $language) { language due new total minutes studied_today next_review_at next_review_count }
    }`,
    { language },
  );
  return data.dashboard;
}

export async function fetchStudyQueue(language: string, ids?: string[]): Promise<StudyCard[]> {
  const data = await authed<{ studyQueue: StudyCard[] }>(
    `query StudyQueue($language: String!, $ids: [ID!]) { studyQueue(language: $language, ids: $ids) { ${CARD_FIELDS} } }`,
    { language, ids: ids ?? null },
  );
  return data.studyQueue;
}

export async function reviewWord(id: string, rating: Rating, durationMs: number): Promise<StudyCard> {
  const data = await authed<{ reviewWord: StudyCard }>(
    `mutation ReviewWord($id: ID!, $rating: Rating!, $duration: Int) {
      reviewWord(id: $id, rating: $rating, duration_ms: $duration) { ${CARD_FIELDS} }
    }`,
    { id, rating, duration: Math.round(durationMs) },
  );
  return data.reviewWord;
}

/** The server's DateTime takes ISO 8601 without fractions: 2026-10-07T18:30:00+00:00. */
function serverDateTime(date: Date): string {
  return date.toISOString().replace(/\.\d{3}Z$/, '+00:00');
}

export async function fetchReminderForecast(at: Date[]): Promise<ReminderForecast[]> {
  const data = await authed<{ reminderForecast: ReminderForecast[] }>(
    `query ReminderForecast($at: [DateTime!]!) { reminderForecast(at: $at) { at count minutes first_term } }`,
    { at: at.map(serverDateTime) },
  );
  return data.reminderForecast;
}
