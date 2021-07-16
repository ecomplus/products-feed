<?php
require './lib/utf8-sanitize.php';

class ProductsFeed {
  public $xml;
  public $convert;

  private $store_id; // 1000
  private $base_uri; // https://www.mysaleschannel.com/
  private $api_host;

  function __construct ($store_id, $base_uri, $api_host = null) {
    // setup store info
    $this->store_id = $store_id;
    $this->base_uri = $base_uri;
    $this->api_host = $api_host === null ? "https://ioapi.ecvol.com/$store_id/v1" : $api_host;
  }

  private function api_request ($endpoint, $method = 'GET', $data = null) {
    // send request to E-Com Plus Store API
    $curl = curl_init();

    if ($method === 'GET') {
      if ($data) {
        // parse data to query string
        $endpoint = $endpoint . '?' . http_build_query($data);
      }
    } else {
      if ($method === 'POST') {
        curl_setopt($curl, CURLOPT_POST, 1);
      } else {
        // PUT, PATCH, DELETE
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
      }
      if ($data) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
      }
    }

    curl_setopt($curl, CURLOPT_URL, $this->api_host . $endpoint);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    if (strpos($this->api_host, '/' . $this->store_id . '/') === false) {
      curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'X-Store-ID: ' . $this->store_id,
        'Content-Type: application/json',
      ));
    }

    $result = curl_exec($curl);
    if (!$result) {
      // @TODO
      exit('Connection Failure');
    }
    curl_close($curl);
    return $result;
  }

  function xml ($title, $query_string, $set_properties, $product_ids, $search_endpoint, $offset = 0, $is_list_all) {
    if (!$title || trim($title) === '') {
      $title = 'Products feed #' . $this->store_id;
    }
    $date = date('Y-m-d\TH:i:s\Z');
    $rand = rand(10000, 99999);

    if (!$product_ids) {
      // get all products
      $json = $this->api_request('/products.json');
      $product_ids = [];
      $result = json_decode($json);
      if (json_last_error() === JSON_ERROR_NONE) {
        $products = @$result->result;
        for ($i = 0; $i < count($products); $i++) {
          $product_ids[] = $products[$i]->_id;
        }
      }
    }
    $total = count($product_ids);
    if ($total > 0) {
      return null;
    }

    $xml = <<<XML
<?xml version="1.0"?>
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:g="http://base.google.com/ns/1.0">
  <title><![CDATA[$title]]></title>
  <link href="{$this->base_uri}" rel="alternate" type="text/html"/>
  <updated>$date</updated>
  <id><![CDATA[#{$this->store_id},(total:$total),(r:$rand),$search_endpoint]]></id>
  <author>
    <name>E-Com Plus</name>
  </author>
XML;

    // get each product body
    $count = 0;
    $max_items = $is_list_all ? 5000 : 500;
    for ($i = $offset; $i < $total && $count < $max_items; $i++) {
      // delay to prevent 503 error
      $count++;
      usleep(200);
      $endpoint = '/products/' . (string)$product_ids[$i] . '.json';
      $json = $this->api_request($endpoint);
      $product = json_decode($json, true);
      if (json_last_error() === JSON_ERROR_NONE && @$product['_id']) {
        // check if product is available
        if (@$product['available'] === true && @$product['visible'] === true) {
          // convert product to GMC XML entry
          $xml .= <<<XML
  {$this->convert($product, $query_string, $set_properties)}
XML;
        }
      } else {
        return array(
          'error' => true,
          'endpoint' => $endpoint,
          'response' => $json
        );
      }
    }

    $xml .= <<<XML
</feed>
XML;
    return $xml;
  }

  function convert ($body, $query_string, $set_properties, $group_id = null) {
    if (isset($body['name']) && isset($body['_id'])) {
      // start converting product body to XML
      // https://support.google.com/merchants/answer/7052112?hl=en
      if ($query_string) {
        // fix http query string
        $query_string = '?_=feed';
      }

      $entry = array(
        'id' => isset($body['sku']) ? $body['sku'] : $body['_id'],
        'title' => $body['name']
      );
      if ($group_id) {
        $entry['item_group_id'] = $group_id;
      }

      // text description
      if (isset($body['body_text'])) {
        $entry['description'] = $body['body_text'];
      } else if (isset($body['body_html'])) {
        $body_text = str_replace('&nbsp;', ' ', preg_replace('#<[^>]+>#', ' ', $body['body_html']));
        $entry['description'] = utf8_sanitize($body_text);
      }

      // product page links
      if (isset($body['permalink'])) {
        $entry['link'] = $body['permalink'] . $query_string;
      } else if (isset($body['slug'])) {
        $entry['link'] = $this->base_uri . $body['slug'] . $query_string;
      } else {
        $entry['link'] = $this->base_uri . $query_string . '&_id=' . $body['_id'];
      }
      if (isset($body['mobile_link'])) {
        $entry['mobile_link'] = $body['mobile_link'] . $query_string;
      }

      // stock
      if (!isset($body['quantity']) || ($body['quantity'] && $body['quantity'] >= @$body['min_quantity'])) {
        $entry['availability'] = 'in stock';
      } else {
        $entry['availability'] = 'out of stock';
      }

      // optional production (factory) time
      if (@$body['production_time']['days']) {
        $entry['min_handling_time'] = @$body['production_time']['days'];
        if (@$body['production_time']['max_time']) {
          $entry['max_handling_time'] = @$body['production_time']['max_time'];
        }
      }

      // product photos
      if (isset($body['pictures']) && count($body['pictures'])) {
        $additional_images = array();
        for ($i = 0; $i < count($body['pictures']) && $i < 10; $i++) {
          $img = $body['pictures'][$i];
          $img_link = null;
          if (isset($img['zoom'])) {
            $img_link = @$img['zoom']['url'];
          } else if (isset($img['big'])) {
            $img_link = @$img['big']['url'];
          } else {
            foreach ($img as $size => $img_obj) {
              // get any image size excepting small
              if (is_array($img_obj) && isset($img_obj['url']) && $size !== 'small') {
                $img_link = @$img_obj['url'];
                break;
              }
            }
          }

          if ($img_link) {
            if ($i === 0) {
              // first image
              $entry['image_link'] = $img_link;
            } else {
              $additional_images[] = $img_link;
            }
          }
        }
        if (count($additional_images) > 0) {
          $entry['additional_image_link'] = $additional_images;
        }
      }

      // prices
      if (isset($body['price'])) {
        if (isset($body['base_price']) && $body['base_price'] > $body['price']) {
          // promotional price
          $entry['price'] = $body['base_price'] . ' ' . @$body['currency_id'];
          $entry['sale_price'] = $body['price'] . ' ' . @$body['currency_id'];

          if (isset($body['price_effective_date'])) {
            $sale_start = @$body['price_effective_date']['start'];
            $sale_end = @$body['price_effective_date']['end'];
            if ($sale_start || $sale_end) {
              if (!$sale_start) {
                $sale_start = date('Y-m-d\TH:i:s\Z');
              }
              if (!$sale_end) {
                // any future date
                $sale_end = date('Y-m-d\TH:i:s\Z', strtotime('+60 days'));
              }
              if ($sale_start > $sale_end) {
                unset($entry['sale_price']);
              } else {
                $entry['sale_price_effective_date'] = $sale_start . '/' . $sale_end;
              }
            }
          }
        } else {
          // eg.: 10.00 BRL
          $entry['price'] = $body['price'] . ' ' . @$body['currency_id'];
        }
      }

      // categories
      if (isset($body['google_product_category_id'])) {
        $entry['google_product_category'] = $body['google_product_category_id'];
      }
      if (isset($body['category_tree']) && $body['category_tree'] !== '') {
        $entry['product_type'] = $body['category_tree'];
      } else if (isset($body['categories']) && count($body['categories']) > 0) {
        // get frst category only
        $entry['product_type'] = @$body['categories'][0]['name'];
      }

      // brand name
      $identifier_exists = false;
      if (isset($body['brands']) && count($body['brands']) > 0) {
        $entry['brand'] = @$body['brands'][0]['name'];
        $identifier_exists = true;
      }

      // product codes
      $codes = array('gtin', 'mpn');
      for ($i = 0; $i < count($codes); $i++) {
        $key = $codes[$i];
        if (isset($body[$key])) {
          if (is_string($body[$key]) && strlen($body[$key]) > 0) {
            // variation gtin/mpn
            $entry[$key] = $body[$key];
            if (!$identifier_exists) {
              $identifier_exists = true;
            }
          } else if (count($body[$key]) > 0) {
            // send first code on array only
            $entry[$key] = $body[$key][0];
            if (!$identifier_exists) {
              $identifier_exists = true;
            }
          }
        }
      }
      // if brand and/or code(s)
      $entry['identifier_exists'] = $identifier_exists ? 'yes' : 'no';

      // product types
      $entry['adult'] = @$body['adult'] === true ? 'yes' : 'no';
      if (isset($body['condition'])) {
        $entry['condition'] = $body['condition'] !== 'not_specified' ? $body['condition'] : 'new';
      }

      // product measures for shipping
      if (isset($body['weight'])) {
        $entry['shipping_weight'] = @$body['weight']['value'] . ' ' . @$body['weight']['unit'];
      }
      if (isset($body['dimensions'])) {
        $dimensions = array('length', 'width', 'height');
        for ($i = 0; $i < count($dimensions); $i++) {
          $key = $dimensions[$i];
          if ($measure_obj = @$body['dimensions'][$key]) {
            $entry['shipping_' . $key] = @$measure_obj['value'] . ' ' . @$measure_obj['unit'];
          }
        }
      }

      // handling specs
      if (isset($body['specifications'])) {
        $custom_label = 0;
        foreach ($body['specifications'] as $spec => $values) {
          if (count($values) === 0) continue;
          $value = $values[0];

          switch ($spec) {
            case 'energy_efficiency_class':
            case 'age_group':
            case 'gender':
            case 'size_type':
            case 'size_system':
              $entry[$spec] = @$value['value'] ? $value['value'] : @$value['text'];
              break;

            case 'size':
            case 'pattern':
            case 'material':
              $entry[$spec] = @$value['text'];
              break;

            case 'colors':
            case 'color':
            case 'cor':
            case 'cores':
              // send up to 3 colors
              $colors = str_replace('/', ' ', @$value['text']);
              for ($i = 1; $i < count($values); $i++) {
                $colors .= '/' . str_replace('/', ' ', @$values[$i]['text']);
              }
              $entry['color'] = $colors;
              break;

            default:
              // parse common GMC specs
              $common_spec = null;
              $text = @$values[$i]['value'] || @$values[$i]['text'];
              switch (strtolower($text)) {
                case 'm':
                case 'l':
                case 'g':
                case 's':
                case 'p':
                case 'xs':
                case 'pp':
                case 'xl':
                case 'gg':
                case 'xxl':
                case 'xg':
                case 'u':
                case 'Único':
                case 'unico':
                  $common_spec = 'size';
                  break;

                case 'adult':
                case 'adulto':
                case 'kids':
                case 'criança':
                case 'infant':
                case 'infantil':
                case 'newborn':
                case 'recém-nascido':
                case 'toddler':
                  $common_spec = 'age_group';
                  break;

                case 'male':
                case 'masculino':
                case 'female':
                case 'feminino':
                case 'unisex':
                  $common_spec = 'gender';
                  break;

                case 'homem':
                  $common_spec = 'gender';
                  $text = 'male';
                  break;

                case 'mulher':
                  $common_spec = 'gender';
                  $text = 'female';
                  break;

                default:
                  // send as custom label
                  if ($custom_label < 5) {
                    $entry['custom_label_' . $custom_label] = @$values[$i]['text'];
                  }
                  break;
              }
              if ($common_spec !== null && !isset($entry[$common_spec])) {
                $entry[$common_spec] = $text;
              }
              break;
          }
        }
      }

      if (is_array($set_properties)) {
        // force some data properties
        foreach ($set_properties as $key => $value) {
          $entry[$key] = $value;
        }
      }

      // parse to XML string
      $xml = '<entry>';
      foreach ($entry as $key => $value) {
        if (is_array($value)) {
          foreach ($value as $nested_value) {
            $xml .= '<g:' . $key . '><![CDATA[' . $nested_value . ']]></g:' . $key . '>';
          }
        } else {
          $xml .= '<g:' . $key . '><![CDATA[' . $value . ']]></g:' . $key . '>';
        }
      }
      $xml .= '</entry>';

      // handle product variations recursively
      if (isset($body['variations'])) {
        foreach ($body['variations'] as $variation) {
          // use default values from product body
          if (isset($body['specifications']) && $variation['specifications']) {
            $variation['specifications'] = array_merge(
              $body['specifications'],
              $variation['specifications']
            );
          }
          $variation = array_merge($body, $variation);
          unset($variation['variations']);

          // get the variation specific picture
          if (isset($variation['picture_id']) && isset($body['pictures'])) {
            $img_obj = null;
            for ($i = 0; $i < count($body['pictures']); $i++) {
              if ($body['pictures'][$i]['_id'] === $variation['picture_id']) {
                $img_obj = $body['pictures'][$i];
                break;
              }
            }
            if ($img_obj) {
              // overwrite pictures array
              $variation['pictures'] = array($img_obj);
            }
          }

          $xml .= $this->convert($variation, $query_string, $set_properties, $entry['id']);
        }
      }

      return $xml;
    } else {
      // no product data
      return '';
    }
  }
}
