<?php
include "db.php";

session_start();

class DriveClient
{
    private $file_name;
    private $bookid;
    private $vendor_name;
    protected function __construct()
    {
        $this->vendor_name = $this->getDriveVendorName();
        if(isset($_GET['bookid']))
        {
            $_SESSION[$this->vendor_name]['current_bookid']=$_GET['bookid'];
        }
        if(isset($_GET['filename']))
        {
            $_SESSION[$this->vendor_name]['current_filename'] = $_GET['filename'];
        }
        if(isset($_SESSION[$this->vendor_name]['current_filename']))
        {
            $this->setFileName($_SESSION[$this->vendor_name]['current_filename']);            
        }
        if(isset($_SESSION[$this->vendor_name]['current_bookid']))
        {
            $this->bookid=$_SESSION[$this->vendor_name]['current_bookid'];            
        }
    }
    public function setFileName($file_name)
    {
        $this->file_name = $file_name; 
    }
    public function getFileName(){ return $this->file_name; }
    public function login() {}
    public function uploadFile() { }
    public function getDriveVendorName() {  throw new Exception('should be overriden'); }
    public function getSessionVar($var_name)
    {
        if(isset($_SESSION[$this->vendor_name][$var_name]))
        {
            return $_SESSION[$this->vendor_name][$var_name];
        }
        return null; 
    }
    public function setSessionVar($var_name,$value)
    {
        $_SESSION[$this->vendor_name][$var_name]=$value;
    }
    protected function curl_request($url, $options = array())
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
    protected function retrieve_parameters($response)
    {
        if(property_exists($response,'access_token'))
        {
            $this->setSessionVar('access_token',$response->access_token);
        }
        else 
        {
            throw new \Exception('access_token not found');
        }
        
        if(property_exists($response,'refresh_token'))
        {
            $this->setSessionVar('refresh_token',$response->refresh_token);                
        }
        else 
        {
            throw new \Exception('refresh_token not found');
        }
        if(property_exists($response,'expires_in'))
        {
            $this->setSessionVar('expires_in',time() + intval($response->expires_in));
        }
    }
    protected function register_link($url,$size)
    {
       $dbase = odbc_connectDatabase();
       if($dbase!=null)
       {
           $sql=sprintf("SELECT ID FROM FILE_STORE WHERE VENDOR_CODE='%s'",$this->getDriveVendorName());
           $rec=$dbase->query($sql);$rec->next();
           $store_id=$rec->field_value('ID');
           
           $sql=sprintf("INSERT INTO BOOKS_LINKS(BOOK_ID,STORE_ID,URL,FILE_SIZE) VALUES(%d,%d,'%s',%d)",$this->bookid,$store_id,$url,$size);
           $dbase->query($sql);
           $dbase->close();
           header('Location: home.php');
       }
    }
}

function createDriveClient($drive_code,$filename,$fileid)
{
    $query='?filename=' . $filename . '&bookid=' . $fileid;
    switch($drive_code)
    {
        case 'GOOG':
        {
            header('Location: google_drive.php' . $query);
            break;
        }
        case 'MSFT':
        {
            header('Location: ms_onedrive.php' . $query);
            break;            
        }
        case 'AMZN':
        {
            header('Location: amazon_cloud.php' . $query);
            break;                    
        }
        case 'BOX':
        {
            header('Location: box_drive.php' . $query);
            break;                    
        }
        case 'PCLD':
        {
            header('Location: pcloud_drive.php' . $query);
            break;
        }
        default:
            header('Location: '. urlencode('home.php?error='.$drive_code));
    }
}
?>