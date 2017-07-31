![](https://habrastorage.org/web/7c1/a19/e76/7c1a19e76cf54cb1adf2217a156b7310.png)

The OpenIDConnect Laravel package is meant to provide you an opportunity to easily authenticate users using OpenID Connect protocol.

## Installation

To install this package you will need:
* Laravel 5.1+
* PHP 7.1+

Use composer to install
```bash
composer require furdarius/oidconnect-laravel:0.1.x@dev
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


## Usage

At first define `login` and `callback` routes:
* `Route::get('/login', 'Auth\LoginController@login');`
* `Route::get('/login/callback', 'Auth\LoginController@callback');`

Also you will need to add credentials for the OpenID Connect service your application utilizes.
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

User authentication controller will looks like:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Furdarius\OIDConnect\Exception\TokenStorageException;
use Furdarius\OIDConnect\TokenStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LoginController extends Controller
{
    /**
     * Login method is used to redirect user on Auth Service login page.
     * It can be good idea to give it route like "miwebsite.com/login:
     *     Route::get('/login', 'Auth\LoginController@login');
     * So, when user open this route, he will redirected to AuthService and see login form.
     * 
     * @param Request $request
     * @return RedirectResponse
     */
    public function login(Request $request)
    {
        /** @var \Symfony\Component\HttpFoundation\RedirectResponse $redirectResponse */
        $redirectResponse = \Socialite::with('myoidc')->stateless()->redirect();

        return $redirectResponse;
    }

    /**
     * When user enter auth data, AuthService will redirect him on callback route.
     * You can define is as Route::get('/login/callback', 'Auth\LoginController@callback'); and then
     * setup redirect link in config (opidconnect.redirect)
     * 
     * @param Request $request
     * @param \Furdarius\OIDConnect\TokenStorage $storage
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