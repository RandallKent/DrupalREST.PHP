<?php

/**
 * Created by JetBrains PhpStorm.
 * Branch project DrupalREST.php | https://github.com/RandallKent/DrupalREST.PHP
 * Support CRUD Entity File, Node, entity_node, taxonomy_term | Token CSRF
 * Require module services | https://drupal.org/project/services - services_entity | https://drupal.org/project/services_entity
 * User: jatorres | https://github.com/jatorres
 * Date: 7/10/13
 * Update: 26/04/2014
 */
class DrupalREST
{
    public $username;
    public $password;
    public $session;
    public $user_drupal;
    public $endpoint;
    public $debug;

    function __construct($domain, $endpoint, $username, $password, $debug)
    {
        $this->username = $username;
        $this->password = $password;
        //TODO: Check for trailing slash and fix if needed
        $this->domain = $domain;
        $this->endpoint = $endpoint;
        $this->debug = $debug;
    }

    // Authentication drupal
    function login()
    {
        $user_data = array(
            'username' => $this->username,
            'password' => $this->password,
        );

        $user = http_build_query($user_data, '', '&');

        $ch = curl_init($this->domain . '/' . $this->endpoint . '/user/login.json');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Accept: application/json",
            "Content-type: application/x-www-form-urlencoded"
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE); // Ask to not return Header
        curl_setopt($ch, CURLOPT_POST, 1); // Do a regular HTTP POST
        curl_setopt($ch, CURLOPT_POSTFIELDS, $user);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Check if login was successfully
        if ($http_code == 200) {
            // Convert json
            $logged_user = json_decode($response);
            $this->user_drupal = $logged_user->user;

        } else {
            // Get error msg
            $http_message = curl_error($ch);
            die('Auth error ' . $http_message);
        }

        //Save Session information to be sent as cookie with future calls
        $this->session = $logged_user->session_name . '=' . $logged_user->sessid;

        // GET CSRF Token
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $this->domain . '/services/session/token',
        ));

        curl_setopt($ch, CURLOPT_COOKIE, "$this->session");

        $ret = new stdClass;

        $ret->response = curl_exec($ch);
        $ret->error = curl_error($ch);
        $ret->info = curl_getinfo($ch);

        $this->csrf_token = $ret->response;
    }

    // Retrieve a entity from a entity id
    public function retrieveEntity($id, $entity = 'node')
    {
        //Cast Entity id as integer
        $id = (int)$id;

        $ch = curl_init($this->domain . '/' . $this->endpoint . '/' . $entity . '/' . $id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Accept: application/json",
            "Cookie: $this->session"
        ));

        $result = $this->_handleResponse($ch);
        curl_close($ch);

        return $result;
    }

    // Create a entity
    public function createEntity($fields_entity, $entity = 'node')
    {
        $post = http_build_query($fields_entity, '', '&');

        $ch = curl_init($this->domain . '/' . $this->endpoint . '/' . $entity . '/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Accept: application/json",
            "Content-type: application/x-www-form-urlencoded",
            "Cookie: $this->session",
            "X-CSRF-Token: $this->csrf_token"
        ));

        $result = $this->_handleResponse($ch);
        curl_close($ch);

        return $result;
    }

    // Update a entity
    public function updateEntity($id, $fields_entity, $entity = 'node')
    {
        $id = (int)$id;
        $put = http_build_query($fields_entity, '', '&');

        $ch = curl_init($this->domain . '/' . $this->endpoint . '/' . $entity . '/' . $id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $put);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Accept: application/json",
            "Content-type: application/x-www-form-urlencoded",
            "Cookie: $this->session",
            'X-CSRF-Token: ' . $this->csrf_token
        ));

        $result = $this->_handleResponse($ch);
        curl_close($ch);

        return $result;
    }

    // Delete a entity
    public function deleteEntity($id, $entity = 'node')
    {
        $id = (int)$id;

        $ch = curl_init($this->domain . '/' . $this->endpoint . '/' . $entity . '/' . $id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Accept: application/json",
            "Cookie: $this->session",
            "X-CSRF-Token: $this->csrf_token"
        ));

        $result = $this->_handleResponse($ch);
        curl_close($ch);

        return $result;
    }

    //Search a Entity
    public function searchResource($entity, $params, $fields_result = NULL)
    {
        $params_url = http_build_query($params);

        $ch = curl_init($this->domain . '/' . $this->endpoint . '/' . $entity . '/?' . $params_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Accept: application/json",
            "Cookie: $this->session"
        ));

        $result = $this->_handleResponse($ch);
        curl_close($ch);

        if (is_object($result) && $result->ErrorCode === NULL) {
            if ($fields_result && count($fields_result) > 0) {

                $fields = array();
                foreach ($fields_result as $value) {
                    if (isset($result->Data[$value])) {
                        $fields[$value] = $result->Data[$value];
                    }
                }

                $result = $fields;
            } else {
                $result = $result->Data;
            }

        } elseif (is_object($result) && $result->ErrorCode !== NULL && $fields_result && $this->debug) {
            $result = $result->body;
        }

        return $result;
    }

    // Private Helper Functions
    private function _handleResponse($ch)
    {
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);

        //break apart header & body
        $header = substr($response, 0, $info['header_size']);
        $body = substr($response, $info['header_size']);

        $result = new stdClass();
        $node = new stdClass();

        if ($info['http_code'] != '200') {
            $header_arrray = explode("\n", $header);
            $result->ErrorCode = $info['http_code'];
            $result->ErrorText = $header_arrray['0'];

        } else {
            $result->ErrorCode = NULL;
            $decodedBody = json_decode($body, true);

            $node->Data = (isset($decodedBody[0]) ? $decodedBody[0] : $decodedBody);
            $result = (object)array_merge((array)$result, (array)$node);
        }

        if ($this->debug) {
            $result->header = $header;
            $result->body = $body;
        }

        return $result;
    }
}