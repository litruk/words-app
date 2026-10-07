<template>
  <q-page class="w-screen account">
    <div class="w-topbar account__bar">
      <button type="button" class="w-icon-btn" :aria-label="t('common.back')" @click="back">
        <NIcon name="back" />
      </button>
      <h1 class="w-topbar__title account__title">{{ t('account.title') }}</h1>
      <button type="button" class="w-link-btn account__save" :disabled="savingProfile || !profileChanged" @click="saveProfile">
        {{ t('common.save') }}
      </button>
    </div>

    <div class="account__body">
      <div class="account__avatar-row">
        <span class="account__avatar" aria-hidden="true">{{ initial(name || auth.user?.name) }}</span>
      </div>

      <section class="account__section">
        <h2 class="w-section-label account__label">{{ t('account.profile') }}</h2>
        <div class="account__fields">
          <TextField v-model="name" :label="t('auth.name')" autocomplete="name" :error="nameError" />
          <TextField v-model="email" :label="t('auth.email')" type="email" autocomplete="email" inputmode="email" :error="emailError" />
        </div>
      </section>

      <section class="account__section">
        <h2 class="w-section-label account__label">{{ t('account.changePassword') }}</h2>
        <div class="account__fields">
          <TextField
            v-model="currentPassword"
            :label="t('account.currentPassword')"
            type="password"
            autocomplete="current-password"
            :error="currentError"
          />
          <TextField
            v-model="newPassword"
            :label="t('account.newPassword')"
            type="password"
            autocomplete="new-password"
            :hint="passwordHint"
            :hint-ok="newPassword.length >= MIN_PASSWORD"
            :error="newError"
          />
          <button type="button" class="w-btn w-btn--md w-btn--tonal" :disabled="savingPassword" @click="savePassword">
            <span v-if="savingPassword" class="w-spinner" />
            <template v-else>{{ t('account.updatePassword') }}</template>
          </button>
        </div>
      </section>

      <div class="account__spacer" />

      <button type="button" class="account__delete" @click="deleteOpen = true">{{ t('account.deleteAccount') }}</button>
    </div>

    <ConfirmDialog
      v-model="deleteOpen"
      :title="t('account.deleteTitle')"
      :text="t('account.deleteText')"
      :confirm="t('account.deleteConfirm')"
      danger
      :busy="deleting"
      @confirm="deleteAccount"
    >
      <TextField v-model="deletePassword" :label="t('auth.password')" type="password" autocomplete="current-password" :error="deleteError" />
    </ConfirmDialog>
  </q-page>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import NIcon from '@/components/NIcon.vue';
import TextField from '@/components/TextField.vue';
import { GraphQLError } from '@/services/graphql-client';
import { useAuthStore } from '@/stores/auth-store';
import { initial } from '@/utils/format';
import { errorText, toast } from '@/utils/notify';
import { isEmail, MIN_PASSWORD } from '@/utils/validation';

const { t } = useI18n();
const router = useRouter();
const auth = useAuthStore();

const name = ref(auth.user?.name ?? '');
const email = ref(auth.user?.email ?? '');
const nameError = ref<string | null>(null);
const emailError = ref<string | null>(null);
const savingProfile = ref(false);

const currentPassword = ref('');
const newPassword = ref('');
const currentError = ref<string | null>(null);
const newError = ref<string | null>(null);
const savingPassword = ref(false);

const deleteOpen = ref(false);
const deletePassword = ref('');
const deleteError = ref<string | null>(null);
const deleting = ref(false);

const profileChanged = computed(
  () => name.value.trim() !== (auth.user?.name ?? '') || email.value.trim() !== (auth.user?.email ?? ''),
);

const passwordHint = computed(() =>
  newPassword.value.length === 0 || newPassword.value.length >= MIN_PASSWORD
    ? t('auth.passwordRule')
    : t('auth.passwordRuleLeft', { n: MIN_PASSWORD - newPassword.value.length }),
);

async function saveProfile() {
  nameError.value = name.value.trim() ? null : t('auth.required');
  emailError.value = isEmail(email.value) ? null : t('auth.invalidEmail');
  if (nameError.value || emailError.value) return;

  savingProfile.value = true;
  try {
    await auth.updateProfile(name.value.trim(), email.value.trim());
    toast(t('account.profileSaved'));
  } catch (e) {
    if (e instanceof GraphQLError) emailError.value = errorText(e);
    else toast(errorText(e));
  } finally {
    savingProfile.value = false;
  }
}

async function savePassword() {
  currentError.value = currentPassword.value ? null : t('auth.required');
  newError.value = newPassword.value.length >= MIN_PASSWORD ? null : t('auth.passwordRule');
  if (currentError.value || newError.value) return;

  savingPassword.value = true;
  try {
    await auth.changePassword(currentPassword.value, newPassword.value);
    currentPassword.value = '';
    newPassword.value = '';
    toast(t('account.passwordUpdated'));
  } catch (e) {
    if (e instanceof GraphQLError) currentError.value = errorText(e);
    else toast(errorText(e));
  } finally {
    savingPassword.value = false;
  }
}

async function deleteAccount() {
  if (!deletePassword.value) {
    deleteError.value = t('auth.required');
    return;
  }

  deleting.value = true;
  try {
    await auth.deleteAccount(deletePassword.value);
  } catch (e) {
    deleteError.value = errorText(e);
  } finally {
    deleting.value = false;
  }
}

function back() {
  if (window.history.state?.back) router.back();
  else void router.replace({ name: 'settings' });
}
</script>

<style scoped>
.account {
  padding-bottom: calc(24px + var(--w-safe-bottom));
}

.account__bar {
  padding-right: 8px;
}

.account__title {
  flex: 1;
}

.account__save {
  font-size: 15px;
  padding: 0 12px;
}

.account__save:disabled {
  color: var(--w-disabled);
  cursor: default;
}

.account__body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 28px;
  padding: 8px 16px 0;
}

.account__avatar-row {
  display: flex;
  justify-content: center;
}

.account__avatar {
  width: 80px;
  height: 80px;
  border-radius: 40px;
  background: var(--w-tonal);
  color: var(--w-on-tonal);
  font-size: 32px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
}

.account__section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.account__label {
  padding: 0 4px;
}

.account__fields {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.account__spacer {
  flex: 1;
}

.account__delete {
  align-self: center;
  min-height: 44px;
  padding: 0 16px;
  border: none;
  border-radius: 22px;
  background: transparent;
  color: var(--w-danger);
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
}
</style>
