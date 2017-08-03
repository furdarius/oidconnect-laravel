<?php

namespace Furdarius\OIDConnect;

use Closure;
use Furdarius\OIDConnect\Contract\Authenticator;
use Furdarius\OIDConnect\Exception\AuthenticationException;
use Lcobucci\Clock\Clock;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\ValidAt;
use Lcobucci\JWT\Validator;

class TokenMiddleware
{
    /**
     * @var RequestTokenParser
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
     * @param RequestTokenParser $parser
     * @param Validator          $validator
     * @param Clock              $clock
     * @param Signer             $signer
     * @param KeysFetcher        $keysFetcher
     * @param TokenRefresher     $tokenRefresher
     * @param Authenticator      $authenticator
     */
    public function __construct(
        RequestTokenParser $parser,
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
        /**
         * We cant get claims from Token interface, so call claims method implicitly
         * link: https://github.com/lcobucci/jwt/pull/186
         *
         * @var $token \Lcobucci\JWT\Token\Plain
         */
        $token = $this->parser->parse($request);

        $kid = $token->headers()->get('kid');
        $key = $this->keysFetcher->getByKID($kid);

        if (!$key) {
            throw new AuthenticationException("Public Key doesn't exist");
        }

        if (!$this->validator->validate($token, new SignedWith($this->signer, $key))) {
            throw new AuthenticationException("Token sign is invalid");
        }

//        if (!$this->validator->validate($token, new ValidAt($this->clock))) {
//            throw new AuthenticationException("Token is expired");
//        }

        $this->authenticator->authUser($token->claims());

        return $next($request);
    }
}
