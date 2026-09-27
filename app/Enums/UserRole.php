<?php

namespace App\Enums;

/**
 * User roles in the College LMS.
 *
 * Every user has exactly one role. Role controls access to dashboards,
 * routes, and permissions.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Teacher = 'teacher';
    case Student = 'student';

    /**
     * Human-readable label for display in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Teacher => 'Teacher',
            self::Student => 'Student',
        };
    }

    /**
     * Returns all role values as a plain array.
     * Useful for validation rules like Rule::in(UserRole::values()).
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}