<?php
$files = glob('*.php');
foreach ($files as $file) {
    if ($file === 'config.php' || $file === 'update_connections.php') {
        continue;
    }
    
    $content = file_get_contents($file);
    $content = str_replace("require_once 'db.php';", "require_once 'config.php';", $content);
    file_put_contents($file, $content);
    echo "Updated $file\n";
}
echo "All files have been updated to use config.php\n"; 