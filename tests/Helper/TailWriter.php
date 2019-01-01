<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2019 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient\Helper;

use Amp\Promise;
use Amp\Success;
use Prooph\EventStore\AsyncEventStoreConnection;
use Prooph\EventStore\EventData;
use function Amp\call;

/** @internal */
class TailWriter
{
    /** @var AsyncEventStoreConnection */
    private $connection;
    /** @var string */
    private $stream;

    public function __construct(AsyncEventStoreConnection $connection, string $stream)
    {
        $this->connection = $connection;
        $this->stream = $stream;
    }

    /** @return Promise<TailWriter> */
    public function then(EventData $event, int $expectedVersion): Promise
    {
        return call(function () use ($event, $expectedVersion) {
            yield $this->connection->appendToStreamAsync($this->stream, $expectedVersion, [$event]);

            return new Success($this);
        });
    }
}
