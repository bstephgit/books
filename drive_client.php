<?php

namespace Drive;

include_once "db.h";
include_once "dbTransactions.php";
include_once "Log.php";


session_start();


abstract class Client
{
    private $file_name;
    private $bookid;
    private $vendor_name;
    private $action;
    protected $access_token;
    protected $refresh_token;
    protected $expires_in;

    protected function __construct()
    {
        $this->vendor_name = $this->getDriveVendorName();
        if($this->vendor_name==null || strlen($this->vendor_name)==0)
        {
            \Logs\logWarning('Invalid Vendor code');
        }

        if(!isset($_SESSION[$this->vendor_name]) || !is_array($_SESSION[$this->vendor_name]))
        {
            $_SESSION[$this->vendor_name]=array();
        }

        if(isset($_GET['action']))
        {
            $_SESSION[$this->vendor_name]['action']=$_GET['action'];
        }
        if(isset($_GET['book_id']))
        {
            $_SESSION[$this->vendor_name]['current_bookid']=$_GET['book_id'];
        }
        if(isset($_GET['file_name']))
        {
            $_SESSION[$this->vendor_name]['current_filename'] = $_GET['file_name'];
        }
        if(isset($_SESSION[$this->vendor_name]['current_filename']))
        {
            $this->setFileName($_SESSION[$this->vendor_name]['current_filename']);
        }
        if(isset($_SESSION[$this->vendor_name]['current_bookid']))
        {
            $this->bookid=$_SESSION[$this->vendor_name]['current_bookid'];
        }
        if(isset($_SESSION[$this->vendor_name]['action']))
        {
            $this->action=$_SESSION[$this->vendor_name]['action'];
        }
    }
    public function setFileName($file_name) { $this->file_name = $file_name; }
    public function getFileName(){ return $this->file_name; }
    public function getBookId() { return $this->bookid; }
    public function setBookId($bid) { $this->bookid=$bid; }
    public function isLogged() { return ($this->access_token && !$this->isExpired()); }

    protected abstract function uploadFile();
    protected abstract function downloadFile();
    protected abstract function deleteFile();
    protected abstract function getDriveVendorName();
    protected abstract function getRedirectUrl();
    //protected abstract function getTokenUrl();
    protected abstract function isExpired();
    protected abstract function onCode($code);
    protected abstract function refreshToken();

    function execute()
    {
        try
        {
            if($this->login())
            {
                $this->doAction();
            }
        }
        catch(\Exception $e)
        {
            $ret=\Logs\logException($e);
        }
    }

    public function login()
    {
        if(!$this->access_token)
        {
            $this->initFromDb();
        }

        if($this->access_token)
        {
            if($this->isExpired())
            {
                $this->refreshToken();
            }
            return true;
        }

        if(isset($_GET['code']))
        {
            $code = $_GET['code'];
            $this->onCode($code);
            return $this->access_token;
        }

        $url=$this->getRedirectUrl();
        header('Location: '. $url );

    }

    public function doAction()
    {
        switch($this->action)
        {
            case 'upload':
                {
                    $this->uploadFile();
                    $dbt=\Database\getTransaction();
                    if(is_a($dbt,'\Database\Transactions\CreateBook'))
                    {
                        $dbt->commit();
                        $this->setBookId($dbt->bookid);
                    }
                    else
                    {
                        throw new \Exception('Transaction not \'\Database\Transactions\CreateBook\' type.');
                    }
                    \Database\removeTransaction();
                    header('Location: home.php?bookid=' . $this->getBookId() );
                }
                break;
            case 'download':
                {
                    $this->downloadFile();
                    header('Location: home.php');
                }
                break;
            case 'delete':
                {
                    $this->deleteFile();
                    $dbt=\Database\getTransaction();
                    if(is_a($dbt,'\Database\Transactions\DeleteBook'))
                    {
                        $dbt->commit();
                    }
                    else
                    {
                        throw new \Exception('Transaction not \'\Database\Transactions\DeleteBook\' type.');
                    }
                    \Database\removeTransaction();
                    header('Location: home.php');
                }
                break;
            default:
                throw new \Exception('Unknown action: ' . $this->action);
        }
    }

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
    public function unsetSessionVar($var_name)
    {
        unset($_SESSION[$this->vendor_name][$var_name]);
    }

    protected function curl_request($url, $options = array())
    {
        $curl = curl_init();
        $whole_options=array(
            // General options.
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_AUTOREFERER    => true,

            // SSL options.
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_URL            => $url
        );

        if($options)
        {
            $whole_options=$whole_options + $options;
        }
        curl_setopt_array($curl, $whole_options);
        $result = curl_exec($curl);

        if (false === $result) {
            curl_close($curl);
            throw new \Exception('curl_exec() failed: ' . curl_error($curl));
        }

        curl_close($curl);
        return $result;
    }
    protected function curl_post($url,$body,$options=array())
    {
        $post_options=array(
             CURLOPT_POST       => true,
             CURLOPT_POSTFIELDS => $body
         );
        if($options)
        {
            $post_options=$options+$post_options;
        }
        return $this->curl_request($url,$post_options);
    }
    private function initFromDb()
    {
        $obj=json_decode($this->loadLoginFromDb());
        if(property_exists($obj,'access_token'))
        {
            $this->access_token=$obj->access_token;
        }
        if(property_exists($obj,'refresh_token'))
        {
            $this->refresh_token=$obj->refresh_token;
        }
        if(property_exists($obj,'expires_in'))
        {
            $this->expires_in=$obj->expires_in;
        }
    }
    protected function retrieve_parameters($obj)
    {
        if(property_exists($obj,'access_token'))
        {
            $this->access_token = $obj->access_token;
        }
        else
        {
            \Logs\logWarning($this->vendor_name . ': no access token');
        }

        if(property_exists($obj,'refresh_token'))
        {
            $this->refresh_token = $obj->refresh_token;
        }
        else
        {
            \Logs\logWarning($this->vendor_name . ': no refresh token');
        }
        if(property_exists($obj,'expires_in'))
        {
            $this->expires_in = time() + intval($obj->expires_in);
        }
        $save=json_encode((object)array('access_token' => $this->access_token,'refresh_token' => $this->refresh_token, 'expires_in' => $this->expires_in));
        $this->saveLoginToDb($save);
    }
    protected function register_link($file_id,$size)
    {
        $dbt = \Database\getTransaction();
        if(is_a($dbt,'\Database\Transactions\CreateBook'))
        {
            $dbt->file_id=$file_id;
            $dbt->file_size=$size;
        }
        else
        {
            throw new \Exception('Cannot register file id: bad type');
        }
    }
    protected function getStoreFileId()
    {
        $store_fileid=null;

        if($this->bookid)
        {
            $dbase = \Database\odbc()->connect();
            if($dbase)
            {
                $sql=sprintf('SELECT FILE_ID FROM BOOKS_LINKS WHERE BOOK_ID=%s AND STORE_ID IN (SELECT ID FROM FILE_STORE WHERE VENDOR_CODE=\'%s\')',$this->getBookId(),$this->getDriveVendorName());
                $rec=$dbase->query($sql);
                if($rec->next())
                {
                    $store_fileid=$rec->field_value('FILE_ID');
                }
                $dbase->close();
            }
        }
        return $store_fileid;
    }
    protected function loadLoginFromDb()
    {
        $dbase=\Database\odbc()->connect();
        if($dbase)
        {
            $sql=sprintf('SELECT LOGIN_INFO FROM FILE_STORE WHERE VENDOR_CODE=\'%s\'',$this->getDriveVendorName());
            $rec=$dbase->query($sql);
            if($rec->next())
            {
                $log_info=$rec->field_value('LOG_INFO');
                $dbase->close();

                return $log_info;
            }
        }
        return null;
    }
    protected function saveLoginToDb($log_info=null)
    {
        $dbase=\Database\odbc()->connect();
        if($dbase)
        {
            $sql='UPDATE FILE_STORE SET LOGIN_INFO=';
            if($log_info)
            {
                $sql .= sprintf('\'%s\'',$log_info);
            }
            else
            {
                $sql .= 'NULL';
            }
            $sql .= sprintf(' WHERE VENDOR_CODE=\'%s\'',$this->getDriveVendorName());
            $dbase->query($sql);
            $dbase->close();
        }
    }
}

function redirectDriveClient($drive_code)
{
    unset($_GET['store_code']);

    switch($drive_code)
    {
        case 'GOOG':
            {
                header( 'Location: google_drive.php?' . http_build_query($_GET));
                break;
            }
        case 'MSOD':
            {
                header( 'Location: ms_onedrive.php?' . http_build_query($_GET));
                break;
            }
        case 'AMZN':
            {
                header( 'Location: amazon_cloud.php?' . http_build_query($_GET));
                break;
            }
        case 'BOX':
            {
                header( 'Location: box_drive.php?' . http_build_query($_GET));
                break;
            }
        case 'PCLD':
            {
                header( 'Location: pcloud_drive.php?' . http_build_query($_GET));
                break;
            }
        default:
            throw new \Exception('Unknown code for store: ' . $drive_code);
    }
}


if(isset($_GET['action']) && isset($_GET['store_code']))
{
    redirectDriveClient($_GET['store_code']);
}



?>