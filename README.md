![](https://habrastorage.org/web/7c1/a19/e76/7c1a19e76cf54cb1adf2217a156b7310.png)

The OpenIDConnect Laravel package is meant to provide you an opportunity to easily authenticate users using OpenID Connect protocol.

[![Latest Stable Version](https://poser.pugx.org/furdarius/oidconnect-laravel/v/stable)](https://packagist.org/packages/furdarius/oidconnect-laravel)
[![Latest Unstable Version](https://poser.pugx.org/furdarius/oidconnect-laravel/v/unstable)](https://packagist.org/packages/furdarius/oidconnect-laravel)
[![Total Downloads](https://poser.pugx.org/furdarius/oidconnect-laravel/downloads)](https://packagist.org/packages/furdarius/oidconnect-laravel)
[![License](https://poser.pugx.org/furdarius/oidconnect-laravel/license)](https://packagist.org/packages/furdarius/oidconnect-laravel)

## Installation

To install this package you will need:
* Laravel 5.1+
* PHP 7.1+

Use composer to install
```bash
composer require furdarius/oidconnect-laravel:dev-master
```

Open `config/app.php` and register the required service providers above your application providers.
```php
'providers' => [
    Laravel\Socialite\SocialiteServiceProvider::class,
    Furdarius\OIDConnect\ServiceProvider::class
]
```

If you'd like to make configuration changes in the configuration file you can pubish it with the following Aritsan command:
```bash
php artisan vendor:publish --provider="Furdarius\OIDConnect\ServiceProvider"
```

After that, roll up migrations:
```bash
php artisan migrate
```

## Usage


#### Configuration
At first you will need to add credentials for the OpenID Connect service your application utilizes.
These credentials should be placed in your `config/opidconnect.php` configuration file.

```php
<?php

return [
    'client_id' => 'CLIENT_ID_HERE',
    'client_secret' => 'CLIENT_SECRET_HERE',
    'redirect' => env('APP_URL') . '/auth/callback',
    'auth' => 'https://oidc.service.com/auth',
    'token' => 'https://oidc.service.com/token',
    'keys' => 'https://oidc.service.com/keys',
];
```

#### Endpoints
Now, your app has auth endpoints:
* `GET /auth/redirect` - Used to redirect client to Auth Service login page.
* `GET /auth/callback` - Used when Auth Service redirect client to callback url with code.
* `POST /auth/refresh` - Used by client for ID Token refreshing.

#### Middleware
You need to use Auth Middleware on protected routes.
Open `App\Http\Kernel` and register middleware in `$routeMiddleware`:
```php
protected $routeMiddleware = [
    'token' => \Furdarius\OIDConnect\TokenMiddleware::class
];
```

And then use it as usual:
```php
Route::middleware('token')->get('/protected', function (Request $request) {
   return "You are on protected zone";
});
```