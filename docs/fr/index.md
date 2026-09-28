---
layout: home

hero:
  name: Laravel API Generator
  text: Une commande. Toute votre API Laravel.
  tagline: "Modèles, services, DTO, policies, tests écrits et documentation : générés en 30 secondes, sans lock-in."
  image:
    light: /logo_dark.png
    dark: /logo.png
    alt: Laravel API Generator
  actions:
    - theme: brand
      text: Commencer
      link: /fr/guide/getting-started
    - theme: alt
      text: Voir sur GitHub
      link: https://github.com/Nameless0l/laravel-api-generator
    - theme: alt
      text: Extension VS Code
      link: /fr/guide/extension/

features:
  - icon: 🤖
    title: Prêt pour les agents IA
    details: "Un serveur MCP donne à Claude Code, Copilot et Cursor quatre outils pour planifier et générer votre API. Ils prévisualisent chaque fichier d'abord et n'écrasent jamais celui que vous avez retouché."
  - icon: 🧪
    title: Des tests écrits, pas des squelettes
    details: "Tests feature et unitaires avec de vraies assertions : PHPUnit ou Pest. php artisan test est vert dès la génération."
  - icon: 🏛️
    title: Une architecture, pas juste des fichiers
    details: Contrôleur fin → couche service → DTO typé, plus policy, form requests et resources. La place pour grandir est déjà là.
  - icon: 🔍
    title: Des modèles que votre IDE comprend
    details: "PHPDoc @property complet sur chaque modèle : autocomplétion immédiate dans VS Code et PhpStorm, sans ide-helper."
  - icon: 🗄️
    title: Part de ce que vous avez déjà
    details: --from-database rétro-conçoit un schéma existant (relations, morphs, uniques) en APIs complètes.
  - icon: 📐
    title: Schema-as-code
    details: Fichiers YAML ou diagrammes Mermaid en entrée. Déclarez un côté d'une relation, l'inverse et sa clé étrangère sont synthétisés.
  - icon: 🔄
    title: Survit au jour 30
    details: --add-fields fait évoluer une entité existante avec une migration incrémentale et des patchs en place. Votre code manuel n'est jamais touché.
  - icon: 📖
    title: La doc dès le premier jour
    details: Export de collection Postman et contrôleurs compatibles Scramble pour une documentation OpenAPI immédiate.
  - icon: 🔓
    title: Zéro lock-in
    details: "Une dépendance --dev. Le code généré est du Laravel pur, sans référence au package : supprimez-le, tout continue de fonctionner."
---

<script setup>
import { withBase } from 'vitepress'
import demoGif from '../demo.gif'
import archImg from '../architecture-flow-fr.svg'
import archMotion from '../architecture-motion.gif'
import scrambleImg from '../scramble-docs.png'

const tabs = [
    {
        title: 'Une commande',
        img: demoGif,
        imgAlt: 'make:fullapi génère deux entités depuis api-schema.yaml, puis php artisan test et pint --test',
        text: "Depuis une ligne de flags ou un fichier de schéma, make:fullapi écrit treize fichiers par entité et enregistre les routes. Les tests qu'il écrit passent tout de suite, et le code passe Pint.",
        link: '/fr/guide/generating',
        linkText: 'La commande make:fullapi',
    },
    {
        title: 'Agents IA',
        img: withBase('/mcp-tools.png'),
        imgAlt: 'Les quatre outils du serveur MCP dans Claude Code',
        text: "Ajoutez laravel/mcp et votre agent reçoit quatre outils pour lister les entités, prévisualiser chaque fichier, générer l'API et ajouter des champs. Le prompt design-api le mène de votre description à un plan que vous validez, et un fichier retouché à la main n'est jamais écrasé.",
        link: '/fr/guide/mcp',
        linkText: 'Le serveur MCP',
    },
    {
        title: 'Tests inclus',
        code: `it('lists posts', function () {
    Post::factory()->count(3)->create();

    $response = $this->getJson('/api/posts');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

it('shows a post', function () {
    $post = Post::factory()->create();

    $response = $this->getJson("/api/posts/{$post->getKey()}");

    $response->assertStatus(200)
        ->assertJsonFragment(['id' => $post->getKey()]);
});`,
        text: "De vraies assertions contre de vrais endpoints, factories comprises, en PHPUnit ou en Pest. Cet extrait est un test généré, non retouché, et la plupart des générateurs s'arrêtent au squelette.",
        link: '/fr/guide/testing',
        linkText: 'Les tests générés',
    },
    {
        title: 'Architecture',
        img: archMotion,
        imgAlt: "Diagramme de l'architecture générée",
        text: "Un contrôleur fin qui délègue à une couche service, des DTO readonly typés, des policies, form requests et resources : la structure qu'on construit un bon jour, présente dès le premier.",
        link: '/fr/guide/generating',
        linkText: 'Ce qui est généré',
    },
    {
        title: 'Doc API',
        img: scrambleImg,
        imgAlt: 'Documentation OpenAPI Scramble',
        text: "Les contrôleurs générés sont écrits pour que Scramble les documente sans aucune annotation : un Swagger UI interactif sur /docs/api. Export de collection Postman inclus.",
        link: '/fr/guide/docs-and-postman',
        linkText: 'Doc API & Postman',
    },
    {
        title: 'Depuis votre base',
        code: `php artisan make:fullapi --from-database

php artisan make:fullapi --from-database \\
    --tables=products,orders`,
        text: "Projet legacy ? --from-database lit le schéma et transforme chaque table en API complète et documentée : les colonnes deviennent des champs typés, les clés étrangères des relations, les tables pivots des belongsToMany. Ajoutez --tables= pour n'en cibler que certaines.",
        link: '/fr/guide/from-database',
        linkText: 'Depuis une base existante',
    },
    {
        title: 'Extension VS Code',
        img: withBase('/ext-builder.gif'),
        imgAlt: "Le builder de l'extension : choisir un exemple, ajouter une relation, et l'aperçu en direct suit",
        text: "Tout le générateur sans le terminal. Un builder avec l'aperçu en direct de chaque fichier, un seul écran de relecture pour votre base, une spec ou une description Copilot, et les migrations et les tests lancés depuis le même panneau.",
        link: '/fr/guide/extension/',
        linkText: "L'extension VS Code",
    },
]

// Preuve sociale : ajouter les vraies citations au fil du lancement ; la section reste cachée tant que la liste est vide.
// { quote: 'Ce qui a été écrit, sans guillemets', author: 'Nom', handle: '@handle', link: 'https://x.com/…' },
const testimonials = []
</script>

## 30 secondes, chrono en main

```bash
composer require --dev nameless/laravel-api-generator

php artisan make:fullapi Post --fields="title:string,status:enum(draft,published)" --pest
php artisan test
```

Trois commandes, et la suite de tests est déjà verte. Les tests arrivent avec l'API.

## En action

<HomeFeatureTabs :items="tabs" />

<HomeTestimonials title="Ce qu'on en dit" :items="testimonials" />

## Voir la démo

<div style="position:relative;padding-bottom:56.25%;height:0;margin:16px 0">
  <iframe src="https://www.youtube-nocookie.com/embed/bRK9Y8jn7yY" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" title="Laravel API Generator 4.0 en deux minutes" allowfullscreen loading="lazy"></iframe>
</div>
