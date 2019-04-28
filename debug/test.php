<?php

include_once "../db.php";
//require_once "System.php";

session_start();

function list_books_with_bad_img_folder()
{
  $dbase = \Database\odbc()->connect();

  echo "<html><body>";
  
  $con = $dbase->con();
  $sql='SELECT ID,TITLE,IMG_PATH FROM BOOKS';
  
  $rec=$dbase->query($sql);
  while($rec->next())
  {
    $img_path = $rec->field_value('IMG_PATH');
    if(!is_file($img_path))
    {
      $book_id=$rec->field_value('ID');
      $book_title=$rec->field_value('TITLE');
      echo '['. $book_id  .'] "' . $book_title . '" ==> (' . $img_path . ')<br>';
    }
  }
  $dbase->close();

  echo "</body></html>";
}

function list_orphan_img_folders()
{
  $dbase = \Database\odbc()->connect();

  echo "<html><body>";
  //var_dump(\Database\odbc());
  //$_SESSION['ODBC']=NULL;
  $folder_list=scandir ('img/');
  //var_dump($folder_list);
  if (!$folder_list)
  {
    echo 'error scandir';
    exit();
  }

  foreach( $folder_list as $dir )
  {
    $path="img/$dir";
    if(!in_array($dir,array(".","..")) && is_dir($path) )
    {
      $con = $dbase->con();
      $sql='SELECT IMG_PATH FROM BOOKS WHERE IMG_PATH LIKE \'%' . mysqli_real_escape_string($con,$dir) . '%\'';
      $rec=$dbase->query($sql);
      if(!$rec->next())
      {

        $res = scandir($path);
        if($res && count($res)>2)
        {
          $done = false;
          foreach($res as $img_path)
          {
            if(!in_array($img_path,array(".","..")))
            {
              $whole = $path . '/' . $img_path;
              //$encoded = urlencode($whole);
              echo " <h2>$dir</h2><img src='$whole'> <a href='$whole' target='_blank'>$whole</a></img><br>";        
              $done=true;
            }
          }
          if(!$done)
            echo "<h2>Image not found in folder $path</h2>";
        }
        else
        {
          echo "<h2>$path not found</h2>";
        }

      }
    }
  }
  $dbase->close();

  echo "</body></html>";
}
function test_db()
{
		echo '<html><head><title>DB connection test</title></head><body>';
		echo '<p><u>Database test</u></p>';

		try{
        echo '<h3>' . $_SERVER['HTTP_HOST'] . '</h3>';

        unset($_SESSION['ODBC']);
				$dbase = \Database\odbc()->connect();
        //$dbase = @mysqli_connect( 'localhost', 'dbuser', 'aldu', 'bookstoredb');
				if ($dbase){
            $rec=$dbase->query('SELECT * FROM FILE_STORE WHERE VENDOR_CODE=\'OBM\'');
            if ($rec->next())
            {
              $log_info = $rec->field_value('LOGIN_INFO');
              echo '<p> LOG INFO = ' . $log_info . '</p>';
            }
            $dbase->query($sql);
						echo '<span style="color:green">database connected</span>';
            $dbase->close();
				}
				else{
						echo '<span style="color:red">database not connected</span>';
				}
		}catch(\Exception $e){
			echo '<span style="color:red">';
			var_dump($e);
			echo '</span>';
		}
		echo '</body></html>';
}
//list_books_with_bad_img_folder();
test_db();
//phpinfo();
//var_dump(class_exists('System', false));
//echo 'Login:<br>';

//$drive = new MSOneDriveHelper();
//var_dump($drive);

//$drive->login();
?>
