<?php

namespace Furdarius\OIDConnect;

use Furdarius\OIDConnect\Adapter\JSONFetcherAdapter;
use Furdarius\OIDConnect\Adapter\NullAuthenticatorAdapter;
use Furdarius\OIDConnect\Contract\Authenticator;
use Furdarius\OIDConnect\Contract\JSONGetter;
use Furdarius\OIDConnect\Contract\JSONPoster;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider as ServiceProviderIlluminate;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Lcobucci\Clock\Clock;
use Lcobucci\Clock\SystemClock;
use Lcobucci\Jose\Parsing\Decoder;
use Lcobucci\Jose\Parsing\Parser as JsonDecoder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Validation\Validator as JWTValidator;
use Lcobucci\JWT\Validator;


class ServiceProvider extends ServiceProviderIlluminate
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([realpath(__DIR__ . '/../config/opidconnect.php') => config_path('opidconnect.php')]);
        $this->loadMigrationsFrom(realpath(__DIR__ . '/../migrations'));
        $this->loadRoutesFrom(realpath(__DIR__ . '/Http/routes.php'));

        $socialite = $this->app->make(SocialiteFactory::class);

        $socialite->extend(
            'myoidc',
            function ($app) use ($socialite) {
                $config = $app['config']['opidconnect'];

                return new OIDConnectSocialiteProvider(
                    $app[Request::class],
                    $app[Parser::class],
                    $config['client_id'],
                    $config['client_secret'],
                    $config['redirect'],
                    $config['auth'],
                    $config['token']
                );
            }
        );
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(JSONFetcherAdapter::class, function ($app) {
            $config = $app['config']['opidconnect']['guzzle'];

            $cl = new Client($config);

            return new JSONFetcherAdapter($cl);
        });

        $this->app->singleton(JSONGetter::class, function ($app) {
            return $app[JSONFetcherAdapter::class];
        });

        $this->app->singleton(JSONPoster::class, function ($app) {
            return $app[JSONFetcherAdapter::class];
        });

        $this->app->singleton(Decoder::class, function ($app) {
            return new JsonDecoder();
        });

        $this->app->singleton(Parser::class, function ($app) {
            return new Token\Parser($app[Decoder::class]);
        });

        $this->app->singleton(Validator::class, function ($app) {
            return new JWTValidator();
        });

        $this->app->singleton(Clock::class, function ($app) {
            return new SystemClock();
        });

        $this->app->singleton(Signer::class, function ($app) {
            return new Signer\Rsa\Sha256();
        });

        $this->app->bind(KeysFetcher::class, function ($app) {
            $config = $app['config']['opidconnect'];

            return new KeysFetcher(
                $app[JSONGetter::class],
                $app['cache.store'],
                $app[Decoder::class],
                $config['keys']
            );
        });

        $this->app->bind(TokenRefresher::class, function ($app) {
            $config = $app['config']['opidconnect'];

            return new TokenRefresher(
                $app[JSONPoster::class],
                $app[TokenStorage::class],
                $config['client_id'],
                $config['client_secret'],
                $config['redirect'],
                $config['token']
            );
        });

        $this->app->singleton(Authenticator::class, function ($app) {
            return new NullAuthenticatorAdapter();
        });
    }
}
