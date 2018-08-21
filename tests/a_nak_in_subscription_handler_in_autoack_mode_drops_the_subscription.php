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
use Amp\TimeoutException;
use Closure;
use Exception;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\EventAppearedOnPersistentSubscription;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\EventId;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\Internal\AbstractEventStorePersistentSubscription;
use Prooph\EventStoreClient\Internal\ResolvedEvent;
use Prooph\EventStoreClient\NamedConsumerStrategy;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;
use Prooph\EventStoreClient\SubscriptionDroppedOnPersistentSubscription;
use Prooph\EventStoreClient\SubscriptionDropReason;
use Ramsey\Uuid\Uuid;
use Throwable;

class a_nak_in_subscription_handler_in_autoack_mode_drops_the_subscription extends TestCase
{
    use SpecificationWithConnection;

    /** @var string */
    private $stream;
    /** @var PersistentSubscriptionSettings */
    private $settings;
    /** @var Deferred */
    private $resetEvent;
    /** @var Throwable */
    private $exception;
    /** @var SubscriptionDropReason */
    private $reason;
    /** @var string */
    private $group;

    protected function setUp()
    {
        $this->stream = '$' . Uuid::uuid4()->toString();
        $this->settings = new PersistentSubscriptionSettings(
            false,
            0,
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
        $this->resetEvent = new Deferred();
        $this->group = 'naktest';
    }

    protected function given(): Generator
    {
        yield $this->conn->createPersistentSubscriptionAsync(
            $this->stream,
            $this->group,
            $this->settings,
            DefaultData::adminCredentials()
        );

        $dropBehaviour = function (
            SubscriptionDropReason $reason,
            Throwable $exception = null
        ): void {
            $this->reason = $reason;
            $this->exception = $exception;
            $this->resetEvent->resolve(true);
        };

        $this->conn->connectToPersistentSubscription(
            $this->stream,
            $this->group,
            new class() implements EventAppearedOnPersistentSubscription {
                public function __invoke(
                    AbstractEventStorePersistentSubscription $subscription,
                    ResolvedEvent $resolvedEvent
                ): Promise {
                    throw new \Exception('test');
                }
            },
            new class(Closure::fromCallable($dropBehaviour)) implements SubscriptionDroppedOnPersistentSubscription {
                private $callback;

                public function __construct(callable $callback)
                {
                    $this->callback = $callback;
                }

                public function __invoke(
                    AbstractEventStorePersistentSubscription $subscription,
                    SubscriptionDropReason $reason,
                    Throwable $exception = null
                ): void {
                    ($this->callback)($reason, $exception);
                }
            },
            10,
            true,
            DefaultData::adminCredentials()
        );
    }

    protected function when(): Generator
    {
        yield $this->conn->appendToStreamAsync(
            $this->stream,
            ExpectedVersion::ANY,
            [
                new EventData(EventId::generate(), 'test', true, '{"foo: "bar"}'),
            ],
            DefaultData::adminCredentials()
        );
    }

    /**
     * @test
     * @throws Throwable
     */
    public function the_subscription_gets_dropped(): void
    {
        $this->executeCallback(function (): Generator {
            try {
                $result = yield Promise\timeout($this->resetEvent->promise(), 5000);
            } catch (TimeoutException $e) {
                $this->fail('Timed out');
            }

            $this->assertTrue($result);
            $this->assertTrue($this->reason->equals(SubscriptionDropReason::eventHandlerException()));
            $this->assertInstanceOf(Exception::class, $this->exception);
            $this->assertSame('test', $this->exception->getMessage());
        });
    }
}
