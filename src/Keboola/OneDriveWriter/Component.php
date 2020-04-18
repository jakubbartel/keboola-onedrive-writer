<?php

namespace Keboola\OneDriveWriter;

use League\Flysystem;
use Keboola\Component\BaseComponent;

class Component extends BaseComponent
{

    /**
     * @return string
     */
    protected function getConfigDefinitionClass(): string
    {
        return ConfigDefinition::class;
    }

    /**
     * @param string $sharePointWebUrl
     * @return Writer
     * @throws MicrosoftGraphApi\Exception\AccessTokenInvalidData
     * @throws MicrosoftGraphApi\Exception\AccessTokenNotInitialized
     * @throws MicrosoftGraphApi\Exception\GatewayTimeout
     * @throws MicrosoftGraphApi\Exception\GenerateAccessTokenFailure
     * @throws MicrosoftGraphApi\Exception\InitAccessTokenFailure
     * @throws MicrosoftGraphApi\Exception\InvalidSharePointWebUrl
     * @throws MicrosoftGraphApi\Exception\InvalidSharingUrl
     * @throws MicrosoftGraphApi\Exception\MissingSiteId
     */
    public function initWriter(string $sharePointWebUrl): Writer
    {
        $adapter = new FlySystem\Adapter\Local(sprintf('%s%s', $this->getDataDir(), '/in/files'));
        $fileSystem = new Flysystem\Filesystem($adapter);

        return new Writer(
            $this->getConfig()->getOAuthApiAppKey(),
            $this->getConfig()->getOAuthApiAppSecret(),
            $this->getConfig()->getOAuthApiData(),
            $fileSystem,
            $sharePointWebUrl ? $sharePointWebUrl : null
        );
    }

    /**
     * @throws MicrosoftGraphApi\Exception\MissingDownloadUrl intentionally treat this as application
     *         exception so this error is alerted to developer
     * @throws Exception\UserException
     * @throws Exception\ApplicationException
     */
    public function run() : void
    {
        $fileParameters = $this->getConfig()->getParameters();

        $sharePointWebUrl = isset($fileParameters['sharePointWebUrl']) ? $fileParameters['sharePointWebUrl'] : '';

        $writer = $this->initWriter($sharePointWebUrl);

        $writer->writeDir(self::getDataDir() . '/in/files', isset($fileParameters['path']) ? $fileParameters['path'] : '');
    }

}
