<?php

namespace Furdarius\OIDConnect\Contract;

use Lcobucci\JWT\Token\DataSet;

interface Authenticator
{
    /**
     * @param DataSet $claims
     *
     * @return mixed
     */
    public function authUser(DataSet $claims);
}
