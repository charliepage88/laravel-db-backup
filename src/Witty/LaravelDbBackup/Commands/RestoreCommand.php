<?php

namespace Witty\LaravelDbBackup\Commands;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Finder\Finder;
use Witty\LaravelDbBackup\Commands\Helpers\Encrypt;
use Witty\LaravelDbBackup\Models\Dump;

/**
 * Class RestoreCommand
 * @package Witty\LaravelDbBackup\Commands
 */
class RestoreCommand extends BaseCommand
{

    /**
     * @var string
     */
    protected $name = 'db:restore';
    protected $description = 'Restore a database backup to current SQL connection.';
    protected $database;

    /**
     * @return void
     */
    public function handle()
    {
        return $this->fire();
    }

    /**
     * @return void
     */
    public function fire()
    {
        $this->database = $this->getDatabase($this->input->getOption('database'));
        if ($this->option('aws-dump')) {

            return $this->restoreDumpFromAws($this->option('aws-dump'));

        }
        if ($this->option('aws-last-dump')) {

            return $this->restoreLastAwsDump();

        }
        $fileName = $this->argument('filename');

        if ($this->option('last-dump')) {
            $fileName = $this->lastBackupFile();

            if (!$fileName) {
                return $this->line(
                    $this->colors->getColoredString("\n" . 'No backups have been created.' . "\n", 'red')
                );
            }
        }

        if ($fileName) {
            return $this->restoreDump($fileName);
        }

        $this->listAllDumps();
    }

    /**
     * @throws \League\Flysystem\FileNotFoundException
     */
    private function restoreLastAwsDump()
    {

        $lastDumpName = Dump::latest()->first();

        if ($lastDumpName instanceof Dump) {
            return $this->restoreDumpFromAws($lastDumpName->file_name);
        }
        return $this->line(
            $this->colors->getColoredString("\n" . 'No query results in your DB. Try option --aws-dump' . "\n", 'red')
        );
    }

    /**
     * @param $fileName
     * @throws \League\Flysystem\FileNotFoundException
     */
    private function restoreDumpFromAws($fileName)
    {

        $content = $this->getDumpFromAws($fileName);
        if (!$content) {
            return $this->line(
                $this->colors->getColoredString("\n" . 'File not found.' . "\n", 'red')
            );
        }

        if (is_file($this->getDumpsPath() . $fileName)) {
            unlink($this->getDumpsPath() . $fileName);
        }

        file_put_contents($this->getDumpsPath() . $fileName, $content);

        if (is_file($this->getDumpsPath() . $fileName)) {
            return $this->restoreDump($fileName);
        }

        return $this->line(
            $this->colors->getColoredString("\n" . 'Filed to save file from aws.' . "\n", 'red')
        );
    }

    /**
     * @param $dump
     * @return bool|false|string
     * @throws \League\Flysystem\FileNotFoundException
     */
    private function getDumpFromAws($filename)
    {
        $s3 = new S3Client([
            'region'  => Config::get('db-backup.s3.region'),
            'version' => 'latest',
            'credentials' => [
                'key'    => Config::get('db-backup.s3.accessKey'),
                'secret' => Config::get('db-backup.s3.secretKey')
            ]
        ]);

        try {
            return $s3->getObject([
                'Bucket' => Config::get('db-backup.s3.bucket'),
                'Key' => $this->getS3Path() . '/' . $filename
            ]);
        } catch (Aws\S3\Exception\S3Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * @param string $fileName
     * @return void
     */
    protected function restoreDump($fileName)
    {
        $sourceFile = $this->getDumpsPath() . $fileName;

        if ($this->isCompressed($sourceFile)) {
            $sourceFile = $this->uncompress($sourceFile);
        }

        $status = $this->database->restore($this->getUncompressedFileName($sourceFile));

        if ($this->isCompressed($sourceFile)) {
            $this->uncompressCleanup($this->getUncompressedFileName($sourceFile));
        }

        if ($status === true) {
            return $this->line(
                sprintf($this->colors->getColoredString("\n" . '%s was successfully restored.' . "\n", 'green'), $fileName)
            );
        }

        Encrypt::decryptFile($sourceFile);

        $status = $this->database->restore($this->getUncompressedFileName($sourceFile));
        if ($status === true) {
            return $this->line(
                sprintf($this->colors->getColoredString("\n" . '%s was successfully restored.' . "\n", 'green'), $fileName)
            );
        }

        $this->line(
            $this->colors->getColoredString("\n" . 'Database restore failed.' . "\n", 'red')
        );
    }

    /**
     * @return void
     */
    protected function listAllDumps()
    {
        $finder = new Finder();
        $finder->files()->in($this->getDumpsPath());

        if ($finder->count() === 0) {
            return $this->line(
                $this->colors->getColoredString("\n" . 'You haven\'t saved any dumps.' . "\n", 'brown')
            );
        }

        $this->line($this->colors->getColoredString("\n" . 'Please select one of the following dumps:' . "\n", 'white'));

        $finder->sortByName();
        $count = count($finder);

        $i = 0;
        foreach ($finder as $dump) {
            $i++;
            $fileName = $dump->getFilename();
            if ($i === ($count - 1)) $fileName .= "\n";

            $this->line($this->colors->getColoredString($fileName, 'brown'));
        }
    }

    /**
     * Uncompress a GZip compressed file
     *
     * @param string $fileName Relative or absolute path to file
     * @return string               Name of uncompressed file (without .gz extension)
     */
    protected function uncompress($fileName)
    {
        $fileNameUncompressed = $this->getUncompressedFileName($fileName);
        $command = sprintf('gzip -dc %s > %s', $fileName, $fileNameUncompressed);
        if ($this->console->run($command) !== true) {
            $this->line($this->colors->getColoredString("\n" . 'Uncompress of gzipped file failed.' . "\n", 'red'));
        }

        return $fileNameUncompressed;
    }

    /**
     * Remove uncompressed files
     *
     * Files are temporarily uncompressed for usage in restore. We do not need these copies
     * permanently.
     *
     * @param string $fileName Relative or absolute path to file
     * @return boolean              Success or failure of cleanup
     */
    protected function cleanup($fileName)
    {
        $status = true;
        $fileNameUncompressed = $this->getUncompressedFileName($fileName);
        if ($fileName !== $fileNameUncompressed) {
            $status = File::delete($fileName);
        }

        return $status;
    }

    /**
     * Retrieve filename without Gzip extension
     *
     * @param string $fileName Relative or absolute path to file
     * @return string               Filename without .gz extension
     */
    protected function getUncompressedFileName($fileName)
    {
        return preg_replace('"\.gz$"', '', $fileName);
    }

    /**
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['filename', InputArgument::OPTIONAL, 'Filename of the dump']
        ];
    }

    /**
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['database', null, InputOption::VALUE_OPTIONAL, 'The database connection to restore to'],
            ['last-dump', true, InputOption::VALUE_NONE, 'The last dump stored'],
            ['aws-last-dump', true, InputOption::VALUE_NONE, 'The last dump from aws'],
            ['aws-dump', null, InputOption::VALUE_OPTIONAL, 'The dump from aws. Enter file name'],
        ];
    }

    /**
     * @return string
     */
    private function lastBackupFile()
    {
        $finder = new Finder();
        $finder->files()->in($this->getDumpsPath());

        $lastFileName = '';

        foreach ($finder as $dump) {
            $filename = $dump->getFilename();
            $filenameWithoutExtension = $this->filenameWithoutExtension($filename);
            if ((int)$filenameWithoutExtension > (int)$this->filenameWithoutExtension($lastFileName)) {
                $lastFileName = $filename;
            }
        }

        return $lastFileName;
    }

    /**
     * @param string $filename
     * @return string
     */
    private function filenameWithoutExtension($filename)
    {
        return preg_replace('/\\.[^.\\s]{3,4}$/', '', $filename);
    }
}
