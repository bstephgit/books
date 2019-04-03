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
if(isset($_GET['edit']))
{
  header('Cache-Control: no-cache, no-store, must-revalidate');
}
$order='DESC';
if(isset($_GET['order']))
{
  $value=$_GET['order'];
  if($value==='DESC' || $value==='ASC')
  {
    $order=$value;
  }
}

if(isset($_GET['logout']))
{
  if (isset($_SESSION['user']))
  {
    unset($_SESSION['user']);
  }
}

$user = '';
if (isset($_SESSION['user']))
{
  $user = $_SESSION['user'];
}
else
{
  $link = null;
  if(isset($_GET['upload']))
  {
    $link = 'home.php';
    if(isset($_COOKIE['browse_backlink']))
    {
      $link = $_COOKIE['browse_backlink'];
    }
    $_SESSION['error'] = 'Not logged: book upload forbidden';
  }
  else if(isset($_GET['edit']))
  {
    $_SESSION['error'] = 'Not logged: book edition forbidden';
    $link = 'home.php?bookid=' . $_GET['edit'];
  }
  if($link!=null)
  {
    header('Location: ' . $link );
    exit;
  }
}

if(isset($_GET['errid']))
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
            $_SESSION['error'] = "<h2>ERR: $msg</h2><br><h3>FILE: $file</h3><br><h3>FUNCTION: $func</h3><br> <h3>LINE: $line</h3>";
        }
        $dbase->close();
    }
}
$error = '';
if(isset($_SESSION['error']))
{
  $error=$_SESSION['error'];
  unset($_SESSION['error']);
}
?>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <link type="text/css" rel="stylesheet" href="books.css"/>
    <script type='text/javascript' src='../zip.js/zip.js'></script>
    <script type='text/javascript' src='../zip.js/inflate.js'></script>
    <script type='text/javascript' src='../rar/uncompress.js'></script>
    <script type='text/javascript' src='store.js'></script>
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
<!-- <div class="container"> -->

<div class="nav">
    <div class="banner"></div>
    <div class='internal'>
        <div class='navtitle'><h3>Menu</h3></div>
        <div class="nav_elements">
          
            <?php
              if(strlen($user)==0)
              {
                printf('<a class="nav_element" href="#" onclick="user_login()">login</a><br>');
              }
              else{
                printf('<span>user: %s (<a class="nav_element" href="home.php?logout=1">logout</a></span>)<br>', $user);
              }
            ?>
            <a class="nav_element" href="home.php">home</a><br>
            <?php 
            if(isset($_GET['bookid'])) {
                //TO REMOVE
                if(strlen($user)>0)
                {
                  printf( '<a class="nav_element" href="upload.php?action=book_delete&bookid=%s" onclick="return confirm(\'delete book?\');">delete book</a><br>',$_GET['bookid']);
                  printf( '<a class="nav_element" href="home.php?edit=%s">edit book</a><br>',$_GET['bookid']);  
                }
                else
                {
                  printf( '<a class="nav_element" href="#" onclick="not_logged_msg()">delete book</a><br>');
                  printf( '<a class="nav_element" href="#" onclick="not_logged_msg()">edit book</a><br>');  
                }
            }
            else
            {
              $cmd_order='ASC';
              $label_order='ascendant order';
              if($order==='ASC')
              {
                $cmd_order = 'DESC';
                $label_order='descendant order';
              }
              $url="home.php";
              $sep="?";
              foreach($_GET as $key => $value)
              {
                if($key!=='order')
                {
                  $url= $url . $sep . $key . '=' . $value;
                  $sep = '&';
                }
              }
              $url = $url . $sep . "order=" . $cmd_order;
              printf('<a class="nav_element" href="%s">%s</a><br>',$url,$label_order);
            }
          ?>
          <?php if (strlen($user)>0){ ?>
            <a class="nav_element" href="home.php?upload=1">uploader</a><br>
          <?php  } else { ?>
            <a class="nav_element" href="#" onclick="not_logged_msg()">uploader</a><br>
          <?php }  ?>
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

<!-- </div> -->
   <!-- The Modal -->
<div id="modal_id" class="modal">
    <div class='internal modal-content' style='width: 20%; margin: auto;'>
      <span class="close">&times;</span>
      <div class='navtitle' id='modal_title'></div>
      <!-- Modal content -->
      <div class="nav_elements" id='modal_content'></div>
    </div>
</div>
  <script type='text/javascript'>
      
      function get_modal(){
        return document.getElementById('modal_id');
      }
    
      function set_modal_title(str)
      {
        document.getElementById("modal_title").innerHTML = str;
      }
    
      function set_modal_content(str)
      {
        document.getElementById("modal_content").innerHTML = str;
      }
      
      function show_modal()
      {
          get_modal().style.display = "block";
      }
      function user_login()
      {
        set_modal_title( '<h3>Please Login</h3>' );
        
        var content = '<form method="POST" action="store.php">' +
          '<input type="hidden" name="action" value="userlogin"><br>' +
           '<table class="nav_element">' +
            '<tr>' +
              '<td>User:</td><td><input type="text" name="user"/></td>' +
            '</tr>' +
            '<tr>' +
              '<td>Password:</td><td><input type="password" name="password"/></td>' +
            '</tr>' +
          '</table>' +
          '<input type="submit" value="Login" name="do_log">' +
         '</form>';
         set_modal_content(content);
         show_modal();
      }
      // Get the button that opens the modal
      // Get the <span> element that closes the modal
      var span = document.getElementsByClassName("close")[0];
      // When the user clicks on <span> (x), close the modal
      span.onclick = function() {
        get_modal().style.display = "none";
      }

      // When the user clicks anywhere outside of the modal, close it
      window.onclick = function(event) {
        var modal = get_modal();
        if (event.target == modal) {
          modal.style.display = "none";
        }
      }
      function error_modal(title,error_msg)
      {
        set_modal_title('<table class=\'nav_element\' style=\'margin: 10px\'><tr><td><img src=\'error.png\'></td><td><h3 style=\'margin-top: 15px\'>' + title + '</h3></td></tr></table>');
        set_modal_content(error_msg);
        show_modal();
      }
      <?php
      //show error
      if(strlen($error)>0)
      {
         printf("error_modal('Error','%s');",$error);
      }

      ?>
      function not_logged_msg()
      {
        error_modal('Not logged',"Only for authentified users.");
      }
    </script>
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
                    $links=$dbase->query('SELECT lnks.FILE_ID,lnks.FILE_SIZE,fs.VENDOR, fs.VENDOR_CODE FROM BOOKS_LINKS AS lnks, FILE_STORE AS fs WHERE lnks.BOOK_ID=' . $id . ' AND fs.ID=lnks.STORE_ID');
                    \utils\print_book($id,$rec,$subjects,$links);
                    $dbase->close();
                    printf( '<a class="nav_element" href="%s">back</a><br>',$_COOKIE['browse_backlink']);
                }
            }
            else //page or subject or search
            {
               \utils\printFooterLinks($order);
            }
        ?>
        </div>
    </div>
  <span style="text-align: right; color: white"><h5 style="padding-right: 15px"><i>&#169; Stéphane Samara</i></h5></span>
  </div>
</body>
</html>
