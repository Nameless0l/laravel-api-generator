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
import demoGif from '../demo.gif'
import archImg from '../architecture-flow-fr.svg'
import archMotion from '../architecture-motion.gif'
import scrambleImg from '../scramble-docs.png'

const tabs = [
    {
        title: 'Une commande',
        img: demoGif,
        imgAlt: 'Démo terminal de make:fullapi',
        text: "make:fullapi transforme une ligne en douze fichiers et une route enregistrée : modèle, contrôleur, service, DTO, requests, resources, policy, migration, factory, seeder et deux suites de tests.",
        link: '/fr/guide/generating',
        linkText: 'La commande make:fullapi',
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
        bullets: [
            "Builder visuel avec aperçu du code en direct",
            "Import depuis votre base, un JSON ou une spec OpenAPI",
            "Diagramme d'entités interactif",
            "Migrate, seed, tests et doc API en un clic",
        ],
        text: "Tout le générateur sans le terminal : une extension gratuite avec builder visuel, aperçu en direct et actions de cycle de vie en un clic.",
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

<!-- VIDEO #1 (YouTube) : décommenter et renseigner VIDEO_ID quand la première vidéo de démo est en ligne :
## Voir la démo

<div style="position:relative;padding-bottom:56.25%;height:0;margin:16px 0">
  <iframe src="https://www.youtube-nocookie.com/embed/VIDEO_ID" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" title="Une API Laravel complète en 30 secondes" allowfullscreen loading="lazy"></iframe>
</div>
-->
