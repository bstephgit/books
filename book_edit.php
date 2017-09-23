 
<?php   
include_once "db.php";

$upload = isset($_GET['upload']) && $_GET['upload']==='1';
$edit = isset($_GET['edit']);
$subjects=array();
$base=Database\odbc()->connect();

if($edit)
{
    $id=$_GET['edit'];
    $sql="SELECT * FROM BOOKS WHERE ID=$id";
    $rec_book=$base->query($sql);
    $sql="SELECT SUBJECT_ID FROM BOOKS_SUBJECTS_ASSOC WHERE BOOK_ID=$id";
    $rec_subjects=$base->query($sql);
    $book=array();
    if($rec_book->next())
    {
        $book['ID']=$rec_book->field_value('ID');
        $book['TITLE']=$rec_book->field_value('TITLE');
        $book['YEAR']=$rec_book->field_value('YEAR');
        $book['AUTHORS']=$rec_book->field_value('AUTHORS');
        $book['DESCR']=$rec_book->field_value('DESCR');
        $book['IMG_PATH']=$rec_book->field_value('IMG_PATH');
    }
    while($rec_subjects->next())
    {
        //$subjects[ sprintf('topic%s',$rec_subjects->field_value('SUBJECT_ID')) ]=1;
        $subjects[]=$rec_subjects->field_value('SUBJECT_ID');
    }
}
?>
<form method="POST" action="upload.php" id="form_upload">
  <table class="nav_element" cellpadding="15">
      <?php if ($upload) { ?>
    <tr><td><input type="file" id="file_upload"></td><td colspan="2"><div class='upload-out'><div class='upload-in' id='upl-in1'></div></div></td></tr>
      <?php } ?>
      <tr><td>Titre<br> <input type="text" name="title" size="40"> </td>
      <td>Auteur<br> <input type="text" name="author"> </td>
      <td>Ann&eacute;e de parution<br> <input type="text" name="year"></td></tr> 
      <tr><td colspan="3">Description<br><textarea rows="5" cols="90" name="descr">&lt;div class=&quot;scrollable&quot;&gt;&lt;/div&gt;</textarea></td></tr>

  </table>
  <?php if($upload){ ?>
    <div id="hidden_fields">
      <input type='hidden' name='file_name' id='fname'>
      <input type='hidden' name='file_size' id='fsize'>
      <input type='hidden' name='action' value='book_create'>                
      <input type='hidden' name='fileid' id='fileid'>                
      <input type='hidden' name='hash' id='hash'>
      <input type='hidden' name='imgfile' id='imgfile'>
    </div>

  <?php } if($edit){ ?>
      <input type='hidden' name='action' value='book_update'>
      <input type='hidden' name='bookid' value=''>
      <?php echo '<img id="preview" width="250" height="280" src="' . $book['IMG_PATH'] . '" style="cursor: hand" onclick="browseimage()"/>'; ?>
      <input type='file' name="imginput" id='imginput' style='visibility: hidden' onchange='loadimage(this)'>
      <input type='hidden' name='uploadflag' id='uploadflag' value='false'>
      <input type='hidden' name='imgfile' id='imgfile'>
  <?php } if($upload){ ?>

      <table class="nav_element">
        <tr>
          <td>
            <div class="nav_elements">
              <p>Store<br>
<!--
              <input type="radio" name="store" value="GOOG" checked> Google drive<br>
              <input type="radio" name="store" value="MSOD"> MS OneDrive<br>
              <input type="radio" name="store" value="AMZN"> Amazon<br>
              <input type="radio" name="store" value="BOX"> Box.com<br>
              <input type="radio" name="store" value="PCLD"> pCloud  </p>
-->
            <?php 
                  $oneGo = pow(1024,3);
                  $stores=$base->query('SELECT * FROM FILE_STORE');
                  while($stores->next())
                  {
                    $capacity = floatval($stores->field_value('STORAGE_CAPACITY') * $oneGo);
                    $store_id = $stores->field_value('ID');
                    $vendor = ucwords(strtolower($stores->field_value('VENDOR')));
                    $vendor_code = $stores->field_value('VENDOR_CODE');
                    
                    $sumrec=$base->query('SELECT SUM(FILE_SIZE) FROM BOOKS_LINKS WHERE STORE_ID=' . $store_id);
                    $sumrec->next(true);
                    $sumfiles = floatval($sumrec->field_value(0));
                    echo sprintf( '<input type="radio" name="store" value="%s"> %s (%02.2f%% used)<br>', $vendor_code, $vendor, ($sumfiles/$capacity*100.0) );
                  }
            ?>
                </p>
            </div>
         </td>
          <td width='500' align='right'><canvas id="preview" width="250" height="280" style="border:1px solid #000000;"></canvas></td>
     </tr>
   </table>
  <?php } ?>
  <div class="nav_elements">
      <?php  if($base){ \utils\print_subjects_tags($base,$subjects); $base->close();}?>
  </div>
  <p><input type="submit" value='uploader' id='submit_btn'></p>
</form>
<?php
if($edit)
{
    $script=sprintf(
            "var upload_form = document.getElementById('form_upload');
            upload_form.title.value=\"%s\";
            upload_form.author.value=\"%s\";
            upload_form.year.value=\"%s\";
            upload_form.descr.value=\"%s\";
            upload_form.bookid.value=\"%s\";
            upload_form.submit_btn.value='update';", 
            $book['TITLE'], $book['AUTHORS'], $book['YEAR'], str_replace(array("\r\n",'"','\''),array("<BR>",'\\"','\\\''),$book['DESCR']), $book['ID']);

    echo '<script type="text/javascript">';
        echo $script;
        /*foreach ($subjects as $key => $value) {
            printf('upload_form.%s.checked=true;',$key);
        }*/
    echo '</script>';
}
$base->close();
?>
            