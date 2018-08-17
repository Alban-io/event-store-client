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

namespace Prooph\EventStoreClient\UserManagement;

use Amp\Promise;
use Prooph\EventStoreClient\Exception\UserCommandFailedException;
use Prooph\EventStoreClient\IpEndPoint;
use Prooph\EventStoreClient\Transport\Http\EndpointExtensions;
use Prooph\EventStoreClient\UserCredentials;

class SyncUsersManager
{
    /** @var AsyncUsersManager */
    private $manager;

    public function __construct(
        IpEndPoint $endPoint,
        int $operationTimeout,
        string $httpSchema = EndpointExtensions::HttpSchema
    ) {
        $this->manager = new AsyncUsersManager($endPoint, $operationTimeout, $httpSchema);
    }

    public function enable(string $login, UserCredentials $userCredentials = null): void
    {
        Promise\wait($this->manager->enableAsync($login, $userCredentials));
    }

    public function disable(string $login, UserCredentials $userCredentials = null): void
    {
        Promise\wait($this->manager->disableAsync($login, $userCredentials));
    }

    /** @throws UserCommandFailedException */
    public function deleteUser(string $login, UserCredentials $userCredentials = null): void
    {
        Promise\wait($this->manager->deleteUserAsync($login, $userCredentials));
    }

    /** @return UserDetails[] */
    public function listAll(UserCredentials $userCredentials = null): array
    {
        return Promise\wait($this->manager->listAllAsync($userCredentials));
    }

    public function getCurrentUser(UserCredentials $userCredentials): UserDetails
    {
        return Promise\wait($this->manager->getCurrentUserAsync($userCredentials));
    }

    public function getUser(string $login, UserCredentials $userCredentials = null): UserDetails
    {
        return Promise\wait($this->manager->getUserAsync($login, $userCredentials));
    }

    /**
     * @param string $login
     * @param string $fullName
     * @param string[] $groups
     * @param string $password
     * @param UserCredentials|null $userCredentials
     * @return void
     */
    public function createUser(
        string $login,
        string $fullName,
        array $groups,
        string $password,
        UserCredentials $userCredentials = null
    ): void {
        Promise\wait($this->manager->createUserAsync(
            $login,
            $fullName,
            $groups,
            $password,
            $userCredentials
        ));
    }

    /**
     * @param string $login
     * @param string $fullName
     * @param string[] $groups
     * @param UserCredentials|null $userCredentials
     * @return void
     */
    public function updateUser(
        string $login,
        string $fullName,
        array $groups,
        UserCredentials $userCredentials = null
    ): void {
        Promise\wait($this->manager->updateUserAsync(
            $login,
            $fullName,
            $groups,
            $userCredentials
        ));
    }

    public function changePassword(
        string $login,
        string $oldPassword,
        string $newPassword,
        UserCredentials $userCredentials = null
    ): void {
        Promise\wait($this->manager->changePasswordAsync(
            $login,
            $oldPassword,
            $newPassword,
            $userCredentials
        ));
    }

    public function resetPassword(
        string $login,
        string $newPassword,
        UserCredentials $userCredentials = null
    ): void {
        Promise\wait($this->manager->resetPasswordAsync(
            $login,
            $newPassword,
            $userCredentials
        ));
    }
}
