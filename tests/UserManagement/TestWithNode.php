<?php
/**
 * This file is part of the prooph/event-store-client.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient\UserManagement;

use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\IpEndPoint;
use Prooph\EventStoreClient\UserManagement\UsersManager;

abstract class TestWithNode extends TestCase
{
    /** @var UsersManager */
    protected $manager;

    protected function setUp(): void
    {
        $this->manager = new UsersManager(
            new IpEndPoint(
                \getenv('ES_HOST'),
                (int) \getenv('ES_HTTP_PORT')
            ),
            5000
        );
    }
}
