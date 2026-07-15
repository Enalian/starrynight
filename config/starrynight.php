<?php

return [
    'total_stars' => env('STARRYNIGHT_TOTAL_STARS', 75),
    'flicker_count' => env('STARRYNIGHT_FLICKER_COUNT', 15),
    'enable_meteors' => env('STARRYNIGHT_ENABLE_METEORS', 'true'),
    'star_color' => env('STARRYNIGHT_STAR_COLOR', '#ffffff'),
    'flicker_speed' => env('STARRYNIGHT_FLICKER_SPEED', '3000'),
    'background_image' => env('STARRYNIGHT_BACKGROUND_IMAGE', ''),
    'background_blur' => env('STARRYNIGHT_BACKGROUND_BLUR', '5'),
];
