<?php

namespace Paheko;

use Paheko\Plugin\Dompdf\PDF;

if (!class_exists('ZipArchive')) {
	throw new UserException('Cette extension nécessite l\'installation du module PHP zip (apt install php-zip).');
}

// Download and unzip DomPDF library
$url = 'https://github.com/dompdf/dompdf/releases/download/v2.0.3/dompdf_2-0-3.zip';

$file = tempnam(CACHE_ROOT, 'dompdf');

copy($url, $file);

$zip = new \ZipArchive;
$zip->open($file);
$zip->extractTo(PDF::DIRECTORY);
$zip->close();

unlink($file);

$plugin->registerSignal('pdf.stream', 'Paheko\Plugin\Dompdf\PDF::stream');
$plugin->registerSignal('pdf.create', 'Paheko\Plugin\Dompdf\PDF::create');
