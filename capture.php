<?php

require_once('config.php');

/**
 * Set up Kaltura Client
 */
$config = new KalturaConfiguration();
$client = new KalturaClient($config);
$ks = $client->session->start(
    ADMIN_SECRET,
    USER_ID,
    KalturaSessionType::ADMIN,
    PARTNER_ID);
$client->setKs($ks);

/**
 * Set download links
 */
list($windows, $osx) = getDownloadLinks($client);

/**
 * Get appToken to be used in launch_data
 */
$token = getAppToken($client);

/**
 * JSON settings object that is encoded to become the launch URL
 */
$launch_data = array(
    "appToken" => $token->token,
    "appTokenId" => $token->id,
    "userId" => USER_ID,
    "partnerId" => PARTNER_ID,
    "serviceUrl" => SERVICE_URL,
    "appHost" => "http://".PARTNER_ID.".kaltura.com",
    "entryURL" => "media",
    "hostingAppType" => "MediaSpace",
    "hashType" => "SHA256"
);

$launch_url = base64_encode(json_encode($launch_data));


/**
 * Search for UiConf object with the name "kalturaCaptureVersioning
 * Retrieve download links from config object
 *
 * @param Kaltura Client
 *
 * @return windows and osx download links
 */
function getDownloadLinks($client)
{
    $filter = new KalturaUiConfFilter();
    $filter->nameLike = "KalturaCaptureVersioning";
    $uiConfs = $client->uiConf->listTemplates($filter);
    $config = json_decode($uiConfs->objects[0]->config);
    $windows = ($config->win_downloadUrl);
    $osx = ($config->osx_downloadUrl);
    return array($windows, $osx);
}


/**
 * Get all available user tokens and send through filterToken()
 *
 * @param Kaltura Client
 *
 * @return relevant or new token
 */
function getAppToken($client)
{
    $filter = new KalturaAppTokenFilter();
    $filter->sessionUserIdEqual = USER_ID;
    $appTokens = $client->appToken->listAction($filter);

    $token = filterToken($appTokens);
    if ($token == null) {
        $token = addToken($client);
    }
    return $token;
}

/**
 * Iterate through user tokens to find type "kalturaCaptureAppToken"
 * and the correct version
 *
 * @param list of appTokens
 *
 * @return first matching token or null if not found
 */
function filterToken($appTokens)
{
    foreach ($appTokens->objects as $appToken) {
        $data = json_decode($appToken->description);
        if ($data->type === 'kalturaCaptureAppToken' && $data->version === CAPTURE_VERSION)
            return $appToken;
    }
    return null;
}

/**
 * Create and return new app token
 * with specific privileges and settings
 * and various permissions needed for launch
 *
 * @param Kaltura Client
 *
 * @return appToken
 */
function addToken($client)
{
    $roleId = getRole($client);
    $appToken = new KalturaAppToken();

    $appToken->sessionType = KalturaSessionType::ADMIN;
    $appToken->sessionUserId = USER_ID;
    $appToken->sessionPrivileges = "setrole:" . $roleId . ",editadmintags:*";
    $appToken->hashType = KalturaAppTokenHashType::SHA256;
    $appToken->description = '{"type": "kalturaCaptureAppToken", "version":'.CAPTURE_VERSION.'}';

    $result = $client->appToken->add($appToken);
    return $result;
}


/**
 * Search for existing role by configured name
 * Create new role if it doesn't exist
 *
 * @param Kaltura Client
 *
 * @return id of matching or new role
 */
function getRole($client)
{
    $filter = new KalturaUserRoleFilter();
    $filter->nameLike = ROLE_NAME;

    $roles = $client->userRole->listAction($filter);
    if ($roles->objects == 0)
        return addRole($client);
    else return $roles->objects[0]->id;
}


/**
 * Create and return id of new role with pre configured name
 * and various permissions needed for launch
 *
 * @param Kaltura Client
 *
 * @return id of new role
 */
function addRole($client)
{
    $role = new KalturaUserRole();

    $role->name = ROLE_NAME;
    $role->description = "Upload by kalturacapture client";
    $role->permissionNames = "CONTENT_INGEST_UPLOAD,CONTENT_MANAGE_BASE,cuePoint.MANAGE, CONTENT_MANAGE_THUMBNAIL, STUDIO_BASE";
    $role->tags = "kalturacapture";

    $result = $client->userRole->add($role);
    return $result->id;
}
?>

<html>
<body>
<div>
    <a href="<?php echo $osx; ?>">OSX Download URL</a><br/>
    <a href="<?php echo $windows; ?>">Windows Download URL</a><br/>
    <a href="kaltura-pc:<?php echo $launch_url; ?>">Open Capture App</a>
</div>
</body>
</html>
