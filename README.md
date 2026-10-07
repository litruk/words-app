# Slovnik / Словник

Words and phrases in every language you learn, in one place: add a word by
voice or by typing, get translations, transcriptions and examples from the
dictionary, and learn it with spaced repetition and reminders.

| Folder | What it is |
| --- | --- |
| `backend/` | Laravel 13 + Lighthouse GraphQL API (Sanctum tokens, Postgres). Dictionary entries come from Gemini. |
| `app/` | Quasar 2 (Vue 3, TypeScript, Pinia) app, shipped to Android with Capacitor. |
| `.github/workflows/` | CI: API images to GHCR and the k3s rollout; the debug APK to Firebase App Distribution. |

The design lives in the claude.ai Design canvas "Словник — приложение для изучения слов".

## Running it locally

```bash
# API on http://localhost:8090/graphql (Postgres and Redis in Sail)
cd backend
cp .env.example .env              # then set GEMINI_API_KEY
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate

# App on http://localhost:9100 (Node 22.12+ / 24)
cd ../app
npm install
npx quasar dev --port 9100
```

Tests: `cd backend && ./vendor/bin/sail artisan test`; types: `cd app && npm run typecheck`.

## How it fits together

- **Languages.** Every user has a native language (translations go into it, and
  the interface speaks it) and one or more languages they learn. The interface
  is translated into English (the base), Russian, Ukrainian and German
  (`app/src/i18n/`); the API answers in the same language (`backend/lang/`).
- **Dictionary.** `lookup` asks Gemini once per text and direction and caches
  the answer for everybody (`backend/app/Services/LookupService.php`).
- **Learning.** An SM-2 scheduler in the Anki style
  (`backend/app/Services/Scheduler.php`): learning steps of 1 and 10 minutes,
  then intervals that grow by the card's ease; ten new cards a day per language.
- **Reminders** are local notifications the app plans a week ahead from the
  server's forecast of what will be due (`app/src/services/reminders.ts`).
- **Voice and pronunciation** use the phone's own speech recognition and
  text-to-speech; nothing is recorded or sent anywhere but the recognised text.
- **Offline.** A word added without a connection waits on the phone and is
  sent later; the server then fills in its translation.

Deployment: `backend/k8s/README.md`. Android builds: `app/README.md`.
