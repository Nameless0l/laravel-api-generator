# Example Data for Laravel API Generator

This directory contains sample JSON files that can be used with the Laravel API Generator package.

## class_data.json

A blog/CMS schema with four entities: **Author**, **Category**, **Article**, and **Tag**.

### Field types demonstrated

| Type       | Example field           |
|------------|-------------------------|
| `string`   | Author.name, Tag.color  |
| `text`     | Author.bio, Article.content |
| `boolean`  | Author.is_active, Article.is_featured |
| `integer`  | Article.views_count     |
| `datetime` | Article.published_at    |
| `json`     | Article.metadata        |

### Relationships demonstrated

- **oneToMany** -- Author hasMany Articles, Category hasMany Articles
- **manyToOne** -- Article belongsTo Author, Article belongsTo Category
- **manyToMany** -- Article belongsToMany Tags, Tag belongsToMany Articles

### Usage

Generate the full API from this example file:

1. Copy the file to your Laravel project root:

```bash
cp examples/class_data.json /path/to/your/laravel-project/class_data.json
```

2. Generate the full API:

```bash
cd /path/to/your/laravel-project
php artisan make:fullapi
```

3. Run migrations and seed:

```bash
php artisan migrate:fresh --seed
```

4. Browse the API documentation (requires [Scramble](https://github.com/dedoc/scramble)):

```bash
php artisan serve
# Open http://localhost:8000/docs/api
```

You can also use this file with the [VS Code extension](https://marketplace.visualstudio.com/items?itemName=Nameless0l.laravel-api-generator) via the **Import JSON** button.
