<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Collection\Collection;

it('supports keyBy/indexBy/mapWithKeys/countBy in pipelines', function () {
    $rows = Collection::make([
        ['id' => 1, 'team' => 'A', 'score' => 30],
        ['id' => 2, 'team' => 'B', 'score' => 50],
        ['id' => 3, 'team' => 'A', 'score' => 40],
    ]);

    expect($rows->copy()->keyBy('id')->all())->toBe([
        1 => ['id' => 1, 'team' => 'A', 'score' => 30],
        2 => ['id' => 2, 'team' => 'B', 'score' => 50],
        3 => ['id' => 3, 'team' => 'A', 'score' => 40],
    ])
        ->and($rows->copy()->indexBy(fn (array $row) => 'r' . $row['id'])->all())->toBe([
            'r1' => ['id' => 1, 'team' => 'A', 'score' => 30],
            'r2' => ['id' => 2, 'team' => 'B', 'score' => 50],
            'r3' => ['id' => 3, 'team' => 'A', 'score' => 40],
        ])
        ->and($rows->copy()->mapWithKeys(fn (array $row) => [$row['id'] => $row['score']])->all())->toBe([1 => 30, 2 => 50, 3 => 40])
        ->and($rows->process()->countBy('team'))->toBe(['A' => 2, 'B' => 1]);
});

it('supports min/max/minBy/maxBy and set helpers in pipelines', function () {
    $numbers = Collection::make([1, 2, 2, 3]);

    expect($numbers->process()->min())->toBe(1)
        ->and($numbers->process()->max())->toBe(3)
        ->and($numbers->copy()->intersect([2, 4])->all())->toBe([1 => 2, 2 => 2])
        ->and($numbers->copy()->diff([2])->all())->toBe([0 => 1, 3 => 3])
        ->and($numbers->copy()->symmetricDiff([3, 4])->all())->toBe([1, 2, 2, 4])
        ->and($numbers->process()->same([2, 1, 2, 3]))->toBeTrue();

    $rows = Collection::make([
        ['id' => 1, 'score' => 10],
        ['id' => 2, 'score' => 40],
        ['id' => 3, 'score' => 20],
    ]);

    expect($rows->process()->min('score'))->toBe(10)
        ->and($rows->process()->max('score'))->toBe(40)
        ->and($rows->process()->minBy('score'))->toBe(['id' => 1, 'score' => 10])
        ->and($rows->process()->maxBy('score'))->toBe(['id' => 2, 'score' => 40])
        ->and($rows->process()->firstWhere('score', '>=', 20))->toBe(['id' => 2, 'score' => 40]);
});

it('supports values, rekey, deep merge helpers, and unwrap alias', function () {
    $collection = Collection::make(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $renamed = $collection->copy()->rekey(['first_name' => 'firstName'])->all();

    expect($renamed)->toBe([
        'firstName' => 'Ada',
        'last_name' => 'Lovelace',
    ]);

    $overlay = Collection::make(['db' => ['host' => 'localhost', 'opts' => ['timeout' => 5]]]);
    $merged = $overlay->copy()->overlay(['db' => ['opts' => ['ssl' => true]]])->all();
    expect($merged)->toBe([
        'db' => ['host' => 'localhost', 'opts' => ['timeout' => 5, 'ssl' => true]],
    ]);

    $single = Collection::make(['only']);
    expect($single->copy()->unwrap()->all())->toBe(['only'])
        ->and($single->copy()->unWrap()->all())->toBe(['only'])
        ->and($single->copy()->values()->all())->toBe(['only']);
});
