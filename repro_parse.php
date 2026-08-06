<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$text = file_get_contents(__DIR__ . '/repro_ocr.txt');
$parser = $app->make(App\Services\InvoiceParserService::class);

echo "=== parse() result ===\n";
$result = $parser->parse($text);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
