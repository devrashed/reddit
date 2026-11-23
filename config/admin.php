<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Administrator Account
    |--------------------------------------------------------------------------
    |
    | Credentials used by the AdminUserSeeder to provision the initial
    | administrator. Keep these values in the environment file and never
    | commit real credentials to the repository.
    |
    */

    'name' => env('ADMIN_NAME', 'Administrator'),

    'email' => env('ADMIN_EMAIL'),

    'password' => env('ADMIN_PASSWORD'),

];
