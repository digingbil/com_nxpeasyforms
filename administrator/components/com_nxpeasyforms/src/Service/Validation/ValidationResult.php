<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Validation;

/**
 * Immutable value object that captures form validation outcome.
 */
final class ValidationResult
{
    /**
     * @var array<string, mixed>
     */
    private array $sanitisedData;

    /**
     * @var array<string, string>
     */
    private array $errors;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $fieldMeta;

    /**
     * @param array<string, mixed> $sanitisedData
     * @param array<string, string> $errors
     * @param array<int, array<string, mixed>> $fieldMeta
     */
    public function __construct(array $sanitisedData, array $errors, array $fieldMeta)
    {
        $this->sanitisedData = $sanitisedData;
        $this->errors = $errors;
        $this->fieldMeta = $fieldMeta;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSanitisedData(): array
    {
        return $this->sanitisedData;
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFieldMeta(): array
    {
        return $this->fieldMeta;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, string>, 2: array<int, array<string, mixed>>}
     */
    public function toTuple(): array
    {
        return [$this->sanitisedData, $this->errors, $this->fieldMeta];
    }
}
