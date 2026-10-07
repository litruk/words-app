<template>
  <q-page class="w-screen forgot">
    <div class="w-topbar">
      <button
        v-if="sentTo"
        type="button"
        class="w-icon-btn"
        :aria-label="t('common.back')"
        @click="sentTo = null"
      >
        <NIcon name="back" />
      </button>
      <router-link v-else :to="{ name: 'login' }" class="w-icon-btn" :aria-label="t('common.back')">
        <NIcon name="back" />
      </router-link>
    </div>

    <div v-if="!sentTo" class="forgot__body">
      <span class="forgot__badge" aria-hidden="true">
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <rect x="5" y="10.5" width="14" height="10" rx="2" />
          <path d="M8 10.5V7.5a4 4 0 0 1 8 0v3" />
          <path d="M12 14.5v2" />
        </svg>
      </span>
      <div class="forgot__intro">
        <h1 class="w-title">{{ t('auth.forgotTitle') }}</h1>
        <p class="w-lead">{{ t('auth.forgotLead') }}</p>
      </div>

      <form class="forgot__form" novalidate @submit.prevent="send">
        <TextField
          v-model="email"
          :label="t('auth.email')"
          type="email"
          autocomplete="email"
          inputmode="email"
          enterkeyhint="send"
          :error="error"
        />
        <button type="submit" class="w-btn" :disabled="busy">
          <span v-if="busy" class="w-spinner" />
          <template v-else>{{ t('auth.sendLink') }}</template>
        </button>
      </form>

      <div class="forgot__spacer" />

      <div class="forgot__switch">
        <span>{{ t('auth.remembered') }}</span>
        <router-link :to="{ name: 'login', query: email ? { email } : {} }" class="w-link-btn">{{ t('auth.signIn') }}</router-link>
      </div>
    </div>

    <div v-else class="forgot__body forgot__body--sent">
      <div class="sent">
        <span class="sent__badge" aria-hidden="true"><NIcon name="mail" :size="44" :stroke-width="1.7" /></span>
        <div class="sent__text">
          <h1 class="w-title">{{ t('auth.sentTitle') }}</h1>
          <i18n-t keypath="auth.sentLead" tag="p" scope="global" class="w-lead">
            <template #email><strong>{{ sentTo }}</strong></template>
          </i18n-t>
        </div>
        <div class="sent__hint">
          <NIcon name="alert" :size="20" />
          <span>{{ t('auth.sentHint') }}</span>
        </div>
      </div>

      <div class="sent__actions">
        <button v-if="mailApp" type="button" class="w-btn" @click="openMail">{{ t('auth.openMail') }}</button>
        <button type="button" class="w-btn w-btn--outline sent__resend" :disabled="countdown > 0 || busy" @click="send">
          {{ countdown > 0 ? t('auth.resendIn', { time: clock }) : t('auth.resend') }}
        </button>
        <router-link :to="{ name: 'login', query: { email: sentTo } }" class="w-link-btn sent__back">
          {{ t('auth.backToSignIn') }}
        </router-link>
      </div>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { Capacitor } from '@capacitor/core';
import { AppLauncher } from '@capacitor/app-launcher';
import NIcon from '@/components/NIcon.vue';
import TextField from '@/components/TextField.vue';
import { useAuthStore } from '@/stores/auth-store';
import { errorText, toast } from '@/utils/notify';
import { isEmail } from '@/utils/validation';

/** The server lets a new link out once a minute. */
const RESEND_AFTER = 60;
const GMAIL = 'com.google.android.gm';

const { t } = useI18n();
const auth = useAuthStore();
const route = useRoute();

const email = ref(typeof route.query.email === 'string' ? route.query.email : '');
const sentTo = ref<string | null>(null);
const error = ref<string | null>(null);
const busy = ref(false);
const countdown = ref(0);
const mailApp = ref(false);
let timer: ReturnType<typeof setInterval> | undefined;

const clock = computed(() => `${Math.floor(countdown.value / 60)}:${String(countdown.value % 60).padStart(2, '0')}`);

onMounted(async () => {
  if (Capacitor.isNativePlatform()) {
    mailApp.value = await AppLauncher.canOpenUrl({ url: GMAIL })
      .then(({ value }) => value)
      .catch(() => false);
  }
});

onBeforeUnmount(() => clearInterval(timer));

async function send() {
  const address = email.value.trim();
  error.value = !address ? t('auth.required') : isEmail(address) ? null : t('auth.invalidEmail');
  if (error.value) return;

  busy.value = true;
  try {
    await auth.forgotPassword(address);
    if (sentTo.value) toast(t('auth.resent'));
    sentTo.value = address;
    startCountdown();
  } catch (e) {
    error.value = errorText(e);
    if (sentTo.value) toast(error.value);
  } finally {
    busy.value = false;
  }
}

function startCountdown() {
  countdown.value = RESEND_AFTER;
  clearInterval(timer);
  timer = setInterval(() => {
    countdown.value = Math.max(0, countdown.value - 1);
    if (countdown.value === 0) clearInterval(timer);
  }, 1000);
}

function openMail() {
  void AppLauncher.openUrl({ url: GMAIL });
}
</script>

<style scoped>
.forgot {
  padding-bottom: calc(28px + var(--w-safe-bottom));
}

.forgot__body {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding: 8px 24px 0;
}

.forgot__badge {
  width: 64px;
  height: 64px;
  border-radius: 32px;
  background: var(--w-tonal);
  color: var(--w-primary);
  display: flex;
  align-items: center;
  justify-content: center;
}

.forgot__intro {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 24px;
}

.forgot__form {
  display: flex;
  flex-direction: column;
  gap: 24px;
  margin-top: 32px;
}

.forgot__spacer {
  flex: 1;
  min-height: 24px;
}

.forgot__switch {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  font-size: 15px;
  color: var(--w-muted);
}

.forgot__switch .w-link-btn {
  font-size: 15px;
}

.forgot__body--sent {
  padding-top: 0;
  align-items: center;
}

.sent {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 24px;
  padding-bottom: 24px;
  width: 100%;
}

.sent__badge {
  width: 96px;
  height: 96px;
  border-radius: 48px;
  background: var(--w-primary-soft);
  color: var(--w-primary);
  display: flex;
  align-items: center;
  justify-content: center;
}

.sent__text {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  text-align: center;
}

.sent__text strong {
  font-weight: 600;
  color: var(--w-ink);
}

.sent__hint {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  width: 100%;
  box-sizing: border-box;
  padding: 14px 16px;
  border-radius: 16px;
  background: var(--w-container);
  font-size: 14px;
  line-height: 20px;
  color: var(--w-muted);
}

.sent__hint svg {
  flex-shrink: 0;
}

.sent__actions {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 100%;
}

.sent__resend:disabled {
  background: transparent;
  border-color: var(--w-divider);
  color: var(--w-disabled);
  font-weight: 500;
}

.sent__back {
  align-self: center;
  font-size: 15px;
}
</style>
