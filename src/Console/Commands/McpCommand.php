<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Mcp\Server\Registrar;

class McpCommand extends Command
{
    public const HANDLE = 'laravel-api-generator';

    protected $signature = 'api-generator:mcp';

    protected $description = 'Start the MCP server that lets coding agents plan and generate APIs (needs laravel/mcp)';

    public function handle(): int
    {
        if (! class_exists(Registrar::class)) {
            $this->output->getErrorStyle()->writeln('<error>The MCP server needs laravel/mcp (Laravel 12.41+). Install it with: composer require --dev laravel/mcp</error>');

            return self::FAILURE;
        }

        return $this->call('mcp:start', ['handle' => self::HANDLE]);
    }
}
