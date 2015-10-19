<?php
namespace org\ccextractor\submissionplatform\objects;

use SplEnum;

class UserRole extends SplEnum
{
    // Default is file.
    const __default = -1;
    // Guest user
    const GUEST = -1;
    // Admin
    const ADMIN = 0;
    // Contributor
    const CONTRIBUTOR = 1;
}