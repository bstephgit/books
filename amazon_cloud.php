<?php

include "drive_client.php";

define('AMAZON_','AMZN');

class AmazonCloudHelper extends DriveClient
{
	const CLIENT_ID="amzn1.application-oa2-client.4e00bfdaef2d4892846825fe546bf80d";
	const CLIENT_SECRET="852a445d0e389160932648d79b534ebe0eb3c6268bd4f0e5c823a4a9c3ed8733";
	const REDIRECT_URI='http://albertdupre.esy.es/books/amazon_cloud.php';

	const AUTH_URL = "https://www.amazon.com/ap/oa";
	const TOKEN_URL = "https://api.amazon.com/auth/o2/token";
    const API_URL = "https://drive.amazonaws.com";
    
	public function __construct()
	{
       parent::__construct();
	}

    public function getDriveVendorName()
    {
        return AMAZON_;
    }
	public function login()
	{
        if(isset($_SESSION[AMAZON_]['access_token']))
        {
            return;
        }
		if(isset($_GET['code']))
		{
			$code=$_GET['code'];
			$body=array(
				'grant_type' => 'authorization_code' ,
				'code' => $code ,
				'client_id' => urlencode(self::CLIENT_ID) ,
				'client_secret' => urlencode(self::CLIENT_SECRET) ,
				'redirect_uri' => urlencode(self::REDIRECT_URI)
				);

			$options=array(
				CURLOPT_POST       => true,

				CURLOPT_HTTPHEADER => array(
					'Content-Type: application/x-www-form-urlencoded'
            	),

            	CURLOPT_POSTFIELDS => $body
        	);
            $response=$this->curl_request(self::TOKEN_URL,$options);
			$response=json_decode($response);
            
            if(array_key_exists('access_token',$response))
            {
                $_SESSION[AMAZON_]['access_token']=$response['access_token'];
            }
            if(array_key_exists('refresh_token',$response))
            {
                $_SESSION[AMAZON_]['refresh_token']=$response['refresh_token'];                
            }
            $this->getEndPoint();
		}
		else
		{
			$url = self::AUTH_URL
				. '?client_id=' . self::CLIENT_ID
				. '&scope=clouddrive%3Aread_all%20clouddrive%3Awrite'
				. '&response_type=code'
				. '&redirect_uri=http://localhost';

			header('Location: '. $url);
		}

        return $url;
	}
	public function uploadFile()
	{
       $content_url=$_SESSIONS[AMAZON_]['contentUrl'];
       if($content_url!=null)
       {
           $book_dir=$this->getBookDir();
           
           $the_file= new CURLFile('temp/'.$this->getFileName());
           
           $body=array(
               'metadata' => array( 'name' => $this->getFileName(), 'kind' => 'FILE', 'parents' => array($book_dir['id'])),
               'content' => $the_file
           );
           $options=array(
				CURLOPT_POST       => true,

				CURLOPT_HTTPHEADER => array(
					'Authorization: Bearer ' . $this->getSessionVar('access_token')
            	),

            	CURLOPT_POSTFIELDS => $body
        	);
            $url=$this->getSessionVar('contentUrl') . '/nodes';
            $response=$this->curl_request($url,$options);
			$response=json_decode($response);
            
       }
       else
       {
           throw new \Exception('cannot get conntent url for Amazon cloud');
       }
	}

	private function curl_request($url, $options = array())
	{
		$curl = curl_init();

        $defaultOptions=array(
            // General options.
            CURLOPT_RETURNTRANSFER => true,
            //CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_AUTOREFERER    => true,

            // SSL options.
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_URL            => $url
        );

		curl_setopt_array($curl,$defaultOptions + $options);
        $result = curl_exec($curl);
        if (false === $result) {
            throw new \Exception('curl_exec() failed: ' . curl_error($curl));
        }
        return $result;
	}
    private function getEndPoint()
    {
        if(isset($_SESSION[AMAZON_]['contentUrl']))
        {
            return;
        }
        $url = self::API_URL . '/drive/v1/account/endpoint';
        
        $options = array(
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $_SESSION[AMAZON_]['access_token'])
        );
        $response=json_decode( $this->curl_request($url,$options) );
        
        if(array_key_exists('customerExists',$response))
        {
            if($response['customerExists']===false)
            {
                throw new \Exception('getEndPoint failed: customer does not exist');
            }
        }
        if(array_key_exists('contentUrl',$response))
        {
            $_SESSION[AMAZON_]['contentUrl']=$response['contentUrl'];
        }
        if(array_key_exists('metadataUrl',$response))
        {
            $_SESSION[AMAZON_]['metadataUrl']=$response['metadataUrl'];
        }
    } 
    
    private function getBookDir()
    {
        $books_folder=$this->getFolder('Books');
        if($books_folder==null)
        {
            $books_folder=$this->createFolder('Books');
        }
        return $books_folder;
    }
    
    private function getFolder($name)
    {
        $url = $this->getSessionVar('metadataUrl') . '/nodes?filters=kind:FOLDER AND name:' . $name;
        $options = array(
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->getSessionVar('access_token') )
            
        );
        $response=$this->curl_request($url,$options);
        $response=json_decode($response);
        if(count($response)==0)
        {
            return null;
        }
        return $response[0];
    }
    private function createFolder($name)
    {
        $url = $this->getSessionVar('metadataUrl') . '/nodes';
        $data=array("name"=>"Books", "kind"=>"FOLDER","labels"=>array("BookStore"));
        $options= array(
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->getSessionVar('access_token') )
           
        );
        $response=$this->curl_request($url,$options);
        $response=json_decode($response);
        return $response;
    }
    
}

session_start();

if(!isset($_SESSION[AMAZON_]))
{
	$_SESSION[AMAZON_]=array( 
        'code' => null , 
        'access_token' => null, 
        'refresh_token' => null, 
        'current_file' => null,
        'contentUrl' => null,
        'metadataUrl' => null,
        'current_file' => null );
}
try
{
    $amzn_drive=new AmazonCloudHelper();
    $amzn_drive->login();
    $amzn_drive->uploadFile();
    header('Location: home.php?done=1');
}
catch(Exception $e)
{
    echo $e->getMessage();
}
?>