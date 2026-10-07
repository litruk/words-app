<template>
  <div>
    <div
      class="w-field"
      :class="{ 'w-field--error': !!error, 'w-field--trailing': trailing || type === 'password' }"
    >
      <label :for="id" class="w-field__label">{{ label }}</label>
      <textarea
        v-if="multiline"
        :id="id"
        ref="input"
        v-model="model"
        class="w-field__input w-field__input--multiline"
        :rows="rows"
        :placeholder="placeholder"
        :aria-invalid="!!error"
        :aria-describedby="hint || error ? `${id}-help` : undefined"
      />
      <input
        v-else
        :id="id"
        ref="input"
        v-model="model"
        class="w-field__input"
        :class="{ 'w-field__input--large': large }"
        :type="type === 'password' && revealed ? 'text' : type"
        :autocomplete="autocomplete"
        :inputmode="inputmode"
        :enterkeyhint="enterkeyhint"
        :placeholder="placeholder"
        :autocapitalize="type === 'email' || type === 'password' ? 'off' : undefined"
        :spellcheck="type === 'email' || type === 'password' ? false : undefined"
        :aria-invalid="!!error"
        :aria-describedby="hint || error ? `${id}-help` : undefined"
        @keydown.enter="emit('enter')"
      />
      <div v-if="type === 'password'" class="w-field__trailing">
        <button
          type="button"
          class="w-icon-btn w-icon-btn--sm muted"
          :aria-label="revealed ? t('common.hidePassword') : t('common.showPassword')"
          @click="revealed = !revealed"
        >
          <NIcon :name="revealed ? 'eye' : 'eyeOff'" :size="22" />
        </button>
      </div>
      <div v-else-if="trailing" class="w-field__trailing">
        <slot name="trailing" />
      </div>
    </div>
    <span
      v-if="error || hint"
      :id="`${id}-help`"
      class="w-field__help"
      :class="{ 'w-field__help--error': !!error, 'w-field__help--ok': !error && hintOk }"
      :role="error ? 'alert' : undefined"
    >
      <NIcon v-if="error" name="alert" :size="14" :stroke-width="2.2" />
      <NIcon v-else-if="hintOk" name="check" :size="14" :stroke-width="2.6" />
      {{ error || hint }}
    </span>
  </div>
</template>

<script setup lang="ts">
import { ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import NIcon from '@/components/NIcon.vue';

withDefaults(
  defineProps<{
    label: string;
    type?: 'text' | 'email' | 'password' | 'search';
    autocomplete?: string;
    inputmode?: 'text' | 'email' | 'search';
    enterkeyhint?: 'done' | 'go' | 'next' | 'search' | 'send';
    placeholder?: string;
    error?: string | null;
    hint?: string | null;
    /** The hint is a met requirement: shown with a check, in the accent. */
    hintOk?: boolean;
    trailing?: boolean;
    large?: boolean;
    multiline?: boolean;
    rows?: number;
  }>(),
  {
    type: 'text',
    autocomplete: 'off',
    error: null,
    hint: null,
    hintOk: false,
    trailing: false,
    large: false,
    multiline: false,
    rows: 3,
  },
);

const emit = defineEmits<{ enter: [] }>();
const model = defineModel<string>({ required: true });

const { t } = useI18n();
const id = useId();
const revealed = ref(false);
const input = ref<HTMLInputElement | HTMLTextAreaElement | null>(null);

defineExpose({ focus: () => input.value?.focus() });
</script>

<style scoped>
.w-field__input--large {
  font-size: 17px;
}

.w-field__input--multiline {
  height: auto;
  min-height: 100px;
  padding-top: 14px;
  padding-bottom: 14px;
  font-size: 15px;
  line-height: 22px;
  resize: none;
}
</style>
