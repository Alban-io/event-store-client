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

namespace Prooph\EventStoreClient\Messages\ClusterMessages;

use Prooph\EventStoreClient\Exception\InvalidArgumentException;

class ClusterInfoDto
{
    /** @var MemberInfoDto[] */
    private $members = [];

    public function __construct(array $members = [])
    {
        foreach ($members as $member) {
            if (! $member instanceof MemberInfoDto) {
                throw new InvalidArgumentException('Expected an array of MemberInfoDto');
            }

            $this->members[] = $member;
        }
    }

    /** @return MemberInfoDto[] */
    public function members(): array
    {
        return $this->members;
    }
}
