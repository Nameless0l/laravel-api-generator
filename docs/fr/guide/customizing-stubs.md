# Personnaliser les stubs

Chaque fichier généré provient d'un template éditable. Si le style de code par défaut n'est pas le vôtre, changez les templates — pas les fichiers générés.

## Publier les stubs

```bash
php artisan vendor:publish --tag=api-generator-stubs
```

Cela copie chaque `.stub` dans `stubs/vendor/laravel-api-generator/`. Le `StubLoader` vérifie toujours ce dossier d'abord et retombe sur les défauts du package, donc vous ne surchargez **que les stubs dont vous avez besoin**.

## Valider vos personnalisations

`api-generator:validate-stubs` vérifie que chaque `{{placeholder}}` requis est toujours présent, pour que la génération ne puisse jamais produire silencieusement du code cassé :

```bash
php artisan api-generator:validate-stubs
php artisan api-generator:validate-stubs --json   # lisible machine, code de sortie 1 en cas d'erreur
```

Branchez la forme `--json` dans votre CI pour attraper les stubs cassés avant qu'ils n'atteignent une machine. L'[extension VS Code](/fr/guide/extension/quick-actions) lance cette validation automatiquement avant chaque génération.

## Étendre le générateur

Besoin d'un tout nouveau type d'artefact ? Créez un générateur personnalisé en étendant `AbstractGenerator` :

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
        return 'custom'; // charge stubs/custom.stub
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

Enregistrez-le dans votre service provider et il sera appelé automatiquement pendant la génération.

<!-- VIDEO #7 (YouTube) — décommenter et renseigner VIDEO_ID quand la vidéo est en ligne, puis la placer en haut de page :
<div style="position:relative;padding-bottom:56.25%;height:0;margin:16px 0">
  <iframe src="https://www.youtube-nocookie.com/embed/VIDEO_ID" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" title="Zéro lock-in : personnaliser les stubs puis désinstaller" allowfullscreen loading="lazy"></iframe>
</div>
-->
