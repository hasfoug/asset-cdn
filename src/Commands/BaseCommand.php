<?php
/**
 * Created by PhpStorm.
 * User: Christopher
 * Date: 01.03.2018
 * Time: 18:51.
 */

namespace Arubacao\AssetCdn\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\SplFileInfo;

abstract class BaseCommand extends Command
{
    /**
     * @param \Symfony\Component\Finder\SplFileInfo[] $files
     * @param string $storageFolder
     * @return array
     */
    protected function mapToPathname(array $files, string $storageFolder): array
    {
        return array_map(function (SplFileInfo $file) use ($storageFolder) {
            return "$storageFolder/{$file->getRelativePathname()}";
        }, $files);
    }
}
