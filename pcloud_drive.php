<?php

include "drive_client.php";

define('PCLOUD_','PCLD');
define('__REDIRECT_URI__','https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']);

class PCloudDrive extends DriveClient
{
    const CLIENT_ID="3IPgVggnGsm";
	const CLIENT_SECRET="f4dAROwCG2mIAwllDuevJBCRlUgy";
	const REDIRECT_URI=__REDIRECT_URI__;

	const AUTH_URL = "https://my.pcloud.com/oauth2/authorize";
	const TOKEN_URL = "https://api.pcloud.com/oauth2_token";
    const API_URL = "https://api.pcloud.com";

    public function __construct()
    {
        parent::__construct();
    }
    
    public function getDriveVendorName()
    {
        return PCLOUD_;
    }
    
    public function login() 
    {
        if($this->getSessionVar('access_token'))
        {
            return;
        }
        else if(isset($_GET['code']))
        {
            $body='code=' . $_GET['code'] . '&' .
                  'client_id=' . self::CLIENT_ID . '&' .
                  'client_secret=' . self::CLIENT_SECRET;
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
            if($response->access_token)
            {
                $this->setSessionVar('access_token',$response->access_token);
            }
            else
            {
                throw new Exception('access_token not found');
            }
        }
        else if(isset($_GET['error']))
        {
            throw new Exception($_GET['error']);
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
            if($book_folder->folderid)
            {
                $url= self::API_URL . '/uploadfile';

                $the_file= new CURLFile(realpath('temp/'.$this->getFileName()));
                
                $body=array(
                   'filename' => $this->getFileName(),
                   'folderid' => $book_folder->folderid,
                   'nopartial' => 1,
                   'data' => $the_file
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
                var_dump($response);
                if($response->error)
                {
                    throw new Exception($response->error);
                }
                $url = self::API_URL . '/getfilelink?fileid=' . $response->metadata[0]->fileid . '&' . 'forcedownload=1';
                $download_url=json_decode($this->curl_request($url,$options));
                $download_url= 'https://' . $download_url->hosts[0] . $download_url->path;
                $this->register_link($download_url, $response->metadata[0]->size);
            }
            else{
                throw new Exception("invalid 'Books' folder", 1);
            }
        }
    }
    
    private function getBookFolder()
    {
        $url = self::API_URL . '/listfolder?path=' . urlencode('/') . '&' . 'recursive=0' . '&' . 'nofiles=1';
        $options = array(
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->getSessionVar('access_token') )
            
        );
        $response=$this->curl_request($url,$options);
        $response=json_decode($response);
        if($response->error)
        {
            throw new Exception('searching \'Books\' folder error: ' . $response->error);
        }
        foreach($response->metadata->contents as $item)
        {
            if($item->name==='Books' && $item->isfolder)
            {
                return $item;
            }
        }
        $url = self::API_URL . '/createfolder?folderid=0' . '&' . 'name=Books';
        
        $options = array(
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->getSessionVar('access_token') ),
        );
        $response=$this->curl_request($url,$options);
        $response=json_decode($response);
        if($response->error)
        {
            throw new Exception('creating \'Books\' folder error: ' . $response->error);
        }
        return $response->metadata;
    }
}

if(!isset($_SESSION[PCLOUD_]) || !is_array($_SESSION[PCLOUD_]))
{
    $_SESSION[PCLOUD_]= array(
        'access_token' => null,
    );
}
try
{
    $pcl_helper= new PCloudDrive();
    $pcl_helper->login();
    $pcl_helper->uploadFile();   
}
catch(Exception $e)
{
    unset($_SESSION[PCLOUD_]);
    echo $e;
    http_response_code(500);
}

?>