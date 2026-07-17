import DefaultTheme from 'vitepress/theme'
import type { Theme } from 'vitepress'
import HomeFeatureTabs from './components/HomeFeatureTabs.vue'
import HomeTestimonials from './components/HomeTestimonials.vue'
import './custom.css'

export default {
    extends: DefaultTheme,
    enhanceApp({ app }) {
        app.component('HomeFeatureTabs', HomeFeatureTabs)
        app.component('HomeTestimonials', HomeTestimonials)
    },
} satisfies Theme
