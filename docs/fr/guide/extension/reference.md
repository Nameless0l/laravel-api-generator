# Référence commandes & réglages

## Commandes

Toutes les commandes vivent sous la catégorie **Laravel API Generator** de la palette (`Ctrl+Shift+P`). La plupart sont aussi accessibles depuis la barre d'outils et le menu `…` de la sidebar.

| Commande | Description |
|----------|-------------|
| Generate Full API | Ouvre le panneau du [builder d'entités](/fr/guide/extension/builder) |
| Generate APIs from Database | Génération schéma complet avec multi-sélection des tables |
| Generate APIs from Schema File | Génère depuis `api-schema.yaml` / `.yml` / `.json` |
| Generate APIs from Mermaid Diagram | Génère depuis un fichier `.mmd` |
| Add Fields to Entity… | Fait évoluer une entité via `--add-fields` |
| Regenerate File(s)… | Reconstruit les artefacts choisis via `--only=` |
| Delete Full API | Supprime fichiers, routes et enregistrement du seeder d'une entité |
| Show Entity Diagram | Ouvre le [canevas interactif](/fr/guide/extension/diagram-and-sidebar) |
| Show Snippets | Liste les snippets PHP embarqués |
| Go to Related File | Saute entre les fichiers générés d'une entité |
| Refresh Entities | Re-scanne le projet à la recherche d'entités générées |

## Raccourcis clavier

| Touches | Commande |
|---------|----------|
| `Ctrl+Alt+R` (`Cmd+Alt+R` sur macOS) | Go to Related File |

## Réglages

| Réglage | Défaut | Description |
|---------|--------|-------------|
| `laravelApiGenerator.phpPath` | `php` | Chemin de l'exécutable PHP |
| `laravelApiGenerator.locale` | `auto` | Langue de l'interface : `auto` (suit VS Code), `en` ou `fr` |

## Snippets PHP

Tapez un préfixe `lag:` dans n'importe quel fichier PHP :

| Préfixe | Produit |
|---------|---------|
| `lag:service` | Une classe service complète (getAll avec filtres, create, find, update, delete) |
| `lag:controller` | Un contrôleur CRUD avec injection du service |
| `lag:dto` | Une classe DTO readonly avec `fromRequest()` |
| `lag:request` | Une FormRequest avec `authorize()` et `rules()` |
| `lag:resource` | Une méthode `toArray()` de resource API |
| `lag:factory` | Une méthode `definition()` de factory |
| `lag:test-feature` | Un squelette de test feature |
| `lag:test-unit` | Un squelette de test unitaire de service |
| `lag:route` | `Route::apiResource(…)` |
| `lag:filter` | Un query scope `scopeFilter()` |

## Activation

L'extension s'active quand le workspace contient un fichier `artisan` — y compris dans les monorepos où l'app Laravel vit jusqu'à deux niveaux de profondeur (`backend/`, `apps/api/`…).

## Changelog

Les versions de l'extension sont listées sur la page [Changelog](/fr/changelog).
