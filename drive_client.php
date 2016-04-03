<?php

class DriveClient
{
    private $file_name;
    private $vendor_name;
    public function __construct()
    {
        $this->vendor_name = $this->getDriveVendorName();
        if(isset($_GET['filename']))
        {
            $_SESSION[$this->vendor_name]['current_filename'] = $_GET['filename'];
        }
        if(isset($_SESSION[$this->vendor_name]['current_filename']))
        {
            $this->setFileName($_SESSION[$this->vendor_name]['current_filename']);            
        }
    }
    public function setFileName($file_name)
    {
        $this->file_name = $file_name; 
    }
    public function getFileName(){ return $this->file_name; }
    public function login() {}
    public function uploadFile() { }
    public function getDriveVendorName() {  return ""; }
    public function getSessionVar($var_name)
    {
        if(isset($_SESSION[$this->vendor_name][$var_name]))
        {
            return $_SESSION[$this->vendor_name][$var_name];
        }
        return null; 
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
}

function createDriveClient($drive_code,$filename)
{
    switch($drive_code)
    {
        case 'GOOG':
        {
            header('Location: google_drive.php?filename=' . $filename );
            break;
        }
        case 'MSFT':
        {
            header('Location: ms_onedrive.php?filename=' . $filename );
            break;            
        }
        case 'AMZN':
        {
            header('Location: amazon_cloud.php?filename=' . $filename );
            break;                    
        }
        default:
            header('Location: '. urlencode('home.php?error='.$drive_code));
    }
}
?>