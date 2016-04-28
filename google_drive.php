<?php
set_include_path(get_include_path() . PATH_SEPARATOR . realpath('../google-api-php-client/src/'));
set_include_path(get_include_path() . PATH_SEPARATOR . realpath('../google-api-php-client/src/Google'));
set_include_path(get_include_path() . PATH_SEPARATOR . realpath('../google-api-php-client/src/Google/Service'));

include_once "drive_client.php";

require_once realpath('../google-api-php-client/src').'/Google/Auth/OAuth2.php';
require_once realpath('../google-api-php-client/src').'/Google/Client.php';
require_once realpath('../google-api-php-client/src').'/Google/Service.php';
require_once realpath('../google-api-php-client/src').'/Google/Model.php';
require_once realpath('../google-api-php-client/src').'/Google/Config.php';
require_once realpath('../google-api-php-client/src').'/Google/Collection.php';
require_once realpath('../google-api-php-client/src').'/Google/Service/Resource.php';
require_once realpath('../google-api-php-client/src').'/Google/Service/Drive.php';
require_once realpath('../google-api-php-client/src').'/Google/Auth/AssertionCredentials.php';

define('DRIVE_SCOPE', 'https://www.googleapis.com/auth/drive');
define('SERVICE_ACCOUNT_EMAIL', 'incinerator-book-store@velvety-tube-124123.iam.gserviceaccount.com');
define('SERVICE_ACCOUNT_PKCS12_FILE_PATH', realpath('My Project-2c1987e4809b.json'));
define('GOOGLE_','GOOG');

 class GoogleDriveHelper extends Drive\Client
 {
    private $google_drive_service;
    private $drive_file;

    public function __construct()
    {
        $this->drive_file=new Google_Service_Drive_DriveFile();        
        parent::__construct();
		$this->buildAuth();
    }
	public function setFileName($file_name)
	{
		$this->drive_file->setName($file_name);
        parent::setFileName($file_name);
	}
    public function getDriveVendorName()
    {
        return GOOGLE_;
    }
	public function login()
	{
		$client = $this->google_drive_service->getClient();
		if (isset($_REQUEST['logout']))
		{
            $this->unsetSessionVar('upload_token');
            $this->unsetSessionVar('refresh_token');
		}

		if (isset($_GET['code'])) {
			$token= $client->authenticate($_GET['code']);
            $this->setSessionVar('upload_token',$token);
			$redirect = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
            header('Location: ' . filter_var($redirect, FILTER_SANITIZE_URL));
		}

		if ($this->getSessionVar('upload_token')) {
            if($client->getAccessToken()==null)
            {
                $client->setAccessToken($this->getSessionVar('upload_token'));
            }
            if ($client->isAccessTokenExpired()) 
            {
                $this->unsetSessionVar('upload_token');
                $refresh_token=$client->getRefreshToken();
                if($refresh_token)
                {
                    $client->refreshToken($refresh_token);
                    $this->setSessionVar('upload_token',$client->getAccessToken());
                }
                else
                {
                    throw new \Exception('Cannot refresh auth (token null)');
                }
            }
            else
            {
                $client->setAccessToken($this->getSessionVar('upload_token'));
            }
		}
        else 
        {
            $authUrl = $client->createAuthUrl();
            header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
		}
	}
    private function buildService($userEmail)
	{
        $client = new Google_Client();
        $auth=$client->loadServiceAccountJson(SERVICE_ACCOUNT_PKCS12_FILE_PATH,array(DRIVE_SCOPE));
        $auth->sub = $userEmail;
        $client->setAssertionCredentials($auth);
        $this->google_drive_service = new Google_Service_Drive($client);
    }

    private function buildAuth()
    {
        $client_id = '76824108658-qopibc57hedf4k4he7rlateis2bkoigv.apps.googleusercontent.com';
        $client_secret = '444KL-z0e_JZR8_PBu_vXvKH';
        $redirect_uri = 'http://' .  $_SERVER['HTTP_HOST'] . '/books/google_drive.php?callbackAuth';

        $client = new Google_Client();
        $client->setClientId($client_id);
        $client->setClientSecret($client_secret);
        $client->setRedirectUri($redirect_uri);
        $client->addScope("https://www.googleapis.com/auth/drive");
        $this->google_drive_service = new Google_Service_Drive($client);
	}
    public function deleteFile()
    {
        if($this->getSessionVar('upload_token'))
        {
            $file_id=$this->getStoreFileId();
            if($file_id)
            {
                $this->google_drive_service->files->delete($file_id);
            }
            else
            {
                throw new \Exception('cannot delete file: file id not found');
            }
        }
        else
        {
            throw new \Exception('Cannot delete file: acces token not found');
        }
    }
    public function downloadFile()
    {
        if($this->getSessionVar('upload_token'))
        {
            $file_id=$this->getStoreFileId();
            if($file_id)
            {
                $obj=$this->google_drive_service->files->get($file_id,array('fields'=>'name'));
                $content=$this->google_drive_service->files->get($res->id,array('alt'=>'media'));
                file_put_contents('temp/' . $obj->name,$content);
            }
        }
    }
    public function uploadFile()
    {
        if($this->getSessionVar('upload_token'))
        {
		    $file_name = $this->drive_file->getName();
		    $this->drive_file->setParents( array($this->getDestFolder("Books")->getId()) );
		    //$this->drive_file->setDescription('uploaded from server');
		    //$this->drive_file->setSize(filesize('temp/'.$file_name));
		    $size_mo = $this->drive_file->getSize() / (1024*1024);
            $res=null;
		    if($size_mo>5)
		    {
			    $res=$this->uploadMultipart();
		    }
		    else
		    {
			    $res=$this->uploadSimple();
		    }
            if($res->error)
            {
                throw new \Exception('upload error: ' . $res->error);
            }

            $obj=$this->google_drive_service->files->get($res->id,array('fields'=>'id,size'));
            if($obj)
            {
                $this->register_link($obj->id,$obj->size);
            }
        }
    }

    private function uploadSimple()
    {
		if($this->google_drive_service && $this->google_drive_service->files)
		{
				return $this->google_drive_service->files->create(
					$this->drive_file,
					array(
						'data' => file_get_contents('temp/'.$this->drive_file->getName()),
						'mimeType' => 'application/octet-stream',
						'uploadType' => 'multipart'
					)
				);
		}
		return 0;
    }
    private function uploadMultipart()
    {
        $chunkSizeBytes = 1 * 1024 * 1024;

        // Call the API with the media upload, defer so it doesn't immediately return.
        $client->setDefer(true);
        $request = $this->google_drive_service->files->create($this->drive_file);

        // Create a media file upload to represent our upload process.
        $media = new Google_Http_MediaFileUpload(
            $client,
            $request,
            'text/plain',
            null,
            true,
            $chunkSizeBytes
        );
        $media->setFileSize($this->drive_file->getSize());

        // Upload the various chunks. $status will be false until the process is
        // complete.
        $status = false;
        $handle = fopen('temp/'.$this->drive_file->getTitle(), "rb");
        while (!$status && !feof($handle)) {
            // read until you get $chunkSizeBytes from TESTFILE
            // fread will never return more than 8192 bytes if the stream is read buffered and it does not represent a plain file
            // An example of a read buffered file is when reading from a URL
            $chunk = readVideoChunk($handle, $chunkSizeBytes);
            $status = $media->nextChunk($chunk);
        }

        // The final value of $status will be the data from the API for the object
        // that has been uploaded.
        $result = false;
        if ($status != false) {
            $result = $status;
        }

        fclose($handle);
        return $result;
    }
    private function readVideoChunk ($handle, $chunkSize)
    {
        $byteCount = 0;
        $giantChunk = "";
        while (!feof($handle)) {
            // fread will never return more than 8192 bytes if the stream is read buffered and it does not represent a plain file
            $chunk = fread($handle, 8192);
            $byteCount += strlen($chunk);
            $giantChunk .= $chunk;
            if ($byteCount >= $chunkSize)
            {
                return $giantChunk;
            }
        }
        return $giantChunk;
    }
	private function getDestFolder($name)
	{
		$file=$this->google_drive_service->files->listFiles(array('q'=>"name='$name' and mimeType='application/vnd.google-apps.folder'"));
		if($file->count()==0)
		{
			$file=new Google_Service_Drive_DriveFile();
			$file->setName($name);
			$file->setMimeType('application/vnd.google-apps.folder');
			$file=$this->google_drive_service->files->create($file,array("mimeType"=>'application/vnd.google-apps.folder'));
		}
		else
		{
			$file=$file->current();
		}
		return $file;
	}
}



 $gg_upload_helper = new GoogleDriveHelper();
 $gg_upload_helper->execute();


?>