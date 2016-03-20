<?php

class DB
{
    private $connexion;
    
    public function __construct()
    {
        $connexion=NULL;
    }
    public function __destruct() 
    {
        $this->close();
    }
    public function connect($server,$username,$password,$db_name)
    {
		$this->close();
        $this->connexion = @mysqli_connect( $server, $username, $password, $db_name);
	    if(mysqli_connect_errno())
        {
	        $this->connexion = NULL;
	    }
	    return $this->connexion;
	}
    public function close()
    {
        if($this->connexion)
        {
            mysqli_close($this->connexion);
            $this->connexion = NULL;
        }
    }
    
    public function query($sql_query)
    {
        if($this->connexion)
        {
            $response=mysqli_query($this->connexion,$sql_query);
            if($response)
            {
                if(is_bool($response))
                {
                    return mysqli_insert_id($this->connexion);
                }
                return new Record($response);
            }
        }
        return NULL;
    }
    public function is_connected()
    {
        return $this->connexion!=NULL;
    }
}

class Record
{
    private $current_row;
    private $response;
    public function __construct($mysql_response)
    {
        $this->response=$mysql_response;
        $this->current_row = NULL;
    }
    
    public function next()
    {
        $this->current_row=mysqli_fetch_assoc($this->response);
        return $this->current_row;
    }
    public function is_valid()
    {
        return $this->response!=NULL && $this->current_row!=NULL;
    }
    public function field_value($field_id)
    {
        if($this->current_row)
        {
            return $this->current_row[$field_id];
        }
        return NULL;
    }
}

class ODBC
{
    private $_config;
    
    public function __construct()
    {
        $this->_config = array('server'=>null,'base'=>null,'user'=>null,'password'=>null);
    }
    public function load($file_path)
    {
        try{
            $xml = new XMLReader();
            if($xml->open($file_path)===false)
            {
                throw new Exception('Error opening odbc xml file: '.$file_path);
            }
            $this_host=$_SERVER['HTTP_HOST'];
            $matching_host=false;
            $element_name='';
            while($xml->read())
            {
                if($xml->nodeType==XMLReader::ELEMENT)
                {
                    $element_name=$xml->name;
                    if($element_name=='host')
                    {
                        $hname=$xml->getAttribute('name');
                        if($hname!=null)
                        {
                            $matching_host=($this_host==$hname);
                        }
                    }
                }
                if($xml->nodeType == XMLReader::END_ELEMENT)
                {
                    if($element_name=='host')
                    {
                        $matching_host=false;
                    }
                    $element_name='';
                }
                if($matching_host && $xml->nodeType==XMLReader::TEXT)
                {
                    if(array_key_exists($element_name,$this->_config))
                    {
                        $this->_config[$element_name]=$xml->value;
                    }
                }
            }
            $xml->close();
            return $this->has_valid_configuration();
        }
        catch(Exception $e)
        {
            return false;
        }
        return true;
    }
    public function has_valid_configuration()
    {
        return isset($this->_config['server']) && isset($this->_config['base']) && isset($this->_config['user']) && isset($this->_config['password']);
    }
    public function connect()
    {
        if($this->has_valid_configuration())
        {
            $db = new DB();
            $server=$this->_config['server'];
            $base=$this->_config['base'];
            $user=$this->_config['user'];
            $password=$this->_config['password'];
            $db->connect($server,$user,$password,$base);
            if($db->is_connected())
            {
                return $db;
            }
        }
        return null;
    }
    
    public function print_()
    {
        echo var_dump($this->_config);
    }
}

function loadOdbc()
{
    unset ($_SESSION['ODBC']);
    if(!isset($_SESSION['ODBC']))
    {
        $odbc=new ODBC();
        if($odbc->load('odbc.xml'))
        {
            $_SESSION['ODBC']=$odbc;
        }
    }
}

function odbc_connectDatabase()
{
    $base=null;
    if(isset($_SESSION['ODBC']))
    {
        $odbc=$_SESSION['ODBC'];
        $base = $odbc->connect();
    }
    return $base;
}

?>
