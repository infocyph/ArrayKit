<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Tests\Support;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

(new Release530PersistentWorkerSoak())->run();
