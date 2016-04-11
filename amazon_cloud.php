<?php

include "drive_client.php";

define('AMAZON_','AMZN');


class AmazonCloudHelper extends DriveClient
{
    const CLIENT_ID="amzn1.application-oa2-client.4e00bfdaef2d4892846825fe546bf80d";
    const CLIENT_SECRET="852a445d0e389160932648d79b534ebe0eb3c6268bd4f0e5c823a4a9c3ed8733";
	const REDIRECT_URI='https://albertdupre.byethost13.com/books/amazon_cloud.php';

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
        if($this->getSessionVar('access_token'))
        {
            $expires_in=$this->getSessionVar('expires_in');
            if($expires_in && $expires_in<=time())
            {
                $refresh_token=$this->getSessionVar('refresh_token');
                if($refresh_token==null)
                {
                    throw new \Exception('cannot find refresh token');
                }
                $body= 'grant_type=refresh_token' . '&' .
                'refresh_token=' . $refresh_token . '&' .
                'client_id=' . self::CLIENT_ID . '&' .
                'client_secret=' . self::CLIENT_SECRET ;

                $options=array(
                    CURLOPT_POST       => true,

                    CURLOPT_HTTPHEADER => array(
                        'Content-Type: application/x-www-form-urlencoded'
                    ),

                    CURLOPT_POSTFIELDS => $body
                );
                $response=$this->curl_request(self::TOKEN_URL,$options);
                $response=json_decode($response);
                $this->retrieve_parameters($response);
            }
            return;
        }
        if(isset($_GET['code']))
        {
            $code=$_GET['code'];
            $body= 'grant_type=authorization_code' . '&' .
                'code=' . $code . '&' .
                'client_id=' . self::CLIENT_ID . '&' .
                'client_secret=' . self::CLIENT_SECRET . '&' .
                'redirect_uri=' . self::REDIRECT_URI;

            $options=array(
                CURLOPT_POST       => true,

                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/x-www-form-urlencoded'
                ),

                CURLOPT_POSTFIELDS => $body
            );
            $response=$this->curl_request(self::TOKEN_URL,$options);
            $response=json_decode($response);
            $this->retrieve_parameters($response);
        }
        else
        {
            $url = self::AUTH_URL
                . '?client_id=' . self::CLIENT_ID
                . '&scope=' . urlencode('clouddrive:read_document clouddrive:write')
                . '&response_type=code'
                . '&redirect_uri=' ;

            header('Location: '. $url . self::REDIRECT_URI);
        }
	}
	public function uploadFile()
	{
        if($this->getSessionVar('access_token'))
        {
            $this->getEndPoint();
            $content_url=$this->getSessionVar('contentUrl');
            if($content_url!=null)
            {
                $book_dir=$this->getBookDir();
                
                $the_file= new CURLFile(realpath('temp/'.$this->getFileName()));
                
                $metadata=json_encode(array( 'name' => $this->getFileName(), 'kind' => 'FILE', 'parents' => array($book_dir->id)));
                $body=array(
                    'metadata' => $metadata,
                    'content' => $the_file
                );
                $options=array(
                     CURLOPT_POST       => true,

                     CURLOPT_HTTPHEADER => array(
                         'Authorization: Bearer ' . $this->getSessionVar('access_token'),
                         'Content-Type: multipart/form-data'
                     ),

                     CURLOPT_POSTFIELDS => $body
                 );
                $url=$this->getSessionVar('contentUrl') . '/nodes';
                $response=$this->curl_request($url,$options);
                $response=json_decode($response);
                var_dump($response);
                if($response)
                {
                    $url=$this->getSessionVar('contentUrl') . 'nodes/'. $response->id . '/content';
                    $this->register_link($url,$response->contentProperties->size);
                }
            }
            else
            {
                throw new \Exception('cannot get conntent url for Amazon cloud');
            }
        }
	}
    
    private function getEndPoint()
    {
        if($this->getSessionVar('contentUrl'))
        {
            return;
        }
        $url = self::API_URL . '/drive/v1/account/endpoint';
        
        $options = array(
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->getSessionVar('access_token') )
        );
        
        $response=$this->curl_request($url,$options);
        $response=json_decode( $response );
 
        if(property_exists($response,'customerExists'))
        {
            if($response->customerExists===false)
            {
                throw new \Exception('getEndPoint failed: customer does not exist');
            }
        }
        if(property_exists($response,'contentUrl'))
        {
            $this->setSessionVar('contentUrl',$response->contentUrl);
        }
        else 
        {
            throw new \Exception('contentUrl not found');
        }
        if(property_exists($response,'metadataUrl'))
        {
            $this->setSessionVar('metadataUrl',$response->metadataUrl);
        }
        else 
        {
            throw new \Exception('metadataUrl not found');
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
        $url = $this->getSessionVar('metadataUrl') . '/nodes?filters=' . urlencode('kind:FOLDER AND name:' . $name);
        $options = array(
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->getSessionVar('access_token') )
            
        );
        $response=$this->curl_request($url,$options);
        $response=json_decode($response);
        if($response!=null && $response->data!=null)
        {
            foreach($response->data as $node)
            {
                if($node->name===$name)
                {
                    return $node;
                }
            }
        }
        return null;
    }
    
    private function createFolder($name)
    {
        $url = $this->getSessionVar('metadataUrl') . '/nodes';
        $data=(object)array("name"=>"Books", "kind"=>"FOLDER","labels"=>array("BookStore"));
        $options= array(
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->getSessionVar('access_token') )
           
        );
        $response=$this->curl_request($url,$options);
        $response=json_decode($response);
        return $response;
    }
}

session_start();
if(!isset($_SESSION[AMAZON_]) || !is_array($_SESSION[AMAZON_]))
{
    $_SESSION[AMAZON_]=array( 
        'access_token' => null, 
        'refresh_token' => null, 
        'current_file' => null,
        'contentUrl' => null,
        'metadataUrl' => null,
        'current_file' => null,
        'expires_in' => 0 );
}

try
{
    $amzn_drive=new AmazonCloudHelper();
    $amzn_drive->login();
    $amzn_drive->uploadFile();
}
catch(Exception $e)
{
    echo $e->getMessage();
}
?>