<?php declare(strict_types = 1);

namespace Keboola\OneDriveWriter\MicrosoftGraphApi;

use GuzzleHttp;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Model;

class Sites
{

    /**
     * @var Api
     */
    private $api;

    /**
     * Files constructor.
     *
     * @param Api $api
     */
    public function __construct(Api $api)
    {
        $this->api = $api;
    }

    /**
     * @param string $sharePointWebUrl
     * @return string
     * @throws Exception\AccessTokenNotInitialized
     * @throws Exception\GenerateAccessTokenFailure
     * @throws Exception\InvalidSharingUrl
     * @throws Exception\GatewayTimeout
     * @throws Exception\MissingSiteId
     * @throws Exception\InvalidSharePointWebUrl
     */
    public function getSiteIdBySharePointWebUrl(string $sharePointWebUrl) : string
    {
        $components = self::parseSharePointWebUrlComponents($sharePointWebUrl);

        if($components['host'] === '' || $components['path'] === '') {
            throw new Exception\InvalidSharePointWebUrl();
        }

        try {
            /** @var Model\DriveItem $sharedDriveItem */
            $sharePointSite = $this->api->getApi()
                ->createRequest('GET', sprintf('/sites/%s:%s', $components['host'], $components['path']))
                ->setReturnType(Model\Site::class)
                ->execute();
        } catch(GraphException | GuzzleHttp\Exception\ClientException $e) {
            throw new Exception\InvalidSharingUrl(
                sprintf('Given url "%s" cannot be loaded as SharePoint site', $sharePointWebUrl)
            );
        } catch(GuzzleHttp\Exception\ServerException $e) {
            if(strpos($e->getMessage(), '504 Gateway Timeout') !== false) {
                throw new Exception\GatewayTimeout();
            } else {
                throw $e;
            }
        }

        return Sites::parseSiteId($sharePointSite);
    }

    /**
     * @param Model\Site $site
     * @return string
     * @throws Exception\MissingSiteId
     */
    private static function parseSiteId(Model\Site $site) : string
    {
        $properties = $site->getProperties();

        if( ! isset($properties['id'])) {
            throw new Exception\MissingSiteId();
        }

        return $properties['id'];
    }

    /**
     * @param string $sharePointWebUrl
     * @return array
     */
    private static function parseSharePointWebUrlComponents(string $sharePointWebUrl) : array {
        if(strpos($sharePointWebUrl, 'https://') === 0) {
            // ok
        } else if(strpos($sharePointWebUrl, 'http://') === 0) {
            $sharePointWebUrl = substr_replace($sharePointWebUrl, 'https://', 0, strlen('http://'));
        } else {
            $sharePointWebUrl = 'https://'.$sharePointWebUrl;
        }

        return parse_url($sharePointWebUrl);
    }

}
