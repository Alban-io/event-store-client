<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\Promise;
use Amp\Success;
use Generator;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\EventStoreConnection;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\Projections\ProjectionsManager;
use Prooph\EventStoreClient\UserCredentials;
use Prooph\EventStoreClient\Util\Guid;
use ProophTest\EventStoreClient\Helper\TestConnection;
use Throwable;
use function Amp\call;
use function Amp\Promise\wait;

trait ProjectionSpecification
{
    /** @var ProjectionsManager */
    protected $projectionsManager;
    /** @var EventStoreConnection */
    protected $connection;
    /** @var UserCredentials */
    protected $credentials;

    protected function given(): Generator
    {
        yield new Success();
    }

    abstract protected function when(): Generator;

    /** @throws Throwable */
    protected function execute(callable $test): void
    {
        wait(call(function () use ($test) {
            $this->credentials = DefaultData::adminCredentials();
            $this->connection = TestConnection::create();

            yield $this->connection->connectAsync();

            $this->projectionsManager = new ProjectionsManager(
                TestConnection::httpEndPoint(),
                5000
            );

            yield from $this->given();
            yield from $this->when();
            yield from $test();
            yield from $this->end();
        }));
    }

    protected function end(): Generator
    {
        $this->connection->close();

        yield new Success();
    }

    protected function createEvent(string $eventType, string $data): EventData
    {
        return new EventData(null, $eventType, true, $data);
    }

    protected function postEvent(string $stream, string $eventType, string $data): Promise
    {
        return $this->connection->appendToStreamAsync(
            $stream,
            ExpectedVersion::ANY,
            [$this->createEvent($eventType, $data)]
        );
    }

    protected function createOneTimeProjection(): Promise
    {
        $query = $this->createStandardQuery(Guid::generateAsHex());

        return $this->projectionsManager->createOneTimeAsync($query, $this->credentials);
    }

    protected function createContinuousProjection(string $projectionName): Promise
    {
        $query = $this->createStandardQuery(Guid::generateAsHex());

        return $this->projectionsManager->createContinuousAsync(
            $projectionName,
            $query,
            false,
            $this->credentials
        );
    }

    protected function createStandardQuery(string $stream): string
    {
        return <<<QUERY
fromStream('$stream').when({
    '\$any': function (s, e) {
        s.count = 1;
        return s;
    }
});
QUERY;
    }

    protected function createEmittingQuery(string $stream, string $emittingStream): string
    {
        return <<<QUERY
fromStream('$stream').when({
    '\$any': function (s, e) {
        emit('$emittingStream', 'emittedEvent', e);
    }
});
QUERY;
    }
}
