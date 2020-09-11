<?php
require './lib/main.php';

function search_products ($field, $value, $store_id) {
  $endpoint = 'https://apx-search.e-com.plus/api/v1/items.json?size=500&q=' . $field . ':' . $value;

  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $endpoint);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
  curl_setopt($curl, CURLOPT_HTTPHEADER, array(
    'X-Store-ID: ' . $store_id,
    'Content-Type: application/json',
  ));
  $json = curl_exec($curl);
  curl_close($curl);

  $product_ids = [];
  $result = json_decode($json);
  if (json_last_error() === JSON_ERROR_NONE) {
    $items = @$result->hits->hits;
    for ($i = 0; $i < count($items); $i++) {
      $product_ids[] = $items[$i]->_id;
    }
  }
  return $product_ids;
}

$store_id = @$_SERVER['HTTP_X_STORE_ID'];
if (!$store_id) {
  echo 'Store ID unset';
  exit();
}

$products_feed = new ProductsFeed(
  $store_id,
  'https://' . @$_SERVER['HTTP_X_STORE_DOMAIN'] . '/'
);

$product_ids = isset($_GET['product_ids']) ? json_decode($_GET['product_ids'], true) : null;
if (!$product_ids) {
  if (isset($_GET['search_field']) && isset($_GET['search_value'])) {
    $product_ids = search_products($_GET['search_field'], $_GET['search_value'], $store_id);
  }
}

echo $products_feed->xml(
  @$_GET['title'],
  @$_GET['query_string'],
  isset($_GET['set_properties']) ? json_decode($_GET['set_properties'], true) : null,
  $product_ids,
  @$_GET['offset']
);
