<?php
    
namespace Logs;

include_once "db.php";

function isLogsActivated()
{
  return true;
}
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
  if(isLogsActivated())
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
    return logBase('INFO',$msg);
}

function logErr($msg)
{
    return logBase('ERR',$msg);
}
function logException(\Exception $e)
{
  if(isLogsActivated())
  {
    $entry=new Entry();
    $entry->level='ERR';
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
    return logBase('WARN',$msg);
}

function logDebug($msg)
{
    return logBase('DEBUG',$msg);
}
?>
