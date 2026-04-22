<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Branch;

$branch = Branch::where('name', 'like', 'Posted Branch from Agent 2%')->with(['bankAccounts','setting','invoiceCounter'])->first();
if (! $branch) {
    $branch = Branch::with(['bankAccounts','setting','invoiceCounter'])->orderBy('id','desc')->first();
}
if (! $branch) {
    echo "NO_BRANCH\n";
    exit(1);
}
echo json_encode($branch, JSON_PRETTY_PRINT) . PHP_EOL;
