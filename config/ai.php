<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Leave reason enhancement
    |--------------------------------------------------------------------------
    |
    | The Prism provider and model used to enhance leave-request reasons.
    | The provider must match a key in config/prism.php and have its API key
    | configured (e.g. ANTHROPIC_API_KEY). If no key is set, the AI actions
    | are hidden automatically.
    |
    */

    'enhance' => [
        'provider' => env('AI_ENHANCE_PROVIDER', 'gemini'),
        'model' => env('AI_ENHANCE_MODEL', 'gemini-2.5-flash'),
    ],

];
