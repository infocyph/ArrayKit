<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Tests\Feature;

use Infocyph\ArrayKit\Tests\Fixtures\DTOTraitUser;
use Infocyph\ArrayKit\Tests\Fixtures\TestPersonDto;
use Infocyph\ArrayKit\Tests\Fixtures\TestVisibilityDto;

it('can create a DTO from an array', function () {
    $dto = TestPersonDto::create(['name' => 'Alice', 'age' => 30, 'extra' => 'ignored']);

    // Cast to array using ->toArray()
    $data = $dto->toArray();
    expect($data)->toBe([
        'name' => 'Alice',
        'age' => 30,
    ]);
});

it('can hydrate an existing DTO instance from an array', function () {
    $dto = new TestPersonDto();

    $dto->fromArray(['name' => 'Bob', 'age' => 28, 'ignored' => true]);

    expect($dto->toArray())->toBe([
        'name' => 'Bob',
        'age' => 28,
    ]);
});

it('supports hydrate mapping and scalar coercion', function () {
    $dto = new TestPersonDto();

    $dto->hydrate(['full_name' => 'Carol', 'age' => '33'], ['full_name' => 'name'], true);

    expect($dto->toArray())->toBe([
        'name' => 'Carol',
        'age' => 33,
    ]);
});

it('supports nested DTO hydration and deep export', function () {
    $dto = new DTOTraitUser;
    $dto->hydrateNested(['address' => ['city' => 'Paris']]);

    expect($dto->toArrayDeep())->toBe([
        'address' => ['city' => 'Paris'],
    ]);
});

it('hydrates and exports only public instance properties', function () {
    $dto = new TestVisibilityDto();

    $dto->hydrate([
        'shared' => 'changed',
        'nickname' => null,
        'protectedValue' => 'changed',
        'privateValue' => 'changed',
    ], coerce: true);

    expect($dto->toArray())->toBe(['nickname' => null])
        ->and($dto::$shared)->toBe('original');
});
