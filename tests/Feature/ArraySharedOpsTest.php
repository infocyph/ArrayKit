<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Array\ArraySharedOps;

it('iterates with each and stops when callback returns false', function () {
    $visited = [];

    $result = ArraySharedOps::each([10, 20, 30], function (int $value) use (&$visited) {
        $visited[] = $value;

        return $value !== 20;
    });

    expect($visited)->toBe([10, 20])
        ->and($result)->toBe([10, 20, 30]);
});

it('evaluates every with callback', function () {
    expect(ArraySharedOps::every([2, 4, 6], fn (int $value): bool => $value % 2 === 0))->toBeTrue()
        ->and(ArraySharedOps::every([2, 3, 6], fn (int $value): bool => $value % 2 === 0))->toBeFalse();
});

it('partitions arrays by callback result', function () {
    [$pass, $fail] = ArraySharedOps::partition(
        ['a' => 10, 'b' => 25, 'c' => 30],
        fn (int $value): bool => $value >= 20,
    );

    expect($pass)->toBe(['b' => 25, 'c' => 30])
        ->and($fail)->toBe(['a' => 10]);
});

it('supports skip, skipWhile, and skipUntil with key preservation', function () {
    $data = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4];

    expect(ArraySharedOps::skip($data, 2))->toBe(['c' => 3, 'd' => 4])
        ->and(ArraySharedOps::skipWhile($data, fn (int $value): bool => $value < 3))->toBe(['c' => 3, 'd' => 4])
        ->and(ArraySharedOps::skipUntil($data, fn (int $value): bool => $value === 3))->toBe(['c' => 3, 'd' => 4]);
});

it('normalizes array keys from mixed values', function () {
    $stringable = new class() {
        public function __toString(): string
        {
            return 'obj-key';
        }
    };

    expect(ArraySharedOps::normalizeArrayKey(9))->toBe(9)
        ->and(ArraySharedOps::normalizeArrayKey('x'))->toBe('x')
        ->and(ArraySharedOps::normalizeArrayKey(true))->toBe('1')
        ->and(ArraySharedOps::normalizeArrayKey(1.5))->toBe('1.5')
        ->and(ArraySharedOps::normalizeArrayKey(null))->toBe('')
        ->and(ArraySharedOps::normalizeArrayKey(['a' => 1]))->toBe('{"a":1}')
        ->and(ArraySharedOps::normalizeArrayKey($stringable))->toBe('obj-key');
});
