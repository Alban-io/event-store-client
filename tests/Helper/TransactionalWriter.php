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

namespace ProophTest\EventStoreClient\Helper;

use Amp\Promise;
use Generator;
use Prooph\EventStore\AsyncEventStoreConnection;
use function Amp\call;

/** @internal */
class TransactionalWriter
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

    /**
     * @param int $expectedVersion
     * @return Promise<OngoingTransaction>
     */
    public function startTransaction(int $expectedVersion): Promise
    {
        return call(function () use ($expectedVersion): Generator {
            $transaction = yield $this->connection->startTransactionAsync(
                $this->stream,
                $expectedVersion
            );

            return new OngoingTransaction($transaction);
        });
    }
}
