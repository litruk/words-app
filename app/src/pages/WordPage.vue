<template>
  <q-page class="w-screen word">
    <div class="w-topbar">
      <button type="button" class="w-icon-btn" :aria-label="t('common.back')" @click="back">
        <NIcon name="back" />
      </button>
      <div class="w-topbar__spacer" />
      <template v-if="word">
        <button type="button" class="w-icon-btn muted" :aria-label="t('word.edit')" @click="editing = true">
          <NIcon name="edit" />
        </button>
        <button type="button" class="w-icon-btn muted" :aria-label="t('word.more')">
          <NIcon name="more" />
          <q-menu anchor="bottom right" self="top right" class="word-menu">
            <q-list>
              <q-item v-close-popup clickable @click="resetProgress">
                <q-item-section avatar><NIcon name="repeat" :size="20" /></q-item-section>
                <q-item-section>{{ t('word.resetProgress') }}</q-item-section>
              </q-item>
              <q-item v-close-popup clickable class="word-menu__danger" @click="confirmDelete = true">
                <q-item-section avatar><NIcon name="trash" :size="20" /></q-item-section>
                <q-item-section>{{ t('word.delete') }}</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </button>
      </template>
    </div>

    <div v-if="loading && !word" class="word__loading"><span class="w-spinner" /></div>

    <div v-else-if="!word" class="word__missing">
      <p>{{ t('word.notFound') }}</p>
      <router-link :to="{ name: 'dictionary' }" class="w-btn w-btn--md w-btn--tonal">{{ t('nav.dictionary') }}</router-link>
    </div>

    <div v-else class="word__body">
      <header class="head">
        <div class="head__meta">
          <span class="head__lang">{{ language(word.language).badge }}</span>
          <span v-if="word.part_of_speech" class="muted">{{ word.part_of_speech }}</span>
        </div>
        <h1 class="head__term">{{ word.term }}</h1>
        <div class="head__sounds">
          <button
            v-for="sound in sounds"
            :key="sound.label"
            type="button"
            class="sound"
            :aria-label="t('add.pronounceAccent', { accent: sound.label })"
            @click="speakWord(word.term, word.language, sound.accent)"
          >
            <NIcon name="speakerSoft" :size="20" />
            <span class="sound__accent">{{ sound.label }}</span>
            <span v-if="sound.ipa" class="sound__ipa ipa">{{ sound.ipa }}</span>
          </button>
          <button
            type="button"
            class="slow"
            :aria-label="t('word.slow')"
            @click="speakWord(word.term, word.language, sounds[0]?.accent, 0.5)"
          >
            0.5×
          </button>
        </div>
      </header>

      <section v-if="word.translations.length > 0" class="translation">
        <span class="translation__label">{{ t('word.translation') }}</span>
        <span class="translation__text">{{ word.translations.join(', ') }}</span>
      </section>

      <section v-if="word.note" class="block">
        <h2 class="w-section-label">{{ t('word.note') }}</h2>
        <p class="block__text">{{ word.note }}</p>
      </section>

      <section v-if="word.meaning" class="block">
        <h2 class="w-section-label">{{ t('word.meaning') }}</h2>
        <p class="block__text">{{ word.meaning }}</p>
      </section>

      <section v-if="word.examples.length > 0" class="block">
        <h2 class="w-section-label">{{ t('word.examples') }}</h2>
        <ul class="examples">
          <li v-for="(example, i) in word.examples" :key="i" class="example">
            <div class="example__text">
              <span class="example__sentence">
                <template v-for="(part, j) in highlight(example.text, word.term)" :key="j">
                  <strong v-if="part.match">{{ part.text }}</strong>
                  <template v-else>{{ part.text }}</template>
                </template>
              </span>
              <span v-if="example.translation" class="example__translation">{{ example.translation }}</span>
            </div>
            <button
              type="button"
              class="w-icon-btn w-icon-btn--sm w-icon-btn--accent"
              :aria-label="t('word.listenExample')"
              @click="speakWord(example.text, word.language, sounds[0]?.accent)"
            >
              <NIcon name="speakerSoft" :size="22" />
            </button>
          </li>
        </ul>
      </section>

      <section v-if="word.forms.length > 0" class="block">
        <h2 class="w-section-label">{{ t('word.forms') }}</h2>
        <div class="forms w-divider-list">
          <div v-for="form in word.forms" :key="form.term" class="form">
            <span class="form__term">{{ form.term }}</span>
            <span class="form__translation">{{ form.translation }}</span>
            <span class="form__pos">{{ form.part_of_speech }}</span>
          </div>
        </div>
      </section>

      <section v-if="word.synonyms.length > 0" class="block">
        <h2 class="w-section-label">{{ t('word.synonyms') }}</h2>
        <div class="w-chip-row">
          <router-link
            v-for="synonym in word.synonyms"
            :key="synonym"
            :to="{ name: 'add', query: { mode: 'text', term: synonym } }"
            class="w-chip"
          >
            {{ synonym }}
          </router-link>
        </div>
      </section>

      <section class="study">
        <div class="study__head">
          <h2 class="w-h2">{{ t('word.learning') }}</h2>
          <span class="study__status" :class="`study__status--${status}`">{{ t(statusLabel) }}</span>
        </div>
        <div class="study__level">
          <div class="level" :aria-label="t('word.level', { level: word.level })">
            <span v-for="n in 5" :key="n" :class="{ 'level--on': n <= word.level }" />
          </div>
          <span class="muted study__small">{{ t('word.level', { level: word.level }) }}</span>
        </div>
        <div class="facts">
          <div class="fact">
            <NIcon name="clock" :size="18" class="muted" />
            <span class="fact__label">{{ t('word.nextReview') }}</span>
            <span class="fact__value">{{ nextReview }}</span>
          </div>
          <div class="fact">
            <NIcon name="repeat" :size="18" class="muted" />
            <span class="fact__label">{{ t('word.reviews') }}</span>
            <span class="fact__value">{{ t('word.reviewsValue', { n: word.reps, m: word.lapses }) }}</span>
          </div>
          <div class="fact">
            <NIcon :name="word.source === 'VOICE' ? 'mic' : 'keyboard'" :size="18" class="muted" />
            <span class="fact__label">{{ t('word.added') }}</span>
            <span class="fact__value">
              {{ t(word.source === 'VOICE' ? 'word.addedByVoice' : 'word.addedByText', { date: formatDayMonth(new Date(word.created_at), locale) }) }}
            </span>
          </div>
        </div>
        <router-link :to="{ name: 'study', query: { ids: word.id } }" class="w-btn w-btn--md w-btn--tonal">
          <NIcon name="cards" :size="20" />
          {{ t('word.reviewNow') }}
        </router-link>
      </section>
    </div>

    <EditWordSheet v-if="word" v-model="editing" :word="word" @saved="saved" @delete="confirmDelete = true" />

    <ConfirmDialog
      v-model="confirmDelete"
      :title="t('word.delete')"
      :text="word ? t('word.deleteConfirm', { term: word.term }) : ''"
      :confirm="t('common.delete')"
      danger
      :busy="deleting"
      @confirm="remove"
    />
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EditWordSheet from '@/components/EditWordSheet.vue';
import NIcon from '@/components/NIcon.vue';
import { speakWord } from '@/services/speech';
import { deleteWord, fetchWord, resetWordProgress } from '@/services/words-api';
import type { Word } from '@/services/types';
import { useWordsStore } from '@/stores/words-store';
import { formatDayMonth, formatShortDate, highlight } from '@/utils/format';
import { language } from '@/utils/languages';
import { errorText, toast } from '@/utils/notify';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();
const words = useWordsStore();

const word = ref<Word | null>(null);
const loading = ref(true);
const editing = ref(false);
const confirmDelete = ref(false);
const deleting = ref(false);

/** One button per pronunciation: UK and US for English, one otherwise. */
const sounds = computed(() => {
  if (!word.value) return [];

  const accents = language(word.value.language).accents;
  return accents.map((accent) => ({
    accent: accent.id,
    label: accent.label,
    ipa: word.value!.transcriptions.find((transcription) => transcription.accent === accent.id)?.ipa ?? word.value!.transcriptions[0]?.ipa ?? null,
  }));
});

const status = computed(() => {
  const level = word.value?.level ?? 0;
  if (level === 0) return 'new';
  if (level >= 5) return 'learned';
  if (level >= 3) return 'remembering';
  return 'learning';
});

const statusLabel = computed(
  () =>
    ({
      new: 'word.statusNew',
      learning: 'word.statusLearning',
      remembering: 'word.statusRemembering',
      learned: 'word.statusLearned',
    })[status.value],
);

const nextReview = computed(() => {
  if (!word.value?.due_at || word.value.state === 'NEW') return t('word.notScheduled');
  return formatShortDate(new Date(word.value.due_at), locale.value);
});

async function load() {
  loading.value = true;
  try {
    word.value = await fetchWord(String(route.params.id));
  } catch (e) {
    toast(errorText(e));
  } finally {
    loading.value = false;
  }
}

function back() {
  if (window.history.state?.back) router.back();
  else void router.replace({ name: 'dictionary' });
}

function saved(updated: Word) {
  word.value = updated;
  words.changed();
  toast(t('editSheet.saved'));
}

async function resetProgress() {
  if (!word.value) return;
  try {
    word.value = await resetWordProgress(word.value.id);
    words.changed();
    toast(t('word.progressReset'));
  } catch (e) {
    toast(errorText(e));
  }
}

async function remove() {
  if (!word.value) return;
  deleting.value = true;
  try {
    await deleteWord(word.value.id);
    words.changed();
    confirmDelete.value = false;
    editing.value = false;
    toast(t('word.deleted'));
    back();
  } catch (e) {
    toast(errorText(e));
  } finally {
    deleting.value = false;
  }
}

onMounted(load);
watch(() => route.params.id, (id) => id && void load());
</script>

<style scoped>
.word {
  padding-bottom: calc(32px + var(--w-safe-bottom));
}

.word__loading,
.word__missing {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  color: var(--w-primary);
}

.word__missing p {
  color: var(--w-muted);
}

.word__body {
  padding: 0 16px;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.head {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.head__meta {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
}

.head__lang {
  height: 26px;
  padding: 0 10px;
  border-radius: 13px;
  background: var(--w-primary);
  color: var(--w-on-primary);
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  letter-spacing: 0.03em;
}

.head__term {
  font-size: 40px;
  line-height: 48px;
  font-weight: 600;
  letter-spacing: -0.02em;
  overflow-wrap: anywhere;
}

.head__sounds {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  align-items: center;
}

.sound {
  height: 44px;
  padding: 0 14px 0 10px;
  border-radius: 22px;
  border: none;
  background: var(--w-tonal);
  color: var(--w-on-tonal);
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.sound__accent {
  font-size: 12px;
  font-weight: 700;
}

.sound__ipa {
  font-size: 15px;
}

.slow {
  height: 44px;
  padding: 0 12px;
  border-radius: 22px;
  border: 1px solid var(--w-outline);
  background: transparent;
  color: var(--w-muted);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.translation {
  background: var(--w-primary-soft);
  color: var(--w-on-primary-soft);
  border-radius: 20px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.translation__label {
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.02em;
}

.translation__text {
  font-size: 22px;
  line-height: 28px;
  font-weight: 600;
}

.block {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.block__text {
  font-size: 16px;
  line-height: 24px;
  white-space: pre-line;
}

.examples {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.example {
  display: flex;
  gap: 8px;
  align-items: flex-start;
}

.example__text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.example__sentence {
  font-size: 16px;
  line-height: 23px;
}

.example__sentence strong {
  font-weight: 600;
  color: var(--w-primary);
}

.example__translation {
  font-size: 14px;
  line-height: 20px;
  color: var(--w-muted);
}

.form {
  display: grid;
  grid-template-columns: minmax(0, 130px) minmax(0, 1fr) auto;
  align-items: center;
  gap: 12px;
  min-height: 48px;
}

.form__term {
  font-size: 16px;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.form__translation {
  font-size: 15px;
  color: var(--w-muted);
}

.form__pos {
  font-size: 12px;
  color: var(--w-muted);
}

.study {
  background: var(--w-container);
  border-radius: 20px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.study__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.study__status {
  font-size: 12px;
  font-weight: 600;
  border-radius: 8px;
  padding: 4px 10px;
  background: var(--w-warm);
  color: var(--w-on-warm);
}

.study__status--new {
  background: transparent;
  color: var(--w-muted);
  border: 1px solid var(--w-outline-soft);
  padding: 3px 9px;
}

.study__status--learned {
  background: var(--w-primary-soft);
  color: var(--w-on-primary-soft);
}

.study__level {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.study__small {
  font-size: 12px;
}

.level {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 4px;
}

.level span {
  height: 8px;
  border-radius: 4px;
  background: var(--w-outline-soft);
}

.level .level--on {
  background: var(--w-primary);
}

.facts {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.fact {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 32px;
}

.fact__label {
  flex: 1;
  font-size: 14px;
  color: var(--w-muted);
}

.fact__value {
  font-size: 14px;
  font-weight: 600;
}

.word-menu__danger {
  color: var(--w-danger);
}
</style>
