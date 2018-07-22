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

namespace Prooph\EventStoreClient\Internal;

use Ramsey\Uuid\Uuid;

/** @internal */
class UuidGenerator
{
    public static function generate(): string
    {
        return \str_replace('-', '', Uuid::uuid4()->toString());
    }

    public static function empty(): string
    {
        return '00000000-0000-0000-0000-000000000000';
    }
}
