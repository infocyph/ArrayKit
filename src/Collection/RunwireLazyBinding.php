<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Collection;

use Infocyph\Runwire\CancellationToken;
use Infocyph\Runwire\Coroutine\CoroutineScope;
use Infocyph\Runwire\RequestContext;
use InvalidArgumentException;
use LogicException;

/** @internal */
final readonly class RunwireLazyBinding
{
    private const int MAX_CHECKPOINT_INTERVAL = 1_000_000;

    public CancellationToken $cancellation;

    public function __construct(
        CancellationToken|RequestContext $context,
        public ?CoroutineScope $scope = null,
        public int $checkpointEvery = 256,
    ) {
        if ($checkpointEvery < 1 || $checkpointEvery > self::MAX_CHECKPOINT_INTERVAL) {
            throw new InvalidArgumentException(
                'Runwire checkpoint interval must be between 1 and 1000000 items.',
            );
        }

        if ($context instanceof RequestContext) {
            if ($context->completed()) {
                throw new LogicException('Completed Runwire request context cannot be bound to a lazy collection.');
            }

            $context = $context->cancellation;
        }

        $this->cancellation = $context;
    }

    public function checkpoint(): void
    {
        $this->cancellation->throwIfCancelled();
        $this->scope?->yieldNow();
        $this->cancellation->throwIfCancelled();
    }
}
