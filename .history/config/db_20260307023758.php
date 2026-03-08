
<!-- <?php
// Start a session if one hasn't already been started
// Sessions are crucial because they let us remember who is logged in across different pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define the database host (usually 'localhost' when running XAMPP/WAMP on your own computer)
$host = 'localhost';

// Define the database name we want to connect to (must match the database we created in phpMyAdmin)
$dbname = 'quickfix_db';

// Define the database username (XAMPP default username is usually 'root')
$username = 'root';

// Define the database password (XAMPP default password is usually an empty string '')
$password = '';

// Try to establish a database connection using PDO (PHP Data Objects)
// We use a try-catch block to gracefully handle any connection errors without exposing sensitive data
try {
    // Create a new PDO instance with the connection details
    // charset=utf8mb4 ensures that all text (including emojis or special characters) is handled correctly
    $pdo = new PDO("mysql:host=$host;port=3307;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set PDO to throw exceptions (errors) when a database query fails
    // This makes it much easier to spot and fix SQL syntax problems during development
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set the default fetch mode to an associative array
    // This allows us to access data using column names (like $row['name']) instead of numbers (like $row[0])
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // If the database connection fails, catch the error (exception) here
    // Stop the script from running entirely (using die()) and display a safe error message
    // You can also change this to a friendly user message in a real production environment
    die("Database Connection Failed: " . $e->getMessage());
}
?> -->
