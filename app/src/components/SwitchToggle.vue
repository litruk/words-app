<template>
  <button type="button" role="switch" class="w-switch" :aria-checked="model" :aria-label="label" @click="model = !model">
    <span class="w-switch__track">
      <span class="w-switch__thumb">
        <NIcon v-if="model" name="check" :size="14" :stroke-width="3" />
      </span>
    </span>
  </button>
</template>

<script setup lang="ts">
import NIcon from '@/components/NIcon.vue';

defineProps<{ label: string }>();
const model = defineModel<boolean>({ required: true });
</script>
