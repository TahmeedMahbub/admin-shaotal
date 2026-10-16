<?php

return [
    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
    'api_key' => env('CLOUDINARY_API_KEY'),
    'api_secret' => env('CLOUDINARY_API_SECRET'),

    // Root folder; callers pass a sub-folder such as "products" -> "shaotal/products".
    'root_folder' => env('CLOUDINARY_ROOT_FOLDER', 'shaotal'),

    'allowed_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
    'max_kb' => 5120,
];
