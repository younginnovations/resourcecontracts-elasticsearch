<?php namespace App\Services;

use League\Route\Http\Exception;

/**
 * Class PdfTextService
 * @package App\Services
 */
class PdfTextService extends Service
{

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
        try {
            $data     = json_decode($textData['pages'], true);
            $metadata = json_decode($textData['metadata'], true);
            $master   = $this->insertIntoMaster($textData['contract_id'], $data);
            $year     = '';
            if (!empty($metadata['signature_date'])) {
                $year = date('Y', strtotime($metadata['signature_date']));
            }
            $metadata['signature_year'] = $year;
            $response                   = [];
            foreach ($data as $text) {
                $param       = $this->getIndexType();
                $param['id'] = $text['id'];
                $doc         = [
                    'metadata'    => $metadata,
                    'page_no'     => (integer) $text['page_no'],
                    "contract_id" => (integer) $textData['contract_id'],
                    "text"        => $text['text'],
                    "pdf_url"     => $text['pdf_url'],
                ];
                $document    = $this->es->exists($param);
                if ($document) {
                    $param['body']['doc'] = $doc;
                    $response[]           = $this->es->update($param);
                    //logger()->info("Pdf Text document updated", $response);
                } else {
                    $param['body'] = $doc;
                    $response[]    = $this->es->index($param);
                    //logger()->info("Pdf Text document created", $response);
                }
            }

            return array_merge($response, $master);

        } catch (\Exception $e) {
            logger()->error("Pdf text document not created", [$e->getMessage()]);

            return [$e->getMessage()];
        }
    }

    /**
     * Create or Update a document
     * @param $contractId ,$pdftext
     * @return array
     */
    private function insertIntoMaster($contractId, $pdftext)
    {
        try {
            $response        = [];
            $params['index'] = $this->index;
            $params['type']  = "master";
            $params['id']    = $contractId;
            $document        = $this->es->exists($params);
            $body            = [
                "metadata"             => [],
                "metadata_string"      => [],
                "pdf_text_string"      => $this->getPdfTextString($pdftext),
                "annotations_category" => [],
                "annotations_string"   => []
            ];
            if ($document) {
                $params['body']['doc'] = ["pdf_text_string" => $this->getPdfTextString($pdftext),];

                $response = $this->es->update($params);
                logger()->info("Pdf text updated in master index", $response);

                return $response;
            }
            $params['body'] = $body;

            $response = $this->es->index($params);

            //logger()->info("Pdf text created in master index", $response);

            return $response;
        } catch (Exception $e) {
            logger()->error("Error while indexing pdf text in master", [$e->getMessage()]);

            return [$e->getMessage()];
        }
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
