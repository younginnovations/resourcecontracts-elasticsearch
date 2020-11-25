<?php namespace App\Services;

use Elasticsearch\ClientBuilder;

class Service
{
    /**
     * ES Index Name
     * @var string
     */
    protected $index;

    /*
     * Prefix to use for all ES indexes
     * @var string
     */
    protected $indices_prefix;

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
        $hosts       = explode(",",env('ELASTICSEARCH_SERVER'));
        $this->index = env('INDEX');
        $this->indices_prefix = env('INDICES_PREFIX');
        $client      = ClientBuilder::create()->setHosts($hosts);
        $this->es    = $client->build();
    }

    /**
     * Get Index and Type
     * @return array
     */
    public function getIndexType()
    {
        return [
            'index' => $this->index,
            'type'  => $this->type,
        ];
    }

    public function getMasterIndex()
    {
        return $this->indices_prefix . '_master';
    }

    public function getMetadataIndex()
    {
        return $this->indices_prefix . '_metadata';
    }

    public function getAnnotationsIndex()
    {
        return $this->indices_prefix . '_annotations';
    }

    public function getPdfTextIndex()
    {
        return $this->indices_prefix . '_pdf_text';
    }

    /**
     * Delete a document by id
     *
     * @param $param
     *
     * @return array
     */
    public function deleteDocument($param)
    {
        return $this->es->delete($param);
    }

    /**
     * Delete a document By query
     *
     * @param $param
     *
     * @return array
     */
    public function deleteDocumentByQuery($param)
    {
        return $this->es->deleteByQuery($param);
    }
}
