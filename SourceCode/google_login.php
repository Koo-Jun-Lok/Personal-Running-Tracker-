<?php
// 配置信息
$client_id = "241745161935-6qlqidb6b5f4vkudrh9f1fgnh95kds1u.apps.googleusercontent.com";
$redirect_uri = "https://koo.codex-biz.com/prt_system/callback.php"; 
$scope = "https://www.googleapis.com/auth/fitness.activity.read";

$params = [
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'response_type' => 'code',
    'scope' => $scope,
    'access_type' => 'offline', 
    'prompt' => 'consent'       
];

$auth_url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query($params);
header("Location: $auth_url");
exit;
?>