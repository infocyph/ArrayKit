<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Config\Config;

beforeEach(function () {
    $this->config = new Config();
});

it('applies get hooks only when getWithHooks is used', function () {
    $this->config->set('site.title', 'ArRayKit');
    $this->config->onGet('site.title', fn($value) => strtolower((string) $value));

    expect($this->config->get('site.title'))->toBe('ArRayKit')
        ->and($this->config->getWithHooks('site.title'))->toBe('arraykit');
});

it('applies set hooks only when setWithHooks is used', function () {
    $this->config->onSet('user.name', fn($value) => strtoupper((string) $value));

    $this->config->set('user.name', 'john');
    expect($this->config->get('user.name'))->toBe('john');

    $this->config->setWithHooks('user.name', 'alice');
    expect($this->config->get('user.name'))->toBe('ALICE');
});

it('supports hook-aware bulk set and bulk get operations', function () {
    $this->config->onSet('user.name', fn($value) => strtoupper((string) $value));
    $this->config->onGet('user.name', fn($value) => strtolower((string) $value));

    $this->config->setWithHooks([
        'user.name' => 'ALICE',
        'user.email' => 'alice@example.com',
    ]);

    expect($this->config->getWithHooks(['user.name', 'user.email']))->toBe([
        'user.name' => 'alice',
        'user.email' => 'alice@example.com',
    ]);
});

it('supports hook-aware fill without overwriting existing keys', function () {
    $this->config->set('app.name', 'ArrayKit');
    $this->config->onSet('app.name', fn($value) => strtoupper((string) $value));
    $this->config->onSet('app.env', fn($value) => strtoupper((string) $value));

    $this->config->fillWithHooks([
        'app.name' => 'should-not-replace',
        'app.env' => 'local',
    ]);

    expect($this->config->get('app.name'))->toBe('ArrayKit')
        ->and($this->config->get('app.env'))->toBe('LOCAL');
});
