<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

use nameless\CodeGenerator\Contracts\LineHandler;

final class StdioLoop
{
    public function __construct(
        private readonly LineHandler $handler,
    ) {}

    /**
     * Anything a provider or a notice prints while a request runs is moved
     * to stderr, so stdout only ever carries protocol lines.
     *
     * @param  resource  $input
     * @param  resource  $output
     * @param  resource  $errors
     */
    public function run($input, $output, $errors): void
    {
        while (! $this->handler->stopped() && ($line = fgets($input)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            ob_start();
            try {
                $response = $this->handler->handle($line);
            } finally {
                $stray = (string) ob_get_clean();
                if ($stray !== '') {
                    fwrite($errors, $stray);
                }
            }

            if ($response !== null) {
                fwrite($output, $response."\n");
                fflush($output);
            }
        }
    }
}
