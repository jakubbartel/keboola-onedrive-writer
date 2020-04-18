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
     * @param string $sharePointWebUrl
     * @return string
     * @throws Exception\AccessTokenNotInitialized
     * @throws Exception\GatewayTimeout
     * @throws Exception\GenerateAccessTokenFailure
     * @throws Exception\InvalidSharePointWebUrl
     * @throws Exception\InvalidSharingUrl
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
     */
    public function writeFile(string $filePathname, string $driveFilePathname, string $siteId=null) : self
    {
        $fileSize = filesize($filePathname);

        // "You can upload the entire file, or split the file into multiple byte ranges, as long as the maximum bytes in any given request is less than 60 MiB."
        // "If your app splits a file into multiple byte ranges, the size of each byte range MUST be a multiple of 320 KiB"
        // https://docs.microsoft.com/cs-cz/graph/api/driveitem-createuploadsession?view=graph-rest-1.0#upload-bytes-to-the-upload-session
        $uploadFragSize = 320 * 1024 * 10; // 3.2 MiB

        if($siteId === null) {
            $url = '/me/drive/root:/'.$driveFilePathname.':/createUploadSession';
        } else {
            $url = '/sites/'.$siteId.'/drive/root:/'.$driveFilePathname.':/createUploadSession';
        }

        try {
            $uploadSession = $this->api->getApi()
                ->createRequest('POST', $url)
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
