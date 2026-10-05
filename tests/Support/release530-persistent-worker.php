<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Tests\Support\Release530PersistentWorkerSoak;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

(new Release530PersistentWorkerSoak())->run();
