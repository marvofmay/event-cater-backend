<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\Entity;

use App\Common\Domain\Exception\BusinessRuleViolation;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'event_customer')]
#[ORM\Index(name: 'event_customer_name', columns: ['name'])]
class EventCustomer
{
    #[ORM\Id] #[ORM\Column(length: 36)] private string $uuid;
    #[ORM\Column(length: 200)] private string $name;
    #[ORM\Column(length: 10, nullable: true)] private ?string $nip;
    #[ORM\Column(length: 14, nullable: true)] private ?string $regon;
    #[ORM\Column(length: 10, nullable: true)] private ?string $krs;
    #[ORM\Column(length: 500, nullable: true)] private ?string $address;
    #[ORM\Column(length: 30, nullable: true)] private ?string $phone;
    #[ORM\Column(length: 254, nullable: true)] private ?string $email;
    #[ORM\Column(length: 200, nullable: true)] private ?string $city;
    #[ORM\Column(length: 200, nullable: true)] private ?string $street;
    #[ORM\Column(length: 20, nullable: true)] private ?string $postalCode;
    #[ORM\Column(length: 100, nullable: true)] private ?string $country;

    public function __construct(string $uuid, string $name, ?string $nip = null, ?string $regon = null, ?string $krs = null, ?string $address = null, ?string $phone = null, ?string $email = null, ?string $city = null, ?string $street = null, ?string $postalCode = null, ?string $country = 'Polska')
    {
        $this->uuid = Uuid::fromString($uuid)->toString();
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 200) {
            throw new BusinessRuleViolation('Nazwa kontrahenta jest wymagana i może mieć do 200 znaków.');
        }
        $this->name = $name;
        $this->nip = self::identifier($nip, [10], 'NIP');
        $this->regon = self::identifier($regon, [9, 14], 'REGON');
        $this->krs = self::identifier($krs, [10], 'KRS');
        $this->address = self::optional($address);
        $this->phone = self::optional($phone);
        $this->email = self::optional($email);
        $this->city = self::optional($city);
        $this->street = self::optional($street);
        $this->postalCode = self::optional($postalCode);
        $this->country = self::optional($country);
        foreach (['city' => 200, 'street' => 200, 'postalCode' => 20, 'country' => 100] as $field => $limit) {
            if ($this->$field !== null && mb_strlen($this->$field) > $limit) {
                throw new BusinessRuleViolation('Pole adresowe '.$field.' może mieć do '.$limit.' znaków.');
            }
        }
        if ($this->city !== null || $this->street !== null || $this->postalCode !== null) {
            $this->address = implode(', ', array_filter([$this->street, trim(($this->postalCode ?? '').' '.($this->city ?? '')), $this->country], static fn (?string $part) => $part !== null && $part !== ''));
        }
        if ($this->address !== null && mb_strlen($this->address) > 500) {
            throw new BusinessRuleViolation('Adres może mieć do 500 znaków.');
        }
        if ($this->phone !== null && (!preg_match('/^\\+?[0-9 ()\\-.]{6,30}$/D', $this->phone) || strlen(preg_replace('/\\D/', '', $this->phone)) < 6)) {
            throw new BusinessRuleViolation('Podaj poprawny numer telefonu kontrahenta.');
        }
        if ($this->email !== null && (strlen($this->email) > 254 || !filter_var($this->email, FILTER_VALIDATE_EMAIL))) {
            throw new BusinessRuleViolation('Podaj poprawny adres e-mail kontrahenta.');
        }
    }

    private static function optional(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }

    private static function identifier(?string $value, array $lengths, string $label): ?string
    {
        $value = self::optional($value);
        if ($value === null) {
            return null;
        }
        $value = str_replace([' ', '-'], '', $value);
        if (!ctype_digit($value) || !in_array(strlen($value), $lengths, true)) {
            throw new BusinessRuleViolation($label.' musi zawierać '.implode(' lub ', $lengths).' cyfr.');
        }
        return $value;
    }

    public function toArray(): array
    {
        return ['uuid' => $this->uuid, 'name' => $this->name, 'nip' => $this->nip, 'regon' => $this->regon, 'krs' => $this->krs, 'address' => $this->address, 'phone' => $this->phone, 'email' => $this->email, 'city' => $this->city, 'street' => $this->street, 'postalCode' => $this->postalCode, 'country' => $this->country];
    }
}
