<?php

namespace Bolt;

use Bolt\Filesystem\AggregateFilesystemInterface;
use Bolt\Filesystem\Exception\IOException;
use Bolt\Filesystem\FilesystemInterface;
use Bolt\Filesystem\Handler\HandlerInterface;
use Doctrine\Common\Cache\FilesystemCache;
use Silex;

/**
 * Simple, file based cache for volatile data.. Useful for storing non-vital
 * information like feeds, and other stuff that can be recovered easily.
 *
 * @author Bob den Otter, bob@twokings.nl
 */
class Cache extends FilesystemCache
{
    /** Max cache age. Default 10 minutes. */
    const DEFAULT_MAX_AGE = 600;
    /** Default cache file extension. */
    const EXTENSION = '.data';

    /** @var AggregateFilesystemInterface */
    private $filesystem;

    /**
     * Cache constructor.
     *
     * @param string                       $directory
     * @param string                       $extension
     * @param int                          $umask
     * @param AggregateFilesystemInterface $filesystem
     */
    public function __construct($directory, $extension = self::EXTENSION, $umask = 0002, AggregateFilesystemInterface $filesystem = null)
    {
        parent::__construct($directory, $extension, $umask);
        $this->filesystem = $filesystem;
    }

    /**
     * @deprecated Deprecated since 3.0, to be removed in 4.0. Use doFlush() instead.
     */
    public function clearCache()
    {
        return $this->doFlush();
    }

    /**
     * Clear the cache. Both the doctrine FilesystemCache, as well as twig and thumbnail temp files.
     *
     * @return array
     */
    public function doFlush()
    {
        $result = [
            'successfiles'   => 0,
            'failedfiles'    => 0,
            'failed'         => [],
            'successfolders' => 0,
            'failedfolders'  => 0,
            'log'            => '',
        ];

        // Clear Doctrine's folder.
        parent::doFlush();

        if ($this->filesystem instanceof AggregateFilesystemInterface) {
            // Clear our own cache folder.
            $this->flushFilesystemCache($this->filesystem->getFilesystem('cache'), $result);

            // Clear the thumbs folder.
            $this->flushFilesystemCache($this->filesystem->getFilesystem('thumbs'), $result);
        }

        return $result;
    }

    /**
     * Helper function for doFlush().
     *
     * @param FilesystemInterface $filesystem
     * @param array               $result
     */
    private function flushFilesystemCache(FilesystemInterface $filesystem, &$result)
    {
        $files = $filesystem->find()
            ->files()
            ->notName('index.html')
            ->ignoreDotFiles()
            ->ignoreVCS()
        ;

        /** @var HandlerInterface $file */
        foreach ($files as $file) {
            try {
                $file->delete();
                $result['successfiles']++;
            } catch (IOException $e) {
                $result['failedfiles']++;
                $result['failed'][] = $file->getPath();
            }
        }

        $dirs = $filesystem->find()
            ->directories()
            ->depth('< 1')
        ;

        /** @var HandlerInterface $dir */
        foreach ($dirs as $dir) {
            try {
                $dir->delete();
                $result['successfolders']++;
            } catch (IOException $e) {
                $result['failedfolders']++;
            }
        }
    }
}
