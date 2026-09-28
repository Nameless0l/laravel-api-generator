# Types de champs & clés primaires

Les champs se déclarent en paires `nom:type`, séparées par des virgules :

```bash
php artisan make:fullapi Product --fields="name:string,price:decimal,stock:integer,meta:json"
```

## Types supportés

| Type | Colonne BDD | Type PHP | Règle de validation |
|------|-------------|----------|---------------------|
| `string` | `VARCHAR(255)` | `string` | `string\|max:255` |
| `text` | `TEXT` | `string` | `string` |
| `integer` / `int` | `INTEGER` | `int` | `integer` |
| `bigint` | `BIGINTEGER` | `int` | `integer` |
| `boolean` / `bool` | `BOOLEAN` | `bool` | `boolean` |
| `float` / `decimal` | `DECIMAL(8,2)` | `float` | `numeric` |
| `json` | `JSON` | `array` | `array` |
| `date` | `DATE` | `DateTimeInterface` | `date` |
| `time` | `TIME` | `string` | `date_format:H:i,H:i:s` |
| `datetime` | `DATETIME` | `DateTimeInterface` | `date` |
| `timestamp` | `TIMESTAMP` | `DateTimeInterface` | `date` |
| `uuid` | `UUID` | `string` | `uuid` |
| `enum(a,b,...)` | `ENUM('a','b')` | backed enum + cast | `Rule::enum()` |

Chaque type se propage dans toute la pile : colonne de migration, règles de validation, cast du modèle, valeur de factory, type de propriété du DTO et `@property` du PHPDoc.

## Champs enum natifs

```bash
php artisan make:fullapi Article --fields="title:string,status:enum(draft,published,archived)"
```

Une seule définition de champ produit toute la chaîne. Voici le code qu'écrit la commande ci-dessus, réduit aux lignes qui concernent l'enum :

::: code-group

```php [Enum]
namespace App\Enums;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
```

```php [Modèle]
/**
 * @property int $id
 * @property string $title
 * @property ArticleStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Article extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'status'];

    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
        ];
    }
}
```

```php [Request]
public function rules(): array
{
    return [
        'title' => 'required|string|max:255',
        'status' => ['required', Rule::enum(ArticleStatus::class)],
    ];
}
```

```php [Factory]
public function definition(): array
{
    return [
        'title' => fake()->word(),
        'status' => fake()->randomElement(ArticleStatus::cases()),
    ];
}
```

```php [Migration]
Schema::create('articles', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->enum('status', ['draft', 'published', 'archived']);
    $table->timestamps();
});
```

:::

L'enum porte le nom de l'entité et du champ, `ArticleStatus` ici, si bien que deux entités peuvent avoir chacune leur `status`.

Dans un fichier de schéma :

```yaml
status: enum(draft,published) default=draft
```

## Clés primaires personnalisées

Ajoutez `:primary` (CLI) ou le modificateur `primary` (fichier de schéma) pour faire d'un champ la clé primaire à la place de l'`id` auto-incrémenté :

```bash
php artisan make:fullapi Country --fields="code:string:primary,name:string"
```

Toute la pile suit automatiquement :

- Migration : `$table->string('code')->primary()`, pas de `$table->id()`
- Modèle : `$primaryKey`, `$incrementing = false`, `$keyType` déclarés
- **Relations entrantes** : la FK est nommée `country_code`, typée comme la clé, avec `->references('code')` dans la migration et `exists:countries,code` en validation
- Les tests générés utilisent `getKey()` pour passer avec les deux styles de clé

## Nullable, unique et valeurs par défaut

La syntaxe `--fields` s'en tient à `nom:type`. Pour les contraintes par champ, utilisez l'[assistant interactif](/fr/guide/generating#assistant-interactif) ou un [fichier de schéma](/fr/guide/schema-files), qui accepte un raccourci ou un mapping :

```yaml
fields:
  title: string
  slug: string unique
  content: text nullable
  views: { type: integer, default: 0 }
```

<!-- VIDEO #2 (YouTube) : décommenter et renseigner VIDEO_ID quand la vidéo est en ligne, puis la placer en haut de page :
<div style="position:relative;padding-bottom:56.25%;height:0;margin:16px 0">
  <iframe src="https://www.youtube-nocookie.com/embed/VIDEO_ID" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" title="Enums et clés primaires personnalisées" allowfullscreen loading="lazy"></iframe>
</div>
-->
