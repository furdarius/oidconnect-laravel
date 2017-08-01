<?php

namespace Furdarius\OIDConnect\Http\Controllers;

use Furdarius\OIDConnect\Exception\AuthenticationException;
use Furdarius\OIDConnect\Exception\TokenStorageException;
use Furdarius\OIDConnect\TokenRefresher;
use Furdarius\OIDConnect\TokenStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Lcobucci\JWT\Parser;
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

        return $this->responseJson([
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'token' => $user->token,
        ]);
    }

    /**
     * @param array|\JsonSerializable $data
     * @param int                     $status
     * @param array                   $headers
     *
     * @return JsonResponse
     */
    protected function responseJson($data, int $status = 200, array $headers = []): JsonResponse
    {
        return response()->json($data, $status, $headers)
            ->setEncodingOptions(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * @param Request        $request
     * @param TokenRefresher $refresher
     * @param Parser         $parser
     *
     * @return AuthenticationException|JsonResponse
     */
    public function refresh(Request $request, TokenRefresher $refresher, Parser $parser)
    {
        $data = $request->json()->all();

        if (!isset($data['token'])) {
            return new AuthenticationException("Failed to get JWT token from input");
        }

        $jwt = $data['token'];

        /**
         * We cant get claims from Token interface, so call claims method implicitly
         * link: https://github.com/lcobucci/jwt/pull/186
         *
         * @var $token \Lcobucci\JWT\Token\Plain
         */
        $token = $parser->parse($jwt);

        $claims = $token->claims();

        $sub = $claims->get('sub');
        $iss = $claims->get('iss');

        $refreshedIDToken = $refresher->refreshIDToken($sub, $iss);

        return $this->responseJson([
            'token' => $refreshedIDToken,
        ]);
    }
}
