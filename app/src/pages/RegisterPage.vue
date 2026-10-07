<template>
  <q-page class="w-screen register">
    <StepHeader :current="1" :total="2" :back="{ name: 'login' }" />

    <div class="register__body">
      <div class="register__intro">
        <h1 class="w-title">{{ t('auth.createTitle') }}</h1>
        <p class="w-lead">{{ t('auth.createLead') }}</p>
      </div>

      <form class="register__form" novalidate @submit.prevent="submit">
        <TextField v-model="name" :label="t('auth.name')" autocomplete="name" enterkeyhint="next" :error="errors.name" />
        <TextField
          v-model="email"
          :label="t('auth.email')"
          type="email"
          autocomplete="email"
          inputmode="email"
          enterkeyhint="next"
          :error="errors.email"
        />
        <TextField
          v-model="password"
          :label="t('auth.password')"
          type="password"
          autocomplete="new-password"
          enterkeyhint="done"
          :error="errors.password"
          :hint="passwordHint"
          :hint-ok="password.length >= MIN_PASSWORD"
        />

        <div class="register__terms">
          <input id="register-terms" v-model="accepted" type="checkbox" />
          <label for="register-terms">
            <i18n-t keypath="auth.terms" tag="span" scope="global">
              <template #terms><strong>{{ t('auth.termsLink') }}</strong></template>
              <template #privacy><strong>{{ t('auth.privacyLink') }}</strong></template>
            </i18n-t>
          </label>
        </div>
        <span v-if="errors.terms" class="w-field__help w-field__help--error" role="alert">{{ errors.terms }}</span>

        <button type="submit" class="w-btn" :disabled="busy">
          <span v-if="busy" class="w-spinner" />
          <template v-else>{{ t('common.continue') }}</template>
        </button>
      </form>

      <div class="register__spacer" />

      <div class="register__switch">
        <span>{{ t('auth.haveAccount') }}</span>
        <router-link :to="{ name: 'login' }" class="w-link-btn">{{ t('auth.signIn') }}</router-link>
      </div>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import StepHeader from '@/components/StepHeader.vue';
import TextField from '@/components/TextField.vue';
import { GraphQLError } from '@/services/graphql-client';
import { useAuthStore } from '@/stores/auth-store';
import { currentLocale } from '@/i18n';
import { errorText, toast } from '@/utils/notify';
import { isEmail, MIN_PASSWORD } from '@/utils/validation';

const { t } = useI18n();
const auth = useAuthStore();
const router = useRouter();

const name = ref('');
const email = ref('');
const password = ref('');
const accepted = ref(false);
const busy = ref(false);
const errors = reactive<Record<'name' | 'email' | 'password' | 'terms', string | null>>({
  name: null,
  email: null,
  password: null,
  terms: null,
});

const passwordHint = computed(() =>
  password.value.length === 0 || password.value.length >= MIN_PASSWORD
    ? t('auth.passwordRule')
    : t('auth.passwordRuleLeft', { n: MIN_PASSWORD - password.value.length }),
);

async function submit() {
  errors.name = name.value.trim() ? null : t('auth.required');
  errors.email = !email.value.trim() ? t('auth.required') : isEmail(email.value) ? null : t('auth.invalidEmail');
  errors.password = password.value.length >= MIN_PASSWORD ? null : t('auth.passwordRule');
  errors.terms = accepted.value ? null : t('auth.acceptTerms');
  if (errors.name || errors.email || errors.password || errors.terms) return;

  busy.value = true;
  try {
    // The interface language doubles as the first guess at the native one.
    await auth.register(name.value.trim(), email.value.trim(), password.value, currentLocale());
    await router.replace({ name: 'onboarding' });
  } catch (e) {
    if (e instanceof GraphQLError) errors.email = errorText(e);
    else toast(errorText(e));
  } finally {
    busy.value = false;
  }
}
</script>

<style scoped>
.register {
  padding-bottom: calc(28px + var(--w-safe-bottom));
}

.register__body {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding: 0 24px;
}

.register__intro {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 24px;
}

.register__form {
  display: flex;
  flex-direction: column;
  gap: 24px;
  margin-top: 32px;
}

.register__terms {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.register__terms input {
  width: 20px;
  height: 20px;
  margin: 2px 0 0;
  accent-color: var(--w-primary);
  flex-shrink: 0;
}

.register__terms label {
  font-size: 14px;
  line-height: 21px;
  color: var(--w-muted);
}

.register__terms strong {
  font-weight: 600;
  color: var(--w-primary);
}

.register__spacer {
  flex: 1;
  min-height: 24px;
}

.register__switch {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  font-size: 15px;
  color: var(--w-muted);
}

.register__switch .w-link-btn {
  font-size: 15px;
}
</style>
