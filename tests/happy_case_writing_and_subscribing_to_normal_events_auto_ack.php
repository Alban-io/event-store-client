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

use Amp\Deferred;
use Amp\Promise;
use Amp\Success;
use Amp\TimeoutException;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\EventAppearedOnPersistentSubscription;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\Internal\AbstractEventStorePersistentSubscription;
use Prooph\EventStoreClient\Internal\ResolvedEvent;
use Prooph\EventStoreClient\Internal\UuidGenerator;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;
use function Amp\Promise\timeout;
use Throwable;

class happy_case_writing_and_subscribing_to_normal_events_auto_ack extends TestCase
{
    use SpecificationWithConnection;

    /** string */
    private $streamName;
    /** string */
    private $groupName;
    /** @var int */
    private $bufferCount = 10;
    /** @var Deferred */
    private $eventsReceived;

    protected function setUp(): void
    {
        $this->streamName = UuidGenerator::generate();
        $this->groupName = UuidGenerator::generate();
        $this->eventsReceived = new Deferred();
    }

    protected function when(): Generator
    {
        yield new Success();
    }

    /**
     * @test
     * @throws Throwable
     */
    public function do_test(): void
    {
        $this->executeCallback(function () {
            $settings = PersistentSubscriptionSettings::default();

            yield $this->conn->createPersistentSubscriptionAsync(
                $this->streamName,
                $this->groupName,
                $settings,
                DefaultData::adminCredentials()
            );

            $deferred = $this->eventsReceived;

            $this->conn->connectToPersistentSubscription(
                $this->streamName,
                $this->groupName,
                new class($deferred) implements EventAppearedOnPersistentSubscription {
                    private $deferred;
                    private $eventReceivedCount = 0;
                    private $eventWriteCount = 20;

                    public function __construct(Deferred $deferred)
                    {
                        $this->deferred = $deferred;
                    }

                    public function __invoke(
                        AbstractEventStorePersistentSubscription $subscription,
                        ResolvedEvent $resolvedEvent
                    ): Promise {
                        ++$this->eventReceivedCount;

                        if ($this->eventReceivedCount === $this->eventWriteCount) {
                            $this->deferred->resolve(true);
                        }

                        return new Success();
                    }
                }
            );

            for ($i = 0; $i < 20; $i++) {
                $eventData = new EventData(null, 'SomeEvent', false);

                yield $this->conn->appendToStreamAsync(
                    $this->streamName,
                    ExpectedVersion::Any,
                    [$eventData],
                    DefaultData::adminCredentials()
                );
            }

            try {
                $result = yield timeout($this->eventsReceived->promise(), 10000);
                $this->assertTrue($result);
            } catch (TimeoutException $e) {
                $this->fail('Timed out waiting for events');
            }
        });
    }
}
