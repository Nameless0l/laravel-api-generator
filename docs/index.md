---
layout: home

hero:
  name: Laravel API Generator
  text: One command. Your whole Laravel API.
  tagline: Models, services, DTOs, policies, written tests and docs — generated in 30 seconds, with zero lock-in.
  image:
    src: /logo.png
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
      link: https://marketplace.visualstudio.com/items?itemName=Nameless0l.laravel-api-generator

features:
  - icon: 🧪
    title: Tests written, not scaffolded
    details: Feature and unit tests with real assertions — PHPUnit or Pest. php artisan test is green right after generation.
  - icon: 🏛️
    title: An architecture, not just files
    details: Thin controller → service layer → typed DTO, plus policy, form requests and resources. Room to grow, built in.
  - icon: 🔍
    title: Models your IDE understands
    details: Full @property PHPDoc on every model — instant autocomplete in VS Code and PhpStorm, no ide-helper needed.
  - icon: 🗄️
    title: Starts from what you already have
    details: --from-database reverse-engineers an existing schema — relations, morphs, uniques — into complete APIs.
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
    details: A --dev dependency. Generated code is plain Laravel with no reference to the package — remove it, everything keeps working.
---

## 30 seconds, start to finish

```bash
composer require --dev nameless/laravel-api-generator

php artisan make:fullapi Post --fields="title:string,status:enum(draft,published)" --pest
php artisan test   # green ✓
```

![Demo](./demo.gif)
