<?php
// src/Validator/Constraints/LdapEmailExistsValidator.php
namespace App\Validator\Constraints;

use App\Classes\Ldap;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

//todo: pas utile ?
final class LdapEmailExistsValidator extends ConstraintValidator
{
    public function __construct(private readonly Ldap $lookup)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof LdapEmailExists) {
            throw new UnexpectedTypeException($constraint, LdapEmailExists::class);
        }

        $email = trim((string)$value);
        if ($email === '') {
            return;
        }

        if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return;
        }
        if ($this->lookup->emailExists($email) === false) {
            $this->context
                ->buildViolation($constraint->message)
                ->setParameter('{{ email }}', $email)
                ->addViolation();
        }
    }
}
