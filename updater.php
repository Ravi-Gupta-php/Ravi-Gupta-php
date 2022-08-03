<?php
$host = '127.0.0.1';
$pass = '';

// IMPORTANT - point to your site.
$site = 'tanzania';

$redcapRoot = __DIR__ . '/../../';
$myversion = $redcapRoot . 'myversion.txt';
$txt_fileVC = file_get_contents($myversion);
$rowsVC = explode("\n", $txt_fileVC);

$currentABCDversion = '0.0';

foreach($rowsVC as $line)
{
	$row_data = preg_split("/;|,/", $line);
	$currentABCDversion = $row_data[0];
}

error_log("My local ABCD version is: ".$currentABCDversion);

$remote = "sftp://sftpfiles:$pass@$host/uploads/$site/whattoget.txt";

$whattoget= $redcapRoot . 'whattoget.txt';

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $remote);
curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_SFTP);
curl_setopt($curl, CURLOPT_USERPWD, "sftpfiles:$pass");
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
$output = curl_exec($curl);
if ($output === false) {
	$error = 'Curl error while fetching remote file whattoget.txt : ' . curl_error($curl);
	error_log($error);
	header('HTTP/1.0 400 Bad error');
    exit($error);
} else {
	file_put_contents($whattoget, $output);
}
curl_close($curl);

$txt_file = file_get_contents($whattoget);
$rows = explode("\n", $txt_file);
array_shift($rows);

foreach($rows as $line)
{
	$row_data = preg_split("/;|,/", $line);
  
	if (($row_data[0] != '') || ($row_data[0] != null) || ($row_data[0] != '\n')){
	
	if ($row_data[1] > $currentABCDversion){
		$myfiletoget = $row_data[0];
		$remoteFile = "sftp://sftpfiles:$pass@$host$myfiletoget";
		$myfiletosave = my_substr_function($myfiletoget,strposX($myfiletoget,'/',3)+1,strlen($myfiletoget));
		$myfiletosave = str_replace("/","\\",$myfiletosave);
		$localFileToSave = $redcapRoot . $myfiletosave;

		$message = "Saving $myfiletosave.<br/> \n";
		echo $message;
		error_log($message);

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $remoteFile);
		curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_SFTP);
		curl_setopt($curl, CURLOPT_USERPWD, "sftpfiles:$pass");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($curl);
		if ($output === false) {
			$error = "Curl error while fetching:  $myfiletoget :". curl_error($curl);
			error_log($error);
			header('HTTP/1.0 400 Bad error');
			exit($error);
		} else {
			file_put_contents($localFileToSave, $output);
		}
		curl_close($curl); 
	}
  }
}

//Now update my version number locally once completed...
$remoteForVerCheck = "sftp://sftpfiles:$pass@$host/uploads/$site/version.txt";

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $remoteForVerCheck);
curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_SFTP);
curl_setopt($curl, CURLOPT_USERPWD, "sftpfiles:$pass");
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
$output = curl_exec($curl);
if ($output === false) {
	$error = 'Curl error while fetching remote file version.txt: ' . curl_error($curl);
	error_log($error);
	header('HTTP/1.0 400 Bad error');
	exit($error);
} else {
	file_put_contents($myversion, $output);
	$message = "Updated to version $output \n";
	error_log("$message");
	echo $message;
}
curl_close($curl);

function strposX($haystack, $needle, $number){
    if($number == '1'){
        return strpos($haystack, $needle);
    }elseif($number > '1'){
        return strpos($haystack, $needle, strposX($haystack, $needle, $number - 1) + strlen($needle));
    }else{
        return error_log('Error: Value for parameter $number is out of range');
    }
}
function my_substr_function($str, $start, $end)
{
  return substr($str, $start, $end - $start);
}