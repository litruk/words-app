<template>
  <span v-if="state.status === 'today'" class="due-label due-label--today">{{ t('dictionary.statusToday') }}</span>
  <span v-else-if="state.status === 'new'" class="due-label due-label--new">{{ t('dictionary.statusNew') }}</span>
  <span v-else-if="state.status === 'learned'" class="due-label due-label--learned">
    <NIcon name="check" :size="16" :stroke-width="2.4" />
    {{ t('dictionary.statusLearned') }}
  </span>
  <span v-else class="due-label">{{ t('dictionary.statusInDays', { n: state.days }) }}</span>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import NIcon from '@/components/NIcon.vue';
import type { WordSummary } from '@/services/types';
import { dueStatus } from '@/utils/format';

const props = defineProps<{ word: Pick<WordSummary, 'state' | 'level' | 'due_at'> }>();
const { t } = useI18n();

const state = computed(() => dueStatus(props.word));
</script>

<style scoped>
.due-label {
  font-size: 12px;
  color: var(--w-muted);
  white-space: nowrap;
  flex-shrink: 0;
}

.due-label--today {
  font-weight: 600;
  background: var(--w-warm);
  color: var(--w-on-warm);
  border-radius: 8px;
  padding: 4px 8px;
}

.due-label--new {
  font-weight: 600;
  border: 1px solid var(--w-outline-soft);
  border-radius: 8px;
  padding: 3px 8px;
}

.due-label--learned {
  display: flex;
  align-items: center;
  gap: 4px;
  font-weight: 600;
  color: var(--w-primary);
}
</style>
