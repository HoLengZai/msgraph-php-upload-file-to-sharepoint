<?php
require_once __DIR__.'/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../', '.env.granter');
$dotenv->load();

# GRANTER
# To be filled for connecting to MS Graph
$tenant_id = $_ENV['TENANT_ID'];
$granter_clientId = $_ENV['GRANTER_CLIENT_ID'];
$granter_clientSecret = $_ENV['GRANTER_CLIENT_SECRET'];
# To be filled for specifying the SharePoint site that you want access.
$sp_site_id = $_ENV['SP_SITE_ID'];
$app_clientId = $_ENV['APP_CLIENT_ID'];
$app_clientDisplayName = $_ENV['APP_CLIENT_DISPLAYNAME'];


# Set alias 
use GuzzleHttp\Client;
use Microsoft\Graph\Graph;

# Init GuzzleHttp Client class
$guzzle = new Client();

# Prepare URI for MS Graph Authentication
$url = 'https://login.microsoftonline.com/' . $tenant_id . '/oauth2/v2.0/token';
$token = json_decode($guzzle->post($url, [
	'form_params' => [
		'client_id' => $granter_clientId,
		'client_secret' => $granter_clientSecret,
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

# Get SharePoint Document Library ID (= SharePoint Drive ID)
$res = $graph->createRequest("GET", "https://graph.microsoft.com/v1.0/sites/$sp_site_id/drives")->execute();
$result = $res->getBody()["value"][0];
printf("Sharepoint Drive ID (Document Library ID): [%s]\n", $result["id"]);

// Application ID / Display Name used for uploading file in "Site ID"; created through Azure Active Directory portal (App registration)
$app_id = $app_clientId;
$app_displayName = $app_clientDisplayName;

// Request Body to send to allow "Application ID" to write on "Site ID"
$requestBody_content = array(
	'roles' => array('write'),
	'grantedToIdentities' => array(array (
			'application' => array(
				'id' => $app_id,
				'displayName' => $app_displayName
			)
	))
);

// ADD PERMISSION
// Add "Application (client) ID" "write" permission to this "Site ID"
$res = $graph->createRequest("POST", "https://graph.microsoft.com/v1.0/sites/$sp_site_id/permissions")
							->attachBody($requestBody_content)
							->execute();

//// For DEBUG only
// $result = $res->getBody();
// printf("Permission ID: [%s]\n", $result["id"]);
// printf("Application ID: [%s]\n", $result["grantedToIdentitiesV2"][0]["application"]["id"]);
// printf("Application Display Name: [%s]\n", $result["grantedToIdentitiesV2"][0]["application"]["displayName"]);
// printf("Roles received: [%s]\n", implode(", ", $result["roles"]));

//// For deeper DEBUGGING only
// print_r($result);
printf("Provide write access to this SharePoint ID [%s] for Application (client) ID [%s]: OK\n", $sp_site_id, $app_id);

// REMOVE PERMISSION
//// To delete permission id (= Remove an "Application ID" (role) from a "Site ID", you need to retrive the permission ID before using this)
//// Note: To retrieve the permission ID, you can use "list (site) permission" (GET https://graph.microsoft.com/v1.0/sites/{sitesId}/permissions)
// $permission_id = "very_long_id";
// $res = $graph->createRequest("DELETE", "https://graph.microsoft.com/v1.0/sites/$sp_site_id/permissions/$permission_id")->execute();

// LIST ALL PERMISSIONS of the Site ID
$res = $graph->createRequest("GET", "https://graph.microsoft.com/v1.0/sites/$sp_site_id/permissions")->execute();

$result = $res->getBody()["value"][0];
$permission_id = $result["id"];
printf("Permission ID: [%s]\n", $permission_id);
// Note: 'grantedToIdentities' is deprecated, use 'grantedToIdentitiesV2'
printf("Application ID: [%s]\n", $result["grantedToIdentitiesV2"][0]["application"]["id"]);
printf("Application Display Name: [%s]\n", $result["grantedToIdentitiesV2"][0]["application"]["displayName"]);
// Retrieve Roles of the permissions
$res = $graph->createRequest("GET", "https://graph.microsoft.com/v1.0/sites/$sp_site_id/permissions/$permission_id")->execute();
$result = $res->getBody();
printf("Roles received: [%s]\n", implode(", ", $result["roles"]));

//// For Debug only
// print_r($result);
exit;
?>
