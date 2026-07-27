<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class CsrfService
{
    public const FIELD_NAME =
        '_csrf_token';


    private const SESSION_KEY =
        'csrf_token';


    private const TOKEN_BYTES =
        32;


    public function token(): string
    {
        $token =
            $_SESSION[
                self::SESSION_KEY
            ]
            ??
            null;


        if (
            is_string(
                $token
            )
            &&
            $this->isValidTokenFormat(
                $token
            )
        ) {

            return $token;
        }


        return $this->rotate();
    }


    public function rotate(): string
    {
        $token =
            bin2hex(
                random_bytes(
                    self::TOKEN_BYTES
                )
            );


        $_SESSION[
            self::SESSION_KEY
        ] =
            $token;


        return $token;
    }


    public function validate(
        mixed $submittedToken
    ): bool
    {
        $sessionToken =
            $_SESSION[
                self::SESSION_KEY
            ]
            ??
            null;


        if (
            !is_string(
                $sessionToken
            )
            ||
            !$this->isValidTokenFormat(
                $sessionToken
            )
        ) {

            return false;
        }


        if (
            !is_string(
                $submittedToken
            )
            ||
            !$this->isValidTokenFormat(
                $submittedToken
            )
        ) {

            return false;
        }


        return hash_equals(
            $sessionToken,
            $submittedToken
        );
    }


    /**
     * @param array<string,mixed> $input
     */
    public function requireValidToken(
        array $input
    ): void
    {
        if (
            !$this->validate(
                $input[
                    self::FIELD_NAME
                ]
                ??
                null
            )
        ) {

            throw new RuntimeException(
                'The form security token is missing or invalid. Refresh the page and try again.'
            );
        }
    }


    public function clear(): void
    {
        unset(
            $_SESSION[
                self::SESSION_KEY
            ]
        );
    }


    private function isValidTokenFormat(
        string $token
    ): bool
    {
        return
            strlen(
                $token
            )
            ===
            self::TOKEN_BYTES
            *
            2
            &&
            ctype_xdigit(
                $token
            );
    }
}
