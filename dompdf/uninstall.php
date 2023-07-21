<?php

namespace Paheko;

use Paheko\Plugin\Dompdf\PDF;

// Delete DOMPDF library
Utils::deleteRecursive(PDF::DIRECTORY, true);
