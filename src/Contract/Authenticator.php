<?php

namespace Furdarius\OIDConnect\Contract;

interface Authenticator
{
    /**
     * @param string $email
     *
     * @return mixed
     */
    public function authUserByEmail(string $email);
}
