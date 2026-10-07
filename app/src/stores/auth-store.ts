import { defineStore } from 'pinia';
import { gqlQuery, UnauthenticatedError } from '@/services/graphql-client';
import { setLocale } from '@/i18n';
import type { Reminders } from '@/services/types';
import type { LanguageCode } from '@/utils/languages';

const TOKEN_KEY = 'words-app:token';
const USER_KEY = 'words-app:user';
const ACTIVE_LANGUAGE_KEY = 'words-app:active-language';

export interface AuthUser {
  id: string;
  name: string;
  email: string;
  native_language: LanguageCode;
  studied_languages: LanguageCode[];
  reminders: Reminders;
}

const REMINDER_FIELDS = `enabled times { time enabled } days only_when_due skip_if_studied`;

const USER_FIELDS = `id name email native_language studied_languages reminders { ${REMINDER_FIELDS} }`;

interface AuthPayload {
  token: string;
  user: AuthUser;
}

function storedUser(): AuthUser | null {
  try {
    return JSON.parse(localStorage.getItem(USER_KEY) ?? 'null') as AuthUser | null;
  } catch {
    return null;
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem(TOKEN_KEY),
    user: storedUser(),
    /** The language the home screen, adding and studying work in. */
    preferredLanguage: localStorage.getItem(ACTIVE_LANGUAGE_KEY),
  }),

  getters: {
    studiedLanguages: (state): LanguageCode[] => state.user?.studied_languages ?? [],

    /** The picked language while it is still studied, else the first one. */
    activeLanguage(): LanguageCode | null {
      const studied = this.studiedLanguages;
      const preferred = studied.find((code) => code === this.preferredLanguage);

      return preferred ?? studied[0] ?? null;
    },

    needsLanguages(): boolean {
      return this.user !== null && this.studiedLanguages.length === 0;
    },
  },

  actions: {
    /** Verifies the stored token is still valid and refreshes the cached user. Throws if the token is stale. */
    async fetchCurrentUser() {
      const data = await gqlQuery<{ me: AuthUser | null }>(`query Me { me { ${USER_FIELDS} } }`, {}, this.token);
      if (!data.me) throw new UnauthenticatedError('Session expired');
      this.setUser(data.me);
    },

    async register(name: string, email: string, password: string, nativeLanguage: LanguageCode) {
      const data = await gqlQuery<{ register: AuthPayload }>(
        `mutation Register($name: String!, $email: String!, $password: String!, $native: String) {
          register(name: $name, email: $email, password: $password, native_language: $native) { token user { ${USER_FIELDS} } }
        }`,
        { name, email, password, native: nativeLanguage },
      );
      this.setSession(data.register);
    },

    async login(email: string, password: string) {
      const data = await gqlQuery<{ login: AuthPayload }>(
        `mutation Login($email: String!, $password: String!) {
          login(email: $email, password: $password) { token user { ${USER_FIELDS} } }
        }`,
        { email, password },
      );
      this.setSession(data.login);
    },

    async logout() {
      try {
        await gqlQuery(`mutation Logout { logout }`, {}, this.token);
      } catch {
        // best-effort — the local session is cleared regardless
      }
      this.clearSession();
    },

    async forgotPassword(email: string) {
      await gqlQuery<{ forgotPassword: boolean }>(
        `mutation ForgotPassword($email: String!) { forgotPassword(email: $email) }`,
        { email },
      );
    },

    async updateProfile(name: string, email: string) {
      const data = await gqlQuery<{ updateProfile: AuthUser }>(
        `mutation UpdateProfile($name: String!, $email: String) { updateProfile(name: $name, email: $email) { ${USER_FIELDS} } }`,
        { name, email },
        this.token,
      );
      this.setUser(data.updateProfile);
    },

    async changePassword(currentPassword: string, newPassword: string) {
      await gqlQuery<{ changePassword: boolean }>(
        `mutation ChangePassword($currentPassword: String!, $newPassword: String!) {
          changePassword(currentPassword: $currentPassword, newPassword: $newPassword)
        }`,
        { currentPassword, newPassword },
        this.token,
      );
    },

    async deleteAccount(password: string) {
      await gqlQuery<{ deleteAccount: boolean }>(
        `mutation DeleteAccount($password: String!) { deleteAccount(password: $password) }`,
        { password },
        this.token,
      );
      this.clearSession();
    },

    async updateLanguages(nativeLanguage: LanguageCode, studiedLanguages: LanguageCode[]) {
      const data = await gqlQuery<{ updateLanguages: AuthUser }>(
        `mutation UpdateLanguages($native: String!, $studied: [String!]!) {
          updateLanguages(native_language: $native, studied_languages: $studied) { ${USER_FIELDS} }
        }`,
        { native: nativeLanguage, studied: studiedLanguages },
        this.token,
      );
      this.setUser(data.updateLanguages);
    },

    async updateReminders(reminders: Reminders) {
      const data = await gqlQuery<{ updateReminders: Reminders }>(
        `mutation UpdateReminders($input: RemindersInput!) { updateReminders(input: $input) { ${REMINDER_FIELDS} } }`,
        { input: reminders },
        this.token,
      );
      if (this.user) this.setUser({ ...this.user, reminders: data.updateReminders });
    },

    setActiveLanguage(code: LanguageCode) {
      this.preferredLanguage = code;
      localStorage.setItem(ACTIVE_LANGUAGE_KEY, code);
    },

    setUser(user: AuthUser) {
      this.user = user;
      localStorage.setItem(USER_KEY, JSON.stringify(user));
      // The interface speaks the user's native language.
      setLocale(user.native_language);
    },

    setSession(payload: AuthPayload) {
      this.token = payload.token;
      localStorage.setItem(TOKEN_KEY, payload.token);
      this.setUser(payload.user);
    },

    /** The server no longer accepts the token. */
    expire() {
      this.clearSession();
    },

    clearSession() {
      this.token = null;
      this.user = null;
      localStorage.removeItem(TOKEN_KEY);
      localStorage.removeItem(USER_KEY);
    },
  },
});
