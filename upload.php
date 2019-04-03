<?php

include_once "dbTransactions.php";
include_once "db.php";
include_once "Log.php";
include_once "utils.php";

session_start();


if(!is_dir(\utils\temp_dir()))
{
    mkdir(\utils\temp_dir());
}
if(!is_dir(\utils\img_dir()))
{
    mkdir(\utils\img_dir());
}

if(isset($_FILES) && isset($_FILES['upfile']))
{

    //$_FILES['upfile']['name']     //Le nom original du fichier, comme sur le disque du visiteur (exemple : mon_icone.png).
    //$_FILES['upfile']['type']     //Le type du fichier. Par exemple, cela peut être « image/png ».
    //$_FILES['upfile']['size']     //La taille du fichier en octets.
    //$_FILES['upfile']['tmp_name'] //L'adresse vers le fichier uploadé dans le répertoire temporaire.
    //$_FILES['upfile']['error']    //Le code d'erreur, qui permet de savoir si le fichier a bien été uploadé.

    if ($_FILES['upfile']['error'] > 0)
    {
         header('Location: home.php');
         $_SESSION['error'] = $_FILES['upfile']['error']);
         exit();
    }

    $headers = getallheaders();
    $resultat=false;
    if(isset($headers['content-range']) || isset($headers['Content-Range']))
    {
        $range_start=0;
        $range_end=0;
        $file_size=0;
        $filename = $_FILES['upfile']['name'];
        $filepath=\utils\temp_dir($filename);

        $hr='content-range';
        if(!isset($headers[$hr])) 
        {
            $hr='Content-Range';
        }
        sscanf($headers[$hr],'bytes %d-%d/%d',$range_start,$range_end,$file_size);
        if($range_start==0 && is_file($filepath))
        {
            unlink($filepath);
        }
        $hfile = fopen($filepath,'ab');
        if($hfile)
        {
            fwrite($hfile,file_get_contents($_FILES['upfile']['tmp_name']));
            fclose($hfile);
            http_response_code(200);
            exit();
        }
        else
        {
            header('HTTP/ 500 ' . error_get_last());
        }
    }
    else 
    {
        $resultat = move_uploaded_file($_FILES['upfile']['tmp_name'],\utils\temp_dir($_FILES['upfile']['name']));
        $img=\utils\img_dir($_FILES['upfile']['name']);
        
    }
}

if(isset($_POST['action']) && $_POST['action']==='book_create')
{
    $dbt=new \Database\Transactions\CreateBook();

    $dbt->title=$_POST['title'];$dbt->author=$_POST['author'];$dbt->descr=$_POST['descr'];
    $dbt->file_size=$dbt->size=$_POST['file_size'];$dbt->year=$_POST['year'];
    //$dbt->hash=hash_file('md5',\utils\temp_dir($_POST['file_name']));
    $dbt->hash=$_POST['hash'];
    try{
      $img=\utils\pdfGetImage($_POST['file_name'],$_POST['imgfile']);
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
    $dbt->vendor=$_POST['store'];
    $dbt->filename=$_POST['file_name'];
    $dbt->file_id=$_POST['fileid'];
    
    
    \Logs\logDebug($_POST['tags']);
    if(isset($_POST['tags']))
    {
      $dbt->subjects=explode('%0D',$_POST['tags']);
    }
    //\Database\storeTransaction($dbt);
    //$drive_url=sprintf('drive_client.php?action=upload&store_code=%s&file_name=%s',$_POST['store'],urlencode($_POST['file_name']));
    //header('Location: ' . $drive_url);
    try
    {
      \Logs\logDebug('commit transaction');
      $dbt->commit();
      header('Location: home.php?bookid=' . $dbt->bookid);
    }
    catch(\Exception $e)
    {
      $errid=\Logs\logException($e);
      header('Location: home.php?errid=' . $errid);
    }
}


if(isset($_GET['action']) && $_GET['action']==='book_delete')
{
    $dbase = \Database\odbc()->connect();
    $dbt = new \Database\Transactions\DeleteBook();
    $id=$dbt->bookid=$_GET['bookid'];
    

    $sql_query="SELECT VENDOR_CODE FROM FILE_STORE WHERE ID IN (SELECT STORE_ID FROM BOOKS_LINKS WHERE BOOK_ID=$id)";
    $rec=$dbase->query($sql_query);
    $vendor=null;
    if($rec->next())
    {
        $vendor=$rec->field_value('VENDOR_CODE');
    }

    $sql_query="SELECT IMG_PATH FROM BOOKS WHERE ID=$id";
    $rec=$dbase->query($sql_query);
    if($rec->next())
    {
      $img_path=$rec->field_value('IMG_PATH');
      if($img_path!==\utils\img_dir('book250x250.png'))
      {
        $count_rec=$dbase->query("SELECT COUNT(*) FROM BOOKS WHERE IMG_PATH='$img_path'");
        $count_rec->next(true);
        $nb_books=$count_rec->field_value(0);
        if($nb_books==1)
        {
          unlink($img_path);
          rmdir(dirname($img_path));
          \Logs\logDebug("Delete image path '$img_path'");
        }
        else
        {
          \Logs\logDebug("$nb_books book(s) have same image path: '$img_path'. Image not deleted.");
        }
      }
    }
    $dbase->close();
    if($vendor)
    {
        \Database\storeTransaction($dbt);
        $drive_url=sprintf('drive_client.php?action=delete&store_code=%s&bookid=%s',$vendor,$id);
        header('Location: ' . $drive_url);
    }
    else
    {
        $dbt->commit();
        header('Location: home.php');
    }
}

if(isset($_POST['action']) && $_POST['action']==='book_update')
{
    $dbase = \Database\odbc()->connect();
    $con=$dbase->con();
    $title=mysqli_real_escape_string($con,$_POST['title']);
    $author=mysqli_real_escape_string($con,$_POST['author']);
    $pub=$_POST['pub'];
    $descr=mysqli_real_escape_string($con,$_POST['descr']);
    $year=$_POST['year'];
    $id=$_POST['bookid'];
    $imgflag=$_POST['uploadflag'];
    $img_path='';  
    $update_query="UPDATE BOOKS SET TITLE='$title',DESCR='$descr',AUTHORS='$author',YEAR='$year'";
  
    if(isset($_POST['imgfile']) && strlen($_POST['imgfile']) > 0 && $imgflag==='true')
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
          $res=file_put_contents($img_path,base64_decode($_POST['imgfile']));
          if($res===false)
          {
            \Logs\logError('Error writing img content at ' . $img_path );
          }
          else
          {
            \Logs\logDebug('writing img content at ' . $img_path . '. ' . $res . ' bytes written.');
          }
        }
      }else \Logs\logWarning("IMG_PATH not found");
      
    }else {  
      \Logs\logDebug(sprintf("imgfile=%d imgflag=%s",isset($_POST['imgfile']), $imgflag)); 
    }
    \Logs\logDebug("update query: " . $update_query . " WHERE ID=$id");
    $res=$dbase->query($update_query . " WHERE ID=$id");
    
    $dbase->query("DELETE FROM BOOKS_SUBJECTS_ASSOC WHERE BOOK_ID=$id");
    
    $res=$dbase->query('SELECT ID FROM IT_SUBJECT');
    
    \Logs\logDebug($_POST['tags']);
  
    $subjects=array();
    if(isset($_POST['tags']))
    {
       $subjects=explode('%0D',$_POST['tags']);
    }
    foreach($subjects as $subject)
    {
      if(strlen($subject)>0)
      {
        $sub_id=\utils\selectOrCreateSubject($dbase,$subject);
        if($sub_id>0)
        {
            $dbase->query("INSERT INTO BOOKS_SUBJECTS_ASSOC (SUBJECT_ID,BOOK_ID) VALUES($sub_id,$id)");
        }
      }
    }
    $sql='DELETE FROM IT_SUBJECT WHERE ID NOT IN (SELECT SUBJECT_ID FROM BOOKS_SUBJECTS_ASSOC) AND PERMANENT=0';
    $dbase->query($sql);
    $dbase->close();
    header('Location: home.php?bookid='.$id);
    header('Cache-Control: no-cache, no-store, must-revalidate');
}

if(isset($_GET['action']) && $_GET['action']==='download')
{
    if(isset($_GET['bookid']))
    {
        $dbase = \Database\odbc()->connect();
        $bookid=$_GET['bookid'];
        $sql="SELECT VENDOR_CODE FROM FILE_STORE WHERE ID IN (SELECT STORE_ID FROM BOOKS_LINKS WHERE BOOK_ID=$bookid)";
        $rec=$dbase->query($sql);
        if($rec && $rec->next())
        {
            $store=$rec->field_value('VENDOR_CODE');
            $dbase->close();

            $drive_url=sprintf('drive_client.php?action=download&bookid=%s&store_code=%s',$bookid,$store);
            header('Location: ' . $drive_url);
        }
        else
        {
            $dbase->close();
            $errid=\Logs\logErr('cannot get vendor code from database');
            header('Location: home.php?errid=' . $errid);
        }
    }
    else
    {
        header('Location: home.php');
    }
}

if(isset($_GET['action']) && $_GET['action']==='login')
{
    if($_GET['store'])
    {
        $url = sprintf('drive_client.php?action=login&store_code=%s',$_GET['store']);
        header('Location: '. $url);
    }
    else
    {
        echo 'error: store code missing';
    }
}
?>
