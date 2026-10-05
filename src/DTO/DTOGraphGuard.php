<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\DTO;

use InvalidArgumentException;
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
        if ($maxDepth < 1) {
            throw new InvalidArgumentException('DTO graph max depth must be at least 1.');
        }

        if ($maxNodes < 1) {
            throw new InvalidArgumentException('DTO graph max node count must be at least 1.');
        }

        $activeObjects = [];
        $activeReferences = [];
        $visitedNodes = 0;

        self::walk(
            $value,
            1,
            $visitedNodes,
            $maxDepth,
            $maxNodes,
            $activeObjects,
            $activeReferences,
        );
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
    ): void {
        self::assertNodeWithinLimits($depth, $visitedNodes, $maxDepth, $maxNodes);

        if (is_array($value)) {
            self::walkArray(
                $value,
                $depth,
                $visitedNodes,
                $maxDepth,
                $maxNodes,
                $activeObjects,
                $activeReferences,
            );

            return;
        }

        if (is_object($value) && !$value instanceof UnitEnum) {
            self::walkObject(
                $value,
                $depth,
                $visitedNodes,
                $maxDepth,
                $maxNodes,
                $activeObjects,
                $activeReferences,
            );
        }
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
    ): void {
        foreach ($value as $key => $entry) {
            $reference = ReflectionReference::fromArrayElement($value, $key);
            if ($reference === null) {
                self::walk(
                    $entry,
                    $depth + 1,
                    $visitedNodes,
                    $maxDepth,
                    $maxNodes,
                    $activeObjects,
                    $activeReferences,
                );

                continue;
            }

            $referenceId = bin2hex($reference->getId());
            if (isset($activeReferences[$referenceId])) {
                throw new RuntimeException('DTO graph contains a cyclic array reference.');
            }

            $activeReferences[$referenceId] = true;

            try {
                self::walk(
                    $entry,
                    $depth + 1,
                    $visitedNodes,
                    $maxDepth,
                    $maxNodes,
                    $activeObjects,
                    $activeReferences,
                );
            } finally {
                unset($activeReferences[$referenceId]);
            }
        }
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
    ): void {
        $objectId = spl_object_id($value);
        if (isset($activeObjects[$objectId])) {
            throw new RuntimeException('DTO graph contains a cyclic object reference.');
        }

        $activeObjects[$objectId] = true;

        try {
            foreach (new ReflectionObject($value)->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->isStatic() || !$property->isInitialized($value)) {
                    continue;
                }

                self::walk(
                    $property->getValue($value),
                    $depth + 1,
                    $visitedNodes,
                    $maxDepth,
                    $maxNodes,
                    $activeObjects,
                    $activeReferences,
                );
            }
        } finally {
            unset($activeObjects[$objectId]);
        }
    }
}
