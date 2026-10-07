import { defineStore } from 'pinia';
import { fetchStudyQueue, reviewWord } from '@/services/study-api';
import type { Rating, StudyCard } from '@/services/types';

/** A card answered "again" comes back this many cards later in the same session. */
const REQUEUE_GAP = 3;

/** Learning steps are minutes long; anything due sooner returns within the session. */
const REQUEUE_WITHIN_MS = 20 * 60 * 1000;

export type Grades = Record<Rating, number>;

/**
 * One study session: the queue, where the user is in it and how it went.
 * Kept in a store so opening a word from the back of a card and coming back
 * returns to the same card.
 */
export const useStudyStore = defineStore('study', {
  state: () => ({
    language: null as string | null,
    queue: [] as StudyCard[],
    /** Distinct cards the session started with. */
    total: 0,
    /** Cards that have left the session (answered and not due again soon). */
    done: 0,
    newCards: 0,
    flipped: false,
    shownAt: 0,
    startedAt: 0,
    finishedAt: 0,
    grades: { AGAIN: 0, HARD: 0, GOOD: 0, EASY: 0 } as Grades,
    /** Terms answered "again", by word id, for the summary. */
    missed: {} as Record<string, string>,
    loading: false,
    /** The answer to the last card is on its way. */
    saving: false,
    finished: false,
  }),

  getters: {
    current: (state): StudyCard | null => state.queue[0] ?? null,
    active: (state) => state.language !== null && !state.finished,
    position: (state) => Math.min(state.done + 1, state.total),
    minutes: (state) => Math.max(1, Math.round(((state.finishedAt || Date.now()) - state.startedAt) / 60000)),
  },

  actions: {
    async start(language: string, ids?: string[]) {
      this.loading = true;

      try {
        const queue = await fetchStudyQueue(language, ids);
        this.$reset();
        this.language = language;
        this.queue = queue;
        this.total = queue.length;
        this.newCards = queue.filter((card) => card.state === 'NEW').length;
        this.startedAt = Date.now();
        this.shownAt = Date.now();
        this.finished = queue.length === 0;
      } finally {
        this.loading = false;
      }
    },

    flip() {
      this.flipped = true;
    },

    /**
     * Records the answer and moves on at once; the server's verdict decides
     * whether the card comes back later in this session.
     */
    async answer(rating: Rating) {
      const card = this.current;
      if (!card) return;

      const thinking = Date.now() - this.shownAt;
      this.queue.shift();
      this.grades[rating]++;
      if (rating === 'AGAIN') this.missed[card.id] = card.term;

      // With cards left, the next one shows at once. After the last one the
      // answer decides: it may bring the card straight back.
      const wasLast = this.queue.length === 0;
      if (!wasLast) this.next();
      else this.saving = true;

      try {
        const updated = await reviewWord(card.id, rating, thinking);
        const dueSoon = updated.due_at !== null && new Date(updated.due_at).getTime() - Date.now() < REQUEUE_WITHIN_MS;

        if (dueSoon) this.queue.splice(Math.min(REQUEUE_GAP, this.queue.length), 0, updated);
        else this.done++;
      } catch (e) {
        // Not saved: the card comes round again at the end of the session.
        this.grades[rating]--;
        if (rating === 'AGAIN') delete this.missed[card.id];
        this.queue.push(card);
        throw e;
      } finally {
        this.saving = false;
        if (wasLast) this.next();
      }
    },

    next() {
      this.flipped = false;
      this.shownAt = Date.now();
      if (this.queue.length === 0) this.finish();
    },

    finish() {
      this.finished = true;
      this.finishedAt = Date.now();
    },

    end() {
      this.$reset();
    },
  },
});
