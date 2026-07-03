<?php
declare(strict_types=1);

/*
 * This file is part of the Flownative.Prometheus package.
 *
 * (c) Flownative GmbH - www.flownative.com
 */

/**
 * PHPUnit bootstrap for running this package's unit tests standalone.
 *
 * The test cases extend Neos\Flow\Tests\UnitTestCase. That base class lives in the
 * "autoload-dev" section of the neos/flow package, which Composer only activates for
 * the root package – not when neos/flow is installed as a dependency. We therefore
 * register the Flow test namespace here, locating it wherever Composer placed the
 * framework package.
 */

$autoloader = require __DIR__ . '/../vendor/autoload.php';

foreach ([
    __DIR__ . '/../Packages/Framework/Neos.Flow/Tests',
    __DIR__ . '/../vendor/neos/flow/Tests',
] as $flowTestsPath) {
    if (is_dir($flowTestsPath)) {
        $autoloader->addPsr4('Neos\\Flow\\Tests\\', $flowTestsPath);
        break;
    }
}
