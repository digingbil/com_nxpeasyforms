<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Validation;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Immutable value object that captures form validation outcome.
 * Contains sanitized form data, validation errors and field metadata.
 * Used to safely pass validation results between components.
 * @since 1.0.0
 */
final class ValidationResult
{
    /**
     * Sanitized form data with validated and filtered values.
     * Each key corresponds to a form field name and contains the processed value.
     *
     * @var array<string, mixed>
     * @since   1.0.0
     */
    private array $sanitizedData;

    /**
     * Validation error messages indexed by field name.
     * Empty array indicates no validation errors occurred.
     *
     * @var array<string, string>
     * @since   1.0.0
     */
    private array $errors;

    /**
     * Field metadata array containing field properties and configuration.
     * Each entry provides additional context about form fields.
     *
     * @var array<int, array<string, mixed>>
     * @since   1.0.0
     */
    private array $fieldMeta;

    /**
     * Constructor for immutable validation result value object.
     * Initializes the validation result with sanitized data, error messages, and field metadata.
     * This object captures the complete outcome of form validation and should not be modified after creation.
     *
     * @param   array<string, mixed>             $sanitizedData  Sanitized form data with validated and filtered field values
     * @param   array<string, string>            $errors         Validation error messages indexed by field name
     * @param   array<int, array<string, mixed>> $fieldMeta      Field metadata array containing field properties and configuration
     *
     * @return  void
     * @since   1.0.0
     */
    public function __construct(array $sanitizedData, array $errors, array $fieldMeta)
    {
        $this->sanitisedData = $sanitizedData;
        $this->errors = $errors;
        $this->fieldMeta = $fieldMeta;
    }

    /**
     * Retrieves the sanitized form data with validated and filtered field values.
     * This data is safe to use throughout the application as it has been sanitized
     * and validated according to the form's validation rules.
     *
     * @return  array<string, mixed>  The sanitized form data indexed by field name
     * @since   1.0.0
     */
    public function getSanitisedData(): array
    {
        return $this->sanitisedData;
    }

    /**
     * Retrieves all validation error messages indexed by field name.
     * Each entry contains a human-readable error message for the corresponding field.
     * An empty array indicates the form passed all validation checks.
     *
     * @return  array<string, string>  Validation error messages indexed by field name
     * @since   1.0.0
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Retrieves the field metadata array containing field properties and configuration.
     * Provides additional context about form fields such as type, constraints, and validation rules.
     *
     * @return  array<int, array<string, mixed>>  Field metadata indexed by field position
     * @since   1.0.0
     */
    public function getFieldMeta(): array
    {
        return $this->fieldMeta;
    }

    /**
     * Checks if the validation result contains any errors.
     * Useful for conditional logic to determine if form validation failed.
     *
     * @return  bool  True if validation errors exist, false otherwise
     * @since   1.0.0
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Checks if the form validation succeeded with no errors.
     * Provides a positive assertion that all validation checks passed.
     *
     * @return  bool  True if validation passed and no errors exist, false otherwise
     * @since   1.0.0
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * Converts the validation result into a tuple format containing all three components.
     * Useful for destructuring assignments and functional programming patterns where
     * all validation result components need to be accessed simultaneously.
     *
     * @return  array{0: array<string, mixed>, 1: array<string, string>, 2: array<int, array<string, mixed>>}  Tuple of sanitised data, errors, and field metadata
     * @since   1.0.0
     */
    public function toTuple(): array
    {
        return [$this->sanitisedData, $this->errors, $this->fieldMeta];
    }
}
