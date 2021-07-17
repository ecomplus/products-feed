<?php
require './lib/main.php';

function search_products ($field, $value, $store_id) {
  $endpoint = 'https://apx-search.e-com.plus/api/v1/items.json?size=500&q=' . $field . ':"' . $value . '"';

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
  return array(
    'endpoint' => $endpoint,
    'ids' => $product_ids
  );
}

$store_id = @$_SERVER['HTTP_X_STORE_ID'];
if (!$store_id) {
  echo 'Store ID unset';
  exit();
}

$base_uri = @$_GET['base_uri'];
if (!$base_uri) {
  $base_uri = 'https://' . @$_SERVER['HTTP_X_STORE_DOMAIN'] . '/';
}
$products_feed = new ProductsFeed(
  $store_id,
  $base_uri,
  @$_SERVER['HTTP_X_STORE_API_HOST']
);

$is_list_all = @$_SERVER['HTTP_X_PRODUCTS_FEED'] === 'ALL';
$offset = 0;
$product_ids = null;
$search_endpoint = '';
if ($is_list_all) {
  set_time_limit(1680);
} else {
  $product_ids = isset($_GET['product_ids']) ? json_decode($_GET['product_ids'], true) : null;
  if (!$product_ids) {
    if (isset($_GET['search_field']) && isset($_GET['search_value'])) {
      $search_result = search_products($_GET['search_field'], $_GET['search_value'], $store_id);
      $product_ids = $search_result['ids'];
      $search_endpoint = $search_result['endpoint'];
    } else if (isset($_GET['offset']) && (int)$_GET['offset'] > 0) {
      $offset = (int)$_GET['offset'];
    }
  }
}

$xml = $products_feed->xml(
  @$_GET['title'],
  @$_GET['query_string'],
  isset($_GET['set_properties']) ? json_decode($_GET['set_properties'], true) : null,
  $product_ids,
  $search_endpoint,
  $offset,
  $is_list_all
);

if ($xml) {
  if (is_string($xml)) {
    echo $xml;
  } else if (@$xml['error']) {
    http_response_code(503);
    echo "stopped with error at " . @$xml['endpoint'] . " : \n\n" . @$xml['response'];
  }
} else {
  http_response_code(501);
  echo 'empty xml';
}
