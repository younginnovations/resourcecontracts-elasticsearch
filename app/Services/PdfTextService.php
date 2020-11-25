<?php namespace App\Services;

use League\Route\Http\Exception;

/**
 * Class PdfTextService
 * @package App\Services
 */
class PdfTextService extends Service
{
    /**
     * Create or Update a document
     *
     * @param $textData
     *
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
                $params          = [];
                $params['index'] = $this->getPdfTextIndex();
                $param['id'] = $text['id'];
                $doc         = [
                    'metadata'            => $metadata,
                    'page_no'             => (integer) $text['page_no'],
                    'open_contracting_id' => $textData['open_contracting_id'],
                    "contract_id"         => (integer) $textData['contract_id'],
                    "text"                => $text['text'],
                    "pdf_url"             => $text['pdf_url'],
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

        } catch (\Exception $e) {
            logger()->error("Pdf text document not created", [$e->getMessage()]);

            return [$e->getMessage()];
        }
    }

    /**
     * Create or Update a document
     *
     * @param $contractId ,$pdfText
     *
     * @return array
     */
    private function insertIntoMaster($contractId, $pdfText)
    {
        try {
            $params['index'] = $this->getMasterIndex();
            $params['id']    = $contractId;
            $document        = $this->es->exists($params);
            $body            = [
                "metadata"             => [],
                "metadata_string"      => [],
                "pdf_text_string"      => $this->getPdfTextString($pdfText),
                "annotations_category" => [],
                "annotations_string"   => [],
            ];
            if ($document) {
                $params['body']['doc'] = ["pdf_text_string" => $this->getPdfTextString($pdfText),];

                $response = $this->es->update($params);
                logger()->info("Pdf text updated in master index", $response);

                return $response;
            }
            $params['body'] = $body;

            $response = $this->es->index($params);

            logger()->info("Pdf text created in master index", $response);

            return $response;
        } catch (Exception $e) {
            logger()->error("Error while indexing pdf text in master", [$e->getMessage()]);

            return [$e->getMessage()];
        }
    }

    /**
     * Get Pdf Text String
     *
     * @param $pdfText
     *
     * @return string
     */
    private function getPdfTextString($pdfText)
    {
        $data = '';

        foreach ($pdfText as $text) {
            $data .= strip_tags($text['text']).' ';
        }

        return trim($data);
    }

}
