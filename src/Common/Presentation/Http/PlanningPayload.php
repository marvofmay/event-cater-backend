<?php

declare(strict_types=1);

namespace App\Common\Presentation\Http;

use App\Common\Application\DTO\Page;
use App\Common\Domain\Exception\BusinessRuleViolation;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class PlanningPayload
{
    public function __construct(private array $data)
    {
    }

    public static function json(Request $request): self
    {
        if (strlen($request->getContent()) > 2097152) {
            throw new BadRequestHttpException('JSON body exceeds 2 MiB.');
        }
        try {
            $object = json_decode($request->getContent(), flags: JSON_THROW_ON_ERROR);
            if (!$object instanceof \stdClass) {
                throw new \JsonException('Expected object.');
            }
            return new self(json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR));
        } catch (\JsonException $e) {
            throw new BadRequestHttpException('A valid JSON object is required.', $e);
        }
    }

    public function text(string $key, bool $required = true): ?string
    {
        $value = $this->data[$key] ?? null;
        if ($value === null && !$required) {
            return null;
        }
        if (!is_string($value) || ($required && trim($value) === '') || mb_strlen($value) > 20000) {
            throw new BusinessRuleViolation($key.' must be a string'.($required ? ' and is required.' : '.'));
        }
        return trim($value) === '' ? null : trim($value);
    }

    public function integer(string $key, bool $required = true): ?int
    {
        $value = $this->data[$key] ?? null;
        if ($value === null && !$required) {
            return null;
        }
        if (!is_int($value)) {
            throw new BusinessRuleViolation($key.' must be an integer.');
        }
        return $value;
    }

    public function version(): int
    {
        $version = $this->integer('version');
        if ($version < 1) {
            throw new BusinessRuleViolation('version must be positive.');
        }
        return $version;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function quantity(string $key, bool $required = true): ?\App\Common\Domain\ValueObject\Quantity
    {
        if (!$required && !array_key_exists($key, $this->data)) {
            return null;
        }
        $value = $this->data[$key] ?? null;
        if (!is_int($value) && !is_string($value)) {
            throw new BusinessRuleViolation($key.' must be an integer or decimal string, never a float.');
        }
        return \App\Common\Domain\ValueObject\Quantity::from($value);
    }

    public function boolean(string $key): bool
    {
        if (!isset($this->data[$key]) || !is_bool($this->data[$key])) {
            throw new BusinessRuleViolation($key.' must be a boolean.');
        }
        return $this->data[$key];
    }

    public function uuid(string $key, bool $required = true): ?string
    {
        $value = $this->text($key, $required);
        return $value === null ? null : self::identifier($value);
    }

    public static function identifier(string $value): string
    {
        if (!Uuid::isValid($value)) {
            throw new BusinessRuleViolation('Invalid UUID.');
        }
        return Uuid::fromString($value)->toString();
    }

    public function object(string $key): self
    {
        $value = $this->data[$key] ?? [];
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new BusinessRuleViolation($key.' must be an object.');
        }
        return new self($value);
    }

    public function list(string $key, int $max = 300): array
    {
        $value = $this->data[$key] ?? null;
        if (!is_array($value) || !array_is_list($value) || count($value) > $max) {
            throw new BusinessRuleViolation($key.' must be a list of at most '.$max.' items.');
        }
        return $value;
    }

    public static function item(mixed $value): self
    {
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new BusinessRuleViolation('List item must be an object.');
        }
        return new self($value);
    }

    public function labels(string $key): array
    {
        if (!array_key_exists($key, $this->data)) {
            return [];
        }
        $values = $this->list($key, 30);
        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '' || mb_strlen($value) > 100) {
                throw new BusinessRuleViolation($key.' must contain nonempty strings of at most 100 characters.');
            }
        }
        return array_values(array_unique($values));
    }

    public function dateTime(string $key, bool $required = false): ?\DateTimeImmutable
    {
        $value = $this->text($key, $required);
        if ($value === null) {
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            throw new BusinessRuleViolation($key.' must be RFC3339 at whole-second precision with an explicit UTC offset.');
        }
        try {
            $date = new \DateTimeImmutable($value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) {
                throw new \Exception();
            }
            return $date;
        } catch (\Exception) {
            throw new BusinessRuleViolation($key.' contains an invalid date/time.');
        }
    }

    public static function page(Request $request): Page
    {
        $number = $request->query->getString('page', '1');
        $limit = $request->query->getString('limit', '20');
        if (!ctype_digit($number) || !ctype_digit($limit)) {
            throw new BusinessRuleViolation('Invalid pagination.');
        }
        return new Page((int) $number, (int) $limit);
    }

    public static function search(Request $request): string
    {
        $value = trim($request->query->getString('q'));
        if (mb_strlen($value) > 200) {
            throw new BusinessRuleViolation('Search is too long.');
        }
        return $value;
    }
}
