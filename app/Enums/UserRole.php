<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super-admin';
    case Admin = 'admin';
    case Driver = 'driver';
    case Passenger = 'passenger';

    public const SUPER_ADMIN = self::SuperAdmin;

    public const ADMIN = self::Admin;

    public const DRIVER = self::Driver;

    public const PASSENGER = self::Passenger;

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => __('Super Admin'),
            self::Admin => __('Administrator'),
            self::Driver => __('Driver'),
            self::Passenger => __('Passenger'),
        };
    }
}
