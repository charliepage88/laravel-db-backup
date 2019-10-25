# laravel-db-backup

Based off of https://github.com/schickling/laravel-backup with support for Laravel 5.*.

And of https://github.com/wladmonax/laravel-db-backup


Installation
----
Run composer command :
```bash
composer require charliepage88/laravel-db-backup
```
Or                  
                            
Update your `composer.json` file to include this package as a dependency
```json
"charliepage88/laravel-db-backup": "dev-master",
```

Register the service provider by adding it to the providers array in the `config/app.php` file.
```php
'providers' => array(
    'Witty\LaravelDbBackup\DBBackupServiceProvider'
)
```
or 
  
```php
'providers' => array(
    Witty\LaravelDbBackup\DBBackupServiceProvider::class
)
```

Run command to creating tables: 

```sh
$ php artisan migrate
```

# Configuration

Copy the config file into your project by running
```
php artisan vendor:publish
```

This will generate a config file like this
```php
return [

    // add a backup folder in the app/database/ or your dump folder
    'path' => app_path() . '/backups/',

    // add the path to the restore and backup command of mysql
    // on mac: '/Applications/MAMP/Library/bin/'
    // on windows: 'C:\\...\\mysql\\bin\\'
    // on linux: '/usr/bin/'
    // trailing slash is required
    'mysql' => [
        'dump_command_path' => '/usr/bin/',
        'restore_command_path' => '/usr/bin/',
    ],

    // s3 settings
    's3' => [
        'path'  => '/backups',
        'bucket' => env('S3_BUCKET', null),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'accessKey' => env('AWS_ACCESS_KEY_ID', null),
        'secretKey' => env('AWS_SECRET_ACCESS_KEY', null),
    ],
    
    //encrypt settings
    'encrypt' => [
        'key' => env('BACKUP_ENCRYPT_KEY', '')
    ],

    // Use GZIP compression
    'compress' => true,
];

```

__All settings are optional and have set default values.__

## Usage

#### Backup
Creates a dump file in `app/storage/backups`, by default
```sh
$ php artisan db:backup
```

###### Use specific database connection
```sh
$ php artisan db:backup --database=beta
```
###### Enable encryption of backup files
```sh
$ php artisan db:backup --encrypt
```
###### Pass in multiple options easily
```sh
$ php artisan db:backup --upload-s3 --encrypt
```

###### Upload to AWS S3
```sh
$ php artisan db:backup --upload-s3
```

You can use the `--keep-only-s3` option if you don't want to keep a local copy of the SQL dump.

Uses the [aws/aws-sdk-php](https://github.com/aws/aws-sdk-php) package for uploading/listing/getting files.

#### Restore
Paths are relative to the app/storage folder.

###### Restore a dump
```sh
$ php artisan db:restore dump.sql
```

###### Restore from last backup dump
```sh
$ php artisan db:restore --last-dump
```

###### Restore from S3
```sh
$ php artisan db:restore --aws-dump=filename.sql
```

###### Restore from S3 last dump
```sh
$ php artisan db:restore --aws-last-dump
```

###### List dumps
```sh
$ php artisan db:restore
```
