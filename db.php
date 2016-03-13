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
        $this->connexion = @mysql_connect( $server, $username, $password);
	    if($this->connexion)
        {
	        if(!mysql_select_db($db_name, $this->connexion))
            {
	        	mysql_close($this->connexion);
	        	$this->connexion = NULL;
	        }
	    }
	    return $this->connexion;
	}
    public function close()
    {
        if($this->connexion)
        {
            mysql_close($this->connexion);
            $this->connexion = NULL;
        }
    }
    
    public function query($sql_query)
    {
        if($this->connexion)
        {
            $response=mysql_query($sql_query,$this->connexion);
            if($response)
            {
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
        $this->current_row=mysql_fetch_assoc($this->response);
        return $this->current_row;
    }
    public function is_valid()
    {
        return $this->response!=NULL && $this->current_row!=NULL;
    }
    public function field_value($field_id)
    {
        return $this->current_row[$field_id];
    }
}

class ODBC
{
    private $_config;
    
    private function __construct()
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
            $current_node_name;
            while($xml->read())
            {
                $current_node_name=$xml->name;
                if($current_node_name=='host')
                {
                    if($xml->nodeType == XMLReader::ELEMENT)
                    {
                        $hname=$xml->getAttribute('name');
                        if($hname!=null)
                        {
                            $matching_host=($this_host==$hname);
                        }
                    }
                    if($xml->nodeType == XMLReader::END_ELEMENT)
                    {
                        $matching_host=false;
                    }
                    
                }
                
                if($matching_host && $xml->nodeType==XMLReader::TEXT)
                {
                    if(array_key_exists($current_node_name,$this->_config))
                    {
                        $this->_config=$xml->value;
                    }
                }
            }
            $xml->close();
            return has_valid_configuration();
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
                return db;
            }
        }
        return null;
    }
}
?>
