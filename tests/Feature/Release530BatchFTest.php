<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Collection\LazyCollection;
use Infocyph\ArrayKit\Config\Config;
use Infocyph\Runwire\Exception\CancelledException;
use Infocyph\Runwire\RequestContext;
use Infocyph\Runwire\Runtime\Enum\CancellationReason;
use Infocyph\Runwire\Runtime\Enum\RuntimeDriver;
use Infocyph\Runwire\Runtime\RuntimeCapabilityResolver;
use Infocyph\Runwire\Runtime\RuntimeEnvironment;
use Infocyph\Runwire\RuntimeContext;
use Infocyph\Runwire\RuntimeOptions;

function batchFRuntime(RuntimeDriver $driver): RuntimeContext
{
    $environment = new RuntimeEnvironment(
        sapi: $driver === RuntimeDriver::FPM ? 'fpm-fcgi' : 'cli',
        hostedDrivers: [$driver],
        availableDrivers: [$driver],
        frankenPhpWorkerMode: $driver === RuntimeDriver::FRANKENPHP,
        opcacheAvailable: true,
        opcacheEnabled: true,
        opcacheCliEnabled: true,
    );

    $capabilities = (new RuntimeCapabilityResolver())->resolve(
        $driver,
        $environment,
        new RuntimeOptions(driver: $driver),
    );

    return RuntimeContext::fromCapabilities(
        $capabilities,
        mode: 'acceptance',
        workerSlot: 0,
        generation: 1,
    );
}

it('keeps lazy traversal semantics across supported Runwire host capability sets', function (RuntimeDriver $driver) {
    $runtime = batchFRuntime($driver);
    $request = RequestContext::create($runtime);

    $result = LazyCollection::from([1, 2, 3])
        ->withRunwire($runtime, $request, checkpointEvery: 1)
        ->mapLazy(static fn(int $value): int => $value * 2)
        ->all();

    expect($result)->toBe([2, 4, 6])
        ->and($request->completed())->toBeFalse();

    $request->cancel(CancellationReason::HOST_CANCELLED);

    expect(fn () => LazyCollection::from([1])
        ->withRunwire($runtime, $request, checkpointEvery: 1)
        ->all())->toThrow(CancelledException::class)
        ->and($request->completed())->toBeFalse();
})->with([
    RuntimeDriver::FPM,
    RuntimeDriver::FRANKENPHP,
    RuntimeDriver::ROADRUNNER,
    RuntimeDriver::SWOOLE,
]);

it('does not retain request-scoped config or cancellation state across repeated worker cycles', function () {
    $runtime = batchFRuntime(RuntimeDriver::ROADRUNNER);

    for ($cycle = 0; $cycle < 250; $cycle++) {
        $tenant = 'tenant-' . $cycle;
        $config = new Config();
        $config->set('request.tenant', $tenant);
        $config->snapshot();

        expect($config->get('request.tenant'))->toBe($tenant);

        $config->set('request.tenant', 'mutated-' . $cycle);
        expect($config->restore())->toBeTrue()
            ->and($config->get('request.tenant'))->toBe($tenant);

        $request = RequestContext::create($runtime);
        $collection = LazyCollection::fromFactory(
            static fn(): array => [
                ['tenant' => $tenant, 'value' => 1],
                ['tenant' => $tenant, 'value' => 2],
                ['tenant' => $tenant, 'value' => 3],
            ],
        )->withRunwire($runtime, $request, checkpointEvery: 1);

        if (($cycle % 11) === 0) {
            $seen = [];

            try {
                $collection
                    ->mapLazy(function (array $row) use (&$seen, $request): array {
                        $seen[] = $row['tenant'];
                        if (count($seen) === 2) {
                            $request->cancel(CancellationReason::HOST_CANCELLED);
                        }

                        return $row;
                    })
                    ->all();

                test()->fail('Cancelled worker cycle unexpectedly completed.');
            } catch (CancelledException) {
                expect($seen)->toBe([$tenant, $tenant]);
            }
        } else {
            $rows = $collection->all();

            expect($rows)->toHaveCount(3)
                ->and(array_unique(array_column($rows, 'tenant')))->toBe([$tenant]);
        }

        expect($request->completed())->toBeFalse();

        unset($collection, $config, $request);
        if (($cycle % 25) === 0) {
            gc_collect_cycles();
        }
    }

    $fresh = new Config();
    expect($fresh->get('request.tenant', 'missing'))->toBe('missing');

    $freshRequest = RequestContext::create($runtime);
    expect(LazyCollection::from(['fresh'])
        ->withRunwire($runtime, $freshRequest, checkpointEvery: 1)
        ->all())->toBe(['fresh'])
        ->and($freshRequest->cancelled())->toBeFalse();
});
