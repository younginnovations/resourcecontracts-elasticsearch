<?php namespace App\Services;

use Elasticsearch\ClientBuilder;

class Service
{
    /**
     * ES Index Name
     * @var string
     */
    protected $index = '';

    /**
     *  ES Type
     * @var string
     */
    protected $type = '';

    /**
     * @var \Elasticsearch\Client
     */
    protected $es;

    function __construct()
    {
        $client   = new ClientBuilder();
        $this->es = $client->create()->build();
    }

    /**
     * Get Index and Type
     * @return array
     */
    public function getIndexType()
    {
        return [
            'index' => $this->index,
            'type'  => $this->type
        ];
    }

    /**
     * Delete a document by id
     * @param $param
     * @return array
     */
    public function deleteDocument($param)
    {
        return $this->es->delete($param);
    }

    /**
     * Delete a document By query
     * @param $param
     * @return array
     */
    public function deleteDocumentByQuery($param)
    {
        return $this->es->deleteByQuery($param);
    }
}
