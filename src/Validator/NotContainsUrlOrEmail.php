<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class NotContainsUrlOrEmail extends Constraint
{
    public string $message = "Le texte ne doit pas contenir d'adresse web ou e-mail";
}
