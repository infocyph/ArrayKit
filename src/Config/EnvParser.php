<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Config;

use Infocyph\ArrayKit\Config\Support\EnvLineParser;
use Infocyph\ArrayKit\Config\Support\EnvValueResolver;
use InvalidArgumentException;
use UnexpectedValueException;

final class EnvParser
{
    private function __construct() {}

    /**
     * @return array<string, string|null>
     */
    public static function parse(string $contents): array
    {
        self::assertSafeContents($contents, '.env');

        return EnvValueResolver::resolve(EnvLineParser::parseContents($contents));
    }

    /**
     * @return array<string, string|null>
     */
    public static function parseFile(string $path): array
    {
        return EnvValueResolver::resolve(EnvLineParser::parseContents(self::readFile($path)));
    }

    /**
     * @return array<string, string|null>
     */
    public static function parseFileRaw(string $path): array
    {
        return EnvValueResolver::restoreLiteralDollars(EnvLineParser::parseContents(self::readFile($path)));
    }

    /**
     * @param iterable<int, string> $lines
     * @return array<string, string|null>
     */
    public static function parseLines(iterable $lines): array
    {
        return EnvValueResolver::resolve(EnvLineParser::parseLines(self::safeLines($lines)));
    }

    /**
     * @param iterable<int, string> $lines
     * @return array<string, string|null>
     */
    public static function parseLinesRaw(iterable $lines): array
    {
        return EnvValueResolver::restoreLiteralDollars(EnvLineParser::parseLines(self::safeLines($lines)));
    }

    /**
     * Parse values without expanding variable references.
     *
     * @return array<string, string|null>
     */
    public static function parseRaw(string $contents): array
    {
        self::assertSafeContents($contents, '.env');

        return EnvValueResolver::restoreLiteralDollars(EnvLineParser::parseContents($contents));
    }

    private static function assertSafeContents(string $contents, string $path): void
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            throw new UnexpectedValueException("Environment file [{$path}] must not start with a byte-order mark.");
        }

        if (str_contains($contents, "\0")) {
            throw new UnexpectedValueException("Environment file [{$path}] must not contain NUL bytes.");
        }
    }

    private static function readFile(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException("Environment file [{$path}] is not readable.");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new InvalidArgumentException("Environment file [{$path}] could not be read.");
        }

        self::assertSafeContents($contents, $path);

        return $contents;
    }

    /**
     * @param iterable<int, string> $lines
     * @return \Generator<int, string>
     */
    private static function safeLines(iterable $lines): \Generator
    {
        $first = true;

        foreach ($lines as $number => $line) {
            if ($first && str_starts_with($line, "\xEF\xBB\xBF")) {
                throw new UnexpectedValueException('Environment lines must not start with a byte-order mark.');
            }

            if (str_contains($line, "\0")) {
                throw new UnexpectedValueException('Environment lines must not contain NUL bytes.');
            }

            $first = false;

            yield $number => $line;
        }
    }
}
