<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\DTO;

use Infocyph\ArrayKit\DTO\Concerns\DTOTrait;
use InvalidArgumentException;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;
use ReflectionReference;
use RuntimeException;
use UnitEnum;

/** @internal */
final class DTOGraphGuard
{
    public static function assertWithinLimits(mixed $value, int $maxDepth, int $maxNodes): void
    {
        self::traverse($value, $maxDepth, $maxNodes, false);
    }

    /** @return array<array-key, mixed> */
    public static function export(object $value, int $maxDepth, int $maxNodes): array
    {
        $result = self::traverse($value, $maxDepth, $maxNodes, true);
        if (!is_array($result)) {
            throw new InvalidArgumentException('Guarded export requires the standard DTO exporter.');
        }

        return $result;
    }

    private static function assertNodeWithinLimits(
        int $depth,
        int &$visitedNodes,
        int $maxDepth,
        int $maxNodes,
    ): void {
        if ($depth > $maxDepth) {
            throw new RuntimeException('DTO graph traversal exceeded max depth.');
        }

        $visitedNodes++;
        if ($visitedNodes > $maxNodes) {
            throw new RuntimeException('DTO graph traversal exceeded max node count.');
        }
    }

    private static function isStandardExporter(object $value): bool
    {
        if (!is_callable([$value, 'toArrayDeep']) && !is_callable([$value, 'toArray'])) {
            return false;
        }

        foreach (['toArray', 'toArrayDeep'] as $method) {
            if (
                !method_exists($value, $method)
                || new ReflectionMethod($value, $method)->getFileName()
                    !== new ReflectionMethod(DTOTrait::class, $method)->getFileName()
            ) {
                throw new InvalidArgumentException('Guarded DTO export does not execute custom exporters.');
            }
        }

        return true;
    }

    private static function traverse(mixed $value, int $maxDepth, int $maxNodes, bool $export): mixed
    {
        if ($maxDepth < 1) {
            throw new InvalidArgumentException('DTO graph max depth must be at least 1.');
        }

        if ($maxNodes < 1) {
            throw new InvalidArgumentException('DTO graph max node count must be at least 1.');
        }

        $activeObjects = [];
        $activeReferences = [];
        $visitedNodes = 0;

        return self::walk(
            $value,
            1,
            $visitedNodes,
            $maxDepth,
            $maxNodes,
            $activeObjects,
            $activeReferences,
            $export,
        );
    }

    /**
     * @param array<int, true> $activeObjects
     * @param array<string, true> $activeReferences
     */
    private static function walk(
        mixed $value,
        int $depth,
        int &$visitedNodes,
        int $maxDepth,
        int $maxNodes,
        array &$activeObjects,
        array &$activeReferences,
        bool $export,
    ): mixed {
        self::assertNodeWithinLimits($depth, $visitedNodes, $maxDepth, $maxNodes);

        if (is_array($value)) {
            return self::walkArray(
                $value,
                $depth,
                $visitedNodes,
                $maxDepth,
                $maxNodes,
                $activeObjects,
                $activeReferences,
                $export,
            );
        }

        if (is_object($value) && !$value instanceof UnitEnum) {
            return self::walkObject(
                $value,
                $depth,
                $visitedNodes,
                $maxDepth,
                $maxNodes,
                $activeObjects,
                $activeReferences,
                $export,
            );
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $value
     * @param array<int, true> $activeObjects
     * @param array<string, true> $activeReferences
     */
    private static function walkArray(
        array $value,
        int $depth,
        int &$visitedNodes,
        int $maxDepth,
        int $maxNodes,
        array &$activeObjects,
        array &$activeReferences,
        bool $export,
    ): mixed {
        $result = [];
        foreach ($value as $key => $entry) {
            $reference = ReflectionReference::fromArrayElement($value, $key);
            if ($reference === null) {
                $resolved = self::walk(
                    $entry,
                    $depth + 1,
                    $visitedNodes,
                    $maxDepth,
                    $maxNodes,
                    $activeObjects,
                    $activeReferences,
                    $export,
                );
                if ($export) {
                    $result[$key] = $resolved;
                }

                continue;
            }

            $referenceId = bin2hex($reference->getId());
            if (isset($activeReferences[$referenceId])) {
                throw new RuntimeException('DTO graph contains a cyclic array reference.');
            }

            $activeReferences[$referenceId] = true;

            try {
                $resolved = self::walk(
                    $entry,
                    $depth + 1,
                    $visitedNodes,
                    $maxDepth,
                    $maxNodes,
                    $activeObjects,
                    $activeReferences,
                    $export,
                );
                if ($export) {
                    $result[$key] = $resolved;
                }
            } finally {
                unset($activeReferences[$referenceId]);
            }
        }

        return $export ? $result : $value;
    }

    /**
     * @param array<int, true> $activeObjects
     * @param array<string, true> $activeReferences
     */
    private static function walkObject(
        object $value,
        int $depth,
        int &$visitedNodes,
        int $maxDepth,
        int $maxNodes,
        array &$activeObjects,
        array &$activeReferences,
        bool $export,
    ): mixed {
        $objectId = spl_object_id($value);
        if (isset($activeObjects[$objectId])) {
            throw new RuntimeException('DTO graph contains a cyclic object reference.');
        }

        $exportObject = $export && self::isStandardExporter($value);
        $result = [];
        $activeObjects[$objectId] = true;

        try {
            foreach (new ReflectionObject($value)->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->isStatic() || !$property->isInitialized($value)) {
                    continue;
                }

                $resolved = self::walk(
                    $property->getValue($value),
                    $depth + 1,
                    $visitedNodes,
                    $maxDepth,
                    $maxNodes,
                    $activeObjects,
                    $activeReferences,
                    $exportObject,
                );
                if ($exportObject) {
                    $result[$property->getName()] = $resolved;
                }
            }
        } finally {
            unset($activeObjects[$objectId]);
        }

        return $exportObject ? $result : $value;
    }
}
