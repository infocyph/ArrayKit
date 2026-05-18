<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Array\ArrayShape;

it('validates basic array shapes', function () {
    $row = [
        'id' => 10,
        'email' => 'a@example.com',
        'roles' => ['admin', 'editor'],
    ];

    expect(ArrayShape::require($row, [
        'id' => 'int',
        'email' => 'string',
        'roles' => 'list<string>',
    ]))->toBe($row);
});

it('supports optional shape keys and throws on mismatches', function () {
    $row = [
        'id' => 10,
        'email' => 'a@example.com',
    ];

    expect(ArrayShape::require($row, [
        'id' => 'int',
        'email' => 'string',
        'roles?' => 'list<string>',
    ]))->toBe($row)
        ->and(fn () => ArrayShape::require($row, ['id' => 'string']))->toThrow(InvalidArgumentException::class);
});
