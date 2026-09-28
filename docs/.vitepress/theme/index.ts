import { h, nextTick, onMounted, watch } from 'vue'
import DefaultTheme from 'vitepress/theme'
import { useRoute, type Theme } from 'vitepress'
import mediumZoom from 'medium-zoom'
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
    setup() {
        const route = useRoute()
        let zoom: ReturnType<typeof mediumZoom> | undefined
        const attach = () => {
            zoom?.detach()
            zoom = mediumZoom('.vp-doc img', { background: 'var(--vp-c-bg)' })
        }
        onMounted(attach)
        watch(
            () => route.path,
            () => nextTick(attach)
        )
    },
} satisfies Theme
