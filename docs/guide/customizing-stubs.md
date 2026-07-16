# Customizing Stubs

Every generated file comes from an editable template. If the default code style isn't yours, change the templates — not the generated files.

## Publish the stubs

```bash
php artisan vendor:publish --tag=api-generator-stubs
```

This copies every `.stub` into `stubs/vendor/laravel-api-generator/`. The `StubLoader` always checks this folder first and falls back to the package defaults, so you can override **only the stubs you need**.

## Validate your customizations

`api-generator:validate-stubs` checks that every required `{{placeholder}}` is still present, so generation can never silently produce broken code:

```bash
php artisan api-generator:validate-stubs
php artisan api-generator:validate-stubs --json   # machine-readable, exit code 1 on error
```

Wire the `--json` form into CI to catch broken stubs before they reach anyone's machine. The [VS Code extension](/guide/vscode-extension) runs this validation automatically before each generation.

## Extending the generator

Need a whole new artifact type? Create a custom generator by extending `AbstractGenerator`:

```php
use nameless\CodeGenerator\EntitiesGenerator\AbstractGenerator;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;

class CustomGenerator extends AbstractGenerator
{
    public function getType(): string
    {
        return 'Custom';
    }

    public function getOutputPath(EntityDefinition $definition): string
    {
        return app_path("Custom/{$definition->name}Custom.php");
    }

    protected function getStubName(): string
    {
        return 'custom'; // loads stubs/custom.stub
    }

    protected function getReplacements(EntityDefinition $definition): array
    {
        return ['modelName' => $definition->name];
    }

    protected function generateContent(EntityDefinition $definition): string
    {
        return $this->processStub($definition);
    }
}
```

Register it in your service provider and it is called automatically during generation.
