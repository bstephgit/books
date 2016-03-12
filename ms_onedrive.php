<?php

set_include_path(get_include_path() . PATH_SEPARATOR . realpath('../onedrive-php-sdk/src/Krizalys/Onedrive/'));

include "drive_client.php"

require_once realpath(dirname(__FILE__) . '/../onedrive-php-sdk/src/Krizalys/Onedrive/Object.php');
require_once realpath(dirname(__FILE__) . '/../onedrive-php-sdk/src/Krizalys/Onedrive/Client.php');
require_once realpath(dirname(__FILE__) . '/../onedrive-php-sdk/src/Krizalys/Onedrive/File.php');
require_once realpath(dirname(__FILE__) . '/../onedrive-php-sdk/src/Krizalys/Onedrive/Folder.php');

session_start();

define('CLIENT_ID','000000004018962B');
define('REDIRECT_URL','http://' . $_SERVER['HTTP_HOST'] . '/books/ms_onedrive.php');
define('CLIENT_SECRET','XZxVArudOBTAcEvWlO4zlE4bBXCkfm5P');
define('MS_ONEDRIVE_','MSOD')

class MSOneDriveHelper extends DriveClient
{
	private $client;

	public function __construct()
	{
        parent::__construct();
		if(isset($_SESSION['onedrive.client.state']))
		{
			$option = array('client_id' => CLIENT_ID,'state' => $_SESSION[MS_ONEDRIVE_]['onedrive.client.state']);
		}
		else
		{
			$option = array('client_id' => CLIENT_ID);
		}
		$this->client = new \Krizalys\Onedrive\Client($option);
	}
    
    public function getDriveVendorName() 
    {  
        return MS_ONEDRIVE_; 
    }

	public function login()
	{
		if (!array_key_exists('code', $_GET))
		{
			$login_url = $this->client->getLogInUrl(array('wl.basic','wl.signin','wl.skydrive_update'),REDIRECT_URL);

			$_SESSION[MS_ONEDRIVE_]['onedrive.client.state']=$this->client->getState();
			//should be redirected after execution
			header('Location: '.$login_url);
		}
		else
		{
			$this->client->obtainAccessToken(CLIENT_SECRET,$_GET['code']);
		}

	}
	public function uploadFile()
	{
		$public_docs=$this->client->fetchDocs();
		$books_folder=NULL;
		foreach($public_docs->fetchChildObjects() as $abook)
		{
			//echo $abook->getName();
			if($abook->isFolder() && $abook->getName()=='books')
			{
				$books_folder=$abook;
				break;
			}
		}
		if($books_folder==NULL)
		{
			$books_folder=$public_docs->createFolder('Books');
		}
		return $books_folder->createFile($this->getFileName(),file_get_contents('temp/'.$this->getFileName()));
	}
}

if(!isset($_SESSION[MS_ONEDRIVE_]))
{
    $_SESSION[MS_ONEDRIVE_]=array(
        
    );
}

try
{
    $store=new MSOneDriveHelper();
    $store->login();
    $store->uploadFile();
}
catch(Exception $e)
{
    //header('Location: home.php?error='.$e->getMessage());
    echo $e->getMessage();
}
?>