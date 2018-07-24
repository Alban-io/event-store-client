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

use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\EventId;

/** @internal */
class TestEvent
{
    public static function new(EventId $eventId = null, string $data = null, string $metadata = null): EventData
    {
        if (null === $eventId) {
            $eventId = EventId::generate();
        }

        return new EventData($eventId, 'TestEvent', false, $data ?? $eventId->toString(), $metadata ?? 'metadata');
    }
}
