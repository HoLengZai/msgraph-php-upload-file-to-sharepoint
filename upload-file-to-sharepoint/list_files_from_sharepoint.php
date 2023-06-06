<?php
require_once __DIR__.'/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
$dotenv->load();

# APP UPLOADER (can use the same as the app uploader as it got the writer role)
# To be filled for connecting to MS Graph
$tenant_id = $_ENV['TENANT_ID'];
$client_id = $_ENV['CLIENT_ID'];
$client_secret = $_ENV['CLIENT_SECRET'];
$sp_site_id = $_ENV['SP_SITE_ID'];

# Set alias
use GuzzleHttp\Client;
use Microsoft\Graph\Graph;

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
printf("MS Graph Access Token Set: OK\n\n");

// Specify the SharePoint folder that you want to list the content - Example: "00-WEBSITE_APPLICATION_SUBMITTED/2023/12/"
$folder_to_list = "00-WEBSITE_APPLICATION_SUBMITTED/" . date('Y') . "/" . date('m');

// Send request to list all files and folders from the specified apth
$res = $graph->createRequest("GET", "https://graph.microsoft.com/v1.0/sites/$sp_site_id/drive/items/root:/$folder_to_list:/children")
							->execute();

$result = $res->getBody()["value"];

// Display all the folders / files from the SharePoint Document Library
printf("Sharepoint webUrls:\n");
$length = count($result);
for ($i = 0; $i < $length; $i++) {
  printf("%s\n", $result[$i]["webUrl"]);
	// For DEBUG only
	//print_r($result[$i]);
}

printf("\nList folders/files from SharePoint Document Library done\n");
?>
