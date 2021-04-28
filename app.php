<?php

use Amp\Http\Server\HttpServer;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Http\Status;
use Amp\Socket\Server;
use Amp\Http\Server\Request;
use Psr\Log\NullLogger;

require 'vendor/autoload.php';
require __DIR__ . '/config.php';

//Generate JWT Access token for messaging
$service->login();

Amp\Loop::run(function () use ($service) {
    $sockets = [
        Server::listen("0.0.0.0:1338"),
        Server::listen("[::]:1338"),
    ];
    $router = new Router;

    // Handler for intercom events
    $router->addRoute('POST', '/intercom-incoming', new CallableRequestHandler(function(Request $request) use ($service) {
        $buffer = '';
        while (($chunk = yield $request->getBody()->read()) !== null) {
            $buffer .= $chunk;
        }
        $service->handleIntercomEvent($buffer);
        return new Response(Status::OK, [
            "content-type" => "text/plain; charset=utf-8"
        ], "OK");
    }));

    // Handler for Voximplant kit events
    $router->addRoute('POST', '/kit-incoming', new CallableRequestHandler(function (Request $request) use ($service) {
        $buffer = '';
        while (($chunk = yield $request->getBody()->read()) !== null) {
            $buffer .= $chunk;
        }
        $service->handleKitEvent($buffer);
        return new Response(Status::OK, [
            "content-type" => "text/plain; charset=utf-8"
        ], "OK");
    }));

    // Handler for Voximplant Kit checking request
    $router->addRoute('GET', '/kit-incoming', new CallableRequestHandler(function (Request $request) {
        return new Response(Status::OK, [
            "content-type" => "text/plain; charset=utf-8"
        ], "OK");
    }));

    $server = new HttpServer($sockets, $router, new NullLogger);

    yield $server->start();

    Amp\Loop::onSignal(SIGINT, function (string $watcherId) use ($server) {
        Amp\Loop::cancel($watcherId);
        yield $server->stop();
    });
});
