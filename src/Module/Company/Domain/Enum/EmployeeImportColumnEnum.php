<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Enum;

enum EmployeeImportColumnEnum: int
{
    case FIRST_NAME = 0;
    case LAST_NAME = 1;
    case PESEL = 2;
    case EMAIL = 3;
    case PHONE = 4;
    case STREET = 5;
    case POSTCODE = 6;
    case CITY = 7;
    case COUNTRY = 8;
    case EMPLOYMENT_FROM = 9;
    case DEPARTMENT_UUID = 10;
    case POSITION_UUID = 11;
    case CONTACT_TYPE_UUID = 12;
    case ROLE_UUID = 13;
    case PARENT_EMPLOYEE_PESEL = 14;
    case INTERNAL_CODE = 15;
    case EXTERNAL_CODE = 16;
    case EMPLOYMENT_TO = 17;
    case ACTIVE = 18;
    case IS_PESEL_FAKE = 19;

    case DYNAMIC_IS_EMPLOYEE_WITH_PESEL_ALREADY_EXISTS = 100;
    case DYNAMIC_AGGREGATE_UUID = 101;

    public function label(): string
    {
        return match ($this) {
            self::FIRST_NAME => 'firstName',
            self::LAST_NAME => 'lastName',
            self::PESEL => 'pesel',
            self::EMAIL => 'email',
            self::PHONE => 'phone',
            self::STREET => 'street',
            self::POSTCODE => 'postcode',
            self::CITY => 'city',
            self::COUNTRY => 'country',
            self::EMPLOYMENT_FROM => 'employmentFrom',
            self::DEPARTMENT_UUID => 'departmentUUID',
            self::POSITION_UUID => 'positionUuid',
            self::CONTACT_TYPE_UUID => 'contactTypeUUID',
            self::ROLE_UUID => 'roleUuid',
            self::PARENT_EMPLOYEE_PESEL => 'parentEmployeePESEL',
            self::INTERNAL_CODE => 'internalCode',
            self::EXTERNAL_CODE => 'externalCode',
            self::EMPLOYMENT_TO => 'employmentTo',
            self::ACTIVE => 'active',
            self::IS_PESEL_FAKE => 'isPeselFake',
            self::DYNAMIC_IS_EMPLOYEE_WITH_PESEL_ALREADY_EXISTS => 'isEmployeeWithPESELAlreadyExists',
            self::DYNAMIC_AGGREGATE_UUID => 'aggregateUuid',
        };
    }
}
