<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit;

use Infocyph\ArrayKit\Array\ArrayMulti;
use Infocyph\ArrayKit\Array\ArraySingle;
use Infocyph\ArrayKit\Array\BaseArrayHelper;
use Infocyph\ArrayKit\Array\DotNotation;
use Infocyph\ArrayKit\Collection\Collection;
use Infocyph\ArrayKit\Collection\HookedCollection;
use Infocyph\ArrayKit\Collection\LazyCollection;
use Infocyph\ArrayKit\Collection\Pipeline;
use Infocyph\ArrayKit\Config\Config;
use Infocyph\ArrayKit\Config\EnvParser;
use Infocyph\ArrayKit\Config\LazyFileConfig;
use Infocyph\ArrayKit\Config\Support\Environment;
use Infocyph\ArrayKit\Facade\ModuleProxy;

/**
 * Unified static facade for the package.
 *
 * Usage:
 * - ArrayKit::single()->isList(...)
 * - ArrayKit::multi()->flatten(...)
 * - ArrayKit::helper()->times(...)
 * - ArrayKit::dot()->get(...)
 */
final class ArrayKit
{
    /**
     * @var array<class-string, ModuleProxy<object>>
     */
    private static array $moduleProxies = [];

    private function __construct() {}

    public static function collection(mixed $data = []): Collection
    {
        return Collection::make($data);
    }

    /**
     * @param array<array-key, mixed> $items
     */
    public static function config(array $items = []): Config
    {
        $config = new Config();
        if ($items !== []) {
            $config->loadArray($items);
        }

        return $config;
    }

    /** @return ModuleProxy<DotNotation> */
    public static function dot(): ModuleProxy
    {
        return self::proxy(DotNotation::class);
    }

    /** @return ModuleProxy<EnvParser> */
    public static function dotenv(): ModuleProxy
    {
        return self::proxy(EnvParser::class);
    }

    /** @return ModuleProxy<Environment> */
    public static function env(): ModuleProxy
    {
        return self::proxy(Environment::class);
    }

    /** @return ModuleProxy<BaseArrayHelper> */
    public static function helper(): ModuleProxy
    {
        return self::proxy(BaseArrayHelper::class);
    }

    public static function hookedCollection(mixed $data = []): HookedCollection
    {
        return HookedCollection::make($data);
    }

    /**
     * @return LazyCollection<array-key, mixed>
     */
    public static function lazyCollection(mixed $data = []): LazyCollection
    {
        return LazyCollection::make($data);
    }

    /**
     * @param array<array-key, mixed> $items
     */
    public static function lazyConfig(
        string $directory,
        string $extension = 'php',
        array $items = [],
        ?string $namespaceCacheDirectory = null,
    ): LazyFileConfig {
        return new LazyFileConfig($directory, $extension, $items, $namespaceCacheDirectory);
    }

    /** @return ModuleProxy<ArrayMulti> */
    public static function multi(): ModuleProxy
    {
        return self::proxy(ArrayMulti::class);
    }

    public static function pipeline(mixed $data): Pipeline
    {
        return Collection::make($data)->process();
    }

    /** @return ModuleProxy<ArraySingle> */
    public static function single(): ModuleProxy
    {
        return self::proxy(ArraySingle::class);
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return ModuleProxy<T>
     */
    private static function proxy(string $className): ModuleProxy
    {
        // The cache key is the same class string used to construct each proxy.
        /** @var ModuleProxy<T> $proxy */
        $proxy = self::$moduleProxies[$className] ?? self::$moduleProxies[$className] = new ModuleProxy($className);

        return $proxy;
    }
}
