<?php

abstract class TestCase extends PHPUnit_Framework_TestCase
{


    public function getClient()
    {
        $client = new \Elasticsearch\ClientBuilder();
        $client = $client->create()->build();
        return $client;
    }


}