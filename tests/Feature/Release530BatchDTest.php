<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Collection\LazyCollection;
use Infocyph\ArrayKit\DTO\Concerns\DTOTrait;

class Release530BatchDAddress
{
    use DTOTrait;

    public string $city = '';
}

class Release530BatchDBase
{
    public string $base = 'base';
}

class Release530BatchDInherited extends Release530BatchDBase
{
    use DTOTrait;

    public string $name = 'child';
}

class Release530BatchDReadonly
{
    use DTOTrait;

    public readonly string $name;
}

class Release530BatchDUser
{
    use DTOTrait;

    public Release530BatchDAddress $address;

    public mixed $payload = null;
}

it('replays array-backed lazy collections without changing keys or values', function () {
    $source = ['first' => 1, 'second' => 2];
    $lazy = LazyCollection::from($source);

    expect($lazy->all())->toBe($source)
        ->and($lazy->all())->toBe($source)
        ->and($lazy->mapLazy(static fn(int $value): int => $value * 2)->all())->toBe([
            'first' => 2,
            'second' => 4,
        ]);
});

it('exports bounded DTO graphs while allowing shared acyclic objects', function () {
    $child = new Release530BatchDAddress();
    $child->city = 'Dhaka';

    $dto = new Release530BatchDUser();
    $dto->address = $child;
    $dto->payload = [
        'primary' => $child,
        'secondary' => $child,
    ];

    expect($dto->toArrayDeepGuarded())->toBe([
        'address' => ['city' => 'Dhaka'],
        'payload' => [
            'primary' => ['city' => 'Dhaka'],
            'secondary' => ['city' => 'Dhaka'],
        ],
    ]);
});

it('rejects self and mutual DTO cycles before deep export', function () {
    $self = new Release530BatchDUser();
    $self->address = new Release530BatchDAddress();
    $self->payload = $self;

    expect(fn () => $self->toArrayDeepGuarded())
        ->toThrow(RuntimeException::class, 'cyclic object reference');

    $left = new Release530BatchDUser();
    $left->address = new Release530BatchDAddress();
    $right = new Release530BatchDUser();
    $right->address = new Release530BatchDAddress();
    $left->payload = $right;
    $right->payload = $left;

    expect(fn () => $left->toArrayDeepGuarded())
        ->toThrow(RuntimeException::class, 'cyclic object reference');
});

it('enforces DTO graph depth and node budgets', function () {
    $dto = new Release530BatchDUser();
    $dto->address = new Release530BatchDAddress();
    $dto->payload = [
        'deep' => [
            'deeper' => [
                'value' => true,
            ],
        ],
    ];

    expect(fn () => $dto->toArrayDeepGuarded(maxDepth: 3))
        ->toThrow(RuntimeException::class, 'max depth')
        ->and(fn () => $dto->toArrayDeepGuarded(maxNodes: 3))
        ->toThrow(RuntimeException::class, 'max node count');
});

it('rejects cyclic hydration input before mutating the DTO', function () {
    $cycle = [];
    $cycle['self'] = &$cycle;

    $dto = new Release530BatchDUser();
    $dto->address = new Release530BatchDAddress();
    $dto->payload = 'before';

    expect(fn () => $dto->hydrateNestedGuarded(['payload' => $cycle]))
        ->toThrow(RuntimeException::class, 'cyclic array reference')
        ->and($dto->payload)->toBe('before');
});

it('hydrates typed nested DTOs through the guarded entry point', function () {
    $dto = new Release530BatchDUser();

    $dto->hydrateNestedGuarded([
        'address' => ['city' => 'Dhaka'],
        'payload' => ['roles' => ['admin', 'editor']],
    ]);

    expect($dto->address)->toBeInstanceOf(Release530BatchDAddress::class)
        ->and($dto->address->city)->toBe('Dhaka')
        ->and($dto->payload)->toBe(['roles' => ['admin', 'editor']]);
});

it('includes inherited public state in guarded deep export', function () {
    $dto = new Release530BatchDInherited();

    expect($dto->toArrayDeepGuarded())->toBe([
        'name' => 'child',
        'base' => 'base',
    ]);
});

it('preserves readonly hydration behavior when the trait owns the property scope', function () {
    $dto = new Release530BatchDReadonly();
    $dto->hydrateNestedGuarded(['name' => 'fixed']);

    expect($dto->name)->toBe('fixed')
        ->and($dto->toArrayDeepGuarded())->toBe(['name' => 'fixed']);
});

it('rejects invalid DTO graph limits explicitly', function () {
    $dto = new Release530BatchDInherited();

    expect(fn () => $dto->toArrayDeepGuarded(maxDepth: 0))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $dto->toArrayDeepGuarded(maxNodes: 0))
        ->toThrow(InvalidArgumentException::class);
});
