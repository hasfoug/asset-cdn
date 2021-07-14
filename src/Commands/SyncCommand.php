<?php

namespace Arubacao\AssetCdn\Commands;

use Arubacao\AssetCdn\Finder;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\File;
use Symfony\Component\Finder\SplFileInfo;

class SyncCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'asset-cdn:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronizes assets to CDN';

    /**
     * @var string
     */
    private $filesystem;

    /**
     * @var FilesystemManager
     */
    private $filesystemManager;

    /**
     * @var string
     */
    private $storageFolder;

    /**
     * Execute the console command.
     *
     * @param Finder $finder
     * @param FilesystemManager $filesystemManager
     * @param Repository $config
     *
     * @return void
     */
    public function handle(Finder $finder, FilesystemManager $filesystemManager, Repository $config)
    {
        $this->filesystem = $config->get('asset-cdn.filesystem.disk');
        $this->storageFolder = ltrim($config->get('asset-cdn.filesystem.storage_folder', ''), '/');
        $this->filesystemManager = $filesystemManager;
        $filesOnCdn = $this->filesystemManager
            ->disk($this->filesystem)
            ->allFiles();
        $localFiles = $finder->getFiles();
        $filesToDelete = $this->filesToDelete($filesOnCdn, $localFiles);
        $filesToSync = $this->filesToSync($filesOnCdn, $localFiles);

        foreach ($filesToSync as $file) {
            $bool = $this->filesystemManager
                ->disk($this->filesystem)
                ->putFileAs(
                    "{$this->storageFolder}/{$file->getRelativePath()}",
                    new File($file->getPathname()),
                    $file->getFilename(),
                    $config->get('asset-cdn.filesystem.options')
                );

            if (! $bool) {
                $this->error("Problem uploading: {$file->getRelativePathname()}");
            } else {
                $this->info("Successfully uploaded: {$file->getRelativePathname()}");
            }
        }

        if ($this->filesystemManager
            ->disk($this->filesystem)
            ->delete($filesToDelete)) {
            foreach ($filesToDelete as $file) {
                $this->info("Successfully deleted: {$file}");
            }
        }
    }

    /**
     * @param string[] $filesOnCdn
     * @param SplFileInfo[] $localFiles
     * @return SplFileInfo[]
     */
    private function filesToSync(array $filesOnCdn, array $localFiles): array
    {
        $array = array_filter($localFiles, function (SplFileInfo $localFile) use ($filesOnCdn) {
            $localFilePathname = $localFile->getRelativePathname();
            if (! in_array($localFilePathname, $filesOnCdn)) {
                return true;
            }

            $filesizeOfCdn = $this->filesystemManager
                ->disk($this->filesystem)
                ->size("{$this->storageFolder}/$localFilePathname");

            if ($filesizeOfCdn != $localFile->getSize()) {
                return true;
            }

            $md5OfCdn = md5(
                $this->filesystemManager
                    ->disk($this->filesystem)
                    ->get("{$this->storageFolder}/$localFilePathname")
            );

            $md5OfLocal = md5_file($localFile->getRealPath());

            if ($md5OfLocal != $md5OfCdn) {
                return true;
            }

            return false;
        });

        return array_values($array);
    }

    /**
     * @param string[] $filesOnCdn
     * @param SplFileInfo[] $localFiles
     * @return string[]
     */
    private function filesToDelete(array $filesOnCdn, array $localFiles): array
    {
        $localFiles = $this->mapToPathname($localFiles, $this->storageFolder);

        $array = array_filter($filesOnCdn, function (string $fileOnCdn) use ($localFiles) {
            return ! in_array($fileOnCdn, $localFiles);
        });

        return array_values($array);
    }
}
