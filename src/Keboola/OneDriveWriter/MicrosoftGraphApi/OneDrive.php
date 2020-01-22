<?php declare(strict_types = 1);

namespace Keboola\OneDriveWriter\MicrosoftGraphApi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Model\DriveItem;

class OneDrive
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
     * @param string $link
     * @return FileMetadata
     * @throws Exception\AccessTokenNotInitialized
     * @throws Exception\GenerateAccessTokenFailure
     * @throws Exception\InvalidSharingUrl
     * @throws Exception\MissingDownloadUrl
     * @throws Exception\GatewayTimeout
     */
    public function readFileMetadataByLink(string $link) : FileMetadata
    {
        $shares = new Shares($this->api);

        return $shares->getSharesDriveItemMetadata($link);
    }

    /**
     * @param FileMetadata $oneDriveItemMetadata
     * @return File
     * @throws Exception\FileCannotBeLoaded
     */
    public function readFile(FileMetadata $oneDriveItemMetadata) : File
    {
        $client = new Client();

        try {
            $response = $client->get($oneDriveItemMetadata->getDownloadUrl());
        } catch(RequestException $e) {
            $response = $e->getResponse();

            if($response !== null) {
                throw new Exception\FileCannotBeLoaded(
                    sprintf(
                        'File with id "%s" cannot not be downloaded from OneDrive, returned status code %d on download url',
                        $oneDriveItemMetadata->getOneDriveId(),
                        $response->getStatusCode()
                    )
                );
            } else {
                throw new Exception\FileCannotBeLoaded(
                    sprintf(
                        'File with id "%s" cannot not be downloaded from OneDrive, error when performing GET request %s',
                        $oneDriveItemMetadata->getOneDriveId(),
                        $e->getMessage()
                    )
                );
            }
        }

        if($response->getStatusCode() !== 200) {
            throw new Exception\FileCannotBeLoaded(
                sprintf(
                    'File with id "%s" cannot not be downloaded from OneDrive, returned status code %d on download url',
                    $oneDriveItemMetadata->getOneDriveId(),
                    $response->getStatusCode()
                )
            );
        }

        return File::initByStream($response->getBody());
    }

    public function writeFile(string $filePathname, string $driveFilePathname) : self {
        try {
            $this->api->getApi()
                ->createRequest('PUT', '/me/drive/root:/'.$driveFilePathname.':/content')
                ->setReturnType(DriveItem::class)
                ->upload($filePathname);
        } catch(Exception\AccessTokenNotInitialized $e) {
        } catch(Exception\GenerateAccessTokenFailure $e) {
        } catch(GraphException $e) {
        }

        return $this;
    }

}
