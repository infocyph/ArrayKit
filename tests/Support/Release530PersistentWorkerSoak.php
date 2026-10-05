<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Tests\Support;

use Infocyph\ArrayKit\Collection\LazyCollection;
use Infocyph\ArrayKit\Config\LayeredLazyFileConfig;
use Infocyph\ArrayKit\Config\LazyFileConfig;
use Infocyph\Runwire\Exception\CancelledException;
use Infocyph\Runwire\RequestContext;
use Infocyph\Runwire\Runtime\Enum\CancellationReason;
use Infocyph\Runwire\Runtime\Enum\RuntimeDriver;
use Infocyph\Runwire\Runtime\RuntimeCapabilityResolver;
use Infocyph\Runwire\Runtime\RuntimeEnvironment;
use Infocyph\Runwire\RuntimeContext;
use Infocyph\Runwire\RuntimeOptions;
use RuntimeException;
use Throwable;

final class Release530PersistentWorkerSoak
{
    private bool $running = true;

    private readonly string $cacheDirectory;

    private readonly RuntimeContext $runtime;

    private readonly string $sourceDirectory;

    public function __construct()
    {
        $suffix = bin2hex(random_bytes(6));
        $this->sourceDirectory = sys_get_temp_dir() . '/arraykit-530-soak-source-' . $suffix;
        $this->cacheDirectory = sys_get_temp_dir() . '/arraykit-530-soak-cache-' . $suffix;

        if (!mkdir($this->sourceDirectory, 0777, true) || !mkdir($this->cacheDirectory, 0777, true)) {
            throw new RuntimeException('Unable to create ArrayKit worker-soak directories.');
        }

        $this->writeSource(1);
        $this->runtime = $this->runtime();
    }

    public function run(): void
    {
        $this->installSignalHandlers();

        try {
            $cycle = 0;

            while ($this->running) {
                $cycle++;
                $this->runCycle($cycle);

                if (($cycle % 100) === 0) {
                    gc_collect_cycles();
                    usleep(1_000);
                }
            }
        } finally {
            $this->removeDirectory($this->sourceDirectory);
            $this->removeDirectory($this->cacheDirectory);
        }
    }

    private function installSignalHandlers(): void
    {
        if (!function_exists('pcntl_async_signals') || !function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, function (): void {
            $this->running = false;
        });
        pcntl_signal(SIGINT, function (): void {
            $this->running = false;
        });
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } elseif (is_file($path) || is_link($path)) {
                unlink($path);
            }
        }

        rmdir($directory);
    }

    private function runCycle(int $cycle): void
    {
        $tenant = 'tenant-' . $cycle;

        $config = new LayeredLazyFileConfig(
            $this->sourceDirectory,
            namespaceCacheDirectory: $this->cacheDirectory,
            namespaces: ['app'],
        );
        $config->set('app.tenant', $tenant);
        $config->snapshot();
        $config->set('app.tenant', 'mutated-' . $cycle);

        if (!$config->restore() || $config->get('app.tenant') !== $tenant) {
            throw new RuntimeException('Layered config state leaked during worker soak.');
        }

        if (($cycle % 50) === 0) {
            $this->refreshGeneratedCache($cycle);
        }

        $request = RequestContext::create($this->runtime);
        $collection = LazyCollection::fromFactory(
            static fn(): array => [
                ['tenant' => $tenant, 'value' => 1],
                ['tenant' => $tenant, 'value' => 2],
                ['tenant' => $tenant, 'value' => 3],
            ],
        )->withRunwire($this->runtime, $request, checkpointEvery: 1);

        if (($cycle % 13) === 0) {
            try {
                $collection
                    ->mapLazy(function (array $row) use ($request): array {
                        if ($row['value'] === 2) {
                            $request->cancel(CancellationReason::HOST_CANCELLED);
                        }

                        return $row;
                    })
                    ->all();

                throw new RuntimeException('Cancelled worker cycle unexpectedly completed.');
            } catch (CancelledException) {
            }
        } else {
            $rows = $collection->all();
            if (count($rows) !== 3 || array_unique(array_column($rows, 'tenant')) !== [$tenant]) {
                throw new RuntimeException('Lazy collection state leaked across worker cycles.');
            }
        }

        if ($request->completed()) {
            throw new RuntimeException('ArrayKit completed a host-owned Runwire request.');
        }

        unset($collection, $config, $request);
    }

    private function refreshGeneratedCache(int $cycle): void
    {
        $version = intdiv($cycle, 50) + 1;
        $this->writeSource($version);

        $config = new LazyFileConfig(
            $this->sourceDirectory,
            namespaceCacheDirectory: $this->cacheDirectory,
        );
        $config->warmNamespaceCache('app');

        $fresh = new LazyFileConfig(
            $this->sourceDirectory,
            namespaceCacheDirectory: $this->cacheDirectory,
        );

        if ($fresh->get('app.version') !== $version) {
            throw new RuntimeException('Generated cache refresh returned stale data during worker soak.');
        }

        if (($cycle % 100) !== 0) {
            return;
        }

        $config->set('app.unsupported', new class {});
        try {
            $config->warmNamespaceCache('app');
            throw new RuntimeException('Unsupported generated cache payload was unexpectedly published.');
        } catch (Throwable $error) {
            if ($error instanceof RuntimeException && $error->getMessage() === 'Unsupported generated cache payload was unexpectedly published.') {
                throw $error;
            }
        }

        $afterFailure = new LazyFileConfig(
            $this->sourceDirectory,
            namespaceCacheDirectory: $this->cacheDirectory,
        );

        if ($afterFailure->get('app.version') !== $version) {
            throw new RuntimeException('Failed cache rebuild replaced the last valid generation.');
        }
    }

    private function runtime(): RuntimeContext
    {
        $environment = new RuntimeEnvironment(
            sapi: 'cli',
            hostedDrivers: [RuntimeDriver::ROADRUNNER],
            availableDrivers: [RuntimeDriver::ROADRUNNER],
            opcacheAvailable: true,
            opcacheEnabled: true,
            opcacheCliEnabled: true,
        );

        $capabilities = (new RuntimeCapabilityResolver())->resolve(
            RuntimeDriver::ROADRUNNER,
            $environment,
            new RuntimeOptions(driver: RuntimeDriver::ROADRUNNER),
        );

        return RuntimeContext::fromCapabilities(
            $capabilities,
            mode: 'worker-soak',
            workerSlot: 0,
            generation: 1,
        );
    }

    private function writeSource(int $version): void
    {
        $source = sprintf(
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['tenant' => 'source', 'version' => %d];\n",
            $version,
        );

        if (file_put_contents($this->sourceDirectory . '/app.php', $source) === false) {
            throw new RuntimeException('Unable to update worker-soak source config.');
        }
    }
}
