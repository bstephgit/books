<?php

include_once "drive_client.php";

define('BOX_COM_','BOX');
define('__REDIRECT_URI__','https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']);

class BoxDrive extends Drive\Client
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

    protected function getRedirectUrl()
    {
        $url= self::AUTH_URL . '?response_type=code' . '&' .
            'client_id=' . self::CLIENT_ID . '&' .
            'redirect_uri=' . self::REDIRECT_URI . '&' .
            'state=' . urlencode($this->getFileName()) .  '&' .
            'box_login=' . urlencode('tcn75323@gmail.com');
        return $url;
    }
    protected function isExpired()
    {
        $this->expires_in >= time();
    }
    protected function onCode($code)
    {
        $body='grant_type=authorization_code' . '&' .
                  'code=' . $code . '&' .
                  'client_id=' . self::CLIENT_ID . '&' .
                  'client_secret=' . self::CLIENT_SECRET . '&' .
                  'redirect_uri=' . self::REDIRECT_URI;

        $response=json_decode($this->curl_post(self::TOKEN_URL,$body,$options));

        if($response->error)
        {
            throw new Exception($response->error);
        }

        $this->retrieve_parameters($response);
    }
    protected function refreshToken()
    {
        if($this->refresh_token)
        {
            $body = 'grant_type=refresh_token' . '&' .
                'refresh_token=' . urlencode($this->refresh_token) . '&' .
                'client_id=' . urlencode(self::CLIENT_ID) . '&' .
                'client_secret=' . urlencode(self::CLIENT_SECRET);

            $response=json_decode($this->curl_post(self::TOKEN_URL,$body));
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

    public function deleteFile()
    {
        if($this->isLogged())
        {
            $file_id=$this->getStoreFileId();
            if($file_id)
            {
                $url=self::API_URL . '/files/' . $file_id;
                $options=array(
                    CURLOPT_CUSTOMREQUEST => 'DELETE',

                    CURLOPT_HTTPHEADER => array(
                        'Authorization: Bearer ' . $this->access_token
                    )
                );
                $response=$this->curl_request($url,$options);
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
                $options=array(
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTPHEADER => array(
                        'Authorization: Bearer ' . $this->access_token
                    )
                );
                $url= self::API_URL . sprintf('/files/%s',$fileid);
                $file_info = json_decode($this->curl_request($url,$options));

                $url = self::API_URL . sprintf('/files/%s/content',$fileid);
                $opt_output = array(
                    CURLOPT_FILE => 'temp/' . $file_info->name
                );
                $this->curl_request($url, array_merge($options,$opt_output));
            }
        }
    }
    public function uploadFile() 
    {
        if($this->isLogged())
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
                     CURLOPT_HTTPHEADER => array(
                         'Authorization: Bearer ' . $this->access_token,
                         'Content-Type: multipart/form-data'
                     )
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
                //$download_url = self::API_URL . '/files/' . $response->entries[0]->id . '/content';
                $this->register_link($response->entries[0]->id, $response->entries[0]->size);
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
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->access_token )
            
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
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->access_token ),
        );
        $response=$this->curl_post($url,$body,$options);
        $response=json_decode($response);
        return $response;
    }
}

$box_helper= new BoxDrive();
$box_helper->execute();
    

?>