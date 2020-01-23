<?php declare(strict_types = 1);

namespace Keboola\OneDriveWriter;

use League\Flysystem;
use Symfony\Component\Finder\Finder;

class Writer
{

    /**
     * @var MicrosoftGraphApi\OAuthProvider
     */
    private $provider;

    /**
     * @var MicrosoftGraphApi\Api
     */
    private $api;

    /**
     * @var Flysystem\Filesystem
     */
    private $filesystem;

    /**
     * Writer constructor.
     *
     * @param string $oAuthAppId
     * @param string $oAuthAppSecret
     * @param string $oAuthData serialized data returned by oAuth API
     * @param Flysystem\Filesystem $filesystem
     * @throws MicrosoftGraphApi\Exception\AccessTokenInvalidData
     * @throws MicrosoftGraphApi\Exception\InitAccessTokenFailure
     */
    public function __construct(
        string $oAuthAppId,
        string $oAuthAppSecret,
        string $oAuthData,
        Flysystem\Filesystem $filesystem
    ) {
        $this->filesystem = $filesystem;

        $this->initOAuthProvider($oAuthAppId, $oAuthAppSecret);
        $this->initOAuthProviderAccessToken($oAuthData);
        $this->initApi();
    }

    /**
     * @param string $oAuthData
     * @return Writer
     * @throws MicrosoftGraphApi\Exception\AccessTokenInvalidData
     * @throws MicrosoftGraphApi\Exception\InitAccessTokenFailure
     */
    private function initOAuthProviderAccessToken(string $oAuthData) : self
    {
        $this->provider->initAccessToken($oAuthData);

        return $this;
    }

    /**
     * @param string $oAuthAppId
     * @param string $oAuthAppSecret
     * @return Writer
     */
    private function initOAuthProvider(string $oAuthAppId, string $oAuthAppSecret) : self
    {
        $redirectUri = '';

        $this->provider = new MicrosoftGraphApi\OAuthProvider($oAuthAppId, $oAuthAppSecret, $redirectUri);

        return $this;
    }

    /**
     * @return Writer
     */
    private function initApi() : self
    {
        $this->api = new MicrosoftGraphApi\Api($this->provider);

        return $this;
    }

    /**
     * @param MicrosoftGraphApi\File $file
     * @param string $filePathname
     * @return Writer
     */
    private function writeFileToOutput(MicrosoftGraphApi\File $file, string $filePathname) : self
    {
        $file->saveToFile($this->filesystem, $filePathname);

        return $this;
    }

    /**
     * Look up all xls(x) and return their path with desired output file.
     *
     * @param string $dirPath
     * @return array
     */
    private function getFilesToProcess(string $dirPath): array {
        $filePaths = [];

        $finder = new Finder();
        $finder->files()->in($dirPath);

        foreach($finder as $file) {
            $filePaths[] = $file->getRelativePathname();
        }

        return $filePaths;
    }

    /**
     * @param string $dirPath
     * @param string $fileRelPathname
     * @param string $driveDir
     * @return Writer
     * @throws Exception\UserException
     * @throws MicrosoftGraphApi\Exception\MissingDownloadUrl
     * @throws \Exception
     */
    public function writeFile(string $dirPath, string $fileRelPathname, string $driveDir) : self
    {
        $files = new MicrosoftGraphApi\OneDrive($this->api);

        $filePathname = sprintf('%s/%s', $dirPath, $fileRelPathname);

        $driveFilePathname = strlen($driveDir) > 0
            ? sprintf("%s/%s", trim($driveDir, '/'), $fileRelPathname)
            : $fileRelPathname;

        try {
            $files->writeFile($filePathname, $driveFilePathname);
        } catch(MicrosoftGraphApi\Exception\GenerateAccessTokenFailure $e) {
            throw new Exception\UserException('Microsoft OAuth API token refresh failed, please reset authorization for the writer configuration');
        } catch(MicrosoftGraphApi\Exception\FileCannotBeLoaded | MicrosoftGraphApi\Exception\InvalidSharingUrl $e) {
            throw new Exception\UserException($e->getMessage());
        } catch(MicrosoftGraphApi\Exception\GatewayTimeout $e) {
            throw new Exception\UserException('Microsoft API timeout, rerun to try again');
        } catch(MicrosoftGraphApi\Exception\AccessTokenNotInitialized $e) {
            throw new \Exception(sprintf("Access token not initialized: %s", $e->getMessage()));
        }

        printf("File \"%s\" written as \"%s\"\n", $fileRelPathname, $driveFilePathname);

        return $this;
    }

    /**
     * @param string $dirPath
     * @param string $driveDir
     * @return Writer
     * @throws Exception\UserException
     * @throws MicrosoftGraphApi\Exception\MissingDownloadUrl
     */
    public function writeDir(string $dirPath, string $driveDir) : self {
        $files = $this->getFilesToProcess($dirPath);

        printf("Found %d files to process\n", count($files));

        foreach($files as $fileRelPathname) {
            $this->writeFile($dirPath, $fileRelPathname, $driveDir);
        }

        return $this;
    }

}
