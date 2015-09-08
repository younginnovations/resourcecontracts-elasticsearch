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
     * Delete Contract
     * @param $request
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
     * @param $id
     * @return array
     */
    private function deleteMetadata($id)
    {
        try {
            $params['index'] = $this->index;
            $params['type']  = "metadata";
            $params['id']    = $id;

            $delete = $this->deleteDocument($params);
            logger()->info("Metadata Deleted", $delete);

            return $delete;
        } catch (Missing404Exception $e) {
            logger()->error("Metadata not found", $e->getMessage());

            return "Metadata not found";
        }
    }


    /**
     * Delete Pdf TExt
     * @param $id
     * @return array
     */
    private function deletePdfText($id)
    {
        try {
            $params['index']                                = $this->index;
            $params['type']                                 = "pdf_text";
            $params['body']['query']['term']['contract_id'] = $id;

            $delete = $this->deleteDocumentByQuery($params);
            logger()->info("Pdf Text Deleted", $delete);

            return $delete;
        } catch (Missing404Exception $e) {
            logger()->error("Pdf text not found", $e->getMessage());

            return "Text not found";
        }

    }

    /**
     * Delete Annotations
     * @param $id
     * @return array
     */
    private function deleteAnnotations($id)
    {
        try {
            $params['index']                                = $this->index;
            $params['type']                                 = "annotations";
            $params['body']['query']['term']['contract_id'] = $id;

            $delete = $this->deleteDocumentByQuery($params);
            logger()->info("Annotations deleted", $params);

            return $delete;
        } catch (Missing404Exception $e) {
            logger()->error("Annotaions not found", $e->getMessage());

            return "Annotations not found";
        }
    }

    /**
     * Delete contract from master type
     * @param $id
     * @return array
     */
    private function deleteMaster($id)
    {
        try {
            $params['index'] = $this->index;
            $params['type']  = "master";
            $params['id']    = $id;
            $delete          = $this->deleteDocument($params);
            logger()->info("Master Deleted", $delete);

            return $delete;
        } catch (Missing404Exception $e) {
            logger()->error("Master Not found", $e->getMessage());

            return "Master not found";
        }
    }
}
