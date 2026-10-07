import { currentLocale } from '@/i18n';

const API_URL = import.meta.env.QCLI_API_URL ?? 'https://api-words.litruk.dev/graphql';

export class GraphQLError extends Error {}

/**
 * The server rejected the request's credentials: the token is missing, revoked
 * or belongs to a deleted account. Only this warrants dropping the local
 * session - a server-side fault must never log the user out.
 */
export class UnauthenticatedError extends GraphQLError {}

/**
 * The server could not be reached at all: no connection, DNS, timeout. Screens
 * show their offline state for it, and new words wait in the offline queue.
 */
export class NetworkError extends Error {}

interface GraphQLResponse<T> {
  data: T | null;
  errors?: Array<{
    message: string;
    extensions?: { validation?: Record<string, string[]>; guards?: string[] };
  }>;
}

function errorMessage(errors: NonNullable<GraphQLResponse<unknown>['errors']>): string {
  const validation = errors[0]?.extensions?.validation;
  if (validation) {
    const firstMessage = Object.values(validation)[0]?.[0];
    if (firstMessage) return firstMessage;
  }
  return errors[0]?.message ?? 'GraphQL request failed';
}

/** Lighthouse reports a failed guard with `extensions.guards`, e.g. `["sanctum"]`. */
function isUnauthenticated(errors: NonNullable<GraphQLResponse<unknown>['errors']>): boolean {
  return errors.some((error) => (error.extensions?.guards?.length ?? 0) > 0);
}

function graphQLError(errors: NonNullable<GraphQLResponse<unknown>['errors']>): GraphQLError {
  const message = errorMessage(errors);

  return isUnauthenticated(errors) ? new UnauthenticatedError(message) : new GraphQLError(message);
}

function timezone(): string {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
  } catch {
    return 'UTC';
  }
}

export async function gqlQuery<T>(
  query: string,
  variables: Record<string, unknown> = {},
  token?: string | null,
): Promise<T> {
  let res: Response;

  try {
    res = await fetch(API_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        // Server messages in the interface language, "today" in the device's zone.
        'Accept-Language': currentLocale(),
        'X-Timezone': timezone(),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: JSON.stringify({ query, variables }),
    });
  } catch (e) {
    throw new NetworkError(e instanceof Error ? e.message : 'Network request failed');
  }

  let json: GraphQLResponse<T>;
  try {
    json = (await res.json()) as GraphQLResponse<T>;
  } catch {
    throw new GraphQLError(`Unexpected response (${res.status})`);
  }

  if (json.errors?.length) {
    throw graphQLError(json.errors);
  }
  if (!json.data) {
    throw new GraphQLError('GraphQL response had no data');
  }
  return json.data;
}
