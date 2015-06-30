<?php
$router->addRoute('GET', '/', 'App\Controllers\ApiController::index');
$router->addRoute('POST', '/contract/metadata', 'App\Controllers\ApiController::metadata');
$router->addRoute('POST', '/contract/pdf-text', 'App\Controllers\ApiController::pdfText');
$router->addRoute('POST', '/contract/annotations', 'App\Controllers\ApiController::annotation');