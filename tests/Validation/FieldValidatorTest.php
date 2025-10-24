<?php

declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Tests\Validation;

use Joomla\Component\Nxpeasyforms\Administrator\Service\Validation\FieldValidator;
use Joomla\Component\Nxpeasyforms\Administrator\Service\Validation\ValidationResult;
use PHPUnit\Framework\TestCase;

final class FieldValidatorTest extends TestCase
{
    public function testValidEmailField(): void
    {
        $validator = new FieldValidator();
        $fields = [
            ['type' => 'email', 'name' => 'email', 'label' => 'Email'],
        ];

        $result = $validator->validateAll($fields, ['email' => 'test@example.com'], []);

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertTrue($result->isValid());
        $this->assertSame('test@example.com', $result->getSanitisedData()['email']);
    }

    public function testMissingRequiredFieldCreatesError(): void
    {
        $validator = new FieldValidator();
        $fields = [
            ['type' => 'text', 'name' => 'name', 'label' => 'Name', 'required' => true],
        ];

        $result = $validator->validateAll($fields, [], []);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('name', $result->getErrors());
    }
}
