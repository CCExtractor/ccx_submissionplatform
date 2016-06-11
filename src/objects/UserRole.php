<?php
namespace org\ccextractor\submissionplatform\objects;

class UserRole extends BasicEnum
{
    // Default is file.
    const __default = self::USER;
    // Guest user
    const USER = -1;
    // Admin
    const ADMIN = 0;
    // Contributor
    const CONTRIBUTOR = 1;

    private static $names = [
        self::USER => "User",
        self::ADMIN => "Admin",
        self::CONTRIBUTOR => "Contributor"
    ];

    private static $roles = [
        "User" => [self::USER, self::ADMIN, self::CONTRIBUTOR],
        "Admin" => [self::ADMIN],
        "Contributor" => [self::ADMIN, self::CONTRIBUTOR]
    ];

    /**
     * UserRole constructor.
     */
    public function __construct($enumValue)
    {
        parent::__construct($enumValue, UserRole::__default);
    }


    /**
     * Checks if a given role id matches a const defined value.
     *
     * @param $role_id
     *
     * @return bool
     */
    public static function isValidValue($role_id)
    {
        return array_key_exists($role_id, self::$names);
    }

    /**
     * Checks if a given role name belongs to this userRole.
     *
     * @param string $roleName The role name to check.
     * @return bool True on success, false on failure.
     */
    public function hasRole($roleName)
    {
        if(array_key_exists($roleName, self::$roles)){
            return in_array((int)$this,self::$roles[$roleName]);
        }
        return false;
    }


    public function toString()
    {
        return self::$names[(int)$this];
    }
}