<?php
    
namespace Logs;

include_once "db.h";

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
    $e = new Entry();

    $bt=debug_backtrace()[1];

    $e->level=$level;
    $e->file=basename($bt['file']);
    $e->line=$bt['line'];
    $function = $bt['function'];
    if(isset($bt['class']))
    {
        $function = $bt['class'] . '::' . $function;
    }
    $e->function=str_replace('\\','\\\\', $function);
    $e->msg=str_replace('\'', '\\\'', $msg);
    return $e->insert();
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
    var_dump($entry);
    $ret=$entry->insert();
    var_dump($ret);
   
    return $ret;
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
