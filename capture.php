<?php

require_once('config.php');

// set up client

$config = new KalturaConfiguration();
$client = new KalturaClient($config);
$ks = $client->session->start(
    ADMIN_SECRET,
    USER_ID,
    KalturaSessionType::ADMIN,
    PARTNER_ID);
$client->setKs($ks);

// set download links
    list($windows, $osx) = getDownloadLinks($client);

// get appToken to be used in json data below
$token = getAppToken($client);

$launch_data = array(
    "appToken" => $token->token,
    "appTokenId" => $token->id,
    "userId" => PARTNER_ID,
    "partnerId" => 2365491,
    "serviceUrl" => SERVICE_URL,
    "appHost" => "http://".PARTNER_ID.".kaltura.com",
    "entryURL" => "media",
    "hostingAppType" => "MediaSpace",
    "hashType" => "SHA256"
);

$launch_url = base64_encode(json_encode($launch_data));


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


function filterToken($appTokens)
{
    foreach ($appTokens->objects as $appToken) {
        $data = json_decode($appToken->description);
        if ($data->type === 'kalturaCaptureAppToken' && $data->version === CAPTURE_VERSION)
            return $appToken;
    }
    return null;
}


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


function getRole($client)
{
    $filter = new KalturaUserRoleFilter();
    $filter->nameLike = ROLE_NAME;

    $roles = $client->userRole->listAction($filter);
    if ($roles->objects == 0)
        return addRole($client);
    else return $roles->objects[0]->id;
}


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