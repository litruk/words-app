<template>
  <q-page class="w-screen settings">
    <div class="settings__bar">
      <h1 class="settings__title">{{ t('settings.title') }}</h1>
    </div>

    <div class="settings__body">
      <router-link :to="{ name: 'account' }" class="card-link" :aria-label="t('settings.account')">
        <span class="card-link__avatar">{{ initial(auth.user?.name) }}</span>
        <span class="card-link__text">
          <span class="card-link__title">{{ auth.user?.name }}</span>
          <span class="card-link__hint card-link__hint--lg">{{ auth.user?.email }}</span>
        </span>
        <span class="card-link__chevron"><NIcon name="chevronRight" :size="20" /></span>
      </router-link>

      <section class="settings__section">
        <div class="settings__heading settings__heading--stack">
          <h2 class="w-section-label">{{ t('settings.nativeLanguage') }}</h2>
          <span class="settings__note">
            <NIcon name="globe" :size="16" />
            {{ t('settings.nativeHint') }}
          </span>
        </div>
        <div role="radiogroup" class="options" :aria-label="t('settings.nativeLanguage')">
          <label
            v-for="lang in LANGUAGES"
            :key="lang.code"
            class="w-option"
            :class="{ 'w-option--selected': native === lang.code }"
          >
            <LangBadge :code="lang.code" :variant="native === lang.code ? 'primary' : 'neutral'" :class="{ 'badge--ink': native === lang.code }" />
            <span class="w-option__text">
              <span class="w-option__name">{{ lang.endonym }}</span>
              <span v-if="native === lang.code && lang.code === deviceLanguage()" class="system-tag">{{ t('settings.asSystem') }}</span>
              <span v-else-if="native !== lang.code" class="w-option__hint">{{ t(`languageNames.${lang.code}`) }}</span>
            </span>
            <input type="radio" name="native" :checked="native === lang.code" :disabled="busy" @change="pickNative(lang.code)" />
          </label>
        </div>
      </section>

      <section class="settings__section">
        <div class="settings__heading">
          <h2 class="w-section-label">{{ t('settings.learning') }}</h2>
          <span class="settings__note">{{ t('settings.severalAllowed') }}</span>
        </div>
        <StudiedLanguages
          :model-value="studied"
          :native="native"
          :native-tag="t('settings.nativeTag')"
          :hint="entriesHint"
          @update:model-value="pickStudied"
        />
      </section>

      <router-link :to="{ name: 'reminders' }" class="card-link card-link--compact">
        <span class="card-link__icon"><NIcon name="bell" :size="22" /></span>
        <span class="card-link__text">
          <span class="card-link__title">{{ t('settings.reminders') }}</span>
          <span class="card-link__hint">{{ remindersLine }}</span>
        </span>
        <span class="card-link__chevron"><NIcon name="chevronRight" :size="20" /></span>
      </router-link>

      <button type="button" class="logout" @click="logout">
        <NIcon name="logout" :size="22" />
        {{ t('settings.logout') }}
      </button>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import LangBadge from '@/components/LangBadge.vue';
import NIcon from '@/components/NIcon.vue';
import StudiedLanguages from '@/components/StudiedLanguages.vue';
import { setLocale } from '@/i18n';
import { rescheduleReminders } from '@/services/reminders';
import { useAuthStore } from '@/stores/auth-store';
import { useWordsStore } from '@/stores/words-store';
import { initial, reminderSummary } from '@/utils/format';
import { deviceLanguage, LANGUAGES, type LanguageCode } from '@/utils/languages';
import { errorText, toast } from '@/utils/notify';

const { t, locale } = useI18n();
const auth = useAuthStore();
const words = useWordsStore();

const native = ref<LanguageCode>(auth.user?.native_language ?? 'en');
const studied = ref<LanguageCode[]>([...auth.studiedLanguages]);
const busy = ref(false);

watch(
  () => auth.user,
  (user) => {
    if (!user || busy.value) return;
    native.value = user.native_language;
    studied.value = [...user.studied_languages];
  },
);

const remindersLine = computed(() =>
  auth.user ? reminderSummary(t, locale.value, auth.user.reminders) : t('settings.remindersOff'),
);

function entriesHint(code: LanguageCode): string {
  const stats = words.statsFor(code);
  return stats && studied.value.includes(code)
    ? t('plural.entries', { n: stats.total }, stats.total)
    : t(`languageNames.${code}`);
}

async function save(nextNative: LanguageCode, nextStudied: LanguageCode[]) {
  const before = { native: native.value, studied: studied.value };
  native.value = nextNative;
  studied.value = nextStudied;

  busy.value = true;
  try {
    await auth.updateLanguages(nextNative, nextStudied);
    words.changed();
    void words.loadStats().catch(() => undefined);
    // Reminder texts are written in the interface language.
    void rescheduleReminders();
  } catch (e) {
    native.value = before.native;
    studied.value = before.studied;
    setLocale(before.native);
    toast(errorText(e));
  } finally {
    busy.value = false;
  }
}

function pickNative(code: LanguageCode) {
  setLocale(code);
  void save(
    code,
    studied.value.filter((language) => language !== code),
  );
}

function pickStudied(next: LanguageCode[]) {
  if (next.length === 0) {
    toast(t('settings.keepOne'));
    return;
  }
  void save(native.value, next);
}

async function logout() {
  await auth.logout();
}

onMounted(() => {
  void words.loadStats().catch(() => undefined);
});
</script>

<style scoped>
.settings {
  padding-bottom: 24px;
}

.settings__bar {
  display: flex;
  align-items: center;
  height: 64px;
  padding: 0 16px;
  flex-shrink: 0;
}

.settings__title {
  font-size: 28px;
  line-height: 36px;
  font-weight: 600;
  letter-spacing: -0.01em;
}

.settings__body {
  padding: 0 16px;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.settings__section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.settings__heading {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  padding: 0 4px;
}

.settings__heading--stack {
  flex-direction: column;
  align-items: stretch;
  gap: 2px;
}

.settings__note {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  line-height: 18px;
  color: var(--w-muted);
}

.settings__note svg {
  flex-shrink: 0;
}

.options {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.badge--ink {
  background: var(--w-ink);
}

.system-tag {
  font-size: 11px;
  font-weight: 600;
  color: var(--w-on-tonal);
  border: 1px solid #6f9e95;
  border-radius: 6px;
  padding: 1px 6px;
  align-self: center;
}

.card-link {
  display: flex;
  align-items: center;
  gap: 14px;
  min-height: 72px;
  padding: 0 8px 0 12px;
  border-radius: 20px;
  background: var(--w-container);
  color: var(--w-ink);
  text-decoration: none;
}

.card-link:hover {
  color: var(--w-ink);
}

.card-link--compact {
  min-height: 64px;
}

.card-link__avatar {
  width: 48px;
  height: 48px;
  border-radius: 24px;
  background: var(--w-tonal);
  color: var(--w-on-tonal);
  font-size: 18px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.card-link__icon {
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

.card-link__text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.card-link__title {
  font-size: 16px;
  font-weight: 600;
}

.card-link__hint {
  font-size: 13px;
  color: var(--w-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.card-link__hint--lg {
  font-size: 14px;
}

.card-link__chevron {
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--w-muted);
}

.logout {
  align-self: flex-start;
  min-height: 44px;
  padding: 0 12px 0 8px;
  border: none;
  border-radius: 22px;
  background: transparent;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 600;
  color: var(--w-danger);
  cursor: pointer;
}
</style>
