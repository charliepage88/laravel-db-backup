<?php

return [

	'path' => storage_path() . '/backups/',

	'mysql' => [
		'dump_command_path' => '/usr/bin/',
		'restore_command_path' => '/usr/bin/',
	],

	's3' => [
		'path' => '/backups',
        'bucket' => env('S3_BUCKET', null)
        'region' => env('', 'us-east-1'),
        'accessKey' => env('AWS_ACCESS_KEY_ID', null),
        'secretKey' => env('AWS_SECRET_ACCESS_KEY', null),
    ],

    'encrypt' => [
        'key' => env('ENCRYPT_KEY','')
    ],
    'compress' => true,
];

