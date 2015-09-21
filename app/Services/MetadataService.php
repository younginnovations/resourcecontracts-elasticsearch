<?php namespace App\Services;

/**
 * Class MetadataService
 * @package App\Services
 */
class MetadataService extends Service
{
    /**
     *  ES Type
     * @var string
     */
    protected $type = 'metadata';

    public function __construct()
    {
        parent::__construct();
        $this->checkIndex();
    }

    /**
     * Create document
     * @param array $metaData
     * @return array
     */
    public function index($metaData)
    {
        try {
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
                'resource_raw'         => $metadata->resource,
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

                $response = $this->es->update($params);

                //logger()->info("Metadata Index updated",$response);

                return array_merge($response, $master);
            }
            $params['body'] = $data;
            $response       = $this->es->index($params);
            logger()->info("Metadata Index created", $response);

            //return array_merge($response, $master);

        } catch (\Exception $e) {
            logger()->error("Metadata Index Error", [$e->getMessage()]);

            return [$e->getMessage()];

        }

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

        try {
            $response        = [];
            $params['index'] = $this->index;
            $params['type']  = "master";
            $params['id']    = $contracId;
            $document        = $this->es->exists($params);
            $body            = [
                "metadata"             => $this->filterMetadata($metadata),
                "metadata_string"      => $this->getMetadataString($metadata),
                "pdf_text_string"      => [],
                "annotations_category" => [],
                "annotations_string"   => []
            ];

            if ($document) {
                $params['body']['doc'] = [
                    "metadata"        => $this->filterMetadata($metadata),
                    "metadata_string" => $this->getMetadataString($metadata)
                ];

                $response = $this->es->update($params);

                //logger()->info("Master Index updated",$response);
                return $response;
            }
            $params['body'] = $body;

            $response = $this->es->index($params);

            // logger()->info("Master Index created",$response);
            return $response;
        } catch (\Exception $e) {
            logger()->error("Error while indexing Metadata in master", [$e->getMessage()]);

            return [$e->getMessage()];
        }
    }

    public function filterMetadata($metadata)
    {

        $data                       = [];
        $data['contract_name']      = $metadata->contract_name;
        $data['country_name']       = $metadata->country->name;
        $data['country_code']       = $metadata->country->code;
        $data['signature_year']     = $metadata->signature_year;
        $data['signature_date']     = $metadata->signature_date;
        $data['resource']           = $metadata->resource;
        $data['file_size']          = $metadata->file_size;
        $data['language']           = $metadata->language;
        $data['category']           = $metadata->category;
        $data['contract_type']      = $metadata->type_of_contract;
        $data['resource_raw']       = $data['resource'];
        $data['company_name']       = [];
        $data['corporate_grouping'] = [];

        foreach ($metadata->company as $company) {
            if ($company->name != "") {
                array_push($data['company_name'], $company->name);
            }
            if ($company->parent_company != "") {
                array_push($data['corporate_grouping'], $company->parent_company);
            }

        }

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

    /**
     * Check if Index exist or not and update the metadata mapping
     */
    public function checkIndex()
    {
        $condition = $this->es->indices()->exists(['index' => $this->index]);
        if (!$condition) {
            $this->es->indices()->create(['index' => $this->index]);
            $metadata = $this->createMetadataMapping();
            $master   = $this->createMasterMapping();

            return true;
        }

        return true;
    }

    /**
     * Put Mapping of Metadata
     * @return bool
     */
    public function createMetadataMapping()
    {
        try {
            $params['index'] = $this->index;
            $this->es->indices()->refresh($params);
            $params['type'] = $this->type;
            $mapping        = [
                'properties' =>
                    [
                        'metadata'             =>
                            [
                                'contract_id' =>
                                    [
                                        'type' => 'integer',
                                    ],
                                'properties'  =>
                                    [
                                        'contract_name'       =>
                                            [
                                                'type' => 'string',
                                            ],
                                        'contract_idenfifier' =>
                                            [
                                                'type' => 'string',
                                            ],
                                        'language'            =>
                                            [
                                                'type' => 'string',
                                            ],
                                        'country'             =>
                                            [
                                                'properties' =>
                                                    [
                                                        'code' =>
                                                            [
                                                                'type' => 'string',
                                                            ],
                                                        'name' =>
                                                            [
                                                                'type' => 'string',
                                                            ],
                                                    ],
                                            ],
                                        'resource'            =>
                                            [
                                                'type' => 'string',
                                            ],
                                        'government_entity'   =>
                                            [
                                                'properties' =>
                                                    [
                                                        'entity'     =>
                                                            [
                                                                'type' => 'string'
                                                            ],
                                                        'identifier' =>
                                                            [
                                                                'type' => 'string'
                                                            ]
                                                    ]
                                            ],
                                        'type_of_contract'    =>
                                            [
                                                'type' => 'string',
                                            ],
                                        'signature_date'      =>
                                            [
                                                'type'   => 'date',
                                                'format' => 'dateOptionalTime',
                                            ],
                                        'signature_year'      =>
                                            [
                                                'type' => 'string',
                                            ],
                                        'document_type'       =>
                                            [
                                                'type' => 'string',
                                            ],
                                        'translation_parent'  =>
                                            [
                                                'type' => 'string',
                                            ],
                                        'company'             =>
                                            [
                                                'properties' =>
                                                    [
                                                        'name'                          =>
                                                            [
                                                                'type' => 'string',
                                                            ],
                                                        'participation_share'           =>
                                                            [
                                                                'type' => 'double',
                                                            ],
                                                        'jurisdiction_of_incorporation' =>
                                                            [
                                                                'type' => 'string',
                                                            ],
                                                        'registration_agency'           =>
                                                            [
                                                                'type' => 'string',
                                                            ],
                                                        'company_founding_date'         =>
                                                            [
                                                                'type'   => 'date',
                                                                'format' => 'dateOptionalTime',
                                                            ],
                                                        'company_address'               =>
                                                            [
                                                                'type' => 'string',
                                                            ],
                                                        'company_number'                =>
                                                            [
                                                                'type' => 'string',
                                                            ],
                                                        'parent_company'                =>
                                                            [
                                                                'type' => 'string',
                                                            ],
                                                        'open_corporate_id'             =>
                                                            [
                                                                'type' => 'string',
                                                            ],
                                                        'operator'                      =>
                                                            [
                                                                'type' => 'string',
                                                            ],
                                                    ],
                                            ],
                                        'project_title'       =>
                                            [
                                                'type' => 'string',
                                            ],
                                        'project_identifier'  =>
                                            [
                                                'type' => 'string',
                                            ],
                                        'concession'          =>
                                            [
                                                'properties' =>
                                                    [
                                                        'license_name'       =>
                                                            [
                                                                'type' => 'string',
                                                            ],
                                                        'license_identifier' =>
                                                            [
                                                                'type' => 'string',
                                                            ],
                                                    ],
                                            ],
                                        'source_url'          =>
                                            [
                                                'type' => 'string',
                                            ],
                                        'disclosure_mode'     =>
                                            [
                                                'type' => 'string',
                                            ],
                                        'date_retrieval'      =>
                                            [
                                                'type'   => 'date',
                                                'format' => 'dateOptionalTime',
                                            ],
                                        'category'            =>
                                            [
                                                'type' => 'string',
                                            ],
                                        'translated_from'     =>
                                            [
                                                'properties' =>
                                                    [
                                                        'id'            =>
                                                            [
                                                                'type' => 'integer',
                                                            ],
                                                        'contract_name' =>
                                                            [
                                                                'type' => 'string',
                                                            ],
                                                    ],
                                            ],
                                    ],

                            ],
                        'file_size'            =>
                            [
                                'type' => 'long',
                            ],
                        'amla_url'             =>
                            [
                                'type' => 'string',
                            ],
                        'file_url'             =>
                            [
                                'type' => 'string',
                            ],
                        'word_file'            =>
                            [
                                'type' => 'string',
                            ],
                        'updated_user_name'    =>
                            [
                                'type' => 'string',
                            ],
                        'total_pages'          =>
                            [
                                'type' => 'integer',
                            ],
                        'updated_user_email'   =>
                            [
                                'type' => 'string',
                            ],
                        'created_user_name'    =>
                            [
                                'type' => 'string',
                            ],
                        'created_user_email'   =>
                            [
                                'type' => 'string',
                            ],
                        'supporting_contracts' =>
                            [
                                'properties' =>
                                    [
                                        'id'            =>
                                            [
                                                'type' => 'integer',
                                            ],
                                        'contract_name' =>
                                            [
                                                'type' => 'string',
                                            ],
                                    ],
                            ],
                        'created_at'           =>
                            [
                                'type'   => 'date',
                                'format' => 'dateOptionalTime',
                            ],
                        'updated_at'           =>
                            [
                                'type'   => 'date',
                                'format' => 'dateOptionalTime',
                            ],
                        'resource_raw'         =>
                            [
                                'type'  => 'string',
                                'index' => 'not_analyzed',
                            ],
                    ],
            ];

            $params['body'][$this->type] = $mapping;

            $meadata = $this->es->indices()->putMapping($params);;
            logger()->info("Metadata Mapping done", $meadata);

            return $meadata;
        } catch (\Exception $e) {
            logger()->error("Metadata Mapping Error", (array) $e->getMessage());

            return (array) $e->getMessage();
        }
    }

    /**
     * Create Master Mapping
     */
    private function createMasterMapping()
    {
        try {
            $params['index'] = $this->index;
            $this->es->indices()->refresh($params);
            $params['type'] = "master";
            $mapping        = [
                "properties" => [
                    "metadata"             => [
                        "properties" => [
                            "contract_name"      => [
                                "type" => "string"
                            ],
                            "country_name"       => [
                                "type" => "string"
                            ],
                            "country_code"       => [
                                "type" => "string"
                            ],
                            "signature_year"     => [
                                "type" => "string"
                            ],
                            "signature_date"     => [
                                'type'   => 'date',
                                'format' => 'dateOptionalTime',
                            ],
                            "resource"           => [
                                "type" => "string"
                            ],
                            "resource_raw"       => [
                                "type"  => "string",
                                'index' => 'not_analyzed'
                            ],
                            "file_size"          => [
                                "type" => "integer",
                            ],
                            "language"           => [
                                "type" => "string"
                            ],
                            "category"           => [
                                "type" => "string"
                            ],
                            "contract_type"      => [
                                "type"  => "string",
                                "index" => "not_analyzed"
                            ],
                            "company_name"       => [
                                "type"  => "string",
                                "index" => "not_analyzed"
                            ],
                            "corporate_grouping" => [
                                "type"  => "string",
                                "index" => "not_analyzed"
                            ]
                        ]
                    ],
                    "annotations_category" => [
                        "type"  => "string",
                        "index" => "not_analyzed"
                    ],
                    "metadata_string"      => [
                        "type" => "string"
                    ],
                    "pdf_text_string"      => [
                        "type" => "string"
                    ],
                    "annotations_string"   => [
                        "type" => "string"
                    ]
                ]
            ];

            $params['body']["master"] = $mapping;
            $masterIndex              = $this->es->indices()->putMapping($params);
            logger()->info("Master mapping created", $masterIndex);

            return $masterIndex;
        } catch (\Exception $e) {
            logger()->error("Master Index Erro", [$e->getMessage()]);

            return [$e->getMessage()];
        }
    }
}
