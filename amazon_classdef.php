<?php
include_once "drive_client_classdef.php";

define('AMAZON_','AMZN');


class AmazonCloudHelper extends Drive\Client
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
    
    protected function onCode($code)
    {
        $body= 'grant_type=authorization_code' . '&' .
            'code=' . $code . '&' .
            'client_id=' . self::CLIENT_ID . '&' .
            'client_secret=' . self::CLIENT_SECRET . '&' .
            'redirect_uri=' . self::REDIRECT_URI;

        $options=array(

            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded'
            )
        );
        $response=$this->curl_post(self::TOKEN_URL,$body,$options);
        \Logs\logDebug(var_export($response,true));
        $this->set_token($response);
    }
    public function getRedirectUrl()
    {
        $url = self::AUTH_URL
                . '?client_id=' . self::CLIENT_ID
                . '&scope=' . urlencode('clouddrive:read_document clouddrive:write')
                . '&response_type=code'
                . '&redirect_uri=' . self::REDIRECT_URI ;
        return $url;
    }
    public function isExpired()
    {
        try
        {
            if(!property_exists($this,'token')) throw new \Exception('token not found');
            if(!property_exists($this->token,'expires_in')) throw new \Exception('expires_in not found');
            if(!property_exists($this->token,'created')) throw new \Exception('created not found');
            return (($this->token->expires_in + $this->token->created)<=time());
        }
        catch(\Exception $e)
        {
            \Logs\logWarning($e->getMessage());
        }
        return false;
    }

    public function refreshToken()
    {
        if($this->token==null || $this->token->refresh_token==null)
        {
            throw new \Exception('no refresh token');
        }
        $body= 'grant_type=refresh_token' . '&' .
        'refresh_token=' . $this->token->refresh_token . '&' .
        'client_id=' . self::CLIENT_ID . '&' .
        'client_secret=' . self::CLIENT_SECRET ;

        $options=array(
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded'
            )
        );
        $response=$this->curl_post(self::TOKEN_URL,$body,$options);
        \Logs\logDebug(var_export($response,true));
        $this->set_token($response);
    }

    public function store_info()
    {
        if($this->isLogged())
        {
            $this->getEndPoint();
            $content_url=$this->getSessionVar('contentUrl');
            $metadata_url=$this->getSessionVar('metadataUrl');
            $book_folder=$this->getBookDir();
            return (object)array(
                'access_token' => $this->getAccessToken(),
                'book_folder' => $book_folder->id,
                'urls' => array(
                        'download' => array( 'method' => 'GET', 'url' => $content_url . '/nodes/{fileid}', 'headers' => array('Authorization: Bearer {accesstoken}')), 
                        'upload' => array( 'method' => 'POST', 'url' => $content_url . '/nodes', 'headers' => array('Authorization: Bearer {accesstoken}'),
                                            'body' => array( 'metadata' => '{"name":"{filename}","kind":"FILE","parents":["{parentid}"]}', 'content' => '{filecontent}' )),
                        'delete' => array( 'method' => 'DELETE', 'url' => $metadata_url . '/nodes/{parentid}/children/{fileid}' ) )
                );
        }
        else
        {
            throw new \Exception('not logged');
        }
    }
		public function downloadLink($fileid)
		{
			if($this->isLogged())
			{
				$this->getEndPoint();
				$root_url=$this->getSessionVar('contentUrl');;
				$access_token=$this->getAccessToken();
				return array( 'method' => 'GET', 'url' =>  sprintf("%s/nodes/%s",$root_url,$fileid), 'headers' => array("Authorization: Bearer $access_token"));
			}
			else
			{
					throw new \Exception('not logged');
			}
		}
	public function uploadFile()
	{
        if($this->isLogged())
        {
            $this->getEndPoint();

            $content_url=$this->getSessionVar('contentUrl');
            if($content_url!=null)
            {
                $book_dir=$this->getBookDir();
                
                $the_file= new CURLFile(realpath('temp/'.$this->getFileName()));
                
                $metadata=json_encode(array( 'name' => urlencode($this->getFileName()), 'kind' => 'FILE', 'parents' => array($book_dir->id)));
                $body=array(
                    'metadata' => $metadata,
                    'content' => $the_file
                );
                $options=array(
                     CURLOPT_HTTPHEADER => array(
                         'Authorization: Bearer ' . $this->getAccessToken(),
                         'Content-Type: multipart/form-data'
                     )
                 );
                $url=$this->getSessionVar('contentUrl') . '/nodes';
                $response=$this->curl_post($url,$body,$options);
                $response=json_decode($response);
                
                if($response)
                {
                    //$url=$this->getSessionVar('contentUrl') . 'nodes/'. $response->id . '/content';
                    $this->register_link($response->id,$response->contentProperties->size);
                }
            }
            else
            {
                throw new \Exception('cannot get conntent url for Amazon cloud');
            }
        }
	}
    public function deleteFile()
    {
        if($this->isLogged())
        {
            $this->getEndPoint();

            $file_id=$this->getStoreFileId();
            $metadata_url=$this->getSessionVar('metadataUrl');
            if($file_id && $metadata_url)
            {
                $book_dir=$this->getBookDir();
                if($book_dir)
                {
                    $url=sprintf('%s/nodes/%s/children/%s',$metadata_url,$book_dir->id,$file_id);
                    $options=array(
                         CURLOPT_CUSTOMREQUEST => 'DELETE',

                         CURLOPT_HTTPHEADER => array(
                             'Authorization: Bearer ' . $this->getAccessToken()
                         )
                     );
                    $response=$this->curl_request($url,$options);
                    $response=json_decode($response);
                }
            }
        }
    }
    public function downloadFile()
    {
        if($this->isLogged())
        {
            $this->getEndPoint();

            $file_id=$this->getStoreFileId();
            if($file_id)
            {
                $metadata_url=$this->getSessionVar('metadataUrl');
                if($metadata_url)
                {
                    $url=sprintf('%s/nodes/%s',$metadata_url,$file_id);
                    $options=array(
                            CURLOPT_HTTPHEADER => array(
                                 'Authorization: Bearer ' . $this->getAccessToken()
                             )
                         );
                    $mdata=$this->curl_request($url,$options);

                    if ($mdata)
                    {
                        echo $mdata . '<br>';
                        $mdata=json_decode($mdata);
                    }
                    else
                    {
                        throw new \Exception('cannot get file information');
                    }

                    $content_url=$this->getSessionVar('contentUrl');
                    if($content_url)
                    {

                        $url=sprintf('%snodes/%s/content',$content_url,$file_id);

                        $tmpfile = realpath('temp') . '/' . $mdata->name;
                        
                        $content = $this->curl_request($url,$options);
                        echo $url . '<br>';

                        echo $content . '<br>';
                        var_dump($options);
                        echo  '<br>';

                        $this->downloadToBrowser($tmpfile,$content);
                    }
                }
            }
        }
        else
        {
            throw new \Exception('not logged');
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
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->getAccessToken() )
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
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->getAccessToken() )
            
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
        $data=json_encode((object)array("name"=>"Books", "kind"=>"FOLDER","labels"=>array("BookStore")));
        $options= array(
            CURLOPT_HTTPHEADER => array( 'Authorization: Bearer ' . $this->getAccessToken() )
        );
        $response=$this->curl_post($url,$data,$options);
        $response=json_decode($response);
        return $response;
    }
}
?>