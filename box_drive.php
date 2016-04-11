<?php

include "drive_client.php";

define('BOX_COM_','BOX');
define('__REDIRECT_URI__','https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']);

class BoxDrive extends DriveClient
{
    const CLIENT_ID="2en9g8pt7jgu5kgvyss7qbrxgk783212";
	const CLIENT_SECRET="t0nY1UF8AkmKZZp7qPEHWU8i2OG2pZwD";
	const REDIRECT_URI=__REDIRECT_URI__;

	const AUTH_URL = "https://account.box.com/api/oauth2/authorize";
	const TOKEN_URL = "https://api.box.com/oauth2/token";
    const API_URL = "https://api.box.com/2.0";

    public function __construct()
    {
        parent::__construct();
    }
    
    public function getDriveVendorName()
    {
        return BOX_COM_;
    }
    
    public function login() 
    {
        if($this->getSessionVar('access_token'))
        {
            $expire_time=$this->getSessionVar('expires_in');
            if($expire_time < time())
            {
                if($this->getSessionVar('refresh_token'))
                {
                    $body = 'grant_type=refresh_token' . '&' . 
                        'refresh_token=' . urlencode($this->getSessionVar('refresh_token')) . '&' .
                        'client_id=' . urlencode(self::CLIENT_ID) . '&' .
                        'client_secret=' . urlencode(self::CLIENT_SECRET);
                        $options=array(
                            CURLOPT_POST       => true,
                            CURLOPT_POSTFIELDS => $body
                        );
                        $response=$this->curl_request(self::TOKEN_URL,$options);
                        $response=json_decode($response);
                        var_dump($response);
                        if($response->error)
                        {
                            throw new Exception($response->error);
                        }
                        $this->retrieve_parameters($response);
                }
                else
                {
                    throw new Exception('Refresh token not found');
                }
            }
        }
        else if(isset($_GET['code']))
        {
            $body='grant_type=authorization_code' . '&' .
                  'code=' . $_GET['code'] . '&' .
                  'client_id=' . self::CLIENT_ID . '&' .
                  'client_secret=' . self::CLIENT_SECRET . '&' .
                  'redirect_uri=' . self::REDIRECT_URI;
             $options=array(
                    CURLOPT_POST       => true,
                    CURLOPT_POSTFIELDS => $body
                );
            $response=$this->curl_request(self::TOKEN_URL,$options);
            $response=json_decode($response);
			if($response->error)
			{
				throw new Exception($response->error);
			}
            $this->retrieve_parameters($response);
        }
        else if(isset($_GET['error']))
        {
            $err_msg=$_GET['error'];
            if(isset($_GET['error_description']))
            {
                $err_msg += ': ' . $_GET['error_description'];
            }
            throw new Exception($err_msg);
        }
        else
        {
            $url= self::AUTH_URL . '?response_type=code' . '&' .
            'client_id=' . self::CLIENT_ID . '&' .
            'redirect_uri=' . self::REDIRECT_URI . '&' .
            'state=' . urlencode($this->getFileName()) .  '&' .
            'box_login=' . urlencode('tcn75323@gmail.com');
            header('Location: ' . $url);
        }
    }
    public function uploadFile() 
    {
        if($this->getSessionVar('access_token'))
        {
            $book_folder=$this->getBookFolder();
            if($book_folder->id)
            {
                $url= 'https://upload.box.com/api/2.0/files/content';
                
                $attributes= array( "name" => $this->getFileName(), "parent" => array("id" => $book_folder->id));
                $the_file= new CURLFile(realpath('temp/'.$this->getFileName()));
                
                $body=array(
                   'attributes' => json_encode($attributes),
                   'file' => $the_file
               );
                
                $options=array(
                     CURLOPT_POST       => true,

                     CURLOPT_HTTPHEADER => array(
                         'Authorization: Bearer ' . $this->getSessionVar('access_token'),
                         'Content-Type: multipart/form-data'
                     ),

                     CURLOPT_POSTFIELDS => $body
                 );
                $response=$this->curl_request($url,$options);
                if($response==null)
                {
                    throw new Exception('response is null');
                }
                $response=json_decode($response);
                if($response->error)
                {
                    throw new Exception($response->error);
                }
                $download_url = self::API_URL . '/files/' . $response->entries[0]->id . '/content';
                $this->register_link($download_url, $response->entries[0]->size);
            }
            else{
                throw new Exception("invalid 'Books' folder", 1);
            }
        }
    }
    
    private function getBookFolder()
    {
        $url = self::API_URL . '/folders/0/items';
        $options = array(
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->getSessionVar('access_token') )
            
        );
        $response=$this->curl_request($url,$options);
        $response=json_decode($response);
        foreach($response->entries as $item)
        {
            if($item->name==='Books' && $item->type==='folder')
            {
                return $item;
            }
        }
        $url = self::API_URL . '/folders';
        $body = json_encode( array( 'name' => 'Books', "parent" => array("id" => "0") ) );
        
        $options = array(
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->getSessionVar('access_token') ),
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => $body
        );
        $response=$this->curl_request($url,$options);
        $response=json_decode($response);
        return $response;
    }
}

if(!isset($_SESSION[BOX_COM_]) || !is_array($_SESSION[BOX_COM_]))
{
    $_SESSION[BOX_COM_]= array(
        'access_token' => null,
        'refresh_token' => null,
        'expires_in' => 0
    );
}
try
{
    $box_helper= new BoxDrive();
    $box_helper->login();
    $box_helper->uploadFile();   
}
catch(Exception $e)
{
    unset($_SESSION[BOX_COM_]);
    echo $e;
    http_response_code(500);
}

?>