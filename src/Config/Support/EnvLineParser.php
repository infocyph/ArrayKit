<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Config\Support;

use UnexpectedValueException;

/**
 * @internal
 */
final class EnvLineParser
{
    private function __construct() {}

    /**
     * @return array<string, string|null>
     */
    public static function parseContents(string $contents): array
    {
        return self::parseLines(preg_split('/\r\n|\r|\n/', $contents) ?: []);
    }

    /**
     * @param iterable<int, string> $lines
     * @return array<string, string|null>
     */
    public static function parseLines(iterable $lines): array
    {
        $items = [];

        foreach (self::collectLogicalLines($lines) as $number => $line) {
            $line = rtrim($line, "\r\n");
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $line = str_starts_with($trimmed, 'export ')
                ? ltrim(substr($trimmed, 7))
                : $trimmed;

            if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)(?:=(.*))?$/s', $line, $matches)) {
                throw new UnexpectedValueException('Invalid environment entry at line ' . ($number + 1) . '.');
            }

            $items[$matches[1]] = array_key_exists(2, $matches)
                ? self::parseValue($matches[2])
                : null;
        }

        return $items;
    }

    /**
     * @param iterable<int, string> $lines
     * @return array<int, string>
     */
    private static function collectLogicalLines(iterable $lines): array
    {
        $logical = [];
        $buffer = null;
        $startLine = 0;
        $quote = null;

        foreach ($lines as $lineNumber => $line) {
            if ($buffer === null) {
                $buffer = rtrim($line, "\r\n");
                $startLine = $lineNumber;
                $quote = self::openValueQuote($buffer);

                if ($quote === null || self::quotedValueIsClosed($buffer, $quote)) {
                    $logical[$startLine] = $buffer;
                    $buffer = null;
                    $quote = null;
                }

                continue;
            }

            $buffer .= "\n" . rtrim($line, "\r\n");
            if ($quote !== null && self::quotedValueIsClosed($buffer, $quote)) {
                $logical[$startLine] = $buffer;
                $buffer = null;
                $quote = null;
            }
        }

        if ($buffer !== null) {
            $logical[$startLine] = $buffer;
        }

        return $logical;
    }

    private static function openValueQuote(string $line): ?string
    {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, 'export ')) {
            $trimmed = ltrim(substr($trimmed, 7));
        }

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*=(.*)$/', $trimmed, $matches)) {
            return null;
        }

        $value = $matches[1];

        return $value !== '' && ($value[0] === '"' || $value[0] === '\'') ? $value[0] : null;
    }

    private static function parseQuotedValue(string $value, string $quote): string
    {
        $length = strlen($value);
        $result = '';

        for ($index = 1; $index < $length; $index++) {
            $char = $value[$index];

            if ($char === $quote) {
                $remainder = trim(substr($value, $index + 1));
                if ($remainder !== '' && !str_starts_with($remainder, '#')) {
                    throw new UnexpectedValueException('Unexpected characters after quoted environment value.');
                }

                return $result;
            }

            if ($quote === '"' && $char === '\\' && $index + 1 < $length) {
                $escaped = $value[++$index];
                $result .= match ($escaped) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    '$' => EnvValueResolver::LITERAL_DOLLAR,
                    '"', '\\' => $escaped,
                    default => '\\' . $escaped,
                };

                continue;
            }

            $result .= $quote === '\'' && $char === '$' ? EnvValueResolver::LITERAL_DOLLAR : $char;
        }

        throw new UnexpectedValueException('Unclosed quoted environment value.');
    }

    private static function parseValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if ($value[0] === ' ' || $value[0] === "\t") {
            throw new UnexpectedValueException('Whitespace before environment values is not supported.');
        }

        $quote = $value[0];
        if ($quote === '\'' || $quote === '"') {
            return self::parseQuotedValue($value, $quote);
        }

        $value = rtrim(self::stripInlineComment($value));
        if (preg_match('/\s+/', $value) && !str_contains($value, '$')) {
            throw new UnexpectedValueException('Environment values containing spaces must be quoted.');
        }

        return EnvValueResolver::protectEscapedDollars($value);
    }

    private static function quotedValueIsClosed(string $line, string $quote): bool
    {
        $offset = strpos($line, '=');
        if ($offset === false) {
            return true;
        }

        $escaped = false;
        $length = strlen($line);
        for ($index = $offset + 2; $index < $length; $index++) {
            $char = $line[$index];
            if ($quote === '"' && $char === '\\' && !$escaped) {
                $escaped = true;

                continue;
            }

            if ($char === $quote && !$escaped) {
                return true;
            }

            $escaped = false;
        }

        return false;
    }

    private static function stripInlineComment(string $value): string
    {
        $length = strlen($value);
        for ($index = 0; $index < $length; $index++) {
            if ($value[$index] === '#' && $index > 0 && ctype_space($value[$index - 1])) {
                return substr($value, 0, $index);
            }
        }

        return $value;
    }
}
