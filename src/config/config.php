<?php

return [
    // set path for backup location, ex. /storage/backups would be
    // the full path
	'path' => storage_path() . '/backups/',

    // add the path to the restore and backup command of mysql
    // on mac: '/Applications/MAMP/Library/bin/'
    // on windows: 'C:\\...\\mysql\\bin\\'
    // on linux: '/usr/bin/'
    // trailing slash is required
	'mysql' => [
		'dump_command_path' => '/usr/bin/',
		'restore_command_path' => '/usr/bin/',
	],

    // s3 config
	's3' => [
		'path' => '/backups',
        'bucket' => env('S3_BUCKET', null),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'accessKey' => env('AWS_ACCESS_KEY_ID', null),
        'secretKey' => env('AWS_SECRET_ACCESS_KEY', null)
    ],

    // encryption
    'encrypt' => [
        'key' => env('BACKUP_ENCRYPT_KEY')
    ],

    // GZIP compression
    'compress' => true,
];

