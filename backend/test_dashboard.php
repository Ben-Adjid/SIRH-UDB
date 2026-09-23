<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');

try {
    $user = \App\Models\User::find(7);
    if (!$user) {
        die("User 7 not found\n");
    }
    auth()->setUser($user);
    $controller = new \App\Http\Controllers\Api\DashboardController();
    $response = $controller->adminStats();
    echo "Success\n";
    echo $response->getContent();
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n";
    echo $e->getTraceAsString();
}
?>
