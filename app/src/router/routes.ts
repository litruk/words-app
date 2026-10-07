import type { RouteRecordRaw } from 'vue-router';

declare module 'vue-router' {
  interface RouteMeta {
    /** The bottom bar is shown on these screens, with this tab lit. */
    tab?: 'home' | 'dictionary' | 'settings';
  }
}

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    component: () => import('@/layouts/AuthLayout.vue'),
    children: [{ path: '', name: 'login', component: () => import('@/pages/LoginPage.vue') }],
  },
  {
    path: '/register',
    component: () => import('@/layouts/AuthLayout.vue'),
    children: [{ path: '', name: 'register', component: () => import('@/pages/RegisterPage.vue') }],
  },
  {
    path: '/forgot-password',
    component: () => import('@/layouts/AuthLayout.vue'),
    children: [
      { path: '', name: 'forgot-password', component: () => import('@/pages/ForgotPasswordPage.vue') },
    ],
  },
  {
    path: '/onboarding',
    component: () => import('@/layouts/AuthLayout.vue'),
    children: [{ path: '', name: 'onboarding', component: () => import('@/pages/OnboardingPage.vue') }],
  },

  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    children: [
      { path: '', name: 'home', component: () => import('@/pages/HomePage.vue'), meta: { tab: 'home' } },
      {
        path: 'dictionary',
        name: 'dictionary',
        component: () => import('@/pages/DictionaryPage.vue'),
        meta: { tab: 'dictionary' },
      },
      {
        path: 'settings',
        name: 'settings',
        component: () => import('@/pages/SettingsPage.vue'),
        meta: { tab: 'settings' },
      },
      { path: 'settings/reminders', name: 'reminders', component: () => import('@/pages/RemindersPage.vue') },
      { path: 'settings/account', name: 'account', component: () => import('@/pages/AccountPage.vue') },
      // ?mode=voice|text, ?term= to start from a typed or searched text
      { path: 'add', name: 'add', component: () => import('@/pages/AddWordPage.vue') },
      { path: 'words/:id', name: 'word', component: () => import('@/pages/WordPage.vue') },
      // ?ids=1,2,3 for exactly those cards
      { path: 'study', name: 'study', component: () => import('@/pages/StudyPage.vue') },
    ],
  },

  // Always leave this as last one
  {
    path: '/:catchAll(.*)*',
    component: () => import('@/pages/ErrorNotFound.vue'),
  },
];

export default routes;
