<?php declare(strict_types = 1);

use League\Flysystem;

// path ok (mounted by docker volume, see docker-compose.yml)
require __DIR__.'/../vendor/autoload.php';

session_start();

if(isset($_GET['clear'])) {
    session_unset();
    header('Location: ' . getenv('REDIRECT_URI'));
    exit();
}

$provider = new \Keboola\OneDriveWriter\MicrosoftGraphApi\OAuthProvider(
    getenv('CLIENT_ID'),
    getenv('CLIENT_SECRET'),
    getenv('REDIRECT_URI')
);

if($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['code'])) {
    if(empty($_GET['state']) || ($_GET['state'] !== $_SESSION['state'])) {
        unset($_SESSION['state']);
        exit('State value does not match the one initially sent');
    }

    $provider->generateAccessToken($_GET['code']);

    $_SESSION['access_token'] = $provider->getAccessToken();
    $_SESSION['access_token_data'] = $provider->getAccessTokenData();

    //	// The id token is a JWT token that contains information about the user
    //	// It's a base64 coded string that has a header, payload and signature
    //	$idToken = $accessToken->getValues()['id_token'];
    //	$decodedAccessTokenPayload = base64_decode(
    //		explode('.', $idToken)[1]
    //	);
    //	$jsonAccessTokenPayload = json_decode($decodedAccessTokenPayload, true);
    //
    //	// The following user properties are needed in the next page
    //	$_SESSION['preferred_username'] = $jsonAccessTokenPayload['preferred_username'];
    //	$_SESSION['given_name'] = $jsonAccessTokenPayload['name'];

    header('Location: ' . getenv('REDIRECT_URI'));
    exit();
} elseif($_SERVER['REQUEST_METHOD'] === 'GET' && ( ! isset($_SESSION['access_token']) || isset($_GET['refresh']))) {
    $authorizationUrl = $provider->getAuthorizationUrl();

    $_SESSION['state'] = $provider->getState();

    header('Location: '.$authorizationUrl);
    exit();
} elseif(isset($_SESSION['access_token']) && isset($_GET['refresh-token'])) {
    $provider->initAccessToken($_SESSION['access_token_data']);

    echo '<pre>';
    echo '===== OLD ACCESS TOKEN ====='."\n";
    print_r($provider->getAccessToken());
    print_r(json_decode($provider->getAccessTokenData(), true));
    echo '</pre>';

    $provider->refreshAccessToken();

    $_SESSION['access_token'] = $provider->getAccessToken();
    $_SESSION['access_token_data'] = $provider->getAccessTokenData();

    echo '<pre>';
    echo '===== NEW ACCESS TOKEN ====='."\n";
    print_r($provider->getAccessToken());
    print_r(json_decode($provider->getAccessTokenData(), true));
    echo '</pre>';
} else {
    $provider->initAccessToken($_SESSION['access_token_data']);

    echo '<pre>';
    print_r($provider->getAccessToken());
    echo '</pre>';
    echo '<pre>';
    print_r($provider->getAccessTokenData());
    echo '</pre>';
    echo '<pre>';
    print_r(json_encode($provider->getAccessTokenData()));
    echo '</pre>';
    echo '<pre>';
    print_r(json_decode($provider->getAccessTokenData(), true));
    echo '</pre>';

    echo "aaa";

    $adapter = new FlySystem\Adapter\Local(sprintf('%s%s', "/tmp", '/out/files'));
    $fileSystem = new Flysystem\Filesystem($adapter);

    $w = new \Keboola\OneDriveWriter\Writer(
        getenv('CLIENT_ID'),
        getenv('CLIENT_SECRET'),
        $provider->getAccessTokenData(),
        $fileSystem
    );

    try {
        $w->writeFile('.', 'test.pdf', '');
    } catch(\Keboola\OneDriveWriter\Exception\UserException $e) {
        print_r($e);
    }

}
