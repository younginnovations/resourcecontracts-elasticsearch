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
                'contract_id' => (integer) $annotation['contract'],
                'page_no'     => (integer) $annotation['document_page_no'],
            ];
            $document                                 = $this->es->exists($params);
            if ($document) {
                $params['body']['doc'] = $doc;
                $response[]            = $this->es->update($params);
            } else {
                $params['body'] = $doc;
                $response[]     = $this->es->index($params);
            }

        }

        return $response;
    }
}
