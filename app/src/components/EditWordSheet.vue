<template>
  <BottomSheet v-model="model" :title="t('editSheet.title')">
    <div class="edit">
      <TextField v-model="term" :label="t('add.term')" :error="termError" />

      <section class="edit__section">
        <h3 class="w-section-label">{{ t('editSheet.translations') }}</h3>
        <div class="w-chip-row">
          <span v-for="translation in translations" :key="translation" class="chosen">
            {{ translation }}
            <button
              type="button"
              class="chosen__remove"
              :aria-label="t('editSheet.removeTranslation', { translation })"
              @click="remove(translation)"
            >
              <NIcon name="close" :size="16" :stroke-width="2.2" />
            </button>
          </span>

          <form v-if="adding" class="own" @submit.prevent="addOwn">
            <input
              ref="ownInput"
              v-model="own"
              class="own__input"
              :placeholder="t('editSheet.ownPlaceholder')"
              enterkeyhint="done"
              @blur="addOwn"
            />
          </form>
          <button v-else type="button" class="own-btn" @click="startOwn">
            <NIcon name="plus" :size="16" :stroke-width="2.2" />
            {{ t('editSheet.own') }}
          </button>
        </div>

        <div v-if="suggestions.length > 0" class="more">
          <span class="more__label">{{ t('editSheet.moreOptions') }}</span>
          <button v-for="suggestion in suggestions" :key="suggestion" type="button" class="more__chip" @click="choose(suggestion)">
            <NIcon name="plus" :size="14" :stroke-width="2.2" />
            {{ suggestion }}
          </button>
        </div>
      </section>

      <TextField v-model="note" :label="t('editSheet.note')" multiline :hint="t('editSheet.noteHint')" :error="noteError" />

      <div class="edit__actions">
        <button type="button" class="w-btn w-btn--md w-btn--danger-text edit__delete" @click="emit('delete')">
          <NIcon name="trash" :size="20" />
          {{ t('common.delete') }}
        </button>
        <div class="w-topbar__spacer" />
        <button type="button" class="w-btn w-btn--md edit__save" :disabled="busy" @click="save">
          <span v-if="busy" class="w-spinner" />
          <template v-else>{{ t('common.save') }}</template>
        </button>
      </div>
    </div>
  </BottomSheet>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BottomSheet from '@/components/BottomSheet.vue';
import NIcon from '@/components/NIcon.vue';
import TextField from '@/components/TextField.vue';
import { updateWord } from '@/services/words-api';
import type { Word } from '@/services/types';
import { errorText } from '@/utils/notify';

const props = defineProps<{ word: Word }>();
const emit = defineEmits<{ saved: [word: Word]; delete: [] }>();
const model = defineModel<boolean>({ required: true });

const { t } = useI18n();

const term = ref('');
const translations = ref<string[]>([]);
const note = ref('');
const own = ref('');
const adding = ref(false);
const ownInput = ref<HTMLInputElement | null>(null);
const busy = ref(false);
const termError = ref<string | null>(null);
const noteError = ref<string | null>(null);

const suggestions = computed(() => {
  const chosen = translations.value.map((translation) => translation.toLowerCase());
  return [...props.word.translations, ...props.word.suggested_translations].filter(
    (candidate, i, all) =>
      !chosen.includes(candidate.toLowerCase()) && all.findIndex((other) => other.toLowerCase() === candidate.toLowerCase()) === i,
  );
});

// Every opening starts from the word as it is now.
watch(model, (open) => {
  if (!open) return;
  term.value = props.word.term;
  translations.value = [...props.word.translations];
  note.value = props.word.note ?? '';
  own.value = '';
  adding.value = false;
  termError.value = null;
  noteError.value = null;
});

function remove(translation: string) {
  translations.value = translations.value.filter((candidate) => candidate !== translation);
}

function choose(translation: string) {
  translations.value = [...translations.value, translation];
}

async function startOwn() {
  adding.value = true;
  await nextTick();
  ownInput.value?.focus();
}

function addOwn() {
  const value = own.value.trim().replace(/\s+/g, ' ');
  if (value && !translations.value.some((candidate) => candidate.toLowerCase() === value.toLowerCase())) {
    choose(value);
  }
  own.value = '';
  adding.value = false;
}

async function save() {
  if (adding.value) addOwn();

  termError.value = term.value.trim() ? null : t('auth.required');
  noteError.value = translations.value.length > 0 || note.value.trim() ? null : t('auth.required');
  if (termError.value || noteError.value) return;

  busy.value = true;
  try {
    const saved = await updateWord(props.word.id, {
      term: term.value.trim(),
      translations: translations.value,
      note: note.value.trim() || null,
    });
    emit('saved', saved);
    model.value = false;
  } catch (e) {
    termError.value = errorText(e);
  } finally {
    busy.value = false;
  }
}
</script>

<style scoped>
.edit {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 4px 4px 0;
}

.edit__section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.chosen {
  height: 40px;
  padding: 0 2px 0 14px;
  border-radius: 10px;
  background: var(--w-tonal);
  color: var(--w-on-tonal);
  font-size: 14px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 2px;
}

.chosen__remove {
  width: 36px;
  height: 36px;
  border-radius: 18px;
  border: none;
  background: transparent;
  color: inherit;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.own-btn {
  height: 40px;
  padding: 0 14px 0 10px;
  border-radius: 10px;
  border: 1px dashed var(--w-outline);
  background: transparent;
  color: var(--w-primary);
  font-size: 14px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
}

.own__input {
  height: 40px;
  width: 160px;
  box-sizing: border-box;
  padding: 0 12px;
  border-radius: 10px;
  border: 2px solid var(--w-primary);
  background: transparent;
  font-size: 14px;
  color: var(--w-ink);
  outline: none;
}

.more {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.more__label {
  font-size: 13px;
  color: var(--w-muted);
  margin-right: 2px;
}

.more__chip {
  height: 36px;
  padding: 0 12px 0 8px;
  border-radius: 8px;
  border: 1px solid var(--w-outline-soft);
  background: transparent;
  color: var(--w-ink);
  font-size: 13px;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  cursor: pointer;
}

.edit__actions {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 4px;
}

.edit__delete {
  padding: 0 16px 0 12px;
}

.edit__save {
  padding: 0 28px;
}
</style>
