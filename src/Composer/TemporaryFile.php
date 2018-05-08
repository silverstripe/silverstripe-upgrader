<?php

namespace SilverStripe\Upgrader\Composer;

/**
 * Trait for creating a temporary file that will be deleted once the execution is completed.
 */
trait TemporaryFile
{

    /**
     * Handler to access our temporary file
     * @var resource
     */
    private $handle;

    /**
     * Get a reference to the resource handle that can be use to write to the file.
     * @return resource
     */
    protected function getHandle()
    {
        if (!$this->handle) {
            $this->handle = tmpfile();
        }
        return $this->handle;
    }

    /**
     * Write some content to a temporary file.
     * @param string $content
     * @return void
     */
    protected function writeTmpFile(string $content)
    {
        $handle = $this->getHandle();
        fseek($handle, 0);
        fwrite($handle, $content);
        ftruncate($handle, ftell($handle));
    }

    /**
     * Get the path to the temporary file.
     * @return string
     */
    public function getTmpFilePath(): string
    {
        $handle = $this->getHandle();
        $metaData = stream_get_meta_data($handle);
        return $metaData['uri'];
    }

    /**
     * Explicitly close our temporary file.
     * @return void
     */
    public function close()
    {
        $handle = $this->getHandle();
        fclose($handle);
    }
}
