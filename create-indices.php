#!/usr/bin/env php
<?php
define('APP_PATH', dirname(__FILE__));

/*
 * Composer Autoload
 */
require './vendor/autoload.php';

/*
 * Load environment.
 */
require 'server.php';
require 'app/helper.php';

use App\Services\MetadataService;

/**
 * Class command
 */
class CreateIndicesCommand
{
    /**
     * @var string
     */
    protected $base;

    /**
     * command constructor.
     */
    public function __construct()
    {
        $this->base = dirname(__FILE__);
    }

    /**
     * Run serve command
     */
    public function run()
    {
        echo APP_PATH;
        $metadata = new MetadataService();
        echo "Indices created";
    }
}

$command = new CreateIndicesCommand();
$command->run();



