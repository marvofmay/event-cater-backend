<?php

declare(strict_types=1);

namespace App\tests\unit\module\system\domain;

use App\Module\System\Domain\Service\User\PasswordPolicy;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    public function testEightCharactersAreAcceptedButSevenAreRejected(): void
    {
        self::assertSame([], PasswordPolicy::errors('Haslo12!', 'Haslo12!'));
        self::assertNotEmpty(PasswordPolicy::errors('Haslo1!', 'Haslo1!'));
    }

    public function testMaximumLengthAndOtherRulesArePreserved(): void
    {
        $maximum = 'Aa1!'.str_repeat('a', 60);
        self::assertSame([], PasswordPolicy::errors($maximum, $maximum));
        self::assertNotEmpty(PasswordPolicy::errors($maximum.'a', $maximum.'a'));
        self::assertNotEmpty(PasswordPolicy::errors('haslo12!', 'haslo12!'));
        self::assertNotEmpty(PasswordPolicy::errors('Haslo12!', 'Haslo13!'));
    }
}
