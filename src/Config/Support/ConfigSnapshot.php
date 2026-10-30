<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Config\Support;

use ReflectionReference;
use UnexpectedValueException;

/** @internal */
final class ConfigSnapshot
{
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

        return self::copy($items, $active);
    }

    /**
     * @param array<array-key, mixed> $items
     * @param array<string, true> $active
     * @return array<array-key, mixed>
     */
    private static function copy(array $items, array &$active): array
    {
        $output = [];
        foreach ($items as $key => $value) {
            $reference = ReflectionReference::fromArrayElement($items, $key);
            $id = $reference !== null ? bin2hex($reference->getId()) : null;
            if ($id !== null && isset($active[$id])) {
                throw new UnexpectedValueException('Configuration snapshot contains a cyclic array reference.');
            }

            if ($id !== null) {
                $active[$id] = true;
            }

            try {
                $output[$key] = is_array($value) ? self::copy($value, $active) : $value;
            } finally {
                if ($id !== null) {
                    unset($active[$id]);
                }
            }
        }

        return $output;
    }
}
