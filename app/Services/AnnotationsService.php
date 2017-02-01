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
     *
     * @param $request
     *
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
                    logger()->info("Annotations document updated", $response);
                } else {
                    $params['body'] = $doc;
                    $response[]     = $this->es->index($params);
                    logger()->info("Annotations document created", $response);
                }

                $contractId = $annotation['contract_id'];
            }

            $master = $this->insertIntoMaster($contractId, $annotations);

            return array_merge($response, $master);
        } catch (\Exception $e) {
            logger()->error("Annotations index error", [$e->getMessage()]);

            return [$e->getMessage()];
        }
    }

    /**
     * Remove Keys From Array
     *
     * @param $items
     *
     * @return array
     */
    protected function removeKeys($items)
    {
        $i = [];

        foreach ($items as $item) {
            $i[] = $item;
        }

        return $i;
    }

    /**
     * Index Master
     *
     * @param $contractId
     * @param $annotations
     *
     * @return array
     */
    private function insertIntoMaster($contractId, $annotations)
    {
        try {
            $params['index']      = $this->index;
            $params['type']       = "master";
            $params['id']         = $contractId;
            $document             = $this->es->exists($params);
            $annotations_category = $this->getAnnotationsCategory($annotations);
            $annotations_category = $annotations_category == '' ? [] : $annotations_category;
            $annotations_string   = $this->getAnnotationsString($annotations);
            $annotations_string   = $annotations_string == '' ? [] : $annotations_string;

            $body = [
                "metadata"             => [],
                "metadata_string"      => [],
                "pdf_text_string"      => [],
                "annotations_category" => $annotations_category,
                "annotations_string"   => $annotations_string,
            ];

            if ($document) {
                $params['body']['doc'] = [
                    "annotations_category" => $annotations_category,
                    "annotations_string"   => $annotations_string,
                ];
                $response              = $this->es->update($params);
                logger()->info("Annotations updated in master", $response);

                return $response;
            }
            $params['body'] = $body;
            $response       = $this->es->index($params);
            logger()->info("Annotations updated in master", $response);

            return $response;
        } catch (\Exception $e) {
            logger()->error("Annotations error while inserting in master", [$e->getMessage()]);

            return [$e->getMessage()];
        }
    }

    /**
     * Format annotations in string
     *
     * @param $annotations
     *
     * @return string
     */
    private function getAnnotationsString($annotations)
    {
        $data = '';

        foreach ($annotations as $annotation) {
            $text = isset($annotation['text']) ? $annotation['text'] : "";
            $data .= ' '.$text;
        }

        foreach ($annotations as $annotation) {
            $text = isset($annotation['category']) ? $annotation['category'] : "";
            $data .= ' '.$text;
        }

        return trim($data);
    }

    /**
     * Get all the annotations category
     *
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

        $data = array_unique($category);
        $data = $this->removeKeys($data);

        return $data;
    }
}
