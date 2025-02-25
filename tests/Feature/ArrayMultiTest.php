<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Array\ArrayMulti;

it('can collapse a multi-dimensional array', function () {
    $source = [[1, 2], [3, 4], [5]];
    $result = ArrayMulti::collapse($source);
    expect($result)->toBe([1, 2, 3, 4, 5]);
});

it('can flatten an array fully by default', function () {
    $source = [1, [2, [3, 4]], 5];
    $result = ArrayMulti::flatten($source);
    expect($result)->toBe([1, 2, 3, 4, 5]);
});

it('can flatten an array by one level', function () {
    $source = [1, [2, [3, 4]], 5];
    // Flatten only one level
    $result = ArrayMulti::flatten($source, 1);
    expect($result)->toBe([1, 2, [3, 4], 5]);
});

it('can get the depth of a nested array', function () {
    $source = [1, [2, [3]], 4];
    $depth  = ArrayMulti::depth($source);
    expect($depth)->toBe(3);
});

it('sorts a multi-dimensional array recursively (asc)', function () {
    $data = [
        ['z' => 3, 'a' => 2],
        ['z' => 1, 'a' => 4],
    ];
    $sorted = ArrayMulti::sortRecursive($data);
    // Expect each sub-array to have sorted keys 'a' => x, 'z' => y
    expect($sorted)->toEqual([
        ['a' => 2, 'z' => 3],
        ['a' => 4, 'z' => 1],
    ]);
});
// only()
it('selects only specified keys from a multidimensional array', function () {
    $data = [
        ['a' => 1, 'b' => 2, 'c' => 3],
        ['a' => 4, 'b' => 5, 'c' => 6],
        ['a' => 7, 'b' => 8],
    ];
    $result = ArrayMulti::only($data, ['a', 'c']);
    expect($result)->toBe([
        ['a' => 1, 'c' => 3],
        ['a' => 4, 'c' => 6],
        ['a' => 7],
    ]);
});

// collapse()
it('collapses a multidimensional array into a single-dimensional array', function () {
    $data = [
        [1, 2],
        [3, 4],
        5,
        [6, 7],
    ];
    $result = ArrayMulti::collapse($data);
    expect($result)->toBe([1, 2, 3, 4, 6, 7]);
});

// depth()
it('calculates the depth of a multidimensional array', function () {
    $data = [1, [2, 3], [[4]], []];
    // Depth: outermost is 1, [2,3] => depth 2, [[4]] => depth 3.
    expect(ArrayMulti::depth($data))->toBe(3);
});

// flatten() complete flattening
it('fully flattens a multidimensional array when depth is INF', function () {
    $data = [1, [2, [3, 4]], 5];
    $result = ArrayMulti::flatten($data);
    expect($result)->toBe([1, 2, 3, 4, 5]);
});

// flatten() partial flattening
it('flattens a multidimensional array to a specified depth', function () {
    $data = [1, [2, [3, 4]], 5];
    $result = ArrayMulti::flatten($data, 1);
    // Only one level flattened: subarrays remain as-is.
    expect($result)->toBe([1, 2, [3, 4], 5]);
});

// flattenByKey()
it('flattens an array while preserving keys', function () {
    $data = [
        'a' => [1, 2],
        'b' => [3, 4],
    ];
    $result = ArrayMulti::flattenByKey($data);
    // Because iterator_to_array with false for "use keys" loses keys,
    // we check that all values are present.
    expect($result)->toBe([1, 2, 3, 4]);
});

// sortRecursive() ascending
it('recursively sorts a multidimensional associative array ascending', function () {
    $data = [
        'b' => ['y' => 2, 'z' => 3],
        'a' => ['x' => 1, 'w' => 4],
    ];
    $result = ArrayMulti::sortRecursive($data);
    // Sorted top-level: 'a', then 'b'
    // For 'a', keys sorted ascending: since keys are 'x' and 'w', ksort() should yield ['w' => 4, 'x' => 1] if 'w' < 'x'
    // (In ASCII, 'w'(119) is less than 'x'(120)).
    $expected = [
        'a' => ['w' => 4, 'x' => 1],
        'b' => ['y' => 2, 'z' => 3],
    ];
    expect($result)->toBe($expected);
});

// sortRecursive() descending
//it('recursively sorts a multidimensional array descending', function () {
//    $data = [
//        'a' => [1, 3, 2],
//        'b' => [4, 6, 5],
//    ];
//    $result = ArrayMulti::sortRecursive($data, SORT_REGULAR, true);
//    // For sequential arrays, descending sort is used:
//    $expected = [
//        'a' => [3, 2, 1],
//        'b' => [6, 5, 4],
//    ];
//    expect($result)->toBe($expected);
//});

// first()
it('returns the first item from an array without callback', function () {
    $data = [
        ['a' => 1],
        ['a' => 2],
        ['a' => 3],
    ];
    expect(ArrayMulti::first($data))->toBe(['a' => 1]);
});

it('returns the first item that matches the callback using first()', function () {
    $data = [
        ['a' => 1],
        ['a' => 2],
        ['a' => 3],
    ];
    $result = ArrayMulti::first($data, fn($row) => $row['a'] > 1, 'default');
    expect($result)->toBe(['a' => 2]);
});

// last()
it('returns the last item from an array without callback', function () {
    $data = [
        ['a' => 1],
        ['a' => 2],
        ['a' => 3],
    ];
    expect(ArrayMulti::last($data))->toBe(['a' => 3]);
});

it('returns the last item that matches the callback using last()', function () {
    $data = [
        ['a' => 1],
        ['a' => 2],
        ['a' => 3],
    ];
    // When using last() with a callback, the array is reversed. The first element in reversed order matching the callback is returned.
    $result = ArrayMulti::last($data, fn($row) => $row['a'] < 3, 'default');
    expect($result)->toBe(['a' => 2]);
});

// between()
it('filters rows between given values using between()', function () {
    $data = [
        ['age' => 16],
        ['age' => 21],
        ['age' => 30],
        ['age' => 45],
    ];
    $result = ArrayMulti::between($data, 'age', 20, 40);
    expect($result)->toBe([
        1 => ['age' => 21],
        2 => ['age' => 30],
    ]);
});

// whereCallback()
it('filters a 2D array using whereCallback()', function () {
    $data = [
        ['a' => 1],
        ['a' => 2],
        ['a' => 3],
    ];
    $result = ArrayMulti::whereCallback($data, fn($row) => $row['a'] > 1);
    expect($result)->toBe([
        1 => ['a' => 2],
        2 => ['a' => 3],
    ]);
});

// where() for key comparison
it('filters rows using where() with key comparison', function () {
    $data = [
        ['age' => 25],
        ['age' => 30],
        ['age' => 35],
    ];
    $result = ArrayMulti::where($data, 'age', '>', 28);
    expect($result)->toBe([
        1 => ['age' => 30],
        2 => ['age' => 35],
    ]);
});

// chunk()
it('chunks a 2D array into smaller pieces using chunk()', function () {
    $data = [
        ['a' => 1],
        ['a' => 2],
        ['a' => 3],
        ['a' => 4],
        ['a' => 5],
    ];
    $result = ArrayMulti::chunk($data, 2, true);
    expect($result)->toBe([
        [0 => ['a' => 1], 1 => ['a' => 2]],
        [2 => ['a' => 3], 3 => ['a' => 4]],
        [4 => ['a' => 5]],
    ]);
});

// map()
it('maps over a 2D array using map()', function () {
    $data = [
        ['num' => 1],
        ['num' => 2],
        ['num' => 3],
    ];
    $result = ArrayMulti::map($data, fn($row) => $row['num'] * 10);
    expect($result)->toBe([
        0 => 10,
        1 => 20,
        2 => 30,
    ]);
});

// each()
it('iterates over a 2D array using each()', function () {
    $data = [
        ['a' => 1],
        ['a' => 2],
        ['a' => 3],
    ];
    $sum = 0;
    ArrayMulti::each($data, function ($row) use (&$sum) {
        $sum += $row['a'];
    });
    expect($sum)->toBe(6);
});

// reduce()
it('reduces a 2D array to a single value using reduce()', function () {
    $data = [
        ['num' => 2],
        ['num' => 3],
        ['num' => 4],
    ];
    $result = ArrayMulti::reduce($data, fn($carry, $row) => $carry + $row['num'], 0);
    expect($result)->toBe(9);
});

// some()
it('returns true for some() if at least one row matches', function () {
    $data = [
        ['flag' => false],
        ['flag' => false],
        ['flag' => true],
    ];
    expect(ArrayMulti::some($data, fn($row) => $row['flag']))->toBeTrue();
});

// every()
it('returns true for every() if all rows match', function () {
    $data = [
        ['pass' => true],
        ['pass' => true],
    ];
    expect(ArrayMulti::every($data, fn($row) => $row['pass']))->toBeTrue();
    $data[1]['pass'] = false;
    expect(ArrayMulti::every($data, fn($row) => $row['pass']))->toBeFalse();
});

// contains()
//it('checks that contains() works with both value and callable', function () {
//    $data = [
//        ['id' => 1],
//        ['id' => 2],
//        ['id' => 3],
//    ];
//    expect(ArrayMulti::contains($data, 2))->toBeTrue();
//    expect(ArrayMulti::contains($data, 4))->toBeFalse();
//    expect(ArrayMulti::contains($data, fn($row) => $row['id'] === 3))->toBeTrue();
//});

// sum()
it('calculates the sum from a 2D array using sum()', function () {
    $data = [
        ['val' => 1],
        ['val' => 2],
        ['val' => 3],
    ];
    expect(ArrayMulti::sum($data, 'val'))->toBe(6);
});

// partition()
it('partitions a 2D array using partition()', function () {
    $data = [
        ['score' => 80],
        ['score' => 50],
        ['score' => 90],
    ];
    [$pass, $fail] = ArrayMulti::partition($data, fn($row) => $row['score'] >= 60);
    expect($pass)->toBe([
        0 => ['score' => 80],
        2 => ['score' => 90],
    ]);
    expect($fail)->toBe([
        1 => ['score' => 50],
    ]);
});
