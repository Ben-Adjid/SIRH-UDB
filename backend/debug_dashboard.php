<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';

try {
    $result = \App\Models\Payroll::selectRaw("CONCAT(year, '-', month) as period, SUM(net_salary) as total")
        ->where('status', 'paid')
        ->where('year', '>=', \Carbon\Carbon::today()->subMonths(5)->year)
        ->groupBy('year', 'month')
        ->get();

    echo "Count: " . count($result) . "\n";
    if (count($result) > 0) {
        echo "First item properties:\n";
        foreach ($result[0] as $key => $value) {
            echo "  $key => $value\n";
        }
    }

    // Try the pluck version
    echo "\n--- Pluck version ---\n";
    $payroll = \App\Models\Payroll::selectRaw("CONCAT(year, '-', month) as period, SUM(net_salary) as total")
        ->where('status', 'paid')
        ->where('year', '>=', \Carbon\Carbon::today()->subMonths(5)->year)
        ->groupBy('year', 'month')
        ->pluck('total', 'period');

    echo "Pluck result type: " . gettype($payroll) . "\n";
    echo "Pluck result count: " . count($payroll) . "\n";
    if (count($payroll) > 0) {
        echo "Sample entries:\n";
        foreach ($payroll->take(3) as $key => $value) {
            echo "  $key => $value\n";
        }
    }

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
?>
