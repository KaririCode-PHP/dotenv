<?php

declare(strict_types=1);

namespace KaririCode\Dotenv\Core;

/**
 * Splits .env content into entries while preserving the original text.
 *
 * Tools that rewrite files (encrypt, decrypt, example:generate) need the
 * layout the parser discards: comments, blank lines and the exact span of
 * each assignment. Multiline double-quoted values are kept in one entry,
 * using the same escape rule as DotenvParser, so a PEM body is never
 * mistaken for separate lines.
 *
 * @package KaririCode\Dotenv
 * @since   4.5.0
 */
final readonly class EnvFileScanner
{
    private const string ASSIGNMENT_PATTERN = '/\A\s*(export\s+)?([A-Za-z_][A-Za-z0-9_.]*)\s*=(.*)\z/';

    /**
     * @return list<EnvEntry>
     */
    public function scan(string $content): array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
        $lineCount = count($lines);
        $entries = [];

        for ($index = 0; $index < $lineCount; ++$index) {
            $line = $lines[$index];
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')
                || preg_match(self::ASSIGNMENT_PATTERN, $line, $matches) !== 1
            ) {
                $entries[] = new EnvEntry($line);

                continue;
            }

            $block = [$line];
            $rawValue = ltrim($matches[3]);

            if (str_starts_with($rawValue, '"') && !$this->closesDoubleQuote(substr($rawValue, 1))) {
                while ($index + 1 < $lineCount) {
                    $block[] = $lines[++$index];

                    if ($this->closesDoubleQuote($lines[$index])) {
                        break;
                    }
                }

                $rawValue = implode("\n", array_merge([$rawValue], array_slice($block, 1)));
            }

            $entries[] = new EnvEntry(
                implode("\n", $block),
                $matches[2],
                $matches[1] !== '',
                $rawValue,
            );
        }

        return $entries;
    }

    /**
     * True when the fragment contains an unescaped `"` — the parser's rule:
     * a backslash escapes the next character on the same line.
     */
    private function closesDoubleQuote(string $fragment): bool
    {
        $length = strlen($fragment);

        for ($i = 0; $i < $length; ++$i) {
            if ($fragment[$i] === '\\' && $i + 1 < $length) {
                ++$i;

                continue;
            }

            if ($fragment[$i] === '"') {
                return true;
            }
        }

        return false;
    }
}
