<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

class StdinReader
{
    public function read(): string
    {
        $content = stream_get_contents(STDIN);

        return $content === false ? '' : $content;
    }
}
