<?php
// logout.php is a simple script to safely disconnect the user

// 1. Start the session so we can access it
session_start();

// 2. Unset all session variables associated with this specific user
// By clearing the session array, the app forgets who is logged in
$_SESSION = array();

// 3. Destroy the entire session on the server
session_destroy();

// 4. Redirect the user back to the login page (or homepage)
header("Location: login.php");

// 5. Always exit after a header redirect to ensure no further background code runs
exit();
?>
