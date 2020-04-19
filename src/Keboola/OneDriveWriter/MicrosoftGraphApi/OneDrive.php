<?php declare(strict_types=1);

namespace Keboola\OneDriveWriter\MicrosoftGraphApi;

use GuzzleHttp;
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
     * @param string $sharePointWebUrl
     * @return string
     * @throws Exception\InvalidSharePointWebUrl
     * @throws Exception\AccessTokenNotInitialized
     * @throws Exception\GenerateAccessTokenFailure
     * @throws Exception\ClientException
     * @throws Exception\ServerException
     * @throws Exception\MissingSiteId
     */
    public function readSiteIdByWebUrl(string $sharePointWebUrl) : string
    {
        $sites = new Sites($this->api);

        return $sites->getSiteIdBySharePointWebUrl($sharePointWebUrl);
    }

    /**
     * @param string $filePathname
     * @param string $driveFilePathname
     * @param string|null $siteId
     * @return OneDrive
     * @throws Exception\AccessTokenNotInitialized
     * @throws Exception\GenerateAccessTokenFailure
     * @throws Exception\ReadFile
     * @throws Exception\ClientException
     * @throws Exception\ServerException
     */
    public function writeFile(string $filePathname, string $driveFilePathname, string $siteId = null) : self
    {
        if($siteId === null) {
            $url = '/me/drive/root:/'.$driveFilePathname.':/createUploadSession';
        } else {
            $url = '/sites/'.$siteId.'/drive/root:/'.$driveFilePathname.':/createUploadSession';
        }

        try {
            /** @var Model\UploadSession $uploadSession */
            $uploadSession = $this->api->getApi()
                ->createRequest('POST', $url)
                ->attachBody([
                    "@microsoft.graph.conflictBehavior" => "replace",
                ])
                ->setReturnType(Model\UploadSession::class)
                ->setTimeout("30000")
                ->execute();
        } catch(GuzzleHttp\Exception\ClientException $e) {
            throw new Exception\ClientException(
                sprintf("Create upload session request error: %s", Utils::parseGraphApiErrorMessage($e)), 0, $e);
        } catch(GuzzleHttp\Exception\ServerException | GraphException $e) {
            throw new Exception\ServerException(
                sprintf("Create upload session request error: %s", Utils::parseGraphApiErrorMessage($e)), 0, $e);
        }

        $fileSize = filesize($filePathname);

        // "You can upload the entire file, or split the file into multiple byte ranges, as long as the maximum bytes in any given request is less than 60 MiB."
        // "If your app splits a file into multiple byte ranges, the size of each byte range MUST be a multiple of 320 KiB"
        // https://docs.microsoft.com/cs-cz/graph/api/driveitem-createuploadsession?view=graph-rest-1.0#upload-bytes-to-the-upload-session
        $uploadFragSize = 320 * 1024 * 10; // 3.2 MiB

        $uploadUrl = $uploadSession->getUploadUrl();

        $file = fopen($filePathname, 'r');
        if($file === false) {
            throw new Exception\ReadFile(sprintf('Unable to open "%s" file', $filePathname));
        }

        while( ! feof($file)) {
            $start = ftell($file);
            $data = fread($file, $uploadFragSize);
            $end = ftell($file);

            try {
                $this->api->getApi()
                    ->createRequest("PUT", $uploadUrl)
                    ->addHeaders([
                        "Content-Length" => $end - $start,
                        "Content-Range" => sprintf("bytes %d-%d/%d", $start, $end - 1, $fileSize),
                    ])
                    ->attachBody($data)
                    ->setReturnType(Model\UploadSession::class)
                    ->setTimeout("15000")
                    ->execute();
            } catch(GuzzleHttp\Exception\ClientException $e) {
                throw new Exception\ClientException(
                    sprintf("Upload data request error: %s", Utils::parseGraphApiErrorMessage($e)), 0, $e);
            } catch(GuzzleHttp\Exception\ServerException | GraphException $e) {
                throw new Exception\ServerException(
                    sprintf("Upload data request error: %s", Utils::parseGraphApiErrorMessage($e)), 0, $e);
            }
        }

        fclose($file);

        return $this;
    }

}
