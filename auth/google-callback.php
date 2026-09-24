<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * auth/google-callback.php
 *
 * Handles the OAuth 2.0 callback from Google.
 * Verifies CSRF state, exchanges the authorization code for profile data,
 * and logs the voter in (or registers a new voter account with a Voter Code).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$state = (string) input('state', '');
$savedState = (string) ($_SESSION['oauth_state'] ?? '');
unset($_SESSION['oauth_state']);

// 1. Verify CSRF State
if ($state === '' || $savedState === '' || !hash_equals($savedState, $state)) {
    audit_log('oauth_csrf_failed', 'Google OAuth failed: state token mismatch.');
    flash_error('Google authentication timed out or was invalid. Please try again.');
    redirect('auth/login.php');
}

$googleProfile = [];

// 2. Handle Simulation Mode (Lab / Local Dev)
if (input('sim') === '1') {
    $email = strtolower(trim((string) input('email', '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash_error('Please enter a valid Google email address.');
        redirect('auth/google-login.php');
    }

    $firstName = trim((string) input('first_name', ''));
    $lastName  = trim((string) input('last_name', ''));
    if ($firstName === '') {
        $parts = explode('@', $email)[0];
        $firstName = ucfirst($parts);
        $lastName  = 'Student';
    }

    $googleProfile = [
        'google_id'  => 'google_' . md5($email),
        'email'      => $email,
        'first_name' => $firstName,
        'last_name'  => $lastName,
        'avatar'     => null,
    ];
} else {
    // 3. Handle Live Google OAuth 2.0 Token Exchange
    $code = (string) input('code', '');
    if ($code === '') {
        $error = (string) input('error', 'access_denied');
        flash_error('Google Sign-In was cancelled or denied: ' . $error);
        redirect('auth/login.php');
    }

    $redirectUri = (sv_is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('auth/google-callback.php');

    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $postData = [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => $redirectUri,
        'grant_type'    => 'authorization_code',
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        error_log('Google token exchange failed: ' . (string) $response);
        flash_error('Could not authenticate with Google servers. Please try again or use your password.');
        redirect('auth/login.php');
    }

    $tokenData = json_decode((string) $response, true);
    $accessToken = (string) ($tokenData['access_token'] ?? '');

    if ($accessToken === '') {
        flash_error('Invalid response from Google token server.');
        redirect('auth/login.php');
    }

    // Fetch user profile from Google UserInfo endpoint
    $userinfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';
    $ch = curl_init($userinfoUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $userResponse = curl_exec($ch);
    curl_close($ch);

    $userData = json_decode((string) $userResponse, true);
    if (!is_array($userData) || empty($userData['email'])) {
        flash_error('Could not retrieve Google profile details.');
        redirect('auth/login.php');
    }

    $googleProfile = [
        'google_id'  => (string) ($userData['sub'] ?? ''),
        'email'      => (string) ($userData['email'] ?? ''),
        'first_name' => (string) ($userData['given_name'] ?? 'Google'),
        'last_name'  => (string) ($userData['family_name'] ?? 'User'),
        'avatar'     => (string) ($userData['picture'] ?? null),
    ];
}

// 4. Authenticate or Auto-Register User
$result = sv_login_or_register_google_user($googleProfile);

if (($result['ok'] ?? false) === true) {
    $user = $result['user'];
    if (!empty($result['is_new'])) {
        flash_success('Welcome to SecureVote, ' . $user['first_name'] . '! Your voter account is ready. Your personal Voter Code is: ' . $user['voter_code']);
    } else {
        flash_success('Welcome back, ' . $user['first_name'] . '! Signed in via Google.');
    }

    redirect(dashboard_path_for_current_user());
}

flash_error((string) ($result['error'] ?? 'Sign-in with Google failed.'));
redirect('auth/login.php');
