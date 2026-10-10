<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Port\PasswordHasherInterface;
use App\Application\Port\UserRepositoryInterface;
use App\Domain\Exception\ForbiddenRoleException;
use App\Domain\Exception\UsernameTakenException;
use App\Domain\Exception\ValidationException;
use App\Domain\Model\User;
use App\Domain\ValueObject\Role;
use App\Domain\ValueObject\Username;
use App\Domain\ValueObject\Uuid;

final class CreateLibrarianService
{
    private UserRepositoryInterface $userRepository;
    private PasswordHasherInterface $passwordHasher;

    public function __construct(
        UserRepositoryInterface $userRepository,
        PasswordHasherInterface $passwordHasher
    ) {
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
    }

    /**
     * @return array{id: string, username: string, role: string}
     */
    public function execute(string $usernameInput, string $passwordInput, string $roleInput, Role $requesterRole): array
    {
        if (!$requesterRole->isAdmin()) {
            throw new ForbiddenRoleException();
        }

        $normalizedRole = strtolower(trim($roleInput));
        if ($normalizedRole === Role::ADMIN) {
            throw new ValidationException('No está permitido crear usuarios con rol administrador mediante la API.', [
                ['field' => 'role', 'issue' => 'no se puede otorgar rol admin por API']
            ]);
        }
        if ($normalizedRole !== Role::LIBRARIAN) {
            throw new ValidationException('El rol asignado debe ser librarian.', [
                ['field' => 'role', 'issue' => 'debe ser librarian']
            ]);
        }

        $username = new Username($usernameInput);

        if (trim($passwordInput) === '') {
            throw new ValidationException('La contraseña no puede estar vacía.', [
                ['field' => 'password', 'issue' => 'no puede estar vacía']
            ]);
        }

        if ($this->userRepository->findByUsername($username) !== null) {
            throw new UsernameTakenException();
        }

        $user = new User(
            Uuid::generate(),
            $username,
            $this->passwordHasher->hash($passwordInput),
            new Role(Role::LIBRARIAN)
        );

        $this->userRepository->save($user);

        return $user->toArray();
    }
}
