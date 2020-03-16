<?php

namespace App\Services;

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

            $anno = [];
            foreach ($annotations as $annotation) {
                $params          = [];
                $params['index'] = $this->index;
                $params['type']  = $this->type;
                $params['id']    = $annotation['id'];

                $annotation['annotation_text'] = [
                    'en' => $annotation['text'],
                    'fr' => $annotation['text_locale']['fr'],
                    'ar' => $annotation['text_locale']['ar'],
                ];

                $annotation['article_reference'] = [
                    'en' => $annotation['article_reference'],
                    'fr' => $annotation['article_reference_locale']['fr'],
                    'ar' => $annotation['article_reference_locale']['ar'],
                ];

                unset($annotation['text']);
                unset($annotation['text_locale']);
                unset($annotation['article_reference_locale']);

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
                $anno[]     = $annotation;
            }

            $master = $this->insertIntoMaster($contractId, $anno);

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
            $annotations_string[] = [
                "en" => $this->getAnnotationsString($annotations, 'en'),
                "fr" => $this->getAnnotationsString($annotations, 'fr'),
                "ar" => $this->getAnnotationsString($annotations, 'ar'),
            ];
            $body                 = [
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
     * @param $lang
     *
     * @return string
     */
    private function getAnnotationsString($annotations, $lang)
    {
        $data = '';

        logger()->info("Annotations".$lang, $annotations);

        foreach ($annotations as $annotation) {
            $text = isset($annotation['annotation_text'][$lang]) ? $annotation['annotation_text'][$lang] : "";
            $data .= ' ' . $text;
        }

        foreach ($annotations as $annotation) {
            $text = isset($annotation['category']) ? $annotation['category'] : "";
            $data .= ' ' . $text;
        }

        $data = trim($data);

        return $data ?: [];
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

    /**
     * Updates annotation category name "Community consultation " to 
     * "Community consultation" in elastic search
     *
     * @param $contracts
     *
     * @return array
     */
    public function updateAnnotationCategory($contracts)
    {
        $response = [];

        foreach($contracts as $id => $contract) {
            $params['index']      = $this->index;
            $params['type']       = "master";
            $params['id']         = $id;
            $document             = $this->es->exists($params);
            $annotations_category = $this->getAnnotationsCategory($contract);
            $annotations_category = $annotations_category == '' ? [] : $annotations_category;
        
            if ($document) {
                $params['body']['doc'] = [
                    "annotations_category" => $annotations_category,
                ];
                $response[]              = $this->es->update($params);
            }
        }

        return $response;
    }
}
