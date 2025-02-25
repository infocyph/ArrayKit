<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Collection\Collection;

it('can be instantiated with array data', function () {
    $collection = new Collection(['a' => 1, 'b' => 2]);
    expect($collection->items())->toBe(['a' => 1, 'b' => 2]);
});

it('supports array access', function () {
    $collection = new Collection();
    $collection['x'] = 42;
    expect($collection['x'])->toBe(42);
});

it('supports iteration', function () {
    $collection = new Collection(['a' => 1, 'b' => 2]);
    $keys = [];
    foreach ($collection as $key => $val) {
        $keys[] = $key;
    }
    expect($keys)->toBe(['a', 'b']);
});

//it('provides a merge method', function () {
//    $c1 = new Collection(['a' => 1]);
//    $c2 = new Collection(['b' => 2]);
//
//    $c1->merge($c2);
//    expect($c1->items())->toBe(['a' => 1, 'b' => 2]);
//});

it('can filter and return a new collection', function () {
    $collection = new Collection([1, 2, 3, 4]);
    $even       = $collection->filter(fn($val) => $val % 2 === 0);
    expect($even->get()->items())->toBe([1 => 2, 3 => 4]);
});
