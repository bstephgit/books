<?php

require_once "db.php";

//header( "Location: drive_client.php?action=reparent&store_code=PCLD" );

$dbase = \Database\odbc()->connect();

$stores = array();

if ($dbase)
{
	$rec=$dbase->query('SELECT ID, VENDOR, VENDOR_CODE, LOGIN_INFO FROM FILE_STORE');
	while($rec->next(true))
	{
		$store_data = (object)array(
			"id" => $rec->field_value(0),
			"name" => $rec->field_value(1),
			"code" => $rec->field_value(2),
			"login" => json_decode($rec->field_value(3))
		);
		array_push($stores, $store_data);
	}
	$dbase->close();

}

?>

<!DOCTYPE html>
<html>
<head>
	<title>API Test</title>
    <script type='text/javascript' src='store.js'></script>
</head>
<body>

<script type="text/javascript">

	var selected_store;

	function getStores(){
		<?php
			echo '			return ' . json_encode($stores)  . ';';

		?>
	}

	function findStore(code){
		var stores = getStores();
		for (i in stores)
		{
			if (stores[i].code==code) 
				return stores[i];
		}
		return null;
	}

	function make_list(obj)
	{

		if (Array.isArray(obj))
		{
			var code = '<ul>';
			for(index in obj)
				code += '<li>' + make_list(obj[index]) + '</li>';

			return code + '</ul>';
		}
		
		if (typeof(obj)=='object')
		{
			var code = '<ul>';
			for(prop in obj)
				code += '<li>' + prop + ":\t" + make_list(obj[prop]) + '</li>';

			return code + '</ul>';
		}
		if (obj.length > 50 )
			return '<textarea>' + obj + '</textarea>';
		return obj;
	}

	function setStore(code){

		var store = findStore(code);
		var container = document.getElementById('store_data');
		var txt = '';

		selected_store = store;

		if (store!=null){
			txt += '<ul>';
			for(prop in store)
				txt += '<li>' + prop + ':\t' + make_list(store[prop]) + '</li>';
			txt += '</ul>';
		}
		container.innerHTML = txt;
	}

	function refreshLogin(){
		if (selected_store){
			var code = selected_store.code;
			var url = '/books/store.php?action=login&store_code=' + code;
        	var store = new Store(url);

        	console.log('login url:', url);
        	store.onlogin = function (obj){
        		console.log(obj);
        		var store_obj = findStore(code);
        		if (store_obj){
        			store_obj.login = obj;
        			if (selected_store.code==code){
        				setStore(code);
        			}
        		}
        	};
        	store.login();

		}
	}

</script>
<table><tr><td>
<?php 

	$str='';
	foreach ($stores as $s) {
		# code...
		echo sprintf('<input type="radio" name="store" value="%s" onchange="setStore(this.value)">%s<br>',$s->code, $s->name);
	}

?>
</td><td><button onclick="refreshLogin()">Refresh</button></td>
<td>
Method <select>
	<option>GET</option>
	<option>POST</option>
	<option>PUT</option>
</select><br>	
	
	
URL <input type='text' id='url'><br>
Headers <textarea id="request_headers" placeholder="Headers"></textarea><br>
Body <textarea placeholder="body"></textarea><br>
</tr></table>
<div id="store_data"></div>


</body>
</html>


