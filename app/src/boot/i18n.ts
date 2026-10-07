import { defineBoot } from '#q-app';
import { currentLocale, i18n } from '@/i18n';

export default defineBoot(({ app }) => {
  app.use(i18n);
  document.documentElement.lang = currentLocale();
});
