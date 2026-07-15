<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\PendingCommand;

class FromDatabaseCommandTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Category', 'Post', 'Tag'];

    protected array $generatedTables = ['categories', 'posts', 'tags', 'post_tag'];

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content')->nullable();
            $table->foreignId('category_id')->constrained('categories');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained('posts');
            $table->foreignId('tag_id')->constrained('tags');
        });
    }

    /** @test */
    public function it_generates_apis_from_the_existing_database(): void
    {
        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', ['--from-database' => true]);
        $result->expectsOutputToContain('API generation completed successfully');
        $result->assertSuccessful();
        $result->run();

        foreach (['Category', 'Post', 'Tag'] as $entity) {
            $this->assertFileExists(app_path("Models/{$entity}.php"), "{$entity} model");
            $this->assertFileExists(app_path("Http/Controllers/{$entity}Controller.php"));
            $this->assertFileExists(app_path("Services/{$entity}Service.php"));
        }
    }

    /** @test */
    public function it_detects_relationships_soft_deletes_and_pivot_tables(): void
    {
        /** @var PendingCommand $command */
        $command = $this->artisan('make:fullapi', ['--from-database' => true]);
        $command->run();

        $post = (string) file_get_contents(app_path('Models/Post.php'));

        // FK column → belongsTo, pivot table → belongsToMany
        $this->assertStringContainsString('belongsTo(Category::class', $post);
        $this->assertStringContainsString('belongsToMany(Tag::class', $post);
        // deleted_at column → SoftDeletes
        $this->assertStringContainsString('SoftDeletes', $post);

        // Inverse hasMany on the parent
        $category = (string) file_get_contents(app_path('Models/Category.php'));
        $this->assertStringContainsString('hasMany(Post::class', $category);

        // The pivot table is not generated as an entity
        $this->assertFileDoesNotExist(app_path('Models/PostTag.php'));
    }

    /** @test */
    public function it_skips_migrations_and_the_users_table_by_default(): void
    {
        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', ['--from-database' => true]);
        $result->expectsOutputToContain('users table is skipped');
        $result->run();

        // Tables already exist: no migrations generated
        $this->assertSame([], $this->migrationsFor('posts'));

        // users is protected: no API generated for it
        $this->assertFileDoesNotExist(app_path('Http/Controllers/UserController.php'));
    }

    /** @test */
    public function it_generates_migrations_when_requested(): void
    {
        /** @var PendingCommand $command */
        $command = $this->artisan('make:fullapi', ['--from-database' => true, '--with-migrations' => true]);
        $command->run();

        $this->assertNotEmpty($this->migrationsFor('posts'));
        $this->assertNotEmpty($this->migrationsFor('post_tag'));
    }

    /** @test */
    public function it_limits_generation_to_the_requested_tables(): void
    {
        /** @var PendingCommand $command */
        $command = $this->artisan('make:fullapi', ['--from-database' => true, '--tables' => 'categories']);
        $command->run();

        $this->assertFileExists(app_path('Models/Category.php'));
        $this->assertFileDoesNotExist(app_path('Models/Post.php'));
    }
}
