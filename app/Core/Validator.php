<?php
declare(strict_types=1);

namespace App\Core;

class Validator
{
    private array $errors = [];

    public function required(
        string $field,
        mixed $value,
        string $message
    ): self
    {
        if (
            $value === null ||
            trim((string)$value) === ''
        ) {
            $this->errors[$field] = $message;
        }

        return $this;
    }

    public function digits(
        string $field,
        mixed $value,
        int $length,
        string $message
    ): self
    {
        if (
            !preg_match(
                '/^[0-9]{' . $length . '}$/',
                (string)$value
            )
        ) {
            $this->errors[$field] = $message;
        }

        return $this;
    }

    public function matches(
        string $field,
        mixed $value,
        mixed $other,
        string $message
    ): self
    {
        if ($value !== $other) {
            $this->errors[$field] = $message;
        }

        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
