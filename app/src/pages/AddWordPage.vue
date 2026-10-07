<template>
  <q-page class="w-screen add">
    <div class="w-topbar">
      <button type="button" class="w-icon-btn" :aria-label="t('common.close')" @click="close">
        <NIcon name="close" />
      </button>
      <h1 class="w-topbar__title">{{ t('add.title') }}</h1>
    </div>

    <div class="add__body">
      <div role="group" class="w-segmented" :aria-label="t('add.inputMethod')">
        <button type="button" class="w-segmented__item" :aria-pressed="mode === 'voice'" @click="setMode('voice')">
          <NIcon v-if="mode === 'voice'" name="check" :size="18" :stroke-width="2.2" />
          {{ t('add.voice') }}
        </button>
        <button type="button" class="w-segmented__item" :aria-pressed="mode === 'text'" @click="setMode('text')">
          <NIcon v-if="mode === 'text'" name="check" :size="18" :stroke-width="2.2" />
          {{ t('add.text') }}
        </button>
      </div>

      <!-- ===================== Voice ===================== -->
      <template v-if="mode === 'voice'">
        <template v-if="voice === 'permission'">
          <div class="mic-off">
            <span class="mic-off__badge" aria-hidden="true"><NIcon name="micOff" :size="42" /></span>
            <div class="mic-off__text">
              <h2 class="mic-off__title">{{ t('add.micTitle') }}</h2>
              <p class="w-lead">{{ t('add.micText') }}</p>
            </div>
            <ol class="mic-off__steps">
              <li>{{ t('add.micStep1') }}</li>
              <li>{{ t('add.micStep2') }}</li>
              <li>{{ t('add.micStep3') }}</li>
            </ol>
          </div>
          <div class="add__actions add__actions--stack">
            <button v-if="native" type="button" class="w-btn" @click="openAppSettings">{{ t('add.openSettings') }}</button>
            <button type="button" class="w-btn w-btn--outline add__secondary" @click="setMode('text')">
              {{ t('add.typeInstead') }}
            </button>
          </div>
        </template>

        <template v-else>
          <div class="hint-row">
            <NIcon name="globe" :size="18" class="muted" />
            <i18n-t keypath="add.recognizing" tag="span" scope="global" class="hint-row__text">
              <template #language><strong>{{ language(studied).endonym }}</strong></template>
              <template #into>{{ t(`languageInto.${nativeLanguage}`) }}</template>
            </i18n-t>
            <router-link :to="{ name: 'settings' }" class="w-link-btn">{{ t('common.settings') }}</router-link>
          </div>

          <div v-if="voice === 'unavailable'" class="w-banner" role="alert">
            <NIcon name="alert" :size="22" />
            <span>{{ t('add.voiceUnavailable') }}</span>
          </div>

          <div v-else class="recorder" :class="{ 'recorder--quiet': voice === 'notHeard' }">
            <div class="recorder__rings">
              <div class="recorder__ring">
                <button
                  type="button"
                  class="recorder__button"
                  :aria-label="voice === 'listening' ? t('add.stopRecording') : t('add.startRecording')"
                  @click="voice === 'listening' ? stopVoice() : startVoice()"
                >
                  <NIcon name="mic" :size="40" />
                </button>
              </div>
            </div>

            <template v-if="voice === 'listening'">
              <div class="wave" aria-hidden="true">
                <span v-for="(level, i) in levels" :key="i" :style="{ height: `${4 + level * 28}px` }" :class="{ 'wave--soft': i < 2 || i > 14 }" />
              </div>
              <div class="recorder__status">
                <span class="recorder__listening"><span class="recorder__dot" />{{ t('add.listening') }}</span>
                <span v-if="heard" class="recorder__heard">«{{ heard }}»</span>
              </div>
            </template>
            <span v-else-if="voice === 'idle'" class="recorder__prompt">{{ t('add.tapToSpeak') }}</span>
            <span v-else-if="voice === 'notHeard'" class="recorder__prompt">{{ t('add.tapToRetry') }}</span>
            <span v-else-if="heard" class="recorder__heard">«{{ heard }}»</span>
          </div>

          <div v-if="voice === 'notHeard'" class="w-banner" role="alert">
            <NIcon name="alert" :size="22" />
            <div class="w-banner__text">
              <span class="w-banner__title">{{ t('add.notHeardTitle') }}</span>
              <span>{{ t('add.notHeardText') }}</span>
            </div>
          </div>

          <!-- What was heard, looked up -->
          <div v-if="voice === 'done' && lookupState === 'loading'" class="result result--loading">
            <span class="w-spinner" />
            {{ t('add.looking') }}
          </div>

          <section v-else-if="voice === 'done' && result?.found" class="result" :aria-label="t('add.found')">
            <div class="result__top">
              <span class="result__label">{{ t('add.found') }}</span>
              <span v-if="result.part_of_speech" class="result__kind">{{ result.part_of_speech }}</span>
            </div>
            <div class="result__term-row">
              <div class="result__term-text">
                <span class="result__term">{{ result.term }}</span>
                <span v-if="result.transcriptions[0]" class="result__ipa ipa">{{ result.transcriptions[0].ipa }}</span>
              </div>
              <button
                type="button"
                class="result__speak"
                :aria-label="t('add.pronounce')"
                @click="speakWord(result.term!, studied, result.transcriptions[0]?.accent)"
              >
                <NIcon name="speaker" :size="22" />
              </button>
            </div>
            <div class="result__rule" />
            <div class="result__translation-row">
              <span class="result__translation">{{ selected.join(', ') || note }}</span>
              <button type="button" class="w-icon-btn w-icon-btn--sm muted" :aria-label="t('add.editTranslation')" @click="setMode('text')">
                <NIcon name="edit" :size="20" />
              </button>
            </div>
          </section>

          <OfflineBanner v-else-if="voice === 'done' && lookupState === 'offline'" @retry="runLookup" />

          <div v-else-if="voice === 'done' && lookupState === 'notFound'" class="w-banner w-banner--neutral">
            <NIcon name="alert" :size="22" />
            <div class="w-banner__text">
              <span class="w-banner__title">{{ t('add.notFoundTitle') }}</span>
              <span>{{ t('add.notFoundText') }}</span>
            </div>
          </div>

          <div class="add__spacer" />

          <div v-if="voice === 'notHeard'" class="add__actions">
            <button type="button" class="w-btn w-btn--md w-btn--outline add__secondary" @click="setMode('text')">
              <NIcon name="keyboard" :size="20" />
              {{ t('add.text') }}
            </button>
            <button type="button" class="w-btn w-btn--md" @click="startVoice">
              <NIcon name="repeat" :size="20" />
              {{ t('add.again') }}
            </button>
          </div>
          <div v-else-if="voice === 'done'" class="add__actions">
            <button type="button" class="w-btn w-btn--md w-btn--outline add__secondary" @click="startVoice">
              <NIcon name="repeat" :size="20" />
              {{ t('add.again') }}
            </button>
            <router-link
              v-if="result?.existing_word_id"
              :to="{ name: 'word', params: { id: result.existing_word_id } }"
              class="w-btn w-btn--md"
            >
              {{ t('add.openWord') }}
            </router-link>
            <button
              v-else-if="result?.found || lookupState === 'offline'"
              type="button"
              class="w-btn w-btn--md"
              :disabled="saving"
              @click="save"
            >
              <span v-if="saving" class="w-spinner" />
              <template v-else><NIcon name="check" :size="20" :stroke-width="2.2" />{{ t('add.save') }}</template>
            </button>
            <button v-else type="button" class="w-btn w-btn--md" @click="setMode('text')">
              <NIcon name="edit" :size="20" />
              {{ t('add.text') }}
            </button>
          </div>
        </template>
      </template>

      <!-- ===================== Text ===================== -->
      <template v-else>
        <div class="direction">
          <span class="direction__side">
            <LangBadge :code="reverse ? nativeLanguage : studied" small :variant="reverse ? 'neutral' : 'primary'" />
            {{ language(reverse ? nativeLanguage : studied).endonym }}
          </span>
          <button type="button" class="w-icon-btn w-icon-btn--sm muted" :aria-label="t('add.swapDirection')" @click="swap">
            <NIcon name="swap" :size="22" />
          </button>
          <span class="direction__side">
            <LangBadge :code="reverse ? studied : nativeLanguage" small :variant="reverse ? 'primary' : 'neutral'" />
            {{ language(reverse ? studied : nativeLanguage).endonym }}
          </span>
        </div>

        <TextField
          ref="termField"
          v-model="term"
          :label="reverse ? t('add.termIn', { language: t(`languageNames.${nativeLanguage}`) }) : t('add.term')"
          large
          trailing
          enterkeyhint="search"
          :hint="detected"
          @enter="runLookup"
        >
          <template #trailing>
            <button v-if="term" type="button" class="w-icon-btn w-icon-btn--sm muted" :aria-label="t('add.clear')" @click="clearTerm">
              <NIcon name="clear" :size="20" :stroke-width="1.9" />
            </button>
          </template>
        </TextField>

        <OfflineBanner v-if="lookupState === 'offline'" @retry="runLookup" />

        <div v-else-if="lookupState === 'loading'" class="result result--loading">
          <span class="w-spinner" />
          {{ t('add.looking') }}
        </div>

        <section v-else-if="result?.found" class="result" :aria-label="t('add.found')">
          <div class="result__head">
            <span class="result__term result__term--sm">{{ result.term }}</span>
            <div class="result__sounds">
              <button
                v-for="sound in sounds"
                :key="sound.label"
                type="button"
                class="sound"
                :aria-label="t('add.pronounceAccent', { accent: sound.label })"
                @click="speakWord(result.term!, studied, sound.accent)"
              >
                <NIcon name="speakerSoft" :size="20" />
                <span class="sound__accent">{{ sound.label }}</span>
                <span v-if="sound.ipa" class="sound__ipa ipa">{{ sound.ipa }}</span>
              </button>
            </div>
          </div>

          <div v-if="result.existing_word_id" class="existing">
            <span>{{ t('add.alreadyAdded') }}</span>
            <router-link :to="{ name: 'word', params: { id: result.existing_word_id } }" class="w-link-btn">
              {{ t('add.openWord') }}
            </router-link>
          </div>
          <div v-else class="result__choose">
            <span class="result__label">{{ t('add.chooseTranslation') }}</span>
            <div class="w-chip-row">
              <button
                v-for="translation in result.translations"
                :key="translation"
                type="button"
                class="w-chip w-chip--strong"
                :class="{ 'w-chip--selected': selected.includes(translation) }"
                :aria-pressed="selected.includes(translation)"
                @click="toggle(translation)"
              >
                <NIcon v-if="selected.includes(translation)" name="check" :size="16" :stroke-width="2.4" />
                {{ translation }}
              </button>
            </div>
          </div>
        </section>

        <div v-else-if="lookupState === 'notFound'" class="w-banner w-banner--neutral">
          <NIcon name="alert" :size="22" />
          <div class="w-banner__text">
            <span class="w-banner__title">{{ t('add.notFoundTitle') }}</span>
            <span>{{ t('add.notFoundText') }}</span>
          </div>
        </div>

        <TextField
          v-if="lookupState === 'offline'"
          v-model="offlineTranslation"
          :label="t('add.translation')"
          :hint="t('add.translationHint')"
        />
        <TextField
          v-else-if="!result?.existing_word_id"
          v-model="note"
          :label="t('add.note')"
          :placeholder="t('add.optional')"
          :error="noteError"
        />

        <div class="add__spacer" />

        <button
          v-if="!result?.existing_word_id"
          type="button"
          class="w-btn"
          :disabled="!canSave || saving"
          @click="save"
        >
          <span v-if="saving" class="w-spinner" />
          <template v-else>
            <NIcon name="plus" :size="20" :stroke-width="2.1" />
            {{ t('add.addToDictionary') }}
          </template>
        </button>
      </template>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { Capacitor } from '@capacitor/core';
import { AndroidSettings, IOSSettings, NativeSettings } from 'capacitor-native-settings';
import LangBadge from '@/components/LangBadge.vue';
import NIcon from '@/components/NIcon.vue';
import OfflineBanner from '@/components/OfflineBanner.vue';
import TextField from '@/components/TextField.vue';
import { NetworkError } from '@/services/graphql-client';
import { rescheduleReminders } from '@/services/reminders';
import { speakWord } from '@/services/speech';
import type { LookupResult } from '@/services/types';
import { listen, VoiceInputError } from '@/services/voice-input';
import { lookup } from '@/services/words-api';
import { useAuthStore } from '@/stores/auth-store';
import { useWordsStore } from '@/stores/words-store';
import { language, type LanguageCode } from '@/utils/languages';
import { errorText, toast } from '@/utils/notify';

type Mode = 'voice' | 'text';
type VoiceState = 'idle' | 'listening' | 'done' | 'notHeard' | 'permission' | 'unavailable';
type LookupState = 'idle' | 'loading' | 'found' | 'notFound' | 'offline';

/** Translations picked for the user straight away. */
const PRESELECTED = 2;
const BARS = 20;

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const words = useWordsStore();

const native = Capacitor.isNativePlatform();
const studied = computed<LanguageCode>(() => auth.activeLanguage ?? 'en');
const nativeLanguage = computed<LanguageCode>(() => auth.user?.native_language ?? 'en');

const mode = ref<Mode>(route.query.mode === 'text' || typeof route.query.term === 'string' ? 'text' : 'voice');
const reverse = ref(false);
const term = ref(typeof route.query.term === 'string' ? route.query.term : '');
const note = ref('');
const offlineTranslation = ref('');
const noteError = ref<string | null>(null);

const result = ref<LookupResult | null>(null);
const lookupState = ref<LookupState>('idle');
const selected = ref<string[]>([]);
const saving = ref(false);
const termField = ref<InstanceType<typeof TextField> | null>(null);

const voice = ref<VoiceState>('idle');
const heard = ref('');
const levels = ref<number[]>(Array.from({ length: BARS }, () => 0.1));
let stopVoiceSession: (() => void) | null = null;
let fakeLevels: ReturnType<typeof setInterval> | undefined;
/** Whether the word came in by voice, for the "added by voice" line. */
let spoken = false;

const sounds = computed(() => {
  if (!result.value) return [];
  return language(studied.value).accents.map((accent) => ({
    accent: accent.id,
    label: accent.label,
    ipa:
      result.value!.transcriptions.find((transcription) => transcription.accent === accent.id)?.ipa ??
      result.value!.transcriptions[0]?.ipa ??
      null,
  }));
});

const detected = computed(() => {
  if (!result.value?.found) return null;
  const kind = t(result.value.kind === 'PHRASE' ? 'add.kindPhrase' : 'add.kindWord');
  return t('add.detected', { kind: result.value.part_of_speech ? `${kind} · ${result.value.part_of_speech}` : kind });
});

const canSave = computed(() => {
  if (lookupState.value === 'offline') return !reverse.value && term.value.trim() !== '';
  if (lookupState.value === 'loading') return false;
  if (result.value?.found) return selected.value.length > 0 || note.value.trim() !== '';
  return term.value.trim() !== '' && note.value.trim() !== '';
});

/* ---------- Lookup ---------- */

let lookupSeq = 0;
let lookupTimer: ReturnType<typeof setTimeout> | undefined;

async function runLookup() {
  clearTimeout(lookupTimer);
  const text = (mode.value === 'voice' ? heard.value : term.value).trim();
  const seq = ++lookupSeq;

  if (!text) {
    result.value = null;
    lookupState.value = 'idle';
    return;
  }

  lookupState.value = 'loading';
  try {
    const found = await lookup(text, studied.value, mode.value === 'text' && reverse.value);
    if (seq !== lookupSeq) return;

    result.value = found;
    lookupState.value = found.found ? 'found' : 'notFound';
    selected.value = found.translations.slice(0, PRESELECTED);
  } catch (e) {
    if (seq !== lookupSeq) return;

    result.value = null;
    if (e instanceof NetworkError) {
      lookupState.value = 'offline';
    } else {
      lookupState.value = 'idle';
      toast(errorText(e));
    }
  }
}

watch(term, (value, old) => {
  // The voice result handed over to text mode is already looked up.
  if (mode.value !== 'text' || (result.value?.term === value && old === '')) return;
  clearTimeout(lookupTimer);
  if (lookupState.value !== 'offline') lookupState.value = value.trim() ? 'loading' : 'idle';
  lookupTimer = setTimeout(() => void runLookup(), 600);
});

function toggle(translation: string) {
  selected.value = selected.value.includes(translation)
    ? selected.value.filter((candidate) => candidate !== translation)
    : [...selected.value, translation];
}

function swap() {
  reverse.value = !reverse.value;
  void runLookup();
}

function clearTerm() {
  term.value = '';
  result.value = null;
  lookupState.value = 'idle';
  termField.value?.focus();
}

/* ---------- Voice ---------- */

async function startVoice() {
  stopVoice();
  heard.value = '';
  result.value = null;
  lookupState.value = 'idle';
  levels.value = levels.value.map(() => 0.1);
  voice.value = 'listening';

  try {
    const session = await listen({
      locale: language(studied.value).speech,
      onPartial: (text) => (heard.value = text),
      onLevel: (level) => (levels.value = [...levels.value.slice(1), level]),
    });
    stopVoiceSession = session.stop;

    // The browser reports no levels: keep the bars moving while it listens.
    if (!native) {
      fakeLevels = setInterval(() => (levels.value = [...levels.value.slice(1), 0.2 + Math.random() * 0.8]), 120);
    }

    const text = await session.result;
    if (voice.value !== 'listening') return;

    if (text) {
      heard.value = text;
      spoken = true;
      voice.value = 'done';
      await runLookup();
    } else {
      voice.value = 'notHeard';
    }
  } catch (e) {
    voice.value = e instanceof VoiceInputError && e.problem === 'permission' ? 'permission' : 'unavailable';
  } finally {
    clearInterval(fakeLevels);
    stopVoiceSession = null;
  }
}

function stopVoice() {
  stopVoiceSession?.();
}

async function openAppSettings() {
  await NativeSettings.open({
    optionAndroid: AndroidSettings.ApplicationDetails,
    optionIOS: IOSSettings.App,
  }).catch(() => undefined);
}

/* ---------- Mode and saving ---------- */

async function setMode(next: Mode) {
  if (next === mode.value) return;

  if (next === 'text') {
    stopVoice();
    voice.value = 'idle';
    // What was heard carries over, with its lookup.
    if (heard.value) {
      reverse.value = false;
      term.value = result.value?.term ?? heard.value;
    }
    mode.value = 'text';
    await nextTick();
    if (!term.value) termField.value?.focus();
  } else {
    mode.value = 'voice';
    void startVoice();
  }

  void router.replace({ query: { ...route.query, mode: next, term: undefined } });
}

async function save() {
  if (!canSave.value || saving.value) return;

  const offline = lookupState.value === 'offline';
  const typed = (mode.value === 'voice' ? heard.value : term.value).trim();
  const translations = offline
    ? offlineTranslation.value.trim()
      ? [offlineTranslation.value.trim()]
      : []
    : result.value?.found
      ? selected.value
      : [];

  saving.value = true;
  try {
    const outcome = await words.add({
      language: studied.value,
      term: result.value?.found ? result.value.term! : typed,
      translations,
      note: note.value.trim() || null,
      source: spoken ? 'VOICE' : 'TEXT',
      lookup_id: result.value?.found ? result.value.id : null,
    });

    void rescheduleReminders();

    if ('queued' in outcome) {
      toast(t('add.queued'));
      close();
    } else {
      toast(t('add.added', { term: outcome.word.term }));
      await router.replace({ name: 'word', params: { id: outcome.word.id } });
    }
  } catch (e) {
    noteError.value = errorText(e);
    if (mode.value === 'voice') toast(noteError.value);
  } finally {
    saving.value = false;
  }
}

function close() {
  if (window.history.state?.back) router.back();
  else void router.replace({ name: 'home' });
}

onMounted(() => {
  if (mode.value === 'voice') void startVoice();
  else if (term.value) void runLookup();
  else void nextTick(() => termField.value?.focus());
});

onBeforeUnmount(() => {
  stopVoice();
  clearInterval(fakeLevels);
  clearTimeout(lookupTimer);
});
</script>

<style scoped>
.add {
  padding-bottom: calc(24px + var(--w-safe-bottom));
}

.add__body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 0 16px;
}

.add__spacer {
  flex: 1;
}

.add__actions {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.add__actions--stack {
  grid-template-columns: 1fr;
}

.add__secondary {
  color: var(--w-primary);
}

.add__secondary:hover {
  color: var(--w-primary);
}

.hint-row {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
}

.hint-row__text {
  flex: 1;
  font-size: 14px;
  line-height: 20px;
  color: var(--w-muted);
}

.hint-row__text strong {
  font-weight: 600;
  color: var(--w-ink);
}

/* Recorder */

.recorder {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.recorder__rings {
  width: 196px;
  height: 196px;
  border-radius: 98px;
  background: #e3f2ee;
  display: flex;
  align-items: center;
  justify-content: center;
}

.recorder__ring {
  width: 148px;
  height: 148px;
  border-radius: 74px;
  background: #c6e8df;
  display: flex;
  align-items: center;
  justify-content: center;
}

.recorder--quiet .recorder__rings {
  width: 148px;
  height: 148px;
  background: transparent;
}

.recorder--quiet .recorder__ring {
  background: var(--w-container);
}

.recorder__button {
  width: 96px;
  height: 96px;
  border-radius: 48px;
  border: none;
  background: var(--w-primary);
  color: var(--w-on-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(14, 107, 94, 0.28);
}

.wave {
  display: flex;
  align-items: center;
  gap: 3px;
  height: 32px;
}

.wave span {
  width: 3px;
  border-radius: 2px;
  background: var(--w-primary);
  transition: height 0.12s ease;
}

.wave .wave--soft {
  background: #6f9e95;
}

.recorder__status {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.recorder__listening {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 600;
  color: var(--w-primary);
}

.recorder__dot {
  width: 8px;
  height: 8px;
  border-radius: 4px;
  background: var(--w-primary);
  animation: w-skeleton 1s ease-in-out infinite;
}

.recorder__heard {
  font-size: 26px;
  line-height: 34px;
  font-weight: 600;
  text-align: center;
}

.recorder__prompt {
  font-size: 14px;
  color: var(--w-muted);
  text-align: center;
}

/* Microphone refused */

.mic-off {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 20px;
  padding: 0 8px;
}

.mic-off__badge {
  width: 96px;
  height: 96px;
  border-radius: 48px;
  background: var(--w-container-high);
  color: var(--w-muted);
  display: flex;
  align-items: center;
  justify-content: center;
}

.mic-off__text {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  text-align: center;
}

.mic-off__title {
  font-size: 22px;
  line-height: 28px;
  font-weight: 600;
}

.mic-off__steps {
  margin: 0;
  padding: 14px 16px 14px 36px;
  width: 100%;
  box-sizing: border-box;
  border-radius: 16px;
  background: var(--w-container);
  font-size: 14px;
  line-height: 22px;
  color: var(--w-muted);
}

/* Lookup result */

.result {
  background: var(--w-container);
  border-radius: 20px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.result--loading {
  flex-direction: row;
  align-items: center;
  gap: 12px;
  font-size: 14px;
  color: var(--w-muted);
}

.result--loading .w-spinner {
  color: var(--w-primary);
}

.result__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.result__label {
  font-size: 12px;
  font-weight: 600;
  color: var(--w-muted);
  letter-spacing: 0.02em;
}

.result__kind {
  font-size: 12px;
  font-weight: 600;
  color: var(--w-muted);
  border: 1px solid var(--w-outline-soft);
  border-radius: 8px;
  padding: 3px 8px;
}

.result__term-row,
.result__translation-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.result__term-text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.result__term {
  font-size: 22px;
  line-height: 28px;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.result__term--sm {
  font-size: 20px;
  line-height: 26px;
}

.result__ipa {
  font-size: 15px;
  color: var(--w-muted);
}

.result__speak {
  width: 48px;
  height: 48px;
  border-radius: 24px;
  border: none;
  background: var(--w-tonal);
  color: var(--w-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
}

.result__rule {
  height: 1px;
  background: var(--w-outline-soft);
}

.result__translation {
  flex: 1;
  font-size: 16px;
  line-height: 22px;
  font-weight: 500;
}

.result__head {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.result__sounds {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.result__choose {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.sound {
  height: 44px;
  padding: 0 14px 0 10px;
  border-radius: 22px;
  border: none;
  background: var(--w-tonal);
  color: var(--w-on-tonal);
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.sound__accent {
  font-size: 12px;
  font-weight: 700;
}

.sound__ipa {
  font-size: 14px;
}

.existing {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  font-size: 14px;
  color: var(--w-muted);
}

/* Text direction */

.direction {
  display: flex;
  align-items: center;
  gap: 4px;
}

.direction__side {
  flex: 1;
  min-width: 0;
  height: 48px;
  box-sizing: border-box;
  border-radius: 14px;
  background: var(--w-container);
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 12px;
  font-size: 15px;
  font-weight: 500;
}
</style>
