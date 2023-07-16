<?php
require_once __DIR__.'/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
$dotenv->load();

# APP UPLOADER
# To be filled for connecting to MS Graph
$tenant_id = $_ENV['TENANT_ID'];
$client_id = $_ENV['CLIENT_ID'];
$client_secret = $_ENV['CLIENT_SECRET'];
$sp_site_id = $_ENV['SP_SITE_ID'];

$file_fullpath_to_upload = __DIR__.'/test 1.txt';

# Set alias
use GuzzleHttp\Client;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;

# Init GuzzleHttp Client class
$guzzle = new Client();

# Prepare URI for MS Graph Authentication
$url = 'https://login.microsoftonline.com/' . $tenant_id . '/oauth2/v2.0/token';
$token = json_decode($guzzle->post($url, [
	'form_params' => [
		'client_id' => $client_id,
		'client_secret' => $client_secret,
		'scope' => 'https://graph.microsoft.com/.default',
		'grant_type' => 'client_credentials',
	],
])->getBody()->getContents());

# Save MS Graph Authentication Access Token
$accessToken = $token->access_token;

# Init MS Graph class
$graph = new Graph();

# Set MS Graph Access Token retrieved for your $graph instance 
$graph->setAccessToken($accessToken);
printf("MS Graph Access Token Set: OK\n");

# File to Upload
$filename_fullpath = $file_fullpath_to_upload;
$file_basename =  pathinfo( $filename_fullpath, PATHINFO_BASENAME );
$filetype  = pathinfo( $filename_fullpath, PATHINFO_EXTENSION );

// The script will create folders with this tree structure: "00-WEBSITE_APPLICATION_SUBMITTED/2023/12/" for December 2023. If these folders do not exist, it will create them automatically
$current_date = date('Ymd_His');
$new_folder_path = "00-WEBSITE_APPLICATION_SUBMITTED/" . date('Y') . "/" . date('m');
$new_folder_name = $current_date;

// Request Body to send to create a folder (for 31 December 2023 at 23h59m59s, i.e.: 20231231_235959) in "Site ID" Document Library
$requestBody_content = array(
	'name' => $new_folder_name,
	'folder' => new stdClass(),
  '@microsoft.graph.conflictBehavior' => 'rename',
);

// Create the folders
$res = $graph->createRequest("POST", "https://graph.microsoft.com/v1.0/sites/$sp_site_id/drive/items/root:/$new_folder_path:/children")
							->attachBody($requestBody_content)
							->execute();

$uploadfile = $filename_fullpath;

// If the file is under 4MB, no need to create an "Upload Session" otherwise It will create one
$maxuploadsize = 1024 * 1024 * 4;
if (filesize($uploadfile) < $maxuploadsize) {
	$graph->createRequest("PUT", "/sites/$sp_site_id/drive/items/root:/$new_folder_path/$new_folder_name/$file_basename:/content")->upload($filename_fullpath);
}
else {
	///sites/{siteId}/drive/items/{itemId}/createUploadSession
	$uploadSession = $graph->createRequest("POST", "/sites/$sp_site_id/drive/items/root:/$new_folder_path/$new_folder_name/$file_basename:/createUploadSession")
		->addHeaders(["Content-Type" => "application/json"])
		->attachBody([
			"item" => [
				"@microsoft.graph.conflictBehavior" => "replace"
			]
		])
		->setReturnType(Model\UploadSession::class)
		->execute();

	$file = $uploadfile;
	$handle = fopen($file, 'rb');
	$fileSize = fileSize($file);
	$chunkSize = 1024*1024*2;
	$prevBytesRead = 0;
	while (!feof($handle)) {
		$bytes = fread($handle, $chunkSize);
		$bytesRead = ftell($handle);

		$resp = $graph->createRequest("PUT", $uploadSession->getUploadUrl())
			->addHeaders([
				'Connection' => "keep-alive",
				'Content-Length' => ($bytesRead-$prevBytesRead),
				'Content-Range' => "bytes " . $prevBytesRead . "-" . ($bytesRead-1) . "/" . $fileSize,
			])
			->setReturnType(Model\UploadSession::class)
			->attachBody($bytes)
			->execute();

		$prevBytesRead = $bytesRead;
	}
}

// For DEBUG only
//printf("Upload file: [%s] - Done\n", $filename_fullpath);
printf("Upload file done\n");
?>
