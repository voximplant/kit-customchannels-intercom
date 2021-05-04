<?php

use GuzzleHttp\Client;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use Intercom\IntercomClient;
use Intercom\Repository;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use VoximplantKitIM\Configuration;
use Symfony\Component\Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Configuration for logging
$logger = new Monolog\Logger('name');
$logger->pushHandler(new StreamHandler(__DIR__ . '/intercom.log', Logger::WARNING));
$logger->pushHandler(new StreamHandler('php://stdout', Logger::WARNING));

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__.'/.env');

//Cache configuration for store conversations data
$filesystemAdapter = new Local(__DIR__ . '/cache/');
$filesystem        = new Filesystem($filesystemAdapter);
$cache = new FilesystemCachePool($filesystem);

//Configuration for Voximplant Kit API client
$kitConfig = new Configuration();
$kitConfig->setHost($_ENV['KIT_API_URL']);
$kitConfig->setApiKey('domain', $_ENV['KIT_ACCOUNT_NAME']);
$kitConfig->setApiKey('access_token', $_ENV['KIT_API_TOKEN']);

$kit = new VoximplantKitIM\VoximplantKitIMClient($kitConfig);

//Configuration for Intercom API client
$intercomToken = $_ENV['INTERCOM_API_TOKEN'];
$intercomAdminId = $_ENV['INTERCOM_ADMIN_ID'];
$intercomClient =  new IntercomClient(new Client([
    'base_uri' => 'https://api.intercom.io',
    'timeout'  => 2.0,
    'http_errors' => false,
    'headers' => [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $intercomToken
    ]
]));

// Your custom channel uuid in Voximplant KIT
$channelUUID = $_ENV['KIT_CHANNEL_UUID'];
$service = new Intercom\Service(
    $intercomClient,
    new Repository($cache),
    $kit,
    $logger,
    $channelUUID,
    $intercomAdminId
);