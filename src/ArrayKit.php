<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit;

use Infocyph\ArrayKit\Array\ArrayMulti;
use Infocyph\ArrayKit\Array\ArraySingle;
use Infocyph\ArrayKit\Array\BaseArrayHelper;
use Infocyph\ArrayKit\Array\DotNotation;
use Infocyph\ArrayKit\Collection\Collection;
use Infocyph\ArrayKit\Collection\HookedCollection;
use Infocyph\ArrayKit\Collection\Pipeline;
use Infocyph\ArrayKit\Config\Config;
use Infocyph\ArrayKit\Config\LazyFileConfig;
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
     * @var array<class-string, ModuleProxy>
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

    public static function dot(): ModuleProxy
    {
        return self::proxy(DotNotation::class);
    }

    public static function helper(): ModuleProxy
    {
        return self::proxy(BaseArrayHelper::class);
    }

    public static function hookedCollection(mixed $data = []): HookedCollection
    {
        return HookedCollection::make($data);
    }

    /**
     * @param array<array-key, mixed> $items
     */
    public static function lazyConfig(string $directory, string $extension = 'php', array $items = []): LazyFileConfig
    {
        return new LazyFileConfig($directory, $extension, $items);
    }

    public static function multi(): ModuleProxy
    {
        return self::proxy(ArrayMulti::class);
    }

    public static function pipeline(mixed $data): Pipeline
    {
        return Collection::make($data)->process();
    }

    public static function single(): ModuleProxy
    {
        return self::proxy(ArraySingle::class);
    }

    /**
     * @param class-string $className
     */
    private static function proxy(string $className): ModuleProxy
    {
        return self::$moduleProxies[$className] ?? self::$moduleProxies[$className] = new ModuleProxy($className);
    }
}
