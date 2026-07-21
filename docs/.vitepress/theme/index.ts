import { h } from 'vue'
import DefaultTheme from 'vitepress/theme'
import type { Theme } from 'vitepress'
import HomeFeatureTabs from './components/HomeFeatureTabs.vue'
import HomeTestimonials from './components/HomeTestimonials.vue'
import SidebarTabs from './components/SidebarTabs.vue'
import './custom.css'

export default {
    extends: DefaultTheme,
    Layout() {
        return h(DefaultTheme.Layout, null, {
            'sidebar-nav-before': () => h(SidebarTabs),
        })
    },
    enhanceApp({ app }) {
        app.component('HomeFeatureTabs', HomeFeatureTabs)
        app.component('HomeTestimonials', HomeTestimonials)
    },
} satisfies Theme
