import { defineConfig } from 'vitepress'

const enSidebar = [
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
        items: [{ text: 'Customizing Stubs', link: '/guide/customizing-stubs' }],
    },
    {
        text: 'VS Code Extension',
        items: [
            { text: 'Overview & Install', link: '/guide/extension/' },
            { text: 'Entity Builder', link: '/guide/extension/builder' },
            { text: 'Imports: DB, JSON, OpenAPI', link: '/guide/extension/imports' },
            { text: 'Diagram & Sidebar', link: '/guide/extension/diagram-and-sidebar' },
            { text: 'Quick Actions & Guardrails', link: '/guide/extension/quick-actions' },
            { text: 'Commands & Settings', link: '/guide/extension/reference' },
        ],
    },
    {
        text: 'Reference',
        items: [
            { text: 'CLI Reference', link: '/reference/cli' },
            { text: 'Changelog', link: '/changelog' },
        ],
    },
]

const frSidebar = [
    {
        text: 'Introduction',
        items: [{ text: 'Démarrage rapide', link: '/fr/guide/getting-started' }],
    },
    {
        text: 'Générer',
        items: [
            { text: 'La commande make:fullapi', link: '/fr/guide/generating' },
            { text: 'Types de champs & clés primaires', link: '/fr/guide/field-types' },
            { text: 'Relations', link: '/fr/guide/relationships' },
        ],
    },
    {
        text: 'Sources',
        items: [
            { text: 'Schémas YAML & JSON', link: '/fr/guide/schema-files' },
            { text: 'Diagrammes Mermaid', link: '/fr/guide/mermaid' },
            { text: 'Depuis une base existante', link: '/fr/guide/from-database' },
        ],
    },
    {
        text: 'Jour 30',
        items: [
            { text: 'Faire évoluer les entités', link: '/fr/guide/evolving' },
            { text: 'Tests générés', link: '/fr/guide/testing' },
            { text: 'Doc API & Postman', link: '/fr/guide/docs-and-postman' },
        ],
    },
    {
        text: 'Avancé',
        items: [{ text: 'Personnaliser les stubs', link: '/fr/guide/customizing-stubs' }],
    },
    {
        text: 'Extension VS Code',
        items: [
            { text: 'Découverte & installation', link: '/fr/guide/extension/' },
            { text: "Builder d'entités", link: '/fr/guide/extension/builder' },
            { text: 'Imports : BDD, JSON, OpenAPI', link: '/fr/guide/extension/imports' },
            { text: 'Diagramme & sidebar', link: '/fr/guide/extension/diagram-and-sidebar' },
            { text: 'Actions rapides & garde-fous', link: '/fr/guide/extension/quick-actions' },
            { text: 'Commandes & réglages', link: '/fr/guide/extension/reference' },
        ],
    },
    {
        text: 'Référence',
        items: [
            { text: 'Référence CLI', link: '/fr/reference/cli' },
            { text: 'Changelog', link: '/fr/changelog' },
        ],
    },
]

export default defineConfig({
    title: 'Laravel API Generator',
    base: '/laravel-api-generator/',
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
    locales: {
        root: {
            label: 'English',
            lang: 'en-US',
            description:
                'One command. Your whole Laravel API — models, services, DTOs, policies, written tests and docs, with zero lock-in.',
            themeConfig: {
                nav: [
                    { text: 'Guide', link: '/guide/getting-started' },
                    { text: 'CLI Reference', link: '/reference/cli' },
                    { text: 'VS Code Extension', link: '/guide/extension/' },
                    { text: 'Changelog', link: '/changelog' },
                    { text: 'Issues', link: 'https://github.com/Nameless0l/laravel-api-generator/issues' },
                ],
                sidebar: enSidebar,
                editLink: {
                    pattern: 'https://github.com/Nameless0l/laravel-api-generator/edit/main/docs/:path',
                    text: 'Edit this page on GitHub',
                },
                footer: {
                    message: 'Released under the MIT License.',
                    copyright: '© Mbassi Loïc Aron (Nameless0l)',
                },
            },
        },
        fr: {
            label: 'Français',
            lang: 'fr-FR',
            link: '/fr/',
            description:
                'Une commande. Toute votre API Laravel — modèles, services, DTO, policies, tests écrits et documentation, sans lock-in.',
            themeConfig: {
                nav: [
                    { text: 'Guide', link: '/fr/guide/getting-started' },
                    { text: 'Référence CLI', link: '/fr/reference/cli' },
                    { text: 'Extension VS Code', link: '/fr/guide/extension/' },
                    { text: 'Changelog', link: '/fr/changelog' },
                    { text: 'Issues', link: 'https://github.com/Nameless0l/laravel-api-generator/issues' },
                ],
                sidebar: frSidebar,
                outline: { label: 'Sur cette page' },
                docFooter: { prev: 'Page précédente', next: 'Page suivante' },
                lastUpdated: { text: 'Mis à jour le' },
                returnToTopLabel: 'Retour en haut',
                darkModeSwitchLabel: 'Apparence',
                sidebarMenuLabel: 'Menu',
                langMenuLabel: 'Changer de langue',
                editLink: {
                    pattern: 'https://github.com/Nameless0l/laravel-api-generator/edit/main/docs/:path',
                    text: 'Modifier cette page sur GitHub',
                },
                footer: {
                    message: 'Publié sous licence MIT.',
                    copyright: '© Mbassi Loïc Aron (Nameless0l)',
                },
            },
        },
    },
    themeConfig: {
        logo: '/logo.png',
        socialLinks: [{ icon: 'github', link: 'https://github.com/Nameless0l/laravel-api-generator' }],
        search: {
            provider: 'local',
            options: {
                locales: {
                    fr: {
                        translations: {
                            button: {
                                buttonText: 'Rechercher',
                                buttonAriaLabel: 'Rechercher',
                            },
                            modal: {
                                displayDetails: 'Afficher la liste détaillée',
                                resetButtonTitle: 'Effacer la recherche',
                                backButtonTitle: 'Fermer la recherche',
                                noResultsText: 'Aucun résultat pour',
                                footer: {
                                    selectText: 'pour sélectionner',
                                    navigateText: 'pour naviguer',
                                    closeText: 'pour fermer',
                                },
                            },
                        },
                    },
                },
            },
        },
    },
})
