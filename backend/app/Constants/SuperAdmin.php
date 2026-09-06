<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Constants;

/**
 * The one account that's exempt from role changes and deletion
 * (UsersService), and the address new-registration notifications go to
 * (RegistrationService).
 */
final class SuperAdmin
{
    public const EMAIL = 'arsi.india.info@gmail.com';
}
