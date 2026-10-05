<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Array\ArrayMulti;
use Infocyph\ArrayKit\Array\DotNotation;
use Infocyph\ArrayKit\ArrayKit;
use Infocyph\ArrayKit\Collection\LazyCollection;
use Infocyph\ArrayKit\Config\LazyFileConfig;
use Infocyph\ArrayKit\DTO\DTO;
use Infocyph\Runwire\Coroutine\CoroutineRuntime;
use Infocyph\Runwire\Coroutine\CoroutineScope;
use Infocyph\Runwire\RequestContext;
use Infocyph\Runwire\Runtime\Enum\RuntimeDriver;
use Infocyph\Runwire\RuntimeCapabilities;
use Infocyph\Runwire\RuntimeContext;

beforeEach(function () {
    $this->reviewDirectory = sys_get_temp_dir() . '/arraykit-review-fix-' . bin2hex(random_bytes(6));
    mkdir($this->reviewDirectory);
    mkdir($this->reviewDirectory . '/source');
    mkdir($this->reviewDirectory . '/cache');
});

afterEach(function () {
    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->reviewDirectory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($entries as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($this->reviewDirectory);
});

function review530Source(string $directory, int $version): void
{
    foreach (['app', 'db'] as $namespace) {
        file_put_contents($directory . '/source/' . $namespace . '.php', "<?php return ['version' => {$version}];\n");
    }
}

function review530Config(string $directory): LazyFileConfig
{
    return new LazyFileConfig($directory . '/source', namespaceCacheDirectory: $directory . '/cache');
}

it('bounds missing direct lookups and default callbacks under the shared safe budget', function () {
    $keys = array_map(static fn(int $index): string => 'missing-' . $index, range(1, 10000));
    $calls = 0;
    $default = function () use (&$calls): string {
        $calls++;

        return 'default';
    };
    expect(DotNotation::getSafe([], $keys, $default, maxNodes: 2))->toHaveCount(2)
        ->and($calls)->toBe(2);
    $calls = 0;
    expect(fn () => DotNotation::getSafe([], $keys, $default, maxNodes: 2, throwOnTooDeep: true))
        ->toThrow(RuntimeException::class)
        ->and($calls)->toBe(2);
});

it('rejects unfinished throwing lookups and accepts an exactly complete budget', function () {
    expect(fn () => DotNotation::getSafe(['a' => 1, 'b' => 2], ['a', 'b'], maxNodes: 1, throwOnTooDeep: true))
        ->toThrow(RuntimeException::class)
        ->and(DotNotation::getSafe(['a' => 1, 'b' => 2], ['a', 'b'], maxNodes: 2, throwOnTooDeep: true))
        ->toBe(['a' => 1, 'b' => 2]);
});

it('does not compare cyclic children after guarded sort traversal is cut short', function (int $maxDepth, int $maxNodes) {
    $left = [];
    $left['self'] = &$left;
    $left['tail'] = 2;
    $right = [];
    $right['self'] = &$right;
    $right['tail'] = 1;
    $result = ArrayMulti::sortRecursiveGuarded([$left, $right], maxDepth: $maxDepth, maxNodes: $maxNodes);
    expect($result)->toHaveCount(2)
        ->and($result[0]['tail'])->toBe(2)
        ->and($result[1]['tail'])->toBe(1);
})->with([[1, 100], [256, 2]]);

it('leaves wide unvisited children in order rather than comparing them outside the sort budget', function () {
    $left = array_fill(0, 100000, 1);
    $right = $left;
    $right[99999] = 2;
    $result = ArrayMulti::sortRecursiveGuarded([$right, $left], maxDepth: 1, maxNodes: 2);
    expect($result[0][99999])->toBe(2)
        ->and($result[1][99999])->toBe(1)
        ->and(ArrayMulti::sortRecursiveGuarded([[3, 1], [2, 0]], maxNodes: 6))
        ->toBe(ArrayMulti::sortRecursive([[3, 1], [2, 0]]));
});

it('rejects host request completion after binding before calling the source', function () {
    $runtime = RuntimeContext::standalone();
    $request = RequestContext::create($runtime);
    $calls = 0;
    $collection = LazyCollection::fromFactory(function () use (&$calls): array {
        $calls++;

        return [1, 2];
    })->withRunwire($runtime, $request, checkpointEvery: 1);
    $request->complete();
    expect(fn () => $collection->all())->toThrow(LogicException::class)
        ->and($collection->take(0)->all())->toBe([])
        ->and($calls)->toBe(0);
});

it('stops at a checkpoint when the host completes a request during iteration', function () {
    $runtime = RuntimeContext::standalone();
    $request = RequestContext::create($runtime);
    $seen = [];
    $collection = LazyCollection::from([1, 2, 3])->withRunwire($runtime, $request, checkpointEvery: 1)
        ->mapLazy(function (int $value) use ($request, &$seen): int {
            $seen[] = $value;
            $request->complete();

            return $value;
        });
    expect(fn () => $collection->all())->toThrow(LogicException::class)
        ->and($seen)->toBe([1]);
});

it('rejects a closed scope even when coroutine capability is unavailable', function () {
    $scope = new CoroutineRuntime()->run(static fn(CoroutineScope $scope): CoroutineScope => $scope);
    expect(fn () => LazyCollection::from([1, 2])->withRunwire(RuntimeContext::standalone(), scope: $scope)->all())
        ->toThrow(LogicException::class, 'Coroutine scope is already closed');
});

it('rechecks request completion after a cooperative yield before consuming the source', function () {
    $runtime = RuntimeContext::fromCapabilities(
        new RuntimeCapabilities(RuntimeDriver::NATIVE, supportsRunwireCoroutines: true),
        'yield-check',
    );
    $request = RequestContext::create($runtime);
    $calls = 0;
    new CoroutineRuntime()->run(function (CoroutineScope $scope) use ($runtime, $request, &$calls): void {
        $scope->spawn(static fn() => $request->complete());
        $collection = LazyCollection::fromFactory(function () use (&$calls): array {
            $calls++;

            return [1];
        })->withRunwire($runtime, $request, $scope, checkpointEvery: 1);
        expect(fn () => $collection->all())->toThrow(LogicException::class, 'Completed Runwire request');
    });
    expect($calls)->toBe(0)->and($request->completed())->toBeTrue();
});

it('keeps untouched generated namespaces refreshable after partial config merges', function (string $operation) {
    review530Source($this->reviewDirectory, 1);
    review530Config($this->reviewDirectory)->warmNamespaceCache(['app', 'db']);
    $config = review530Config($this->reviewDirectory);
    expect($config->get('app'))->toBe(['version' => 1]);
    if ($operation === 'mergeEnvFile') {
        $envFile = $this->reviewDirectory . '/partial.env';
        file_put_contents($envFile, "UNRELATED_REVIEW_VALUE=yes\n");
        $config->mergeEnvFile($envFile);
    } else {
        $config->$operation(['other' => ['value' => true]]);
    }
    review530Source($this->reviewDirectory, 2);
    $config->warmNamespaceCache('app');
    expect(review530Config($this->reviewDirectory)->get('app.version'))->toBe(2);
})->with(['merge', 'overlay', 'mergeEnvFile']);

it('preserves a deliberate namespace override while refreshing untouched cached namespaces', function () {
    review530Source($this->reviewDirectory, 1);
    review530Config($this->reviewDirectory)->warmNamespaceCache(['app', 'db']);
    $config = review530Config($this->reviewDirectory);
    $config->get('db');
    $config->merge(['app' => ['version' => 99]]);
    review530Source($this->reviewDirectory, 2);
    $config->warmNamespaceCache(['app', 'db']);
    $fresh = review530Config($this->reviewDirectory);
    expect($fresh->get('app.version'))->toBe(99)->and($fresh->get('db.version'))->toBe(2);
});

it('pins exact and structural reads to one generation until explicit cache refresh', function () {
    review530Source($this->reviewDirectory, 1);
    $writer = review530Config($this->reviewDirectory);
    $writer->warmNamespaceCache(['app', 'db']);
    $reader = review530Config($this->reviewDirectory);
    expect($reader->get('app.version'))->toBe(1);
    review530Source($this->reviewDirectory, 2);
    $writer->warmNamespaceCache(['app', 'db']);
    expect($reader->get('db'))->toBe(['version' => 1])
        ->and($reader->get('app.version'))->toBe(1)
        ->and(review530Config($this->reviewDirectory)->get('db'))->toBe(['version' => 2]);
    $reader->namespaceCache($this->reviewDirectory . '/cache');
    expect($reader->get('app.version'))->toBe(2)
        ->and($reader->get('db'))->toBe(['version' => 2]);
});

it('copies the current published generation when a pinned reader later becomes a writer', function () {
    review530Source($this->reviewDirectory, 1);
    $writer = review530Config($this->reviewDirectory);
    $writer->warmNamespaceCache(['app', 'db']);
    $writer->get('app');
    review530Source($this->reviewDirectory, 2);
    review530Config($this->reviewDirectory)->warmNamespaceCache('db');
    $writer->warmNamespaceCache('app');
    $fresh = review530Config($this->reviewDirectory);
    expect($fresh->get('app.version'))->toBe(2)->and($fresh->get('db.version'))->toBe(2);
});

it('rejects custom DTO exporters before executing their unbounded callbacks', function () {
    $dto = new class extends DTO {
        public mixed $payload;
    };
    $exporter = new class {
        private int $calls = 0;

        public function calls(): int { return $this->calls; }

        public function toArray(): array {
            $this->calls++;

            return range(1, 10000);
        }
    };
    $dto->payload = $exporter;
    expect(fn () => $dto->toArrayDeepGuarded(maxNodes: 2))
        ->toThrow(InvalidArgumentException::class, 'custom exporters')
        ->and($exporter->calls())->toBe(0)
        ->and($dto->toArrayDeep()['payload'])->toHaveCount(10000);
});

it('exports DTO properties only once under the actual output budget', function () {
    $dto = new class extends DTO {
        public int $reads = 0;

        public array $payload {
            get => ++$this->reads === 1 ? [1] : range(1, 10000);
        }
    };
    expect($dto->toArrayDeepGuarded(maxNodes: 4)['payload'])->toBe([1])
        ->and($dto->reads)->toBe(1);
});

it('preserves native named arguments for both facade reference mutations', function () {
    $dot = ['remove' => true, 'keep' => true];
    $helper = $dot;
    ArrayKit::dot()->forget(target: $dot, keys: 'remove');
    ArrayKit::helper()->forget(array: $helper, keys: 'remove');
    expect($dot)->toBe(['keep' => true])->and($helper)->toBe($dot)
        ->and(fn () => ArrayKit::dot()->forget())->toThrow(ArgumentCountError::class);
});

it('memoizes consumed referenced scalar entries while keeping unread entries lazy', function () {
    $first = 1;
    $second = 2;
    $lazy = LazyCollection::from(['first' => &$first, 'second' => &$second]);
    $first = 3;
    expect($lazy->take(1)->all())->toBe(['first' => 3]);
    $first = 4;
    $second = 5;
    expect($lazy->all())->toBe(['first' => 3, 'second' => 5]);
    $second = 6;
    expect($lazy->all())->toBe(['first' => 3, 'second' => 5]);
});
