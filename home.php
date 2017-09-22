<?php
include_once "db.php";
include_once "Log.php";
include_once "utils.php";

session_start();

if(isset($_GET["page"]) || isset($_GET["subject"]) || isset($_GET["search"]))
{
  \Logs\logInfo('Set Cookie \'browse_backlink\' ' . $_SERVER['REQUEST_URI']);
  setcookie("browse_backlink",$_SERVER['REQUEST_URI']);
}
?>
<html>
<head>
    <link type="text/css" rel="stylesheet" href="books.css"/>
    <script type='text/javascript' src='script.js'></script>
    <script type='text/javascript' src='tags.js'></script>
    <script type='text/javascript' src='https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.2/components/core-min.js'></script>
    <script type='text/javascript' src='https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.2/components/md5-min.js'></script>
    <script type='text/javascript' src='../pdf.js/build/pdf.js'></script>
    <script type='text/javascript'>
          
          function downloadFile(bookid)
          {
            var store = new Store('store.php?action=downloadLink&bookid='+bookid);

            store.onerror = function (err) {
              console.error(err);
              var msg=err.toString();
              if(err.status){ 
                var reader = new window.FileReader(); 
                reader.readAsDataURL(err.response); reader.onloadend = function() 
                {  var res = reader.result; msg = err.status + " " + atob(res.substr(res.indexOf(',')+1)); alert(msg); }
              }else{
                alert(msg);
              }
              
            };
            store.onlogin = function (obj) {
              store.download();
            };
            store.login();
          }
      function changepage(event)
      {
         var dochange = (event.type==='change') || (event.type==='keyup' && event.keyCode===13);
         if(dochange)
         {
           var pagectrl = document.getElementById('nbpage');
           var uobj = new URL(window.location.href);
           var rx = /page=\d+/;
           var url = uobj.protocol + '//' + uobj.hostname + uobj.pathname;
           var pageindex = Math.min(pagectrl.value,pagectrl.max);
           if(rx.test(uobj.search))
           {
              url = url + uobj.search.replace(rx,'page=' + pageindex);    
           }
           else
           {
              if(uobj.search.length===0)
              {
                url += '?'
              }
             else
             {
               url += uobj.search + '&';
             }
             url += 'page=' + pageindex;
           }
           window.location.href = url;
         }
      }
    </script>
</head>
<body>
<div class="container">

<div class="nav">
    <div class="banner"></div>
    <div class='internal'>
        <div class='navtitle'><h3>Menu</h3></div>
        <div class="nav_elements">
            <a class="nav_element" href="home.php">home</a><br>
            <?php if(isset($_GET['bookid'])) {
                printf( '<a class="nav_element" href="upload.php?action=book_delete&bookid=%s" onclick="return confirm(\'delete book?\');">delete book</a><br>',$_GET['bookid']);
                printf( '<a class="nav_element" href="home.php?edit=%s">edit book</a><br>',$_GET['bookid']);
            } ?>
            <a class="nav_element" href="home.php?upload=1">uploader</a><br>
        </div>
        
    </div>
    <div class='internal'>
        <div class='navtitle'><h3>Rechercher</h3></div>
        <div class="nav_elements">
          <form method="GET" action="home.php">
            <?php 
            if(isset($_GET["search"]))
            {
              printf("<input type='text' name='search' value='%s'>",$_GET["search"]);
            }
            else {
            ?>
            <p><input type='text' name="search"></p>  
            <?php } ?>
            <p><input type='submit' value='rechercher'></p>
           </form>
        </div>
    </div>
    <div class='internal'>
        <div class='navtitle'><h3>Sujets</h3></div>
        <div class="nav_elements">
            <div class='scrollable'>
            <?php 
                $base=Database\odbc()->connect();
                if($base && $base->is_connected())
                {
                    $rec = $base->query('SELECT ID,NAME FROM IT_SUBJECT ORDER BY NAME');
                    if($rec)
                    {
                        $sep="";
                        while($rec->next()){
                            echo $sep . '<a class="nav_element" id="subjects" href="home.php?subject=' . $rec->field_value('ID') . '">' .  $rec->field_value('NAME') . "</a>";
                            $sep=", ";
                        }
                    }
                    else {
                        echo mysql_error();
                    }
                    $base->close();
                }
            ?>
            </div>
        </div>
    </div>
    
</div>

<div class="main">
    <div class="banner"><h1 class="title">BOOK STORE</h1></div>

        <div class='internal'>
        <div class="nav_elements" style="border-radius: 15px">
        <?php  
            if (isset($_GET['upload']) || isset($_GET['edit'])){
              include "book_edit.php";
            }
            else if (isset($_GET['bookid']))
            { 
                $id=$_GET['bookid'];
                $dbase = Database\odbc()->connect();
                if($dbase->is_connected())
                {
                    $query_str="SELECT TITLE,YEAR,DESCR,AUTHORS,SIZE,IMG_PATH FROM BOOKS WHERE ID=$id";
                    $rec=$dbase->query($query_str);
                    $subjects=$dbase->query('SELECT ID,NAME FROM IT_SUBJECT WHERE ID IN (SELECT SUBJECT_ID FROM BOOKS_SUBJECTS_ASSOC WHERE BOOK_ID='.$id.') ORDER BY NAME');
                    $links=$dbase->query('SELECT lnks.FILE_ID,lnks.FILE_SIZE,fs.VENDOR FROM BOOKS_LINKS AS lnks, FILE_STORE AS fs WHERE lnks.BOOK_ID=' . $id . ' AND fs.ID=lnks.STORE_ID');
                    \utils\print_book($rec,$subjects,$links);
                    $dbase->close();
                    printf( '<a class="nav_element" href="%s">back</a><br>',$_COOKIE['browse_backlink']);
                }

            } 
        
            \utils\printFooterLinks();
        
            if($_GET['errid'])
            {
                $dbase = Database\odbc()->connect();
                if($dbase)
                {
                    $errid=$_GET['errid'];
                    $rec=$dbase->query("SELECT * FROM LOGS WHERE ID=$errid");
                    if($rec && $rec->next())
                    {
                        $file=$rec->field_value('FILE');
                        $line=$rec->field_value('LINE');
                        $func=$rec->field_value('FUNCTION');
                        $msg=$rec->field_value('MSG');
                        echo "<h2>ERR: $msg</h2><br>";
                        echo "<h3>FILE: $file</h3><br><h3>FUNCTION: $func</h3><br> <h3>LINE: $line</h3>";
                    }
                    $dbase->close();
                }
            }
        ?>
        </div>
    </div>
</div>
</div>
</body>
</html>
