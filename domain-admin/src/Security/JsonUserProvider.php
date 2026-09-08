<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Reads login credentials from the JSON file written by app:user:create
 * (see Command\CreateUserCommand) instead of a database.
 *
 * @implements UserProviderInterface<InMemoryUser>
 */
final class JsonUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly string $usersFilePath,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        foreach ($this->readUsers() as $user) {
            if (hash_equals($user['username'], $identifier)) {
                return new InMemoryUser($user['username'], $user['passwordHash'], ['ROLE_ADMIN']);
            }
        }

        throw new UserNotFoundException(\sprintf('User "%s" not found.', $identifier));
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof InMemoryUser) {
            throw new UnsupportedUserException(\sprintf('Unsupported user class "%s".', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === InMemoryUser::class;
    }

    /**
     * @return list<array{username: string, passwordHash: string}>
     */
    private function readUsers(): array
    {
        if (!is_file($this->usersFilePath)) {
            return [];
        }

        $raw = file_get_contents($this->usersFilePath);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!\is_array($decoded)) {
            return [];
        }

        $users = [];
        foreach ($decoded as $user) {
            if (\is_array($user) && isset($user['username'], $user['passwordHash']) && \is_string($user['username']) && \is_string($user['passwordHash'])) {
                $users[] = ['username' => $user['username'], 'passwordHash' => $user['passwordHash']];
            }
        }

        return $users;
    }
}
