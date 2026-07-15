# Test Scenario: Laravel API Generator v3.5.0

**Date:** 2026-07-15  
**Scope:** End-to-end testing of four new Priority 2 features before tagging v3.5.0.

This document guides manual testing of:
- `--from-database` — Generate APIs for existing database schemas
- `--schema=api-schema.yaml` — Generate from declarative YAML/JSON files
- `--mermaid=` — Generate from Mermaid diagrams
- `--query-builder` — Spatie QueryBuilder integration

VS Code extension v0.7.0 provides UI commands for all three source modes.

---

## Part 1: Package Testing (CLI)

### 1.1 Setup

Create a fresh Laravel app and wire the local package via Composer path repo:

```bash
# Create a scratch directory (outside your projects)
mkdir ~/test-api-generator
cd ~/test-api-generator

# Fresh Laravel 12 app
composer create-project laravel/laravel testapp --no-interaction --prefer-dist

cd testapp

# Register the local package as a path repo
composer config repositories.local path '/path/to/D:/laravel-api-generator'

# Require it as @dev
composer require 'nameless/laravel-api-generator:@dev' -W

# Set up SQLite database (for schema introspection)
touch database/database.sqlite
# .env should already have DB_CONNECTION=sqlite (Laravel 12 default)

# Install API routes
php artisan install:api --no-interaction
```

**Verify:** `php artisan make:fullapi --help` should show 7 new options:
- `--schema=`
- `--mermaid=`
- `--from-database`
- `--tables=`
- `--with-migrations`
- `--query-builder`
- `--only=`

---

### 1.2 Test `--from-database`

#### 1.2.1 Create a "legacy" database schema

Create `database/migrations/2020_01_01_000000_create_legacy_schema.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content')->nullable();
            $table->integer('views')->default(0);
            $table->foreignId('category_id')->constrained('categories');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['post_id', 'tag_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
    }
};
```

Migrate:

```bash
php artisan migrate
```

**Expected output:**
```
Migration: 2020_01_01_000000_create_legacy_schema
Migrated:  ...
```

#### 1.2.2 Generate APIs from the database

```bash
php artisan make:fullapi --from-database --tables=categories,posts,tags
```

**Expected:**
- Generates `Category`, `Post`, `Tag` (but NOT `PostTag` — pivot is auto-detected)
- Post's migration should show `foreignId('category_id')`
- Post model should have `SoftDeletes` trait
- Post model should have `belongsTo(Category)` and `belongsToMany(Tag)` relations
- Post controller's index should support pagination
- Pivot migration is generated separately (named `2026_07_15_XXXXX_create_post_tag_pivot.php`)

Check generated files:

```bash
# Verify Post model has FK and soft deletes
cat app/Models/Post.php | grep -A 5 'protected $fillable'
cat app/Models/Post.php | grep 'SoftDeletes'
cat app/Models/Post.php | grep -A 3 'belongsTo'

# Verify Category has no FK (it's a parent)
cat app/Models/Category.php | grep 'foreignId'  # Should be empty

# Verify pivot migration exists
ls -la database/migrations | grep post_tag
```

---

### 1.3 Test `--schema` and `--mermaid`

Copy the example files to the test app:

```bash
# From your package repo
cp examples/api-schema.yaml /path/to/testapp/api-schema.yaml
cp examples/blog.mmd /path/to/testapp/blog.mmd
```

#### 1.3.1 Generate from YAML schema

```bash
php artisan make:fullapi --schema=api-schema.yaml
```

**Expected:**
- Generates `Category`, `Post`, `Tag`, `Comment` (4 entities from the schema)
- Post has soft deletes, relations to Category and Tag
- Tag has belongsToMany(Post)
- Comment has belongsTo(Post)
- All migrations are ordered (parents first)
- Pivot migration for posts+tags

Verify:

```bash
ls -la app/Models/ | grep -E 'Category|Post|Tag|Comment'
```

#### 1.3.2 Generate from Mermaid diagram

```bash
php artisan make:fullapi --mermaid=blog.mmd
```

**Expected:**
- Generates `User`, `Post`, `Tag` from the erDiagram
- Post has `belongsTo(User)` and `belongsToMany(Tag)`
- Relationships are bidirectional (Tag has `hasMany(Post)` via pivot)
- UK constraints on name fields are detected

Verify:

```bash
grep -A 5 'belongsTo' app/Models/Post.php
grep 'belongsToMany' app/Models/Post.php
```

---

### 1.4 Test `--query-builder`

Generate a single entity with Spatie QueryBuilder:

```bash
php artisan make:fullapi Article --fields=title:string,content:text,published_at:datetime --query-builder
```

**Expected:**
- `ArticleService::getAll()` uses `QueryBuilder::for(Article::class)`
- Service defines `allowedFilters` and `allowedSorts`
- `ArticleController::index()` calls `service->getAll()` (no manual filtering)
- Generated code includes `use Spatie\QueryBuilder\QueryBuilder;`

Verify:

```bash
grep -n 'QueryBuilder::for' app/Services/ArticleService.php
grep -n 'allowedFilters' app/Services/ArticleService.php
grep -n 'allowedSorts' app/Services/ArticleService.php
```

Test the endpoint with a request:

```bash
# Start the dev server (in another terminal)
php artisan serve &

# Create a test article (use tinker or a POST to /api/articles)
php artisan tinker
>>> Article::create(['title' => 'Test', 'content' => 'Content', 'published_at' => now()])

# Test filtering and sorting
curl 'http://localhost:8000/api/articles?filter[title]=Test&sort=-created_at'
```

---

### 1.5 Verify migrations and routing

Run migrations (should succeed without FK errors):

```bash
php artisan migrate:fresh --seed 2>&1 | grep -i error
```

List all routes:

```bash
php artisan route:list | grep -E 'api/(categories|posts|tags|articles)'
```

**Expected:** Full REST routes (GET, POST, PUT, DELETE) for each resource.

---

## Part 2: VS Code Extension Testing (v0.7.0)

### 2.1 Install the extension

The extension VSIX is located at:
```
D:\laravel-api-generator-vscode\laravel-api-generator-0.7.0.vsix
```

**In VS Code:**
1. Open the Extensions sidebar (Ctrl+Shift+X / Cmd+Shift+X)
2. Click the `...` menu at the top
3. Select **"Install from VSIX..."**
4. Navigate to and select `laravel-api-generator-0.7.0.vsix`
5. Reload VS Code if prompted

**Verify:** The Laravel API Generator sidebar appears with a `...` menu showing new commands.

---

### 2.2 Test the three new source commands

Open the test app in VS Code (`File > Open Folder > .../testapp`).

The sidebar **Laravel API Generator > Generated Entities** should show a `...` menu with:
- Generate Full API (existing)
- **Generate APIs from Database** (NEW)
- **Generate APIs from Schema File** (NEW)
- **Generate APIs from Mermaid Diagram** (NEW)
- ... + other existing buttons

#### 2.2.1 Generate APIs from Database

Click **Generate APIs from Database**:

1. ✅ The extension lists tables: `categories`, `posts`, `tags`, `post_tag`, `users` (+ system tables filtered out)
2. ✅ `users` is pre-unchecked to protect `User.php`
3. ✅ Select `categories`, `posts`, `tags` (uncheck `post_tag` — it's a pivot, should be auto-detected)
4. ✅ A second QuickPick appears:
   - [ ] Use Spatie QueryBuilder for index filtering & sorting
   - [ ] Also generate migration files (tables already exist)
5. ✅ Check the first option (QueryBuilder), uncheck migrations (they exist)
6. ✅ Click OK or press Enter
7. ✅ A progress notification shows "Generating APIs..."
8. ✅ After success: "APIs generated successfully."

**Verify:** Check that generated models have the QueryBuilder service stub (look for `allowedFilters` in `app/Services/CategoryService.php`, etc.)

#### 2.2.2 Generate APIs from Schema File

Click **Generate APIs from Schema File**:

1. ✅ If `api-schema.yaml` exists at the project root, a QuickPick offers:
   - Use api-schema.yaml (found at project root)
   - Browse for a schema file...
2. ✅ Select "Use api-schema.yaml"
3. ✅ A second QuickPick for options (same as above)
4. ✅ Uncheck both (avoid overwriting existing migrations)
5. ✅ Success notification

**Verify:** Models are generated (or refreshed if they already exist).

#### 2.2.3 Generate APIs from Mermaid Diagram

Click **Generate APIs from Mermaid Diagram**:

1. ✅ Open `blog.mmd` in the editor first
2. ✅ Click the command
3. ✅ A QuickPick offers:
   - Use current file (blog.mmd)
   - Browse for a Mermaid file...
4. ✅ Select "Use current file"
5. ✅ Options QuickPick
6. ✅ Success

**Verify:** Models (User, Post, Tag) are generated with correct relations.

---

### 2.3 Test the QueryBuilder checkbox in the form

In the generator panel (click **Generate Full API**):

1. ✅ A new checkbox appears in the Options section:
   - [ ] Auth (Sanctum)
   - [ ] Postman Collection
   - [ ] Soft Deletes
   - [ ] **Spatie QueryBuilder** (NEW)
2. ✅ Check it, enter an entity name (e.g., `Product`), add a field, and click **Generate API**
3. ✅ The generated service uses QueryBuilder

**Verify:** `app/Services/ProductService.php` has `allowedFilters` and `allowedSorts`.

---

### 2.4 Test old-package detection

If testing with a Composer package older than v3.5 (for regression):

1. ✅ Click one of the three new source commands
2. ✅ The artisan command fails with "option does not exist"
3. ✅ A modal appears: "This feature requires nameless/laravel-api-generator >= 3.5. Update the Composer package..."
4. ✅ Click "Update Package"
5. ✅ A terminal window opens and runs `composer update nameless/laravel-api-generator -W`

---

## Part 3: Clean up and finalize

Once all tests pass:

```bash
# Back up the test app for reference (optional)
cp -r ~/test-api-generator/testapp ~/test-api-generator/testapp_v35_final

# Clean up
rm -rf ~/test-api-generator/testapp
```

---

## Checklist for sign-off

- [ ] Part 1.2: `--from-database` detects FKs, pivot tables, soft deletes
- [ ] Part 1.3: `--schema` and `--mermaid` generate correct entities and relations
- [ ] Part 1.4: `--query-builder` flag produces QueryBuilder code (service + controller)
- [ ] Part 1.5: Migrations run without FK errors; routes are registered
- [ ] Part 2.2.1: Extension's "Generate APIs from Database" works (QuickPicks, options, success)
- [ ] Part 2.2.2: Extension's "Generate APIs from Schema File" works (auto-detect or browse)
- [ ] Part 2.2.3: Extension's "Generate APIs from Mermaid Diagram" works (current file or browse)
- [ ] Part 2.3: QueryBuilder checkbox in the form UI works
- [ ] Part 2.4: Old-package detection and update prompt work

---

## Notes

- **Path repo:** Composer uses symlinks by default, so changes to the package source are reflected immediately. No need to `composer update` after code changes—just modify the source and artisan will pick up the new code.
- **Database:** SQLite file-based is easiest for testing; no MySQL/PostgreSQL setup needed.
- **Clean slate:** If something goes wrong mid-test, run `php artisan migrate:fresh --seed` to reset.
- **Extension reload:** If the extension caches something, reload VS Code (Cmd+R / Ctrl+R).
