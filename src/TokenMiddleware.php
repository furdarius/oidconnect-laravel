<?php

namespace Furdarius\OIDConnect;

use Closure;
use Furdarius\OIDConnect\Contract\Authenticator;
use Furdarius\OIDConnect\Exception\AuthenticationException;
use Lcobucci\Clock\Clock;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\ValidAt;
use Lcobucci\JWT\Validator;

class TokenMiddleware
{
    const AUTH_HEADER = "Authorization";

    /**
     * @var Parser
     */
    private $parser;
    /**
     * @var Validator
     */
    private $validator;
    /**
     * @var Clock
     */
    private $clock;
    /**
     * @var Signer
     */
    private $signer;
    /**
     * @var KeysFetcher
     */
    private $keysFetcher;
    /**
     * @var TokenRefresher
     */
    private $tokenRefresher;
    /**
     * @var Authenticator
     */
    private $authenticator;

    /**
     * TokenMiddleware constructor.
     *
     * @param Parser         $parser
     * @param Validator      $validator
     * @param Clock          $clock
     * @param Signer         $signer
     * @param KeysFetcher    $keysFetcher
     * @param TokenRefresher $tokenRefresher
     * @param Authenticator  $authenticator
     */
    public function __construct(
        Parser $parser,
        Validator $validator,
        Clock $clock,
        Signer $signer,
        KeysFetcher $keysFetcher,
        TokenRefresher $tokenRefresher,
        Authenticator $authenticator
    ) {
        $this->parser = $parser;
        $this->validator = $validator;
        $this->clock = $clock;
        $this->signer = $signer;
        $this->keysFetcher = $keysFetcher;
        $this->tokenRefresher = $tokenRefresher;
        $this->authenticator = $authenticator;
    }

    /**
     * Validate an ID Token of incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     *
     * @return mixed
     * @throws \Furdarius\OIDConnect\Exception\AuthenticationException
     */
    public function handle($request, Closure $next)
    {
        $bearer = $request->headers->get(TokenMiddleware::AUTH_HEADER);

        if (empty($bearer)) {
            throw new AuthenticationException("Request doesn't contain auth token");
        }

        $parts = explode(" ", $bearer);

        if (count($parts) < 2) {
            throw new AuthenticationException("Invalid format of auth header");
        }

        $jwt = $parts[1];

        $token = $this->parser->parse($jwt);
        $kid = $token->headers()->get('kid');
        $key = $this->keysFetcher->getByKID($kid);

        if (!$key) {
            throw new AuthenticationException("Public Key doesn't exist");
        }

        if (!$this->validator->validate($token, new SignedWith($this->signer, $key))) {
            throw new AuthenticationException("Token sign is invalid");
        }

        $claims = $token->claims();

        if (!$this->validator->validate($token, new ValidAt($this->clock))) {
            $sub = $claims->get('sub');
            $iss = $claims->get('iss');

            try {
                $refreshedIDToken = $this->tokenRefresher->refreshIDToken($sub, $iss);
                $this->authUser($token->claims());

                return $next($request)
                    ->header('Access-Control-Expose-Headers', 'Authorized')
                    ->header('Authorized', "Refreshed " . $refreshedIDToken);
            } catch (\RuntimeException $e) {
                throw new AuthenticationException($e->getMessage());
            }
        }

        $this->authenticator->authUserByEmail($claims->get('email'));

        return $next($request);
    }
}
