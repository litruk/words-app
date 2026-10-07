<template>
  <BottomSheet v-model="model" :title="t('languageSheet.title')">
    <div role="radiogroup" class="sheet-options">
      <label
        v-for="code in auth.studiedLanguages"
        :key="code"
        class="w-option"
        :class="{ 'w-option--selected': code === auth.activeLanguage }"
      >
        <LangBadge :code="code" :variant="code === auth.activeLanguage ? 'primary' : 'neutral'" />
        <span class="sheet-options__text">
          <span class="w-option__name">{{ language(code).endonym }}</span>
          <span v-if="words.statsFor(code)" class="w-option__hint">
            {{
              t('languageSheet.stats', {
                entries: t('plural.entries', { n: words.statsFor(code)!.total }, words.statsFor(code)!.total),
                due: words.statsFor(code)!.due,
              })
            }}
          </span>
        </span>
        <input type="radio" name="active-language" :checked="code === auth.activeLanguage" @change="pick(code)" />
      </label>
    </div>

    <router-link :to="{ name: 'settings' }" class="sheet-link" @click="model = false">
      <NIcon name="sliders" :size="22" />
      <span>{{ t('languageSheet.editList') }}</span>
      <NIcon name="chevronRight" :size="20" class="sheet-link__chevron" />
    </router-link>
  </BottomSheet>
</template>

<script setup lang="ts">
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BottomSheet from '@/components/BottomSheet.vue';
import LangBadge from '@/components/LangBadge.vue';
import NIcon from '@/components/NIcon.vue';
import { useAuthStore } from '@/stores/auth-store';
import { useWordsStore } from '@/stores/words-store';
import { language, type LanguageCode } from '@/utils/languages';

const model = defineModel<boolean>({ required: true });

const { t } = useI18n();
const auth = useAuthStore();
const words = useWordsStore();

watch(model, (open) => {
  if (open) void words.loadStats().catch(() => undefined);
});

function pick(code: LanguageCode) {
  auth.setActiveLanguage(code);
  model.value = false;
}
</script>

<style scoped>
.sheet-options {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.sheet-options .w-option {
  min-height: 72px;
  gap: 14px;
  border-radius: 16px;
}

.sheet-options .w-lang {
  width: 40px;
  height: 40px;
  border-radius: 20px;
}

.sheet-options__text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.sheet-link {
  display: flex;
  align-items: center;
  gap: 14px;
  min-height: 56px;
  margin-top: 12px;
  padding: 0 20px;
  border-radius: 16px;
  box-shadow: 0 -1px 0 var(--w-divider);
  color: var(--w-ink);
  font-size: 15px;
  font-weight: 500;
  text-decoration: none;
}

.sheet-link:hover {
  color: var(--w-ink);
}

.sheet-link > span {
  flex: 1;
}

.sheet-link__chevron {
  color: var(--w-muted);
}
</style>
