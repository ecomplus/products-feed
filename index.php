<?php
require './lib/main.php';

try {
  ini_set('memory_limit', '300M');
} catch (Exception $e) {
  error_log("Caught $e");
}

function search_products ($field, $value, $store_id, $api_host = null, $offset = 0) {
  $curl = curl_init();
  if (!$api_host) {
    $api_host = 'https://apx-search.e-com.plus/api/v1';
  }
  if ($value && $field && strpos($field, 'specs.') !== false) {
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(array(
      "query" => array(
        "bool" => array(
          "filter" => array(array(
            "nested" => array(
              "path" => "specs",
              "query" => array(
                "terms" => array(
                  $field => array($value),
                ),
              ),
            ),
          )),
        ),
      ),
      "size" => 500,
      "from" => $offset,
    )));
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
      'X-Store-ID: ' . $store_id,
      'Content-Type: application/json',
    ));
    $endpoint = $api_host . '/items.json';
  } else {
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('X-Store-ID: ' . $store_id));
    $endpoint = $api_host . '/items.json?size=500&from=' . $offset . '&q=' . $field . ':"' . $value . '"';
  }
  curl_setopt($curl, CURLOPT_URL, $endpoint);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
  curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($curl, CURLOPT_TIMEOUT, 30);
  curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($curl, CURLOPT_FAILONERROR, false);
  curl_setopt($curl, CURLOPT_HTTP200ALIASES, array(400));
  $json = curl_exec($curl);
  $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  $curl_error = curl_error($curl);
  if ($json) {
    $response = $json;
  } elseif ($curl_error) {
    $response = "CURL Error: " . $curl_error;
  } else {
    $response = "HTTP: " . $http_code;
  }
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
    'ids' => $product_ids,
    'response' => $response
  );
}

$store_id = @$_SERVER['HTTP_X_STORE_ID'];
$store_domain = @$_SERVER['HTTP_X_STORE_DOMAIN'];
if (!$store_id) {
  echo 'Store ID unset';
  exit();
}

$base_uri = 'https://' . $store_domain;
if (isset($_GET['base_path'])) {
  $base_uri .= $_GET['base_path'];
} else {
  $base_uri .= '/';
}

$is_list_all = @$_SERVER['HTTP_X_PRODUCTS_FEED'] === 'ALL';
$is_api_v2 = @$_GET['api_v'] === '2';
$offset = 0;
$product_ids = null;
$search_endpoint = '';
$search_respose = '';
if ($is_list_all) {
  ignore_user_abort(true);
  set_time_limit(1680);
} else {
  $product_ids = isset($_GET['product_ids']) ? json_decode($_GET['product_ids'], true) : null;
  if (!$product_ids) {
    if (isset($_GET['offset']) && (int)$_GET['offset'] > 0) {
      $offset = (int)$_GET['offset'];
    }
    if (isset($_GET['search_field']) && isset($_GET['search_value'])) {
      $api_host = $is_api_v2 ? 'https://ecomplus.io/v2/search/_els' : null;
      $search_result = search_products(
        $_GET['search_field'],
        $_GET['search_value'],
        $store_id,
        $api_host,
        $offset
      );
      $offset = 0;
      $product_ids = $search_result['ids'];
      $search_endpoint = $search_result['endpoint'];
      $search_respose = $search_result['response'];
    }
  }
}
$is_skip_variations = isset($_GET['skip_variations']);
$discount = isset($_GET['discount']) && (float)$_GET['discount'] > 0
  ? (float)$_GET['discount']
  : 0;

$output_file = null;
$wip_output_file = null;

if ($is_list_all) {
  $output_file = "/tmp/products-feed-$store_id.xml";
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
    $link_replacement = strpos($base_uri, '{{_id}}')
      ? str_replace('{{_id}}', '$1', $base_uri)
      : $base_uri . '$2';
    echo preg_replace('/<!--([a-z0-9]+)-->{{base_uri}}([^<\s\n]+)/i', $link_replacement, $stored_xml);
  } else {
    http_response_code(202);
    echo 'xml is being generated, come back soon';
  }

  header('Connection: close');
  header('Content-Length: ' . ob_get_length());
  ob_end_flush();
  @ob_flush();
  flush();

  if (file_exists($output_file) && time() - filemtime($output_file) <= 600) {
    exit();
  }
}

$products_feed = new ProductsFeed(
  $store_id,
  !$output_file ? $base_uri : '{{base_uri}}',
  @$_SERVER['HTTP_X_STORE_API_HOST'],
  $is_api_v2 ? "https://ecomplus.io/:$store_id/v2" : null
);

$xml = $products_feed->xml(
  !$is_list_all ? @$_GET['title'] : null,
  !$is_list_all ? @$_GET['qs'] : null,
  !$is_list_all && isset($_GET['set_properties']) ? json_decode($_GET['set_properties'], true) : null,
  $product_ids,
  $search_endpoint,
  $offset,
  $is_list_all,
  $wip_output_file,
  $is_skip_variations,
  $discount
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
    echo "empty xml \n\n" . $search_endpoint . " \n" . (isset($product_ids) ? count($product_ids) : '_') . " \n\n" . $search_respose;
  }
} else if ($xml) {
  rename($wip_output_file, $output_file);
}
