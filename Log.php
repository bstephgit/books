<?php
    
namespace Logs;

include_once "db.php";

class Entry
{
    private $msg;
    private $level;
    private $function;
    private $file;
    private $line;

    public function __set($prop,$val)
    {
        switch($prop)
        {
            case 'msg':
            case 'level':
            case 'file':
            case 'line':
            case 'function':
                $this->$prop=$val;
        }
    }
    public function insert()
    {
        $ret=false;
        if($dbase=\Database\odbc()->connect())
        {
            $sql=sprintf('INSERT INTO LOGS(LEVEL,TIME,FILE,LINE,FUNCTION,MSG) VALUES(\'%s\',NOW(),\'%s\',\'%s\',\'%s\',\'%s\')',$this->level,$this->file,$this->line,$this->function,$this->msg);
            $ret=$dbase->query($sql);
            $dbase->close();
        }
        return $ret;
    }
}

function logBase($level,$msg)
{
  if(Config::instance()->enabled)
  {
    $e = new Entry();

    $bt=debug_backtrace()[1];

    $e->level=$level;
    $e->file=basename($bt['file']);
    $e->line=$bt['line'];
    $function = debug_backtrace()[2]['function'];
    if(isset($bt['class']))
    {
        $function = $bt['class'] . '::' . $function;
    }
    $e->function=str_replace('\\','\\\\', $function);
    $e->msg=str_replace('\'', '\\\'', $msg);
    return $e->insert();
  }
  return true;
}
    
function logInfo($msg)
{
  if(Config::instance()->isLogLevelEnough(Config::INFO))
  {
    return logBase(Config::INFO,$msg);
  }
  return false;
}

function logErr($msg)
{
  if(Config::instance()->isLogLevelEnough(Config::ERROR))
  {
    return logBase(Config::ERROR,$msg);
  }
  return false; 
}
function logException(\Exception $e)
{
  if(Config::instance()->enabled)
  {
    $entry=new Entry();
    $entry->level=Config::ERROR;
    $entry->msg=str_replace('\'', '\\\'', $e->getMessage());
    $entry->file=basename($e->getFile());
    $entry->line=strval($e->getLine());
    $t = $e->getTrace()[0];
    $function='';
    if(isset($t['class']))
    {
        $function=$t['class'] . '::';
    }
    $function .= $t['function'];
    $entry->function=str_replace('\\','\\\\', $function) ;
    $ret=$entry->insert();
   
    return $ret;
  }
  return true;
}
function logWarning($msg)
{
    if(Config::instance()->isLogLevelEnough(Config::WARNING))
    {
      return logBase(Config::WARNING,$msg);
    }
    return false;
}

function logDebug($msg)
{
  if(Config::instance()->isLogLevelEnough(Config::DEBUG))
  {
    return logBase(Config::DEBUG,$msg);
  }
  return false;
}

class Config
{
    private $_config;
    private static $_singleton=null;
    const INFO='INFO';    
    const WARNING='WARN';
    const ERROR='ERR';
    const DEBUG='DEBUG';
      
    public static function instance()
    {
      if(self::$_singleton===null)
      {
         self::$_singleton = new Config;
      }
      return self::$_singleton;
    }
    private function __construct()
    {
        $this->_config = (object)array('enabled'=>false,'level'=>self::INFO);
        $this->load();
    }
    private function load()
    {
        if(file_exists ('logs.json'))
        {
          $this->_config=json_decode(file_get_contents('logs.json'));
        }
       
    }
    private function save()
    {
       file_put_contents('logs.json',json_encode($this->_config));
    }
    public function __get($property)
    {
      if($property==='enabled')
      {
        return $this->_config->enabled;
      }
      if($property==='level')
      {
        return $this->_config->level;
      }
      return null;
    }
    
    public function __set($property,$value)
    {
      if($property==='enabled' && is_bool($value))
      {
        $this->_config->enabled=$value;
        $this->save();
      }
      if($property==='level' && is_string($value))
      {
        if($value!==$this->_config->level)
        {
          $ok=false;
          switch($value)
          {
            case self::INFO:
            case self::ERROR:
            case self::WARNING:
            case self::DEBUG:
              $ok=true;
              
          }
          if($ok)
          {
            $this->_config->level=$value;
            $this->save();
          }
        }
      }
    }
    public function isLogLevelEnough($level)
    {
      $nb1 = self::getLevelNumber($level);
      $nb2 = self::getLevelNumber($this->_config->level);
      return $nb2 >= $nb1;
    }
    private static function getLevelNumber($level)
    {
      switch($level)
      {
        case self::ERROR: return 0;
        case self::WARNING: return 1;
        case self::INFO: return 2;
        case self::DEBUG: return 3;
        default: return 4;
      }
    }
}
?>
