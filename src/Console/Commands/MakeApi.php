<?php

namespace nameless\CodeGenerator\Console\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;



class MakeApi extends Command
{
    protected $signature = 'make:fullapi {name?} {--fields=}';
    protected $description = 'Créer un modèle, migration, controller, resource, request, factory, seeder et DTO avec champs dynamiques et relations';
    protected $classes;
    private $nameLower;
    const BASE_STUB_PATH = "vendor/nameless/laravel-api-generator/stubs/";
    public function handle()
    {
        $name = $this->argument('name');

        if (empty($this->argument('name'))) {
            $this->callDiagramsMethods();
            return;
        }
        $fields = $this->option('fields');
        $this->nameLower = strtolower($name);
        if (!$fields) {
            $this->error('Vous devez spécifier des champs avec l\'option --fields="champ1:type1,champ2:type2"');
            return;
        }
        $this->callDefaulttMethods($name, $fields);
    }

    private function callDefaulttMethods($name, $fields, ?array $classData = null)
    {

        $fieldsArray = $this->parseFields($fields);
        $pluralName = Str::plural(Str::lower($name));
        $this->nameLower = Str::lower($name);

        info("Création des fichiers pour l'API: {$name}");
        $route = "Route::apiResource('{$pluralName}', App\Http\Controllers\\{$name}Controller::class);";
        $apiFilePath = base_path('routes/api.php');
        $phpHeader = "<?php\n\nuse Illuminate\Support\Facades\Route;\n\n";

        if (!File::exists($apiFilePath)) {
            File::put($apiFilePath, $phpHeader);
        }

        $existingRoutes = File::get($apiFilePath);
        if (!str_contains($existingRoutes, $route)) {
            File::append($apiFilePath, PHP_EOL . $route);
        }

        // Créer le modèle avec la migration et le factory
        Artisan::call("make:model {$name} -mf");
        info("Modèle, migration et factory créés.");

        // Mettre à jour le modèle avec les fillable et les relations
        $this->updateModel($name, $fieldsArray, $classData);
        $this->info("Modèle mis à jour avec les fillable et les relations.");

        // Ajouter les champs et les clés étrangères dans la migration
        $this->updateMigration($name, $fieldsArray, $classData);

        // Créer les migrations pour les tables pivots (ManyToMany)
        $this->createPivotMigrations($name, $classData);

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
        $this->warn("Aucun nom fourni. Utilisation par défaut du fichier JSON...");
        $jsonFilePath = base_path('class_data.json');
        if (!file_exists($jsonFilePath)) {
            $this->error("Le fichier class_data.json est introuvable.");
            return;
        }

        $this->info("Lecture du fichier JSON...");
        $jsonData = file_get_contents($jsonFilePath);
        // Le JSON peut être un tableau d'objets ou un seul objet avec une clé "data"
        $rawClasses = json_decode($jsonData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Erreur de décodage JSON : " . json_last_error_msg());
            return;
        }
        
        // S'assurer que nous travaillons avec un tableau
        if (isset($rawClasses['data']) && is_array($rawClasses['data'])) {
             $this->classes = [$rawClasses];
        } else {
             $this->classes = $rawClasses;
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
        $this->classes = array_map(function ($classData) {
            // Gérer les deux formats JSON (avec ou sans l'enveloppe 'data')
            $class = isset($classData['data']) ? $classData['data'] : $classData;
    
            return [
                'name' => ucfirst($class['name']),
                'parent' => isset($class['parent']) ? ucfirst($class['parent']) : null,
                'attributes' => array_map(function ($attribute) {
                    return [
                        'name' => $attribute['name'],
                        '_type' => match (strtolower($attribute['_type'])) {
                            'integer', 'long', 'int' => 'int',
                            'bigint' => 'int',
                            'str', 'string', 'text', 'java.time.offsetdatetime', 'java.time.localdate' => 'string',
                            'boolean' => 'bool',
                            'java.math.bigdecimal' => 'float',
                            'java.util.map' => 'json',
                            default => strtolower($attribute['_type']),
                        },
                    ];
                }, $class['attributes'] ?? []),
                'oneToOneRelationships' => $class['oneToOneRelationships'] ?? [],
                'manyToOneRelationships' => $class['manyToOneRelationships'] ?? [],
                'oneToManyRelationships' => $class['oneToManyRelationships'] ?? [],
                'manyToManyRelationships' => $class['manyToManyRelationships'] ?? [],
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
                // Passer le tableau complet de la classe, qui inclut maintenant les relations
                $this->callDefaulttMethods($className, $fieldsString, $class);
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
                'integer', 'int' => "fake()->randomNumber()",
                'boolean', 'bool' => "fake()->boolean()",
                'text' => "fake()->sentence()",
                'uuid', 'UUID' => "fake()->uuid()",
                'float' => "fake()->randomFloat(2, 1, 1000)",
                'json' => "json_encode(['key' => 'value'])",
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
            $parts = explode(':', $field);
            if(count($parts) == 2){
                $fieldsArray[$parts[0]] = strtolower($parts[1]);
            }
        }
        return $fieldsArray;
    }

    private function updateMigration($name, $fieldsArray, ?array $classData = null)
    {
        $pluralName = Str::plural(Str::snake($name));
        $migrations = glob(database_path("migrations/*_create_{$pluralName}_table.php"));

        if (empty($migrations)) {
            $this->error("Impossible de trouver une migration pour {$name}.");
            return;
        }

        $migrationPath = $migrations[0]; 
        $migrationFile = file_get_contents($migrationPath);

        $fieldLines = '';
        foreach ($fieldsArray as $field => $type) {
             $type = match ($type) {
                'string' => 'string',
                'integer', 'int' => 'integer',
                'boolean', 'bool' => 'boolean',
                'text' => 'text',
                'float' => 'decimal',
                'json' => 'json',
                'date', 'datetime', 'timestamp', 'time' => 'timestamp',
                'uuid', 'UUID' => 'uuid',
                default => 'string'
            };
            if($type === 'decimal'){
                 $fieldLines .= "\$table->{$type}('{$field}', 8, 2)->nullable();\n            ";
            } else {
                 $fieldLines .= "\$table->{$type}('{$field}')->nullable();\n            ";
            }
        }
        
        $foreignKeyLines = '';
        if ($classData) {
            // Les relations Many-to-One et One-to-One (côté "enfant") impliquent une clé étrangère
            $relations = array_merge($classData['manyToOneRelationships'], $classData['oneToOneRelationships']);
            foreach ($relations as $relation) {
                $foreignKeyColumn = Str::snake($relation['role']) . '_id';
                // Assumer que le nom de la table est le pluriel du nom du modèle lié
                $relatedTable = Str::plural(Str::snake($relation['comodel']));
                $foreignKeyLines .= "\$table->foreignId('{$foreignKeyColumn}')->nullable()->constrained('{$relatedTable}')->onDelete('set null');\n            ";
            }
        }

        // Injecter les champs dans la migration
        $pattern = '/\$table->id\(\);(.*?)\$table->timestamps\(\);/s';
        $replacement = "\$table->id();\n            {$fieldLines}{$foreignKeyLines}\$table->timestamps();";
        
        if(preg_match($pattern, $migrationFile)) {
            $migrationFile = preg_replace($pattern, $replacement, $migrationFile);
        } else {
             $pattern = '/Schema::create\([\'"]' . preg_quote($pluralName, '/') . '[\'"],\s*function\s*\(Blueprint\s*\$table\)\s*\{.*?id\(\);/s';
             $replacement = "Schema::create('{$pluralName}', function (Blueprint \$table) {\n            \$table->id();\n            {$fieldLines}{$foreignKeyLines}";
             $migrationFile = preg_replace($pattern, $replacement, $migrationFile);
        }

        file_put_contents($migrationPath, $migrationFile);
        $this->info("Migration mise à jour : {$migrationPath}");
    }

    private function createPivotMigrations($name, ?array $classData = null)
    {
        if (!$classData || empty($classData['manyToManyRelationships'])) {
            return;
        }

        $modelNameSingular = Str::snake($name);

        foreach ($classData['manyToManyRelationships'] as $relation) {
            $relatedModel = ucfirst($relation['comodel']);
            $relatedModelSingular = Str::snake($relatedModel);

            $tableParts = [$modelNameSingular, $relatedModelSingular];
            sort($tableParts);
            $pivotTableName = implode('_', $tableParts);

            $existingMigrations = File::glob(database_path("migrations/*_create_{$pivotTableName}_table.php"));
            if (!empty($existingMigrations)) {
                $this->warn("La migration de la table pivot '{$pivotTableName}' existe déjà. Ignorée.");
                continue;
            }

            $migrationName = "create_{$pivotTableName}_table";
            Artisan::call('make:migration', ['name' => $migrationName]);
            $this->info("Migration de la table pivot créée : {$migrationName}");

            $migrationFile = last(File::glob(database_path("migrations/*_{$migrationName}.php")));

            if ($migrationFile) {
                $upMethodContent = "
        Schema::create('{$pivotTableName}', function (Blueprint \$table) {
            \$table->primary(['{$tableParts[0]}_id', '{$tableParts[1]}_id']);
            \$table->foreignId('{$tableParts[0]}_id')->constrained('" . Str::plural($tableParts[0]) . "')->onDelete('cascade');
            \$table->foreignId('{$tableParts[1]}_id')->constrained('" . Str::plural($tableParts[1]) . "')->onDelete('cascade');
            \$table->timestamps();
        });";

                $migrationContent = file_get_contents($migrationFile);
                $migrationContent = preg_replace(
                    '/(public function up\(\): void\s*{)/s',
                    "$1" . $upMethodContent,
                    $migrationContent
                );

                file_put_contents($migrationFile, $migrationContent);
                $this->info("Migration de la table pivot mise à jour : {$migrationFile}");
            }
        }
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
            'string' => 'string|max:255',
            'integer', 'int' => 'integer',
            'boolean', 'bool' => 'boolean',
            'text' => 'string',
            'uuid', 'UUID' => 'uuid',
            'float' => 'numeric',
            'json' => 'json',
            'date', 'datetime', 'timestamp' => 'date',
            default => 'sometimes|string'
        };
        // CORRECTION : Pas de guillemets dans le match, on les ajoute ici
        $rules .= "'{$field}' => 'sometimes|{$rule}',\n            ";
    }

    $requestFile = preg_replace(
        "/public function rules\(\).*?\{.*?\n.*?\}/s",
        "public function rules(): array\n    {\n        return [\n            {$rules}\n        ];\n    }",
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
            \${$pluralName} = \$this->service->getAll();
            return {$name}Resource::collection(\${$pluralName});
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
        
        // Vider la classe avant d'ajouter les nouvelles méthodes
        $pattern = '/class ' . $name . 'Controller extends Controller\s*{[^}]*}/';
        $replacement = 'class ' . $name . 'Controller extends Controller {' . $methods . '}';
        $content = preg_replace($pattern, $replacement, $content, 1);


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
            "/public function run\(\): void\s*{[^}]*}/s",
            "public function run(): void\n    {\n        \\App\\Models\\{$name}::factory(10)->create();\n    }",
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
            if ($type == 'float'){
                 $type = 'float';
            }
            if($type == 'json'){
                 $type = 'array';
            }
            if ($type == 'date' || $type == 'datetime' || $type == 'timestamp' || $type == 'time') {
                $type = '\DateTimeInterface';
            }
            if($type == 'uuid' || $type == 'UUID'){
                $type = 'string';
            }
            
            $attributes .= "public ?{$type} \${$field},
        ";
        }
        $atributsFromRequest = '';
        foreach ($fieldsArray as $field => $type) {
            if ($type == 'date' || $type == 'datetime' || $type == 'timestamp' || $type == 'time') {
                $atributsFromRequest .= "$field: \$request->filled('{$field}') ? \Carbon\Carbon::parse(\$request->get('{$field}')) : null,\n            ";
            } else {
                $atributsFromRequest .= "$field: \$request->get('{$field}'),\n            ";
            }
        }
        $attributes = rtrim($attributes, ",
        ");
        $atributsFromRequest = rtrim($atributsFromRequest, ",
            ");

        $content = <<<EOD
<?php

namespace App\DTO;

use App\Http\Requests\\{$name}Request;
use Illuminate\Http\Request;

readonly class {$name}DTO
{

    public function __construct(
        {$attributes}
    ) {}

    public static function fromRequest(Request \$request): self
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
        \$data = array_filter((array) \$dto, fn(\$value) => \$value !== null);
        return {$name}::create(\$data);
    }

    public function find(\$id)
    {
        return {$name}::findOrFail(\$id);
    }

    public function update({$name} \${$nameLower}, {$name}DTO \$dto)
    {
        \$data = array_filter((array) \$dto, fn(\$value) => \$value !== null);
        \${$nameLower}->update(\$data);
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
        
        $policyContent = file_get_contents($policyPath);

        // Rendre toutes les méthodes true
        $policyContent = preg_replace('/(return\s+)(false|Response::deny\(\));/m', '$1true;', $policyContent);


        file_put_contents($policyPath, $policyContent);
    }

    private function updateAuthServiceProvider($name)
    {
        $providerPath = app_path('Providers/AuthServiceProvider.php');

        if (!file_exists($providerPath)) {
            $this->createAuthServiceProvider();
        }

        $content = file_get_contents($providerPath);

        // Éviter les doublons d'imports et de mappings
        $modelImport = "use App\\Models\\{$name};";
        $policyImport = "use App\\Policies\\{$name}Policy;";
        $mapping = "        {$name}::class => {$name}Policy::class,";

        if (strpos($content, $modelImport) === false) {
             $content = preg_replace('/(namespace App\\\\Providers;)/', "$1\n{$modelImport}", $content, 1);
        }
        if (strpos($content, $policyImport) === false) {
             $content = preg_replace('/(namespace App\\\\Providers;)/', "$1\n{$policyImport}", $content, 1);
        }

        if (strpos($content, $mapping) === false) {
            $content = preg_replace(
                '/protected \$policies = \[/',
                "protected \$policies = [\n{$mapping}",
                $content,
                1
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

        if (!file_exists(app_path('Providers'))) {
            mkdir(app_path('Providers'), 0755, true);
        }

        file_put_contents($providerPath, $content);
        $this->info('AuthServiceProvider créé.');
    }

    private function updateModel($name, $fieldsArray, ?array $classData = null)
    {
        $modelPath = app_path("Models/{$name}.php");

        if (!file_exists($modelPath)) {
            $this->error("Le modèle {$name} n'existe pas.");
            return;
        }

        // Préparer les données pour le template
        $fillableFields = array_keys($fieldsArray);
        
        // Ajouter les clés étrangères
        if ($classData) {
            $relations = array_merge(
                $classData['manyToOneRelationships'] ?? [], 
                $classData['oneToOneRelationships'] ?? []
            );
            foreach ($relations as $relation) {
                $fillableFields[] = Str::snake($relation['role']) . '_id';
            }
        }
        
        $fillableString = "'" . implode("', '", $fillableFields) . "'";
        $fillableProperty = "protected \$fillable = [{$fillableString}];";

        // Générer les relations
        $relationshipMethods = '';
        $imports = '';
        $parentClass = 'Model';
        
        if ($classData) {
            // Ne pas traiter l'héritage pour l'instant car dans ce JSON c'est des relations
            // L'héritage sera traité différemment plus tard
            
            $relations = [
                'oneToOne' => $classData['oneToOneRelationships'] ?? [],
                'oneToMany' => $classData['oneToManyRelationships'] ?? [],
                'manyToOne' => $classData['manyToOneRelationships'] ?? [],
                'manyToMany' => $classData['manyToManyRelationships'] ?? []
            ];

            $relatedModels = [];
            
            foreach ($relations['oneToOne'] as $rel) {
                $methodName = Str::camel($rel['role']);
                $relatedModel = ucfirst($rel['comodel']);
                $relatedModels[$relatedModel] = true;
                $relationshipMethods .= "\n    public function {$methodName}()\n    {\n        return \$this->hasOne({$relatedModel}::class);\n    }\n";
            }
            
            foreach ($relations['oneToMany'] as $rel) {
                $methodName = Str::camel($rel['role']);
                $relatedModel = ucfirst($rel['comodel']);
                $relatedModels[$relatedModel] = true;
                $relationshipMethods .= "\n    public function {$methodName}()\n    {\n        return \$this->hasMany({$relatedModel}::class);\n    }\n";
            }
            
            foreach ($relations['manyToOne'] as $rel) {
                $methodName = Str::camel($rel['role']);
                $relatedModel = ucfirst($rel['comodel']);
                $relatedModels[$relatedModel] = true;
                $relationshipMethods .= "\n    public function {$methodName}()\n    {\n        return \$this->belongsTo({$relatedModel}::class);\n    }\n";
            }
            
            foreach ($relations['manyToMany'] as $rel) {
                $methodName = Str::camel($rel['role']);
                $relatedModel = ucfirst($rel['comodel']);
                $relatedModels[$relatedModel] = true;
                $relationshipMethods .= "\n    public function {$methodName}()\n    {\n        return \$this->belongsToMany({$relatedModel}::class);\n    }\n";
            }
            
            // Ajouter les imports pour les modèles liés
            $uniqueRelatedModels = array_unique(array_keys($relatedModels));
            foreach ($uniqueRelatedModels as $relatedModel) {
                if ($relatedModel !== $name && $relatedModel !== $parentClass) { // Éviter l'auto-import et le parent
                    $imports .= "\nuse App\\Models\\{$relatedModel};";
                }
            }
        }

        // Charger le stub et remplacer les placeholders
        $stubPath = base_path(self::BASE_STUB_PATH . 'model.stub');
        if (!file_exists($stubPath)) {
            $this->error("Stub model.stub introuvable: {$stubPath}");
            return;
        }
        
        $modelContent = file_get_contents($stubPath);
        $modelContent = str_replace('{{modelName}}', $name, $modelContent);
        $modelContent = str_replace('{{parentClass}}', $parentClass, $modelContent);
        $modelContent = str_replace('{{fillable}}', $fillableProperty, $modelContent);
        $modelContent = str_replace('{{relationships}}', $relationshipMethods, $modelContent);
        $modelContent = str_replace('{{imports}}', $imports, $modelContent);

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
            $fieldsCode .= "            '{$field}' => \$this->{$field},\n";
        }

        $stub = file_get_contents(base_path(MakeApi::BASE_STUB_PATH . 'resource.stub'));
        $template = str_replace(
            ['{{modelName}}', '{{fields}}'],
            [$name, rtrim($fieldsCode)],
            $stub
        );
        file_put_contents($resourcePath, $template);
    }
}