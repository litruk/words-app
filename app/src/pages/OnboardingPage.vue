<template>
  <q-page class="w-screen onboarding">
    <StepHeader :current="2" :total="2" />

    <div class="onboarding__body">
      <div class="onboarding__intro">
        <h1 class="w-title">{{ t('auth.languagesTitle') }}</h1>
        <p class="w-lead">{{ t('auth.languagesLead') }}</p>
      </div>

      <section class="onboarding__section">
        <h2 class="w-section-label onboarding__label">{{ t('auth.nativeLanguage') }}</h2>
        <div role="radiogroup" :aria-label="t('auth.nativeLanguage')" class="onboarding__native">
          <label
            v-for="lang in LANGUAGES"
            :key="lang.code"
            class="native-option"
            :class="{ 'native-option--selected': native === lang.code }"
          >
            <input type="radio" name="native" :checked="native === lang.code" @change="pickNative(lang.code)" />
            <span>{{ lang.endonym }}</span>
          </label>
        </div>
        <span v-if="native === deviceLanguage()" class="onboarding__detected">
          <NIcon name="phone" :size="16" />
          {{ t('auth.detectedFromSystem') }}
        </span>
      </section>

      <section class="onboarding__section">
        <div class="onboarding__heading">
          <h2 class="w-section-label">{{ t('auth.learning') }}</h2>
          <span class="muted">{{ t('auth.severalAllowed') }}</span>
        </div>
        <StudiedLanguages
          v-model="studied"
          :native="native"
          :native-tag="t('auth.nativeTag')"
          :hint="(code) => t(`languageNames.${code}`)"
        />
      </section>

      <div class="onboarding__spacer" />

      <span v-if="error" class="w-field__help w-field__help--error" role="alert">{{ error }}</span>
      <button type="button" class="w-btn onboarding__start" :disabled="busy" @click="start">
        <span v-if="busy" class="w-spinner" />
        <template v-else>{{ studied.length > 0 ? t('auth.start', { n: studied.length }, studied.length) : t('auth.pickLanguage') }}</template>
      </button>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import NIcon from '@/components/NIcon.vue';
import StepHeader from '@/components/StepHeader.vue';
import StudiedLanguages from '@/components/StudiedLanguages.vue';
import { setLocale } from '@/i18n';
import { allowNotifications, rescheduleReminders } from '@/services/reminders';
import { useAuthStore } from '@/stores/auth-store';
import { deviceLanguage, LANGUAGES, type LanguageCode } from '@/utils/languages';
import { errorText } from '@/utils/notify';

const { t } = useI18n();
const auth = useAuthStore();
const router = useRouter();

const native = ref<LanguageCode>(auth.user?.native_language ?? deviceLanguage());
// English is what most people come for; a native English speaker gets nothing preselected.
const studied = ref<LanguageCode[]>(auth.studiedLanguages.length > 0 ? [...auth.studiedLanguages] : native.value === 'en' ? [] : ['en']);
const busy = ref(false);
const error = ref<string | null>(null);

function pickNative(code: LanguageCode) {
  native.value = code;
  studied.value = studied.value.filter((c) => c !== code);
  // The interface follows the native language right away.
  setLocale(code);
}

async function start() {
  if (studied.value.length === 0) {
    error.value = t('auth.pickLanguage');
    return;
  }

  error.value = null;
  busy.value = true;
  try {
    await auth.updateLanguages(native.value, studied.value);
    auth.setActiveLanguage(studied.value[0]!);
    // Reminders are on by default; this is the moment to ask for notifications.
    if (await allowNotifications().catch(() => false)) void rescheduleReminders();
    await router.replace({ name: 'home' });
  } catch (e) {
    error.value = errorText(e);
  } finally {
    busy.value = false;
  }
}
</script>

<style scoped>
.onboarding {
  padding-bottom: calc(28px + var(--w-safe-bottom));
}

.onboarding__body {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding: 0 16px;
}

.onboarding__intro {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 24px;
  padding: 0 8px;
}

.onboarding__section {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 28px;
}

.onboarding__section + .onboarding__section {
  margin-top: 24px;
  gap: 8px;
}

.onboarding__label {
  padding: 0 8px;
}

.onboarding__heading {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  padding: 0 8px;
  font-size: 13px;
}

.onboarding__native {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.native-option {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 56px;
  padding: 0 14px;
  border-radius: 14px;
  border: 1px solid var(--w-outline-soft);
  box-sizing: border-box;
  font-size: 16px;
  font-weight: 500;
  cursor: pointer;
}

.native-option input {
  width: 20px;
  height: 20px;
  margin: 0;
  accent-color: var(--w-primary);
  flex-shrink: 0;
}

.native-option--selected {
  border-color: transparent;
  background: var(--w-tonal);
  font-weight: 600;
}

.onboarding__detected {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 0 8px;
  font-size: 13px;
  line-height: 18px;
  color: var(--w-muted);
}

.onboarding__spacer {
  flex: 1;
  min-height: 16px;
}

.onboarding__start {
  margin: 16px 8px 0;
}
</style>
