<template>
  <q-dialog v-model="model">
    <section class="w-dialog" role="alertdialog" aria-modal="true" :aria-labelledby="titleId">
      <h2 :id="titleId" class="w-dialog__title">{{ title }}</h2>
      <p v-if="text" class="w-dialog__text">{{ text }}</p>
      <slot />
      <div class="w-dialog__actions">
        <button type="button" class="w-link-btn" @click="model = false">{{ t('common.cancel') }}</button>
        <button
          type="button"
          class="w-link-btn"
          :class="{ 'w-dialog__danger': danger }"
          :disabled="busy"
          @click="emit('confirm')"
        >
          {{ confirm }}
        </button>
      </div>
    </section>
  </q-dialog>
</template>

<script setup lang="ts">
import { useId } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps<{ title: string; text?: string; confirm: string; danger?: boolean; busy?: boolean }>();
const emit = defineEmits<{ confirm: [] }>();
const model = defineModel<boolean>({ required: true });
const { t } = useI18n();
const titleId = useId();
</script>

<style scoped>
.w-dialog {
  width: min(320px, calc(100vw - 48px));
  background: var(--w-bg);
  border-radius: 28px;
  padding: 24px 24px 12px;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.w-dialog__title {
  font-size: 22px;
  line-height: 28px;
  font-weight: 600;
}

.w-dialog__text {
  font-size: 14px;
  line-height: 20px;
  color: var(--w-muted);
}

.w-dialog__actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.w-dialog__danger {
  color: var(--w-danger);
}
</style>
