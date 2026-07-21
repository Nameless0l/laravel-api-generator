<script setup lang="ts">
import { computed } from 'vue'
import { useData, withBase } from 'vitepress'

const { page } = useData()

const isFr = computed(() => page.value.relativePath.startsWith('fr/'))
const isExtension = computed(() => page.value.relativePath.includes('guide/extension/'))

const extensionLink = computed(() =>
  withBase(isFr.value ? '/fr/guide/extension/' : '/guide/extension/')
)
const cliLink = computed(() =>
  withBase(isFr.value ? '/fr/guide/getting-started' : '/guide/getting-started')
)
const extensionLabel = computed(() => (isFr.value ? 'Extension IDE' : 'IDE Extension'))
</script>

<template>
  <nav class="sidebar-tabs">
    <a :href="extensionLink" :class="{ active: isExtension }">{{ extensionLabel }}</a>
    <a :href="cliLink" :class="{ active: !isExtension }">CLI</a>
  </nav>
</template>

<style scoped>
.sidebar-tabs {
  display: flex;
  gap: 22px;
  margin: 2px 0 14px;
  border-bottom: 1px solid var(--vp-c-divider);
}
.sidebar-tabs a {
  position: relative;
  padding: 4px 1px 9px;
  font-size: 14px;
  font-weight: 600;
  color: var(--vp-c-text-2);
  transition: color 0.2s;
}
.sidebar-tabs a:hover,
.sidebar-tabs a.active {
  color: var(--vp-c-text-1);
}
.sidebar-tabs a.active::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  bottom: -1px;
  height: 2px;
  border-radius: 1px;
  background: var(--vp-c-text-1);
}
</style>
