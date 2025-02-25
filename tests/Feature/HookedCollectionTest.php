<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Collection\HookedCollection;

it('applies on get hook for a specific offset', function () {
    $collection = new HookedCollection(['title' => 'Hello']);

    // Hook to transform the title to uppercase on get
    $collection->onGet('title', fn($value) => strtoupper($value));

    // Normal offsetGet triggers the hook:
    expect($collection['title'])->toBe('HELLO');
});

it('applies on set hook for a specific offset', function () {
    $collection = new HookedCollection();

    // Hook to append "!!!" to any new value set for offset "shout"
    $collection->onSet('shout', fn($value) => $value . '!!!');

    $collection['shout'] = 'Hey';
    expect($collection['shout'])->toBe('Hey!!!');
});

it('still behaves like a normal collection otherwise', function () {
    $collection = new HookedCollection();
    $collection['test'] = 123;
    expect($collection['test'])->toBe(123);
});
