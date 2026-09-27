<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Console\Commands;

use Illuminate\Console\Command;
use nameless\CodeGenerator\Support\StdioLoop;

class ServeCommand extends Command
{
    protected $signature = 'api-generator:serve {--stdio : Speak JSON-RPC 2.0 over stdin and stdout, one message per line}';

    protected $description = 'Answer generation previews for editors and agents without writing files';

    public function handle(StdioLoop $loop): int
    {
        if (! $this->option('stdio')) {
            $this->error('Only --stdio is supported: php artisan api-generator:serve --stdio');

            return self::FAILURE;
        }

        ini_set('display_errors', 'stderr');
        $loop->run(STDIN, STDOUT, STDERR);

        return self::SUCCESS;
    }
}
