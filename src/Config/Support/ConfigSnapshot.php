<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Config\Support;

use ReflectionReference;
use UnexpectedValueException;

/** @internal */
final class ConfigSnapshot
{
    private const int MAX_DEPTH = 256;

    private const int MAX_NODES = 100000;

    /**
     * Snapshot arrays and scalar values by value while retaining object identity.
     * Repeated array references become independent values; cyclic array references
     * are rejected before the caller replaces an existing valid snapshot.
     *
     * @param array<array-key, mixed> $items
     * @return array<array-key, mixed>
     */
    public static function detach(array $items): array
    {
        $active = [];
        $copies = [];
        $visited = 0;

        return self::copy($items, $active, $copies, $visited);
    }

    /**
     * @param array<array-key, mixed> $items
     * @param array<string, true> $active
     * @param array<string, mixed> $copies
     * @return array<array-key, mixed>
     */
    private static function copy(array $items, array &$active, array &$copies, int &$visited, int $depth = 1): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw new UnexpectedValueException('Configuration snapshot exceeded max depth.');
        }

        $output = [];
        foreach ($items as $key => $value) {
            if (++$visited > self::MAX_NODES) {
                throw new UnexpectedValueException('Configuration snapshot exceeded max node count.');
            }
            $reference = ReflectionReference::fromArrayElement($items, $key);
            if ($reference === null) {
                $output[$key] = is_array($value) ? self::copy($value, $active, $copies, $visited, $depth + 1) : $value;

                continue;
            }

            $output[$key] = self::copyReference($value, bin2hex($reference->getId()), $active, $copies, $visited, $depth);
        }

        return $output;
    }

    /**
     * @param array<string, true> $active
     * @param array<string, mixed> $copies
     */
    private static function copyReference(
        mixed $value,
        string $id,
        array &$active,
        array &$copies,
        int &$visited,
        int $depth,
    ): mixed {
        if (isset($active[$id])) {
            throw new UnexpectedValueException('Configuration snapshot contains a cyclic array reference.');
        }

        if (array_key_exists($id, $copies)) {
            return $copies[$id];
        }

        $active[$id] = true;

        try {
            return $copies[$id] = is_array($value) ? self::copy($value, $active, $copies, $visited, $depth + 1) : $value;
        } finally {
            unset($active[$id]);
        }
    }
}
