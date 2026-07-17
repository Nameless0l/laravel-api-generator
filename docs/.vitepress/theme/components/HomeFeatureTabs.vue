<script setup lang="ts">
import { ref } from 'vue'
import { withBase } from 'vitepress'

interface TabItem {
    title: string
    text: string
    link: string
    linkText: string
    img?: string
    imgAlt?: string
    code?: string
    bullets?: string[]
}

const props = defineProps<{ items: TabItem[] }>()
const active = ref(0)
</script>

<template>
    <section class="lag-tabs">
        <div class="lag-tabs-nav" role="tablist">
            <button
                v-for="(item, i) in items"
                :key="item.title"
                role="tab"
                :aria-selected="i === active"
                :class="{ active: i === active }"
                @click="active = i"
            >
                {{ item.title }}
            </button>
        </div>
        <div class="lag-tabs-panel" role="tabpanel">
            <div class="lag-tabs-media">
                <img
                    v-if="items[active].img"
                    :src="items[active].img"
                    :alt="items[active].imgAlt || items[active].title"
                    loading="lazy"
                />
                <div v-else-if="items[active].code" class="lag-terminal">
                    <div class="lag-terminal-bar"><i></i><i></i><i></i></div>
                    <pre><code>{{ items[active].code }}</code></pre>
                </div>
                <ul v-else-if="items[active].bullets" class="lag-bullets">
                    <li v-for="b in items[active].bullets" :key="b">{{ b }}</li>
                </ul>
            </div>
            <div class="lag-tabs-text">
                <p>{{ items[active].text }}</p>
                <a class="lag-readmore" :href="withBase(items[active].link)">{{ items[active].linkText }} →</a>
            </div>
        </div>
    </section>
</template>

<style scoped>
.lag-tabs {
    margin: 32px 0 0;
}

.lag-tabs-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 16px;
}

.lag-tabs-nav button {
    padding: 8px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    color: var(--vp-c-text-2);
    background: var(--vp-c-bg-soft);
    transition:
        color 0.2s,
        background 0.2s;
}

.lag-tabs-nav button:hover {
    color: var(--vp-c-text-1);
}

.lag-tabs-nav button.active {
    color: #fff;
    background: var(--vp-c-brand-1);
}

.lag-tabs-panel {
    display: grid;
    grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);
    gap: 24px;
    align-items: center;
    padding: 24px;
    border-radius: 12px;
    background: var(--vp-c-bg-soft);
    min-height: 260px;
}

.lag-tabs-media img {
    display: block;
    width: 100%;
    border-radius: 8px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.25);
}

.lag-terminal {
    border-radius: 8px;
    overflow: hidden;
    background: #0f172a;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.25);
}

.lag-terminal-bar {
    display: flex;
    gap: 6px;
    padding: 10px 12px;
    background: #1e293b;
}

.lag-terminal-bar i {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #ef4444;
}

.lag-terminal-bar i:nth-child(2) {
    background: #eab308;
}

.lag-terminal-bar i:nth-child(3) {
    background: #22c55e;
}

.lag-terminal pre {
    margin: 0;
    padding: 16px;
    overflow-x: auto;
}

.lag-terminal code {
    font-family: var(--vp-font-family-mono);
    font-size: 12.5px;
    line-height: 1.7;
    color: #e2e8f0;
    white-space: pre;
}

.lag-bullets {
    margin: 0;
    padding: 8px 0 8px 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.lag-bullets li {
    font-size: 14px;
    line-height: 1.5;
    color: var(--vp-c-text-1);
}

.lag-bullets li::marker {
    color: var(--vp-c-brand-1);
}

.lag-tabs-text p {
    font-size: 14.5px;
    line-height: 1.7;
    color: var(--vp-c-text-2);
    margin: 0 0 14px;
}

.lag-readmore {
    font-size: 14px;
    font-weight: 600;
    color: var(--vp-c-brand-1);
    text-decoration: none;
}

.lag-readmore:hover {
    text-decoration: underline;
}

@media (max-width: 640px) {
    .lag-tabs-panel {
        grid-template-columns: 1fr;
        align-items: start;
    }
}
</style>
