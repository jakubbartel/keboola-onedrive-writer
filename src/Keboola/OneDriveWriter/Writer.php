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
     * @var string
     */
    private $sharePointSiteId;

    /**
     * Writer constructor.
     *
     * @param string $oAuthAppId
     * @param string $oAuthAppSecret
     * @param string $oAuthData serialized data returned by oAuth API
     * @param Flysystem\Filesystem $filesystem
     * @param string|null $sharePointWebUrl
     * @throws Exception\ApplicationException
     * @throws Exception\UserException
     * @throws MicrosoftGraphApi\Exception\AccessTokenInvalidData
     * @throws MicrosoftGraphApi\Exception\AccessTokenNotInitialized
     * @throws MicrosoftGraphApi\Exception\GenerateAccessTokenFailure
     * @throws MicrosoftGraphApi\Exception\InitAccessTokenFailure
     */
    public function __construct(
        string $oAuthAppId,
        string $oAuthAppSecret,
        string $oAuthData,
        Flysystem\Filesystem $filesystem,
        string $sharePointWebUrl = null
    ) {
        $this->filesystem = $filesystem;

        $this->initOAuthProvider($oAuthAppId, $oAuthAppSecret);
        $this->initOAuthProviderAccessToken($oAuthData);
        $this->initApi();

        if($sharePointWebUrl !== null) {
            $this->initSharePointId($sharePointWebUrl);
        }
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
     * @param string $sharePointWebUrl
     * @return Writer
     * @throws MicrosoftGraphApi\Exception\AccessTokenNotInitialized
     * @throws MicrosoftGraphApi\Exception\GenerateAccessTokenFailure
     * @throws Exception\UserException
     * @throws Exception\ApplicationException
     */
    private function initSharePointId(string $sharePointWebUrl) : self
    {
        $oneDrive = new MicrosoftGraphApi\OneDrive($this->api);

        try {
            $this->sharePointSiteId = $oneDrive->readSiteIdByWebUrl($sharePointWebUrl);
        } catch(MicrosoftGraphApi\Exception\InvalidSharePointWebUrl $e) {
            throw new Exception\UserException(sprintf('SharePoint Site url "%s" is invalid', $sharePointWebUrl));
        } catch(MicrosoftGraphApi\Exception\ClientException $e) {
            throw new Exception\UserException(
                sprintf('Given url "%s" cannot be loaded as SharePoint site: %s', $sharePointWebUrl, $e->getMessage())
            );
        } catch(MicrosoftGraphApi\Exception\ServerException $e) {
            throw new Exception\ApplicationException(
                sprintf('Given url "%s" cannot be loaded as SharePoint site: %s', $sharePointWebUrl, $e->getMessage())
            );
        } catch(MicrosoftGraphApi\Exception\MissingSiteId $e) {
            throw new Exception\ApplicationException(
                sprintf('SharePoint Site "%s" response is missing site id parameter', $sharePointWebUrl)
            );
        }

        return $this;
    }

    /**
     * @param string $dirPath
     * @return array
     */
    private function getFilesToProcess(string $dirPath): array
    {
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
     * @throws Exception\ApplicationException
     * @throws Exception\UserException
     * @throws MicrosoftGraphApi\Exception\AccessTokenNotInitialized
     * @throws MicrosoftGraphApi\Exception\GenerateAccessTokenFailure
     */
    public function writeFile(string $dirPath, string $fileRelPathname, string $driveDir) : self
    {
        $files = new MicrosoftGraphApi\OneDrive($this->api);

        $filePathname = sprintf('%s/%s', $dirPath, $fileRelPathname);

        $driveFilePathname = strlen($driveDir) > 0
            ? sprintf("%s/%s", trim($driveDir, '/'), $fileRelPathname)
            : $fileRelPathname;

        try {
            $files->writeFile($filePathname, $driveFilePathname, $this->sharePointSiteId);
        } catch(MicrosoftGraphApi\Exception\ClientException $e) {
            throw new Exception\UserException(
                sprintf('File "%s" upload error: %s', $filePathname, $e->getMessage())
            );
        } catch(MicrosoftGraphApi\Exception\ServerException $e) {
            throw new Exception\ApplicationException(
                sprintf('File "%s" upload error: %s', $filePathname, $e->getMessage())
            );
        } catch(MicrosoftGraphApi\Exception\ReadFile $e) {
            throw new Exception\ApplicationException(sprintf('Write file: %s', $e->getMessage()));
        }

        printf("File \"%s\" written as \"%s\"\n", $fileRelPathname, $driveFilePathname);

        return $this;
    }

    /**
     * @param string $dirPath
     * @param string $driveDir
     * @return Writer
     * @throws Exception\ApplicationException
     * @throws Exception\UserException
     * @throws MicrosoftGraphApi\Exception\AccessTokenNotInitialized
     * @throws MicrosoftGraphApi\Exception\GenerateAccessTokenFailure
     */
    public function writeDir(string $dirPath, string $driveDir) : self
    {
        $files = $this->getFilesToProcess($dirPath);

        printf("Found %d files to process\n", count($files));

        foreach($files as $fileRelPathname) {
            $this->writeFile($dirPath, $fileRelPathname, $driveDir);
        }

        return $this;
    }

}
