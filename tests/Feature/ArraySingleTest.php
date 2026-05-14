<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Array\ArraySingle;

it('checks if a key exists in a single-dimensional array', function () {
    $data = ['one' => 1, 'two' => 2];
    expect(ArraySingle::exists($data, 'two'))
        ->toBeTrue()
        ->and(ArraySingle::exists($data, 'three'))->toBeFalse();
});

it('retrieves only specified keys', function () {
    $data   = ['name' => 'Alice', 'age' => 30, 'job' => 'Developer'];
    $subset = ArraySingle::only($data, ['name', 'job']);
    expect($subset)->toBe(['name' => 'Alice', 'job' => 'Developer']);
});

it('can detect if array is a list', function () {
    $list  = [10, 20, 30];
    $assoc = ['a' => 1, 'b' => 2];
    expect(ArraySingle::isList($list))
        ->toBeTrue()
        ->and(ArraySingle::isList($assoc))->toBeFalse();
});

it('treats an empty array as a list', function () {
    expect(ArraySingle::isList([]))->toBeTrue();
});

it('treats an empty array as non-associative', function () {
    expect(ArraySingle::isAssoc([]))->toBeFalse();
});

it('calculates average of numeric values', function () {
    $nums = [2, 4, 6, 8];
    expect(ArraySingle::avg($nums))->toBe(5);
});

it('searches an array for a callback condition', function () {
    $data = [1, 2, 3, 4];
    $key  = ArraySingle::search($data, fn ($value) => $value === 3);
    expect($key)->toBe(2);
});

it('checks containsAll and containsAny with strict and loose modes', function () {
    $data = [1, 2, 3, '3'];

    expect(ArraySingle::containsAll($data, [1, '2']))
        ->toBeTrue()
        ->and(ArraySingle::containsAll($data, [1, '2'], true))->toBeFalse()
        ->and(ArraySingle::containsAny($data, ['x', 2]))->toBeTrue()
        ->and(ArraySingle::containsAny($data, ['x', '2'], true))->toBeFalse();
});

it('sums the array using sum()', function () {
    $arr = [1, 2, 3];
    expect(ArraySingle::sum($arr))
        ->toBe(6)
        ->and(ArraySingle::sum($arr, fn ($v) => $v * 2))
        ->toBe(12);
});

it('filters non-empty values without crashing on mixed data', function () {
    $arr = [1, '', 0, '0', null, false, 'hello'];

    expect(ArraySingle::nonEmpty($arr))->toBe([1, 0, '0', null, false, 'hello'])
        ->and(ArraySingle::nonEmpty($arr, true))->toBe([
            0 => 1,
            2 => 0,
            3 => '0',
            4 => null,
            5 => false,
            6 => 'hello',
        ]);
});
it('removes duplicates from the array using unique()', function () {
    $arr = [1, 2, 2, 3, 3, 4];
    expect(ArraySingle::unique($arr))
        ->toBe([1, 2, 3, 4])
        ->and(ArraySingle::unique([1, '1', 2, 3], true))
        ->toBe([1, '1', 2, 3]); // Strict comparison
});

it('handles unique() with mixed values in loose and strict modes', function () {
    $arr = [1, '1', true, [1], ['1']];

    expect(ArraySingle::unique($arr))->toBe([1, [1]])
        ->and(ArraySingle::unique($arr, true))->toBe([1, '1', true, [1], ['1']]);
});
it('slices the array using slice()', function () {
    $arr = [1, 2, 3, 4, 5];
    expect(ArraySingle::slice($arr, 1, 3))
        ->toBe([1 => 2, 2 => 3, 3 => 4])
        ->and(ArraySingle::slice($arr, 1))->toBe([1 => 2, 2 => 3, 3 => 4, 4 => 5]);
});
it('partitions the array based on a callback using partition()', function () {
    $arr = [1, 2, 3, 4, 5];
    $result = ArraySingle::partition($arr, fn ($v) => $v % 2 === 0);
    expect($result)->toBe([
        [1 => 2, 3 => 4], // passed 'even' numbers
        [0 => 1, 2 => 3, 4 => 5], // failed 'odd' numbers
    ]);
});
it('rejects unwanted values using reject()', function () {
    $arr = [1, 2, 3, 4, 5, 'a', 'b', 'c'];
    $result = ArraySingle::reject($arr, fn ($val) => is_numeric($val) && $val > 3);
    expect(array_values($result))->toBe([1, 2, 3, 'a', 'b', 'c']);
});

it('skips the first n items using skip()', function () {
    $arr = [1, 2, 3, 4, 5, 6];
    expect(array_values(ArraySingle::skip($arr, 3)))->toBe([4, 5, 6]);
});
it('skips items while the callback returns true using skipWhile()', function () {
    $arr = [1, 2, 3, 4, 5];
    expect(array_values(ArraySingle::skipWhile($arr, fn ($v) => $v < 3)))->toBe([3, 4, 5]);
});
it('skips items until the callback returns true using skipUntil()', function () {
    $arr = [1, 2, 3, 4, 5];
    expect(array_values(ArraySingle::skipUntil($arr, fn ($v) => $v === 3)))->toBe([3, 4, 5]);
});

it('throws for invalid nth step values', function () {
    expect(fn () => ArraySingle::nth([1, 2, 3], 0))->toThrow(InvalidArgumentException::class);
});

it('throws for invalid nth offset values', function () {
    expect(fn () => ArraySingle::nth([1, 2, 3], 1, -1))->toThrow(InvalidArgumentException::class);
});

it('selects nth values using step and offset semantics', function () {
    $arr = [10, 20, 30, 40, 50, 60];

    expect(ArraySingle::nth($arr, 2, 0))->toBe([10, 30, 50])
        ->and(ArraySingle::nth($arr, 2, 1))->toBe([20, 40, 60])
        ->and(ArraySingle::nth($arr, 2, 2))->toBe([30, 50])
        ->and(ArraySingle::nth($arr, 2, 4))->toBe([50]);
});

it('supports countBy, min/max, minBy/maxBy, mapWithKeys, values and rekey helpers', function () {
    $rows = [
        ['id' => 1, 'group' => 'a', 'score' => 10],
        ['id' => 2, 'group' => 'a', 'score' => 30],
        ['id' => 3, 'group' => 'b', 'score' => 20],
    ];

    expect(ArraySingle::countBy([1, 2, 2, 3, 3, 3]))->toBe([1 => 1, 2 => 2, 3 => 3])
        ->and(ArraySingle::countBy($rows, fn (array $row) => $row['group']))->toBe(['a' => 2, 'b' => 1])
        ->and(ArraySingle::min([9, 2, 4]))->toBe(2)
        ->and(ArraySingle::max([9, 2, 4]))->toBe(9)
        ->and(ArraySingle::minBy($rows, fn (array $row) => $row['score']))->toBe(['id' => 1, 'group' => 'a', 'score' => 10])
        ->and(ArraySingle::maxBy($rows, fn (array $row) => $row['score']))->toBe(['id' => 2, 'group' => 'a', 'score' => 30])
        ->and(ArraySingle::mapWithKeys($rows, fn (array $row) => [$row['id'] => $row['score']]))->toBe([1 => 10, 2 => 30, 3 => 20])
        ->and(ArraySingle::values(['x' => 1, 'y' => 2]))->toBe([1, 2])
        ->and(ArraySingle::rekey(['first_name' => 'Ada'], ['first_name' => 'firstName']))->toBe(['firstName' => 'Ada']);
});

it('supports intersect, diff, symmetricDiff and same helpers', function () {
    $left = [1, 2, 3, '3'];
    $right = [3, 4, '3'];

    expect(ArraySingle::intersect($left, $right))->toBe([2 => 3, 3 => '3'])
        ->and(ArraySingle::diff($left, $right))->toBe([0 => 1, 1 => 2])
        ->and(ArraySingle::symmetricDiff([1, 2, 3], [3, 4]))->toBe([1, 2, 4])
        ->and(ArraySingle::same([1, 2, 2], [2, 1, 2]))->toBeTrue()
        ->and(ArraySingle::same([1, 2], ['1', 2], true))->toBeFalse();
});
