<?php
require './lib/main.php';

$products_feed = new ProductsFeed(
  @$_SERVER['HTTP_X_STORE_ID'],
  'https://' . @$_SERVER['HTTP_X_STORE_DOMAIN'] . '/'
);

echo $products_feed->xml(
  @$_GET['title'],
  @$_GET['query_string'],
  isset($_GET['set_properties']) ? json_decode($_GET['set_properties']) : null,
  isset($_GET['product_ids']) ? json_decode($_GET['product_ids']) : null
);
