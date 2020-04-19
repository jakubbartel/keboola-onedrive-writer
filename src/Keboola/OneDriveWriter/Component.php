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
     * @throws Exception\ApplicationException
     * @throws Exception\UserException
     * @throws MicrosoftGraphApi\Exception\AccessTokenInvalidData
     * @throws MicrosoftGraphApi\Exception\AccessTokenNotInitialized
     * @throws MicrosoftGraphApi\Exception\GenerateAccessTokenFailure
     * @throws MicrosoftGraphApi\Exception\InitAccessTokenFailure
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
     * @throws Exception\ApplicationException
     * @throws Exception\UserException
     * @throws MicrosoftGraphApi\Exception\AccessTokenInvalidData application exception
     * @throws MicrosoftGraphApi\Exception\AccessTokenNotInitialized application exception
     * @throws MicrosoftGraphApi\Exception\InitAccessTokenFailure application exception
     */
    public function run() : void
    {
        $fileParameters = $this->getConfig()->getParameters();

        $sharePointWebUrl = isset($fileParameters['sharePointWebUrl']) ? $fileParameters['sharePointWebUrl'] : '';

        try {
            $this->initWriter($sharePointWebUrl)->writeDir(
                self::getDataDir() . '/in/files',
                isset($fileParameters['path']) ? $fileParameters['path'] : ''
            );
        } catch(MicrosoftGraphApi\Exception\GenerateAccessTokenFailure $e) {
            throw new Exception\UserException(
                'Microsoft OAuth API token refresh failed, please reset Authorization in the writer\'s configuration');
        }
    }

}
