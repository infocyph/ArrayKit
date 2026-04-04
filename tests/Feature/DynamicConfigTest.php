<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Config\DynamicConfig;

beforeEach(function () {
    // For simplicity, we create a new instance each time
    $this->dynamic = new DynamicConfig();
});

it('allows hooking on get operations', function () {
    // Suppose we set a value
    $this->dynamic->set('site.title', 'ArRayKit');

    // Then define a hook that lowercases on get
    $this->dynamic->onGet('site.title', fn($val) => strtolower($val));

    // Now retrieving it should be lowercased
    expect($this->dynamic->get('site.title'))->toBe('arraykit');
});

it('allows hooking on set operations', function () {
    // Hook that uppercases the value before storing
    $this->dynamic->onSet('user.name', fn($val) => strtoupper($val));

    $this->dynamic->set('user.name', 'john');
    // The stored value should be uppercase
    expect($this->dynamic->get('user.name'))->toBe('JOHN');
});

it('supports hooks with bulk set/get operations', function () {
    $this->dynamic->onSet('user.name', fn ($value) => strtoupper($value));
    $this->dynamic->onGet('user.name', fn ($value) => strtolower($value));

    $this->dynamic->set([
        'user.name' => 'alice',
        'user.email' => 'alice@example.com',
    ]);

    expect($this->dynamic->get(['user.name', 'user.email']))->toBe([
        'user.name' => 'alice',
        'user.email' => 'alice@example.com',
    ]);
});

it('supports bulk fill operations without overwriting existing keys', function () {
    $this->dynamic->set('app.name', 'ArrayKit');

    $this->dynamic->fill([
        'app.name' => 'ShouldNotReplace',
        'app.env' => 'local',
    ]);

    expect($this->dynamic->get('app.name'))
        ->toBe('ArrayKit')
        ->and($this->dynamic->get('app.env'))->toBe('local');
});
