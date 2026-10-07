import { Capacitor } from '@capacitor/core';
import { QueueStrategy, TextToSpeech } from '@capacitor-community/text-to-speech';
import { language } from '@/utils/languages';

/**
 * Pronunciation through the device's own text-to-speech: free, offline, and
 * British or American English by picking the voice's locale.
 */

/** The voice for a language and, for English, an accent (`uk` / `us`). */
export function voiceFor(languageCode: string, accent?: string | null): string {
  const accents = language(languageCode).accents;

  return (accents.find((candidate) => candidate.id === accent) ?? accents[0]!).voice;
}

export async function speak(text: string, voice: string, rate = 1): Promise<void> {
  if (!text.trim()) return;

  if (Capacitor.isNativePlatform()) {
    try {
      await TextToSpeech.speak({ text, lang: voice, rate, pitch: 1, volume: 1, queueStrategy: QueueStrategy.Flush });
    } catch {
      // A device without that regional voice still has the language.
      await TextToSpeech.speak({ text, lang: voice.split('-')[0]!, rate, queueStrategy: QueueStrategy.Flush }).catch(
        () => undefined,
      );
    }
    return;
  }

  if (!('speechSynthesis' in window)) return;

  window.speechSynthesis.cancel();
  const utterance = new SpeechSynthesisUtterance(text);
  utterance.lang = voice;
  utterance.rate = rate;
  const exact = window.speechSynthesis.getVoices().find((candidate) => candidate.lang === voice);
  if (exact) utterance.voice = exact;
  window.speechSynthesis.speak(utterance);
}

export function speakWord(text: string, languageCode: string, accent?: string | null, rate = 1): Promise<void> {
  return speak(text, voiceFor(languageCode, accent), rate);
}
