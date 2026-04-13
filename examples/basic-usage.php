<?php

declare(strict_types=1);

use Infocyph\ArrayKit\ArrayKit;

$isList = ArrayKit::single()->isList([1, 2, 3]);
$name = ArrayKit::dot()->get(['user' => ['name' => 'Alice']], 'user.name');

$config = ArrayKit::config(['app' => ['env' => 'local']]);
$env = $config->get('app.env');

$collection = ArrayKit::collection([1, 2, 3, 4]);
$sum = $collection->process()->sum();

unset($isList, $name, $env, $sum);
