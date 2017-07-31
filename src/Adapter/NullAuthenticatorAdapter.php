<?php

namespace Furdarius\OIDConnect\Adapter;

use Furdarius\OIDConnect\Contract\Authenticator;

class NullAuthenticatorAdapter implements Authenticator
{
    public function authUserByEmail(string $email)
    {
    }
}
