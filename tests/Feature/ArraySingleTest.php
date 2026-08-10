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
    $data = ['name' => 'Alice', 'age' => 30, 'job' => 'Developer'];
    $subset = ArraySingle::only($data, ['name', 'job']);
    expect($subset)->toBe(['name' => 'Alice', 'job' => 'Developer']);
});

it('can detect if array is a list', function () {
    $list = [10, 20, 30];
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

it('ignores non-numeric values when calculating average', function () {
    $values = [2, '4', 'x', null, 6];

    expect(ArraySingle::avg($values))->toBe(4);
});

it('ignores non-numeric values when calculating median', function () {
    expect(ArraySingle::median([1, '2', 7.5, 'ignore', null]))->toBe(2)
        ->and(ArraySingle::median(['ignore', null]))->toBe(0);
});

it('searches an array for a callback condition', function () {
    $data = [1, 2, 3, 4];
    $key = ArraySingle::search($data, fn ($value) => $value === 3);
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

it('keeps strict membership results stable across adaptive strategy boundaries', function () {
    $haystack = range(0, 999);
    $small = range(10, 24);
    $large = range(10, 521);

    expect(ArraySingle::containsAll($haystack, $small, true))->toBeTrue()
        ->and(ArraySingle::containsAll($haystack, $large, true))->toBeTrue()
        ->and(ArraySingle::containsAny($haystack, [...range(1000, 1190), 500], true))->toBeTrue()
        ->and(ArraySingle::containsAny($haystack, range(1000, 1191), true))->toBeFalse()
        ->and(ArraySingle::intersect($haystack, $large, true))->toBe(array_combine($large, $large))
        ->and(ArraySingle::diff($haystack, $large, true))->toHaveCount(488);
});

it('preserves PHP loose comparison semantics for mixed scalar membership', function () {
    expect(ArraySingle::containsAny(['enabled'], [true]))->toBeTrue()
        ->and(ArraySingle::containsAny([null], ['0']))->toBeFalse()
        ->and(ArraySingle::containsAll([''], [null]))->toBeTrue();
});

it('keeps non-finite floats distinct in strict set operations', function () {
    $longValue = ['payload' => str_repeat('x', 128)];
    $values = [INF, -INF, NAN, NAN, $longValue, $longValue];

    expect(ArraySingle::unique($values, true))->toHaveCount(5)
        ->and(ArraySingle::duplicates([INF, -INF, NAN, NAN]))->toBe([])
        ->and(ArraySingle::containsAny([INF], [-INF], true))->toBeFalse();
});

it('isolates deterministic shuffle state from the global Mersenne Twister', function () {
    $values = range(1, 20);

    expect(ArraySingle::shuffle($values, 12345))
        ->toBe(ArraySingle::shuffle($values, 12345))
        ->not->toBe(ArraySingle::shuffle($values, 54321));

    mt_srand(9876);
    $first = mt_rand();
    $second = mt_rand();

    mt_srand(9876);
    expect(mt_rand())->toBe($first);
    ArraySingle::shuffle($values, 12345);
    expect(mt_rand())->toBe($second);
});

it('does not retry callbacks that throw argument count errors internally', function () {
    $calls = 0;
    $exception = null;

    try {
        ArraySingle::sum([1], function (int $value, int $key) use (&$calls): never {
            expect([$value, $key])->toBe([1, 0]);
            $calls++;

            throw new ArgumentCountError('callback failure');
        });
    } catch (ArgumentCountError $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(ArgumentCountError::class)
        ->and($calls)->toBe(1);
});

it('sums the array using sum()', function () {
    $arr = [1, 2, 3];
    expect(ArraySingle::sum($arr))
        ->toBe(6)
        ->and(ArraySingle::sum($arr, fn ($v) => $v * 2))
        ->toBe(12);
});

it('ignores non-numeric values in sum() and supports callback keys', function () {
    $arr = [2 => 1, 4 => '2', 8 => 'x'];

    expect(ArraySingle::sum($arr))->toBe(3)
        ->and(ArraySingle::sum($arr, fn ($value, $key) => is_numeric($value) ? ((float) $value + $key) : null))
        ->toBe(9.0);
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
        ->toBe([0 => 1, 1 => 2, 3 => 3, 5 => 4])
        ->and(ArraySingle::unique([1, '1', 2, 3], true))
        ->toBe([1, '1', 2, 3]); // Strict comparison
});

it('supports loose and strict duplicate detection explicitly', function () {
    $values = [1, '1'];

    expect(ArraySingle::duplicates($values))->toBe([1])
        ->and(ArraySingle::duplicates($values, true))->toBe([]);
});

it('preserves large integer precision in numeric selection and accumulation', function () {
    $low = 9007199254740992;
    $high = 9007199254740993;

    expect(ArraySingle::min([$high, $low]))->toBe($low)
        ->and(ArraySingle::max([$low, $high]))->toBe($high)
        ->and(ArraySingle::median([$high]))->toBe($high)
        ->and(ArraySingle::sum([$high, -$low]))->toBe(1)
        ->and(ArraySingle::maxBy(
            [['score' => $low], ['score' => $high]],
            static fn(array $row): int => $row['score'],
        ))->toBe(['score' => $high]);
});

it('treats callable strings as values in ambiguous value-or-callback APIs', function () {
    expect(ArraySingle::contains(['trim', 'other'], 'trim'))->toBeTrue()
        ->and(ArraySingle::search(['trim', 'other'], 'trim'))->toBe(0)
        ->and(ArraySingle::reject(['trim', 'other'], 'trim'))->toBe([1 => 'other']);
});

it('handles unique() with mixed values in loose and strict modes', function () {
    $arr = [1, '1', true, [1], ['1']];

    expect(ArraySingle::unique($arr))->toBe([0 => 1, 3 => [1]])
        ->and(ArraySingle::unique($arr, true))->toBe([1, '1', true, [1], ['1']]);
});

it('preserves original keys when removing duplicates', function () {
    $assoc = ['a' => 1, 'b' => 1, 'c' => '1', 'd' => 2];

    expect(ArraySingle::unique($assoc))->toBe([
        'a' => 1,
        'd' => 2,
    ])->and(ArraySingle::unique($assoc, true))->toBe([
        'a' => 1,
        'c' => '1',
        'd' => 2,
    ]);
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

it('evaluates positivity and negativity using numeric values only', function () {
    expect(ArraySingle::isPositive(['2', 3, 'x']))->toBeTrue()
        ->and(ArraySingle::isPositive(['2', 0, 'x']))->toBeFalse()
        ->and(ArraySingle::isNegative(['-2', -3, 'x']))->toBeTrue()
        ->and(ArraySingle::isNegative(['-2', 1, 'x']))->toBeFalse()
        ->and(ArraySingle::isPositive(['x', null]))->toBeFalse()
        ->and(ArraySingle::isNegative(['x', null]))->toBeFalse();
});

it('validates paginate() arguments', function () {
    expect(fn () => ArraySingle::paginate([1, 2, 3], 0, 2))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ArraySingle::paginate([1, 2, 3], 1, 0))->toThrow(InvalidArgumentException::class);
});

it('throws for invalid structural arguments and derived keys', function () {
    expect(fn () => ArraySingle::chunk([1, 2], 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ArraySingle::combine(['a'], [1, 2]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ArraySingle::countBy([1], fn () => null))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ArraySingle::rekey(['a' => 1], fn () => false))->toThrow(InvalidArgumentException::class);
});

it('uses ordinary callback truthiness for search', function () {
    expect(ArraySingle::search([0, 2], fn (int $value): int => $value))->toBe(1);
});

it('calculates mode from integer and string values only', function () {
    expect(ArraySingle::mode([1, 1, true, true, null, 1.0, ['value']]))->toBe([1]);
});

it('paginates arrays for valid page and per-page values', function () {
    $arr = [1, 2, 3, 4, 5];

    expect(ArraySingle::paginate($arr, 2, 2))->toBe([2 => 3, 3 => 4]);
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
