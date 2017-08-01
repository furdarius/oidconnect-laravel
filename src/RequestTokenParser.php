<?php

namespace Furdarius\OIDConnect;

use Furdarius\OIDConnect\Exception\AuthenticationException;
use Lcobucci\JWT\Parser;
use Illuminate\Http\Request;
use Lcobucci\JWT\Token;

class RequestTokenParser
{
    const AUTH_HEADER = "Authorization";

    /**
     * @var Parser
     */
    private $parser;

    /**
     * TokenMiddleware constructor.
     *
     * @param Parser         $parser
     */
    public function __construct(
        Parser $parser
    ) {
        $this->parser = $parser;
    }

    /**
     * @param Request $request
     *
     * @return Token
     */
    public function parse(Request $request): Token
    {
        $bearer = $request->headers->get(static::AUTH_HEADER);

        if (empty($bearer)) {
            throw new AuthenticationException("Request doesn't contain auth token");
        }

        $parts = explode(" ", $bearer);

        if (count($parts) < 2) {
            throw new AuthenticationException("Invalid format of auth header");
        }

        $jwt = $parts[1];

        return $this->parser->parse($jwt);
    }
}
