<template>
  <q-dialog v-model="model" position="bottom" @hide="emit('hide')">
    <section class="w-sheet" role="dialog" aria-modal="true" :aria-labelledby="titleId">
      <span class="w-sheet__handle" aria-hidden="true" />
      <h2 :id="titleId" class="w-sheet__title">{{ title }}</h2>
      <slot />
    </section>
  </q-dialog>
</template>

<script setup lang="ts">
import { useId } from 'vue';

defineProps<{ title: string }>();
const emit = defineEmits<{ hide: [] }>();
const model = defineModel<boolean>({ required: true });
const titleId = useId();
</script>
