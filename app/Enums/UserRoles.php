<?php

namespace App\Enums;

enum UserRoles: string
{
    case SUPER_ADMIN = 'super-admin';
    case COMPANY_ADMIN = 'company-admin';
    case DRIVER = 'driver';
    case PASSENGER = 'passenger';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::COMPANY_ADMIN => 'Administrador',
            self::DRIVER => 'Conductor',
            self::PASSENGER => 'Pasajero',
        };
    }
}
