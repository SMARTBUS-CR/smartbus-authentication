<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super-admin';
    case CompanyAdmin = 'company-admin';
    case Driver = 'driver';
    case Passenger = 'passenger';

    public const SUPER_ADMIN = self::SuperAdmin;

    public const COMPANY_ADMIN = self::CompanyAdmin;

    public const DRIVER = self::Driver;

    public const PASSENGER = self::Passenger;

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::CompanyAdmin => 'Administrador',
            self::Driver => 'Conductor',
            self::Passenger => 'Pasajero',
        };
    }
}
