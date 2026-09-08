<?php

declare(strict_types=1);

namespace App\Command;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[AsCommand(
    name: 'app:user:create',
    description: 'Create or update the login used by the domain-admin dashboard',
)]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly string $usersFilePath,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $usernameQuestion = new Question('Username: ');
        $usernameQuestion->setValidator(static function (mixed $value): string {
            $value = \is_string($value) ? trim($value) : '';
            if ($value === '') {
                throw new InvalidArgumentException('Username cannot be empty.');
            }

            return $value;
        });
        /** @var string $username */
        $username = $helper->ask($input, $output, $usernameQuestion);

        $passwordQuestion = new Question('Password: ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setHiddenFallback(false);
        $passwordQuestion->setValidator(static function (mixed $value): string {
            if (!\is_string($value) || $value === '') {
                throw new InvalidArgumentException('Password cannot be empty.');
            }

            return $value;
        });
        /** @var string $password */
        $password = $helper->ask($input, $output, $passwordQuestion);

        $hash = $this->passwordHasherFactory
            ->getPasswordHasher(InMemoryUser::class)
            ->hash($password)
        ;

        $users = $this->readUsers();
        $isUpdate = isset($users[$username]);
        $users[$username] = ['username' => $username, 'passwordHash' => $hash];

        $this->writeUsers($users);

        $io->success(\sprintf('User "%s" %s.', $username, $isUpdate ? 'updated' : 'created'));

        return Command::SUCCESS;
    }

    /**
     * @return array<string, array{username: string, passwordHash: string}>
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
                $users[$user['username']] = ['username' => $user['username'], 'passwordHash' => $user['passwordHash']];
            }
        }

        return $users;
    }

    /**
     * @param array<string, array{username: string, passwordHash: string}> $users
     */
    private function writeUsers(array $users): void
    {
        $dir = \dirname($this->usersFilePath);
        if (!is_dir($dir) && !mkdir($dir, 0o750, true) && !is_dir($dir)) {
            throw new RuntimeException(\sprintf('Could not create directory "%s".', $dir));
        }

        file_put_contents(
            $this->usersFilePath,
            json_encode(array_values($users), \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR) . "\n",
            \LOCK_EX,
        );
        chmod($this->usersFilePath, 0o640);

        // This command typically runs via `docker exec` as root while php-fpm
        // itself runs as www-data — make sure the worker can actually read
        // what we just wrote.
        if (\function_exists('posix_getuid') && posix_getuid() === 0) {
            @chown($dir, 'www-data');
            @chgrp($dir, 'www-data');
            @chown($this->usersFilePath, 'www-data');
            @chgrp($this->usersFilePath, 'www-data');
        }
    }
}
