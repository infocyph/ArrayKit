<?php

declare(strict_types=1);

use Infocyph\ArrayKit\traits\DTOTrait;

final class DTOTraitAddress
{
    use DTOTrait;
    public string $city = '';
}

final class DTOTraitUser
{
    use DTOTrait;
    public DTOTraitAddress $address;

    public function __construct()
    {
        $this->address = new DTOTraitAddress();
    }
}

it('can create a DTO from an array', function () {
    // Define a quick test class inline
    $dtoClass = new class {
        use DTOTrait;
        public string $name;
        public int $age;
    };

    $dto = $dtoClass::create(['name' => 'Alice', 'age' => 30, 'extra' => 'ignored']);

    // Cast to array using ->toArray()
    $data = $dto->toArray();
    expect($data)->toBe([
        'name' => 'Alice',
        'age'  => 30,
    ]);
});

it('can hydrate an existing DTO instance from an array', function () {
    $dto = new class {
        use DTOTrait;
        public string $name = '';
        public int $age = 0;
    };

    $dto->fromArray(['name' => 'Bob', 'age' => 28, 'ignored' => true]);

    expect($dto->toArray())->toBe([
        'name' => 'Bob',
        'age' => 28,
    ]);
});

it('supports hydrate mapping and scalar coercion', function () {
    $dto = new class {
        use DTOTrait;
        public string $name = '';
        public int $age = 0;
    };

    $dto->hydrate(['full_name' => 'Carol', 'age' => '33'], ['full_name' => 'name'], true);

    expect($dto->toArray())->toBe([
        'name' => 'Carol',
        'age' => 33,
    ]);
});

it('supports nested DTO hydration and deep export', function () {
    $dto = new DTOTraitUser();
    $dto->hydrateNested(['address' => ['city' => 'Paris']]);

    expect($dto->toArrayDeep())->toBe([
        'address' => ['city' => 'Paris'],
    ]);
});
