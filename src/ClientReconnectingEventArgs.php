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

namespace Prooph\EventStoreClient;

use Prooph\EventStoreClient\Internal\EventStoreAsyncNodeConnection;

class ClientReconnectingEventArgs implements EventArgs
{
    /** @var EventStoreAsyncNodeConnection */
    private $connection;

    public function __construct(EventStoreAsyncNodeConnection $connection)
    {
        $this->connection = $connection;
    }

    public function connection(): EventStoreAsyncNodeConnection
    {
        return $this->connection;
    }
}
