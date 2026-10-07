import { Capacitor } from '@capacitor/core';
import { LocalNotifications, type LocalNotificationSchema } from '@capacitor/local-notifications';
import type { Router } from 'vue-router';
import { i18n } from '@/i18n';
import { fetchDashboard, fetchReminderForecast } from '@/services/study-api';
import type { Reminders } from '@/services/types';
import { useAuthStore } from '@/stores/auth-store';
import { isoWeekday } from '@/utils/format';

/**
 * Study reminders as local notifications. The app plans a week ahead: for
 * every enabled time on every chosen day it asks the server how much will be
 * due then, drops the moments with nothing to do (when asked to) and the rest
 * of today once the user has studied (when asked to), and schedules the rest.
 * It plans again whenever something changes - an answer, a new word, the
 * settings, the app coming back to the foreground.
 */

const CHANNEL = 'reminders';
const ACTIONS = 'study-reminder';
/** Scheduled reminders use ids from here up; a snoozed one uses SNOOZE_ID. */
const FIRST_ID = 1000;
const SNOOZE_ID = 999;
const DAYS_AHEAD = 7;

const t = i18n.global.t;

export function remindersSupported(): boolean {
  return Capacitor.isNativePlatform();
}

export async function notificationsAllowed(): Promise<boolean> {
  if (!remindersSupported()) return false;
  return (await LocalNotifications.checkPermissions()).display === 'granted';
}

/** Asks for the permission when it was never asked; true when granted. */
export async function allowNotifications(): Promise<boolean> {
  if (!remindersSupported()) return false;

  const status = await LocalNotifications.checkPermissions();
  if (status.display === 'granted') return true;
  if (status.display === 'denied') return false;

  return (await LocalNotifications.requestPermissions()).display === 'granted';
}

/** The moments the settings ask for, from now on, a week ahead. */
export function upcomingMoments(reminders: Reminders, now = new Date()): Date[] {
  const times = reminders.times.filter((time) => time.enabled).map((time) => time.time);
  const moments: Date[] = [];

  for (let offset = 0; offset < DAYS_AHEAD; offset++) {
    const day = new Date(now);
    day.setDate(now.getDate() + offset);
    if (!reminders.days.includes(isoWeekday(day))) continue;

    for (const time of times) {
      const [hours, minutes] = time.split(':').map(Number);
      const at = new Date(day);
      at.setHours(hours ?? 0, minutes ?? 0, 0, 0);
      if (at.getTime() > now.getTime() + 60_000) moments.push(at);
    }
  }

  return moments.sort((a, b) => a.getTime() - b.getTime());
}

/** The first enabled reminder time on the day of `date`, if reminders are on. */
export function reminderTimeOn(reminders: Reminders | undefined, date: Date): string | null {
  if (!reminders?.enabled || !reminders.days.includes(isoWeekday(date))) return null;

  return (
    reminders.times
      .filter((time) => time.enabled)
      .map((time) => time.time)
      .sort()[0] ?? null
  );
}

export function notificationText(count: number, minutes: number, firstTerm: string | null) {
  const duration = t('plural.aboutMinutes', { n: minutes }, minutes);

  return {
    title: t('reminders.notificationTitle', { n: count }, count),
    body: firstTerm
      ? t('reminders.notificationBody', { term: firstTerm, duration })
      : t('reminders.notificationBodyShort', { duration }),
  };
}

let preparing: Promise<void> | null = null;

/** The channel and the "Start" / "In an hour" buttons, in the interface language. */
function prepare(): Promise<void> {
  preparing ??= (async () => {
    await LocalNotifications.createChannel({
      id: CHANNEL,
      name: t('reminders.title'),
      importance: 4,
      visibility: 1,
    }).catch(() => undefined);
  })();

  return preparing.then(() =>
    LocalNotifications.registerActionTypes({
      types: [
        {
          id: ACTIONS,
          actions: [
            { id: 'start', title: t('reminders.start'), foreground: true },
            { id: 'snooze', title: t('reminders.snooze') },
          ],
        },
      ],
    }),
  );
}

async function cancelScheduled(): Promise<void> {
  const { notifications } = await LocalNotifications.getPending();
  const ours = notifications.filter((notification) => notification.id >= FIRST_ID);
  if (ours.length > 0) await LocalNotifications.cancel({ notifications: ours.map(({ id }) => ({ id })) });
}

let planning: Promise<void> | null = null;

/** Plans the coming week's reminders again; quietly does nothing off-device. */
export function rescheduleReminders(): Promise<void> {
  if (!remindersSupported()) return Promise.resolve();

  // One plan at a time; a request during planning waits for it and plans again.
  planning = (planning ?? Promise.resolve()).then(plan).catch(() => undefined);
  return planning;
}

async function plan(): Promise<void> {
  const auth = useAuthStore();
  const reminders = auth.user?.reminders;

  if (!auth.token || !reminders?.enabled) {
    await cancelScheduled();
    return;
  }
  if (!(await notificationsAllowed())) return;

  let moments = upcomingMoments(reminders);

  if (reminders.skip_if_studied && auth.activeLanguage) {
    const { studied_today } = await fetchDashboard(auth.activeLanguage);
    if (studied_today) {
      const tomorrow = new Date();
      tomorrow.setHours(24, 0, 0, 0);
      moments = moments.filter((moment) => moment >= tomorrow);
    }
  }

  const forecast = moments.length > 0 ? await fetchReminderForecast(moments) : [];

  const notifications: LocalNotificationSchema[] = forecast
    .map((slot, i) => ({ slot, at: moments[i]! }))
    .filter(({ slot }) => slot.count > 0 || !reminders.only_when_due)
    .map(({ slot, at }, i) => ({
      id: FIRST_ID + i,
      ...notificationText(slot.count, slot.minutes, slot.first_term),
      schedule: { at, allowWhileIdle: true },
      channelId: CHANNEL,
      actionTypeId: ACTIONS,
      extra: { open: 'study' },
    }));

  await prepare();
  await cancelScheduled();
  if (notifications.length > 0) await LocalNotifications.schedule({ notifications });
}

/**
 * Tapping a reminder or its "Start" opens a study session; "In an hour"
 * brings the same reminder back an hour later.
 */
export function handleReminderActions(router: Router): void {
  if (!remindersSupported()) return;

  void LocalNotifications.addListener('localNotificationActionPerformed', ({ actionId, notification }) => {
    if (actionId === 'snooze') {
      const at = new Date(Date.now() + 60 * 60 * 1000);
      void LocalNotifications.schedule({
        notifications: [
          {
            ...notification,
            id: SNOOZE_ID,
            schedule: { at, allowWhileIdle: true },
            channelId: CHANNEL,
            actionTypeId: ACTIONS,
          },
        ],
      });
      return;
    }

    void router.push({ name: 'study' });
  });
}
