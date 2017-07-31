<?php

namespace Furdarius\OIDConnect;

use Carbon\Carbon;
use Furdarius\OIDConnect\Contract\JSONGetter;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Lcobucci\Jose\Parsing\Decoder;
use Lcobucci\JWT\Signer\Key;

class KeysFetcher
{
    /**
     * @var JSONGetter
     */
    private $fetcher;
    /**
     * @var string
     */
    private $jwksURI;
    /**
     * @var Decoder
     */
    private $decoder;
    /**
     * @var CacheRepository
     */
    private $cache;

    /**
     * KeysFetcher constructor.
     *
     * @param JSONGetter     $fetcher
     * @param CacheRepository $cache
     * @param Decoder         $decoder
     * @param string          $jwksURI
     */
    public function __construct(JSONGetter $fetcher, CacheRepository $cache, Decoder $decoder, string $jwksURI)
    {
        $this->fetcher = $fetcher;
        $this->cache = $cache;
        $this->jwksURI = $jwksURI;
        $this->decoder = $decoder;
    }

    /**
     * Fetch JWK key from JWKs URI with defined kid
     *
     * @param string $kid
     *
     * @return Key|null
     */
    public function getByKID(string $kid): ?Key
    {
        $cacheKey = 'keys.' . $kid;

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        /** @var Key[] $keys */
        $keys = $this->fetch();

        if (!isset($keys[$kid])) {
            return null;
        }

        $this->cache->put($cacheKey, $keys[$kid], Carbon::now()->addHours(6));

        return $keys[$kid];
    }

    /**
     * Fetch list of JWKs from JWKs URI
     *
     * @return array
     */
    public function fetch(): array
    {
        $result = [];

        $data = $this->fetcher->get($this->jwksURI);
        foreach ($data['keys'] as $key) {
            $result[$key['kid']] = new Key($this->createPemFromModulusAndExponent($key['n'], $key['e']));
        }

        return $result;
    }


    /**
     *
     * Create a public key represented in PEM format from RSA modulus and exponent information
     *
     * @param string $n the RSA modulus encoded in URL Safe Base64
     * @param string $e the RSA exponent encoded in URL Safe Base64
     *
     * @return string the RSA public key represented in PEM format
     */
    protected function createPemFromModulusAndExponent(string $n, string $e): string
    {
        $modulus = $this->decoder->base64UrlDecode($n);
        $publicExponent = $this->decoder->base64UrlDecode($e);

        $components = [
            'modulus' => pack('Ca*a*', 2, $this->encodeLength(strlen($modulus)), $modulus),
            'publicExponent' => pack('Ca*a*', 2, $this->encodeLength(strlen($publicExponent)), $publicExponent),
        ];

        $RSAPublicKey = pack(
            'Ca*a*a*',
            48,
            $this->encodeLength(strlen($components['modulus']) + strlen($components['publicExponent'])),
            $components['modulus'],
            $components['publicExponent']
        );

        $rsaOID = pack('H*', '300d06092a864886f70d0101010500');
        $RSAPublicKey = chr(0) . $RSAPublicKey;
        $RSAPublicKey = chr(3) . $this->encodeLength(strlen($RSAPublicKey)) . $RSAPublicKey;
        $RSAPublicKey = pack(
            'Ca*a*',
            48,
            $this->encodeLength(strlen($rsaOID . $RSAPublicKey)),
            $rsaOID . $RSAPublicKey
        );

        $RSAPublicKey = "-----BEGIN PUBLIC KEY-----" . PHP_EOL
            . chunk_split(base64_encode($RSAPublicKey), 64, PHP_EOL)
            . '-----END PUBLIC KEY-----';

        return $RSAPublicKey;
    }

    /**
     * DER-encode the length
     *
     * DER supports lengths up to (2**8)**127, however, we'll only support lengths up to (2**8)**4.  See
     * {@link http://itu.int/ITU-T/studygroups/com17/languages/X.690-0207.pdf#p=13 X.690 paragraph 8.1.3}
     * for more information.
     *
     * @param int $length
     *
     * @return string
     */
    protected function encodeLength(int $length): string
    {
        if ($length <= 0x7F) {
            return chr($length);
        }

        $temp = ltrim(pack('N', $length), chr(0));
        return pack('Ca*', 0x80 | strlen($temp), $temp);
    }
}
