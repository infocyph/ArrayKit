<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Array\BaseArrayHelper;

it('detects multi-dimensional arrays correctly', function () {
    expect(BaseArrayHelper::isMultiDimensional([[1], [2]]))
        ->toBeTrue()
        ->and(BaseArrayHelper::isMultiDimensional([1, 2, 3]))->toBeFalse();
});

it('wraps a non-array value', function () {
    $wrapped = BaseArrayHelper::wrap('hello');
    expect($wrapped)->toBe(['hello']);
});

it('preserves every falsey value except null when wrapping', function (mixed $value, array $expected) {
    expect(BaseArrayHelper::wrap($value))->toBe($expected);
})->with([
    'null' => [null, []],
    'empty array' => [[], []],
    'false' => [false, [false]],
    'integer zero' => [0, [0]],
    'float zero' => [0.0, [0.0]],
    'negative float zero' => [-0.0, [-0.0]],
    'numeric zero string' => ['0', ['0']],
    'empty string' => ['', ['']],
]);

it('checks if at least one item meets a condition', function () {
    $data = [1, 2, 3];
    $res = BaseArrayHelper::haveAny($data, fn ($val) => $val > 2);
    expect($res)->toBeTrue();
});

it('checks if all items meet a condition', function () {
    $data = [2, 4, 6];
    $res = BaseArrayHelper::isAll($data, fn ($val) => $val % 2 === 0);
    expect($res)->toBeTrue();
});

it('uses normal truthy callback semantics for any and all checks', function () {
    expect(BaseArrayHelper::haveAny([0, 2], static fn(int $value): int => $value))
        ->toBeTrue()
        ->and(BaseArrayHelper::isAll([1, 2], static fn(int $value): int => $value))->toBeTrue()
        ->and(BaseArrayHelper::isAll([1, 0], static fn(int $value): int => $value))->toBeFalse();
});

it('finds the first key matching a callback', function () {
    $data = ['a' => 10, 'b' => 15, 'c' => 20];
    $key = BaseArrayHelper::findKey($data, fn ($val) => $val > 10);
    expect($key)->toBe('b')
        ->and(BaseArrayHelper::findKey([0, 2], fn (int $value): int => $value))->toBe(1);
});

it('rejects a zero range step', function () {
    expect(fn () => BaseArrayHelper::range(1, 5, 0))->toThrow(InvalidArgumentException::class);
});
