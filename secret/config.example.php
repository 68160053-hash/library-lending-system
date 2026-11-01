<?php
// Copy this file to secret/config.php on the server (that file is git-ignored)
// and fill in the real database settings. NEVER commit secret/config.php.
return [
    'host' => 'localhost',
    'user' => 'your_db_user',
    'pass' => 'your_db_password',
    'name' => 'library_db',
    // Optional: "Sign in with Google" (create an OAuth client in Google Cloud Console, see README)
    // 'google_client_id'     => 'xxxx.apps.googleusercontent.com',
    // 'google_client_secret' => 'your_google_client_secret',
    // 'google_redirect_uri'  => 'https://angsila.informatics.buu.ac.th/~68160053/library/google-callback.php',
];
