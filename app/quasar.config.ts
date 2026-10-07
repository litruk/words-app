// Configuration for your app
// https://v2.quasar.dev/quasar-cli-vite/quasar-config-file

import { defineConfig } from '#q-app';

export default defineConfig(() => {
  return {
    // https://v2.quasar.dev/quasar-cli-vite/boot-files
    // i18n first: auth may already need translated messages.
    boot: ['i18n', 'auth'],

    css: ['app.css'],

    extras: [],

    build: {
      sassVariables: 'src/css/quasar.variables.scss',

      typescript: {
        strict: true,
        vueShim: true,
      },

      vueRouterMode: 'hash',
    },

    devServer: {
      open: false,
    },

    framework: {
      config: {},
      plugins: ['Notify'],
    },

    animations: [],

    capacitor: {
      hideSplashscreen: true,
    },
  };
});
