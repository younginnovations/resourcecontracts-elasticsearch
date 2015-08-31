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
    protected $index = 'nrgi1';

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
        $master   = $this->insertIntoMaster($textData['contract_id'], $data);
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
                'page_no'     => (integer)$text['page_no'],
                "contract_id" => (integer)$textData['contract_id'],
                "text"        => $text['text'],
                "pdf_url"     => $text['pdf_url'],
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

        return array_merge($response, $master);

    }

    /**
     * Create or Update a document
     * @param $contractId ,$pdftext
     * @return array
     */
    private function insertIntoMaster($contractId, $pdftext)
    {
        $params['index'] = "nrgi";
        $params['type']  = "master";
        $params['id']    = $contractId;
        $document        = $this->es->exists($params);
        $body            = [
            "metadata"           => [],
            "metadata_string"    => [],
            "pdf_text_string"    => $this->getPdfTextString($pdftext),
            "annotations_string" => []
        ];
        if ($document) {
            $params['body']['doc'] = ["pdf_text_string" => $this->getPdfTextString($pdftext),];
            return $this->es->update($params);
        }
        $params['body'] = $body;

        return $this->es->index($params);
    }

    private function getPdfTextString($pdftext)
    {

        $data = '';
        foreach ($pdftext as $text) {
            $data .= strip_tags($text['text']) . ' ';
        }

        return $data;
    }


}
