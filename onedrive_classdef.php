<?php

require_once "drive_client_classdef.php";


define('CLIENT_ID_OLD', '000000004018962B');
define('CLIENT_ID', 'ffd664c2-aba8-4284-ba2e-2300b5320387');
define('REDIRECT_URL', 'https://' . $_SERVER['HTTP_HOST'] . '/books/ms_onedrive.php');
define('CLIENT_SECRET_OLD', 'XZxVArudOBTAcEvWlO4zlE4bBXCkfm5P');
define('CLIENT_SECRET', 'mmtOWK94962~qephLTNW}~]');
define('MS_ONEDRIVE_', 'MSOD');

class MSOneDriveHelper extends Drive\Client
{
    const BASE_AUTH_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0';
    const AUTH_URL= self::BASE_AUTH_URL . '/authorize';
    const TOKEN_URL= self::BASE_AUTH_URL . '/token';
    const SCOPES = 'offline_access%20Directory.ReadWrite.All%20Files.ReadWrite';
    const API_URL='https://graph.microsoft.com/v1.0';

    public function __construct()
    {
        parent::__construct();
        \Logs\logDebug(var_export($this, true));
        $option=null;
    }

    public function getDriveVendorName()
    {
        return MS_ONEDRIVE_;
    }

    public function getRedirectUrl()
    {
        $login_url = self::AUTH_URL . '?client_id=' . CLIENT_ID . '&response_type=code&redirect_uri=' . urlencode(REDIRECT_URL) . '&response_mode=query&scope=' . self::SCOPES . '&state=311274';
        return $login_url;
    }
    
    public function isExpired()
    {
        try
        {
            if(!property_exists($this, 'token') || $this->token==null) { 
                throw new \Exception('token not found');
            }
            if(!property_exists($this->token, 'expires_in') || $this->token->expires_in==null) { 
                throw new \Exception('expires_in not found');
            }
            if(!property_exists($this->token, 'created') || $this->token->created==null) { 
                throw new \Exception('created not found');
            }
            return (($this->token->expires_in + $this->token->created)<=time());
        }
        catch(\Exception $e)
        {
            \Logs\logErr(var_export($this, true));
            \Logs\logWarning($e->getMessage());
        }
        return false;
    }
    protected function onCode($code)
    {
        $body = 'client_id='. CLIENT_ID . '&scope=' . self::SCOPES . '&code=' . $code . '&redirect_uri=' . urlencode(REDIRECT_URL) . '&grant_type=authorization_code&client_secret=' . CLIENT_SECRET;

        $options=array(
                     CURLOPT_HTTPHEADER => array(
                         'Content-Type: application/x-www-form-urlencoded'
                     )
                );

        $response = $this->curl_post(self::TOKEN_URL, $body, $options);

        if(!$response) {
            throw new Exception($response->error);
        }
        $response = json_decode($response);
        \Logs\logDebug(var_export($response, true));

        $this->set_token($response);
    }
    public function refreshToken()
    {
        if(property_exists($this, "token") && property_exists($this->token, "refresh_token") ) {

            $options=array( CURLOPT_HTTPHEADER => array( 'Content-Type: application/x-www-form-urlencoded') );
            $body = 'client_id=' . CLIENT_ID . '&redirect_uri=' . urlencode(REDIRECT_URL) . '&client_secret=' . CLIENT_SECRET .
                    '&refresh_token=' . $this->token->refresh_token . '&grant_type=refresh_token&scope=' . self::SCOPES;
            \Logs\logDebug($body);
            $response = $this->curl_post(self::TOKEN_URL, $body, $options);

            \Logs\logDebug(var_export($response, true));

            if(!$response || property_exists($response, "error")) {
                $this->set_token(null);
                throw new Exception($response->error);
            }
            \Logs\logDebug(var_export($response, true));
            $this->set_token($response);
        }
        else
        {
            $this->set_token(null);
            throw new \Exception('refresh token is null');
        }
    }

    public function uploadFile()
    {
        if($this->isLogged()) {
            $book_folder=$this->getBookFolder();
            $options = array(CURLOPT_CUSTOMREQUEST => $info['method'], CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $this->getAccessToken()));

            $info = $this->store_info();
            
            $url = str_replace(array('{fileid}','{parentid}'), array($fileid, $book_folder), $info['url']);
            $body=new CURLFile(realpath('temp/'.$this->getFileName()));

            $res = $this->curl_post($url,$body,$options);
            
            if($res) {
                $res = json_decode($this->curl_post($url,$body,$options));
                $this->register_link($res->id, $res->size);
            }
            else
            {
                throw new \Exception('Cannot upload file: ' . var_export($res, true));
            }
        }
        else
        {
            throw new \Exception('no token to file uploaded');
        }
    }
    public function deleteFile()
    {
        if($this->isLogged()) {
            $file_id=$this->getStoreFileId();
            if($file_id) {
                $info = $this->store_info()->urls['delete'];
                $options = array(CURLOPT_CUSTOMREQUEST => $info['method'], CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $this->getAccessToken()));
                $url = str_replace('{fileid}', $file_id, $info['url']);
                $this->curl_request($url,$options);
            }
            else
            {
                \Logs\logWarning('OndeDrive/deleteFile: file id not found');
            }
        }
    }
    public function downloadFile()
    {
        if($this->isLogged()) {
            $file_id=$this->getStoreFileId();
            if($file_id) {
                
                $info = $this->store_info()->urls['download'];
                
                $options = array(CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $this->getAccessToken()));
                $res = $this->curl_request(self::API_URL . '/me/drive/items/' . $file_id, $options);

                $name = '';
                if ($res)
                {
                    $name = json_decode($res)->name;
                }
                else
                {
                    throw new Exception("OneDrive/download: cannot get file name");
                }
                $options = array_merge($options, array(CURLOPT_CUSTOMREQUEST => $info['method'], CURLOPT_FOLLOWLOCATION => true));
                $url = str_replace('{fileid}', $file_id, $info['url']);

                $content = $this->curl_request($url,$options);

                $tmpfile = realpath('temp') . '/' . $name;
                $this->downloadToBrowser($tmpfile, $content);
            }
            else{
                throw new \Exception('OneDrive: cannot downloadFile');
            }
        }
    }
    public function store_info()
    {
        if($this->isLogged()) {
            $book_folder=$this->getBookFolder();
            $base_url='https://apis.live.net/v5.0';

            return (object) array(
                'access_token' => $this->getAccessToken(),
                'book_folder' => $book_folder,
                'urls' => array(
                    'download' => array( 'method' => 'GET', 'url' => self::API_URL . '/me/drive/items/{fileid}/content', 'headers' => (object)array('Authorization: Bearer {accesstoken}') ), 
                    'upload' => array(  'method' => 'PUT', 'url' => self::API_URL . '/me/drive/items/{parentid}:/{filename}:/content' , 'body' => '{filecontent}', 'headers' => (object)array('Authorization: Bearer {accesstoken}') ),
                    'delete' => array('method' => 'DELETE', 'url' => self::API_URL . '/me/drive/items/{fileid}', 'headers' => (object)array('Authorization: Bearer {accesstoken}') )
                    )
                );
        }
        else
        {
            throw new \Exception('not logged');
        }
    }
    public function downloadLink($fileid)
    {
        if($this->isLogged()) {
            $access_token = $this->getAccessToken();
            $info = $this->store_info()->urls['download'];
            return array( 'method' => $info['method'], 'url' => str_replace('{fileid}', $fileid, $info['url']),
                'headers' => array("Authorization: Bearer $access_token") );
        }
        else
        {
            throw new \Exception('not logged');
        }
    }
    private function getBookFolder()
    {
        $books_folder = null;
        
        try{
            $url = self::API_URL . '/me/drive/root:/Documents/Books';
            $options = array(
                CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->getAccessToken() )

            );
            $books_folder = json_decode($this->curl_request($url,$options));
            
        }catch (\Exception $e){
            //POST /me/drive/items/{parent-item-id}/children
            //POST /me/drive/items/{parent-item-id}/children
            $url = self::API_URL + "/me/drive/root:/Documents/";
            $response = json_decode($this->curl_request($url));

            $url = self::API_URL . '/me/drive/items/' . $response->id . '/children';
            $options=array( CURLOPT_HTTPHEADER => array( 'Content-Type: application/json', 'Authorization: Bearer ' . $this->getAccessToken() ) );
            /*
                {
                  "name": "New Folder",
                  "folder": { },
                  "@microsoft.graph.conflictBehavior": "rename"
                }
            */
            $body = json_encode(array('name' => 'Books', "folder" => object(), "@microsoft.graph.conflictBehavior" => "rename" ));
            $books_folder = $this->curl_post($url, $body, $options);
        }

        if ($books_folder && $books_folder->id) {
            \Logs\logDebug("OneDrive Book Id=" + strval($books_folder->id));
            return $books_folder->id;
        }
        else{
            throw new Exception("OneDrive API: Books folder not found");
            
        }
    }
}
    
?>