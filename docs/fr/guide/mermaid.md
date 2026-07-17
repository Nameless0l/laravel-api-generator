# Diagrammes Mermaid

Le diagramme de votre README (celui que GitHub rend nativement) est une entrée valide. Dessinez votre modèle de données en Mermaid, ou demandez-le à un assistant IA, et générez l'API à partir de lui.

## Usage

```bash
php artisan make:fullapi --mermaid=blog.mmd
```

Avec un diagramme comme :

```
erDiagram
    USER ||--o{ POST : writes
    POST }o--o{ TAG : tagged

    POST {
        string title
        text content
        datetime deleted_at
    }
    TAG {
        string name UK
    }
```

([Exemple complet](https://github.com/Nameless0l/laravel-api-generator/blob/main/examples/blog.mmd))

## Ce que le parseur comprend

`erDiagram` et `classDiagram` sont tous deux supportés :

- **Les cardinalités** (`||--o{`, `"1" --> "*"`) deviennent les bonnes relations Eloquent **des deux côtés** : l'inverse et sa colonne FK sont [synthétisés](/fr/guide/relationships#declarez-un-cote-obtenez-les-deux)
- **Les compositions / agrégations** (`*--`, `o--`) deviennent des `hasMany`
- **Les marqueurs `UK`** deviennent des champs uniques avec la règle de validation correspondante
- **Les colonnes `deleted_at`** activent les soft deletes (trait + endpoints restore/force-delete)
- **Les fences markdown et commentaires sont retirés** : collez les diagrammes tels que votre assistant IA les a produits

## Le workflow design-first

1. Dessinez le diagramme ER dans `docs/erd.mmd` : GitHub le rend dans la PR.
2. Relisez le *diagramme*, pas 40 fichiers de code généré.
3. Mergez, puis `php artisan make:fullapi --mermaid=docs/erd.mmd`.

Le diagramme **est** la source. Aucune dérive entre la doc d'architecture et le code.
