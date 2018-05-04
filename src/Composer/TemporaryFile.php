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

    protected function getHandle()
    {
        if (!$this->handle) {
            $this->handle = tmpfile();
        }
        return $this->handle;
    }

    protected function writeTmpFile($content)
    {
        $handle = $this->getHandle();
        fseek($handle, 0);
        fwrite($handle, $content);
        ftruncate($handle, ftell($handle));
    }

    public function getTmpFilePath()
    {
        $handle = $this->getHandle();
        $metaDatas = stream_get_meta_data($handle);
        return $metaDatas['uri'];
    }

    public function close()
    {
        $handle = $this->getHandle();
        fclose($handle);
    }
}
