# Slovnik API

Laravel 13 + [Lighthouse](https://lighthouse-php.com) GraphQL API for the
Slovnik app. One endpoint, `/graphql`; the schema is `graphql/schema.graphql`.
Besides it the host serves only the password reset page the reset mail links
to (`/reset-password/{token}`).

## Local development

```bash
cp .env.example .env              # set GEMINI_API_KEY
composer install
./vendor/bin/sail up -d           # app on :8090, Postgres :5433, Redis :6381
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan test
```

The ports are shifted (`APP_PORT`, `FORWARD_*` in `.env`) so the stack runs next
to receipt-app's; `COMPOSE_PROJECT_NAME=words` keeps the containers apart.

## Pieces worth knowing

| Where | What |
| --- | --- |
| `app/Services/LookupService.php` | Gemini dictionary entries, cached per text and direction in `lookups` |
| `app/Services/Scheduler.php` | SM-2 spaced repetition (answers: again / hard / good / easy) |
| `app/Services/StudyPlanner.php` | due counts, the daily budget of new cards, the session queue, the reminder forecast |
| `app/Http/Middleware/ApplyClientPreferences.php` | interface language from `Accept-Language`, "today" from the app's `X-Timezone` |
| `lang/` | validation and error messages in en / ru / uk / de |

The Lighthouse query cache runs in `opcache` mode: Laravel 13 refuses to
unserialize objects read back from a cache store (`cache.serializable_classes`),
which breaks the default `store` mode on the second identical query.

Deployment: `k8s/README.md`.
