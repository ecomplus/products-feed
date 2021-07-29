<?php
require './lib/main.php';

try {
  ini_set('memory_limit', '128M');
} catch (Exception $e) {
  error_log("Caught $e");
}

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
$store_domain = @$_SERVER['HTTP_X_STORE_DOMAIN'];
if (!$store_id) {
  echo 'Store ID unset';
  exit();
}

$base_uri = @$_GET['base_uri'];
if (!$base_uri) {
  $base_uri = 'https://' . $store_domain . '/';
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
  ignore_user_abort(true);
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

$output_file = null;
$wip_output_file = null;
if ($is_list_all) {
  $output_file = "/tmp/products-feed-$store_id-$store_domain.xml";
  $wip_output_file = "$output_file.wip";
  if (file_exists($wip_output_file) && time() - filemtime($wip_output_file) <= 3600) {
    http_response_code(202);
    echo "work in progress: \n\n";
    echo file_get_contents($wip_output_file);
    exit();
  }
  ob_start();

  $stored_xml = null;
  if (file_exists($output_file)) {
    $stored_xml = file_get_contents($output_file);
  }
  if (is_string($stored_xml) && strlen($stored_xml) > 10) {
    echo $stored_xml;
  } else {
    http_response_code(202);
    echo 'xml is being generated, come back soon';
  }

  header('Connection: close');
  header('Content-Length: ' . ob_get_length());
  ob_end_flush();
  @ob_flush();
  flush();
}

$xml = $products_feed->xml(
  @$_GET['title'],
  @$_GET['query_string'],
  isset($_GET['set_properties']) ? json_decode($_GET['set_properties'], true) : null,
  $product_ids,
  $search_endpoint,
  $offset,
  $is_list_all,
  $wip_output_file
);

if (!$output_file) {
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
} else if ($xml) {
  rename($wip_output_file, $output_file);
}
