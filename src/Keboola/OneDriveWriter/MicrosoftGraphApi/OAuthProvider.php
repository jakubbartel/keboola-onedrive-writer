<?php declare(strict_types = 1);

namespace Keboola\OneDriveWriter\MicrosoftGraphApi;

use Exception as PHPException;
use InvalidArgumentException;
use League\OAuth2;

class OAuthProvider
{

    /**
     * @const string
     */
    private const AUTHORITY_URL = 'https://login.microsoftonline.com/common';

    /**
     * @const string
     */
    private const AUTHORIZE_ENDPOINT = '/oauth2/v2.0/authorize';

    /**
     * @const string
     */
    private const TOKEN_ENDPOINT = '/oauth2/v2.0/token';

    /**
     * @const string access token will also contain a refresh token
     */
    private const SCOPE_OFFLINE_ACCESS = 'offline_access';

    /**
     * @const string
     */
    private const SCOPE_FILES_READ = 'Files.Read';

    /**
     * @const string
     */
    private const SCOPE_SITES_READ_ALL = 'Sites.Read.All';

    /**
     * @var OAuth2\Client\Provider\GenericProvider
     */
    private $provider;

    /**
     * @var OAuth2\Client\Token\AccessTokenInterface
     */
    private $accessToken;

    /**
     * Client constructor.
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param string $redirectUri
     */
    public function __construct(
        string $clientId,
        string $clientSecret,
        string $redirectUri
    ) {
        $this->provider = new OAuth2\Client\Provider\GenericProvider([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri' => $redirectUri,
            'urlAuthorize' => sprintf('%s%s', self::AUTHORITY_URL, self::AUTHORIZE_ENDPOINT),
            'urlAccessToken' => sprintf('%s%s', self::AUTHORITY_URL, self::TOKEN_ENDPOINT),
            'urlResourceOwnerDetails' => '',
            'scopes' => implode(' ', [
                self::SCOPE_OFFLINE_ACCESS,
                self::SCOPE_FILES_READ,
                self::SCOPE_SITES_READ_ALL,
                //'Files.ReadWrite',
                //'Files.ReadWrite.All',
            ]),
        ]);
    }

    /**
     * @return string
     */
    public function getAuthorizationUrl() : string
    {
        return $this->provider->getAuthorizationUrl();
    }

    /**
     * @return string
     */
    public function getState() : string
    {
        return $this->provider->getState();
    }

    /**
     * @param string $accessTokenData
     * @return mixed[]
     * @throws Exception\AccessTokenInvalidData
     */
    private function parseAccessTokenData(string $accessTokenData) : array
    {
        $dataArr = json_decode($accessTokenData, true);

        if($dataArr === null || ! is_array($dataArr)) {
            throw new Exception\AccessTokenInvalidData(
                sprintf(
                    'Data json cannot be unmarshalled, sample: "%s"',
                    is_string($accessTokenData) ? substr($accessTokenData, 0, 16) : gettype($accessTokenData)
                )
            );
        }

        return $dataArr;
    }

    /**
     * @param string $accessTokenData
     * @return OAuthProvider
     * @throws Exception\InitAccessTokenFailure
     * @throws Exception\AccessTokenInvalidData
     */
    public function initAccessToken(string $accessTokenData) : self
    {
        $dataArr = $this->parseAccessTokenData($accessTokenData);

        try {
            $this->accessToken = new OAuth2\Client\Token\AccessToken($dataArr);
        } catch(InvalidArgumentException $e) {
            throw new Exception\InitAccessTokenFailure(sprintf(
                'Cannot init access token by the provided data array: "%s"', $e->getMessage()
            ));
        }

        return $this;
    }

    /**
     * Generate access token using code after OAuth2 redirection to token handler page.
     *
     * @param string $code
     * @return OAuthProvider
     * @throws Exception\GenerateAccessTokenFailure
     */
    public function generateAccessToken(string $code) : self
    {
        try {
            $this->accessToken = $this->provider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);
        } catch(OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            throw new Exception\GenerateAccessTokenFailure(
                sprintf('Cannot generate access token: "%s"', $e->getMessage())
            );
        }

        return $this;
    }

    /**
     * @return OAuthProvider
     * @throws Exception\AccessTokenNotInitialized
     * @throws Exception\GenerateAccessTokenFailure
     */
    public function refreshAccessToken() : self
    {
        if($this->accessToken === null) {
            throw new Exception\AccessTokenNotInitialized();
        }

        try {
            $this->accessToken = $this->provider->getAccessToken('refresh_token', [
                'refresh_token' => $this->accessToken->getRefreshToken(),
            ]);
        } catch(PHPException $e) { // TODO handle correct exceptions
            throw new Exception\GenerateAccessTokenFailure(
                sprintf('Cannot generate access token by refresh token: "%s" (%s)', $e->getMessage(), get_class($e))
            );
        }

        return $this;
    }

    /**
     * @return OAuth2\Client\Token\AccessTokenInterface
     * @throws Exception\AccessTokenNotInitialized
     * @throws Exception\GenerateAccessTokenFailure
     */
    private function getRawAccessToken() : OAuth2\Client\Token\AccessTokenInterface
    {
        // always refresh the access token, because "expires_in" is e.g. 3600 (secs) so expiration
        // is always +1 hour from now
        if(true || $this->accessToken->hasExpired()) {
            $this->refreshAccessToken();
        }

        return $this->accessToken;
    }

    /**
     * @return string
     * @throws Exception\AccessTokenNotInitialized
     * @throws Exception\GenerateAccessTokenFailure
     */
    public function getAccessToken() : string
    {
        return $this->getRawAccessToken()->getToken();
    }

    /**
     * @return string
     */
    public function getAccessTokenData() : string
    {
        return json_encode($this->getRawAccessToken());
    }

    /**
     * @return string
     */
    public function getRefreshToken() : ?string
    {
        return $this->accessToken->getRefreshToken();
    }

    /**
     * @return string
     */
    public function getJWTToken() : string
    {
        return $this->getRawAccessToken()->getValues()['id_token'];
    }

}
