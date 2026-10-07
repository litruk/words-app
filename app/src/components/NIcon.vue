<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    :fill="icon.filled ? 'currentColor' : 'none'"
    :stroke="icon.filled ? 'none' : 'currentColor'"
    :stroke-width="strokeWidth ?? icon.width ?? 1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
  >
    <template v-for="(shape, i) in icon.shapes" :key="i">
      <circle v-if="shape.circle" :cx="shape.circle[0]" :cy="shape.circle[1]" :r="shape.circle[2]" />
      <rect
        v-else-if="shape.rect"
        :x="shape.rect[0]"
        :y="shape.rect[1]"
        :width="shape.rect[2]"
        :height="shape.rect[3]"
        :rx="shape.rect[4]"
      />
      <path v-else :d="shape.path" />
    </template>
  </svg>
</template>

<script setup lang="ts">
import { computed } from 'vue';

/**
 * The line icons from the design boards, drawn on a 24px grid.
 */
type Shape = {
  path?: string;
  circle?: [number, number, number];
  rect?: [number, number, number, number, number];
};

const ICONS = {
  close: { width: 1.9, shapes: [{ path: 'M6 6l12 12' }, { path: 'M18 6L6 18' }] },
  back: { width: 1.9, shapes: [{ path: 'M19 12H5' }, { path: 'M11 18l-6-6 6-6' }] },
  arrowRight: { width: 2, shapes: [{ path: 'M5 12h14' }, { path: 'M13 6l6 6-6 6' }] },
  chevronDown: { width: 2, shapes: [{ path: 'M6 9l6 6 6-6' }] },
  chevronRight: { width: 2, shapes: [{ path: 'M9 6l6 6-6 6' }] },
  check: { width: 2.2, shapes: [{ path: 'M5 12.5l4.5 4.5L19 7.5' }] },
  plus: { width: 2.1, shapes: [{ path: 'M12 5v14' }, { path: 'M5 12h14' }] },
  swap: {
    shapes: [{ path: 'M7 4L3 8l4 4' }, { path: 'M3 8h14' }, { path: 'M17 12l4 4-4 4' }, { path: 'M21 16H7' }],
  },
  clear: {
    width: 1.9,
    shapes: [{ circle: [12, 12, 9] }, { path: 'M9 9l6 6' }, { path: 'M15 9l-6 6' }],
  },
  speaker: {
    shapes: [{ path: 'M4 9.5v5h3.5l4.5 4v-13l-4.5 4z' }, { path: 'M15.5 9a4 4 0 0 1 0 6' }, { path: 'M18 6.5a7.5 7.5 0 0 1 0 11' }],
  },
  speakerSoft: {
    shapes: [{ path: 'M4 9.5v5h3.5l4.5 4v-13l-4.5 4z' }, { path: 'M15.5 9a4 4 0 0 1 0 6' }],
  },
  mic: {
    shapes: [{ rect: [9, 3, 6, 11, 3] }, { path: 'M5 11a7 7 0 0 0 14 0' }, { path: 'M12 18v3' }],
  },
  keyboard: {
    shapes: [
      { rect: [2.5, 6, 19, 12, 2] },
      { path: 'M6 10h.01' },
      { path: 'M10 10h.01' },
      { path: 'M14 10h.01' },
      { path: 'M18 10h.01' },
      { path: 'M7 14h10' },
    ],
  },
  globe: {
    shapes: [
      { circle: [12, 12, 9] },
      { path: 'M3 12h18' },
      { path: 'M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3z' },
    ],
  },
  edit: { shapes: [{ path: 'M4 20h4L19 9l-4-4L4 16z' }, { path: 'M13.5 6.5l4 4' }] },
  repeat: { width: 1.9, shapes: [{ path: 'M4 12a8 8 0 1 0 2.5-5.8' }, { path: 'M4 4v4h4' }] },
  sort: { width: 1.9, shapes: [{ path: 'M4 7h16' }, { path: 'M7 12h10' }, { path: 'M10 17h4' }] },
  search: { width: 1.9, shapes: [{ circle: [11, 11, 7] }, { path: 'M20 20l-3.5-3.5' }] },
  home: { shapes: [{ path: 'M4 10.5L12 4l8 6.5V20h-5v-6H9v6H4z' }] },
  book: {
    shapes: [
      { path: 'M5 19.5v-15A1.5 1.5 0 0 1 6.5 3H19v15H6.5A1.5 1.5 0 0 0 5 19.5z' },
      { path: 'M5 19.5A1.5 1.5 0 0 0 6.5 21H19v-3' },
    ],
  },
  cards: {
    shapes: [{ rect: [3, 7, 13, 14, 2] }, { path: 'M8 7V5a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3' }],
  },
  sliders: {
    shapes: [
      { path: 'M4 7h9' },
      { path: 'M17 7h3' },
      { circle: [15, 7, 2] },
      { path: 'M4 17h3' },
      { path: 'M11 17h9' },
      { circle: [9, 17, 2] },
    ],
  },
  phone: { width: 1.9, shapes: [{ rect: [7, 2.5, 10, 19, 2] }, { path: 'M11 18h2' }] },
  eyeOff: {
    shapes: [
      { path: 'M3 3l18 18' },
      { path: 'M10.6 5.1A9.7 9.7 0 0 1 12 5c5 0 9 5 9 7a9.7 9.7 0 0 1-2.4 3.4' },
      { path: 'M6.6 6.6C4.4 8 3 10.4 3 12c0 2 4 7 9 7a9.3 9.3 0 0 0 4.4-1.1' },
      { path: 'M9.9 9.9a3 3 0 0 0 4.2 4.2' },
    ],
  },
  eye: {
    shapes: [{ path: 'M3 12c0-2 4-7 9-7s9 5 9 7-4 7-9 7-9-5-9-7z' }, { circle: [12, 12, 3] }],
  },
  bell: { shapes: [{ path: 'M6 16v-5a6 6 0 0 1 12 0v5l1.5 2h-15z' }, { path: 'M10 20.5a2 2 0 0 0 4 0' }] },
  user: { shapes: [{ circle: [12, 8, 4] }, { path: 'M4 21a8 8 0 0 1 16 0' }] },
  clock: { width: 2, shapes: [{ circle: [12, 12, 9] }, { path: 'M12 7v5l3 2' }] },
  logout: {
    width: 1.9,
    shapes: [{ path: 'M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3' }, { path: 'M10 16l-4-4 4-4' }, { path: 'M6 12h10' }],
  },
  more: {
    filled: true,
    shapes: [{ circle: [12, 5.5, 1.7] }, { circle: [12, 12, 1.7] }, { circle: [12, 18.5, 1.7] }],
  },
  trash: {
    shapes: [{ path: 'M4 7h16' }, { path: 'M9 7V4h6v3' }, { path: 'M6 7l1 13h10l1-13' }, { path: 'M10 11v6M14 11v6' }],
  },
  stop: { filled: true, shapes: [{ rect: [7, 7, 10, 10, 2] }] },
  wifiOff: {
    shapes: [
      { path: 'M3 3l18 18' },
      { path: 'M8.5 16.5a5 5 0 0 1 7 0' },
      { path: 'M5 12.5a10 10 0 0 1 5.2-2.8' },
      { path: 'M19 12.5a10 10 0 0 0-2.5-1.8' },
      { path: 'M2 8.8a15 15 0 0 1 4.2-2.6' },
      { path: 'M22 8.8A15 15 0 0 0 11 5.1' },
      { path: 'M12 20h.01' },
    ],
  },
  micOff: {
    width: 1.7,
    shapes: [
      { path: 'M3 3l18 18' },
      { path: 'M9 9v2a3 3 0 0 0 5.1 2.1' },
      { path: 'M15 9.3V6a3 3 0 0 0-5.7-1.3' },
      { path: 'M5 11a7 7 0 0 0 11.5 5.4' },
      { path: 'M19 11a7 7 0 0 1-.6 2.8' },
      { path: 'M12 18v3' },
    ],
  },
  alert: { width: 1.9, shapes: [{ circle: [12, 12, 9] }, { path: 'M12 7.5v5.5' }, { path: 'M12 16.5h.01' }] },
  mail: { shapes: [{ rect: [3, 5, 18, 14, 2] }, { path: 'M3.5 6.5L12 13l8.5-6.5' }] },
  lock: { shapes: [{ rect: [5, 10, 14, 11, 2] }, { path: 'M8 10V7a4 4 0 0 1 8 0v3' }] },
} satisfies Record<string, { width?: number; filled?: boolean; shapes: Shape[] }>;

export type IconName = keyof typeof ICONS;

const props = withDefaults(defineProps<{ name: IconName; size?: number; strokeWidth?: number }>(), { size: 24 });

const icon = computed<{ width?: number; filled?: boolean; shapes: Shape[] }>(() => ICONS[props.name]);
</script>
