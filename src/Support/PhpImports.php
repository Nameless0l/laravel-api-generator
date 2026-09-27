<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

/**
 * Keeps the import block of generated PHP the way Pint wants it.
 */
final class PhpImports
{
    private const IMPORT = '/^use\s+\\\\?([A-Za-z_][A-Za-z0-9_\\\\]*)(\s+as\s+[A-Za-z_][A-Za-z0-9_]*)?;\s*$/';

    /**
     * Sorts the imports alphabetically, drops duplicates and classes of the file's own namespace.
     */
    public static function normalize(string $php): string
    {
        $eol = str_contains($php, "\r\n") ? "\r\n" : "\n";
        $lines = explode("\n", str_replace("\r\n", "\n", $php));
        $namespace = preg_match('/^namespace\s+([^;\s]+);/m', $php, $m) ? $m[1] : '';

        $first = null;
        $last = null;
        $imports = [];
        foreach ($lines as $index => $line) {
            if (preg_match(self::IMPORT, $line, $m)) {
                $first ??= $index;
                $last = $index;
                $alias = trim($m[2] ?? '');
                $sameNamespace = $alias === '' && strrpos($m[1], '\\') !== false
                    && substr($m[1], 0, (int) strrpos($m[1], '\\')) === $namespace;
                if (! $sameNamespace) {
                    $imports[strtolower($m[1].' '.$alias)] = ['class' => $m[1], 'line' => "use {$m[1]}".($alias === '' ? '' : " {$alias}").';'];
                }

                continue;
            }

            if ($first !== null && trim($line) !== '') {
                break;
            }
        }

        if ($first === null || $last === null) {
            return $php;
        }

        usort($imports, fn (array $a, array $b) => strcasecmp(
            str_replace('\\', ' ', $a['class']),
            str_replace('\\', ' ', $b['class'])
        ));

        $before = array_slice($lines, 0, $first);
        while ($before !== [] && trim((string) end($before)) === '') {
            array_pop($before);
        }
        $after = array_slice($lines, $last + 1);
        while ($after !== [] && trim($after[0]) === '') {
            array_shift($after);
        }

        $block = $imports === [] ? [] : [...array_column($imports, 'line'), ''];

        return implode($eol, [...$before, '', ...$block, ...$after]);
    }

    /**
     * @param  array<int, string>  $classes  fully qualified, without the leading backslash
     */
    public static function add(string $php, array $classes): string
    {
        if ($classes === []) {
            return $php;
        }

        $eol = str_contains($php, "\r\n") ? "\r\n" : "\n";
        $lines = implode($eol, array_map(fn (string $class) => "use {$class};", $classes));

        $offset = null;
        if (preg_match('/^use\s+[^;(]+;/m', $php, $m, PREG_OFFSET_CAPTURE)) {
            $offset = (int) $m[0][1];
        } else {
            foreach (['/^namespace\s+[^;]+;\R/m', '/^declare\(strict_types=1\);\R/m', '/^<\?php\R/'] as $anchor) {
                if (preg_match($anchor, $php, $m, PREG_OFFSET_CAPTURE)) {
                    $offset = (int) $m[0][1] + strlen($m[0][0]);
                    break;
                }
            }
        }

        if ($offset === null) {
            return $php;
        }

        return self::normalize(substr($php, 0, $offset).$lines.$eol.$eol.substr($php, $offset));
    }
}
