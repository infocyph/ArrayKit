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

it('finds the first key matching a callback', function () {
    $data = ['a' => 10, 'b' => 15, 'c' => 20];
    $key = BaseArrayHelper::findKey($data, fn ($val) => $val > 10);
    expect($key)->toBe('b');
});
