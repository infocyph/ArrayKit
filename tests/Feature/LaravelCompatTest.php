<?php

declare(strict_types=1);

use Infocyph\ArrayKit\LaravelCompat\Arr;
use Infocyph\ArrayKit\LaravelCompat\Collection as CompatCollection;

it('provides Arr compatibility helpers', function () {
    $data = ['user' => ['name' => 'Alice']];

    Arr::set($data, 'user.role', 'admin');

    expect(Arr::get($data, 'user.name'))->toBe('Alice')
        ->and(Arr::has($data, 'user.role'))->toBeTrue()
        ->and(Arr::hasAny($data, ['user.email', 'user.role']))->toBeTrue()
        ->and(Arr::only($data['user'], ['name']))->toBe(['name' => 'Alice'])
        ->and(Arr::except($data['user'], ['role']))->toBe(['name' => 'Alice']);
});

it('provides a Laravel-compatible Collection class', function () {
    $collection = new CompatCollection([1, 2, 3, 4]);

    expect($collection->filter(fn (int $v) => $v % 2 === 0)->all())->toBe([1 => 2, 3 => 4]);
});
