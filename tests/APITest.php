<?php

class APITest extends TestCase
{

    public function setUp()
    {
        $client          = $this->getClient();
        $params['index'] = "test_nrgi";
        $client->indices()->create($params);
    }

    public function tearDown()
    {
        $client          = $this->getClient();
        $params['index'] = "test_nrgi";
        $client->indices()->delete($params);
    }

    public function testInsertDocument()
    {
        $data           = [
            'metadata'           => json_decode('{"contract_name":"sdfsdfsdf","contract_identifier":"","language":"EN","country":{"code":"AL","name":"Albania"},"government_entity":"","government_identifier":"","type_of_contract":"","signature_date":"2015-06-23","document_type":"Contract","translation_parent":"","company":[{"name":"","jurisdiction_of_incorporation":"","registration_agency":"","company_foundin g_date":"","company_address":"","comp_id":"","parent_company":"","open_corporate_id":""}],"license_name":"","license_identifier":"","project_title":"","project_identifier":"","Source_url":"","date_retrieval":"","signature_year":"2015","resource":[],"category":[],"file_size":54836}'),
            'updated_user_name'  => "admin",
            'updated_user_email' => "admin@nrgi.com",
            'created_user_name'  => "admin",
            'created_user_email' => "admin@nrgi.app",
            'created_at'         => "2015-06-19T04:26:24",
            'updated_at'         => "2016-06-20T04:26:24"
        ];
        $param['index'] = "test_nrgi";
        $param['type']  = "test_metadata";
        $param['id']    = 1;
        $param['body']  = $data;
        $client         = $this->getClient();
        $response       = $client->index($param);
        $this->assertTrue($response['created']);
    }

    public function testDeleteDocument()
    {
        $data           = [
            'metadata'           => json_decode('{"contract_name":"sdfsdfsdf","contract_identifier":"","language":"EN","country":{"code":"AL","name":"Albania"},"government_entity":"","government_identifier":"","type_of_contract":"","signature_date":"2015-06-23","document_type":"Contract","translation_parent":"","company":[{"name":"","jurisdiction_of_incorporation":"","registration_agency":"","company_foundin g_date":"","company_address":"","comp_id":"","parent_company":"","open_corporate_id":""}],"license_name":"","license_identifier":"","project_title":"","project_identifier":"","Source_url":"","date_retrieval":"","signature_year":"2015","resource":[],"category":[],"file_size":54836}'),
            'updated_user_name'  => "admin",
            'updated_user_email' => "admin@nrgi.com",
            'created_user_name'  => "admin",
            'created_user_email' => "admin@nrgi.app",
            'created_at'         => "2015-06-19T04:26:24",
            'updated_at'         => "2016-06-20T04:26:24"
        ];
        $param['index'] = "test_nrgi";
        $param['type']  = "test_metadata";
        $param['id']    = 2;
        $param['body']  = $data;
        $client         = $this->getClient();
        $client->index($param);
        $response = $client->delete(['index' => "test_nrgi", "type" => "test_metadata", "id" => 2]);
        $this->assertTrue($response['found']);
    }

    public function testUpdateDocument()
    {
        $data           = [
            'metadata'           => json_decode('{"contract_name":"sdfsdfsdf","contract_identifier":"","language":"EN","country":{"code":"AL","name":"Albania"},"government_entity":"","government_identifier":"","type_of_contract":"","signature_date":"2015-06-23","document_type":"Contract","translation_parent":"","company":[{"name":"","jurisdiction_of_incorporation":"","registration_agency":"","company_foundin g_date":"","company_address":"","comp_id":"","parent_company":"","open_corporate_id":""}],"license_name":"","license_identifier":"","project_title":"","project_identifier":"","Source_url":"","date_retrieval":"","signature_year":"2015","resource":[],"category":[],"file_size":54836}'),
            'updated_user_name'  => "admin",
            'updated_user_email' => "admin@nrgi.com",
            'created_user_name'  => "admin",
            'created_user_email' => "admin@nrgi.app",
            'created_at'         => "2015-06-19T04:26:24",
            'updated_at'         => "2016-06-20T04:26:24"
        ];
        $param['index'] = "test_nrgi";
        $param['type']  = "test_metadata";
        $param['id']    = 3;
        $param['body']  = $data;

        $client = $this->getClient();
        $client->index($param);
        unset($param['body']);
        $param['body']['doc'] = ['created_user_name' => "deepak"];
        $client->update($param);
        $data = $client->get(['index' => "test_nrgi", 'type' => "test_metadata", 'id' => 3]);
        $this->assertEquals($data['_source']['created_user_name'], "deepak");
    }
}