#!/usr/bin/php
<?php

require 'addrgen.php';

for ($i = 0; $i < 5; $i++) {
	print addr_from_mpk('f7d391b80782b3f46ed5ba3c449485a90295c06c9678aa543f73f240175cffcdbd69aca634e9a36687e6fbcc107422bca58a6115fa66afad4ce2f2eea0a6e130', $i) . "\n";
}

/*

should print:

1CksxZMsz4o4hSS7igWXbWYwL1aaXsW64x
1DpowaMMPLmHgwMNNKC7NKxEXDdv4N4qv6
17F1wHLEVMmwSaKnuEfgjaxGnwTbd848rS
16NaFFLSBytDL6MKfqGtf7i3VomXS11kcA
1FMq6dnSRGnLgDdoxDwGojkxaSMwHHy1Sj

*/

?>
