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
	    return $connexion;
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
?>
