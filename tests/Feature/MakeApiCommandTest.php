<?php

namespace nameless\CodeGenerator\Tests\Feature;

use nameless\CodeGenerator\Tests\TestCase;

class MakeApiCommandTest extends TestCase
{
    /** @test */
    public function it_can_create_api_files(): void
    {
        // Arrange
        $name = 'Post';
        $fields = 'title:string,content:text,published:boolean';

        // Act
        /** @var \Illuminate\Testing\PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => $name,
            '--fields' => $fields
        ]);
        $result->assertSuccessful();

        // Assert
        $this->assertFileExists(app_path("Models/{$name}.php"));
        $this->assertFileExists(app_path("Http/Controllers/{$name}Controller.php"));
        $this->assertFileExists(app_path("Services/{$name}Service.php"));
        $this->assertFileExists(app_path("DTO/{$name}DTO.php"));
    }

    /** @test */
    public function it_requires_fields_option(): void
    {
        /** @var \Illuminate\Testing\PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => 'Post'
        ]);
        $result->assertFailed();
    }

    /** @test */
    public function it_creates_valid_model_with_fillable(): void
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
        $modelContent = file_get_contents($modelPath);
        $this->assertNotFalse($modelContent, "Failed to read model file: {$modelPath}");
        $this->assertStringContainsString(
            "protected \$fillable = ['title', 'content'];",
            $modelContent
        );
    }
}
