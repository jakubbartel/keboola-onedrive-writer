<?php declare(strict_types = 1);

namespace Keboola\OneDriveWriter\MicrosoftGraphApi;

use Keboola\OneDriveWriter\MicrosoftGraphApi\Exception\MissingDownloadUrl;
use Microsoft\Graph\Model;

class FileMetadata
{

    /**
     * @var string
     */
    private $oneDriveId;

    /**
     * @var string
     */
    private $oneDriveName;

    /**
     * @var string
     */
    private $downloadUrl;

    /**
     * FileMetadata constructor.
     *
     * @param string $oneDriveId
     * @param string $oneDriveName
     * @param string $downloadUrl
     */
    private function __construct($oneDriveId, $oneDriveName, $downloadUrl)
    {
        $this->oneDriveId = $oneDriveId;
        $this->oneDriveName = $oneDriveName;
        $this->downloadUrl = $downloadUrl;
    }

    /**
     * @param Model\DriveItem $oneDriveItem
     * @return FileMetadata
     * @throws MissingDownloadUrl
     */
    public static function initByOneDriveModel(Model\DriveItem $oneDriveItem): self
    {
        $properties = $oneDriveItem->getProperties();

        if( ! isset($properties['@microsoft.graph.downloadUrl'])) {
            throw new MissingDownloadUrl();
        }

        return new FileMetadata(
            $oneDriveItem->getId(),
            $oneDriveItem->getName(),
            $properties['@microsoft.graph.downloadUrl']
        );
    }

    /**
     * @return string
     */
    public function getOneDriveId() : string
    {
        return $this->oneDriveId;
    }

    /**
     * @return string
     */
    public function getOneDriveName() : string
    {
        return $this->oneDriveName;
    }

    /**
     * @return string
     */
    public function getDownloadUrl() : string
    {
        return $this->downloadUrl;
    }

}
