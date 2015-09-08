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
                    //logger()->info("Annotations document updated", $response);
                } else {
                    $params['body'] = $doc;
                    $response[]     = $this->es->index($params);
                    //logger()->info("Annotations document created", $response);
                }

                $contractId = $annotation['contract_id'];

            }
            $master = $this->insertIntoMaster($contractId, $annotations);

            return array_merge($response, $master);


        } catch (\Exception $e) {
            logger()->error("Annotations index error", $e->getMessage());

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
        try {
            $response        = [];
            $params['index'] = $this->index;
            $params['type']  = "master";
            $params['id']    = $contractId;
            $document        = $this->es->exists($params);
            $body            = [
                "metadata"             => [],
                "metadata_string"      => [],
                "pdf_text_string"      => [],
                "annotations_category" => $this->getAnnotationsCategory($annotations),
                "annotations_string"   => $this->getAnnotationsString($annotations)
            ];
            if ($document) {
                $params['body']['doc'] = ["annotations_category" => $this->getAnnotationsCategory($annotations), "annotations_string" => $this->getAnnotationsString($annotations)];

                $response = $this->es->update($params);
                logger()->info("Annotaions updated in master", $response);

                return $response;

            }
            $params['body'] = $body;

            $response = $this->es->index($params);

            //logger()->info("Annotations created in master", $response);

            return $response;
        } catch (\Exception $e) {
            logger()->error("Annotations error while inserting in master", $e->getMessage());

            return $e->getMessage();
        }
    }

    private function getAnnotationsString($annotations)
    {

        $data = '';
        foreach ($annotations as $annotation) {
            $quote    = isset($annotation['quote']) ? $annotation['quote'] : "";
            $text     = isset($annotation['text']) ? $annotation['text'] : "";
            $category = isset($annotation['category']) ? $annotation['category'] : "";
            $data .= ' ' . $quote . ' ' . $text . ' ' . $category;

        }

        return $data;
    }

    /**
     * Get all the annotations category
     * @param $annotations
     *
     * @return array $category
     */
    private function getAnnotationsCategory($annotations)
    {
        $category = [];
        foreach ($annotations as $annotation) {
            array_push($category, $annotation['category']);
        }

        return array_unique($category);
    }
}
