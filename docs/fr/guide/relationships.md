# Relations

Les relations se déclarent dans les [fichiers de schéma](/fr/guide/schema-files), les [diagrammes Mermaid](/fr/guide/mermaid), `class_data.json`, l'assistant interactif — ou sont détectées automatiquement [depuis votre base](/fr/guide/from-database).

## Déclarez un côté, obtenez les deux

Sur toutes les sources schéma et Mermaid, déclarer un seul côté d'un `belongsTo` / `hasMany` / `belongsToMany` suffit — la relation inverse **et sa colonne FK dans la migration** sont synthétisées automatiquement, exactement comme le fait `--from-database` :

```yaml
entities:
  Category:
    fields:
      name: string unique
    relations:
      posts: hasMany Post   # Post reçoit belongsTo Category + la colonne category_id, automatiquement
  Post:
    fields:
      title: string
```

Si les deux côtés sont déclarés, ils sont dédupliqués.

## Vocabulaire Eloquent

Les fichiers de schéma utilisent directement les noms de méthodes Eloquent :

| Déclaration | Méthode Eloquent | Clé étrangère |
|-------------|------------------|---------------|
| `author: belongsTo User` | `belongsTo()` | Sur la table courante |
| `posts: hasMany Post` | `hasMany()` | Sur la table liée |
| `profile: hasOne Profile` | `hasOne()` | Sur la table liée |
| `tags: belongsToMany Tag` | `belongsToMany()` | Table pivot, créée automatiquement |

Les entités sont générées **parents d'abord**, pour que les migrations s'exécutent dans un ordre sûr vis-à-vis des clés étrangères, et les migrations pivot sont créées automatiquement pour chaque `belongsToMany`.

## Relations polymorphiques

Les fichiers de schéma supportent `morphTo`, `morphOne` et `morphMany` :

```yaml
entities:
  Post:
    fields:
      title: string
    relations:
      comments: morphMany Comment
  Comment:
    fields:
      body: text
    relations:
      commentable: morphTo
```

`morphTo` émet `$table->morphs('commentable')` dans la migration et `morphTo()` sur le modèle ; `morphOne` / `morphMany` pointent en retour avec le bon nom de morph. L'introspection de base détecte automatiquement les paires de colonnes `*_type` / `*_id` comme `morphTo`.

## Les clés primaires personnalisées se propagent

Quand une entité liée utilise une [clé primaire personnalisée](/fr/guide/field-types#cles-primaires-personnalisees), chaque relation entrante suit : nom de la FK (`country_code`), type de colonne, `->references('code')` et la règle de validation `exists:countries,code`.

## Mode JSON (`class_data.json`)

La génération en masse depuis JSON utilise des tableaux de relations explicites :

| Clé JSON | Méthode Eloquent |
|----------|------------------|
| `oneToOneRelationships` | `hasOne()` |
| `oneToManyRelationships` | `hasMany()` |
| `manyToOneRelationships` | `belongsTo()` |
| `manyToManyRelationships` | `belongsToMany()` |

```json
[
  {
    "name": "User",
    "attributes": [{ "name": "name", "_type": "string" }],
    "oneToManyRelationships": [{ "role": "posts", "comodel": "Post" }]
  },
  {
    "name": "Post",
    "attributes": [{ "name": "title", "_type": "string" }],
    "manyToOneRelationships": [{ "role": "user", "comodel": "User" }]
  }
]
```

L'héritage de modèle est aussi supporté via la clé `"parent"`.

## Les relations dans le PHPDoc

Chaque relation atterrit dans le docblock du modèle, donc votre IDE autocomplète `$post->comments` immédiatement :

```php
/**
 * @property-read Category $category
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Comment> $comments
 */
```
