<?php
include_once "db.php";
include_once "utils.php";
include_once "Log.php" ;
include_once "dbTransactions.php";

function getBook($id,$database)
{
  $obj=null;
  $query_str="SELECT ID,TITLE,YEAR,DESCR,AUTHORS,SIZE,IMG_PATH,HASH FROM BOOKS WHERE ID=$id";
  $rec=$database->query($query_str);
  if($rec->next())
  {
     $id = $rec->field_value('ID');
     $title = $rec->field_value('TITLE');
     $year = $rec->field_value('YEAR');
     $authors = $rec->field_value('AUTHORS');
     $description = $rec->field_value('DESCR');
     $size = $rec->field_value('SIZE');
     $hash = $rec->field_value('HASH');
      
     $img_path = $rec->field_value('IMG_PATH');
     $obj=(object)array('id' => $id, 'title' => $title, 'authors' => $authors, 'year' => intval($year), 'description' =>  utf8_encode($description), 'size' => $size, 
                        'hash' => $hash, 'img_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/books/' . utf8_encode($rec->field_value('IMG_PATH')) );
     //var_dump($description,true);
     $rec_subjects=$database->query('SELECT ID,NAME FROM IT_SUBJECT WHERE ID IN (SELECT SUBJECT_ID FROM BOOKS_SUBJECTS_ASSOC WHERE BOOK_ID='.$id.') ORDER BY NAME');
     $subjects = array();
     while($rec_subjects->next())
     {
       $subject_id=$rec_subjects->field_value('ID');
       $subject_name=$rec_subjects->field_value('NAME');
       array_push($subjects,(object)array('id' => $subject_id, 'name' => $subject_name));
     }
     $obj->subjects = $subjects;
     $rec_link=$database->query('SELECT lnks.FILE_ID,lnks.FILE_SIZE,lnks.FILE_NAME,fs.VENDOR, fs.VENDOR_CODE FROM BOOKS_LINKS AS lnks, FILE_STORE AS fs WHERE lnks.BOOK_ID=' . $id . ' AND fs.ID=lnks.STORE_ID');
     $link = (object)array();
     if($rec_link->next())
     {
       $file_id=$rec_link->field_value('FILE_ID');
       $file_size=$rec_link->field_value('FILE_SIZE');
       $file_name=$rec_link->field_value('FILE_NAME');
       $vendor = $rec_link->field_value('VENDOR');
       $vendor_code = $rec_link->field_value('VENDOR_CODE');
       $link = (object)array('file_id' => $file_id, 'file_name' => $file_name, 'file_size' => $file_size, 'vendor' => $vendor, 'vendor_code' => $vendor_code);
     }
    try
    {
      $drive = \utils\createDriveClient($link->vendor_code);
      $drive->login();
      $link->access_token = $drive->getAccessToken();
      $obj->link = $link;
    }
    catch(\Exception $e)
    {
      $obj = (object)array( 'error' => $e->getMessage() );
    }

  }
  else
  {
    $obj = (object) array('error' => 'Book not found id=' . strval($id) );
  }
  return $obj;
}

//response object
$resp = null;

if(isset($_SERVER['REQUEST_METHOD']))
{
  $method=$_SERVER['REQUEST_METHOD'];
  if($method==='GET')
  {
    if(isset($_GET['page']) || isset($_GET['subject']) || isset($_GET['search']))
    {
       $resp=array();
       $page=1;
       $nb_elem=6;
       if(isset($_GET['page']))
       {
         $page=intval($_GET['page']);
       }
       $offset=(($page-1)*$nb_elem);

       $dbase = \Database\odbc()->connect();
       $has_subject=false;
       $has_search=false;
       $subject='';
       $search_str='';
       $search_regex='';
       if(isset($_GET['subject']))
       {
         $has_subject=true;
         $subject=$_GET['subject'];
         $sql="SELECT COUNT(*) FROM BOOKS WHERE ID IN (SELECT BOOK_ID FROM BOOKS_SUBJECTS_ASSOC WHERE SUBJECT_ID=$subject)";
       }
       else if(isset($_GET['search']))
       {
         $search_str=urlencode($_GET['search']);
         $has_search=true;
         $searchstring=$_GET['search'];
         $tok = strtok($searchstring,' ');
         $sep='';
         while($tok!=false)
         {
           $search_regex = $search_regex . $sep . \utils\escapeRegExpChars(mysqli_real_escape_string($dbase->con(),$tok));
           $sep = '[[:blank:]]+';
           $tok = strtok(' ');
         }
         $sql="SELECT COUNT(*) FROM BOOKS WHERE TITLE REGEXP '$search_regex'";
       }
       else
       {
         $sql='SELECT COUNT(*) FROM BOOKS';
       }

       $rec=$dbase->query($sql);
       $rec->next(true);
       $count=$rec->field_value(0);

       $page_max=intval(ceil($count/$nb_elem));
       $page=min($page,$page_max);

      if($has_subject)
      {
        $sql="SELECT ID,TITLE,IMG_PATH FROM BOOKS WHERE ID IN (SELECT BOOK_ID FROM BOOKS_SUBJECTS_ASSOC WHERE SUBJECT_ID=$subject) ORDER BY ID LIMIT $nb_elem OFFSET $offset";
      }
      else if($has_search)
      {
        $sql="SELECT ID,TITLE,IMG_PATH FROM BOOKS WHERE TITLE REGEXP '$search_regex' ORDER BY ID LIMIT $nb_elem OFFSET $offset";
      }
      else
      {
       $sql="SELECT ID,TITLE,IMG_PATH FROM BOOKS ORDER BY ID LIMIT $nb_elem OFFSET $offset";
      }
      $rec=$dbase->query($sql);
      if($rec)
      {
          while($rec->next())
          {
            array_push( $resp, (object) array( 'id' => $rec->field_value('ID'), 'title' => utf8_encode($rec->field_value('TITLE')),  'img_path' => 'https://' . $_SERVER['HTTP_HOST'] . '/books/' . utf8_encode($rec->field_value('IMG_PATH'))) );
          }
      }
      $resp = (object) array('number' => $page, 'items' => $resp, 'nb_pages' => $page_max );
      $dbase->close();
    }
    else if(isset($_GET['bookid']))
    {
      $id=$_GET['bookid'];
      try
      {
        $dbase = Database\odbc()->connect();
        if($dbase->is_connected())
        {
            $resp = getBook($id,$dbase);
            $dbase->close();
        }
        else
        {
          $resp = (object) array('error' => 'cannot connect database');
        }
      }
      catch(\Exception $e)
      {
        $resp = (object) array('error' => $e.getMessage());
      }
    }
    else if(isset($_GET['allsubjects']))
    {
      $base=Database\odbc()->connect();
      if($base && $base->is_connected())
      {
          $resp = array();
          $rec = $base->query('SELECT ID,NAME FROM IT_SUBJECT ORDER BY NAME');
          if($rec)
          {
             while($rec->next())
             {
               array_push($resp, (object)array( 'id' => $rec->field_value('ID')  , 'name' => $rec->field_value('NAME') ) );

             }
          }
          else {
              $resp = (object)array( 'error' => stringval(mysql_error() ));
          }
          $base->close();
      }
      else
      {
        $resp = (object) array('error' => 'cannot connect database');
      }
    }
    else if(isset($_GET['vendor']))
    {
      try
      {
        $query = $_GET['vendor'];
        $dbase = Database\odbc()->connect();
        if($dbase->is_connected())
        {
          $sql = 'SELECT ID,VENDOR,VENDOR_CODE FROM FILE_STORE';
          
          if($query=='allvendors')
          {
            $rec = $dbase->query($sql);
            $resp=array();
            while($rec->next())
            {
              array_push($resp,(object)array('id' => $rec->field_value('ID'), 'vendor' => $rec->field_value('VENDOR'), 'vendor_code' => $rec->field_value('VENDOR_CODE')));
            }
          }
          else
          {
            if (intval($query)>0)
            {
              $sql = $sql . ' WHERE ID=' . $query;
            }
            else
            {
              $sql = $sql . ' WHERE VENDOR_CODE=\'' . $query . '\'';
            }
            $rec = $dbase->query($sql);
            if($rec->next())
            {
              $resp = (object)array('id' => $rec->field_value('ID'), 'vendor' => $rec->field_value('VENDOR'), 'vendor_code' => $rec->field_value('VENDOR_CODE'));
            }
          }
          
          $dbase->close();
        }
        else
        {
          $resp = (object) array('error' => 'cannot connect database');
        }
      }
      catch(\Exception $e)
      {
        $resp = (object) array('error' => $e.getMessage());
      }
    }
  }
  else if($method==='POST')
  {
    $data = json_decode(file_get_contents('php://input'), false);
    $dbt=new \Database\Transactions\CreateBook();
    
    $dbt->title=$data->title;$dbt->author=$data->authors;$dbt->descr=$data->description;
    $dbt->file_size=$dbt->size=$data->size;$dbt->year=$data->year;
    
    //$dbt->hash=hash_file('md5',\utils\temp_dir($_POST['file_name']));
    $dbt->hash=$data->hash;
    try{
      $img=\utils\pdfGetImage($data->link->file_name,$data->img_url);
    }catch(\Exception $e)
    {
      \Logs\logException($e);
      $img=''; 
    }
    if(strlen($img)==0)
    {
      $img=\utils\img_dir('book250x250.png');
    }  
    
    $dbt->img=$img;
    $dbt->vendor=$data->link->vendor;
    $dbt->filename=$data->link->file_name;
    $dbt->file_id=$data->link->file_id;
    
    
    \Logs\logDebug(var_export($data->subjects));
    if(isset($data->subjects))
    {
      $dbt->subjects=$data->subjects;
    }
    //\Database\storeTransaction($dbt);
    //$drive_url=sprintf('drive_client.php?action=upload&store_code=%s&file_name=%s',$_POST['store'],urlencode($_POST['file_name']));
    //header('Location: ' . $drive_url);
    try
    {
      \Logs\logDebug('commit transaction');
      $dbt->commit();
      $db = \Database\odbc()->connect();
      try
      {
        if($db->is_connected())
        {
          $resp = getBook($dbt->bookid,$db);
        }
        else
        {
          $resp = (object)array('error' => 'cannot return book: database connection failed');
        }
      }
      catch(\Exception $e)
      {
        $resp = (object) array('error' => $e->getMessage());
      }
      finally
      {
        $db->close();
      }      
    }
    catch(\Exception $e)
    {
      $errid=\Logs\logException($e);
      $resp = (object)array('error' => $e->getMessage());
    }
  }
  else if($method==='PUT')
  {
    try
    {
      $dbase = \Database\odbc()->connect();
      $con=$dbase->con();
      $data = json_decode(file_get_contents('php://input'), false);
      $title=mysqli_real_escape_string($con,$data->title);
      $author=mysqli_real_escape_string($con,$data->authors);
      $descr=mysqli_real_escape_string($con,$data->description);
      $year=$data->year;
      $id=$data->id;
      \Logs\logDebug(var_export($data,true));
      $update_query="UPDATE BOOKS SET TITLE='$title',DESCR='$descr',AUTHORS='$author',YEAR='$year'";
      $imgfile = $data->img_url;
      if(strlen($imgfile) > 0 )
      {

        $res=$dbase->query("SELECT IMG_PATH, FILE_NAME FROM BOOKS B, BOOKS_LINKS L WHERE B.ID=$id AND L.BOOK_ID=B.ID");
        if($res->next(false))
        {
          $img_path=$res->field_value('IMG_PATH');
          $img_basename= '[' . pathinfo($res->field_value('FILE_NAME'))['filename'] . ']';
          if($img_path!=null && strlen($img_path)>0 && $img_path!==\utils\img_dir('book250x250.png'))
          {
            if(is_file($img_path))
            {
              \Logs\logDebug('unlink ' . $img_path);
              unlink($img_path); 
            }
          }
          else
          {
            $img_path=\utils\img_dir($img_basename) . '/img.png';
            \Logs\logDebug("new img path: ".$img_path);
            $update_query = $update_query . ', IMG_PATH=\'' . mysqli_real_escape_string($con,$img_path) . '\'';
          }
          if(strlen($img_path))
          {
            if(!is_dir(\utils\img_dir($img_basename)))
            {
              mkdir(\utils\img_dir($img_basename));
            }
            $res=file_put_contents($img_path,base64_decode($data->img_url));
            if($res===false)
            {
              \Logs\logError('Error writing img content at ' . $img_path );
            }
            else
            {
              \Logs\logDebug('writing img content at ' . $img_path . '. ' . $res . ' bytes written.');
            }
          }
        }else {\Logs\logWarning("IMG_PATH not found");}

      }
      \Logs\logDebug("update query: " . $update_query . " WHERE ID=$id");
      $res=$dbase->query($update_query . " WHERE ID=$id");
    
      $dbase->query("DELETE FROM BOOKS_SUBJECTS_ASSOC WHERE BOOK_ID=$id");

      $res=$dbase->query('SELECT ID FROM IT_SUBJECT');

      \Logs\logDebug($data->subjects);

      if(property_exists($data,'subjects'))
      {
        foreach($data->subjects as $subject)
        {
          if(strlen($subject->name)>0)
          {
            $sub_id=\utils\selectOrCreateSubject($dbase,$subject->name);
            if($sub_id>0)
            {
                $dbase->query("INSERT INTO BOOKS_SUBJECTS_ASSOC (SUBJECT_ID,BOOK_ID) VALUES($sub_id,$id)");
            }
            else
            {
              \Logs\logWarning('subject not updated '. var_dump($subject,false));
            }
          }
        }
        $sql='DELETE FROM IT_SUBJECT WHERE ID NOT IN (SELECT SUBJECT_ID FROM BOOKS_SUBJECTS_ASSOC GROUP BY SUBJECT_ID) AND PERMANENT=0';
        $dbase->query($sql);
      }
      else
      {
        \Logs\logWarning('no subjects found in posted data');
      }
      $resp = getBook($id,$dbase);
    }
    catch(\Exception $e)
    {
      $resp = (object)array('error' => $e->getMessage());
    }
    finally
    {
      $dbase->close();
    }
  }
  else if($method==='DELETE')
  {

  }
  else
  {
    $resp = array('error' => 'HTTP Method not handled:\'' . $method . '\'');
  }
}
else
{
  $resp = (object) array( 'error' => 'HTTP error: no request method: ' );
  
}
header("Content-type:application/json");          
echo json_encode($resp,JSON_UNESCAPED_SLASHES);

?>