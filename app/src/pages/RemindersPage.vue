<template>
  <q-page class="w-screen reminders">
    <div class="w-topbar">
      <button type="button" class="w-icon-btn" :aria-label="t('common.back')" @click="back">
        <NIcon name="back" />
      </button>
      <h1 class="w-topbar__title">{{ t('reminders.title') }}</h1>
    </div>

    <div class="reminders__body">
      <div class="master">
        <div class="master__text">
          <span class="master__title">{{ t('reminders.master') }}</span>
          <span class="master__hint">{{ t('reminders.masterHint') }}</span>
        </div>
        <SwitchToggle v-model="settings.enabled" :label="t('reminders.master')" />
      </div>

      <div v-if="denied && settings.enabled" class="w-banner" role="alert">
        <NIcon name="bell" :size="22" />
        <div class="w-banner__text">
          <span>{{ t('reminders.denied') }}</span>
          <button type="button" class="banner-action" @click="openNotificationSettings">{{ t('add.openSettings') }}</button>
        </div>
      </div>

      <section class="block" :class="{ 'block--off': !settings.enabled }">
        <h2 class="w-section-label">{{ t('reminders.time') }}</h2>
        <div class="w-divider-list times">
          <div v-for="(time, i) in settings.times" :key="time.time" class="time">
            <span class="time__icon" :class="{ 'time__icon--off': !time.enabled }"><NIcon name="clock" :size="22" /></span>
            <button type="button" class="time__text" :aria-label="t('common.edit')" @click="editTime(i)">
              <span class="time__value" :class="{ 'time__value--off': !time.enabled }">{{ time.time }}</span>
              <span class="time__part">{{ t(`reminders.${partOfDay(time.time)}`) }}</span>
            </button>
            <SwitchToggle v-model="time.enabled" :label="t('reminders.reminderAt', { time: time.time })" />
          </div>
        </div>
        <button v-if="settings.times.length < 6" type="button" class="add-time" @click="addTime">
          <NIcon name="plus" :size="20" :stroke-width="2.1" />
          {{ t('reminders.addTime') }}
        </button>
      </section>

      <section class="block" :class="{ 'block--off': !settings.enabled }">
        <h2 class="w-section-label">{{ t('reminders.days') }}</h2>
        <div role="group" class="days" :aria-label="t('reminders.daysLabel')">
          <button
            v-for="(name, i) in weekdayNames(locale)"
            :key="i"
            type="button"
            class="day"
            :aria-pressed="settings.days.includes(i + 1)"
            @click="toggleDay(i + 1)"
          >
            {{ name }}
          </button>
        </div>
      </section>

      <section class="block" :class="{ 'block--off': !settings.enabled }">
        <h2 class="w-section-label">{{ t('reminders.conditions') }}</h2>
        <div class="w-divider-list">
          <div class="condition">
            <div class="condition__text">
              <span class="condition__title">{{ t('reminders.onlyWhenDue') }}</span>
              <span class="condition__hint">{{ t('reminders.onlyWhenDueHint') }}</span>
            </div>
            <SwitchToggle v-model="settings.only_when_due" :label="t('reminders.onlyWhenDue')" />
          </div>
          <div class="condition">
            <div class="condition__text">
              <span class="condition__title">{{ t('reminders.skipIfStudied') }}</span>
              <span class="condition__hint">{{ t('reminders.skipIfStudiedHint') }}</span>
            </div>
            <SwitchToggle v-model="settings.skip_if_studied" :label="t('reminders.skipIfStudied')" />
          </div>
        </div>
      </section>

      <section class="block">
        <h2 class="w-section-label">{{ t('reminders.preview') }}</h2>
        <div class="preview">
          <div class="preview__app">
            <span class="preview__icon">Aя</span>
            <span>{{ t('auth.appName') }} · {{ preview.time }}</span>
          </div>
          <span class="preview__title">{{ preview.title }}</span>
          <span class="preview__body">{{ preview.body }}</span>
          <div class="preview__actions">
            <span>{{ t('reminders.start') }}</span>
            <span>{{ t('reminders.snooze') }}</span>
          </div>
        </div>
      </section>
    </div>

    <q-dialog v-model="timeDialog">
      <div class="time-dialog">
        <q-time v-model="editingTime" format24h flat color="primary" />
        <div class="time-dialog__actions">
          <button v-if="editingIndex !== null" type="button" class="w-link-btn time-dialog__remove" @click="removeTime">
            {{ t('common.delete') }}
          </button>
          <div class="w-topbar__spacer" />
          <button type="button" class="w-link-btn" @click="timeDialog = false">{{ t('common.cancel') }}</button>
          <button type="button" class="w-link-btn" @click="applyTime">{{ t('common.save') }}</button>
        </div>
      </div>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { AndroidSettings, IOSSettings, NativeSettings } from 'capacitor-native-settings';
import NIcon from '@/components/NIcon.vue';
import SwitchToggle from '@/components/SwitchToggle.vue';
import {
  allowNotifications,
  notificationsAllowed,
  notificationText,
  remindersSupported,
  rescheduleReminders,
  upcomingMoments,
} from '@/services/reminders';
import { fetchReminderForecast } from '@/services/study-api';
import type { ReminderForecast, Reminders } from '@/services/types';
import { useAuthStore } from '@/stores/auth-store';
import { weekdayNames } from '@/utils/format';
import { errorText, toast } from '@/utils/notify';

const { t, locale } = useI18n();
const router = useRouter();
const auth = useAuthStore();

/** A plain copy: store state is a reactive proxy, which structuredClone refuses. */
const copy = (reminders: Reminders): Reminders => JSON.parse(JSON.stringify(reminders)) as Reminders;

const settings = reactive<Reminders>(
  copy(auth.user?.reminders ?? { enabled: true, times: [], days: [1, 2, 3, 4, 5], only_when_due: true, skip_if_studied: false }),
);
const denied = ref(false);
const forecast = ref<ReminderForecast | null>(null);

const timeDialog = ref(false);
const editingTime = ref('09:00');
const editingIndex = ref<number | null>(null);

function partOfDay(time: string): 'morning' | 'afternoon' | 'evening' | 'night' {
  const hour = Number(time.split(':')[0]);
  if (hour >= 5 && hour < 12) return 'morning';
  if (hour >= 12 && hour < 17) return 'afternoon';
  if (hour >= 17 && hour < 23) return 'evening';
  return 'night';
}

/** What the next reminder would say, from what will be due then. */
const preview = computed(() => {
  const next = upcomingMoments(settings)[0] ?? null;
  const count = forecast.value?.count ?? 0;
  const text = notificationText(count, Math.max(1, forecast.value?.minutes ?? 1), forecast.value?.first_term ?? null);

  return { time: next ? next.toTimeString().slice(0, 5) : settings.times.find((time) => time.enabled)?.time ?? '20:30', ...text };
});

async function loadForecast() {
  const next = upcomingMoments(settings)[0];
  if (!next) return;
  forecast.value = (await fetchReminderForecast([next]).catch(() => []))[0] ?? null;
}

function toggleDay(day: number) {
  settings.days = settings.days.includes(day)
    ? settings.days.filter((candidate) => candidate !== day)
    : [...settings.days, day].sort((a, b) => a - b);
}

function editTime(index: number) {
  editingIndex.value = index;
  editingTime.value = settings.times[index]!.time;
  timeDialog.value = true;
}

function addTime() {
  editingIndex.value = null;
  editingTime.value = '18:00';
  timeDialog.value = true;
}

function applyTime() {
  const time = editingTime.value;
  const others = settings.times.filter((_, i) => i !== editingIndex.value);

  if (!others.some((candidate) => candidate.time === time)) {
    settings.times = [...others, { time, enabled: true }].sort((a, b) => a.time.localeCompare(b.time));
  }
  timeDialog.value = false;
}

function removeTime() {
  settings.times = settings.times.filter((_, i) => i !== editingIndex.value);
  timeDialog.value = false;
}

async function openNotificationSettings() {
  await NativeSettings.open({
    optionAndroid: AndroidSettings.AppNotification,
    optionIOS: IOSSettings.App,
  }).catch(() => undefined);
}

let saveTimer: ReturnType<typeof setTimeout> | undefined;

async function save() {
  try {
    await auth.updateReminders(copy(settings));
    void rescheduleReminders();
    void loadForecast();
  } catch (e) {
    toast(errorText(e));
  }
}

watch(
  settings,
  () => {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => void save(), 500);
  },
  { deep: true },
);

// Turning reminders on is when the app asks to show notifications.
watch(
  () => settings.enabled,
  async (enabled) => {
    if (enabled && remindersSupported()) denied.value = !(await allowNotifications());
  },
);

function back() {
  if (window.history.state?.back) router.back();
  else void router.replace({ name: 'settings' });
}

onMounted(async () => {
  void loadForecast();
  if (remindersSupported()) denied.value = !(await notificationsAllowed());
});

onBeforeUnmount(() => {
  // Leaving right after a change still saves it.
  if (saveTimer) {
    clearTimeout(saveTimer);
    void save();
  }
});
</script>

<style scoped>
.reminders {
  padding-bottom: calc(24px + var(--w-safe-bottom));
}

.reminders__body {
  padding: 0 16px;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.master {
  background: var(--w-container);
  border-radius: 20px;
  padding: 16px 16px 16px 20px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.master__text,
.condition__text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.master__title {
  font-size: 16px;
  line-height: 22px;
  font-weight: 600;
}

.master__hint {
  font-size: 14px;
  line-height: 20px;
  color: var(--w-muted);
}

.banner-action {
  align-self: flex-start;
  margin-left: -8px;
  height: 40px;
  padding: 0 8px;
  border: none;
  border-radius: 20px;
  background: transparent;
  color: #8c1d18;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.block {
  display: flex;
  flex-direction: column;
  gap: 8px;
  transition: opacity 0.2s ease;
}

.block--off {
  opacity: 0.5;
}

.time {
  display: flex;
  align-items: center;
  gap: 16px;
  min-height: 64px;
}

.time__icon {
  width: 40px;
  height: 40px;
  border-radius: 20px;
  background: var(--w-tonal);
  color: var(--w-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.time__icon--off {
  background: var(--w-container-high);
  color: var(--w-muted);
}

.time__text {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  border: none;
  background: transparent;
  padding: 0;
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.time__value {
  font-size: 22px;
  line-height: 28px;
  font-weight: 600;
}

.time__value--off {
  color: var(--w-muted);
}

.time__part {
  font-size: 13px;
  color: var(--w-muted);
}

.add-time {
  align-self: flex-start;
  height: 44px;
  padding: 0 12px 0 8px;
  margin-top: 4px;
  border: none;
  border-radius: 22px;
  background: transparent;
  color: var(--w-primary);
  font-size: 14px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.days {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 8px;
}

.day {
  height: 44px;
  border-radius: 22px;
  border: 1px solid var(--w-outline);
  background: transparent;
  color: var(--w-muted);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  padding: 0;
}

.day[aria-pressed='true'] {
  border-color: transparent;
  background: var(--w-primary);
  color: var(--w-on-primary);
  font-weight: 600;
}

.condition {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 72px;
}

.condition__title {
  font-size: 16px;
  line-height: 22px;
  font-weight: 500;
}

.condition__hint {
  font-size: 13px;
  line-height: 18px;
  color: var(--w-muted);
}

.preview {
  background: var(--w-surface);
  border: 1px solid var(--w-divider);
  border-radius: 20px;
  padding: 14px 16px 8px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.preview__app {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: var(--w-muted);
}

.preview__icon {
  width: 20px;
  height: 20px;
  border-radius: 6px;
  background: var(--w-primary);
  color: var(--w-on-primary);
  font-size: 9px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}

.preview__title {
  font-size: 15px;
  line-height: 20px;
  font-weight: 600;
}

.preview__body {
  font-size: 14px;
  line-height: 20px;
  color: var(--w-muted);
}

.preview__actions {
  display: flex;
  gap: 4px;
  margin-left: -8px;
}

.preview__actions span {
  height: 40px;
  padding: 0 8px;
  display: flex;
  align-items: center;
  color: var(--w-primary);
  font-size: 13px;
  font-weight: 600;
}

.time-dialog {
  background: var(--w-bg);
  border-radius: 28px;
  padding: 12px 12px 8px;
}

.time-dialog__actions {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 0 8px;
}

.time-dialog__remove {
  color: var(--w-danger);
}
</style>
