<template>
  <div class="studied">
    <label
      v-for="lang in LANGUAGES"
      :key="lang.code"
      class="w-option"
      :class="{
        'w-option--selected': model.includes(lang.code),
        'w-option--disabled': lang.code === native,
      }"
    >
      <LangBadge
        :code="lang.code"
        :variant="lang.code === native ? 'disabled' : model.includes(lang.code) ? 'primary' : 'neutral'"
      />
      <span class="w-option__text">
        <span class="w-option__name">{{ lang.endonym }}</span>
        <span class="w-option__hint">{{ lang.code === native ? nativeTag : hint(lang.code) }}</span>
      </span>
      <input
        type="checkbox"
        :checked="model.includes(lang.code)"
        :disabled="lang.code === native"
        @change="toggle(lang.code)"
      />
    </label>
  </div>
</template>

<script setup lang="ts">
import LangBadge from '@/components/LangBadge.vue';
import { LANGUAGES, type LanguageCode } from '@/utils/languages';

defineProps<{
  native: LanguageCode;
  nativeTag: string;
  /** The grey line next to each language's name. */
  hint: (code: LanguageCode) => string;
}>();

const model = defineModel<LanguageCode[]>({ required: true });

function toggle(code: LanguageCode) {
  model.value = model.value.includes(code) ? model.value.filter((c) => c !== code) : [...model.value, code];
}
</script>

<style scoped>
.studied {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
</style>
