<template>
  <li class="word-row" :class="{ 'word-row--compact': compact }">
    <component :is="to ? RouterLink : 'div'" :to="to" class="word-row__main">
      <span class="word-row__head">
        <span class="word-row__term">{{ word.term }}</span>
        <span v-if="word.kind === 'PHRASE'" class="w-tag">{{ t('dictionary.phrase') }}</span>
        <span v-else-if="ipa" class="word-row__ipa ipa">{{ ipa }}</span>
      </span>
      <span class="word-row__translation">{{ translation }}</span>
    </component>
    <slot />
  </li>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink, type RouteLocationRaw } from 'vue-router';
import { useI18n } from 'vue-i18n';
import type { WordSummary } from '@/services/types';

const props = withDefaults(
  defineProps<{
    word: Pick<WordSummary, 'term' | 'kind' | 'transcriptions' | 'translations' | 'note'>;
    to?: RouteLocationRaw | null;
    /** Home's list: shorter rows, divider between them only. */
    compact?: boolean;
  }>(),
  { to: null, compact: false },
);

const { t } = useI18n();

const ipa = computed(() => props.word.transcriptions[0]?.ipa ?? null);
const translation = computed(() =>
  props.word.translations.length > 0 ? props.word.translations.join(', ') : (props.word.note ?? ''),
);
</script>

<style scoped>
.word-row {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 72px;
  padding: 0 16px;
  border-bottom: 1px solid var(--w-divider);
}

.word-row--compact {
  gap: 4px;
  min-height: 64px;
  padding: 0;
}

.word-row--compact:last-child {
  border-bottom: none;
}

.word-row__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px 0;
  color: inherit;
  text-decoration: none;
  -webkit-tap-highlight-color: transparent;
}

.word-row__main:hover {
  color: inherit;
}

.word-row__head {
  display: flex;
  align-items: baseline;
  gap: 8px;
  min-width: 0;
}

.word-row__term {
  font-size: 16px;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.word-row__ipa {
  font-size: 13px;
  color: var(--w-muted);
  white-space: nowrap;
}

.word-row__translation {
  font-size: 14px;
  color: var(--w-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
