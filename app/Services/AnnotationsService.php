<?php namespace App\Services;

/**
 * Class AnnotationsService
 * @package App\Services
 */
class AnnotationsService extends Service
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
    protected $type = 'annotations';

    /**
     * Index Annotations
     * @param $request
     * @return array
     */
    public function index($request)
    {
        $annotations = json_decode($request['annotations'], true);
        $response    = [];
        foreach ($annotations as $annotation) {
            $year = '';
            if (!empty($annotation['metadata']['signature_date'])) {
                $year = date('Y', strtotime($annotation['metadata']['signature_date']));
            }
            $annotation['metadata']['signature_year'] = $year;
            $params                                   = $this->getIndexType();
            $params['id']                             = $annotation['id'];
            $doc                                      = [
                'metadata'    => $annotation['metadata'],
                'ranges'      => $annotation['ranges'],
                'quote'       => $annotation['quote'],
                'text'        => $annotation['text'],
                'tags'        => $annotation['tags'],
                'category'    => $annotation['category'] ,
                'contract_id' => (integer)$annotation['contract'],
                'page_no'     => (integer)$annotation['document_page_no'],
            ];
            $contractId                               = $annotation['contract'];
            $document                                 = $this->es->exists($params);
            if ($document) {
                $params['body']['doc'] = $doc;
                $response[]            = $this->es->update($params);
            } else {
                $params['body'] = $doc;
                $response[]     = $this->es->index($params);
            }

        }
        $master = $this->insertIntoMaster($contractId, $annotations);
        return array_merge($response, $master);
    }

    /**
     * Index Master
     * @param $contractId ,$annotations
     * @return array
     */

    private function insertIntoMaster($contractId, $annotations)
    {
        $params['index'] = "nrgi";
        $params['type']  = "master";
        $params['id']    = $contractId;
        $document        = $this->es->exists($params);
        $body            = [
            "metadata"           => [],
            "metadata_string"    => [],
            "pdf_text_string"    => [],
            "annotations_string" => $this->getAnnotationsString($annotations)
        ];
        if ($document) {
            $params['body']['doc'] = ["annotations_string" => $this->getAnnotationsString($annotations)];
            return $this->es->update($params);
        }
        $params['body'] = $body;
        return $this->es->index($params);
    }

    private function getAnnotationsString($annotations)
    {
        $data = '';
        foreach ($annotations as $annotation) {
            $data .= ' ' . $annotation['quote']. ' ' . $annotation['tags'];
        }

        return $data;
    }
}
