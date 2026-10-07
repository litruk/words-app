<template>
  <q-layout view="hHh lpR fFf">
    <q-page-container>
      <router-view />
    </q-page-container>

    <q-footer v-if="tab" class="w-footer">
      <nav class="w-nav" :aria-label="t('nav.label')">
        <router-link
          v-for="item in ITEMS"
          :key="item.name"
          :to="{ name: item.route }"
          class="w-nav__item"
          :class="{ 'w-nav__item--on': tab === item.name }"
          :aria-current="tab === item.name ? 'page' : undefined"
        >
          <span class="w-nav__pill"><NIcon :name="item.icon" :size="22" /></span>
          {{ t(item.label) }}
        </router-link>
      </nav>
    </q-footer>
  </q-layout>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import NIcon, { type IconName } from '@/components/NIcon.vue';

const { t } = useI18n();
const route = useRoute();

const tab = computed(() => route.meta.tab);

const ITEMS: Array<{ name: string; route: string; icon: IconName; label: string }> = [
  { name: 'home', route: 'home', icon: 'home', label: 'nav.home' },
  { name: 'dictionary', route: 'dictionary', icon: 'book', label: 'nav.dictionary' },
  { name: 'study', route: 'study', icon: 'cards', label: 'nav.study' },
  { name: 'settings', route: 'settings', icon: 'sliders', label: 'nav.settings' },
];
</script>

<style scoped>
.w-footer {
  background: var(--w-container);
  color: var(--w-muted);
}

.w-nav {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  height: 80px;
  box-sizing: border-box;
  padding: 12px 0 16px;
  margin-bottom: var(--w-safe-bottom);
}

.w-nav__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  text-decoration: none;
  color: var(--w-muted);
  font-size: 12px;
  font-weight: 500;
  -webkit-tap-highlight-color: transparent;
}

.w-nav__item:hover {
  color: var(--w-ink);
}

.w-nav__pill {
  width: 64px;
  height: 32px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background-color 0.2s ease;
}

.w-nav__item--on {
  color: var(--w-ink);
  font-weight: 600;
}

.w-nav__item--on .w-nav__pill {
  background: var(--w-tonal);
  color: var(--w-on-tonal);
}
</style>
