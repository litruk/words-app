<template>
  <q-page class="w-screen dictionary">
    <div class="dictionary__bar">
      <h1 class="dictionary__title">{{ t('dictionary.title') }}</h1>
      <button
        v-if="!emptyDictionary"
        type="button"
        class="w-icon-btn muted"
        :aria-label="t('dictionary.sort')"
        @click="sortSheet = true"
      >
        <NIcon name="sort" :stroke-width="1.9" />
      </button>
    </div>

    <div v-if="!emptyDictionary" class="dictionary__search">
      <label class="search">
        <NIcon name="search" :size="22" :stroke-width="1.9" class="muted" />
        <span class="visually-hidden">{{ t('dictionary.search') }}</span>
        <input
          v-model="search"
          type="search"
          class="search__input"
          :placeholder="t('dictionary.searchPlaceholder')"
          enterkeyhint="search"
          autocomplete="off"
        />
        <button
          v-if="search"
          type="button"
          class="w-icon-btn muted"
          :aria-label="t('add.clear')"
          @click="search = ''"
        >
          <NIcon name="clear" :size="20" :stroke-width="1.9" />
        </button>
        <button
          v-else-if="voiceAvailable"
          type="button"
          class="w-icon-btn muted"
          :class="{ 'search__mic--on': listening }"
          :aria-label="t('dictionary.voiceSearch')"
          @click="voiceSearch"
        >
          <NIcon name="mic" :size="22" />
        </button>
      </label>
    </div>

    <div v-if="auth.studiedLanguages.length > 1" role="tablist" class="tabs" :aria-label="t('dictionary.languages')">
      <button
        v-for="code in auth.studiedLanguages"
        :key="code"
        type="button"
        role="tab"
        class="tabs__tab"
        :aria-selected="code === active"
        @click="auth.setActiveLanguage(code)"
      >
        {{ language(code).endonym }}<template v-if="words.statsFor(code)"> · {{ words.statsFor(code)!.total }}</template>
        <span v-if="code === active" class="tabs__indicator" />
      </button>
    </div>

    <template v-if="!emptyDictionary">
      <div role="group" class="filters" :aria-label="t('dictionary.filters')">
        <button
          v-for="option in filters"
          :key="option.value"
          type="button"
          class="w-chip"
          :class="{ 'w-chip--selected': filter === option.value }"
          :aria-pressed="filter === option.value"
          @click="filter = option.value"
        >
          <NIcon v-if="filter === option.value" name="check" :size="18" :stroke-width="2.2" />
          {{ option.label }}
        </button>
      </div>

      <q-infinite-scroll :key="listKey" class="dictionary__list" :offset="300" @load="loadMore">
        <ul class="list">
          <template v-if="filter === 'ALL' && !search">
            <WordRow
              v-for="word in pending"
              :key="word.localId"
              :word="{ term: word.term, kind: 'WORD', transcriptions: [], translations: word.translations, note: word.note ?? null }"
            >
              <span class="status status--later">{{ t('dictionary.pending') }}</span>
            </WordRow>
          </template>
          <WordRow v-for="word in items" :key="word.id" :word="word" :to="{ name: 'word', params: { id: word.id } }">
            <DueLabel :word="word" />
          </WordRow>
        </ul>

        <div v-if="finished && items.length === 0 && pending.length === 0" class="no-results">
          <p class="no-results__title">{{ t('dictionary.noResults') }}</p>
          <p class="no-results__hint">{{ t('dictionary.noResultsHint') }}</p>
          <router-link
            v-if="search.trim()"
            :to="{ name: 'add', query: { mode: 'text', term: search.trim() } }"
            class="w-btn w-btn--md w-btn--tonal"
          >
            <NIcon name="plus" :size="20" />
            {{ t('dictionary.addTerm', { term: search.trim() }) }}
          </router-link>
        </div>

        <template #loading>
          <div class="list-loading"><span class="w-spinner" /></div>
        </template>
      </q-infinite-scroll>

      <q-page-sticky position="bottom-right" :offset="[16, 16]">
        <router-link :to="{ name: 'add', query: { mode: 'voice' } }" class="fab">
          <NIcon name="plus" :size="24" :stroke-width="2.1" />
          {{ t('dictionary.add') }}
        </router-link>
      </q-page-sticky>
    </template>

    <div v-else class="empty">
      <div class="empty__art" aria-hidden="true">
        <div class="empty__card empty__card--back" />
        <div class="empty__card empty__card--middle" />
        <div class="empty__card empty__card--front"><NIcon name="plus" :size="40" /></div>
      </div>
      <div class="empty__text">
        <h2 class="empty__title">{{ t('dictionary.emptyTitle') }}</h2>
        <p class="w-lead">{{ t('dictionary.emptyLead') }}</p>
      </div>
      <div class="empty__actions">
        <router-link :to="{ name: 'add', query: { mode: 'voice' } }" class="w-btn">
          <NIcon name="mic" :size="20" :stroke-width="1.9" />
          {{ t('dictionary.sayWord') }}
        </router-link>
        <router-link :to="{ name: 'add', query: { mode: 'text' } }" class="w-btn w-btn--outline empty__secondary">
          <NIcon name="keyboard" :size="20" />
          {{ t('dictionary.typeWord') }}
        </router-link>
      </div>
    </div>

    <BottomSheet v-model="sortSheet" :title="t('dictionary.sortTitle')">
      <div role="radiogroup" class="sort-options">
        <label v-for="option in sorts" :key="option.value" class="w-option" :class="{ 'w-option--selected': sort === option.value }">
          <span class="w-option__text"><span class="w-option__name">{{ option.label }}</span></span>
          <input type="radio" name="sort" :checked="sort === option.value" @change="pickSort(option.value)" />
        </label>
      </div>
    </BottomSheet>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BottomSheet from '@/components/BottomSheet.vue';
import DueLabel from '@/components/DueLabel.vue';
import NIcon from '@/components/NIcon.vue';
import WordRow from '@/components/WordRow.vue';
import { fetchWords } from '@/services/words-api';
import type { WordFilter, WordSort, WordSummary } from '@/services/types';
import { listen, voiceInputAvailable, VoiceInputError } from '@/services/voice-input';
import { useAuthStore } from '@/stores/auth-store';
import { useWordsStore } from '@/stores/words-store';
import { language } from '@/utils/languages';
import { errorText, toast } from '@/utils/notify';

const SORT_KEY = 'words-app:dictionary-sort';

const { t } = useI18n();
const auth = useAuthStore();
const words = useWordsStore();

const active = computed(() => auth.activeLanguage);
const stats = computed(() => (active.value ? words.statsFor(active.value) : null));
const pending = computed(() => (active.value ? words.pendingFor(active.value) : []));
const emptyDictionary = computed(() => stats.value?.total === 0 && pending.value.length === 0);

const search = ref('');
const debouncedSearch = ref('');
const filter = ref<WordFilter>('ALL');
const sort = ref<WordSort>((localStorage.getItem(SORT_KEY) as WordSort | null) ?? 'RECENT');
const sortSheet = ref(false);

const items = ref<WordSummary[]>([]);
const finished = ref(false);
const listKey = ref(0);

const voiceAvailable = ref(false);
const listening = ref(false);
let stopListening: (() => void) | null = null;

const filters = computed<Array<{ value: WordFilter; label: string }>>(() => [
  { value: 'ALL', label: t('dictionary.filterAll') },
  { value: 'DUE', label: t('dictionary.filterDue', { n: stats.value?.due ?? 0 }) },
  { value: 'WORDS', label: t('dictionary.filterWords') },
  { value: 'PHRASES', label: t('dictionary.filterPhrases') },
  { value: 'LEARNED', label: t('dictionary.filterLearned') },
]);

const sorts = computed<Array<{ value: WordSort; label: string }>>(() => [
  { value: 'RECENT', label: t('dictionary.sortRecent') },
  { value: 'ALPHABETICAL', label: t('dictionary.sortAlphabetical') },
  { value: 'DUE', label: t('dictionary.sortDue') },
]);

async function loadMore(index: number, done: (stop?: boolean) => void) {
  if (!active.value) return done(true);

  try {
    const page = await fetchWords({
      language: active.value,
      filter: filter.value,
      search: debouncedSearch.value.trim(),
      sort: sort.value,
      page: index,
    });
    items.value = index === 1 ? page.data : [...items.value, ...page.data];
    finished.value = !page.paginatorInfo.hasMorePages;
    done(finished.value);
  } catch (e) {
    toast(errorText(e));
    finished.value = true;
    done(true);
  }
}

/** Starts the list over: the infinite scroll is recreated and loads page 1. */
function restart() {
  items.value = [];
  finished.value = false;
  listKey.value++;
}

function pickSort(value: WordSort) {
  sort.value = value;
  localStorage.setItem(SORT_KEY, value);
  sortSheet.value = false;
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => (debouncedSearch.value = value), 300);
});

watch([active, filter, sort, debouncedSearch], restart);

watch(active, () => (filter.value = 'ALL'));

async function voiceSearch() {
  if (!active.value || listening.value) return;

  try {
    listening.value = true;
    const session = await listen({ locale: language(active.value).speech, onPartial: (text) => (search.value = text) });
    stopListening = session.stop;
    const heard = await session.result;
    if (heard) search.value = heard;
  } catch (e) {
    toast(e instanceof VoiceInputError && e.problem === 'permission' ? t('add.micText') : t('add.voiceUnavailable'));
  } finally {
    listening.value = false;
    stopListening = null;
  }
}

onMounted(async () => {
  void words.loadStats().catch((e) => toast(errorText(e)));
  void words.flush().then((sent) => (sent > 0 ? restart() : undefined));
  voiceAvailable.value = await voiceInputAvailable();
});

onBeforeUnmount(() => {
  clearTimeout(searchTimer);
  stopListening?.();
});
</script>

<style scoped>
.dictionary {
  padding-bottom: 96px;
}

.dictionary__bar {
  display: flex;
  align-items: center;
  height: 64px;
  padding: 0 4px 0 16px;
  flex-shrink: 0;
}

.dictionary__title {
  flex: 1;
  font-size: 28px;
  line-height: 36px;
  font-weight: 600;
  letter-spacing: -0.01em;
}

.dictionary__search {
  padding: 0 16px;
}

.search {
  height: 56px;
  border-radius: 28px;
  background: var(--w-container-high);
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 0 4px 0 16px;
  cursor: text;
}

.search__input {
  flex: 1;
  min-width: 0;
  height: 48px;
  border: none;
  background: transparent;
  font-size: 16px;
  color: var(--w-ink);
  outline: none;
  padding: 0 8px;
}

.search__input::placeholder {
  color: var(--w-disabled);
}

.search__input::-webkit-search-cancel-button {
  display: none;
}

.search__mic--on {
  color: var(--w-primary);
  background: var(--w-tonal);
}

.tabs {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: minmax(0, 1fr);
  height: 48px;
  margin-top: 12px;
  border-bottom: 1px solid var(--w-divider);
  flex-shrink: 0;
}

.tabs__tab {
  position: relative;
  border: none;
  background: transparent;
  color: var(--w-muted);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}

.tabs__tab[aria-selected='true'] {
  color: var(--w-primary);
  font-weight: 600;
}

.tabs__indicator {
  position: absolute;
  left: 50%;
  bottom: 0;
  width: 96px;
  margin-left: -48px;
  height: 3px;
  border-radius: 3px 3px 0 0;
  background: var(--w-primary);
}

.filters {
  display: flex;
  gap: 8px;
  padding: 12px 16px 8px;
  overflow-x: auto;
  scrollbar-width: none;
  flex-shrink: 0;
}

.filters::-webkit-scrollbar {
  display: none;
}

.filters .w-chip {
  flex-shrink: 0;
}

.list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.list-loading {
  display: flex;
  justify-content: center;
  padding: 16px;
  color: var(--w-primary);
}

.status--later {
  font-size: 12px;
  color: var(--w-muted);
  white-space: nowrap;
}

.no-results {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 48px 32px;
  text-align: center;
}

.no-results__title {
  font-size: 18px;
  font-weight: 600;
}

.no-results__hint {
  font-size: 14px;
  line-height: 20px;
  color: var(--w-muted);
  margin-bottom: 8px;
}

.fab {
  height: 56px;
  padding: 0 20px 0 16px;
  border-radius: 16px;
  background: var(--w-primary-soft);
  color: var(--w-on-primary-soft);
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 15px;
  font-weight: 600;
  text-decoration: none;
  box-shadow:
    0 2px 6px rgba(23, 29, 27, 0.16),
    0 6px 16px rgba(23, 29, 27, 0.1);
}

.fab:hover {
  color: var(--w-on-primary-soft);
}

.sort-options {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

/* Empty dictionary */

.empty {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 28px;
  padding: 24px 32px;
}

.empty__art {
  position: relative;
  width: 168px;
  height: 136px;
}

.empty__card {
  position: absolute;
  width: 116px;
  height: 120px;
  border-radius: 20px;
  box-sizing: border-box;
}

.empty__card--back {
  left: 34px;
  top: 4px;
  background: var(--w-divider);
  transform: rotate(10deg);
}

.empty__card--middle {
  left: 22px;
  top: 8px;
  background: var(--w-container);
  transform: rotate(-5deg);
}

.empty__card--front {
  left: 26px;
  top: 12px;
  background: var(--w-surface);
  border: 1.5px dashed #6f9e95;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--w-primary);
}

.empty__text {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  text-align: center;
}

.empty__title {
  font-size: 22px;
  line-height: 28px;
  font-weight: 600;
}

.empty__actions {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 100%;
  max-width: 280px;
}

.empty__secondary {
  color: var(--w-primary);
}

.empty__secondary:hover {
  color: var(--w-primary);
}
</style>
