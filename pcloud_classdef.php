<?php
include_once "drive_client_classdef.php";


define('PCLOUD_','PCLD');
define('_REDIRECT_URI_','https://' . $_SERVER['HTTP_HOST'] . '/books/pcloud_drive.php');


class PCloudDrive extends Drive\Client
{
    const CLIENT_ID="3IPgVggnGsm";
	const CLIENT_SECRET="f4dAROwCG2mIAwllDuevJBCRlUgy";
    const REDIRECT_URI=_REDIRECT_URI_;

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

    public function getRedirectUrl()
    {
        $url= self::AUTH_URL . '?response_type=code' . '&' .
            'client_id=' . self::CLIENT_ID . '&' .
            'redirect_uri=' . self::REDIRECT_URI;
        \Logs\logInfo('redirect url: '.$url);
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
            $token = (object)array('access_token' => $response->access_token, 'refresh_token' => 'N/A' , 'expires_in' => 2678400 );
            $this->set_token($token);
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
                         'Authorization: Bearer ' . $this->getAccessToken(),
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
        else
        {
            throw new \Exception('not logged');
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
                            'Authorization: Bearer ' . $this->getAccessToken()
                        )
                    );
                $response=json_decode($this->curl_request($url,$options));
            }
        }
        else
        {
            throw new \Exception('not logged');
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
                           'Authorization: Bearer ' . $this->getAccessToken()
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
        else
        {
            throw new \Exception('not logged');
        }
    }
    public function store_info()
    {
        $book_folder=$this->getBookFolder();
        $base_url=self::API_URL;
        
        return (object) array(
            'access_token' => $this->getAccessToken(),
            'book_folder' => $book_folder->folderid,
            'urls' => array('download' =>array( 'method' => 'GET', 'url' => $base_url. '/getfilelink?fileid={fileid}&access_token={accesstoken}&forcedownload=1' ), 
                            'upload' => array( 'method' => 'POST', 'url' => $base_url . '/uploadfile?access_token={accesstoken}',
                                               'body' =>array ( 'filename' => '{filename}',
                                                                'folderid' => '{parentid}',
                                                                'nopartial' => 1,
                                                                'data' => '{filecontent}' ) ),
                            'delete' => array( 'method' => 'GET', 'url' => $base_url . '/deletefile?fileid={fileid}') )
            );
    }
		
		public function downloadLink($fileid)
		{
			if($this->isLogged())
			{
				$access_token=$this->getAccessToken();
				$download_url = self::API_URL . '/getfilelink?fileid=' /*'/getpublinkdownload?fileid='*/ . $fileid . '&access_token=' . $access_token . '&forcedownload=1';
				//$url = self::API_URL . '/getfilepublink?fileid=' . $fileid;
				if($this->useDownloadProxy())
				{
					$options=array(
							 CURLOPT_HTTPHEADER => array(
									 'Authorization: Bearer ' . $access_token
							 )
					 );

					$resp=json_decode($this->curl_request($download_url,$options));
					if($resp->result!=0)
					{
						throw new \Exception('cannot get file link');
					}
					$download_url= 'https://' . $resp->hosts[ time() % count($resp->hosts) ] . $resp->path;
				}
				
				return array( 'method' => 'GET', 'url' =>  $download_url, 'headers' => array("Authorization: Bearer $access_token") );
			}
			else
			{
					throw new \Exception('not logged');
			}
		}
    private function getBookFolder()
    {
        $url = self::API_URL . '/listfolder?path=' . urlencode('/') . '&' . 'recursive=0' . '&' . 'nofiles=1';
        $options = array(
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->getAccessToken() )

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
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->getAccessToken() ),
        );
        $response=$this->curl_request($url,$options);
        $response=json_decode($response);

        if($response->error)
        {
            throw new Exception('creating \'Books\' folder error: ' . $response->error);
        }
        return $response->metadata;
    }
		public function useDownloadProxy() { return true; }
}
    
    ?>