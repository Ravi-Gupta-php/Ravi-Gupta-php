<?php
$host = '138.68.29.51';
$pass = 'alphabet9soup';
$site = 'tanzania';
$REDCAP_PROXY_HOST = "tcp://openproxy.who.int"; // Proxy server address
$REDCAP_PROXY_PORT = "8080";    // Proxy server port
$dirSep = DIRECTORY_SEPARATOR;
if ($dirSep == '/') {
   $os = 'linux';
}

if ($os === 'linux') {
	error_log("File updater: os is linux, yeah?: " . $os . " and uses these dirseps: " . $dirSep , 0);
} else {
	error_log("File updater: os is windows, yeah?: " . $os . " and uses these dirseps: " . $dirSep , 0);
}
// $redcapPath = realpath(__DIR__ . '/../..');
$redcapPath = realpath(__DIR__ . $dirSep . '..' . $dirSep .'..');
$versionFile = $redcapPath . $dirSep . 'myversion.txt';
$whattogetFile = $redcapPath . $dirSep . 'myversion.txt';
$pathExists = file_exists($versionFile);
// error_log("current wd: " . getcwd(), 0);
error_log("File updater: current path: " . $versionFile . " exists: " . $pathExists, 0);

// $txt_fileVC = file_get_contents('C:\wamp64\www\redcap\myversion.txt');
$txt_fileVC = file_get_contents($versionFile);
$rowsVC = explode("\n", $txt_fileVC);

$currentABCDversion = '0.0';

foreach($rowsVC as $line)
{
	$row_data = preg_split("/;|,/", $line);
	$currentABCDversion = $row_data[0];
}
error_log("File updater: current currentABCDversion: " . $currentABCDversion, 0);

echo "My local ABCD version is: ".$currentABCDversion."<br />";

$remote = "sftp://sftpfiles:$pass@$host/uploads/" . $site ."/whattoget.txt";
// $local = 'C:\wamp64\www\redcap\whattoget.txt';
$local = $whattogetFile;

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $remote);
curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_SFTP);
curl_setopt($curl, CURLOPT_USERPWD, "sftpfiles:$pass");
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
file_put_contents($local, curl_exec($curl));
curl_close($curl);

echo "got here!<br/>";
error_log("File updater: Just updated  " . $local, 0);
// $txt_file = file_get_contents('C:\wamp64\www\redcap\whattoget.txt');
$txt_file = file_get_contents($whattogetFile);
$rows = explode("\n", $txt_file);
array_shift($rows);

error_log("File updater: Now to start looping through the rows". $rows);

foreach($rows as $line)
{
	$row_data = preg_split("/;|,/", $line);

	error_log("File updater: row_data[0]: ". $row_data[0]);
  
	if (($row_data[0] != '') || ($row_data[0] != null) || ($row_data[0] != '\n')){

		error_log("File updater: row_data[1]: ". $row_data[1] . " currentABCDversion: " . $currentABCDversion);
	
	if ($row_data[1] > $currentABCDversion) {

		$myfiletoget = $row_data[0];
		error_log("File updater: Initiating transfer of  ". $myfiletoget);

		$remote2 = "sftp://sftpfiles:$pass@$host$myfiletoget";
		
		$myfiletosave = my_substr_function($myfiletoget,strposX($myfiletoget,'/',3)+1,strlen($myfiletoget));
		error_log("File updater: myfiletosave: " . $myfiletosave, 0);
		if ($os != 'linux') {
		$myfiletosave = str_replace("/","\\",$myfiletosave);
		}
		
		// $local2 = 'C:\\wamp64\\www\\redcap\\'.$myfiletosave;
		$local2 = $redcapPath . $dirSep . $myfiletosave;
		error_log("File updater: saving local2: " . $local2, 0);
		echo $myfiletoget."<br />";
		echo $local2."<br />";

		$curl2 = curl_init();
		curl_setopt($curl2, CURLOPT_URL, $remote2);
		curl_setopt($curl2, CURLOPT_PROTOCOLS, CURLPROTO_SFTP);
		curl_setopt($curl2, CURLOPT_USERPWD, "sftpfiles:$pass");
		curl_setopt($curl2, CURLOPT_RETURNTRANSFER, 1);
		if (isset($REDCAP_PROXY_HOST)) {
		error_log("File updater: Using proxy server for file xfer: ". $REDCAP_PROXY_HOST . " of remote file " . $remote2 . " to local file: " . $local2);
		curl_setopt($ch, CURLOPT_PROXY, "$REDCAP_PROXY_HOST:$REDCAP_PROXY_PORT");
		}
		file_put_contents($local2, curl_exec($curl2));
		curl_close($curl2); 
	}
  }
}

//Now update my version number locally once completed...

$remoteForVerCheck = "sftp://sftpfiles:$pass@$host/uploads/tanzania/version.txt";
// $localForVerCheck = 'C:\wamp64\www\redcap\myversion.txt';
$localForVerCheck = $versionFile;

error_log("File updater: Now update my version number locally.  " . $localForVerCheck, 0);

$curlVC = curl_init();
curl_setopt($curlVC, CURLOPT_URL, $remoteForVerCheck);
curl_setopt($curlVC, CURLOPT_PROTOCOLS, CURLPROTO_SFTP);
curl_setopt($curlVC, CURLOPT_USERPWD, "sftpfiles:$pass");
curl_setopt($curlVC, CURLOPT_RETURNTRANSFER, 1);
if (isset($REDCAP_PROXY_HOST)) {
	error_log("File updater: Using proxy server for file xfer: ". $REDCAP_PROXY_HOST . "for transfer of " . $remoteForVerCheck . " to " . $localForVerCheck);
	curl_setopt($ch, CURLOPT_PROXY, "$REDCAP_PROXY_HOST:$REDCAP_PROXY_PORT");
  }
file_put_contents($localForVerCheck, curl_exec($curlVC));
curl_close($curlVC);

error_log("File updater: Update process completed.", 0);

function strposX($haystack, $needle, $number){
    if($number == '1'){
        return strpos($haystack, $needle);
    }elseif($number > '1'){
        return strpos($haystack, $needle, strposX($haystack, $needle, $number - 1) + strlen($needle));
    }else{
        return error_log('File updater: Error: Value for parameter $number is out of range');
    }
}
function my_substr_function($str, $start, $end)
{
  return substr($str, $start, $end - $start);
}