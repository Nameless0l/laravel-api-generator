# Extension VS Code

Une interface visuelle gratuite pour le générateur : construisez vos entités dans un formulaire, prévisualisez le code généré en direct, et pilotez tout le cycle de vie de l'API sans toucher au terminal.

[**Installer depuis le Marketplace**](https://marketplace.visualstudio.com/items?itemName=Nameless0l.laravel-api-generator) · [Dépôt de l'extension](https://github.com/Nameless0l/laravel-api-generator-vscode)

<!-- VIDEO #5 (YouTube) : décommenter et renseigner VIDEO_ID quand la vidéo du tour de l'extension est en ligne :
<div style="position:relative;padding-bottom:56.25%;height:0;margin:16px 0">
  <iframe src="https://www.youtube-nocookie.com/embed/VIDEO_ID" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" title="Le tour de l'extension VS Code" allowfullscreen loading="lazy"></iframe>
</div>
-->

<!-- CAPTURE : vue complète de l'extension (sidebar + panneau). Enregistrer sous docs/public/ext-overview.png puis :
![L'extension dans VS Code](/ext-overview.png)
-->

## Ce qu'elle apporte

| | |
|---|---|
| [Le builder d'entités](/fr/guide/extension/builder) | Un formulaire avec aperçu du code en direct à la place des flags CLI : champs, enums, relations, options |
| [Les imports](/fr/guide/extension/imports) | Générez depuis votre base de données, un fichier de schéma, un diagramme Mermaid, une définition JSON ou une **spec OpenAPI / Swagger** |
| [Diagramme & sidebar](/fr/guide/extension/diagram-and-sidebar) | Un canevas d'entités interactif et l'arborescence de tout ce que vous avez généré |
| [Actions rapides & garde-fous](/fr/guide/extension/quick-actions) | Migrate, seed, tests, routes et doc API en un clic, serveur et dépendances gérés pour vous |
| [Commandes & réglages](/fr/guide/extension/reference) | Référence de la palette de commandes, raccourcis, settings, snippets PHP |

Toute l'interface (libellés, popups, invites, messages d'erreur) existe en **anglais et en français**, selon la langue d'affichage de VS Code (forçable via le réglage `laravelApiGenerator.locale`).

## Installation

1. Cherchez **« Laravel API Generator »** dans les extensions VS Code (`Ctrl+Shift+X`), ou installez depuis le [Marketplace](https://marketplace.visualstudio.com/items?itemName=Nameless0l.laravel-api-generator).
2. Ouvrez un projet Laravel : l'extension s'active dès qu'elle trouve un fichier `artisan` (monorepos supportés : les apps Laravel jusqu'à deux niveaux sous la racine du workspace, ex. `backend/` ou `apps/api/`, sont détectées).
3. L'extension pilote le package Composer de votre projet :

```bash
composer require --dev nameless/laravel-api-generator
```

Si le package manque, l'extension propose de l'installer pour vous, en dépendance de dev (rien du générateur ne part en production, et le code généré n'en dépend pas). Si la version installée est trop ancienne pour une fonctionnalité, elle l'explique et propose un `composer update`.

Un **walkthrough natif** (Help → Get Started) couvre l'installation du package, la première génération, l'import de base de données et la sidebar.

## Prérequis

- VS Code 1.80+
- PHP 8.2+ dans le PATH (ou réglez `laravelApiGenerator.phpPath`)
- Un projet Laravel 10 / 11 / 12 / 13

## Votre première API, sans terminal

1. Cliquez sur l'icône **Laravel API Generator** dans la barre d'activité.
2. Choisissez un preset Quick Start (Blog Post, Product…), remplissez le formulaire, ou [importez](/fr/guide/extension/imports) depuis votre base ou une spec.
3. Regardez l'[aperçu en direct](/fr/guide/extension/builder) se mettre à jour pendant la saisie, puis cliquez **Generate API**. Le Model et le Controller s'ouvrent dans l'éditeur.
4. Cliquez **Run Migrations** (si `.env` manque, l'extension propose de le créer depuis `.env.example`), puis **Fresh + Seed** si vous voulez des données d'exemple.
5. Cliquez **Open API Docs** : l'extension démarre le serveur si aucun ne tourne et ouvre la documentation interactive de votre nouvelle API dans le navigateur.

Le tour complet des boutons est sur la page [Actions rapides](/fr/guide/extension/quick-actions).
