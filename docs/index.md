---
layout: home

hero:
  name: Laravel API Generator
  text: One command. Your whole Laravel API.
  tagline: "Models, services, DTOs, policies, written tests and docs: generated in 30 seconds, with zero lock-in."
  image:
    light: /logo_dark.png
    dark: /logo.png
    alt: Laravel API Generator
  actions:
    - theme: brand
      text: Get Started
      link: /guide/getting-started
    - theme: alt
      text: View on GitHub
      link: https://github.com/Nameless0l/laravel-api-generator
    - theme: alt
      text: VS Code Extension
      link: /guide/extension/

features:
  - icon: 🧪
    title: Tests written, not scaffolded
    details: "Feature and unit tests with real assertions: PHPUnit or Pest. php artisan test is green right after generation."
  - icon: 🏛️
    title: An architecture, not just files
    details: Thin controller → service layer → typed DTO, plus policy, form requests and resources. Room to grow, built in.
  - icon: 🔍
    title: Models your IDE understands
    details: "Full @property PHPDoc on every model: instant autocomplete in VS Code and PhpStorm, no ide-helper needed."
  - icon: 🗄️
    title: Starts from what you already have
    details: --from-database reverse-engineers an existing schema (relations, morphs, uniques) into complete APIs.
  - icon: 📐
    title: Schema-as-code
    details: YAML files or Mermaid ER diagrams as input. Declare one side of a relation, the inverse and its FK are synthesized.
  - icon: 🔄
    title: Survives day 30
    details: --add-fields evolves an existing entity with an incremental migration and in-place patches. Manual code is never touched.
  - icon: 📖
    title: Docs on day one
    details: Postman collection export and Scramble-friendly controllers for instant OpenAPI documentation.
  - icon: 🔓
    title: Zero lock-in
    details: "A --dev dependency. Generated code is plain Laravel with no reference to the package: remove it, everything keeps working."
---

<script setup>
import demoGif from './demo.gif'
import archImg from './architecture-flow.svg'
import scrambleImg from './scramble-docs.png'

const tabs = [
    {
        title: 'One command',
        img: demoGif,
        imgAlt: 'make:fullapi terminal demo',
        text: 'make:fullapi turns one line into twelve files and a registered route: model, controller, service, DTO, requests, resources, policy, migration, factory, seeder and two test suites.',
        link: '/guide/generating',
        linkText: 'The make:fullapi command',
    },
    {
        title: 'Tests included',
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
        text: 'Not scaffolded: written. Real assertions against real endpoints, factories included, PHPUnit or Pest. This excerpt is an actual generated test, untouched.',
        link: '/guide/testing',
        linkText: 'Generated tests',
    },
    {
        title: 'Architecture',
        img: archImg,
        imgAlt: 'Generated architecture diagram',
        text: 'A thin controller delegating to a service layer, typed readonly DTOs, policies, form requests and resources: the structure you would build on a good day, there from day one.',
        link: '/guide/generating',
        linkText: 'What gets generated',
    },
    {
        title: 'API docs',
        img: scrambleImg,
        imgAlt: 'Scramble OpenAPI documentation',
        text: 'Generated controllers are written so Scramble can document them with zero annotations: an interactive Swagger UI at /docs/api. Postman collection export included.',
        link: '/guide/docs-and-postman',
        linkText: 'API docs & Postman',
    },
    {
        title: 'From your database',
        code: `php artisan make:fullapi --from-database

php artisan make:fullapi --from-database \\
    --tables=products,orders`,
        text: 'Legacy project? --from-database reads the schema and turns every table into a complete, documented API: columns become typed fields, foreign keys become relations, pivot tables become belongsToMany. Add --tables= to target just some of them.',
        link: '/guide/from-database',
        linkText: 'From an existing database',
    },
    {
        title: 'VS Code extension',
        bullets: [
            'Visual entity builder with live code preview',
            'Import from your database, JSON or an OpenAPI spec',
            'Interactive entity diagram',
            'Migrate, seed, test and open API docs in one click',
        ],
        text: 'The whole generator without the terminal: a free extension with a visual builder, live preview and one-click lifecycle actions.',
        link: '/guide/extension/',
        linkText: 'The VS Code extension',
    },
]

// Social proof: add real quotes as they arrive; the section stays hidden while the list is empty.
// { quote: 'What they wrote, without quotation marks', author: 'Their Name', handle: '@handle', link: 'https://x.com/…' },
const testimonials = []
</script>

## 30 seconds, start to finish

```bash
composer require --dev nameless/laravel-api-generator

php artisan make:fullapi Post --fields="title:string,status:enum(draft,published)" --pest
php artisan test
```

Three commands, and the test suite is already green: the tests come with the API.

## See it in action

<HomeFeatureTabs :items="tabs" />

<HomeTestimonials title="What people say" :items="testimonials" />

<!-- VIDEO #1 (YouTube): uncomment and set VIDEO_ID once the first demo video is online:
## Watch the demo

<div style="position:relative;padding-bottom:56.25%;height:0;margin:16px 0">
  <iframe src="https://www.youtube-nocookie.com/embed/VIDEO_ID" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" title="A complete Laravel API in 30 seconds" allowfullscreen loading="lazy"></iframe>
</div>
-->
