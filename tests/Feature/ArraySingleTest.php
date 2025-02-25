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

it('calculates average of numeric values', function () {
    $nums = [2, 4, 6, 8];
    expect(ArraySingle::avg($nums))->toBe(5);
});

it('searches an array for a callback condition', function () {
    $data = [1, 2, 3, 4];
    $key  = ArraySingle::search($data, fn ($value) => $value === 3);
    expect($key)->toBe(2);
});
it('sums the array using sum()', function () {
    $arr = [1, 2, 3];
    expect(ArraySingle::sum($arr))
        ->toBe(6)
        ->and(ArraySingle::sum($arr, fn ($v) => $v * 2))
        ->toBe(12);
});
it('removes duplicates from the array using unique()', function () {
    $arr = [1, 2, 2, 3, 3, 4];
    expect(ArraySingle::unique($arr))
        ->toBe([1, 2, 3, 4])
        ->and(ArraySingle::unique([1, '1', 2, 3], true))
        ->toBe([1, '1', 2, 3]); // Strict comparison
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
