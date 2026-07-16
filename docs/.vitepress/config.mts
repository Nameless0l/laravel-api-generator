import { defineConfig } from 'vitepress'

export default defineConfig({
    title: 'Laravel API Generator',
    description:
        'One command. Your whole Laravel API — models, services, DTOs, policies, written tests and docs, with zero lock-in.',
    base: '/laravel-api-generator/',
    lang: 'en-US',
    lastUpdated: true,
    sitemap: {
        hostname: 'https://nameless0l.github.io/laravel-api-generator/',
    },
    head: [
        ['link', { rel: 'icon', type: 'image/png', href: '/laravel-api-generator/logo.png' }],
        ['meta', { property: 'og:title', content: 'Laravel API Generator' }],
        [
            'meta',
            {
                property: 'og:description',
                content: 'One command. Your whole Laravel API — tests written, docs included, zero lock-in.',
            },
        ],
    ],
    themeConfig: {
        logo: '/logo.png',
        nav: [
            { text: 'Guide', link: '/guide/getting-started' },
            { text: 'CLI Reference', link: '/reference/cli' },
            {
                text: 'VS Code Extension',
                link: 'https://marketplace.visualstudio.com/items?itemName=Nameless0l.laravel-api-generator',
            },
        ],
        sidebar: [
            {
                text: 'Introduction',
                items: [{ text: 'Getting Started', link: '/guide/getting-started' }],
            },
            {
                text: 'Generate',
                items: [
                    { text: 'The make:fullapi Command', link: '/guide/generating' },
                    { text: 'Field Types & Primary Keys', link: '/guide/field-types' },
                    { text: 'Relationships', link: '/guide/relationships' },
                ],
            },
            {
                text: 'Sources',
                items: [
                    { text: 'YAML & JSON Schemas', link: '/guide/schema-files' },
                    { text: 'Mermaid Diagrams', link: '/guide/mermaid' },
                    { text: 'From an Existing Database', link: '/guide/from-database' },
                ],
            },
            {
                text: 'Day 30',
                items: [
                    { text: 'Evolving Entities', link: '/guide/evolving' },
                    { text: 'Generated Tests', link: '/guide/testing' },
                    { text: 'API Docs & Postman', link: '/guide/docs-and-postman' },
                ],
            },
            {
                text: 'Advanced',
                items: [
                    { text: 'Customizing Stubs', link: '/guide/customizing-stubs' },
                    { text: 'VS Code Extension', link: '/guide/vscode-extension' },
                ],
            },
            {
                text: 'Reference',
                items: [{ text: 'CLI Reference', link: '/reference/cli' }],
            },
        ],
        socialLinks: [{ icon: 'github', link: 'https://github.com/Nameless0l/laravel-api-generator' }],
        search: { provider: 'local' },
        editLink: {
            pattern: 'https://github.com/Nameless0l/laravel-api-generator/edit/main/docs/:path',
            text: 'Edit this page on GitHub',
        },
        footer: {
            message: 'Released under the MIT License.',
            copyright: '© Mbassi Loïc Aron (Nameless0l)',
        },
    },
})
