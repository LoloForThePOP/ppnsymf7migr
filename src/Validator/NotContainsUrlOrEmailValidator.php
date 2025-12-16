<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class NotContainsUrlOrEmailValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof NotContainsUrlOrEmail) {
            throw new UnexpectedTypeException($constraint, NotContainsUrlOrEmail::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;

        $hasUrl = (bool) preg_match('~https?://|www\\.~i', $value);
        $hasEmail = (bool) preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\\.[A-Z]{2,}/i', $value);

        if ($hasUrl || $hasEmail) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
        }

    }
}
