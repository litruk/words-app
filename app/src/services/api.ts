import { gqlQuery, UnauthenticatedError } from '@/services/graphql-client';
import { useAuthStore } from '@/stores/auth-store';

/**
 * A request as the signed-in user. A rejected token ends the local session;
 * the router guard then shows the sign-in screen.
 */
export async function authed<T>(query: string, variables: Record<string, unknown> = {}): Promise<T> {
  const auth = useAuthStore();

  try {
    return await gqlQuery<T>(query, variables, auth.token);
  } catch (e) {
    if (e instanceof UnauthenticatedError) auth.expire();
    throw e;
  }
}
