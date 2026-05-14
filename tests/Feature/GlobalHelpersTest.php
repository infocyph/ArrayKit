<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Collection\Collection;
use Infocyph\ArrayKit\Collection\Pipeline;
use function Infocyph\ArrayKit\array_get as ns_array_get;
use function Infocyph\ArrayKit\array_set as ns_array_set;
use function Infocyph\ArrayKit\chain as ns_chain;
use function Infocyph\ArrayKit\collect as ns_collect;
use function Infocyph\ArrayKit\compare as ns_compare;

it('guards global helper declarations with function_exists checks', function () {
    $source = file_get_contents(__DIR__ . '/../../src/functions.php');

    expect($source)->toContain("if (!function_exists('compare'))")
        ->and($source)->toContain("if (!function_exists('array_get'))")
        ->and($source)->toContain("if (!function_exists('array_set'))")
        ->and($source)->toContain("if (!function_exists('collect'))")
        ->and($source)->toContain("if (!function_exists('chain'))");
});

it('provides namespaced helper alternatives', function () {
    $data = ['app' => ['name' => 'ArrayKit']];
    ns_array_set($data, 'app.env', 'local');

    expect(ns_array_get($data, 'app.name'))->toBe('ArrayKit')
        ->and(ns_array_get($data, 'app.env'))->toBe('local')
        ->and(ns_compare(10, 5, '>'))->toBeTrue()
        ->and(ns_collect([1, 2]))->toBeInstanceOf(Collection::class)
        ->and(ns_chain([1, 2, 3]))->toBeInstanceOf(Pipeline::class);
});
