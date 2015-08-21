<?php namespace App\Services;

/**
 * Class MetadataService
 * @package App\Services
 */
class MetadataService extends Service
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
    protected $type = 'metadata';

    /**
     * Create document
     * @param array $metaData
     * @return array
     */
    public function index($metaData)
    {
        $params       = $this->getIndexType();
        $params['id'] = $metaData['id'];
        $document     = $this->es->exists($params);
        $metadata     = json_decode($metaData['metadata']);
        $master       = $this->insertIntoMaster($metaData['id'], $metadata);
        $createdBy    = json_decode($metaData['created_by']);
        $updatedBy    = json_decode($metaData['updated_by']);
        $data         = [
            'contract_id'          => $metaData['id'],
            'metadata'             => $metadata,
            'updated_user_name'    => $updatedBy->name,
            'total_pages'          => $metaData['total_pages'],
            'updated_user_email'   => $updatedBy->email,
            'created_user_name'    => $createdBy->name,
            'created_user_email'   => $createdBy->email,
            'supporting_contracts' => $metaData['supporting_contracts'],
            'created_at'           => date('Y-m-d', strtotime($metaData['created_at'])) . 'T' . date(
                    'H:i:s',
                    strtotime($metaData['created_at'])
                ),
            'updated_at'           => date('Y-m-d', strtotime($metaData['updated_at'])) . 'T' . date(
                    'H:i:s',
                    strtotime($metaData['updated_at'])
                ),
        ];
        if ($document) {
            $params['body']['doc'] = $data;

            return $this->es->update($params);
        }
        $params['body'] = $data;
        $response       = $this->es->index($params);

        return array_merge($response, $master);
    }

    /**
     * Delete document
     * @param $id
     * @return array
     */
    public function delete($id)
    {
        $params       = $this->getIndexType();
        $params['id'] = $id;

        return $this->es->delete($params);
    }

    /**
     * Create document
     * @param array response
     * @return array
     */
    public function insertIntoMaster($contracId, $metadata)
    {

        $params['index'] = "nrgi";
        $params['type']  = "master";
        $params['id']    = $contracId;
        $document        = $this->es->exists($params);
        $body            = [
            "metadata"           => $this->filterMetadata($metadata),
            "metadata_string"    => $this->getMetadataString($metadata),
            "pdf_text_string"    => [],
            "annotations_string" => []
        ];

        if ($document) {
            $params['body']['doc'] = [
                "metadata"        => $this->filterMetadata($metadata),
                "metadata_string" => $this->getMetadataString($metadata)
            ];

            return $this->es->update($params);
        }
        $params['body'] = $body;

        return $this->es->index($params);
    }

    public function filterMetadata($metadata)
    {

        $data                   = [];
        $data['contract_name']  = $metadata->contract_name;
        $data['country_name']   = $metadata->country->name;
        $data['country_code']   = $metadata->country->code;
        $data['signature_year'] = $metadata->signature_year;
        $data['signature_date'] = $metadata->signature_date;
        $data['resource']       = $metadata->resource;
        $data['file_size']      = $metadata->file_size;
        $data['language']       = $metadata->language;
        $data['category']       = $metadata->category;

        return $data;
    }

    private function getMetadataString($metadata, $data = '')
    {
        foreach ($metadata as $value) {
            if (is_array($value) || is_object($value)) {
                $data = $this->getMetadataString($value, $data);
            } else {
                if ($value != '') {
                    $data .= ' ' . $value;
                }
            }
        }

        return $data;
    }
}
