<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Port\PasswordHasherInterface;
use App\Application\Port\TokenIssuerInterface;
use App\Application\Port\UserRepositoryInterface;
use App\Domain\Exception\InvalidCredentialsException;
use App\Domain\ValueObject\Username;

final class LoginService
{
    private UserRepositoryInterface $userRepository;
    private PasswordHasherInterface $passwordHasher;
    private TokenIssuerInterface $tokenIssuer;

    public function __construct(
        UserRepositoryInterface $userRepository,
        PasswordHasherInterface $passwordHasher,
        TokenIssuerInterface $tokenIssuer
    ) {
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->tokenIssuer = $tokenIssuer;
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int, role: string}
     */
    public function execute(string $usernameInput, string $passwordInput): array
    {
        $trimmed = trim($usernameInput);
        if ($trimmed === '' || $passwordInput === '') {
            throw new InvalidCredentialsException();
        }

        try {
            $username = new Username($trimmed);
        } catch (\Throwable) {
            throw new InvalidCredentialsException();
        }

        $user = $this->userRepository->findByUsername($username);
        if ($user === null) {
            throw new InvalidCredentialsException();
        }

        if (!$this->passwordHasher->verify($passwordInput, $user->passwordHash())) {
            throw new InvalidCredentialsException();
        }

        return $this->tokenIssuer->issue($user->id(), $user->role());
    }
}
