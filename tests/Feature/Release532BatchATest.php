<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Array\ArrayMulti;
use Infocyph\ArrayKit\Array\DotNotation;
use Infocyph\ArrayKit\ArrayKit;

it('rejects cyclic object values before guarded sort delegates to PHP comparison', function (int $options) {
    $left = new stdClass();
    $left->self = $left;
    $left->tail = 2;

    $right = new stdClass();
    $right->self = $right;
    $right->tail = 1;

    $values = [$left, $right];

    expect(ArrayMulti::sortRecursiveGuarded($values, options: $options, maxNodes: 2))
        ->toBe($values)
        ->and(fn () => ArrayMulti::sortRecursiveGuarded(
            $values,
            options: $options,
            maxNodes: 2,
            throwOnTooDeep: true,
        ))->toThrow(RuntimeException::class);
})->with([SORT_REGULAR, SORT_NUMERIC, SORT_STRING, SORT_NATURAL, SORT_LOCALE_STRING]);

it('treats opaque and wide object values as an explicit bounded-sort boundary', function () {
    $wide = new stdClass();
    foreach (range(1, 100) as $key) {
        $wide->{"field_{$key}"} = $key;
    }

    expect(ArrayMulti::sortRecursiveGuarded([3, $wide, 1], maxNodes: 3))
        ->toBe([3, $wide, 1])
        ->and(fn () => ArrayMulti::sortRecursiveGuarded(
            [$wide],
            maxNodes: 1,
            throwOnTooDeep: true,
        ))->toThrow(RuntimeException::class)
        ->and(ArrayMulti::sortRecursiveGuarded([3, 1, 2], maxNodes: 3))
        ->toBe([1, 2, 3]);
});

it('keeps the original move source when a destination readonly property rejects assignment', function () {
    $target = new class {
        public readonly int $locked;

        public function __construct()
        {
            $this->locked = 7;
        }
    };

    $items = ['source' => 42, 'target' => $target];

    expect(fn () => DotNotation::rename($items, 'source', 'target.locked'))
        ->toThrow(InvalidArgumentException::class)
        ->and($items['source'])->toBe(42)
        ->and($target->locked)->toBe(7);
});

it('retains the source after a rejecting magic setter and works through the facade', function () {
    $target = new class {
        public function __set(string $name, mixed $value): void
        {
            throw new RuntimeException("Rejected {$name} value {$value}");
        }
    };

    $items = ['source' => 42, 'target' => $target];

    expect(fn () => ArrayKit::dot()->move($items, 'source', 'target.locked'))
        ->toThrow(RuntimeException::class, 'Rejected')
        ->and($items['source'])->toBe(42);
});

it('preserves successful parent-child moves and rejects non-overwriting moves without mutation', function () {
    $parent = ['a' => ['b' => 1, 'c' => 2]];
    $child = $parent;
    $unchanged = ['from' => 1, 'to' => 2];

    expect(DotNotation::move($parent, 'a', 'a.moved'))->toBeTrue()
        ->and($parent)->toBe(['a' => ['moved' => ['b' => 1, 'c' => 2]]])
        ->and(DotNotation::rename($child, 'a.b', 'a'))->toBeTrue()
        ->and($child)->toBe(['a' => 1])
        ->and(DotNotation::rename($unchanged, 'from', 'to', overwrite: false))->toBeFalse()
        ->and($unchanged)->toBe(['from' => 1, 'to' => 2]);
});
