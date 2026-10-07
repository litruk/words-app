<template>
  <q-page class="w-screen auth">
    <div class="auth__top" />
    <AppMark />

    <div class="auth__intro">
      <h1 class="auth__name">{{ t('auth.appName') }}</h1>
      <p class="auth__tagline">{{ t('auth.tagline') }}</p>
    </div>

    <form class="auth__form" novalidate @submit.prevent="submit">
      <TextField
        v-model="email"
        :label="t('auth.email')"
        type="email"
        autocomplete="email"
        inputmode="email"
        enterkeyhint="next"
        :error="emailError"
      />
      <div class="auth__password">
        <TextField
          v-model="password"
          :label="t('auth.password')"
          type="password"
          autocomplete="current-password"
          enterkeyhint="go"
          :error="passwordError"
        />
        <router-link :to="{ name: 'forgot-password', query: email ? { email } : {} }" class="w-link-btn auth__forgot">
          {{ t('auth.forgot') }}
        </router-link>
      </div>
      <button type="submit" class="w-btn" :disabled="busy">
        <span v-if="busy" class="w-spinner" />
        <template v-else>{{ t('auth.signIn') }}</template>
      </button>
    </form>

    <div class="auth__spacer" />

    <div class="auth__switch">
      <span>{{ t('auth.noAccount') }}</span>
      <router-link :to="{ name: 'register' }" class="w-link-btn">{{ t('auth.signUp') }}</router-link>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import AppMark from '@/components/AppMark.vue';
import TextField from '@/components/TextField.vue';
import { GraphQLError } from '@/services/graphql-client';
import { useAuthStore } from '@/stores/auth-store';
import { errorText, toast } from '@/utils/notify';
import { isEmail } from '@/utils/validation';

const { t } = useI18n();
const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const email = ref(typeof route.query.email === 'string' ? route.query.email : '');
const password = ref('');
const emailError = ref<string | null>(null);
const passwordError = ref<string | null>(null);
const busy = ref(false);

async function submit() {
  emailError.value = !email.value.trim() ? t('auth.required') : isEmail(email.value) ? null : t('auth.invalidEmail');
  passwordError.value = password.value ? null : t('auth.required');
  if (emailError.value || passwordError.value) return;

  busy.value = true;
  try {
    await auth.login(email.value.trim(), password.value);
    await router.replace(auth.needsLanguages ? { name: 'onboarding' } : { name: 'home' });
  } catch (e) {
    // Wrong credentials are shown under the password, as on the design board.
    if (e instanceof GraphQLError) passwordError.value = errorText(e);
    else toast(errorText(e));
  } finally {
    busy.value = false;
  }
}
</script>

<style scoped>
.auth {
  padding: var(--w-safe-top) 24px calc(28px + var(--w-safe-bottom));
}

.auth__top {
  height: 80px;
  flex-shrink: 0;
}

.auth__intro {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 24px;
}

.auth__name {
  font-size: 32px;
  line-height: 40px;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.auth__tagline {
  font-size: 16px;
  line-height: 24px;
  color: var(--w-muted);
}

.auth__form {
  display: flex;
  flex-direction: column;
  gap: 20px;
  margin-top: 40px;
}

.auth__password {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.auth__forgot {
  align-self: flex-end;
}

.auth__spacer {
  flex: 1;
  min-height: 24px;
}

.auth__switch {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  font-size: 15px;
  color: var(--w-muted);
}

.auth__switch .w-link-btn {
  font-size: 15px;
}
</style>
