<?php declare(strict_types = 1);

namespace Keboola\OneDriveWriter\MicrosoftGraphApi;

//use GuzzleHttp\Psr7\StreamWrapper;
use League\Flysystem\Filesystem;
use Psr\Http\Message\StreamInterface;

class File
{

    /**
     * @var StreamInterface
     */
    private $stream;

    /**
     * File constructor can be called only by init* methods.
     */
    private function __construct() {}

    /**
     * @param StreamInterface $stream
     * @return File
     */
    public static function initByStream(StreamInterface $stream) : File {
        $file = new File();

        $file->stream = $stream;

        return $file;
    }

    /**
     * @return string
     */
    public function getContents() : string
    {
        if($this->stream->isSeekable()) {
            $this->stream->rewind();
        }

        return $this->stream->getContents();
    }

    /**
     * @param Filesystem $fileSystem
     * @param string $filePathname
     * @return File
     */
    public function saveToFile(Filesystem $fileSystem, string $filePathname) : self
    {
        // stream version is preferred but not functional
        //$resource = StreamWrapper::getResource($this->stream);
        //$fileSystem->putStream($path, $resource);

        $fileSystem->put($filePathname, $this->getContents());

        return $this;
    }

}
