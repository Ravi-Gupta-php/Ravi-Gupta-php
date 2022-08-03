<?php
$host = '138.68.29.51';
$pass = 'alphabet9soup';
$site = "kenya";
$myversion = "../../myversion.txt";


$txt_fileVC = file_get_contents($myversion);
$rowsVC = explode("\n", $txt_fileVC);

$currentABCDversion = '0.0';

foreach($rowsVC as $line)
{
	$row_data = preg_split("/;|,/", $line);
	$currentABCDversion = $row_data[0];
}

echo "My local ABCD version is: ".$currentABCDversion."<br />";

$remote = "sftp://sftpfiles:$pass@$host/uploads/$site/whattoget.txt";
// $local = 'C:\wamp64\www\redcap\whattoget.txt';
$local = '../../whattoget.txt';

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $remote);
curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_SFTP);
curl_setopt($curl, CURLOPT_USERPWD, "sftpfiles:$pass");
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
file_put_contents($local, curl_exec($curl));
curl_close($curl);

echo getcwd();
echo "got here!<br/> ";

// $txt_file = file_get_contents('C:\wamp64\www\redcap\whattoget.txt');
$txt_file = file_get_contents($local);
$rows = explode("\n", $txt_file);
array_shift($rows);

foreach($rows as $line)
{
	$row_data = preg_split("/;|,/", $line);
  
	if (($row_data[0] != '') || ($row_data[0] != null) || ($row_data[0] != '\n')){
	
	if ($row_data[1] > $currentABCDversion){
	$myfiletoget = $row_data[0];
   
	$remote2 = "sftp://sftpfiles:$pass@$host$myfiletoget";
	
	$myfiletosave = my_substr_function($myfiletoget,strposX($myfiletoget,'/',3)+1,strlen($myfiletoget));
	
	// uncomment this if using windows.
	// $myfiletosave = str_replace("/","\\",$myfiletosave);
	
	// $local2 = 'C:\\wamp64\\www\\redcap\\'.$myfiletosave;
	$local2 = '../../'.$myfiletosave;
	
	echo $myfiletoget."<br />";
	echo $local2."<br />";

	$curl2 = curl_init();
	curl_setopt($curl2, CURLOPT_URL, $remote2);
	curl_setopt($curl2, CURLOPT_PROTOCOLS, CURLPROTO_SFTP);
	curl_setopt($curl2, CURLOPT_USERPWD, "sftpfiles:$pass");
	curl_setopt($curl2, CURLOPT_RETURNTRANSFER, 1);
	file_put_contents($local2, curl_exec($curl2));
	curl_close($curl2); 
	}
  }
}


//Now update my version number locally once completed...

$remoteForVerCheck = "sftp://sftpfiles:$pass@$host/uploads/$site/version.txt";
// $localForVerCheck = 'C:\wamp64\www\redcap\myversion.txt';

$curlVC = curl_init();
curl_setopt($curlVC, CURLOPT_URL, $remoteForVerCheck);
curl_setopt($curlVC, CURLOPT_PROTOCOLS, CURLPROTO_SFTP);
curl_setopt($curlVC, CURLOPT_USERPWD, "sftpfiles:$pass");
curl_setopt($curlVC, CURLOPT_RETURNTRANSFER, 1);
file_put_contents($myversion, curl_exec($curlVC));
curl_close($curlVC);




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