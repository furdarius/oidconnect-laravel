<?php

namespace Furdarius\OIDConnect\Http\Controllers;

use Furdarius\OIDConnect\Exception\TokenStorageException;
use Furdarius\OIDConnect\RequestTokenParser;
use Furdarius\OIDConnect\TokenRefresher;
use Furdarius\OIDConnect\TokenStorage;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AuthController extends BaseController
{
    /**
     *
     * @return RedirectResponse
     */
    public function redirect()
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

        return response()->json([
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

        return response()->json([
            'token' => $refreshedIDToken,
        ]);
    }
}
