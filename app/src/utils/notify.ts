import { Notify } from 'quasar';
import { i18n } from '@/i18n';
import { GraphQLError, NetworkError } from '@/services/graphql-client';

/** A Material snackbar at the bottom of the screen. */
export function toast(message: string, action?: { label: string; handler: () => void }): void {
  Notify.create({
    message,
    classes: 'w-snackbar',
    position: 'bottom',
    timeout: action ? 5000 : 3000,
    actions: action ? [{ label: action.label, color: 'teal-2', noCaps: true, handler: action.handler }] : [],
  });
}

/**
 * What to tell the user about a failed request: the server's own (translated)
 * message where it wrote one for people, "no connection", or a generic line.
 */
export function errorText(e: unknown): string {
  const t = i18n.global.t;

  if (e instanceof NetworkError) return t('common.offline');
  if (e instanceof GraphQLError && e.message && !/internal server error|unexpected response|no data/i.test(e.message)) {
    return e.message;
  }
  return t('common.genericError');
}
