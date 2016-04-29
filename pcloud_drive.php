<?php

include_once "drive_client.php";

define('PCLOUD_','PCLD');
define('__REDIRECT_URI__','https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']);

function HandleHeaderLine($curl, $header_line)
{
    echo "<br>YEAH: ".$header_line; // or do whatever
    return strlen($header_line);
}

class PCloudDrive extends Drive\Client
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

    protected function getRedirectUrl()
    {
        $url= self::AUTH_URL . '?response_type=code' . '&' .
            'client_id=' . self::CLIENT_ID . '&' .
            'redirect_uri=' . self::REDIRECT_URI . '&' .
            'state=' . urlencode($this->getFileName());

        return $url;
    }
    protected function isExpired()
    {
        return false;
    }
    protected function onCode($code)
    {
        $body='getauth=1&code=' . $code . '&' .
            'client_id=' . self::CLIENT_ID . '&' .
            'client_secret=' . self::CLIENT_SECRET;

        $response=$this->curl_post(self::TOKEN_URL,$body);
        $response=json_decode($response);
        if($response->error)
        {
            throw new Exception($response->error);
        }
        if($response->access_token)
        {
            $this->access_token = $response->access_token;
            $login = (object)array('access_token' => $response->access_token, 'refresh_token' => 'N/A' , 'expires_in' => time() + 2678400 );
            $this->retrieve_parameters($login);
        }
        else
        {
            throw new Exception('access_token not found');
        }
    }
    protected function refreshToken(){}

    public function uploadFile() 
    {
        if($this->isLogged())
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
                     CURLOPT_HTTPHEADER => array(
                         'Authorization: Bearer ' . $this->access_token,
                         'Content-Type: multipart/form-data'
                     ),
                 );
                $response=$this->curl_post($url,$body,$options);

                if($response==null)
                {
                    throw new Exception('response is null');
                }

                $response=json_decode($response);

                if($response->error)
                {
                    throw new Exception($response->error);
                }

                $this->register_link($response->metadata[0]->fileid, $response->metadata[0]->size);
            }
            else
            {
                throw new Exception("invalid 'Books' folder", 1);
            }
        }
    }
    public function deleteFile()
    {
        if($this->isLogged())
        {
            $file_id=$this->getStoreFileid();
            if($file_id)
            {
                $url= self::API_URL . '/deletefile?fileid=' . $file_id;
                $options=array(
                        CURLOPT_HTTPHEADER => array(
                            'Authorization: Bearer ' . $this->access_token
                        )
                    );
                $response=json_decode($this->curl_request($url,$options));
            }
        }
    }
    public function downloadFile()
    {
        if($this->isLogged())
        {
            $fileid=$this->getStoreFileId();
            if($fileid)
            {
                $url = self::API_URL . '/getfilelink?fileid=' . $fileid . '&' . 'forcedownload=1';

                $options=array(
                       CURLOPT_HTTPHEADER => array(
                           'Authorization: Bearer ' . $this->access_token
                       )
                   );
                
                $download_url=json_decode($this->curl_request($url,$options));
                $download_url= 'https://' . $download_url->hosts[0] . $download_url->path;

                $metadata_url = self::API_URL . '/checksumfile?fileid=' . $fileid;
                $metadata = json_decode($this->curl_request($metadata_url,$options));

                $tmpfile = realpath('temp') . '/' . $metadata->metadata->name;
                $content=$this->curl_request($download_url, $options);
                $this->downloadToBrowser($tmpfile,$content);
            }
        }
    }
    private function getBookFolder()
    {
        $url = self::API_URL . '/listfolder?path=' . urlencode('/') . '&' . 'recursive=0' . '&' . 'nofiles=1';
        $options = array(
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->access_token )
            
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
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->access_token ),
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


$pcl_helper = new PCloudDrive();
$pcl_helper->execute();


?>