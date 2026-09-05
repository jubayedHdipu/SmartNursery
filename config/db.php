<?php
// File: d:\code\nursery\config\db.php
// Description: Establishes a secure PDO connection to the MySQL database.
// Beginner-friendly procedural PHP with clear comments.

// Include basic application constants (BASE_URL, APP_NAME)
require_once __DIR__ . '/app.php';

// ==============================================================================
// 1. DATABASE CREDENTIALS
// What it does: Defines constants for MySQL hostname, port, database name, and login.
// Why it is necessary: Centralizes settings so we only need to change them in one place.
// ==============================================================================
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'smart_nursery');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4'); // utf8mb4 supports Unicode and botanical emojis

// ==============================================================================
// 2. PDO CONNECTION FUNCTION
// What it does: Creates and returns a PDO database connection object.
// Why it is necessary: PDO provides secure prepared statements that prevent SQL injection.
// ==============================================================================
function get_db(): PDO {
    // Static variable keeps a single shared connection alive across the request (Singleton pattern)
    static $pdo = null;

    if ($pdo === null) {
        // Data Source Name (DSN) tells PDO the driver, host, port, database, and charset
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

        // Configuration options for safe database handling
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on SQL errors for easy debugging
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Return results as simple associative arrays (e.g. $row['email'])
            PDO::ATTR_EMULATE_PREPARES   => false                   // Use native database prepared statements for maximum SQL security
        ];

        try {
            // Attempt to connect to MySQL
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Show beginner-friendly error message if MySQL server is not running
            die("
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 25px; border: 1px solid #f87171; background: #fef2f2; border-radius: 12px;'>
                    <h2 style='color: #dc2626; margin-top: 0;'>🌿 Database Connection Error</h2>
                    <p style='color: #4b5563; line-height: 1.5;'>Could not connect to the MySQL database. Please make sure MySQL is started in your XAMPP Control Panel.</p>
                    <p style='font-size: 12px; color: #6b7280;'><strong>Error details:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                    <hr style='border: none; border-top: 1px solid #fca5a5; margin: 20px 0;'>
                    <p><a href='" . BASE_URL . "/database/setup.php' style='display: inline-block; background: #059669; color: white; padding: 10px 18px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Run 1-Click Database Setup &rarr;</a></p>
                </div>
            ");
        }
    }

    return $pdo;
}
