<?php
include "../db.php";
include "../Log.php";

if(count($_POST)>0)
{
    $dbase=\Database\odbc()->connect();
    if($dbase)
    {
        foreach($_POST as $key => $val)
        {
            if($_POST[$key]==='on')
            {
                $dbase->query("DELETE FROM LOGS WHERE ID=$key");
            }
        }
        $dbase->close();
    }
}

if(isset($_GET['logenabled']))
{
	\Logs\Config::instance()->enabled = ($_GET['logenabled']==='true' ? true : false) ;
}

if(isset($_GET['loglevel']))
{
	\Logs\Config::instance()->level = $_GET['loglevel'] ;
}

function printLogEntries()
{	
	echo '<table>';
	$dbase=\Database\odbc()->connect();
	if($dbase)
	{
			$sql_where_list='WHERE LEVEL';
			switch(\Logs\Config::instance()->level)
			{
				case \Logs\Config::ERROR:
					$sql_where_list = $sql_where_list . '=\'' . \Logs\Config::ERROR;
					break;
				case \Logs\Config::WARNING:
					$sql_where_list = $sql_where_list . ' IN (\'' . \Logs\Config::ERROR . '\',\'' . \Logs\Config::WARNING . '\')' ;
					break;
				case \Logs\Config::INFO: 
					$sql_where_list = $sql_where_list . ' IN (\'' . \Logs\Config::ERROR . '\',\'' . \Logs\Config::WARNING . '\',\'' . \Logs\Config::INFO . '\')' ;
					break;
				case \Logs\Config::DEBUG:
					$sql_where_list='';
					break;
				default:
					$sql_where_list='WHERE FALSE';
			}
			if($rec=$dbase->query('SELECT * FROM LOGS ' . $sql_where_list . ' ORDER BY ID'))
			{
					while($rec->next())
					{
							$msg=htmlspecialchars($rec->field_value('MSG'));
							$level=$rec->field_value('LEVEL');
							$file=$rec->field_value('FILE');
							$line=$rec->field_value('LINE');
							$function=$rec->field_value('FUNCTION');
							$time=$rec->field_value('TIME');
							$id=$rec->field_value('ID');
							$class='';
							if($msg==null) $msg='';							
							if($level==null) $level='';
							if($file==null) $file='';
							if($line==null) $line='';
							if($function==null) $function='';
							if($time==null) $time='';
							switch($level)
							{
								case \Logs\Config::ERROR: 	$class='error';break;
								case \Logs\Config::WARNING: $class='warning';break;
								case \Logs\Config::INFO: 		$class='info';break;
								case \Logs\Config::DEBUG: 	$class='debug';break;
							}
							echo sprintf("<tr class='%s' style='border: solid 0.5px'><td><input type='checkbox' name='%s'></td>
							<td width='25%%'>%s</td>
							<td>%s</td><td>%s</td>
							<td width='15%%'>%s</td><td width='15%%'>%s</td>
							<td>%s</td></tr>",$class,$id,$level,$function,$file,$line,$time,$msg);
					}
			}
			$dbase->close();
	}
	echo '</table>';
}
function printSelectControl($id)
{
	$err	=	\Logs\Config::ERROR;
	$warn	=	\Logs\Config::WARNING;
	$info	=	\Logs\Config::INFO;
	$dbg	=	\Logs\Config::DEBUG;
	echo 
		"<select id=\"$id\" onchange=\"onLevelChange(this)\">
			<option value=\"$err\">Error</option>
			<option value=\"$warn\">Warning</option>
			<option value=\"$info\">Info</option>
			<option value=\"$dbg\">Debug</option>
		</select>";
}
?>

<html>
<head>
    <title>logs viewer</title>
    <link type="text/css" rel="stylesheet" href="logview.css" />
    <script type="text/javascript">
        function selectall(chbox)
        {
            if(chbox.id==='chtop_1'){
                document.getElementById('chbot_1').checked = chbox.checked;
            }
            if (chbox.id === 'chbot_1') {
                document.getElementById('chtop_1').checked = chbox.checked;
            }
            var form = document.getElementById('log_form');
            if (form === null)
            {
                alert('form is null');
                return;
            }
            for (var i = 0; i < form.elements.length; ++i)
            {
                var item = form.elements.item(i);
                if(item.type!=undefined && item.type==='checkbox')
                {
                    item.checked = chbox.checked;
                }
            }
        }
		function enablelogs(me)
		{
			var url = new URL(window.location);
			window.location =  url.origin + url.pathname + '?logenabled=' + me.checked;
		}
		function onLevelChange(me)
		{
			var url = new URL(window.location);
			window.location =  url.origin + url.pathname + '?loglevel=' + me.value;
		}
		function reload(){
			window.location = window.location;
		}
    </script>
</head>
<body class='logview'>
    <input type="checkbox" id="chtop_1" onchange="selectall(this)" />Select All 
		<input type="checkbox" id="chtop_2" onchange="enablelogs(this)" />Logs ebnabled
		<?php printSelectControl('chtop_3'); ?> Log Level
	<br />

    <form id="log_form" action="logview.php" method="POST">
        <input type="submit" value="delete" />
				<button onclick='reload();'> refresh	</button>
        
				<?php printLogEntries();	?>
           
        <input type="submit" value="delete"/>
			<button onclick='reload();'> refresh</button>
    </form>
    <input type="checkbox" id="chbot_1" onchange="selectall(this)" /> Select All
		<input type="checkbox" id="chbot_2" onchange="enablelogs(this)" />Logs ebnabled
		<?php printSelectControl('chbot_3'); ?> Log Level
	<?php
		echo '<script type=\'text/javascript\'>';
		$enabled=(\Logs\Config::instance()->enabled) ? 'true' : 'false';
		$level=\Logs\Config::instance()->level;
		echo 'document.getElementById(\'chtop_2\').checked=' . $enabled . ';
					document.getElementById(\'chbot_2\').checked=' . $enabled . ';
					document.getElementById(\'chtop_3\').value=\'' . $level . '\';
					document.getElementById(\'chbot_3\').value=\'' . $level . '\';';
		echo '</script>';
	?>
</body>
</html>
