<?php namespace App\Services;

/**
 * Class PdfTextService
 * @package App\Services
 */
class PdfTextService extends Service
{
    /**
     * ES Index Name
     * @var string
     */
    protected $index = 'nrgi';

    /**
     *  ES Type
     * @var string
     */
    protected $type = 'pdf_text';

    /**
     * Create or Update a document
     * @param $textData
     * @return array
     */
    public function index($textData)
    {
        $data     = json_decode($textData['pages'], true);
        $metadata = json_decode($textData['metadata'], true);
        $year     = '';
        if (!empty($metadata['signature_date'])) {
            $year = date('Y', strtotime($metadata['signature_date']));
        }
        $metadata['signature_year'] = $year;
        $response                   = array();
        foreach ($data as $text) {
            $param       = $this->getIndexType();
            $param['id'] = $text['id'];
            $doc         = [
                'metadata'    => $metadata,
                'page_no'     => (integer) $text['page_no'],
                "contract_id" => (integer) $textData['contract_id'],
                "text"        => $text['text']
            ];
            $document    = $this->es->exists($param);
            if ($document) {
                $param['body']['doc'] = $doc;
                $response[]           = $this->es->update($param);
            } else {
                $param['body'] = $doc;
                $response[]    = $this->es->index($param);
            }
        }

        return $response;

    }

}
