<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Collection;

use Infocyph\Runwire\CancellationToken;
use Infocyph\Runwire\Coroutine\CoroutineScope;
use Infocyph\Runwire\Coroutine\TaskLocal;
use Infocyph\Runwire\RequestContext;
use Infocyph\Runwire\Runtime\Enum\RuntimeCapability;
use Infocyph\Runwire\RuntimeContext;
use InvalidArgumentException;
use LogicException;

/** @internal */
final readonly class RunwireLazyBinding
{
    private const int MAX_CHECKPOINT_INTERVAL = 1_000_000;

    private ?CancellationToken $requestCancellation;

    private ?CancellationToken $scopeCancellation;

    private ?TaskLocal $scopeCheckKey;

    private bool $yieldEnabled;

    public function __construct(
        public RuntimeContext $runtime,
        private ?RequestContext $request = null,
        public ?CoroutineScope $scope = null,
        public int $checkpointEvery = 256,
    ) {
        if ($checkpointEvery < 1 || $checkpointEvery > self::MAX_CHECKPOINT_INTERVAL) {
            throw new InvalidArgumentException(
                'Runwire checkpoint interval must be between 1 and 1000000 items.',
            );
        }

        if ($request !== null) {
            if ($request->completed()) {
                throw new LogicException('Completed Runwire request context cannot be bound to a lazy collection.');
            }

            if ($request->runtime() !== $runtime) {
                throw new LogicException('Runwire request context belongs to a different runtime context.');
            }
        }

        $this->requestCancellation = $request?->cancellation;
        $this->scopeCancellation = $scope?->cancellation();
        $this->scopeCheckKey = $scope === null ? null : new TaskLocal();
        $this->yieldEnabled = $scope !== null
            && $runtime->supports(RuntimeCapability::RUNWIRE_COROUTINES);
    }

    public function checkpoint(): void
    {
        $this->assertActive();
        $this->requestCancellation?->throwIfCancelled();
        $this->scopeCancellation?->throwIfCancelled();

        if ($this->yieldEnabled) {
            $this->scope?->yieldNow();
        }

        $this->assertActive();
        $this->requestCancellation?->throwIfCancelled();
        $this->scopeCancellation?->throwIfCancelled();
    }

    private function assertActive(): void
    {
        if ($this->request?->completed()) {
            throw new LogicException('Completed Runwire request context cannot be traversed.');
        }

        if ($this->scope !== null && $this->scopeCheckKey !== null) {
            // Runwire 2.1.1 guards this read without yielding or changing task-local state.
            $this->scope->hasLocal($this->scopeCheckKey);
        }
    }
}
