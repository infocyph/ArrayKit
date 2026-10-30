<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Array\ArraySingle;
use Infocyph\ArrayKit\Array\BaseArrayHelper;
use Infocyph\ArrayKit\ArrayKit;
use Infocyph\ArrayKit\Collection\Collection;
use Infocyph\ArrayKit\Config\Config;
use Infocyph\ArrayKit\Tests\Fixtures\Release532IntMode;
use Infocyph\ArrayKit\Tests\Fixtures\Release532StringMode;

it('validates positive random counts even for empty arrays', function () {
    expect(fn () => BaseArrayHelper::random([], 1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ArrayKit::helper()->random([], 2))->toThrow(InvalidArgumentException::class)
        ->and(BaseArrayHelper::random([]))->toBeNull()
        ->and(BaseArrayHelper::random([], 0))->toBe([]);
});

it('does not assign either sign to NaN while retaining finite and infinite signs', function () {
    expect(ArraySingle::isPositive([NAN]))->toBeFalse()
        ->and(ArraySingle::isNegative([NAN]))->toBeFalse()
        ->and(ArrayKit::single()->isPositive([1, NAN]))->toBeFalse()
        ->and(ArrayKit::single()->isNegative([-1, NAN]))->toBeFalse()
        ->and(ArraySingle::isPositive([INF]))->toBeTrue()
        ->and(ArraySingle::isNegative([-INF]))->toBeTrue()
        ->and(ArraySingle::isPositive([1, 'ignored']))->toBeTrue();
});

it('keeps finite medians finite after overflow-safe midpoint calculation', function () {
    expect(ArraySingle::median([1e308, 1e308]))->toBe(1e308)
        ->and(ArrayKit::single()->median([-1e308, -1e308]))->toBe(-1e308)
        ->and(ArraySingle::median([-1e308, 1e308]))->toBe(0.0)
        ->and(ArraySingle::median([1, 2, 3]))->toBe(2)
        ->and(ArraySingle::median([1, 2]))->toBe(1.5);
});

it('uses the default for wrong enum backing scalar types without coercion', function () {
    $config = new Config();
    $config->loadArray(['int' => '1', 'string' => 1, 'valid_int' => 1, 'valid_string' => '2']);

    expect($config->getEnum('int', Release532IntMode::class, Release532IntMode::Two))
        ->toBe(Release532IntMode::Two)
        ->and($config->getEnum('string', Release532StringMode::class, Release532StringMode::Two))
        ->toBe(Release532StringMode::Two)
        ->and($config->getEnum('valid_int', Release532IntMode::class))
        ->toBe(Release532IntMode::One)
        ->and($config->getEnum('valid_string', Release532StringMode::class))
        ->toBe(Release532StringMode::Two);
});

it('preserves finite averages across intermediate overflow and float-to-int boundaries', function () {
    expect(ArraySingle::avg([1e308, 1e308]))->toBe(1e308)
        ->and(ArraySingle::avg([-1e308, -1e308]))->toBe(-1e308)
        ->and(ArrayKit::single()->avg([PHP_INT_MAX, PHP_INT_MAX]))->toBeFloat()
        ->and(ArraySingle::avg([PHP_INT_MAX, PHP_INT_MAX]) > 0)->toBeTrue()
        ->and(ArraySingle::avg([1e20]))->toBe(1e20)
        ->and(ArraySingle::avg([10, 20, 'ignored']))->toBe(15)
        ->and(is_nan(ArraySingle::avg([INF, -INF])))->toBeTrue();
});

it('forwards only callable public pipeline methods through collections', function () {
    $collection = Collection::make([3, 1, 2]);

    expect(fn () => $collection->numericAggregate(null, true))
        ->toThrow(BadMethodCallException::class)
        ->and(fn () => $collection->unrecognizedInternalMethod())
        ->toThrow(BadMethodCallException::class)
        ->and($collection->values()->all())->toBe([3, 1, 2]);
});

it('keeps finite extreme averages inside the input range', function (int $count) {
    expect(ArraySingle::avg(array_fill(0, $count, PHP_FLOAT_MAX)))->toBe(PHP_FLOAT_MAX)
        ->and(ArraySingle::avg(array_fill(0, $count, -PHP_FLOAT_MAX)))->toBe(-PHP_FLOAT_MAX);
})->with([3, 7, 11, 21, 100, 1000]);

it('retains small remainders when extreme finite values cancel during averaging', function (float $remainder) {
    $extremes = [PHP_FLOAT_MAX, PHP_FLOAT_MAX, -PHP_FLOAT_MAX, -PHP_FLOAT_MAX];
    expect(ArraySingle::avg([...$extremes, $remainder]))->toBe($remainder / 5)
        ->and(ArraySingle::avg([$remainder, ...$extremes]))->toBe($remainder / 5);
})->with([1.0, 1e-100, 1e-300, -1.0, -1e-100, -1e-300]);
