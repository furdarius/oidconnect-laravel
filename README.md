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

Open config/app.php and register the required service provider above your application providers.
```php
'providers' => [
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

Also you will need to define `redirect`, `callback` and `refresh` routes:
```php
Route::get('/auth/redirect', 'Auth\LoginController@redirect');
Route::get('/auth/callback', 'Auth\LoginController@callback');
Route::get('/auth/refresh', 'Auth\LoginController@refresh');
```

User authentication controller will looks like:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Furdarius\OIDConnect\Exception\TokenStorageException;
use Furdarius\OIDConnect\RequestTokenParser;
use Furdarius\OIDConnect\TokenRefresher;
use Furdarius\OIDConnect\TokenStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LoginController extends Controller
{
    /**
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function redirect(Request $request)
    {
        /** @var \Symfony\Component\HttpFoundation\RedirectResponse $redirectResponse */
        $redirectResponse = \Socialite::with('myoidc')->stateless()->redirect();

        return $redirectResponse;
    }

    /**
     * @param Request                            $request
     * @param \Furdarius\OIDConnect\TokenStorage $storage
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function callback(Request $request, TokenStorage $storage)
    {
        /** @var \Laravel\Socialite\Two\User $user */
        $user = \Socialite::with('myoidc')->stateless()->user();

        if (!$storage->saveRefresh($user['sub'], $user['iss'], $user->refreshToken)) {
            throw new TokenStorageException("Failed to save refresh token");
        }

        return $this->responseJson([
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'token' => $user->token,
        ]);
    }

    /**
     * @param Request                              $request
     * @param \Furdarius\OIDConnect\TokenRefresher $refresher
     * @param RequestTokenParser                   $parser
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh(Request $request, TokenRefresher $refresher, RequestTokenParser $parser)
    {
        /**
         * We cant get claims from Token interface, so call claims method implicitly
         * link: https://github.com/lcobucci/jwt/pull/186
         *
         * @var $token \Lcobucci\JWT\Token\Plain
         */
        $token = $parser->parse($request);

        $claims = $token->claims();

        $sub = $claims->get('sub');
        $iss = $claims->get('iss');

        $refreshedIDToken = $refresher->refreshIDToken($sub, $iss);

        return $this->responseJson([
            'token' => $refreshedIDToken,
        ]);
    }
}
```


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