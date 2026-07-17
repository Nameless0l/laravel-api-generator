# Schémas YAML & JSON

Décrivez toute votre API dans un fichier déclaratif et versionnable : committez-le, relisez-le en PR, régénérez à volonté.

## Schéma YAML

```yaml
# api-schema.yaml
options:
  query_builder: true        # optionnel, s'applique à toutes les entités
  pest: true                 # tests Pest au lieu de PHPUnit

entities:
  Category:
    fields:
      name: string unique
    relations:
      posts: hasMany Post

  Post:
    soft_deletes: true
    fields:
      title: string
      content: text nullable
      status: enum(draft,published) default=draft
      views: { type: integer, default: 0 }
    relations:
      tags: belongsToMany Tag

  Tag:
    fields:
      name: string unique
```

```bash
php artisan make:fullapi --schema=api-schema.yaml

# Ou simplement : si api-schema.yaml (ou .yml / .json) existe à la racine
# du projet, il est détecté automatiquement
php artisan make:fullapi
```

## Syntaxe des champs

Les champs acceptent un raccourci ou un mapping :

```yaml
fields:
  title: string                              # raccourci
  slug: string unique
  excerpt: text nullable
  code: string primary                       # clé primaire personnalisée
  status: enum(draft,published) default=draft
  views: { type: integer, default: 0, rules: 'min:0' }   # mapping
```

## Options

Les options peuvent être globales (sous `options:`) ou par entité :

| Option | Effet |
|--------|-------|
| `soft_deletes: true` | Trait SoftDeletes + endpoints restore/force-delete |
| `query_builder: true` | Filtrage & tri Spatie QueryBuilder sur l'index |
| `pest: true` | Tests Pest au lieu de PHPUnit |

## Ce que vous obtenez gratuitement

- **Relations inverses synthétisées** : déclarez `posts: hasMany Post` sur `Category`, et `Post` reçoit le `belongsTo` et sa colonne de migration `category_id`. [Détails](/fr/guide/relationships).
- **Ordre sûr pour les clés étrangères** : les entités sont générées parents d'abord, `php artisan migrate` ne trébuche jamais sur une table manquante.
- **Pivots automatiques** : chaque `belongsToMany` crée sa migration pivot.

## Mode JSON en masse (`class_data.json`)

Le format historique de génération en masse, toujours pleinement supporté : créez `class_data.json` à la racine du projet et lancez `php artisan make:fullapi` sans argument. Voir [Relations → Mode JSON](/fr/guide/relationships#mode-json-class-data-json) pour le format, ou [téléchargez le schéma Blog d'exemple](https://github.com/Nameless0l/laravel-api-generator/blob/main/examples/class_data.json).

::: tip Compatible IA
Un fichier YAML unique décrivant toute une API est une cible idéale pour un assistant IA : demandez un schéma à votre modèle préféré, relisez-le, générez. Pas de chemins de fichiers hallucinés : c'est le générateur qui possède la structure.
:::
