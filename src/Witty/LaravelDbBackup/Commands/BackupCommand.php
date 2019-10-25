<?php

namespace Witty\LaravelDbBackup\Commands;

use Aws\S3\S3Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Witty\LaravelDbBackup\Commands\Helpers\BackupFile;
use Witty\LaravelDbBackup\Commands\Helpers\BackupHandler;
use Witty\LaravelDbBackup\Commands\Helpers\Encrypt;
use Witty\LaravelDbBackup\Models\Dump;


/**
 * Class BackupCommand
 * @package Witty\LaravelDbBackup\Commands
 */
class BackupCommand extends BaseCommand
{
    /**
     * @var string
     */
    protected $name = 'db:backup';
    protected $description = 'Backup the default database to `storage/dumps`';
    protected $filePath;
    protected $fileName;

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
        $database = $this->getDatabase($this->input->getOption('database'));

        $this->checkDumpFolder();

        //----------------
        $dbfile = new BackupFile($this->argument('filename'), $database, $this->getDumpsPath());
        $this->filePath = $dbfile->path();
        $this->fileName = $dbfile->name();

        $status = $database->dump($this->filePath);
        $handler = new BackupHandler($this->colors);

        // Error
        //----------------
        if ($status !== true) {
            return $this->line($handler->errorResponse($status));
        }

        // Compression
        //----------------
        if ($this->isCompressionEnabled()) {
            $this->compress();
            $this->fileName .= ".gz";
            $this->filePath .= ".gz";
        }
        // Encrypting
        //----------------
        if ($this->option('encrypt') !== false) {
            if (!Encrypt::encryptFile($this->filePath)) {
                return $this->line('Encrypt returned false result');
            }
        }

        // Save dump name to db
        //----------------
        Dump::create([
            'file' => $this->filePath,
            'file_name' => $this->fileName,
            'prefix' => $this->option('prefix'),
            'encrypted' => ($this->option('encrypt') ? 1 : 0),
            'created_at' => Carbon::now()->timestamp
        ]);

        $this->line($handler->dumpResponse($this->argument('filename'), $this->filePath, $this->fileName));

        // S3 Upload
        //----------------
        if ($this->option('upload-s3') !== false) {
            $this->uploadS3();
            $this->line($handler->s3DumpResponse());

            if ($this->option('keep-only-s3') !== false) {
                File::delete($this->filePath);
                $this->line($handler->localDumpRemovedResponse());
            }
        }
    }

    /**
     * Perform Gzip compression on file
     *
     * @return boolean
     */
    protected function compress()
    {
        $command = sprintf('gzip -9 %s', $this->filePath);

        return $this->console->run($command);
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['filename', InputArgument::OPTIONAL, 'Filename or -path for the dump.'],
        ];
    }

    /**
     * @return array
     */
    protected function getOptions()
    {
        return [
            [
                'database',
                null,
                InputOption::VALUE_OPTIONAL,
                'The database connection to backup',
                'mysql'
            ],
            [
                'upload-s3',
                null,
                InputOption::VALUE_OPTIONAL,
                'Upload the dump to your S3 bucket',
                true
            ],
            [
                'keep-only-s3',
                null,
                InputOption::VALUE_OPTIONAL,
                'Delete the local dump after upload to S3 bucket',
                true
            ],
            [
                'prefix',
                null,
                InputOption::VALUE_OPTIONAL,
                'Prefix for sql backup',
                date('Y-m-d') . '-'
            ],
            [
                'encrypt',
                null,
                InputOption::VALUE_OPTIONAL,
                'Encrypt dump',
                false
            ],
        ];
    }

    /**
     * @return void
     */
    protected function checkDumpFolder()
    {
        $dumpsPath = $this->getDumpsPath();

        if (!is_dir($dumpsPath)) {
            mkdir($dumpsPath);
        }
    }

    /**
     * @return void
     */
    protected function uploadS3()
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
            $s3->putObject([
                'Bucket' => Config::get('db-backup.s3.bucket'),
                'Key' => $this->getS3Path() . '/' . $this->fileName,
                'SourceFile' => $this->filePath
            ]);
        } catch (Aws\S3\Exception\S3Exception $e) {
            die($e->getMessage());
        }
    }
}
