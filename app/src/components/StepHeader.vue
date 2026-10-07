<template>
  <div class="w-topbar step-header">
    <router-link v-if="back" :to="back" class="w-icon-btn" :aria-label="t('common.back')">
      <NIcon name="back" />
    </router-link>
    <div class="w-topbar__spacer" />
    <span class="step-header__count">{{ t('auth.step', { current, total }) }}</span>
  </div>
  <div class="step-header__bar" aria-hidden="true">
    <span v-for="n in total" :key="n" :class="{ 'step-header__done': n <= current }" />
  </div>
</template>

<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router';
import { useI18n } from 'vue-i18n';
import NIcon from '@/components/NIcon.vue';

defineProps<{ current: number; total: number; back?: RouteLocationRaw | null }>();
const { t } = useI18n();
</script>

<style scoped>
.step-header {
  padding-right: 20px;
}

.step-header__count {
  font-size: 14px;
  font-weight: 500;
  color: var(--w-muted);
}

.step-header__bar {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: minmax(0, 1fr);
  gap: 6px;
  padding: 0 24px;
}

.step-header__bar span {
  height: 4px;
  border-radius: 2px;
  background: var(--w-divider);
}

.step-header__bar .step-header__done {
  background: var(--w-primary);
}
</style>
