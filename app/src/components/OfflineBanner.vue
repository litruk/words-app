<template>
  <div class="w-banner offline" role="alert">
    <NIcon name="wifiOff" :size="22" />
    <div class="w-banner__text">
      <span class="w-banner__title">{{ t('add.offlineTitle') }}</span>
      <span>{{ t('add.offlineText') }}</span>
      <button type="button" class="offline__retry" @click="emit('retry')">
        <NIcon name="repeat" :size="18" />
        {{ t('common.retry') }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import NIcon from '@/components/NIcon.vue';

const emit = defineEmits<{ retry: [] }>();
const { t } = useI18n();
</script>

<style scoped>
.offline {
  padding-bottom: 10px;
}

.offline__retry {
  align-self: flex-start;
  height: 40px;
  margin-left: -8px;
  padding: 0 8px;
  border: none;
  border-radius: 20px;
  background: transparent;
  color: #8c1d18;
  font-size: 14px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
}
</style>
