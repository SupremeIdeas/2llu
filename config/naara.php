<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Support & contact (blueprint Section 32)
    |--------------------------------------------------------------------------
    |
    | WhatsApp number (international format, digits only) and support email
    | surfaced to customers for live help. Leave WhatsApp blank to hide the
    | button.
    |
    */

    'support' => [
        'whatsapp' => env('SUPPORT_WHATSAPP', ''),          // e.g. 2348012345678
        'email' => env('SUPPORT_EMAIL', 'support@naarasim.com'),
        'whatsapp_message' => env('SUPPORT_WHATSAPP_MESSAGE', 'Hi NaaraSim, I need help with'),
    ],

];
