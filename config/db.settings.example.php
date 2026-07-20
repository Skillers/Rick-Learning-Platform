<?php
/**
 * Template for config/db.settings.php — COPY this file to db.settings.php and
 * fill in the real values. db.settings.php is gitignored and must never be
 * committed: it holds a live database password.
 *
 * Standaard XAMPP-installatie: host 'localhost', user 'root'. Zet een eigen
 * wachtwoord op de MySQL root-gebruiker en vul dat hieronder in.
 */
define('DB_HOST',    'localhost');
define('DB_NAME',    'Rick Learning Platform');
define('DB_USER',    'root');
define('DB_PASS',    '');          // <- vul hier het MySQL-wachtwoord in
define('DB_CHARSET', 'utf8mb4');
