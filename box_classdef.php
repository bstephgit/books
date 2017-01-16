<?php

include_once "drive_client_classdef.php";

define('BOX_COM_','BOX');
define('__REDIRECT_URI__','https://' . $_SERVER['HTTP_HOST'] . '/books/box_drive.php');

class BoxDrive extends Drive\Client
{
    const CLIENT_ID="2en9g8pt7jgu5kgvyss7qbrxgk783212";
	const CLIENT_SECRET="t0nY1UF8AkmKZZp7qPEHWU8i2OG2pZwD";
	const REDIRECT_URI=__REDIRECT_URI__;

	const AUTH_URL = "https://account.box.com/api/oauth2/authorize";
	const TOKEN_URL = "https://api.box.com/oauth2/token";
    const API_URL = "https://api.box.com/2.0";
    const UPLOAD_URL = "https://upload.box.com/api/2.0/files/content";

    public function __construct()
    {
        parent::__construct();
    }

    public function getDriveVendorName()
    {
        return BOX_COM_;
    }

    public function getRedirectUrl()
    {
        $url= self::AUTH_URL  . '?response_type=code' . '&' .
            'client_id=' . self::CLIENT_ID . '&' .
            'redirect_uri=' . self::REDIRECT_URI . '&' .
						'state=xyz'   . '&' .
            'box_login=' . urlencode('tcn75323@gmail.com');
						\Logs\logDebug($url);
        return $url;
    }
    public function isExpired()
    {
        try
        {
            if(!property_exists($this,'token') || $this->token==NULL) throw new \Exception('token not found');
            if(!property_exists($this->token,'expires_in') || $this->token->expires_in==NULL) throw new \Exception('expires_in not found');
            if(!property_exists($this->token,'created') || $this->token->created==NULL) throw new \Exception('created not found');
            return (($this->token->expires_in + $this->token->created)<=time());
        }
        catch(\Exception $e)
        {
						\Logs\logErr(var_export($this,true));
            \Logs\logWarning($e->getMessage());
        }
        return false;
    }
    protected function onCode($code)
    {
        $body='grant_type=authorization_code' . '&' .
                  'code=' . $code . '&' .
                  'client_id=' . self::CLIENT_ID . '&' .
                  'client_secret=' . self::CLIENT_SECRET . '&' .
                  'redirect_uri=' . self::REDIRECT_URI;

        $response=json_decode($this->curl_post(self::TOKEN_URL,$body,$options));
				\Logs\logDebug($body);
        if($response->error)
        {
            throw new Exception($response->error);
        }
        \Logs\logDebug(var_export($response,true));

        $this->set_token($response);
    }
    public function refreshToken()
    {
        if($this->token->refresh_token)
        {
            $body = 'grant_type=refresh_token' . '&' .
                'refresh_token=' . urlencode($this->token->refresh_token) . '&' .
                'client_id=' . urlencode(self::CLIENT_ID) . '&' .
                'client_secret=' . urlencode(self::CLIENT_SECRET);
						\Logs\logDebug($body);
            $response=json_decode($this->curl_post(self::TOKEN_URL,$body));
            if($response->error)
            {
                throw new Exception($response->error);
            }
            \Logs\logDebug(var_export($response,true));
            $this->set_token($response);
        }
        else
        {
            $this->set_token(null);
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
                        'Authorization: Bearer ' . $this->getAccessToken()
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
                        'Authorization: Bearer ' . $this->getAccessToken()
                    )
                );
                $url= self::API_URL . sprintf('/files/%s',$fileid);
                $file_info = json_decode($this->curl_request($url,$options));

                $tmpfile = realpath('temp') . '/' . $file_info->name;
                $url = self::API_URL . sprintf('/files/%s/content',$fileid);

                $content=$this->curl_request($url, $options);
                $this->downloadToBrowser($tmpfile,$content);
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
                $url= self::UPLOAD_URL;

                $attributes= array( "name" => $this->getFileName(), "parent" => array("id" => $book_folder->id));

                $the_file= new CURLFile(realpath('temp/'.$this->getFileName()));

                $body=array(
                   'attributes' => json_encode($attributes),
                   'file' => $the_file
               );

                $options=array(
                     CURLOPT_HTTPHEADER => array(
                         'Authorization: Bearer ' . $this->getAccessToken(),
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

    public function store_info()
    {
        if($this->isLogged())
        {
            $book_folder=$this->getBookFolder();
            return (object) array (
                'access_token' => $this->getAccessToken(),
                'book_folder' => $book_folder->id ,
                'urls' => (object)array('download' => (object)array( 'method' => 'GET', 'url' => self::API_URL.'/files/{fileid}/content' ),
                                'upload' => (object)array( 'method' => 'POST', 'url' => self::UPLOAD_URL ,
                                                    'headers' => (object)array('Authorization: Bearer {accesstoken}'),
                                                    'body' => (object)array( 
																																'attributes' => (object)array('name'=>'{filename}', 'parent'=> (object)array('id'=>'{parentid}')), 
																																'file' => '{filecontent}' ) ),
                                'delete' => (object)array('method' => 'DELETE', 'url' => self::API_URL.'/files/{fileid}')
                 ) );
        }
        throw new \Exception('not logged');
    }
		public function downloadLink($fileid)
		{
			if($this->isLogged())
			{
				$root_url=self::API_URL;
				$access_token=$this->getAccessToken();
				return array( 'method' => 'GET', 'url' =>  sprintf("%s/files/%s/content",$root_url,$fileid), 'headers' => array("Authorization: Bearer $access_token"));
			}
			else
			{
					throw new \Exception('not logged');
			}
		}
    private function getBookFolder()
    {
        $url = self::API_URL . '/folders/0/items';
        $options = array(
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->getAccessToken() )

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
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->getAccessToken() ),
        );
        $response=$this->curl_post($url,$body,$options);
        $response=json_decode($response);
        return $response;
    }
	  public function useDownloadProxy() { return true; }
}

 ?>