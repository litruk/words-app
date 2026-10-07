<template>
  <q-page class="w-screen home">
    <div class="home__bar">
      <button
        v-if="auth.activeLanguage"
        type="button"
        class="lang-chip"
        :aria-label="t('home.switchLanguage')"
        @click="languageSheet = true"
      >
        <LangBadge :code="auth.activeLanguage" small />
        <span>{{ language(auth.activeLanguage).endonym }}</span>
        <NIcon name="chevronDown" :size="18" :stroke-width="2" />
      </button>
      <div class="w-topbar__spacer" />
      <router-link :to="{ name: 'reminders' }" class="w-icon-btn muted" :aria-label="t('home.reminders')">
        <NIcon name="bell" />
      </router-link>
      <router-link :to="{ name: 'account' }" class="w-icon-btn" :aria-label="t('home.account')">
        <span class="avatar"><NIcon name="user" :size="20" /></span>
      </router-link>
    </div>

    <div v-if="loading" class="home__progress" role="progressbar" :aria-label="t('common.loading')">
      <span />
    </div>

    <q-pull-to-refresh class="home__refresh" color="primary" @refresh="refresh">
      <div class="home__greeting">
        <h1 class="home__title">{{ t(`greeting.${greetingKey()}`) }}</h1>
        <p class="home__learning">
          {{ t('home.learning', { list: auth.studiedLanguages.map((code) => language(code).endonym).join(', ') }) }}
        </p>
      </div>

      <div class="home__content">
        <!-- Today -->
        <div v-if="!dashboard" class="due due--skeleton" aria-hidden="true">
          <span class="skeleton" style="width: 152px; height: 14px" />
          <div class="due__count">
            <span class="skeleton" style="width: 64px; height: 52px; border-radius: 12px" />
            <div class="due__lines">
              <span class="skeleton" style="width: 88px; height: 12px" />
              <span class="skeleton" style="width: 148px; height: 12px" />
            </div>
          </div>
          <span class="skeleton" style="height: 48px; border-radius: 24px" />
        </div>

        <section v-else-if="dashboard.total > 0" class="due">
          <div class="due__head">
            <span class="due__label">{{ t('home.dueToday') }}</span>
            <span class="due__time">
              <NIcon name="clock" :size="16" />
              {{ t('home.aboutMinutes', { n: dashboard.minutes }) }}
            </span>
          </div>
          <div class="due__count">
            <span class="due__number">{{ dashboard.total }}</span>
            <span class="due__breakdown">
              {{ t('plural.cardsWord', dashboard.total) }}<br />
              {{ breakdown }}
            </span>
          </div>
          <router-link :to="{ name: 'study' }" class="w-btn w-btn--md">
            {{ t('home.startReview') }}
            <NIcon name="arrowRight" :size="20" :stroke-width="2" />
          </router-link>
        </section>

        <section v-else class="due due--done">
          <div class="due__done">
            <span class="due__check"><NIcon name="check" :size="28" :stroke-width="2.2" /></span>
            <div class="due__done-text">
              <span class="due__done-title">{{ t('home.allDone') }}</span>
              <span>{{ t('home.nothingDue') }}</span>
            </div>
          </div>
          <template v-if="dashboard.next_review_at && dashboard.next_review_count > 0">
            <div class="due__rule" />
            <div class="due__next">
              <NIcon name="clock" :size="18" :stroke-width="2" />
              <i18n-t keypath="home.nextReview" tag="span" scope="global">
                <template #cards>
                  <strong>{{ t('plural.cards', { n: dashboard.next_review_count }, dashboard.next_review_count) }}</strong>
                </template>
                <template #when>{{ nextWhen }}</template>
              </i18n-t>
            </div>
          </template>
        </section>

        <!-- Add -->
        <section class="home__section">
          <h2 class="w-h2">{{ t('home.addTitle') }}</h2>
          <div class="add-tiles">
            <router-link :to="{ name: 'add', query: { mode: 'voice' } }" class="add-tile">
              <span class="add-tile__icon add-tile__icon--strong"><NIcon name="mic" :size="22" :stroke-width="1.9" /></span>
              <span class="add-tile__text">
                <span class="add-tile__title">{{ t('home.byVoice') }}</span>
                <span class="add-tile__hint">{{ t('home.byVoiceHint') }}</span>
              </span>
            </router-link>
            <router-link :to="{ name: 'add', query: { mode: 'text' } }" class="add-tile">
              <span class="add-tile__icon"><NIcon name="keyboard" :size="22" /></span>
              <span class="add-tile__text">
                <span class="add-tile__title">{{ t('home.byText') }}</span>
                <span class="add-tile__hint">{{ t('home.byTextHint') }}</span>
              </span>
            </router-link>
          </div>
        </section>

        <!-- Recent -->
        <section class="home__section home__section--recent">
          <div class="recent__head">
            <h2 class="w-h2">{{ t('home.recent') }}</h2>
            <router-link v-if="recent.length > 0" :to="{ name: 'dictionary' }" class="w-link-btn recent__all">
              {{ t('home.seeAll') }}
            </router-link>
          </div>

          <div v-if="!recentLoaded" class="recent__skeleton" aria-hidden="true">
            <div v-for="n in 3" :key="n" class="recent__skeleton-row">
              <div class="recent__lines">
                <span class="skeleton" :style="{ width: `${[112, 144, 96][n - 1]}px`, height: '14px' }" />
                <span class="skeleton skeleton--soft" :style="{ width: `${[196, 172, 150][n - 1]}px`, height: '12px' }" />
              </div>
              <span class="skeleton skeleton--soft" style="width: 28px; height: 28px; border-radius: 14px; margin-right: 8px" />
            </div>
          </div>

          <ul v-else-if="recent.length > 0 || pending.length > 0" class="recent__list">
            <WordRow
              v-for="word in pending"
              :key="word.localId"
              :word="{ term: word.term, kind: 'WORD', transcriptions: [], translations: word.translations, note: word.note ?? null }"
              compact
            >
              <span class="recent__pending">{{ t('dictionary.pending') }}</span>
            </WordRow>
            <WordRow
              v-for="word in recent"
              :key="word.id"
              :word="word"
              :to="{ name: 'word', params: { id: word.id } }"
              compact
            >
              <button
                type="button"
                class="w-icon-btn w-icon-btn--sm w-icon-btn--accent"
                :aria-label="t('home.pronounce', { term: word.term })"
                @click="speakWord(word.term, word.language, word.transcriptions[0]?.accent)"
              >
                <NIcon name="speaker" :size="22" />
              </button>
            </WordRow>
          </ul>

          <p v-else class="recent__empty">{{ t('home.noWordsYet') }}</p>
        </section>
      </div>
    </q-pull-to-refresh>

    <LanguageSheet v-model="languageSheet" />
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import LangBadge from '@/components/LangBadge.vue';
import LanguageSheet from '@/components/LanguageSheet.vue';
import NIcon from '@/components/NIcon.vue';
import WordRow from '@/components/WordRow.vue';
import { reminderTimeOn } from '@/services/reminders';
import { speakWord } from '@/services/speech';
import { useAuthStore } from '@/stores/auth-store';
import { useWordsStore } from '@/stores/words-store';
import { formatWhen, greetingKey } from '@/utils/format';
import { language } from '@/utils/languages';
import { errorText, toast } from '@/utils/notify';

const { t, locale } = useI18n();
const auth = useAuthStore();
const words = useWordsStore();

const languageSheet = ref(false);
const loading = ref(false);

const active = computed(() => auth.activeLanguage);
const dashboard = computed(() => (active.value ? words.dashboards[active.value] : undefined));
const recent = computed(() => (active.value ? (words.recent[active.value] ?? []) : []));
const recentLoaded = computed(() => !!active.value && words.recent[active.value] !== undefined);
const pending = computed(() => (active.value ? words.pendingFor(active.value) : []));

const breakdown = computed(() => {
  if (!dashboard.value) return '';
  const parts = [];
  if (dashboard.value.due > 0) parts.push(t('plural.reviews', { n: dashboard.value.due }, dashboard.value.due));
  if (dashboard.value.new > 0) parts.push(t('plural.newCards', { n: dashboard.value.new }, dashboard.value.new));
  return parts.join(' · ');
});

const nextWhen = computed(() => {
  const at = dashboard.value?.next_review_at;
  if (!at) return '';
  const date = new Date(at);
  return formatWhen(t, date, locale.value, reminderTimeOn(auth.user?.reminders, date));
});

async function load() {
  const code = active.value;
  if (!code) return;

  loading.value = true;
  try {
    await Promise.all([words.loadDashboard(code), words.loadRecent(code)]);
  } catch (e) {
    toast(errorText(e));
  } finally {
    loading.value = false;
  }
}

async function refresh(done: () => void) {
  await words.flush();
  await load();
  done();
}

onMounted(() => {
  void words.flush().then((sent) => (sent > 0 ? load() : undefined));
  void load();
});

watch(active, () => void load());
</script>

<style scoped>
.home {
  padding-bottom: 24px;
}

.home__bar {
  display: flex;
  align-items: center;
  gap: 4px;
  height: 56px;
  padding: 0 8px 0 16px;
  flex-shrink: 0;
}

.lang-chip {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 44px;
  box-sizing: border-box;
  padding: 0 12px 0 8px;
  border-radius: 22px;
  border: 1px solid var(--w-outline-soft);
  background: transparent;
  color: var(--w-ink);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}

.avatar {
  width: 36px;
  height: 36px;
  border-radius: 18px;
  background: var(--w-tonal);
  color: var(--w-on-tonal);
  display: flex;
  align-items: center;
  justify-content: center;
}

.home__progress {
  position: relative;
  height: 4px;
  overflow: hidden;
  background: var(--w-divider);
  flex-shrink: 0;
}

.home__progress span {
  position: absolute;
  top: 0;
  width: 38%;
  height: 4px;
  border-radius: 2px;
  background: var(--w-primary);
  animation: home-progress 1.2s ease-in-out infinite;
}

@keyframes home-progress {
  from {
    left: -38%;
  }
  to {
    left: 100%;
  }
}

.home__refresh {
  flex: 1;
}

.home__greeting {
  padding: 8px 16px 16px;
}

.home__title {
  font-size: 28px;
  line-height: 36px;
  font-weight: 600;
  letter-spacing: -0.01em;
}

.home__learning {
  margin-top: 4px;
  font-size: 14px;
  line-height: 20px;
  color: var(--w-muted);
}

.home__content {
  display: flex;
  flex-direction: column;
  gap: 24px;
  padding: 0 16px;
}

/* Today */

.due {
  background: var(--w-primary-soft);
  color: var(--w-on-primary-soft);
  border-radius: 28px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.due--skeleton {
  background: var(--w-container);
  gap: 14px;
}

.due__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.due__label {
  font-size: 14px;
  font-weight: 600;
}

.due__time {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
  font-weight: 500;
}

.due__count {
  display: flex;
  align-items: center;
  gap: 14px;
}

.due__lines {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.due__number {
  font-size: 57px;
  line-height: 64px;
  font-weight: 600;
  letter-spacing: -0.03em;
}

.due__breakdown {
  font-size: 15px;
  line-height: 21px;
}

.due--done {
  gap: 16px;
}

.due__done {
  display: flex;
  align-items: center;
  gap: 16px;
}

.due__check {
  width: 52px;
  height: 52px;
  border-radius: 26px;
  background: var(--w-primary);
  color: var(--w-on-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.due__done-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 14px;
  line-height: 20px;
}

.due__done-title {
  font-size: 20px;
  line-height: 26px;
  font-weight: 600;
}

.due__rule {
  height: 1px;
  background: #8ccfbf;
}

.due__next {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  line-height: 20px;
}

.due__next svg {
  flex-shrink: 0;
}

.due__next strong {
  font-weight: 600;
}

/* Add */

.home__section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.add-tiles {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.add-tile {
  display: flex;
  align-items: center;
  gap: 12px;
  height: 72px;
  padding: 0 12px;
  border-radius: 20px;
  background: var(--w-container-high);
  color: var(--w-ink);
  text-decoration: none;
}

.add-tile:hover {
  color: var(--w-ink);
  background: #dbe3df;
}

.add-tile__icon {
  width: 44px;
  height: 44px;
  border-radius: 22px;
  background: var(--w-tonal);
  color: var(--w-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.add-tile__icon--strong {
  background: var(--w-primary);
  color: var(--w-on-primary);
}

.add-tile__text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.add-tile__title {
  font-size: 15px;
  font-weight: 600;
}

.add-tile__hint {
  font-size: 12px;
  color: var(--w-muted);
}

/* Recent */

.home__section--recent {
  gap: 4px;
}

.recent__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 32px;
}

.recent__all {
  padding: 12px 4px 12px 12px;
}

.recent__list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.recent__pending {
  font-size: 12px;
  color: var(--w-muted);
  white-space: nowrap;
}

.recent__empty {
  padding: 12px 0;
  font-size: 14px;
  color: var(--w-muted);
}

.recent__skeleton-row {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 64px;
  border-bottom: 1px solid var(--w-divider);
}

.recent__skeleton-row:last-child {
  border-bottom: none;
}

.recent__lines {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
</style>
