# Slovnik app

Quasar 2 (Vue 3, TypeScript, Pinia, vue-i18n) app, packaged for Android with
Capacitor.

## Develop

```bash
npm install
npx quasar dev --port 9100     # http://localhost:9100, API from QCLI_API_URL in .env
npm run typecheck              # also catches a missing translation key
```

`.env` points `QCLI_API_URL` at the local Sail API (`http://localhost:8090/graphql`).
Voice input in the browser uses Chrome's Web Speech API; pronunciation uses
`speechSynthesis`. On the phone both are the device's own services.

## Interface languages

Strings live in `src/i18n/`: `en.ts` is the base and the fallback, and
`ru.ts`, `uk.ts`, `de.ts` are typed against it, so a key that is missing or
extra fails `npm run typecheck`. Russian and Ukrainian plurals take three forms
(`1 карточка | 2 карточки | 5 карточек`). The interface follows the user's
native language; before sign-in it follows the device.

To add an interface language: a new file in `src/i18n/`, an entry in
`src/utils/languages.ts`, and the same code in `backend/config/languages.php`
and `backend/lang/`.

## Android builds

### Locally, over USB

```bash
quasar build -m capacitor -T android --debug
adb install -r src-capacitor/android/app/build/outputs/apk/debug/app-debug.apk
```

`--debug` runs `assembleDebug`, which signs the APK with the standard Android
debug keystore, so it installs without any release signing setup.

Builds are signed with `src-capacitor/android/debug.keystore`, which is checked
in on purpose. Without it Gradle falls back to the build machine's own
`~/.android/debug.keystore`, and CI has none - so every CI build carried a fresh
certificate, Android refused to update over the previous install, and the
reinstall wiped the session (that is why the app kept asking to log in again).
A debug keystore is not a secret and Play refuses releases signed with one.

Two things follow from it:

- Moving from a build made before this change needs one uninstall
  (`adb uninstall com.slovnik.app`); updates after that keep their data.
- CI sets `versionCode` from the run number while local builds stay at 1, so
  installing a local build over a CI one is a downgrade: use `adb install -r -d`.

The Capacitor plugins need a **Java 21** toolchain and Node >= 22.22. With
Homebrew's keg-only JDK that means exporting it for the build:

```bash
export JAVA_HOME=/opt/homebrew/opt/openjdk@21/libexec/openjdk.jdk/Contents/Home
``` The API
endpoint comes from `QCLI_API_URL` in `.env` (gitignored; see the commented
line there for pointing the app at a local Sail backend).

### In CI, distributed to testers

`.github/workflows/android-debug.yml` builds the same debug APK on every push
to `main` that touches `app/**`, and can also be started by hand
("Run workflow") with release notes. The APK is uploaded as a workflow
artifact, and - once the settings below exist - pushed to Firebase App
Distribution, where testers install it through the Firebase App Tester app.

One-time setup:

1. Create a Firebase project (the free Spark plan is enough) and add an
   **Android app** with package name `com.slovnik.app`. Note its App ID
   (`1:<number>:android:<hash>`). `google-services.json` is not needed - App
   Distribution does not require the Firebase SDK in the app.
2. In the Firebase console open **App Distribution** and start it for that app.
   To have builds shared automatically, either create a tester group and note
   its **alias** (not the display name - the CLI matches on the alias), or just
   collect the tester emails.
3. In Google Cloud IAM create a service account with the role **Firebase App
   Distribution Admin** and download a JSON key for it.
4. In GitHub under *Settings → Secrets and variables → Actions* add:
   - secret `FIREBASE_SERVICE_ACCOUNT` - the whole JSON key file,
   - secret `FIREBASE_ANDROID_APP_ID` - the App ID from step 1,
   - variable `FIREBASE_TESTER_GROUPS` (optional) - tester group alias,
   - variable `FIREBASE_TESTERS` (optional) - comma-separated tester emails,
   - variable `QCLI_API_URL` (optional) - to build against another backend.

   Secrets may live in an environment instead of the repository, as long as the
   workflow's job declares that environment (it declares `production`).

   With neither tester variable set, the release is still uploaded to App
   Distribution - it is simply not shared with anyone yet.

Without the two secrets the workflow still runs and leaves the APK as an
artifact; it only skips the distribution step.

Each CI build gets `versionCode` = the GitHub run number (passed in as the
`appVersionCode` gradle property), so releases are told apart in App
Distribution as `1.0 (<run>)`. Local builds keep `versionCode 1`.
