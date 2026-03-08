<?php
require_once 'config/db.php';

try {
    $pdo->exec("USE quickfix_db");
    
    // Add columns
    $pdo->exec("ALTER TABLE services 
                ADD COLUMN image_url VARCHAR(255) DEFAULT 'https://images.unsplash.com/photo-1581092918056-0c4c3acd37be?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', 
                ADD COLUMN rating DECIMAL(3,1) DEFAULT 4.5");
                
    // Update specific services with better images and ratings to showcase the UI
    $pdo->exec("UPDATE services SET image_url = 'https://images.unsplash.com/photo-1621905251189-08b45d6a269e?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', rating = 4.8 WHERE title LIKE '%Electrician%'");
    $pdo->exec("UPDATE services SET image_url = 'https://images.unsplash.com/photo-1585704032915-c3400ca199e7?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', rating = 4.7 WHERE title LIKE '%Plumb%'");
    $pdo->exec("UPDATE services SET image_url = 'https://images.unsplash.com/photo-1542013936693-884638332954?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', rating = 4.9 WHERE title LIKE '%Gas%'");
    $pdo->exec("UPDATE services SET image_url = 'https://images.unsplash.com/photo-1558025213-43557e4e84b8?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80', rating = 4.6 WHERE title LIKE '%Lock%'");
    
    echo "Database migration successful.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
