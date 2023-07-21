<?php

namespace Paheko;

echo '<html><head></head><body>
<h1>Redirected from the HelloAsso checkout</h1>';

echo '<pre>';

echo 'GET:' . "\n";
var_dump($_GET);

echo "\n\n". 'POST:' . "\n";
var_dump($_POST);

echo '</pre></body></html>';

/*
 * Expected GET content
 * 

array(4) {
  ["p"]=>
  string(2) "21"
  ["action"]=>
  string(6) "return"
  ["checkoutIntentId"]=>
  string(4) "6104"
  ["code"]=>
  string(9) "succeeded"
}

* 
*/