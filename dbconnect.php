<?php
declare(strict_types=1);

// get postgres configuration file from /inc folder
$file = fopen("inc/postgres.conf", "r");

// display configuration error message if file not found
if (!$file) {
    echo '<h2 style="color:red;">PostgreSQL configuration file not found</h2>';
    echo 'File should be located on server at \var\www\html\inc\postresql.conf';
    exit;

} else {
    $line = fgets($file);
    $connection_string = $line;
    fclose($file);

    // try connecting to PostgreSQL database specified in config file
    /*
    $db = @pg_connect($connection_string) or die("Error connecting to PostgreSQL database");
    if ($db == "failed") {
        echo '<h2 style="color:red;">Error connecting to PostgreSQL database</h2>';
        echo 'Database connection string: ' . $connection_string;
    }
    */

    // open postgres database connection
    $db = @pg_connect($connection_string);
    if (!$db) {
        echo '<h2 style="color:red;">Error connecting to PostgreSQL database</h2>';
        echo 'Database connection string: ' . $connection_string;
        echo pg_last_error();
        exit;
    }
}

?>