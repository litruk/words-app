import { defineStore } from 'pinia';
import { NetworkError } from '@/services/graphql-client';
import { fetchDashboard } from '@/services/study-api';
import { addWord, fetchLanguageStats, fetchWords, type NewWord } from '@/services/words-api';
import type { Dashboard, LanguageStats, Word, WordSummary } from '@/services/types';

const PENDING_KEY = 'words-app:pending-words';

/** A word added without a connection, waiting to be sent. */
export interface PendingWord extends NewWord {
  localId: string;
  createdAt: string;
}

function storedPending(): PendingWord[] {
  try {
    return JSON.parse(localStorage.getItem(PENDING_KEY) ?? '[]') as PendingWord[];
  } catch {
    return [];
  }
}

/**
 * What the home screen and the dictionary show, kept between visits so a
 * screen renders at once and refreshes behind it, plus the offline queue of
 * words that still have to reach the server.
 */
export const useWordsStore = defineStore('words', {
  state: () => ({
    dashboards: {} as Record<string, Dashboard>,
    recent: {} as Record<string, WordSummary[]>,
    stats: null as LanguageStats[] | null,
    pending: storedPending(),
    syncing: false,
  }),

  getters: {
    statsFor: (state) => (language: string) => state.stats?.find((row) => row.language === language) ?? null,
    pendingFor: (state) => (language: string) => state.pending.filter((word) => word.language === language),
  },

  actions: {
    async loadDashboard(language: string) {
      this.dashboards[language] = await fetchDashboard(language);
      return this.dashboards[language];
    },

    async loadRecent(language: string) {
      const page = await fetchWords({ language, sort: 'RECENT', first: 3 });
      this.recent[language] = page.data;
    },

    async loadStats() {
      this.stats = await fetchLanguageStats();
    },

    /**
     * Adds a word, or keeps it for later when there is no connection. Errors
     * from the server itself (a duplicate, a missing translation) are thrown.
     */
    async add(word: NewWord): Promise<{ word: Word } | { queued: PendingWord }> {
      try {
        const saved = await addWord(word);
        this.changed();
        return { word: saved };
      } catch (e) {
        if (!(e instanceof NetworkError)) throw e;

        const queued: PendingWord = { ...word, localId: crypto.randomUUID(), createdAt: new Date().toISOString() };
        this.pending.push(queued);
        this.persistPending();
        return { queued };
      }
    },

    /**
     * Sends the words saved offline. Stops at the first network failure; a
     * word the server refuses (already in the dictionary) is dropped.
     * Returns how many reached the dictionary.
     */
    async flush(): Promise<number> {
      if (this.syncing || this.pending.length === 0) return 0;

      this.syncing = true;
      let sent = 0;

      try {
        for (const word of [...this.pending]) {
          try {
            const { localId: _localId, createdAt: _createdAt, ...fields } = word;
            await addWord(fields);
            sent++;
          } catch (e) {
            if (e instanceof NetworkError) break;
          }

          this.pending = this.pending.filter((candidate) => candidate.localId !== word.localId);
          this.persistPending();
        }
      } finally {
        this.syncing = false;
      }

      if (sent > 0) this.changed();
      return sent;
    },

    /** Something in the dictionary changed: cached counts are stale. */
    changed() {
      this.stats = null;
    },

    persistPending() {
      localStorage.setItem(PENDING_KEY, JSON.stringify(this.pending));
    },

    reset() {
      this.dashboards = {};
      this.recent = {};
      this.stats = null;
    },
  },
});
