<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Array\ArrayMulti;
use Infocyph\ArrayKit\Array\ArraySingle;
use Infocyph\ArrayKit\Array\DotNotation;
use Infocyph\ArrayKit\Collection\Collection;

function batchCClosedResource(): mixed
{
    $resource = fopen('php://temp', 'r+');
    fclose($resource);

    return $resource;
}

it('preserves native strict membership semantics across lookup thresholds', function (int $valueCount) {
    $stored = batchCClosedResource();
    $candidate = batchCClosedResource();

    $values = range(1, max(1, $valueCount - 1));
    $values[] = $stored;

    $rows = [
        'candidate' => ['value' => $candidate],
        'stored' => ['value' => $stored],
    ];

    expect(ArrayMulti::whereIn($rows, 'value', $values, true))->toBe([
        'stored' => ['value' => $stored],
    ])->and(ArrayMulti::whereNotIn($rows, 'value', $values, true))->toBe([
        'candidate' => ['value' => $candidate],
    ])->and(ArrayMulti::firstWhereIn($rows, 'value', $values, true))->toBe([
        'value' => $stored,
    ]);
})->with([255, 256, 257]);

it('preserves strict membership for nested resources nan objects and scalar edge cases', function () {
    $left = batchCClosedResource();
    $right = batchCClosedResource();
    $object = new stdClass();
    $otherObject = new stdClass();

    $cases = [
        [['resource' => $right], ['resource' => $left], null],
        [NAN, NAN, 0],
        [$otherObject, $object, null],
        [null, null, 'x'],
        [false, 0, 'x'],
        [0, false, 'x'],
    ];

    foreach ($cases as [$candidate, $storedValue, $filler]) {
        $values = array_fill(0, 255, $filler);
        $values[] = $storedValue;
        $expected = in_array($candidate, $values, true);
        $actual = ArrayMulti::whereIn([['value' => $candidate]], 'value', $values, true);

        expect($actual !== [])->toBe($expected);
    }
});

it('keeps strict membership semantics through collection pipelines', function () {
    $stored = batchCClosedResource();
    $candidate = batchCClosedResource();
    $values = range(1, 255);
    $values[] = $stored;

    $collection = new Collection([
        ['value' => $candidate],
        ['value' => $stored],
    ]);

    expect($collection->process()->whereIn('value', $values, true)->all())->toBe([
        1 => ['value' => $stored],
    ]);
});

it('treats existing wildcard leaves as present regardless of leaf truthiness', function () {
    foreach ([[], null, false, 0, '', 'value'] as $leaf) {
        expect(DotNotation::matches(
            ['rows' => [['value' => $leaf]]],
            'rows.*.value',
        ))->toBeTrue();
    }

    expect(DotNotation::matches(
        ['rows' => [['missing' => true]]],
        'rows.*.value',
    ))->toBeFalse()
        ->and(DotNotation::matches(['rows' => []], 'rows.*.value'))->toBeFalse();
});

it('supports multiple wildcard presence and escaped literal wildcard keys', function () {
    $data = [
        'groups' => [
            [
                'rows' => [
                    ['value' => []],
                ],
            ],
        ],
        'literal' => [
            '*' => ['value' => null],
        ],
    ];

    expect(DotNotation::matches($data, 'groups.*.rows.*.value'))->toBeTrue()
        ->and(DotNotation::matches($data, 'literal.\\*.value'))->toBeTrue();
});

it('anchors SQL-like patterns at the true end of the value', function () {
    $rows = [
        'exact' => ['name' => 'admin'],
        'newline' => ['name' => "admin\n"],
        'internal-newline' => ['name' => "a\nb"],
        'meta' => ['name' => 'a.b'],
        'case' => ['name' => 'ADMIN'],
        'empty' => ['name' => ''],
    ];

    expect(ArrayMulti::whereLike($rows, 'name', 'admin'))->toBe([
        'exact' => ['name' => 'admin'],
        'case' => ['name' => 'ADMIN'],
    ])->and(ArrayMulti::whereLike($rows, 'name', 'admin', true))->toBe([
        'exact' => ['name' => 'admin'],
    ])->and(ArrayMulti::whereLike($rows, 'name', 'a%b', true))->toBe([
        'internal-newline' => ['name' => "a\nb"],
    ])->and(ArrayMulti::whereLike($rows, 'name', 'a_b', true))->toBe([
        'internal-newline' => ['name' => "a\nb"],
    ])->and(ArrayMulti::whereLike($rows, 'name', 'a.b', true))->toBe([
        'meta' => ['name' => 'a.b'],
    ])->and(ArrayMulti::whereLike($rows, 'name', '', true))->toBe([
        'empty' => ['name' => ''],
    ]);
});

it('surfaces PCRE execution failures instead of treating them as no match', function () {
    $previous = ini_get('pcre.backtrack_limit');
    ini_set('pcre.backtrack_limit', '1');

    try {
        expect(fn () => ArrayMulti::whereLike(
            [['name' => str_repeat('a', 200)]],
            'name',
            '%a%a%a%a%a%a%a%z',
            true,
        ))->toThrow(RuntimeException::class);
    } finally {
        ini_set('pcre.backtrack_limit', (string) $previous);
    }
});

it('keeps SQL-like matching equivalent through the collection pipeline', function () {
    $collection = new Collection([
        ['name' => 'admin'],
        ['name' => "admin\n"],
    ]);

    expect($collection->process()->whereLike('name', 'admin')->all())->toBe([
        0 => ['name' => 'admin'],
    ]);
});

it('paginates valid huge inputs without integer overflow', function () {
    $values = ['first' => 1, 'second' => 2, 'third' => 3];

    expect(ArraySingle::paginate([], 1, 10))->toBe([])
        ->and(ArraySingle::paginate($values, 1, 2))->toBe([
            'first' => 1,
            'second' => 2,
        ])->and(ArraySingle::paginate($values, 2, 2))->toBe([
            'third' => 3,
        ])->and(ArraySingle::paginate($values, 3, 2))->toBe([])
        ->and(ArraySingle::paginate($values, PHP_INT_MAX, 2))->toBe([])
        ->and(ArraySingle::paginate($values, 1, PHP_INT_MAX))->toBe($values);
});

it('keeps overflow-safe pagination through the collection pipeline', function () {
    $collection = new Collection(['first' => 1, 'second' => 2]);

    expect($collection->process()->paginate(PHP_INT_MAX, 2)->all())->toBe([]);
});
