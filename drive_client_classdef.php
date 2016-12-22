<?php

namespace Drive;

include_once "db.php";
include_once "dbTransactions.php";
include_once "Log.php";


abstract class Client
{
    private $file_name;
    private $bookid;
    private $vendor_name;
    private $action;
    protected $token;

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
        if(isset($_GET['bookid']))
        {
            $_SESSION[$this->vendor_name]['current_bookid']=$_GET['bookid'];
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
        
        $this->initFromDb();

    }
    public function setFileName($file_name) { $this->file_name = $file_name; }
    public function getFileName(){ return $this->file_name; }
    public function getBookId() { return $this->bookid; }
    public function setBookId($bid) { $this->bookid=$bid; }
    public function isLogged() { return ($this->getAccessToken() && !$this->isExpired()); }
    public function getAccessToken() { if($this->token) return $this->token->access_token; return null; }

    protected abstract function uploadFile();
    protected abstract function downloadFile();
    protected abstract function deleteFile();
    protected abstract function getDriveVendorName();
    protected abstract function getRedirectUrl();
    protected abstract function isExpired();
    protected abstract function onCode($code);
    protected abstract function refreshToken();

    protected abstract function store_info();    
    protected abstract function downloadLink($fileid);

    public function execute()
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
            if($ret && $ret>0)
            {
                header('Location: home.php?errid=' . $ret);
            }
        }
    }

    public function login()
    {

        if($this->getAccessToken())
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
            return ($this->getAccessToken()!=null);
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
                    unlink('temp/'.$dbt->filename);
                    header('Location: home.php?bookid=' . $this->getBookId() );
                }
                break;
            case 'download':
                {
                    $this->downloadFile();
                    header('Location: home.php?bookid=' . $this->getBookId());
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
            case 'login':
                header('Location: store.php?action=login&store_code='. $this->getDriveVendorName().'&html=true');
                break;
            case 'downloadLink':
                header('Location: store.php?action=downloadLink&bookid='. $this->getBookId().'&html=true');
                break;
            default:
                throw new \Exception('Unknown action: ' . $this->action);
        }
    }

    protected function getSessionVar($var_name)
    {
        if(isset($_SESSION[$this->vendor_name][$var_name]))
        {
            return $_SESSION[$this->vendor_name][$var_name];
        }
        return null;
    }
    protected function setSessionVar($var_name,$value)
    {
        $_SESSION[$this->vendor_name][$var_name]=$value;
    }
    protected function unsetSessionVar($var_name)
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
      $data=$this->loadLoginFromDb();
      if($data)
      {
        $this->token=json_decode($data);
      }
    }
    protected function set_token($obj)
    {
        if(is_string($obj))
        {
            $obj=json_decode($obj);
        }
        $this->token=$obj;
        if($this->token && !property_exists($this->token,'created'))
        {
            $this->token->{'created'}=time();
        }

        if($this->token && property_exists($this->token,'access_token'))
        {
            $this->saveLoginToDb(json_encode($this->token));
        }
        else
        {
            $this->saveLoginToDb(null);
        }
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
        $log_info=null;
        $dbase=\Database\odbc()->connect();
        if($dbase)
        {
            $sql=sprintf('SELECT LOGIN_INFO FROM FILE_STORE WHERE VENDOR_CODE=\'%s\'',$this->getDriveVendorName());
            $rec=$dbase->query($sql);
            if($rec->next())
            {
                $log_info=$rec->field_value('LOGIN_INFO');
            }
            $dbase->close();
        }
        return $log_info;
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
    protected function downloadToBrowser($tempfile,$content)
    {
        file_put_contents($tempfile, $content);

        $dbase=\Database\odbc()->connect();
        if($dbase)
        {
            $sql='SELECT FILE_SIZE,FILE_NAME FROM BOOKS_LINKS WHERE BOOK_ID=' . $this->getBookId();
            $rec=$dbase->query($sql);
            if($rec && $rec->next())
            {
                $filename = $rec->field_value('FILE_NAME');
                $filesize = filesize($tempfile);
                $dbase->close();

                header("Content-Description: File Transfer");
                header("Content-Disposition: attachment; filename=\"$filename\"");
                header("Content-Transfer-Encoding: binary");
                header("Content-Length: " . $filesize);
                header('Content-Type: application/octet-stream');
                readfile($tempfile);
                unlink($tempfile);
            }
            else
            {
                $dbase->close();
                throw new \Exception('cannot get file name and size from database');
            }
        }
        else
        {
            throw new \Exception('cannot connect to database');
        }
    }
}



?>