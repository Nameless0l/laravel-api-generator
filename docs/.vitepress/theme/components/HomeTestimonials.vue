<script setup lang="ts">
interface Testimonial {
    quote: string
    author: string
    handle?: string
    link?: string
}

const props = defineProps<{ title: string; items: Testimonial[] }>()
</script>

<template>
    <section v-if="items.length" class="lag-quotes">
        <h2>{{ title }}</h2>
        <div class="lag-quotes-grid">
            <figure v-for="item in items" :key="item.author" class="lag-quote-card">
                <blockquote>“{{ item.quote }}”</blockquote>
                <figcaption>
                    <strong>{{ item.author }}</strong>
                    <a v-if="item.link && item.handle" :href="item.link" target="_blank" rel="noopener">{{
                        item.handle
                    }}</a>
                    <span v-else-if="item.handle">{{ item.handle }}</span>
                </figcaption>
            </figure>
        </div>
    </section>
</template>

<style scoped>
.lag-quotes {
    margin: 64px auto 0;
    max-width: 1152px;
    padding: 0 24px;
}

.lag-quotes h2 {
    text-align: center;
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 32px;
    border: none;
}

.lag-quotes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 16px;
}

.lag-quote-card {
    margin: 0;
    padding: 20px 24px;
    border-radius: 12px;
    background: var(--vp-c-bg-soft);
    border-left: 3px solid var(--vp-c-brand-1);
}

.lag-quote-card blockquote {
    margin: 0 0 12px;
    padding: 0;
    border: none;
    font-size: 14px;
    line-height: 1.7;
    color: var(--vp-c-text-1);
}

.lag-quote-card figcaption {
    display: flex;
    gap: 8px;
    align-items: baseline;
    font-size: 13px;
}

.lag-quote-card figcaption strong {
    color: var(--vp-c-text-1);
}

.lag-quote-card figcaption a,
.lag-quote-card figcaption span {
    color: var(--vp-c-text-2);
    text-decoration: none;
}

.lag-quote-card figcaption a:hover {
    color: var(--vp-c-brand-1);
}
</style>
