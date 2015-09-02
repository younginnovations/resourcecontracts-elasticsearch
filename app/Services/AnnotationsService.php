<?php namespace App\Services;

/**
 * Class AnnotationsService
 * @package App\Services
 */
class AnnotationsService extends Service
{
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
        try {

            $annotations = json_decode($request['annotations'], true);
            $response    = [];
            $contractId  = '';
            foreach ($annotations as $annotation) {
                $params          = [];
                $params['index'] = $this->index;
                $params['type']  = $this->type;
                $params['id']    = $annotation['id'];

                $doc = $annotation;

                $document = $this->es->exists($params);
                if ($document) {
                    $params['body']['doc'] = $doc;
                    $response[]            = $this->es->update($params);
                } else {
                    $params['body'] = $doc;
                    $response[]     = $this->es->index($params);
                }

                $contractId = $annotation['contract_id'];

            }
            $master = $this->insertIntoMaster($contractId, $annotations);

            return array_merge($response, $master);

            return $response;


        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Index Master
     * @param $contractId ,$annotations
     * @return array
     */

    private function insertIntoMaster($contractId, $annotations)
    {
        $params['index'] = $this->index;
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
            $data .= ' ' . isset($annotation['text']) ? $annotation['text'] : "" . " " . isset($annotation['quote']) ? $annotation['quote'] : "" . ' ' . $annotation['tags'];
        }

        return $data;
    }
}
