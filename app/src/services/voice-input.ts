import { Capacitor, type PluginListenerHandle } from '@capacitor/core';
import { SpeechRecognition } from '@capgo/capacitor-speech-recognition';

/**
 * Voice input: the device's own speech recognition, with what it hears so far
 * and how loud it is streamed while the user speaks.
 */

export type VoiceInputProblem = 'permission' | 'unavailable';

export class VoiceInputError extends Error {
  constructor(public readonly problem: VoiceInputProblem) {
    super(problem);
  }
}

export interface ListenOptions {
  /** Recognition locale, e.g. `en-US`. */
  locale: string;
  onPartial?: (text: string) => void;
  /** 0..1 */
  onLevel?: (level: number) => void;
}

export interface Listening {
  /** What was said, or null when nothing was recognised. */
  result: Promise<string | null>;
  stop: () => void;
}

interface WebRecognition {
  lang: string;
  interimResults: boolean;
  maxAlternatives: number;
  onresult: ((event: { results: ArrayLike<ArrayLike<{ transcript: string }>> }) => void) | null;
  onerror: ((event: { error: string }) => void) | null;
  onend: (() => void) | null;
  start: () => void;
  stop: () => void;
}

function webRecognition(): (new () => WebRecognition) | null {
  const scope = window as unknown as Record<string, unknown>;
  return (scope.SpeechRecognition ?? scope.webkitSpeechRecognition ?? null) as (new () => WebRecognition) | null;
}

export async function voiceInputAvailable(): Promise<boolean> {
  if (!Capacitor.isNativePlatform()) return webRecognition() !== null;

  try {
    return (await SpeechRecognition.available()).available;
  } catch {
    return false;
  }
}

export async function listen(options: ListenOptions): Promise<Listening> {
  return Capacitor.isNativePlatform() ? listenNative(options) : listenWeb(options);
}

async function listenNative({ locale, onPartial, onLevel }: ListenOptions): Promise<Listening> {
  if (!(await voiceInputAvailable())) throw new VoiceInputError('unavailable');

  let permission = (await SpeechRecognition.checkPermissions()).speechRecognition;
  if (permission !== 'granted') {
    permission = (await SpeechRecognition.requestPermissions()).speechRecognition;
  }
  if (permission !== 'granted') throw new VoiceInputError('permission');

  let heard = '';
  const handles: PluginListenerHandle[] = [];
  const cleanup = () => handles.splice(0).forEach((handle) => void handle.remove());

  const result = new Promise<string | null>((resolve) => {
    void (async () => {
      handles.push(
        await SpeechRecognition.addListener('partialResults', (event) => {
          const text = event.matches?.[0] ?? event.accumulatedText ?? '';
          if (text) {
            heard = text;
            onPartial?.(text);
          }
        }),
        await SpeechRecognition.addListener('audioLevel', (event) => onLevel?.(event.level)),
        await SpeechRecognition.addListener('listeningState', (event) => {
          if (event.state === 'stopped' || event.status === 'stopped') {
            cleanup();
            resolve(heard.trim() || null);
          }
        }),
      );

      try {
        await SpeechRecognition.start({ language: locale, partialResults: true, popup: false, maxResults: 3 });
      } catch {
        cleanup();
        resolve(null);
      }
    })();
  });

  return {
    result,
    stop: () => void SpeechRecognition.stop().catch(() => undefined),
  };
}

function listenWeb({ locale, onPartial }: ListenOptions): Promise<Listening> {
  const Recognition = webRecognition();
  if (!Recognition) return Promise.reject(new VoiceInputError('unavailable'));

  const recognition = new Recognition();
  recognition.lang = locale;
  recognition.interimResults = true;
  recognition.maxAlternatives = 1;

  let heard = '';
  let denied = false;

  const result = new Promise<string | null>((resolve, reject) => {
    recognition.onresult = (event) => {
      heard = Array.from(event.results)
        .map((alternatives) => alternatives[0]?.transcript ?? '')
        .join(' ');
      onPartial?.(heard);
    };
    recognition.onerror = (event) => {
      denied = event.error === 'not-allowed' || event.error === 'service-not-allowed';
    };
    recognition.onend = () => (denied ? reject(new VoiceInputError('permission')) : resolve(heard.trim() || null));
  });

  recognition.start();

  return Promise.resolve({ result, stop: () => recognition.stop() });
}
