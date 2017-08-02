<?php

namespace Furdarius\OIDConnect;

use Illuminate\Database\Query\Builder as QueryBuilder;

class TokenStorage
{
    /**
     * @var QueryBuilder
     */
    private $query;

    /**
     * TokenStorage constructor.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     */
    public function __construct(QueryBuilder $query)
    {
        $this->query = $query;
    }

    /**
     * Save refresh token in DB
     *
     * @param string $idToken
     * @param string $refreshToken
     *
     * @return bool
     */
    public function saveRefresh(string $idToken, string $refreshToken): bool
    {
        return $this->query->getConnection()
            ->table("tokens")
            ->insert([
                'id_token' => $idToken,
                'refresh_token' => $refreshToken,
            ]);
    }


    /**
     * Fetch and return Refresh token by ID Token
     *
     * @param string $idToken
     *
     * @return null|string
     */
    public function fetchRefresh(string $idToken): ?string
    {
        /* @var \Illuminate\Support\Collection $list */
        $list = $this->query->getConnection()
            ->table("tokens")
            ->select(['refresh_token'])
            ->where('id_token', $idToken)
            ->limit(1)
            ->get();

        if ($list->isEmpty()) {
            return null;
        }

        return $list->first()->refresh_token;
    }

    /**
     * Delete Refresh token by ID Token
     *
     * @param string $idToken
     */
    public function deleteRefresh(string $idToken): void
    {
        $this->query->getConnection()
            ->table("tokens")
            ->select(['refresh_token'])
            ->where('id_token', $idToken)
            ->delete();
    }
}
