<?php declare(strict_types = 1);

namespace Keboola\OneDriveWriter\MicrosoftGraphApi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Model;

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

    public function writeFile(string $filePathname, string $driveFilePathname) : self
    {
        $fileSize = filesize($filePathname);

        // "You can upload the entire file, or split the file into multiple byte ranges, as long as the maximum bytes in any given request is less than 60 MiB."
        // "If your app splits a file into multiple byte ranges, the size of each byte range MUST be a multiple of 320 KiB"
        // https://docs.microsoft.com/cs-cz/graph/api/driveitem-createuploadsession?view=graph-rest-1.0#upload-bytes-to-the-upload-session
        $uploadFragSize = 320 * 1024 * 10; // 3.2 MiB

        try {
            $uploadSession = $this->api->getApi()
                ->createRequest('POST', '/me/drive/root:/'.$driveFilePathname.':/createUploadSession')
                ->attachBody([
                    "@microsoft.graph.conflictBehavior"=> "replace"
                ])
                ->setReturnType(Model\UploadSession::class)
                ->execute();

            $uploadUrl = $uploadSession->getUploadUrl();

            $file = fopen($filePathname, 'r');
            if($file === false) {
                throw new Exception\FileCannotBeLoaded('Unable to open the file stream');
            }

            while(!feof($file)) {
                $start = ftell($file);
                $data = fread($file, $uploadFragSize);
                $end = ftell($file);

                $this->api->getApi()
                    ->createRequest("PUT", $uploadUrl)
                    ->addHeaders([
                        "Content-Length" => $end - $start,
                        "Content-Range" => sprintf("bytes %d-%d/%d", $start, $end-1, $fileSize),
                    ])
                    ->attachBody($data)
                    ->setReturnType(Model\UploadSession::class)
                    ->setTimeout("1000")
                    ->execute();
            }
        } catch(Exception\AccessTokenNotInitialized $e) {
        } catch(Exception\GenerateAccessTokenFailure $e) {
        } catch(Exception\FileCannotBeLoaded $e) {
        } catch(GraphException $e) {
        }

        return $this;
    }

}
