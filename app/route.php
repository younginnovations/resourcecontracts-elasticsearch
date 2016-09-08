<?php
$router->addRoute('GET', '/', 'App\Controllers\ApiController::index');
$router->addRoute('GET', '/logger', 'App\Services\Log\LogController::index');
$router->addRoute('POST', '/contract/metadata', 'App\Controllers\ApiController::metadata');
$router->addRoute('POST', '/contract/pdf-text', 'App\Controllers\ApiController::pdfText');
$router->addRoute('POST', '/contract/annotations', 'App\Controllers\ApiController::annotation');
$router->addRoute('POST', '/contract/delete', 'App\Controllers\ApiController::deleteContract');
$router->addRoute('POST', '/contract/delete/annotation', 'App\Controllers\ApiController::deleteContractAnnotation');
$router->addRoute('POST', '/delete/source', 'App\Controllers\ApiController::deleteSources');