<?php
class DrupalREST {
    var $username;
    var $password;
    var $session;
    var $endpoint;
    var $debug;

    function __construct($endpoint, $username, $password, $debug)
    {
        $this->username = $username;
        $this->password = $password;
        //TODO: Check for trailing slash and fix if needed
        $this->endpoint = $endpoint;
        $this->debug = $debug;
    }

    function login()
    {
       $ch = curl_init($this->endpoint . 'user/login/');
       $post_data = array(
                                'username' => $this->username,
                                'password' => $this->password,
                                );
       $post = http_build_query($post_data, '', '&');
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
       curl_setopt($ch, CURLOPT_HEADER, false);
       curl_setopt($ch, CURLOPT_POST, true);
       curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
       curl_setopt($ch, CURLOPT_HTTPHEADER,array (
            "Accept: application/json",
            "Content-type: application/x-www-form-urlencoded"
                ));
       $response = json_decode(curl_exec($ch));

       curl_close($ch);

       //Save Session information to be sent as cookie with future calls
       $this->session = $response->session_name . '=' . $response->sessid;
    }

    // Retrieve a node from a node id
    function retrieveNode($nid)
    {
       //Cast node id as integer
       $nid = (int) $nid;
       $ch = curl_init($this->endpoint . 'node/' . $nid .'/');
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
       curl_setopt($ch, CURLOPT_HEADER, TRUE);
       curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
       curl_setopt($ch, CURLOPT_HTTPHEADER,array (
                                                    "Accept: application/json",
                                                    "Cookie: $this->session"
                                                    ));
       $result = $this->_handleResponse($ch);

       curl_close($ch);

       return $result;
    }

    function createNode($node)
    {
        $post = http_build_query($node, '', '&');
        $ch = curl_init($this->endpoint . 'node/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
                array (
                       "Accept: application/json",
                       "Content-type: application/x-www-form-urlencoded",
                       "Cookie: $this->session"
                ));

       $result = $this->_handleResponse($ch);

       curl_close($ch);

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

       if ($info['http_code'] != '200')
       {
           $header_arrray = explode("\n",$header);
           $result->ErrorCode = $info['http_code'];
           $result->ErrorText = $header_arrray['0'];
       } else {
           $result->ErrorCode = NULL;
           $decodedBody= json_decode($body);
           $result = (object) array_merge((array) $result, (array) $decodedBody );
       }

       if ($this->debug)
       {
           $result->header = $header;
           $result->body = $body;
       }
       
       return $result;
    }
}

?>
