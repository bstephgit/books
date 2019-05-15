<?php

	declare(strict_types=1);

	use PHPUnit\Framework\TestCase;

	$_SERVER['HTTP_HOST'] = 'localhost';

	set_include_path(get_include_path() . PATH_SEPARATOR . realpath('../../google-api-php-client/src/'));
	set_include_path(get_include_path() . PATH_SEPARATOR . realpath('../../google-api-php-client/src/Google'));
	set_include_path(get_include_path() . PATH_SEPARATOR . realpath('../../google-api-php-client/src/Google/Service'));


	include_once "../oboom_classdef.php";
	include_once "../Log.php";

	const API_URL = "https://api.oboom.com";
	const UPLOAD_URL = "https://upload.oboom.com";


	function curl_request($url,$options=array())
	{
		$curl = curl_init();
        $whole_options=array(
            // General options.
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_AUTOREFERER    => true,

            // SSL options.
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_URL            => $url
        );

        if($options) {
            $whole_options=$whole_options + $options;
        }
        curl_setopt_array($curl, $whole_options);
        $result = curl_exec($curl);

        if ($result===false) {
            curl_close($curl);
            throw new \Exception('curl_exec() failed: ' . curl_error($curl));
        }
        curl_close($curl);

        return $result;
	}

	function curl_post($url,$body,$options=array())
	{
		$post_options=array(
             CURLOPT_POST       => true,
             CURLOPT_POSTFIELDS => $body
         );
        if($options) {
            $post_options=$options+$post_options;
        }
        return curl_request($url, $post_options);
	}
	function make_folder($parent_id, $folder_name)
	{
		$drive = new OBoomDrive();
		$url = API_URL . '/1/mkdir?token=' . $drive->getAccessToken() . '&parent=' . $parent_id . '&name=' . $folder_name;
		$response = json_decode(curl_request($url));

		if ( $response[0] == 200)
		{
			return $response[1];

		}else{
			throw new \Exception('Error:' . var_export($response,true));
		}

	}
	function list_folder($folder_id)
	{
		$drive = new OBoomDrive();
		$url = API_URL . '/1/ls?token=' . $drive->getAccessToken() .'&item=' . strval($folder_id);
		$response = json_decode(curl_request($url));

		if (intval($response[0])===200)
		{
			return $response[2];
		}
		throw new \Exception(var_export($response,true));

	}

	function find_folder($parent_id,$folder_name){
		$drive = new OBoomDrive();
		$list = list_folder($parent_id);
		foreach ($list as $item) {
			if ($item->name===$folder_name && $item->type==='folder')
			{
				return $item;
			}
		}
		return null;
	}

	function find_file($parent_id,$file_name){
		$drive = new OBoomDrive();
		$list = list_folder($parent_id);
		foreach ($list as $item) {
			if ($item->name===$file_name && $item->type==='file')
			{
				return $item;
			}
		}
		return null;
	}
	function items_info($items_id)
	{
		$drive = new OBoomDrive();
		$url = API_URL . '/1/info?token=' . $drive->getAccessToken() . '&items=' . $items_id ;
		return curl_request($url);
	}
	function delete_item($items_id)
	{
		$drive = new OBoomDrive();
		$url = API_URL . '/1/rm?token=' . $drive->getAccessToken() . '&items=' . $items_id ;
		//$url = API_URL . '/1/rm?token=' . $drive->getAccessToken() . '&items=' . $items_id . '&recursive=false&move_to_trash=false';
		return curl_request($url);
	}
	function uploadFile($parent_id, $file_name, $path)
	{
		$drive = new OBoomDrive();
		$the_file= new CURLFile(realpath($path),'application/octet-stream',$file_name);
		$url = UPLOAD_URL . '/1/ul?token=' . $drive->getAccessToken() . '&parent=' . $parent_id;
		$body=array(
                   'file' => $the_file,
               );

        $options=array(
             CURLOPT_HTTPHEADER => array(
                 'Content-Type: multipart/form-data'
             ),
             CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
             CURLOPT_NOPROGRESS => false,

         );
        return curl_post($url,$body,$options);

	}
	function downloadLink($file_id){
		$drive = new OBoomDrive();
		$url = API_URL . '/1/dl?token=' . $drive->getAccessToken() . "&item=" . $file_id;
		$res = curl_request($url);

		$res = json_decode($res);
		if ($res[0]==200)
		{
			$res = 'http://' . $res[1] . '/1/dlh?ticket=' . $res[2] ;

		}
		else
		{
			throw new \Exception('Error: '. var_export($res,true));
		}
		return $res;

	}

	function test_make_folder()
	{
		$resp = make_folder('1','titi');
		var_dump($resp);

	}

	function test_list_folder(){
		$base_folder = 'SEEQYRII';
		$folder = list_folder($base_folder);
		var_dump($folder);	
	}
	

	function test_find_file(){
		$base_folder = 'SEEQYRII';
		$file=find_file($base_folder,'oboom.py');
		var_dump($file);
	}
	
	function test_find_folder(){
		$folder = find_folder('1','toto');
		var_dump($folder);
	}

	function test_upload_file(){
		$path = '/tmp/sqldump.19_Avr_2019.tar.gz';
		$file_postname = 'sqldump.tar.gz';
		$resp = uploadFile($folder->id,$file_postname,$path);
		var_dump($resp);

	}
	function test_delete_item()
	{
		$file = find_file($folder->id,$file_postname);
		$res = delete_item($file->id);
		var_dump($res);

	}

	function test_download_file(){

		$download_link = downloadLink($file->id);
		$data = curl_request($download_link, array( CURLOPT_NOPROGRESS => false ));
		var_dump($download_link);

		$fptr = fopen($file->name, "w+");
		fputs($fptr, $data);
		fclose($fptr);
	}


	final class OboomDriveTest extends TestCase
	{
		function test_make_folder()
		{
			var_dump('make folder');
			$resp = make_folder('1','test');
			
			//ar_dump($resp);
			$this->assertTrue(is_string($resp));
			$this->assertGreaterThan(0,count($resp));

			return $resp;

		}
		/**
		* @depends test_make_folder
		*/
		function test_list_folder($folder_id)
		{

			$resp = list_folder('1');

			//var_dump($resp);
			$this->assertTrue(is_array($resp));

			$c = 0;
			foreach ($resp as $item) {
				# code...
				if ($item->id == $folder_id && $item->type == "folder")
				{
					$c += 1;
				}
			}
			
			$this->assertEquals(1,$c);

			return $folder_id;

		}
		/**
		* @depends test_list_folder
		*/
		function test_find_folder($folder_id)
		{
			$folder = find_folder('1','test');

			$this->assertTrue(is_object($folder));
			$this->assertTrue(property_exists($folder, "id"));
			$this->assertEquals($folder_id,$folder->id);

			return $folder_id;
		}
		/**
		* @depends test_find_folder
		*/
		function test_upload_file($folder_id)
		{
			$res = uploadFile($folder_id,'test.php','./OboomDriveTest.php');
			$this->assertTrue(is_string($res));

			$obj = json_decode($res);
			$this->assertTrue(is_array($obj));
			$this->assertTrue(is_int($obj[0]));
			$this->assertEquals(200,$obj[0]);

			$file = $obj[1][0];
			$this->assertTrue(is_object($file));
			$this->assertTrue(property_exists($file, "id"));
			
			return array ( $folder_id, $file->id );
		}
		/**
		* @depends test_upload_file
		*/
		function test_find_file($array_data)
		{
			$folder_id = $array_data[0];
			$file_id = $array_data[1];
			$file = find_file($folder_id,'test.php');

			$this->assertTrue(is_object($file));
			$this->assertTrue(property_exists($file, "id"));
			$this->assertEquals($file->id,$file_id);
			return $array_data;
		}
		/**
		* @depends test_find_file
		*/
		function test_info_file($array_data)
		{
			$folder_id = $array_data[0];
			$file_id = $array_data[1];
			
			$resp = items_info($file_id);
			$this->assertTrue($resp!==false);

			$resp = json_decode($resp);

			$this->assertTrue(is_array($resp));
			$this->assertTrue(is_int($resp[0]));
			$this->assertEquals(200,$resp[0]);

			//var_dump(items_info($resp));

			$this->assertTrue(is_array($resp[1]));
			$this->assertEquals(1,count($resp[1]));
			$file = $resp[1][0];

			$this->assertTrue(property_exists($file, "id"));
			$this->assertTrue(property_exists($file, "name"));
			$this->assertTrue(property_exists($file, "type"));
			$this->assertEquals($file->id,$file_id);
			$this->assertEquals($file->name,"test.php");
			$this->assertEquals($file->type,"file");

			return $array_data;
		}
		/**
		* @depends test_find_file
		*/
		function test_delete_file($array_data)
		{
			$folder_id = $array_data[0];
			$file_id = $array_data[1];

			$resp = delete_item($file_id);

			$this->assertTrue($resp!==false);

			$resp = json_decode($resp);

			$this->assertTrue(is_array($resp));
			$this->assertTrue(is_int($resp[0]));
			$this->assertEquals(200,$resp[0]);

			$file = find_file($folder_id,'test.php');
			$this->assertEquals($file,null);
			return $folder_id;
		}
		/**
		* @depends test_delete_file
		*/
		function test_delete_folder($folder_id)
		{

			$resp = delete_item($folder_id);

			$this->assertTrue($resp!==false);

			$resp = json_decode($resp);

			$this->assertTrue(is_array($resp));
			$this->assertTrue(is_int($resp[0]));
			$this->assertEquals(200,$resp[0]);
		}	

		function test_instance(){
		
			$drive = new OBoomDrive();
			$this->assertGreaterThan( 0, count( $drive->getAccessToken() ) );
		}
		function test_getDriveVendorName(){
			$drive = new OBoomDrive();
			$vendor = $drive->getDriveVendorName();
			$this->assertEquals(OBOOM_,$vendor);
		}
		function test_downloadLink(){
			$drive = new OBoomDrive();
			$link = $drive->downloadLink('FILE_ID_XYZ');
			var_dump($link);
			$this->assertTrue(true);
		}
		function test_getstore_info(){
			
			$drive = new OBoomDrive();
			$info = $drive->store_info();

			$this->assertTrue(is_object($info));
			$this->assertTrue(property_exists($info, "access_token"));
			$this->assertTrue(property_exists($info, "book_folder"));
			$this->assertTrue(property_exists($info, "urls"));

			$urls = $info->urls;
			$this->assertTrue(property_exists($urls, "download"));
			$this->assertTrue(property_exists($urls, "upload"));
			$this->assertTrue(property_exists($urls, "delete"));
		}

	}

?>