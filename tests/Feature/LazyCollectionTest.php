<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Collection\LazyCollection;

it('supports mapLazy/filterLazy/take/takeUntil/chunkLazy/cursor', function () {
    $lazy = LazyCollection::make([1, 2, 3, 4, 5, 6]);

    $mapped = $lazy
        ->mapLazy(fn (int $value): int => $value * 2)
        ->filterLazy(fn (int $value): bool => $value > 4)
        ->take(3)
        ->all();

    expect($mapped)->toBe([2 => 6, 3 => 8, 4 => 10]);

    $chunks = $lazy->chunkLazy(2)->all();
    expect($chunks)->toBe([[1, 2], [3, 4], [5, 6]]);

    $until = $lazy->takeUntil(fn (int $value): bool => $value === 4)->all();
    expect($until)->toBe([1, 2, 3]);

    $cursor = iterator_to_array($lazy->cursor(), true);
    expect($cursor)->toBe([1, 2, 3, 4, 5, 6]);
});

it('does not advance the source when take(0) is requested', function () {
    $visited = 0;

    $lazy = LazyCollection::from((function () use (&$visited) {
        $visited++;
        yield 1;
        $visited++;
        yield 2;
    })());

    expect($lazy->take(0)->all())->toBe([])
        ->and($visited)->toBe(0);
});

it('keeps traversable input lazy when constructed through make', function () {
    $visited = 0;

    $lazy = LazyCollection::make((function () use (&$visited) {
        $visited++;
        yield 1;
    })());

    expect($visited)->toBe(0)
        ->and($lazy->all())->toBe([1])
        ->and($lazy->all())->toBe([1])
        ->and($visited)->toBe(1);
});

it('can traverse a generator-backed collection more than once', function () {
    $visited = 0;

    $lazy = LazyCollection::from((function () use (&$visited) {
        $visited++;
        yield 'first' => 1;
        $visited++;
        yield 'second' => 2;
    })());

    expect($lazy->take(1)->all())->toBe(['first' => 1])
        ->and($visited)->toBe(1)
        ->and($lazy->all())->toBe(['first' => 1, 'second' => 2])
        ->and($lazy->all())->toBe(['first' => 1, 'second' => 2])
        ->and($visited)->toBe(2);
});

it('creates a new source for every factory-backed traversal', function () {
    $created = 0;

    $lazy = LazyCollection::fromFactory(function () use (&$created): Generator {
        $created++;
        yield 1;
        yield 2;
    });

    expect($lazy->all())->toBe([1, 2])
        ->and($lazy->all())->toBe([1, 2])
        ->and($created)->toBe(2);
});
