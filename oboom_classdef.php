<?php

include_once "drive_client_classdef.php";

define('OBOOM_','OBM');
//define('__REDIRECT_URI__','https://' . $_SERVER['HTTP_HOST'] . '/books/oboom_drive.php');

class OboomDrive extends Drive\Client
{
    const API_KEY='d11cb6219112821d4a28e2c3406ed55eee4663dd';

    const VERSION = '1';
    const ROOT_ID = '1';
	const AUTH_URL = 'https://www.oboom.com/' . self::VERSION . '/login';
    const API_URL = "https://api.oboom.com";
    const UPLOAD_URL = 'https://upload.oboom.com/' . self::VERSION . '/ul';

    public function __construct()
    {
        parent::__construct();
        if ($this->getAccessToken()===null)
        {
            login:

            $mypassword = 'Motdepasse21!';
            $mysalt = strrev($mypassword);

            $hash = hash_pbkdf2('sha1', $mypassword, $mysalt, 1000, 32);

            $auth = 'tcn753@yahoo.fr';

            /*

            { "auth" : "tcn753@yahoo.fr", "pass": "8c0185718926eb08aaf3d34ad3110239", "api_key": "d11cb6219112821d4a28e2c3406ed55eee4663dd", "source": "bookstore"}

            '{"cookie":"917201:682a3c34175e4becaef313bff38521c903f47a50","user":{"id":"917201","name":null,"email":"tcn753@yahoo.fr","api_key":"d11cb6219112821d4a28e2c3406ed55eee4663dd","premium":null,"premium_unix":null,"webspace":53687091200,"traffic":{"current":1099511627776,"increase":0,"last":1555902914,"max":0},"balance":0,"settings":{},"external_id":"null","ftp_username":"","partner":"0","partner_last":0,"partner_fixed":0,"allow_dupe_names":1},"session":"9496b64b-5502-46da-bd71-95369f7fab13"}'
            */
            
            $body = (object) array( 'auth' => $auth, 'pass' => $hash, 'api_key' => self::API_KEY, 'source' => 'bookstore');

            $options = array( CURLOPT_HTTPHEADER => array(
                            'Content-Type:  application/json'
                        ) );

            $response=json_decode($this->curl_post(self::AUTH_URL,json_encode($body),$options));
            if (intval($response[0])===200)
            {
                $this->set_token ( (object) array ( 'access_token' => $response[1]->session ) );
                \Logs\logDebug('Login success, token initialized: ' . var_export($this->token,true));
            }
            else
            {
                throw new \Exception('Login error: ' . var_export($response,true));
            }
        }
        else
        {
            try{
                //check login ok
                $this->_items_info(self::ROOT_ID);

            }
            catch(\Exception $e)
            {
                $this->set_token(null);
                goto login;
            }
        }
        
    }

    public function getDriveVendorName()
    {
        return OBOOM_;
    }

    public function getRedirectUrl()
    {
        return 'https://' . $_SERVER['HTTP_HOST'] . '/books/oboom_drive.php';
    }
    public function isExpired()
    {
        return false;
    }
    protected function onCode($code)
    {

    }
    public function refreshToken()
    {
        //nop
    }
    public function deleteFile()
    {
        if($this->isLogged())
        {
            $file_id=$this->getStoreFileId();
            if($file_id)
            {
                $this->_delete_item($file_id);
            }
            else
            {
                \Logs\logWarning("File ID not found");
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
                
                $url = $this->_downloadLink($fileid);
                $name = $this->_items_info($fileid)[0]->name;

                $tmpfile = realpath('temp') . '/' . $name;

                $content=$this->curl_request($url);
                $this->downloadToBrowser($tmpfile,$content);
            }
            else
            {
                \Logs\logWarning("File ID not found");
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

                $the_file= new CURLFile(realpath('temp/'.$this->getFileName()),'application/octet-stream',$file_name);
                $url = self::API_URL . '/' . self::VERSION . '/ul?token=' . TOKEN . '&parent=' . $book_folder->id;
                $body=array(
                           'file' => $the_file,
                       );

                $options=array(
                     CURLOPT_HTTPHEADER => array(
                         'Content-Type: multipart/form-data'
                     ),
                     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1

                 );
                $response = $this->curl_post($url,$body,$options);
                

                if($response==null)
                {
                    throw new Exception('response is null');
                }

                $response=json_decode($response);
                if($response[0]==200)
                {

                    $this->register_link($response[1]->id, $response[1]->size);

                }
                $this->_throw_error($response);
                //$download_url = self::API_URL . '/files/' . $response->entries[0]->id . '/content';
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
                'urls' => (object)array('download' => (object)array( 'method' => 'GET', 'url' => self::API_URL . '/' . self::VERSION . '/dl?token={accesstoken}&item={fileid}'),
                                
                                'upload' => (object)array( 'method' => 'POST', 'url' => "proxy.php?action=upload",
                                                    'headers' => (object)array( /*'Content-Type: multipart/form-data',*/ 'XForward-To-Url: ' . urlencode(self::UPLOAD_URL), 'XForward-Query: token={accesstoken}&parent={parentid}' ),
                                                    'body' => (object)array( 'file' => '{filecontent}' ) ),
                                'delete' => (object)array('method' => 'GET', 'url' => self::API_URL . '/' . self::VERSION . '/rm?token={accesstoken}&items={fileid}' ) 
                            )
                 );
        }
        throw new \Exception('not logged');
    }
	public function downloadLink($fileid)
	{
		if($this->isLogged())
		{
			$root_url=self::API_URL;
			$access_token=$this->getAccessToken();
            $url = 'proxy.php?url=' . urlencode(sprintf("%s/dl?token=%s&item=%s",$root_url,$access_token,$fileid));
			return array( 'method' => 'GET', 'url' =>  $url, 'headers' => array());
		}
		else
		{
				throw new \Exception('not logged');
		}
	}
    private function getBookFolder()
    {
        $folder = $this->_find_folder(self::ROOT_ID, 'Books');
        if ($folder==null)
        {
            $folder = json_decode($this->_make_folder('1','Books'));
        }
        return $folder;
    }
	public function useDownloadProxy() { return true; }

    private function _make_folder($parent_id, $folder_name)
    {
        $url = self::API_URL . '/' . self::VERSION . '/mkdir?token=' . $this->getAccessToken() . '&parent=' . $parent_id . '&name=' . $folder_name;
        $response = json_decode($this->curl_request($url));

        if ( $response[0] == 200)
        {
            return $response[1];

        }else{
            throw new \Exception('Error:' . var_export($response,true));
        }

    }

    private function _list_folder($folder_id)
    {
        $url = self::API_URL . '/' . self::VERSION . '/ls?token=' . $this->getAccessToken() .'&item=' . strval($folder_id);
        $response = json_decode($this->curl_request($url));

        if (intval($response[0])===200)
        {
            return $response[2];
        }
        throw new \Exception(var_export($response,true));

    }

    private function _find_folder($parent_id,$folder_name){
        $list = $this->_list_folder($parent_id);
        foreach ($list as $item) {
            if ($item->name===$folder_name && $item->type==='folder')
            {
                return $item;
            }
        }
        return null;
    }

    private function _find_file($parent_id,$file_name){
        $list = $this->_list_folder($parent_id);
        foreach ($list as $item) {
            if ($item->name===$file_name && $item->type==='file')
            {
                return $item;
            }
        }
        return null;
    }

    private function _delete_item($items_id)
    {
        $url = self::API_URL . '/' . self::VERSION . '/rm?token=' . $this->getAccessToken() . '&items=' . $items_id;
        $res = json_decode($this->curl_request($url));

        if ($res[0]==200)
            return;
        $this->_throw_error($res);
    }
    private function _upload_file($parent_id, $file_name, $path)
    {
        $the_file= new CURLFile(realpath($path),'application/octet-stream',$file_name);
        $url = self::API_URL . '/' . self::VERSION . '/ul?token=' . $this->getAccessToken() . '&parent=' . $parent_id;
        $body=array(
                   'file' => $the_file,
               );

        $options=array(
             CURLOPT_HTTPHEADER => array(
                 'Content-Type: multipart/form-data'
             ),
             CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,

         );
         $res = json_decode($this->curl_post($url,$body,$options));

         if ($res[0]==200)
         {
            return $res[1][0];
         }
         $this->_throw_error($res);
    }
    private function _downloadLink($file_id){
        $url = self::API_URL . '/' . self::VERSION . '/dl?token=' . $this->getAccessToken() . "&item=" . $file_id;
        $res = $this->curl_request($url);

        $res = json_decode($res);
        if ($res[0]==200)
        {
            $res = 'http://' . $res[1] . '/1/dlh?ticket=' . $res[2] ;
            return $res;

        }
        $this->_throw_error($res);

    }
    private function _items_info($items_id)
    {
        $url = self::API_URL . '/' . self::VERSION . '/info?token=' . $this->getAccessToken() . '&items=' . $items_id ;
        $res = $this->curl_request($url);

        $res = json_decode($res);
        if($res[0]==200)
        {
            return $res[1];
        }
        $this->_throw_error($res);
    }
    private function _throw_error($msg)
    {
        throw new \Exception('Error: '. var_export($msg,true));
    }
}

 ?>