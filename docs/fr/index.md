---
layout: home

hero:
  name: Laravel API Generator
  text: Une commande. Toute votre API Laravel.
  tagline: Modèles, services, DTO, policies, tests écrits et documentation — générés en 30 secondes, sans lock-in.
  image:
    src: /logo.png
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
      link: https://marketplace.visualstudio.com/items?itemName=Nameless0l.laravel-api-generator

features:
  - icon: 🧪
    title: Des tests écrits, pas des squelettes
    details: Tests feature et unitaires avec de vraies assertions — PHPUnit ou Pest. php artisan test est vert dès la génération.
  - icon: 🏛️
    title: Une architecture, pas juste des fichiers
    details: Contrôleur fin → couche service → DTO typé, plus policy, form requests et resources. La place pour grandir est déjà là.
  - icon: 🔍
    title: Des modèles que votre IDE comprend
    details: PHPDoc @property complet sur chaque modèle — autocomplétion immédiate dans VS Code et PhpStorm, sans ide-helper.
  - icon: 🗄️
    title: Part de ce que vous avez déjà
    details: --from-database rétro-conçoit un schéma existant — relations, morphs, uniques — en APIs complètes.
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
    details: Une dépendance --dev. Le code généré est du Laravel pur, sans référence au package — supprimez-le, tout continue de fonctionner.
---

## 30 secondes, chrono en main

```bash
composer require --dev nameless/laravel-api-generator

php artisan make:fullapi Post --fields="title:string,status:enum(draft,published)" --pest
php artisan test   # vert ✓
```

![Démo](../demo.gif)
