import { defineBoot } from '#q-app';
import { watch } from 'vue';
import { App as CapacitorApp } from '@capacitor/app';
import { Capacitor } from '@capacitor/core';
import { useAuthStore } from '@/stores/auth-store';
import { useWordsStore } from '@/stores/words-store';
import { UnauthenticatedError } from '@/services/graphql-client';
import { handleReminderActions, rescheduleReminders } from '@/services/reminders';

const PUBLIC_ROUTES = ['login', 'register', 'forgot-password'];

export default defineBoot(async ({ router }) => {
  const authStore = useAuthStore();
  const wordsStore = useWordsStore();

  if (authStore.token) {
    try {
      await authStore.fetchCurrentUser();
    } catch (e) {
      // Only a real auth rejection from the server means the token is
      // stale/revoked. Neither a network-level failure (backend unreachable)
      // nor a server-side fault ("Internal server error") should log the user
      // out — let the app render optimistically and have individual screens
      // surface their own errors.
      if (e instanceof UnauthenticatedError) authStore.clearSession();
    }
  }

  router.beforeEach((to) => {
    const isPublic = PUBLIC_ROUTES.includes(String(to.name));

    if (!authStore.token) return isPublic ? true : { name: 'login' };
    if (isPublic) return { name: 'home' };
    // Signed up but no languages picked yet: the second registration step.
    if (authStore.needsLanguages && to.name !== 'onboarding') return { name: 'onboarding' };
    return true;
  });

  // A session that ends anywhere (sign-out, a revoked token) lands on sign-in.
  watch(
    () => authStore.token,
    (token) => {
      if (!token) {
        wordsStore.reset();
        void router.replace({ name: 'login' });
      }
    },
  );

  // Words saved offline go out once there is a connection again, and the
  // reminders are planned again whenever the app comes back to the front.
  const catchUp = () => {
    if (!authStore.token) return;
    void wordsStore.flush();
    void rescheduleReminders();
  };

  window.addEventListener('online', catchUp);
  if (Capacitor.isNativePlatform()) {
    void CapacitorApp.addListener('resume', catchUp);
  }
  handleReminderActions(router);
  catchUp();
});
