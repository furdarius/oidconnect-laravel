<?php

namespace Furdarius\OIDConnect;

use Furdarius\OIDConnect\Contract\JSONPoster;
use Furdarius\OIDConnect\Exception\TokenStorageException;

class TokenRefresher
{
    protected $scopes = [
        'openid',
        'email',
        'profile',
    ];
    /**
     * @var TokenStorage
     */
    private $storage;
    private $clientId;
    private $clientSecret;
    /**
     * @var string
     */
    private $redirectUrl;
    /**
     * @var JSONPoster
     */
    private $poster;
    /**
     * @var string
     */
    private $tokenUrl;

    /**
     * TokenRefresher constructor.
     *
     * @param JSONPoster   $poster
     * @param TokenStorage $storage
     * @param string       $clientId
     * @param string       $clientSecret
     * @param string       $redirectUrl
     * @param string       $tokenUrl
     */
    public function __construct(
        JSONPoster $poster,
        TokenStorage $storage,
        string $clientId,
        string $clientSecret,
        string $redirectUrl,
        string $tokenUrl
    ) {
        $this->storage = $storage;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUrl = $redirectUrl;
        $this->poster = $poster;
        $this->tokenUrl = $tokenUrl;
    }

    /**
     * @param string $sub
     * @param string $iss
     *
     * @return string
     */
    public function refreshIDToken(string $sub, string $iss): string
    {
        $refreshToken = $this->storage->fetchRefresh($sub, $iss);

        if (!$refreshToken) {
            throw new TokenStorageException("Failed to fetch refresh token");
        }

        $data = $this->poster->post($this->tokenUrl, [], http_build_query([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'redirect_uri' => $this->redirectUrl,
            'scope' => implode(' ', $this->scopes),
        ]));

        if (!$this->storage->saveRefresh($sub, $iss, $data['refresh_token'])) {
            throw new TokenStorageException("Failed to store refresh token");
        }


        return $data['id_token'];
    }
}
