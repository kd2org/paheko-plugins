<?php

namespace Paheko;

use Paheko\Files\Files;
use Paheko\Entities\Files\File;
use Paheko\UserTemplate\UserTemplate;

use Paheko\Plugin\Caisse\Products;

require __DIR__ . '/../_inc.php';

$products = Products::listByCategory();

$tpl->assign('products_categories', $products);

$out = $tpl->fetch(PLUGIN_ROOT . '/templates/manage/products/print.tpl');
$filename = 'Produits.pdf';

header('Content-type: application/pdf');
header(sprintf('Content-Disposition: attachment; filename="%s"', Utils::safeFileName($filename)));
Utils::streamPDF($out);
