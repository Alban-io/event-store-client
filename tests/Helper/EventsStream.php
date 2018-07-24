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

namespace ProophTest\EventStoreClient\Helper;

use Amp\Promise;
use Amp\Success;
use Prooph\EventStoreClient\EventStoreAsyncConnection;
use Prooph\EventStoreClient\StreamEventsSlice;
use function Amp\call;

/** @internal */
class EventsStream
{
    private const SliceSize = 10;

    /** @return Promise<int> */
    public static function count(EventStoreAsyncConnection $connection, string $stream): Promise
    {
        return call(function () use ($connection, $stream) {
            $result = 0;

            while (true) {
                /** @var StreamEventsSlice $slice */
                $slice = yield $connection->readStreamEventsForwardAsync($stream, $result, self::SliceSize, false);
                $result += \count($slice->events());

                if ($slice->isEndOfStream()) {
                    break;
                }
            }

            return new Success($result);
        });
    }
}
