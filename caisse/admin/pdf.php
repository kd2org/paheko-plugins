<?php

namespace Paheko;

use Paheko\Files\Files;
use Paheko\Entities\Files\File;
use Paheko\UserTemplate\UserTemplate;

use Paheko\Plugin\Caisse\Tabs;

require __DIR__ . '/_inc.php';

$tab = Tabs::get(qg('id'));

if ('' === trim($tab->name)) {
	throw new UserException('La note n\'a pas de nom associé : impossible de produire la facture');
}

$printer = shell_exec('which prince') ? 'prince' : (shell_exec('which chromium') ? 'chromium' : null);

if (!$printer) {
	die('Impossible de trouver Prince XML ou Chrome');
}

$items = $tab->listItems();
$payments = $tab->listPayments();
$remainder = $tab->getRemainder();
$options = $tab->listPaymentOptions();

$eligible = 0;

foreach ($options as $k => &$option) {
	if ($option->id != 3) {
		unset($options[$k]);
		continue;
	}

	$eligible = $option->amount;
}

$remainder_after = $remainder - $eligible;

$file = Files::get(File::CONTEXT_SKELETON . '/caisse_invoice.html');

$tpl = new UserTemplate($file);

if (!$file) {
	$tpl->setSource(PLUGIN_ROOT . '/templates/invoice.skel');
}

$tpl->registerSection('items', function () use ($items) {
	foreach ($items as $item) {
		yield (array) $item;
	}
});

$tpl->registerSection('payments', function () use ($payments) {
	foreach ($payments as $item) {
		yield (array) $item;
	}
});

$tpl->assignArray(compact('tab', 'remainder', 'eligible', 'remainder_after'));

$result = $tpl->fetch();
$file_name = sprintf('Reçu %06d - %s.pdf', $tab->id, preg_replace('/[^\w]+/Ui', ' ', $tab->name));

if ($printer == 'prince') {
	$descriptorspec = array(
	   0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
	   1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
	   2 => array('pipe', 'w'),
	);

	$cmd = 'prince -o - -';
	$process = proc_open($cmd, $descriptorspec, $pipes);

	if (is_resource($process)) {
		// $pipes now looks like this:
		// 0 => writeable handle connected to child stdin
		// 1 => readable handle connected to child stdout

		fwrite($pipes[0], $result);
		fclose($pipes[0]);

		$pdf_content = stream_get_contents($pipes[1]);
		fclose($pipes[1]);

		// It is important that you close any pipes before calling
		// proc_close in order to avoid a deadlock
		$return_value = proc_close($process);

		header('Content-type: application/pdf');
		header(sprintf('Content-Disposition: attachment; filename="%s"', $file_name));
		echo $pdf_content;
	}
}
elseif ($printer == 'chromium') {
	$tmpname = sprintf('%s/print-%s.html', CACHE_ROOT, md5(random_bytes(16)));
	file_put_contents($tmpname, $result);

	exec(sprintf('chromium --headless --disable-gpu --run-all-compositor-stages-before-draw --print-to-pdf-no-header --print-to-pdf=%s %s', escapeshellarg($tmpname . '.pdf'), escapeshellarg($tmpname)));

	header('Content-type: application/pdf');
	header(sprintf('Content-Length: %d', filesize($tmpname . '.pdf')));
	header(sprintf('Content-Disposition: attachment; filename="%s"', $file_name));
	readfile($tmpname . '.pdf');

	unlink($tmpname . '.pdf');
	unlink($tmpname);
}
