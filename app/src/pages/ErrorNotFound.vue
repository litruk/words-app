<template>
  <div class="w-screen not-found">
    <h1 class="w-title">{{ t('notFound.title') }}</h1>
    <router-link :to="{ name: 'home' }" class="w-btn w-btn--md">{{ t('notFound.home') }}</router-link>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<style scoped>
.not-found {
  align-items: center;
  justify-content: center;
  gap: 24px;
  padding: 24px;
  text-align: center;
}
</style>
