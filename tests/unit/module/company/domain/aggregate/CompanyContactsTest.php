<?php

declare(strict_types=1);

namespace App\tests\unit\module\company\domain\aggregate;

use App\Module\Company\Domain\Aggregate\ValueObject\Emails;
use App\Module\Company\Domain\Aggregate\ValueObject\Websites;
use PHPUnit\Framework\TestCase;

final class CompanyContactsTest extends TestCase
{
    public function testWebsiteWithoutProtocolIsUnprocessable(): void
    {
        try {
            Websites::fromArray(['mapa-smakow.pl']);
            self::fail('A website without a protocol must be rejected.');
        } catch (\Exception $error) {
            self::assertSame(422, $error->getCode());
            self::assertSame('website.invalidProtocol', $error->getMessage());
        }
    }

    public function testHttpsWebsiteAndBlankEntriesAreKeptClean(): void
    {
        self::assertSame(
            ['https://mapa-smakow.pl'],
            Websites::fromArray(['', ' https://mapa-smakow.pl '])->toArray(),
        );
    }

    public function testInvalidEmailIsUnprocessable(): void
    {
        try {
            Emails::fromArray(['nie-email']);
            self::fail('An invalid email must be rejected.');
        } catch (\Exception $error) {
            self::assertSame(422, $error->getCode());
            self::assertSame('email.invalid', $error->getMessage());
        }
    }
}
