<template>
  <q-page class="w-screen study">
    <!-- Loading -->
    <div v-if="study.loading && !study.active" class="study__center"><span class="w-spinner" /></div>

    <!-- Nothing to study -->
    <template v-else-if="study.finished && study.total === 0">
      <div class="w-topbar">
        <button type="button" class="w-icon-btn" :aria-label="t('common.close')" @click="leave">
          <NIcon name="close" />
        </button>
      </div>
      <div class="study__center study__empty">
        <span class="summary__badge"><NIcon name="check" :size="42" :stroke-width="2.2" /></span>
        <h1 class="summary__title">{{ t('study.emptyTitle') }}</h1>
        <p class="w-lead">{{ t('study.emptyText') }}</p>
        <router-link :to="{ name: 'add', query: { mode: 'text' } }" class="w-btn w-btn--md w-btn--tonal">
          <NIcon name="plus" :size="20" />
          {{ t('home.addTitle') }}
        </router-link>
      </div>
    </template>

    <!-- Summary -->
    <template v-else-if="study.finished">
      <div class="w-topbar">
        <button type="button" class="w-icon-btn" :aria-label="t('common.close')" @click="leave">
          <NIcon name="close" />
        </button>
      </div>
      <div class="summary">
        <div class="summary__hero">
          <span class="summary__badge"><NIcon name="check" :size="42" :stroke-width="2.2" /></span>
          <h1 class="summary__title">{{ t('study.summaryTitle') }}</h1>
          <p class="summary__line">{{ summaryLine }}</p>
        </div>

        <section class="summary__card">
          <h2 class="summary__h2">{{ t('study.howRecalled') }}</h2>
          <div class="grades-bar" aria-hidden="true">
            <span
              v-for="grade in GRADES"
              v-show="study.grades[grade.rating] > 0"
              :key="grade.rating"
              :style="{ flex: study.grades[grade.rating], background: grade.color }"
            />
          </div>
          <div class="grades">
            <div v-for="grade in GRADES" :key="grade.rating" class="grade">
              <span class="grade__label"><span class="grade__swatch" :style="{ background: grade.color }" />{{ t(grade.label) }}</span>
              <span class="grade__count">{{ study.grades[grade.rating] }}</span>
            </div>
          </div>
        </section>

        <section v-if="missed.length > 0" class="summary__card summary__card--missed">
          <div class="summary__missed-head">
            <h2 class="summary__h2">{{ t('study.repeatTitle') }}</h2>
            <span class="muted summary__hint">{{ t('study.repeatHint') }}</span>
          </div>
          <div class="w-chip-row">
            <router-link
              v-for="word in missed"
              :key="word.id"
              :to="{ name: 'word', params: { id: word.id } }"
              class="w-chip"
            >
              {{ word.term }}
            </router-link>
          </div>
          <button type="button" class="w-btn w-btn--md w-btn--tonal" @click="repeatMissed">
            <NIcon name="repeat" :size="20" />
            {{ t('study.repeatButton', { n: missed.length }) }}
          </button>
        </section>

        <div v-if="nextLine" class="summary__next">
          <NIcon name="clock" :size="20" />
          <i18n-t keypath="study.nextReview" tag="span" scope="global">
            <template #when>{{ nextLine.when }}</template>
            <template #cards><strong>{{ nextLine.cards }}</strong></template>
          </i18n-t>
        </div>

        <div class="study__spacer" />

        <button type="button" class="w-btn" @click="leave">{{ t('common.done') }}</button>
      </div>
    </template>

    <!-- A card -->
    <template v-else-if="card">
      <div class="w-topbar study__bar">
        <button type="button" class="w-icon-btn" :aria-label="t('study.end')" @click="leave">
          <NIcon name="close" />
        </button>
        <div
          class="progress"
          role="progressbar"
          :aria-label="t('study.progress')"
          aria-valuemin="0"
          :aria-valuemax="study.total"
          :aria-valuenow="study.done"
        >
          <span :style="{ width: `${(study.done / Math.max(1, study.total)) * 100}%` }" />
        </div>
        <span class="progress__count">{{ study.position }} / {{ study.total }}</span>
      </div>

      <div class="study__chips">
        <span class="direction-chip">
          {{ language(card.language).endonym }}
          <NIcon name="arrowRight" :size="14" :stroke-width="2.2" />
          {{ language(nativeLanguage).endonym }}
        </span>
        <span class="state-chip" :class="{ 'state-chip--new': card.state === 'NEW' }">
          {{ card.state === 'NEW' ? t('study.chipNew') : t('study.chipReview') }}
        </span>
      </div>

      <div class="deck">
        <div class="deck__shadow deck__shadow--far" aria-hidden="true" />
        <div class="deck__shadow deck__shadow--near" aria-hidden="true" />
        <div class="deck__card" :class="{ 'deck__card--flipped': study.flipped }">
          <!-- Front -->
          <section
            class="face face--front"
            :aria-label="t('study.card')"
            :aria-hidden="study.flipped"
            @click="study.flip()"
          >
            <span class="face__hint">{{ t('study.recall') }}</span>
            <div class="face__center">
              <span class="face__term">{{ card.term }}</span>
              <span v-if="ipa" class="face__ipa ipa">{{ ipa }}</span>
              <button
                type="button"
                class="face__speak face__speak--lg"
                :aria-label="t('study.pronounce')"
                @click.stop="speak"
              >
                <NIcon name="speaker" :size="26" />
              </button>
            </div>
            <span class="face__flip">
              <NIcon name="repeat" :size="16" :stroke-width="1.9" />
              {{ t('study.tapToFlip') }}
            </span>
          </section>

          <!-- Back -->
          <section class="face face--back" :aria-label="t('study.cardBack')" :aria-hidden="!study.flipped">
            <div class="back__head">
              <div class="back__term-text">
                <span class="back__term">{{ card.term }}</span>
                <span v-if="ipa || card.part_of_speech_short" class="back__ipa ipa">
                  {{ [ipa, card.part_of_speech_short].filter(Boolean).join(' · ') }}
                </span>
              </div>
              <button type="button" class="face__speak" :aria-label="t('study.pronounce')" @click="speak">
                <NIcon name="speaker" :size="22" />
              </button>
            </div>
            <div class="back__rule" />
            <div class="back__translation">
              <span class="back__label">{{ t('study.translation') }}</span>
              <span class="back__answer">{{ card.translations.join(', ') || card.note }}</span>
            </div>
            <p v-if="card.note && card.translations.length > 0" class="back__note">{{ card.note }}</p>
            <div v-if="card.examples[0]" class="back__example">
              <span class="back__sentence">
                <template v-for="(part, j) in highlight(card.examples[0].text, card.term)" :key="j">
                  <strong v-if="part.match">{{ part.text }}</strong>
                  <template v-else>{{ part.text }}</template>
                </template>
              </span>
              <span v-if="card.examples[0].translation" class="back__example-translation">{{ card.examples[0].translation }}</span>
            </div>
            <div class="study__spacer" />
            <router-link :to="{ name: 'word', params: { id: card.id } }" class="w-link-btn back__open">
              {{ t('study.openWord') }}
              <NIcon name="chevronRight" :size="18" />
            </router-link>
          </section>
        </div>
      </div>

      <div class="study__footer">
        <button v-if="!study.flipped" type="button" class="w-btn w-btn--lg" @click="study.flip()">
          {{ t('study.showAnswer') }}
        </button>
        <template v-else>
          <span class="rate__question">{{ t('study.howEasy') }}</span>
          <div role="group" class="rate" :aria-label="t('study.rate')">
            <button
              v-for="grade in GRADES"
              :key="grade.rating"
              type="button"
              class="rate__btn"
              :class="`rate__btn--${grade.rating.toLowerCase()}`"
              @click="answer(grade.rating)"
            >
              <span class="rate__label">{{ t(grade.label) }}</span>
              <span class="rate__interval">{{ formatInterval(t, card.next_intervals[grade.key]) }}</span>
            </button>
          </div>
        </template>
      </div>
    </template>

    <!-- The last answer is on its way -->
    <div v-else class="study__center"><span class="w-spinner" /></div>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import NIcon from '@/components/NIcon.vue';
import { fetchDashboard } from '@/services/study-api';
import { reminderTimeOn, rescheduleReminders } from '@/services/reminders';
import { speakWord } from '@/services/speech';
import type { Dashboard, NextIntervals, Rating } from '@/services/types';
import { useAuthStore } from '@/stores/auth-store';
import { useStudyStore } from '@/stores/study-store';
import { useWordsStore } from '@/stores/words-store';
import { formatInterval, formatWhen, highlight } from '@/utils/format';
import { language, type LanguageCode } from '@/utils/languages';
import { errorText, toast } from '@/utils/notify';

const GRADES: Array<{ rating: Rating; key: keyof NextIntervals; label: string; color: string }> = [
  { rating: 'AGAIN', key: 'again', label: 'study.again', color: 'var(--w-grade-again)' },
  { rating: 'HARD', key: 'hard', label: 'study.hard', color: 'var(--w-grade-hard)' },
  { rating: 'GOOD', key: 'good', label: 'study.good', color: 'var(--w-grade-good)' },
  { rating: 'EASY', key: 'easy', label: 'study.easy', color: 'var(--w-grade-easy)' },
];

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const study = useStudyStore();
const words = useWordsStore();

const nextDashboard = ref<Dashboard | null>(null);

const card = computed(() => study.current);
const nativeLanguage = computed<LanguageCode>(() => auth.user?.native_language ?? 'en');
const ipa = computed(() => card.value?.transcriptions[0]?.ipa ?? null);
const missed = computed(() => Object.entries(study.missed).map(([id, term]) => ({ id, term })));

const summaryLine = computed(() =>
  [
    t('plural.cards', { n: study.total }, study.total),
    ...(study.newCards > 0 ? [t('plural.newCards', { n: study.newCards }, study.newCards)] : []),
    t('plural.minutes', { n: study.minutes }, study.minutes),
  ].join(' · '),
);

const nextLine = computed(() => {
  const dashboard = nextDashboard.value;
  if (!dashboard?.next_review_at || dashboard.next_review_count === 0) return null;

  const date = new Date(dashboard.next_review_at);
  return {
    when: formatWhen(t, date, locale.value, reminderTimeOn(auth.user?.reminders, date)),
    cards: t('plural.cards', { n: dashboard.next_review_count }, dashboard.next_review_count),
  };
});

function requestedIds(): string[] | undefined {
  const ids = typeof route.query.ids === 'string' ? route.query.ids.split(',').filter(Boolean) : [];
  return ids.length > 0 ? ids : undefined;
}

async function begin(ids?: string[]) {
  const code = auth.activeLanguage;
  if (!code) return;

  nextDashboard.value = null;
  try {
    await study.start(code, ids);
  } catch (e) {
    toast(errorText(e));
    leave();
  }
}

function speak() {
  if (card.value) void speakWord(card.value.term, card.value.language, card.value.transcriptions[0]?.accent);
}

async function answer(rating: Rating) {
  try {
    await study.answer(rating);
  } catch (e) {
    toast(t('study.saveFailed') + (errorText(e) === t('common.offline') ? '' : ` ${errorText(e)}`));
  }
}

function repeatMissed() {
  void begin(missed.value.map((word) => word.id));
}

function leave() {
  study.end();
  if (window.history.state?.back) router.back();
  else void router.replace({ name: 'home' });
}

// The session is over: plan reminders again and see what comes next.
watch(
  () => study.finished && study.total > 0,
  async (finished) => {
    if (!finished || !study.language) return;
    words.changed();
    void rescheduleReminders();
    nextDashboard.value = await fetchDashboard(study.language).catch(() => null);
  },
  { immediate: true },
);

onMounted(() => {
  const ids = requestedIds();
  // Coming back from a word opened on a card or the summary continues the session.
  const resumable =
    (study.active || study.finished) &&
    study.language === auth.activeLanguage &&
    Date.now() - study.startedAt < 60 * 60 * 1000 &&
    !ids;
  if (!resumable) void begin(ids);
});

// Only a look at a word keeps the session; leaving any other way ends it.
onBeforeRouteLeave((to) => {
  if (to.name !== 'word') study.end();
});
</script>

<style scoped>
.study {
  padding-bottom: calc(28px + var(--w-safe-bottom));
}

.study__center {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 24px 32px;
  color: var(--w-primary);
  text-align: center;
}

.study__empty {
  color: var(--w-ink);
}

.study__spacer {
  flex: 1;
}

.study__bar {
  gap: 8px;
  padding-right: 20px;
}

.progress {
  flex: 1;
  height: 6px;
  border-radius: 3px;
  background: var(--w-divider);
  display: flex;
  overflow: hidden;
}

.progress span {
  height: 6px;
  border-radius: 3px;
  background: var(--w-primary);
  transition: width 0.3s ease;
}

.progress__count {
  min-width: 44px;
  font-size: 14px;
  font-weight: 600;
  text-align: right;
}

.study__chips {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 40px;
  padding: 0 16px;
  flex-shrink: 0;
}

.direction-chip {
  height: 32px;
  padding: 0 12px;
  border-radius: 8px;
  border: 1px solid var(--w-outline-soft);
  box-sizing: border-box;
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 500;
  color: var(--w-muted);
}

.state-chip {
  height: 32px;
  padding: 0 12px;
  border-radius: 8px;
  background: var(--w-warm);
  color: var(--w-on-warm);
  display: flex;
  align-items: center;
  font-size: 13px;
  font-weight: 600;
}

.state-chip--new {
  background: var(--w-tonal);
  color: var(--w-on-tonal);
}

/* The deck */

.deck {
  position: relative;
  flex: 1;
  min-height: 360px;
  margin: 16px 16px 32px;
  display: flex;
  perspective: 1400px;
}

.deck__shadow {
  position: absolute;
  border-radius: 28px;
}

.deck__shadow--far {
  left: 28px;
  right: 28px;
  top: 20px;
  bottom: -20px;
  background: var(--w-divider);
}

.deck__shadow--near {
  left: 14px;
  right: 14px;
  top: 10px;
  bottom: -10px;
  background: var(--w-container);
}

.deck__card {
  position: relative;
  flex: 1;
  transform-style: preserve-3d;
  transition: transform 0.45s cubic-bezier(0.2, 0, 0, 1);
}

.deck__card--flipped {
  transform: rotateY(180deg);
}

.face {
  position: absolute;
  inset: 0;
  border-radius: 28px;
  background: var(--w-surface);
  box-shadow:
    0 1px 3px rgba(23, 29, 27, 0.1),
    0 6px 16px rgba(23, 29, 27, 0.06);
  display: flex;
  flex-direction: column;
  padding: 24px;
  box-sizing: border-box;
  backface-visibility: hidden;
  -webkit-backface-visibility: hidden;
  overflow-y: auto;
}

.face--front {
  align-items: center;
  cursor: pointer;
}

.face--back {
  transform: rotateY(180deg);
  gap: 20px;
}

.face__hint {
  font-size: 13px;
  font-weight: 500;
  color: var(--w-muted);
}

.face__center {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  text-align: center;
}

.face__term {
  font-size: 44px;
  line-height: 52px;
  font-weight: 600;
  letter-spacing: -0.02em;
  overflow-wrap: anywhere;
}

.face__ipa {
  font-size: 18px;
  color: var(--w-muted);
}

.face__speak {
  width: 48px;
  height: 48px;
  border-radius: 24px;
  border: none;
  background: var(--w-tonal);
  color: var(--w-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
}

.face__speak--lg {
  margin-top: 12px;
  width: 56px;
  height: 56px;
  border-radius: 28px;
}

.face__flip {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--w-muted);
}

.back__head {
  display: flex;
  align-items: center;
  gap: 12px;
}

.back__term-text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.back__term {
  font-size: 30px;
  line-height: 38px;
  font-weight: 600;
  letter-spacing: -0.01em;
  overflow-wrap: anywhere;
}

.back__ipa {
  font-size: 15px;
  color: var(--w-muted);
}

.back__rule {
  height: 1px;
  background: var(--w-divider);
  flex-shrink: 0;
}

.back__translation {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.back__label {
  font-size: 12px;
  font-weight: 600;
  color: var(--w-muted);
  letter-spacing: 0.02em;
}

.back__answer {
  font-size: 26px;
  line-height: 32px;
  font-weight: 600;
  color: var(--w-primary);
}

.back__note {
  font-size: 15px;
  line-height: 22px;
  color: var(--w-muted);
  white-space: pre-line;
}

.back__example {
  display: flex;
  flex-direction: column;
  gap: 6px;
  background: var(--w-container-low);
  border-radius: 16px;
  padding: 14px 16px;
}

.back__sentence {
  font-size: 15px;
  line-height: 22px;
}

.back__sentence strong {
  font-weight: 600;
}

.back__example-translation {
  font-size: 14px;
  line-height: 20px;
  color: var(--w-muted);
}

.back__open {
  align-self: flex-start;
  gap: 4px;
}

/* Answer */

.study__footer {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 0 16px;
  flex-shrink: 0;
}

.rate__question {
  font-size: 14px;
  font-weight: 500;
  color: var(--w-muted);
  text-align: center;
}

.rate {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
}

.rate__btn {
  height: 64px;
  border-radius: 16px;
  border: none;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}

.rate__label {
  font-size: 15px;
  font-weight: 600;
}

.rate__interval {
  font-size: 12px;
  font-weight: 500;
}

.rate__btn--again {
  border: 1.5px solid var(--w-danger);
  background: transparent;
  color: var(--w-danger);
}

.rate__btn--hard {
  background: var(--w-divider);
  color: var(--w-ink);
}

.rate__btn--hard .rate__interval {
  color: var(--w-muted);
}

.rate__btn--good {
  background: var(--w-primary);
  color: var(--w-on-primary);
}

.rate__btn--easy {
  background: var(--w-primary-soft);
  color: var(--w-on-primary-soft);
}

/* Summary */

.summary {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 0 16px;
}

.summary__hero {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 8px 0;
}

.summary__badge {
  width: 88px;
  height: 88px;
  border-radius: 44px;
  background: var(--w-primary-soft);
  color: var(--w-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 12px;
}

.summary__title {
  font-size: 28px;
  line-height: 36px;
  font-weight: 600;
  letter-spacing: -0.01em;
  text-align: center;
}

.summary__line {
  font-size: 15px;
  line-height: 22px;
  color: var(--w-muted);
  text-align: center;
}

.summary__card {
  background: var(--w-container);
  border-radius: 20px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.summary__card--missed {
  gap: 12px;
}

.summary__h2 {
  font-size: 14px;
  line-height: 20px;
  font-weight: 600;
}

.summary__missed-head {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.summary__hint {
  font-size: 13px;
  line-height: 18px;
}

.grades-bar {
  display: flex;
  gap: 3px;
  height: 12px;
}

.grades-bar span {
  border-radius: 6px;
}

.grades {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
}

.grade {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.grade__label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--w-muted);
}

.grade__swatch {
  width: 8px;
  height: 8px;
  border-radius: 2px;
}

.grade__count {
  font-size: 22px;
  line-height: 28px;
  font-weight: 600;
}

.summary__next {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 4px;
  font-size: 14px;
  line-height: 20px;
  color: var(--w-muted);
}

.summary__next svg {
  flex-shrink: 0;
}

.summary__next strong {
  font-weight: 600;
  color: var(--w-ink);
}
</style>
