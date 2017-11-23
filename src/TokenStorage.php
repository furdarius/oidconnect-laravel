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
     * @param  \Illuminate\Database\Query\Builder $query
     */
    public function __construct(QueryBuilder $query)
    {
        $this->query = $query;
    }

    /**
     * Save refresh token in DB
     *
     * @param string $sub
     * @param string $iss
     * @param string $refreshToken
     * @return bool
     */
    public function saveRefresh(string $sub, string $iss, string $refreshToken): bool
    {
        return $this->query->getConnection()
            ->table("tokens")
            ->updateOrInsert([
                'sub' => $sub,
                'iss' => $iss,
            ], [
                'refresh_token' => $refreshToken,
            ]);
    }


    /**
     * Fetch and return Refresh token by iss and sub params
     *
     * @param string $sub
     * @param string $iss
     * @return null|string
     */
    public function fetchRefresh(string $sub, string $iss): ?string
    {
        /* @var \Illuminate\Support\Collection $list */
        $list = $this->query->getConnection()
            ->table("tokens")
            ->select(['refresh_token'])
            ->where('sub', $sub)
            ->where('iss', $iss)
            ->limit(1)
            ->get();

        if ($list->isEmpty()) {
            return null;
        }

        return $list->first()->refresh_token;
    }
}