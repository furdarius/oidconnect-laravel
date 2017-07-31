<?php

namespace Furdarius\OIDConnect\Contract;

interface JSONPoster
{
    /**
     * @param string $url
     * @param array  $params
     * @param null   $body
     * @param array  $options
     *
     * @return array
     */
    public function post(string $url, array $params = [], $body = null, array $options = []): array;
}
