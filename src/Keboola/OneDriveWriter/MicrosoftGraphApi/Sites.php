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
     * @throws Exception\InvalidSharePointWebUrl
     * @throws Exception\AccessTokenNotInitialized
     * @throws Exception\GenerateAccessTokenFailure
     * @throws Exception\ClientException
     * @throws Exception\ServerException
     * @throws Exception\MissingSiteId
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
                ->setTimeout("15000")
                ->execute();
        } catch(GuzzleHttp\Exception\ClientException $e) {
            throw new Exception\ClientException("Get SharePoint Site request", 0, $e);
        } catch(GuzzleHttp\Exception\ServerException | GraphException $e) {
            throw new Exception\ServerException("Get SharePoint Site request", 0, $e);
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
    private static function parseSharePointWebUrlComponents(string $sharePointWebUrl) : array
    {
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
