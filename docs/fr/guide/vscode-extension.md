# Extension VS Code

Une interface visuelle gratuite pour le générateur : construisez vos entités dans un formulaire, prévisualisez le code généré en direct, et pilotez tout le cycle de vie sans toucher au terminal.

[**Installer depuis le Marketplace**](https://marketplace.visualstudio.com/items?itemName=Nameless0l.laravel-api-generator) · [Dépôt de l'extension](https://github.com/Nameless0l/laravel-api-generator-vscode)

## Ce qu'elle apporte

- **Builder visuel** — nom d'entité, champs avec sélecteur de type (dont `enum` avec saisie des valeurs et case PK), relations avec autocomplétion des modèles, options en cases à cocher (Auth, Postman, Soft Deletes, QueryBuilder, Pest)
- **Aperçu du code en direct** — voyez le modèle, le cast, la validation, la factory et la migration *avant* de générer
- **Import depuis n'importe où** — votre base de données (sélecteur de tables), une définition JSON, ou une **spécification OpenAPI / Swagger**
- **Diagramme d'entités** — un canevas de relations interactif avec zoom et déplacement
- **Actions rapides** — lancer les migrations, fresh + seed, lancer les tests, lister les routes, ouvrir la doc API, personnaliser les stubs
- **Add Fields** — clic droit sur une entité dans la barre latérale pour la faire évoluer (`--add-fields`), avec un bouton *Run Migrations* en un clic
- **Garde-fous** — validation des stubs avant génération, avertissements d'écrasement, réparation des routes orphelines quand `route:list` échoue
- Anglais et français, selon la langue de votre VS Code

## Prérequis

L'extension pilote le package Composer dans votre projet Laravel :

```bash
composer require --dev nameless/laravel-api-generator
```

Si le package manque, l'extension propose de l'installer pour vous — en dépendance de dev, sans lock-in : rien du générateur ne part en production, et le code généré n'en dépend pas.
