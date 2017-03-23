<?php
/*
Plugin Name: Google Authentication
Plugin URI: https://github.com/8thwall/google-auth-yourls
Description: This plugin enables authentation against Google
Version: 1.0
Author: Tony Tomarchio (atomarch)
Author URI: http://www.8thwall.com, http://www.tomarchio.cc
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

/* Assumes thst you have anready downloaded and installed the  
 * Google APIs Client Library for PHP and it's in the same directory.
 * See https://github.com/google/google-api-php-client for install instructions.
 * Include your composer dependencies:
 */
require_once __DIR__.'/vendor/autoload.php';

/* The function yourls_is_valid_user() in includes/functions-auth.php checks for a valid user via the login 
 * form or stored cookie. The 'shunt_is_valid_user' filter allows plugins such as this one, to short-circuit
 * the entire function.
 */

/* This says: when filter 'shunt_is_valid_user' is triggered, execute function 'atomarch_google_auth'
 * and send back it's return value. Filters should always have a return value.
 */
yourls_add_filter( 'shunt_is_valid_user' , 'atomarch_google_auth' );

function atomarch_google_auth() {

    session_start();

    $client = new Google_Client();
    $client->addScope('profile');
    $client->addScope('email');
    
    // See https://developers.google.com/api-client-library/php/auth/web-app to create
    // an OAuth 2.0 client ID, and download the resulting JSON file
    // This assumes that client_secrets.json file resides in the same directory as plugin.php
    $client->setAuthConfig( dirname(__FILE__) .'/client_secrets.json');

    $client->setRedirectUri('https://' . $_SERVER['HTTP_HOST'] . '/admin/');

    if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
        // User is logged in
        $client->setAccessToken($_SESSION['access_token']);
	
	if ( atomarch_check_domain($client) ) {
		return true;
	} else {
		echo "UNAUTHORIZED";
		die();
	}
	
    } else {
	if (! isset($_GET['code'])) {
		// Generate a URL to request access from Google's OAuth 2.0 server
		$auth_url = $client->createAuthUrl();
		// Redirect the user to $auth_url so they can enter their Google credentials
		header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
	} else {
		// Exchange an authorization code for an access token
		$client->authenticate($_GET['code']);
		$_SESSION['access_token'] = $client->getAccessToken();
		$redirect_uri = 'https://' . $_SERVER['HTTP_HOST'] . '/admin/';
		header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
	}
    }
}

function atomarch_check_domain( $google_client ) {

    // Define domain that has access to login. Use * to allow access from any google account
    define('APPROVED_DOMAIN', "mydomain.com");

    if ( APPROVED_DOMAIN === "*" ) {
	return true;
    }

    if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {

        $google_oauthV2 = new Google_Service_Oauth2($google_client);

        $user_info = $google_oauthV2->userinfo->get();
	$user_domain = substr(strrchr($user_info['email'], "@"), 1);

	if ($user_domain === APPROVED_DOMAIN) {
		return true;
	} else {
		return false;
	}
    }
}	

?>
