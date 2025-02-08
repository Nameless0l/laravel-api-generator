<?php

namespace nameless\CodeGenerator\Console\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;



class MakeApi extends Command
{
    protected $signature = 'make:fullapi {name?} {--fields=}';
    protected $description = 'Créer un modèle, migration, controller, resource, request, factory, seeder et DTO avec champs dynamiques';
    protected $classes;
    private $nameLower;
    const BASE_STUB_PATH = "vendor/nameless/laravel-api-generator/stubs/";
    public function handle()
    {
        $name = $this->argument('name');
        $fields = $this->option('fields');
        $nameLower = strtolower($name);
        $this->nameLower = $nameLower;

        if (empty($name)) {
            $this->callDiagramsMethods();
            return;
        }
        if (!$fields) {
            $this->error('Vous devez spécifier des champs avec l\'option --fields="champ1:type1,champ2:type2"');
            return;
        }
        $this->callDefaulttMethods($name, $fields);
    }

    private function callDefaulttMethods($name, $fields)
    {

        $fieldsArray = $this->parseFields($fields);
        $pluralName = Str::plural(Str::lower($name));

        info("Création des fichiers pour l'API: {$name}");
        $route = "Route::apiResource('{$this->nameLower}', App\Http\Controllers\ProductController::class);";
        $apiFilePath = base_path('routes/api.php');
        File::append($apiFilePath, PHP_EOL . $route);


        // Créer le modèle avec la migration et le factory
        Artisan::call("make:model {$name} -mf");
        info("Modèle, migration et factory créés.");

        // Mettre à jour le modèle avec les fillable
        $this->updateModel($name, $fieldsArray);
        $this->info("Modèle mis à jour avec les fillable.");

        // Ajouter les champs dans la migration
        $this->updateMigration($name, $fieldsArray);

        // Créer le service
        $this->createService($name, $this->nameLower);
        $this->info("Service créé.");

        // Créer la policy
        Artisan::call("make:policy {$name}Policy --model={$name}");
        $this->updatePolicy($name);
        $this->info("Policy créée et configurée.");

        // Créer le contrôleur avec CRUD
        Artisan::call("make:controller {$name}Controller");
        $this->info("Contrôleur API créé.");

        // Créer la resource
        Artisan::call("make:resource {$name}Resource");
        $this->info("Resource créée.");

        $this->updateResource($name, $fieldsArray);
        // Créer la requête (FormRequest)
        Artisan::call("make:request {$name}Request");
        $this->updateRequest($name, $fieldsArray);
        $this->info("Requête créée.");

        // Créer le seeder
        Artisan::call("make:seeder {$name}Seeder");
        $this->updateSeeder($name, $fieldsArray);
        $this->info("Seeder créé.");

        // Créer un DTO
        $this->createDTO($name, $fieldsArray);
        $this->info("DTO créé.");

        // Ajouter les méthodes CRUD au contrôleur
        $this->addCrudToController($name, $this->nameLower, $pluralName);

        // Mettre à jour AuthServiceProvider
        $this->updateAuthServiceProvider($name);

        $this->info("API complète créée pour : {$name} !");
        $this->updateFactory($name, $fieldsArray);
    }
    private function callDiagramsMethods()
    {
        $this->warn("Aucun nom fourni. Utilisation du nom par défaut : Product");
        $jsonFilePath = base_path('class_data.json');
        if (!file_exists($jsonFilePath)) {
            $this->error("Le fichier class_data.json est introuvable.");
            return;
        }

        $this->info("Lecture du fichier JSON...");
        $jsonData = file_get_contents($jsonFilePath);
        $this->classes = json_decode($jsonData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Erreur de décodage JSON : " . json_last_error_msg());
            return;
        }

        $this->info("Extraction des données JSON...");
        $this->jsonExtractionToArray();

        $this->info("Génération des API avec Artisan...");
        $this->runFullApiWithDiagram();
        return;
    }

    /**
     * Extraire et formater les données JSON dans un tableau compatible.
     */
    public function jsonExtractionToArray()
    {
        $this->classes = array_map(function ($class) {
            return [
                'name' => ucfirst($class['name']),
                'attributes' => array_map(function ($attribute) {
                    return [
                        'name' => $attribute['name'],
                        '_type' => match (strtolower($attribute['_type'])) {
                            'integer' => 'int',
                            'bigint' => 'int',
                            'str', 'text' => 'string',
                            'date', 'datetime', 'timestamp', 'time' => '\DateTimeInterface',
                            'boolean' => 'bool',
                            default => $attribute['_type'],
                        },
                    ];
                }, $class['attributes']),
            ];
        }, $this->classes);
    }

    /**
     * Parcourir les classes et exécuter les commandes Artisan pour générer les API.
     */
    public function runFullApiWithDiagram()
    {
        foreach ($this->classes as $class) {
            $className = ucfirst($class['name']);
            $fields = [];

            foreach ($class['attributes'] as $attribute) {
                $fields[] = "{$attribute['name']}:{$attribute['_type']}";
            }

            $fieldsString = implode(',', $fields);

            try {
                $this->callDefaulttMethods($className, $fieldsString);
                // Artisan::call("make:fullapi {$className} --fields={$fieldsString}");
                $this->info("API pour la classe $className générée avec succès !");
            } catch (\Exception $e) {
                $this->error("Erreur lors de la génération de l'API pour la classe $className : " . $e->getMessage());
            }
        }
    }

    private function updateFactory($name, $fieldsArray)
    {
        $factoryPath = database_path("factories/{$name}Factory.php");

        if (!file_exists($factoryPath)) {
            $this->error("Le fichier Factory pour {$name} n'existe pas.");
            return;
        }

        $factoryFile = file_get_contents($factoryPath);

        $fields = [];
        foreach ($fieldsArray as $field => $type) {
            $value = match ($type) {
                'string' => "fake()->word()",
                'integer' => "fake()->randomNumber()",
                'boolean' => "fake()->boolean()",
                'text' => "fake()->sentence()",
                'uuid' => "fake()->uuid()",
                'UUID' => "fake()->uuid()",
                'date', 'datetime', 'timestamp', 'time' => "fake()->dateTime()",
                default => "fake()->word()"
            };
            $fields[] = "'{$field}' => {$value}";
        }

        $fieldsContent = implode(",\n            ", $fields);

        $factoryFile = preg_replace(
            "/return \[.*?\];/s",
            "return [\n            {$fieldsContent}\n        ];",
            $factoryFile
        );

        file_put_contents($factoryPath, $factoryFile);

        $this->info("Factory mis à jour : {$factoryPath}");
    }

    private function parseFields($fields)
    {
        $fieldsArray = [];
        foreach (explode(',', $fields) as $field) {
            [$name, $type] = explode(':', $field);
            $fieldsArray[$name] = $type;
        }
        return $fieldsArray;
    }

    private function updateMigration($name, $fieldsArray)
    {
        $pluralName = Str::plural(Str::snake($name));
        $migrations = glob(database_path("migrations/*_create_{$pluralName}_table.php"));

        if (empty($migrations)) {
            $this->error("Impossible de trouver une migration pour {$name}.");
            return;
        }

        $migrationPath = $migrations[0]; // On suppose qu'il n'y a qu'une seule migration correspondante, si c'est pas le cas c'est pas mon problème heinn 😃
        $migrationFile = file_get_contents($migrationPath);

        $fieldLines = '';
        foreach ($fieldsArray as $field => $type) {
            $type = match ($type) {
                'string' => 'string',
                'integer' => 'integer',
                'boolean' => 'boolean',
                'text' => 'text',
                'date', 'datetime', 'timestamp', 'time' => 'timestamp',
                'uuid' => 'uuid',
                'UUID' => 'uuid',
                default => 'string'
            };
            $fieldLines .= "\$table->{$type}('{$field}')->nullable();\n            ";
        }

        // Injecter les champs dans la migration
        $pattern = '/Schema::create\([\'"]' . preg_quote($pluralName, '/') . '[\'"],\s*function\s*\(Blueprint\s*\$table\)\s*\{.*?\$table->timestamps\(\);/s';
        $replacement = "Schema::create('{$pluralName}', function (Blueprint \$table) {\n            \$table->id();\n            {$fieldLines}\$table->timestamps();";

        $migrationFile = preg_replace($pattern, $replacement, $migrationFile);

        // Sauvegarder la migration mise à jour
        file_put_contents($migrationPath, $migrationFile);

        $this->info("Migration mise à jour : {$migrationPath}");
    }

    private function updateRequest($name, $fieldsArray)
    {
        $requestPath = app_path("Http/Requests/{$name}Request.php");
        $requestFile = file_get_contents($requestPath);

        // Ajouter la méthode authorize qui retourne true
        $requestFile = str_replace(
            "public function authorize(): bool\n    {\n        return false;\n    }",
            "public function authorize(): bool\n    {\n        return true;\n    }",
            $requestFile
        );

        $rules = '';
        foreach ($fieldsArray as $field => $type) {
            $rule = match ($type) {
                'string' => "'string|max:255'",
                'integer' => "'integer'",
                'boolean' => "'boolean'",
                'text' => "'string'",
                'uuid' => "'uuid'",
                'UUID' => "'uuid'",
                'date' => "'date'",
                'datetime' => "'date'",
                'timestamp' => "'date'",
                default => "'required'"
            };
            $rules .= "'{$field}' => {$rule},\n            ";
        }

        $requestFile = preg_replace(
            "/public function rules\(\).*?\{.*?\n.*?\}/s",
            "public function rules()\n    {\n        return [\n            {$rules}\n        ];\n    }",
            $requestFile
        );

        file_put_contents($requestPath, $requestFile);
    }

    private function addCrudToController($name, $nameLower, $pluralName)
    {
        $controllerPath = app_path("Http/Controllers/{$name}Controller.php");

        if (!file_exists($controllerPath)) {
            $this->error("Le contrôleur {$name}Controller n'existe pas.");
            return;
        }

        // Lire le contenu original
        $content = file_get_contents($controllerPath);

        // Ajouter l'import de Controller si pas déjà présent
        if (strpos($content, 'use App\Http\Controllers\Controller;') === false) {
            $content = str_replace(
                'namespace App\Http\Controllers;',
                "namespace App\Http\Controllers;\n\nuse App\Http\Controllers\Controller;",
                $content
            );
        }

        // Remplacer la déclaration de classe
        $content = preg_replace(
            '/class\s+' . $name . 'Controller\s*{/',
            'class ' . $name . 'Controller extends Controller {',
            $content
        );

        $methods = <<<EOD

        private {$name}Service \$service;

        public function __construct({$name}Service \$service)
        {
            \$this->service = \$service;
        }

        public function index()
        {
            \${$nameLower} = \$this->service->getAll();
            return {$name}Resource::collection(\${$nameLower});
        }

        public function store({$name}Request \$request)
        {
            \$dto = {$name}DTO::fromRequest(\$request);
            \${$nameLower} = \$this->service->create(\$dto);
            return new {$name}Resource(\${$nameLower});
        }

        public function show({$name} \${$nameLower})
        {
            return new {$name}Resource(\${$nameLower});
        }

        public function update({$name}Request \$request, {$name} \${$nameLower})
        {
            \$dto = {$name}DTO::fromRequest(\$request);
            \$updated{$name} = \$this->service->update(\${$nameLower}, \$dto);
            return new {$name}Resource(\$updated{$name});
        }

        public function destroy({$name} \${$nameLower})
        {
            \$this->service->delete(\${$nameLower});
            return response(null, 204);
        }

    EOD;


        // Ajouter les imports nécessaires
        $content = str_replace(
            'use Illuminate\Http\Request;',
            "use App\\Http\\Requests\\{$name}Request;\nuse App\\Models\\{$name};\nuse App\\Http\\Resources\\{$name}Resource;\nuse App\\Services\\{$name}Service;\nuse App\\DTO\\{$name}DTO;\nuse Illuminate\\Http\\Response;",
            $content
        );

        $content = str_replace('}', $methods . "\n}", $content);

        file_put_contents($controllerPath, $content);

        $this->info("CRUD ajouté au contrôleur : {$controllerPath}");
    }

    private function updateSeeder($name, $fieldsArray)
    {
        $seederPath = database_path("seeders/{$name}Seeder.php");
        $seederFile = file_get_contents($seederPath);

        $factoryFields = [];
        foreach ($fieldsArray as $field => $type) {
            $value = match ($type) {
                'string' => "fake()->word()",
                'integer' => "fake()->randomNumber()",
                'boolean' => "fake()->boolean()",
                'uuid','UUID'=>"fake()->uuid()",
                'bigint' => "fake()->randomNumber()",
                'date', 'datetime', 'timestamp', 'time' => "fake()->dateTime()",
                'text' => "fake()->paragraph()",
                default => "fake()->word()"
            };
            $factoryFields[] = "'{$field}' => {$value}";
        }

        $factoryContent = implode(",\n            ", $factoryFields);
        $seederFile = preg_replace(
            "/public function run\(\).*?\{.*?\}/s",
            "public function run()\n    {\n        \\App\\Models\\{$name}::factory(10)->create();\n}",
            $seederFile
        );

        file_put_contents($seederPath, $seederFile);
    }

    private function createDTO($name, $fieldsArray)
    {
        $dtoPath = app_path("DTO/{$name}DTO.php");
        if (!file_exists(app_path('DTO'))) {
            mkdir(app_path('DTO'), 0755, true);
        }

        $attributes = '';
        foreach ($fieldsArray as $field => $type) {
            if ($type == 'text') {
                $type = 'string';
            }
            if ($type == 'boolean') {
                $type = 'bool';
            }
            if ($type == 'integer' || $type == 'bigint') {
                $type = 'int';
            }
            if ($type == 'date' || $type == 'datetime' || $type == 'timestamp' || $type == 'time') {
                $type = '\DateTimeInterface';
            }
            $attributes .= "public? $type \${$field},\n        ";
        }
        $atributsFromRequest = '';
        foreach ($fieldsArray as $field => $type) {
            if ($type == 'date' || $type == 'datetime' || $type == 'timestamp' || $type == 'time') {
                $atributsFromRequest .= "$field : \Carbon\Carbon::parse(\$request->get('{$field}')),\n            ";
            } else {
                $atributsFromRequest .= "$field : \$request->get('{$field}'),\n            ";
            }
        }
        $attributes = rtrim($attributes, ', ');
        $atributsFromRequest = rtrim($atributsFromRequest, ', ');

        $content = <<<EOD
<?php



namespace App\DTO;

use App\Http\Requests\\{$name}Request;
readonly class {$name}DTO
{

    public function __construct(
        {$attributes}
    ) {}

    public static function fromRequest({$name}Request \$request): self
    {
        return new self(
            {$atributsFromRequest}
        );
    }
}
EOD;

        file_put_contents($dtoPath, $content);
    }

    private function createService($name, $nameLower)
    {
        if (!file_exists(app_path('Services'))) {
            mkdir(app_path('Services'), 0755, true);
        }

        $servicePath = app_path("Services/{$name}Service.php");
        $content = <<<EOD
<?php

namespace App\Services;

use App\Models\\{$name};
use App\DTO\\{$name}DTO;

class {$name}Service
{
    public function getAll()
    {
        return {$name}::all();
    }

    public function create({$name}DTO \$dto)
    {
        return {$name}::create((array) \$dto);
    }

    public function find(\$id)
    {
        return {$name}::findOrFail(\$id);
    }

    public function update({$name} \${$nameLower}, {$name}DTO \$dto)
    {
        \${$nameLower}->update((array) \$dto);
        return \${$nameLower};
    }

    public function delete({$name} \${$nameLower})
    {
        return \${$nameLower}->delete();
    }
}
EOD;

        file_put_contents($servicePath, $content);
    }

    private function updatePolicy($name)
    {
        $policyPath = app_path("Policies/{$name}Policy.php");

        if (!file_exists($policyPath)) {
            $this->error("Le fichier Policy pour {$name} n'existe pas.");
            return;
        }

        $content = <<<EOD
<?php

namespace App\Policies;

use App\Models\\{$name};
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class {$name}Policy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  User  \$user
     * @return Response|bool
     */
    public function viewAny(User \$user): Response|bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  User  \$user
     * @param  {$name}  \${$this->nameLower}
     * @return Response|bool
     */
    public function view(User \$user, {$name} \${$this->nameLower}): Response|bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  User  \$user
     * @return Response|bool
     */
    public function create(User \$user): Response|bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  User  \$user
     * @param  {$name}  \${$this->nameLower}
     * @return Response|bool
     */
    public function update(User \$user, {$name} \${$this->nameLower}): Response|bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  User  \$user
     * @param  {$name}  \${$this->nameLower}
     * @return Response|bool
     */
    public function delete(User \$user, {$name} \${$this->nameLower}): Response|bool
    {
        return true;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  User  \$user
     * @param  {$name}  \${$this->nameLower}
     * @return Response|bool
     */
    public function restore(User \$user, {$name} \${$this->nameLower}): Response|bool
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  User  \$user
     * @param  {$name}  \${$this->nameLower}
     * @return Response|bool
     */
    public function forceDelete(User \$user, {$name} \${$this->nameLower}): Response|bool
    {
        return true;
    }
}
EOD;

        file_put_contents($policyPath, $content);
    }

    private function updateAuthServiceProvider($name)
    {
        $providerPath = app_path('Providers/AuthServiceProvider.php');

        if (!file_exists($providerPath)) {
            $this->createAuthServiceProvider();
        }

        $content = file_get_contents($providerPath);

        // Ajouter l'import du modèle et de la policy
        $modelImport = "use App\Models\\{$name};";
        $policyImport = "use App\Policies\\{$name}Policy;";

        // Ajouter les imports après le namespace
        $content = preg_replace(
            '/namespace App\\\\Providers;/',
            "namespace App\\Providers;\n\n{$modelImport}\n{$policyImport}",
            $content
        );

        // Ajouter le mapping modèle-policy dans la propriété $policies
        $mapping = "        {$name}::class => {$name}Policy::class,";

        // Ajouter le mapping dans le tableau $policies
        if (strpos($content, 'protected $policies = [') !== false) {
            // Si le tableau existe déjà, ajouter le nouveau mapping
            $content = preg_replace(
                '/protected \$policies = \[(.*?)\]/s',
                "protected \$policies = [\n{$mapping}\$1\n    ]",
                $content
            );
        } else {
            // Si le tableau n'existe pas, le créer avec le mapping
            $content = preg_replace(
                '/class AuthServiceProvider extends ServiceProvider\s*{/',
                "class AuthServiceProvider extends ServiceProvider\n{\n    protected \$policies = [\n{$mapping}\n    ];",
                $content
            );
        }

        file_put_contents($providerPath, $content);
    }

    private function createAuthServiceProvider()
    {
        $providerPath = app_path('Providers/AuthServiceProvider.php');
        $content = <<<EOD
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected \$policies = [];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        \$this->registerPolicies();
    }
}
EOD;

        // Créer le répertoire Providers s'il n'existe pas
        if (!file_exists(app_path('Providers'))) {
            mkdir(app_path('Providers'), 0755, true);
        }

        file_put_contents($providerPath, $content);
        $this->info('AuthServiceProvider créé.');
    }

    private function updateModel($name, $fieldsArray)
    {
        $modelPath = app_path("Models/{$name}.php");

        if (!file_exists($modelPath)) {
            $this->error("Le modèle {$name} n'existe pas.");
            return;
        }

        $modelContent = file_get_contents($modelPath);

        $fillableFields = array_keys($fieldsArray);
        $fillableString = "'" . implode("', '", $fillableFields) . "'";

        $fillableProperty = "\n    protected \$fillable = [{$fillableString}];\n";

        $modelContent = preg_replace(
            '/(class\s+' . $name . '\s+extends\s+Model\s*{)/',
            "$1{$fillableProperty}",
            $modelContent
        );

        file_put_contents($modelPath, $modelContent);
    }

    private function updateResource($name, $fieldsArray)
    {
        $resourcePath = app_path("Http/Resources/{$name}Resource.php");
        if (!file_exists(app_path('Http/Resources'))) {
            mkdir(app_path('Http/Resources'), 0755, true);
        }

        $fieldsCode = '';
        foreach ($fieldsArray as $field => $type) {
            $fieldsCode .= "'{$field}' => \$this->{$field},\n            ";
        }

        // $stubPath = base_path(MakeApi::BASE_STUB_PATH . 'resource.stub');
        $stub = file_get_contents(base_path(MakeApi::BASE_STUB_PATH . 'resource.stub'));
        $template = str_replace(
            ['{{modelName}}', '{{fields}}'],
            [$name, $fieldsCode],
            $stub
        );
        file_put_contents($resourcePath, $template);
    }
}
