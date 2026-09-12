<?php

$ch = curl_init('https://accounts.zoho.com/oauth/v2/token');

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CAINFO, 'C:\xampp\php\extras\ssl\cacert.pem');

$result = curl_exec($ch);

echo "CURL ERROR NUMBER: " . curl_errno($ch) . PHP_EOL;
echo "CURL ERROR: " . curl_error($ch) . PHP_EOL;
echo "RESPONSE:" . PHP_EOL;
var_dump($result);

curl_close($ch);