<?php

namespace Database\Transaction;

include_once "db.php";
include_once "Log.php";

abstract class Transaction
{
	private $committed;
    private $properties;
	protected function __construct()
	{
		$this->committed=false;
        $this->properties=array();
	}
	public function __set($property,$value)
	{
		if($property!='committed' && array_key_exists($property,$this->properties))
		{
            if(is_array($this->properties[$property]) && !is_array($value))
            {
                array_push($this->properties[$property],$value);
            }
            else
            {
                $this->properties[$property]=$value;
            }
		}
	}
	public function commit()
	{
		if(!$this->committed)
		{
            $this->exec_commit();
			$this->committed=true;
		}
	}
	public function rollback()
	{
		if($this->committed)
		{
            $this->exec_rollback();
			$this->committed=false;
		}
	}
	abstract protected function exec_commit();
	abstract protected function exec_rollback();
    protected function create_prop($name,$value)
    {
        $this->properties[$name]=$value;
    }
}

class CreateBook extends Transaction
{
    public function __construct()
    {
        parent::__construct();
        $this->create_prop('file_name',null);
        $this->create_prop('hash',null);
        $this->create_prop('size',null);
        $this->create_prop('descr',null);
        $this->create_prop('year',null);
        $this->create_prop('title',null);
        $this->create_prop('name',null);
        $this->create_prop('author',null);
        $this->create_prop('img',null);
        $this->create_prop('vendor',null);
        $this->create_prop('subjects',array());
        $this->create_prop('file_id',null);
        $this->create_prop('file_size',null);
    }
    public function exec_commit()
    {
        $dbase = \Database\odbc()->connect();
        if($dbase)
        {
            $title=$this->title;
            $descr=$this->descr;
            $author=$this->author;
            $size=$this->size;
            $year=$this->year;
            $year=$this->year;
            $hash=$this->hash;
            $img=$this->img;
            $file_id=$this->file_id;
            $file_size=$this->file_size;
            $vendor=$this->vendor;

            $res=$dbase->query("INSERT INTO BOOKS (TITLE,DESCR,AUTHORS,SIZE,YEAR,HASH,IMG_PATH) VALUES('$title','$descr','$author',$size,'$year','$hash','$img')");
            if(!$res)
            {
                throw new \Exception('database error');
            }
            $id=$this->bookid = $res;

            foreach($this->subjects as $subject)
            {
                $dbase->query("INSERT INTO BOOKS_SUBJECTS_ASSOC (SUBJECT_ID,BOOK_ID) VALUES($subject,$id)");
            }

            $res=$dbase->query("SELECT ID FROM FILE_STORE WHERE VENDOR_CODE='$vendor'");
            $store_id=null;
            if($res->next())
            {
                $store_id=$res->field_value('ID');
            }
            else
            {
                throw new \Exception('cannot get file store id.');
            }
            $dbase->query("INSERT BOOKS_LINKS(BOOK_ID,STORE_ID,FILE_ID,FILE_SIZE) VALUES($id,$store_id,'$file_id',$file_size)");

            $dbase->close();
        }
    }
	public function exec_rollback()
    {
    }
}

class DeleteBook extends Transaction
{
    public function __construct()
    {
        parent::__construct();
        $this->create_prop('bookid',null);
    }
    public function exec_commit()
    {
         $dbase = \Database\odbc()->connect();
         if($dbase)
         {
             $bookid=$this->bookid;
             $dbase->query("DELETE FROM BOOKS WHERE ID=$bookid");
             $dbase->query("DELETE FROM BOOKS_LINKS WHERE BOOK_ID=$bookid");
             $dbase->query("DELETE FROM BOOKS_SUBJECTS_ASSOC WHERE BOOK_ID=$bookid");
             $dbase->close();
         }
    }
    public function exec_rollback()
    {
    }
}

?>