<?php namespace App\Services;

use Elasticsearch\Common\Exceptions\Missing404Exception;
use League\Route\Http\Exception;

/**
 * Delete Contract's Metadata,Annotations and Text
 * Class DeleteContractService
 * @package App\Services
 */
class DeleteContractService extends Service
{
    /**
     * @var MetadataService
     */
    private $meta;

    /**
     * @param MetadataService $meta
     * @param PdfTextService  $textService
     */


    /**
     * Delete Contract
     *
     * @param $request
     *
     * @return mixed
     */
    public function deleteContract($request)
    {
        $id                      = $request['id'];
        $response['metadata']    = $this->deleteMetadata($id);
        $response['pdftext']     = $this->deletePdfText($id);
        $response['annotations'] = $this->deleteAnnotations($id);
        $response['master']      = $this->deleteMaster($id);

        return $response;
    }

    /**
     * Delete Metadata
     *
     * @param $id
     *
     * @return array
     */
    public function deleteMetadata($id)
    {
        try {
            $params['index'] = $this->getMetadataIndex();
            $params['id']    = $id;

            $delete = $this->deleteDocument($params);
            logger()->info("Metadata Deleted", $delete);

            return $delete;
        } catch (Missing404Exception $e) {
            logger()->warning("Metadata not found", [$e->getMessage()]);

            return "Metadata not found";
        }
    }


    /**
     * Delete Pdf TExt
     *
     * @param $id
     *
     * @return array
     */
    public function deletePdfText($id)
    {
        try {
            $params['index']                                = $this->getPdfTextIndex();
            $params['body']['query']['term']['contract_id'] = $id;

            $delete = $this->deleteDocumentByQuery($params);
            $this->updateTextInMaster($id);
            logger()->info("Pdf Text Deleted", $delete);

            return $delete;
        } catch (Missing404Exception $e) {
            logger()->warning("Pdf text not found", [$e->getMessage()]);

            return "Text not found";
        }

    }

    /**
     * Delete Annotations
     *
     * @param $id
     *
     * @return array
     */
    public function deleteAnnotations($id)
    {
        try {
            $params['index']                                = $this->getAnnotationsIndex();
            $params['body']['query']['term']['contract_id'] = $id;
            $delete                                         = $this->deleteDocumentByQuery($params);
            $this->updateAnnotationInMaster($id);
            $deleted = isset($delete['_indices']['_all']['deleted']) ? $delete['_indices']['_all']['deleted'] : 0;
            logger()->info(sprintf("%s annotations deleted", $deleted));

            return $delete;
        } catch (Missing404Exception $e) {
            logger()->warning("Annotations not found", [$e->getMessage()]);

            return "Annotations not found";
        }
    }

    /**
     * Delete contract from master type
     *
     * @param $id
     *
     * @return array
     */
    public function deleteMaster($id)
    {
        try {
            $params['index'] = $this->getMasterIndex();
            $params['id']    = $id;
            $delete          = $this->deleteDocument($params);
            logger()->info("Master Deleted", $delete);

            return $delete;
        } catch (Missing404Exception $e) {
            logger()->warning("Master Not found", [$e->getMessage()]);

            return "Master not found";
        }
    }

    /**
     * Delete text from master
     *
     * @param $id
     *
     * @return array|string
     */
    public function updateTextInMaster($id)
    {
        try {
            $params['index']       = $this->getMasterIndex();
            $params['id']          = $id;
            $params['body']['doc'] = [
                "pdf_text_string" => "",
            ];

            $response = $this->es->update($params);
            logger()->info("Text deleted from master", $response);

            return $response;
        } catch (Missing404Exception $e) {
            logger()->warning("Master Not found", [$e->getMessage()]);

            return "Master not found";
        }
    }

    /**
     * Delete annotation's text and annotation category from master
     *
     * @param $id
     *
     * @return array|string
     */
    public function updateAnnotationInMaster($id)
    {
        try {
            $params['index']       = $this->getMasterIndex();
            $params['id']          = $id;
            $params['body']['doc'] = [
                "annotations_category" => [],
                "annotations_string"   => [],
            ];

            $response = $this->es->update($params);
            logger()->info("Annotation deleted from master", $response);

            return $response;
        } catch (Missing404Exception $e) {
            logger()->warning("Master Not found", [$e->getMessage()]);

            return "Master not found";
        }
    }
}
