<?php

set_include_path(get_include_path() . PATH_SEPARATOR . realpath('../onedrive-php-sdk/src/Krizalys/Onedrive/'));

include_once "drive_client_classdef.php";

require_once realpath(dirname(__FILE__) . '/../onedrive-php-sdk/src/Krizalys/Onedrive/Object.php');
require_once realpath(dirname(__FILE__) . '/../onedrive-php-sdk/src/Krizalys/Onedrive/Client.php');
require_once realpath(dirname(__FILE__) . '/../onedrive-php-sdk/src/Krizalys/Onedrive/File.php');
require_once realpath(dirname(__FILE__) . '/../onedrive-php-sdk/src/Krizalys/Onedrive/Folder.php');

define('CLIENT_ID','000000004018962B');
define('REDIRECT_URL','http://' . $_SERVER['HTTP_HOST'] . '/books/ms_onedrive.php');
define('CLIENT_SECRET','XZxVArudOBTAcEvWlO4zlE4bBXCkfm5P');
define('MS_ONEDRIVE_','MSOD');

class MSOneDriveHelper extends Drive\Client
{
	private $client;

	public function __construct()
	{
        parent::__construct();
        $option=null;
		if($this->getSessionVar('onedrive.client.state'))
		{
			$option = array('client_id' => CLIENT_ID,'state' => $this->getSessionVar('onedrive.client.state'));
            
		}
		else
		{
			$option = array('client_id' => CLIENT_ID);
		}
        if($this->token)
        {
            $option['state'] = (object) array( 'token' => (object) array( 'obtained' => $this->token->created , 'data' => $this->token) );
        }
        \Logs\logDebug(var_export($option,true));

		$this->client = new \Krizalys\Onedrive\Client($option);
	}

    public function getDriveVendorName()
    {
        return MS_ONEDRIVE_;
    }

    public function getRedirectUrl()
    {
        $login_url = $this->client->getLogInUrl(array('wl.basic','wl.skydrive_update','wl.offline_access'),REDIRECT_URL);
        $this->setSessionVar('onedrive.client.state',$this->client->getState());
        return $login_url;
    }
    
    protected function isExpired()
    {
        \Logs\logDebug($this->client->getAccessTokenStatus());
        \Logs\logDebug('token expires '.date('d-m-Y H:i',$this->token->expires_in+$this->token->created));
        \Logs\logDebug('get token expires '.$this->client->getTokenExpire());
        return $this->client->getAccessTokenStatus()===-2;
    }
    protected function onCode($code)
    {
        $this->client->obtainAccessToken(CLIENT_SECRET,$code);
        $state=$this->client->getState();
        $state->token->data->{'created'} = $state->token->obtained;
        $this->set_token( $state->token->data );
    }
    protected function refreshToken()
    {
        $options=array( CURLOPT_HTTPHEADER => array( 'Content-Type: application/x-www-form-urlencoded') );
        $body = 'client_id=' . CLIENT_ID . ' &redirect_uri=' . REDIRECT_URL . '&client_secret=' . CLIENT_SECRET .
                '&refresh_token=' . $this->token->refresh_token . '&grant_type=refresh_token';
        $response = $this->curl_post('https://login.live.com/oauth20_authorize.srf',$body,$options);

        $state = (object)array('redirect_uri' => null,
                        'token'       => array( 'obtained' => time(), 'data' => json_decode($response) )
            );
        $option = array('client_id' => CLIENT_ID,'state' => $state );
        $this->client = new \Krizalys\Onedrive\Client($option);

        $state->token->data->{'created'} = $state->token->obtained;

        $this->set_token( $state->token->data );
        $this->setSessionVar('onedrive.client.state',$this->client->getState());

    }

	public function uploadFile()
	{
        if($this->isLogged())
        {
            $book_folder=$this->getBookFolder();
            $res=$books_folder->createFile($this->getFileName(),file_get_contents('temp/'.$this->getFileName()));
            if($res instanceof \Krizalys\Onedrive\File)
            {
                $this->register_link($res->getId(),$res->getSize());
            }
            else
            {
                throw new \Exception('Cannot upload file: ' . var_dump($res));
            }
        }
        else
        {
            throw new \Exception('no token to file uploaded');
        }
	}
    public function deleteFile()
    {
        if($this->isLogged())
        {
            $file_id=$this->getStoreFileId();
            if($file_id)
            {
                $this->client->deleteObject($file_id);
            }
        }
    }
    public function downloadFile()
    {
        if($this->isLogged())
        {
            $file_id=$this->getStoreFileId();
            if($file_id)
            {
                $file_obj = new \Krizalys\Onedrive\File($this->client,$file_id);
                $name=$file_obj->getName();

                $tmpfile = realpath('temp') . '/' . $name;
                $content=$file_obj->fetchContent(array(CURLOPT_FOLLOWLOCATION => true));
                $this->downloadToBrowser($tmpfile,$content);
            }
        }
    }
    public function store_info()
    {
        if($this->isLogged())
        {
            $book_folder=$this->getBookFolder();
            $base_url='https://apis.live.net/v5.0/';

            return (object) array(
                'access_token' => $this->getAccessToken(),
                'book_folder' => $book_folder->getId(),
                'urls' => array('download' => $base_url, 'upload' => $base_url, 'delete' => $base_url)
                );
        }
        else
        {
            throw new Exception\Exception('not logged');
        }
    }
    private function getBookFolder()
    {
        $public_docs=$this->client->fetchDocs();
        $books_folder=NULL;
        foreach($public_docs->fetchChildObjects() as $abook)
        {
            if($abook->isFolder() && $abook->getName()==='books')
            {
                $books_folder=$abook;
                break;
            }
        }
        if($books_folder==NULL)
        {
            $books_folder=$public_docs->createFolder('Books');
        }
        return $books_folder;
    }
}
    
?>