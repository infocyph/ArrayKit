<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Collection\LazyCollection;
use Infocyph\Runwire\Coroutine\CoroutineRuntime;
use Infocyph\Runwire\Coroutine\CoroutineScope;
use Infocyph\Runwire\Exception\CancelledException;
use Infocyph\Runwire\RequestContext;
use Infocyph\Runwire\Runtime\Enum\CancellationReason;
use Infocyph\Runwire\Runtime\Enum\RuntimeDriver;
use Infocyph\Runwire\RuntimeCapabilities;
use Infocyph\Runwire\RuntimeContext;

function batchERuntime(bool $coroutines = false): RuntimeContext
{
    return RuntimeContext::fromCapabilities(
        new RuntimeCapabilities(
            RuntimeDriver::NATIVE,
            persistentProcess: true,
            persistentApplication: true,
            ownsEventLoop: $coroutines,
            runwireLoopAvailable: $coroutines,
            supportsRunwireCoroutines: $coroutines,
        ),
        'native',
        concurrent: $coroutines,
    );
}

function batchEForwardBinding(
    LazyCollection $collection,
    RuntimeContext $runtime,
    ?RequestContext $request = null,
    ?CoroutineScope $scope = null,
    int $checkpointEvery = 256,
): LazyCollection {
    return $collection->withRunwire($runtime, $request, $scope, $checkpointEvery);
}

it('keeps ordinary lazy collection usage independent from Runwire installation', function () {
    $root = dirname(__DIR__, 2);
    $source = addslashes($root . DIRECTORY_SEPARATOR . 'src');
    $code = <<<'PHP'
$source = '__SOURCE__';
spl_autoload_register(static function (string $class) use ($source): void {
    $prefix = 'Infocyph\\ArrayKit\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $path = $source . DIRECTORY_SEPARATOR . $relative . '.php';
    if (is_file($path)) {
        require $path;
    }
});

$result = \Infocyph\ArrayKit\Collection\LazyCollection::from(['a' => 1, 'b' => 2])->all();
exit($result === ['a' => 1, 'b' => 2] ? 0 : 1);
PHP;
    $code = str_replace('__SOURCE__', $source, $code);
    $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    expect(is_resource($process))->toBeTrue();

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    expect(proc_close($process))->toBe(0, $stdout . $stderr);
});

it('keeps installed but unbound lazy collections on the ordinary path', function () {
    expect(LazyCollection::from(['a' => 1, 'b' => 2])->all())->toBe([
        'a' => 1,
        'b' => 2,
    ]);
});

it('allows metadata-only runtime binding without starting runtime work', function () {
    $runtime = RuntimeContext::standalone();

    expect(LazyCollection::from([1, 2, 3])
        ->withRunwire($runtime, checkpointEvery: 1)
        ->all())->toBe([1, 2, 3]);
});

it('rejects completed or mismatched request contexts at the binding boundary', function () {
    $runtime = batchERuntime();
    $otherRuntime = batchERuntime();
    $request = RequestContext::create($runtime);

    expect(fn () => LazyCollection::from([1])->withRunwire($otherRuntime, $request))
        ->toThrow(LogicException::class, 'different runtime context');

    $request->complete();

    expect(fn () => LazyCollection::from([1])->withRunwire($runtime, $request))
        ->toThrow(LogicException::class, 'Completed Runwire request context');
});

it('validates the Runwire checkpoint interval once at binding', function () {
    $runtime = batchERuntime();

    expect(fn () => LazyCollection::from([1])->withRunwire($runtime, checkpointEvery: 0))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => LazyCollection::from([1])->withRunwire($runtime, checkpointEvery: 1_000_001))
        ->toThrow(InvalidArgumentException::class);
});

it('raises pre-cancelled request state before source factory consumption', function () {
    $runtime = batchERuntime();
    $request = RequestContext::create($runtime);
    $request->cancel(CancellationReason::HOST_CANCELLED);
    $factoryCalls = 0;

    $collection = LazyCollection::fromFactory(
        function () use (&$factoryCalls): array {
            $factoryCalls++;

            return [1, 2, 3];
        },
    )->withRunwire($runtime, $request, checkpointEvery: 1);

    expect(fn () => $collection->all())
        ->toThrow(CancelledException::class)
        ->and($factoryCalls)->toBe(0);
});

it('checks cancellation before advancing the source after a checkpoint boundary', function () {
    $runtime = batchERuntime();
    $request = RequestContext::create($runtime);
    $consumed = 0;
    $seen = [];

    $collection = LazyCollection::from((function () use (&$consumed) {
        foreach ([1, 2, 3, 4] as $value) {
            $consumed++;
            yield $value;
        }
    })())->withRunwire($runtime, $request, checkpointEvery: 2);

    $cursor = $collection->cursor();

    try {
        foreach ($cursor as $value) {
            $seen[] = $value;
            if ($value === 2) {
                $request->cancel(CancellationReason::HOST_CANCELLED);
            }
        }

        test()->fail('Traversal unexpectedly completed after request cancellation.');
    } catch (CancelledException) {
        expect($seen)->toBe([1, 2])
            ->and($consumed)->toBe(2);
    }
});

it('checks filtered-out upstream rows and stops before the next callback', function () {
    $runtime = batchERuntime();
    $request = RequestContext::create($runtime);
    $consumed = 0;
    $callbacks = 0;

    $collection = LazyCollection::from((function () use (&$consumed) {
        foreach ([1, 2, 3, 4] as $value) {
            $consumed++;
            yield $value;
        }
    })())
        ->withRunwire($runtime, $request, checkpointEvery: 2)
        ->filterLazy(function (int $value) use (&$callbacks, $request): bool {
            $callbacks++;
            if ($value === 2) {
                $request->cancel(CancellationReason::HOST_CANCELLED);
            }

            return false;
        });

    expect(fn () => $collection->all())
        ->toThrow(CancelledException::class)
        ->and($callbacks)->toBe(2)
        ->and($consumed)->toBe(2);
});

it('preserves and can explicitly rebind Runwire context through derived operations', function () {
    $runtime = batchERuntime();
    $first = RequestContext::create($runtime);
    $second = RequestContext::create($runtime);

    $derived = LazyCollection::from([1, 2, 3])
        ->withRunwire($runtime, $first, checkpointEvery: 1)
        ->filterLazy(static fn(int $value): bool => $value > 1)
        ->mapLazy(static fn(int $value): int => $value * 10);

    $first->cancel(CancellationReason::HOST_CANCELLED);

    expect(fn () => $derived->all())->toThrow(CancelledException::class)
        ->and($derived->withRunwire($runtime, $second, checkpointEvery: 1)->all())->toBe([
            1 => 20,
            2 => 30,
        ]);
});

it('keeps take zero lazy even when a cancelled request is bound', function () {
    $runtime = batchERuntime();
    $request = RequestContext::create($runtime);
    $request->cancel(CancellationReason::HOST_CANCELLED);
    $factoryCalls = 0;

    $collection = LazyCollection::fromFactory(
        function () use (&$factoryCalls): array {
            $factoryCalls++;

            return [1];
        },
    )->withRunwire($runtime, $request, checkpointEvery: 1);

    expect($collection->take(0)->all())->toBe([])
        ->and($factoryCalls)->toBe(0);
});

it('uses scope cancellation without requiring a request context', function () {
    $runtime = batchERuntime(true);
    $coroutines = new CoroutineRuntime();
    $turns = [];

    $result = $coroutines->run(function (CoroutineScope $scope) use ($runtime, &$turns): array {
        $scope->spawn(function () use (&$turns): void {
            $turns[] = 'sibling';
        });

        return LazyCollection::from([1, 2, 3])
            ->withRunwire($runtime, scope: $scope, checkpointEvery: 1)
            ->mapLazy(function (int $value) use (&$turns): int {
                $turns[] = 'map-' . $value;

                return $value;
            })
            ->all();
    });

    expect($result)->toBe([1, 2, 3])
        ->and($turns[0] ?? null)->toBe('sibling');
});

it('falls back without cooperative yielding when the runtime lacks the capability', function () {
    $runtime = batchERuntime(false);
    $coroutines = new CoroutineRuntime();

    $result = $coroutines->run(
        fn(CoroutineScope $scope): array => LazyCollection::from([1, 2, 3])
            ->withRunwire($runtime, scope: $scope, checkpointEvery: 1)
            ->all(),
    );

    expect($result)->toBe([1, 2, 3]);
});

it('treats a closed active-scope binding as terminal before source consumption', function () {
    $runtime = batchERuntime(true);
    $coroutines = new CoroutineRuntime();
    $closedScope = $coroutines->run(static fn(CoroutineScope $scope): CoroutineScope => $scope);
    $factoryCalls = 0;

    $collection = LazyCollection::fromFactory(
        function () use (&$factoryCalls): array {
            $factoryCalls++;

            return [1];
        },
    )->withRunwire($runtime, scope: $closedScope, checkpointEvery: 1);

    expect(fn () => $collection->all())
        ->toThrow(LogicException::class, 'Coroutine scope is already closed')
        ->and($factoryCalls)->toBe(0);
});

it('honors request cancellation inside an active coroutine scope without completing host context', function () {
    $runtime = batchERuntime(true);
    $request = RequestContext::create($runtime);
    $coroutines = new CoroutineRuntime();
    $consumed = 0;

    expect(fn () => $coroutines->runRequest(
        $request,
        function (CoroutineScope $scope) use ($runtime, $request, &$consumed): array {
            return LazyCollection::from((function () use (&$consumed) {
                foreach ([1, 2, 3] as $value) {
                    $consumed++;
                    yield $value;
                }
            })())
                ->withRunwire($runtime, $request, $scope, checkpointEvery: 2)
                ->mapLazy(function (int $value) use ($request): int {
                    if ($value === 2) {
                        $request->cancel(CancellationReason::HOST_CANCELLED);
                    }

                    return $value;
                })
                ->all();
        },
    ))->toThrow(CancelledException::class)
        ->and($consumed)->toBe(2)
        ->and($request->completed())->toBeFalse();
});

it('preserves source and callback exception identity under Runwire binding', function () {
    $runtime = batchERuntime();

    $source = LazyCollection::from((function () {
        yield 1;
        throw new DomainException('source');
    })())->withRunwire($runtime, checkpointEvery: 1);

    expect(fn () => $source->all())->toThrow(DomainException::class, 'source');

    $callback = LazyCollection::from([1])
        ->withRunwire($runtime, checkpointEvery: 1)
        ->mapLazy(static function (): never {
            throw new DomainException('callback');
        });

    expect(fn () => $callback->all())->toThrow(DomainException::class, 'callback');
});

it('supports explicit intermediary forwarding of the same Runwire instances', function () {
    $runtime = batchERuntime();
    $request = RequestContext::create($runtime);

    $bound = batchEForwardBinding(
        LazyCollection::from(['a' => 1, 'b' => 2]),
        $runtime,
        $request,
        checkpointEvery: 1,
    );

    expect($bound->all())->toBe(['a' => 1, 'b' => 2])
        ->and($request->completed())->toBeFalse();
});
