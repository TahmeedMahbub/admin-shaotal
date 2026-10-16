<?php

return [
    // Admin login accepts an email, or a bare username which is expanded to "<username>@<domain>"
    // because admin_users has no username column.
    'username_domain' => env('ADMIN_USERNAME_DOMAIN', 'shaotal.local'),
    'login_max_attempts' => 5,
    'per_page' => 15,
];
