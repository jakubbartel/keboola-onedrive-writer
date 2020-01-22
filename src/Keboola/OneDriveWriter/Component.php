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
     * @return Writer
     * @throws MicrosoftGraphApi\Exception\AccessTokenInvalidData
     * @throws MicrosoftGraphApi\Exception\InitAccessTokenFailure
     */
    public function initWriter(): Writer
    {
        $adapter = new FlySystem\Adapter\Local(sprintf('%s%s', $this->getDataDir(), '/in/files'));
        $fileSystem = new Flysystem\Filesystem($adapter);

        return new Writer(
            $this->getConfig()->getOAuthApiAppKey(),
            $this->getConfig()->getOAuthApiAppSecret(),
            $this->getConfig()->getOAuthApiData(),
            $fileSystem
        );
    }

    /**
     * @throws MicrosoftGraphApi\Exception\MissingDownloadUrl intentionally treat this as application
     *         exception so this error is alerted to developer
     * @throws Exception\UserException
     */
    public function run() : void
    {
        $writer = $this->initWriter();

        $fileParameters = $this->getConfig()->getParameters();

        $writer->writeDir(self::getDataDir() . '/in/files', isset($fileParameters['path']) ? $fileParameters['path'] : '');
    }

}
