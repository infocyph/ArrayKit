<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Collection\Collection;

it('can be instantiated with array data', function () {
    $collection = new Collection(['a' => 1, 'b' => 2]);
    expect($collection->items())->toBe(['a' => 1, 'b' => 2]);
});

it('serializes arrayable input only once', function () {
    $source = new class implements JsonSerializable {
        public int $calls = 0;

        #[Override]
        public function jsonSerialize(): mixed
        {
            $this->calls++;

            return ['a' => 1];
        }
    };

    expect(Collection::make($source)->all())->toBe(['a' => 1])
        ->and($source->calls)->toBe(1);
});

it('supports array access', function () {
    $collection = new Collection;
    $collection['x'] = 42;
    expect($collection['x'])->toBe(42);
});

it('supports iteration', function () {
    $collection = new Collection(['a' => 1, 'b' => 2]);
    $items = [];
    foreach ($collection as $key => $val) {
        if (!is_int($val)) {
            throw new UnexpectedValueException('Expected integer fixture value.');
        }
        $items[$key] = $val;
    }
    expect($items)->toBe(['a' => 1, 'b' => 2]);
});

it('supports nested iteration without pointer side effects', function () {
    $collection = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);
    $pairs = [];

    foreach ($collection as $outerKey => $outerValue) {
        foreach ($collection as $innerKey => $innerValue) {
            if (!is_int($outerValue) || !is_int($innerValue)) {
                throw new UnexpectedValueException('Expected integer collection values.');
            }
            $pairs[] = [$outerKey, $innerKey, $outerValue + $innerValue];
        }
    }

    expect(count($pairs))->toBe(9)
        ->and($pairs[0])->toBe(['a', 'a', 2])
        ->and($pairs[8])->toBe(['c', 'c', 6]);
});

it('provides a merge method', function () {
    $c1 = new Collection(['a' => 1]);
    $c2 = new Collection(['b' => 2]);

    $c1->merge($c2);
    expect($c1->items())->toBe(['a' => 1, 'b' => 2]);
});

it('can filter and return a new collection', function () {
    $collection = new Collection([1, 2, 3, 4]);
    $even = $collection->filter(static fn(int $val): bool => $val % 2 === 0);
    expect($even->all())->toBe([1 => 2, 3 => 4]);
});

it('considers null-valued keys as existing', function () {
    $collection = new Collection(['nullable' => null]);

    expect(isset($collection['nullable']))->toBeTrue();
});

it('supports copy and immutable snapshots', function () {
    $collection = new Collection(['a' => 1, 'b' => 2]);
    $copy = $collection->copy();
    $immutable = $collection->immutable();

    $collection['a'] = 99;

    expect($copy->all())->toBe(['a' => 1, 'b' => 2])
        ->and($immutable->all())->toBe(['a' => 1, 'b' => 2]);
});

it('supports immutableProcess and pipeImmutable pipeline entrypoints', function () {
    $collection = new Collection([1, 2, 3, 4]);

    $processed = $collection->immutableProcess()->filter(fn (int $v) => $v % 2 === 0)->all();
    $processedAlias = $collection->pipeImmutable()->filter(fn (int $v) => $v > 2)->all();

    expect($processed)->toBe([1 => 2, 3 => 4])
        ->and($processedAlias)->toBe([2 => 3, 3 => 4])
        ->and($collection->all())->toBe([1, 2, 3, 4]);
});
