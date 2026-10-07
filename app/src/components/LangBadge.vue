<template>
  <span
    class="w-lang"
    :class="{ 'w-lang--sm': small, 'w-lang--neutral': variant === 'neutral', 'w-lang--disabled': variant === 'disabled' }"
    aria-hidden="true"
  >
    {{ language(code).badge }}
  </span>
</template>

<script setup lang="ts">
import { language } from '@/utils/languages';

withDefaults(defineProps<{ code: string; small?: boolean; variant?: 'primary' | 'neutral' | 'disabled' }>(), {
  small: false,
  variant: 'primary',
});
</script>
