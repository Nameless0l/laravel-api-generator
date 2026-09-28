# Extension VS Code

Une interface visuelle gratuite pour le générateur. Construisez une entité dans un formulaire pendant que le package affiche les fichiers qu'il va écrire, générez depuis votre base, une spec ou une simple description, puis lancez les migrations et les tests depuis le même panneau.

[**Installer depuis le Marketplace**](https://marketplace.visualstudio.com/items?itemName=Nameless0l.laravel-api-generator) · [Dépôt de l'extension](https://github.com/Nameless0l/laravel-api-generator-vscode)

<!-- VIDEO (YouTube) : décommenter et renseigner VIDEO_ID quand la vidéo de lancement de la 4.0 est en ligne :
<div style="position:relative;padding-bottom:56.25%;height:0;margin:16px 0">
  <iframe src="https://www.youtube-nocookie.com/embed/VIDEO_ID" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" title="Laravel API Generator 4.0 et son extension VS Code" allowfullscreen loading="lazy"></iframe>
</div>
-->

![L'extension dans VS Code : l'accueil de la sidebar, le builder et l'aperçu en direct des fichiers](/ext-overview.png)

## Ce qu'elle apporte

| | |
|---|---|
| [Le builder d'entités](/fr/guide/extension/builder) | Un formulaire à côté de l'aperçu en direct de chaque fichier, rendu par le package installé dans votre projet |
| [Sources & relecture](/fr/guide/extension/imports) | Générez depuis une description avec Copilot, votre base de données, un fichier de schéma, un diagramme Mermaid ou une **spec OpenAPI**, après relecture de la simulation du package |
| [Diagramme & sidebar](/fr/guide/extension/diagram-and-sidebar) | Un canevas d'entités avec un inspecteur, et l'arbre de chaque fichier généré, ceux que vous avez retouchés signalés |
| [API prête & actions du projet](/fr/guide/extension/quick-actions) | Migrations, tests, seed, doc API et stubs, lancés sur place avec leur résultat |
| [Copilot](/fr/guide/extension/reference#copilot-et-fichiers-de-schema) | Le skill d'agent du package et son [serveur MCP](/fr/guide/mcp), pour que le mode agent de Copilot planifie et génère des API avec le package |
| [Commandes & réglages](/fr/guide/extension/reference) | Référence de la palette de commandes, raccourcis, settings, snippets PHP |

Toute l'interface (libellés, popups, invites, messages d'erreur) existe en **anglais et en français**, selon la langue d'affichage de VS Code (forçable via le réglage `laravelApiGenerator.locale`). Les captures de cette documentation montrent l'interface en anglais.

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

- VS Code 1.82+
- PHP 8.2+ dans le PATH (ou réglez `laravelApiGenerator.phpPath`, ou `laravelApiGenerator.phpCommand` pour Sail et Docker)
- Un projet Laravel 10 / 11 / 12 / 13. La ligne 4.x du package demande Laravel 12 : sur Laravel 10 et 11, l'extension installe sa ligne 3.x.

## Votre première API, sans terminal

1. Cliquez sur l'icône **Laravel API Generator** dans la barre d'activité, puis sur **Nouvelle API**.
2. Remplissez le formulaire, partez du menu **Exemples**, ou amenez une entité depuis le menu **Importer**. L'[aperçu en direct](/fr/guide/extension/builder) suit chaque modification.
3. Cliquez sur **Générer l'API** (`Ctrl+Entrée`). Le panneau devient l'[écran de l'API prête](/fr/guide/extension/quick-actions), avec les fichiers écrits et les routes enregistrées.
4. Lancez les migrations, puis les tests. Chaque étape affiche son résultat sur place, par exemple le nombre de tests réussis. Si `.env` manque, l'extension propose d'abord de le créer depuis `.env.example`.
5. **Ouvrir la documentation de l'API** démarre le serveur de développement si aucun ne tourne et ouvre la documentation interactive de votre nouvelle API. Si Scramble manque, l'étape propose de l'installer.

![Un clic sur Générer l'API, puis les migrations et les tests se lancent sur place](/ext-generate.gif)
