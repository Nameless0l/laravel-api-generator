<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

/**
 * Keeps the generated MCP server and routes/ai.php in step with the tools,
 * patching both in place like routes/api.php.
 */
final class McpRegistrar
{
    public const SERVER_CLASS = 'App\\Mcp\\Servers\\ApiServer';

    public const HANDLE = 'api';

    public const WEB_PATH = '/mcp/api';

    private const TOOLS = '/^([ \t]*)protected array \$tools = \[\R((?:.*\R)*?)([ \t]*\];)/m';

    public function __construct(
        private readonly StubLoader $stubLoader
    ) {}

    public static function serverPath(): string
    {
        return app_path('Mcp/Servers/ApiServer.php');
    }

    public static function routesPath(): string
    {
        return base_path('routes/ai.php');
    }

    /**
     * @param  array<int, string>  $tools  fully qualified tool classes
     * @return string|null what kept the tools out of the server, if anything
     */
    public function register(Workspace $workspace, array $tools): ?string
    {
        $path = self::serverPath();
        $content = $workspace->exists($path) ? $workspace->get($path) : $this->stubLoader->load('mcp.server');
        $missing = array_values(array_filter($tools, fn (string $tool) => ! str_contains($content, class_basename($tool).'::class')));

        if ($missing !== []) {
            $patched = preg_replace_callback(
                self::TOOLS,
                fn (array $m) => "{$m[1]}protected array \$tools = [\n{$m[2]}".implode('', array_map(
                    fn (string $tool) => "{$m[1]}    ".class_basename($tool)."::class,\n",
                    $missing
                )).$m[3],
                $content,
                1,
                $count
            );

            if ($patched === null || $count === 0) {
                return 'app/Mcp/Servers/ApiServer.php: could not locate the $tools array, add '.implode(', ', array_map('class_basename', $missing)).' to it.';
            }

            $content = PhpImports::add($patched, $missing);
        }

        $workspace->put($path, $content, 'McpServer');
        $this->route($workspace, "Mcp::local('".self::HANDLE."', ApiServer::class);");

        return null;
    }

    public function exposeOverHttp(Workspace $workspace): void
    {
        if ($workspace->exists(self::serverPath())) {
            $this->route($workspace, "Mcp::web('".self::WEB_PATH."', ApiServer::class)->middleware('auth:sanctum');");
        }
    }

    /**
     * Drops the tools of an entity from the server on disk.
     *
     * @return array<int, string> the removed class names
     */
    public static function forget(string $entity): array
    {
        $path = self::serverPath();
        if (! is_file($path)) {
            return [];
        }

        $namespace = preg_quote("App\\Mcp\\Tools\\{$entity}\\", '/');
        $content = (string) file_get_contents($path);
        preg_match_all('/^use '.$namespace.'(\w+);\R/m', $content, $matches);

        foreach ($matches[1] as $class) {
            $content = (string) preg_replace('/^[ \t]*'.preg_quote($class, '/').'::class,\R/m', '', $content);
        }
        $content = (string) preg_replace('/^use '.$namespace.'\w+;\R/m', '', $content);

        file_put_contents($path, $content);

        return $matches[1];
    }

    private function route(Workspace $workspace, string $line): void
    {
        $path = self::routesPath();
        $content = $workspace->exists($path) ? $workspace->get($path) : "<?php\n\nuse Laravel\\Mcp\\Facades\\Mcp;\n";

        if (str_contains($content, $line)) {
            return;
        }

        $content = PhpImports::add($content, [self::SERVER_CLASS, 'Laravel\\Mcp\\Facades\\Mcp']);
        $workspace->put($path, rtrim($content)."\n".(str_contains($content, 'ApiServer::class') ? '' : "\n").$line."\n", 'McpRoutes');
    }
}
