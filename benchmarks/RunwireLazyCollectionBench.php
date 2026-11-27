<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Benchmarks;

use Infocyph\ArrayKit\Collection\LazyCollection;
use Infocyph\Runwire\RequestContext;
use Infocyph\Runwire\RuntimeContext;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;

/**
 * PhpBench discovers this benchmark through its attributes.
 * @api
 */
#[Revs(1)]
#[Iterations(3)]
#[BeforeMethods('setUp')]
#[ParamProviders('provideSizes')]
final class RunwireLazyCollectionBench
{
    /** @var array<int, int> */
    private array $data = [];

    private ?RequestContext $request = null;

    private ?RuntimeContext $runtime = null;

    /**
     * @param array{size:int} $params
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function setUp(array $params): void
    {
        $this->data = range(1, $params['size']);
        $this->runtime = RuntimeContext::standalone();
        $this->request = RequestContext::create($this->runtime);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Infocyph\Runwire\Exception\CancelledException
     * @throws \Throwable
     */
    public function benchBoundRequestMapFilter(): void
    {
        LazyCollection::from($this->data)
            ->withRunwire(
                $this->runtime ?? throw new \LogicException('Benchmark setup was not run.'),
                $this->request ?? throw new \LogicException('Benchmark setup was not run.'),
                checkpointEvery: 256,
            )
            ->mapLazy(static fn(int $value): int => $value * 2)
            ->filterLazy(static fn(int $value): bool => ($value % 3) === 0)
            ->all();
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Infocyph\Runwire\Exception\CancelledException
     * @throws \Throwable
     */
    public function benchBoundRequestMaterialization(): void
    {
        LazyCollection::from($this->data)
            ->withRunwire(
                $this->runtime ?? throw new \LogicException('Benchmark setup was not run.'),
                $this->request ?? throw new \LogicException('Benchmark setup was not run.'),
                checkpointEvery: 256,
            )
            ->all();
    }

    /**
     * @throws \LogicException
     * @throws \Infocyph\Runwire\Exception\CancelledException
     * @throws \Throwable
     */
    public function benchUnboundMapFilter(): void
    {
        LazyCollection::from($this->data)
            ->mapLazy(static fn(int $value): int => $value * 2)
            ->filterLazy(static fn(int $value): bool => ($value % 3) === 0)
            ->all();
    }

    /**
     * @throws \LogicException
     * @throws \Infocyph\Runwire\Exception\CancelledException
     * @throws \Throwable
     */
    public function benchUnboundMaterialization(): void
    {
        LazyCollection::from($this->data)->all();
    }

    /** @return array<string, array{size:int}> */
    public function provideSizes(): array
    {
        return [
            '1k' => ['size' => 1000],
            '10k' => ['size' => 10000],
            '100k' => ['size' => 100000],
        ];
    }
}
