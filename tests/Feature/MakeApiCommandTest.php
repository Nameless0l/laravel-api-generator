<?php

namespace Nameless\LaravelCodeGenerator\Tests\Feature;

use nameless\LaravelCodeGenerator\Tests\TestCase;

// use Nameless\LaravelCodeGenerator\Tests\TestCase;

class MakeApiCommandTest extends TestCase
{
    /** @test */
    public function it_can_create_api_files()
    {
        // Arrange
        $name = 'Post';
        $fields = 'title:string,content:text,published:boolean';

        // Act
        $this->artisan('make:fullapi', [
            'name' => $name,
            '--fields' => $fields
        ])->assertSuccessful();

        // Assert
        $this->assertFileExists(app_path("Models/{$name}.php"));
        $this->assertFileExists(app_path("Http/Controllers/{$name}Controller.php"));
        $this->assertFileExists(app_path("Services/{$name}Service.php"));
        $this->assertFileExists(app_path("DTO/{$name}DTO.php"));
    }

    /** @test */
    public function it_requires_fields_option()
    {
        $this->artisan('make:fullapi', [
            'name' => 'Post'
        ])->assertFailed();
    }

    /** @test */
    public function it_creates_valid_model_with_fillable()
    {
        // Arrange
        $name = 'Post';
        $fields = 'title:string,content:text';

        // Act
        $this->artisan('make:fullapi', [
            'name' => $name,
            '--fields' => $fields
        ]);

        // Assert
        $modelPath = app_path("Models/{$name}.php");
        $this->assertStringContainsString(
            "protected \$fillable = ['title', 'content'];",
            file_get_contents($modelPath)
        );
    }
}
