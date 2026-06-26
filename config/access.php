<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Area → permission map
    |--------------------------------------------------------------------------
    |
    | Each "area" is a role-gated section of the panel. The value is the
    | permission that unlocks it. Grant that permission to any role in the
    | Roles UI and that role gains access — no code change required.
    |
    | To gate a NEW area:
    |   1. Add an entry here (area key => permission name).
    |   2. Create the permission in RolesAndPermissionsSeeder::PERMISSIONS.
    |   3. Have the resource/page gate with Access::allows('<area>').
    |
    | Super-admins always pass (see App\Support\Access).
    |
    */

    'areas' => [
        'assets' => 'manage assets',
    ],

];
