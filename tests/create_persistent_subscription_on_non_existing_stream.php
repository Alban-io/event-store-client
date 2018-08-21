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

namespace ProophTest\EventStoreClient;

use Amp\Success;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\Internal\UuidGenerator;
use Prooph\EventStoreClient\NamedConsumerStrategy;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;
use Throwable;

class create_persistent_subscription_on_non_existing_stream extends TestCase
{
    use SpecificationWithConnection;

    /** string */
    private $stream;
    /** PersistentSubscriptionSettings */
    private $settings;

    protected function setUp(): void
    {
        $this->stream = UuidGenerator::generate();
        $this->settings = new PersistentSubscriptionSettings(
            false,
            -1,
            false,
            2000,
            500,
            10,
            20,
            1000,
            500,
            0,
            30000,
            10,
            NamedConsumerStrategy::roundRobin()
        );
    }

    protected function when(): Generator
    {
        yield new Success();
    }

    /**
     * @test
     * @doesNotPerformAssertions
     * @throws Throwable
     */
    public function the_completion_succeeds(): void
    {
        $this->executeCallback(function () {
            yield $this->conn->createPersistentSubscriptionAsync(
                $this->stream,
                'nonexistinggroup',
                $this->settings,
                DefaultData::adminCredentials()
            );
        });
    }
}
