<?php

declare(strict_types=1);

namespace App\Module\Company\Structure\Validator\Constraints\Employee;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmploymentDateRangeValidator extends ConstraintValidator
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!property_exists($value, 'employmentFrom') || !property_exists($value, 'employmentTo')) {
            return;
        }

        if (null === $value->employmentFrom || '' === trim($value->employmentFrom)
            || null === $value->employmentTo || '' === trim($value->employmentTo)) {
            return;
        }

        try {
            $from = new \DateTimeImmutable($value->employmentFrom);
            $to = new \DateTimeImmutable($value->employmentTo);
        } catch (\Exception) {
            return;
        }

        if ($from >= $to) {
            $message = $this->translator->trans($constraint->message['text'], [], $constraint->message['domain']);
            $this->context->buildViolation($message)
                ->atPath('employmentTo')
                ->addViolation();
        }
    }
}
