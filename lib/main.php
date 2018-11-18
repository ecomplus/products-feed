<?php
class ProductsFeed {
  public $xml;
  public $convert;

  private $store_id; // 1000
  private $base_uri; // https://www.mysaleschannel.com/
  private $api_host;

  function __construct ($store_id, $base_uri, $api_host = 'https://api.e-com.plus/v1') {
    // setup store info
    $this->store_id = $store_id;
    $this->base_uri = $base_uri;
    $this->api_host = $api_host;
  }

  private function api_request ($endpoint, $method = 'GET', $data) {
    // send request to E-Com Plus Store API
    $curl = curl_init();

    if ($method === 'GET') {
      if ($data) {
        // parse data to query string
        $endpoint = $endpoint + '?' + http_build_query($data);
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
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
      'X-Store-ID: ' . $this->store_id,
      'Content-Type: application/json',
    ));

    $result = curl_exec($curl);
    if (!$result) {
      // @TODO
      exit('Connection Failure');
    }
    curl_close($curl);
    return $result;
  }

  function xml ($title = 'Products feed', $query_string, $set_properties, $product_ids) {
    $xml = <<<XML
<?xml version="1.0"?>
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:g="http://base.google.com/ns/1.0">
  <title><![CDATA[$title]]></title>
  <link rel="self" href="{this->$base_uri}"/>
  <updated>{date('Y-m-d\TH:i:s\Z')}</updated>
XML;

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

    // get each product body
    for ($i = 0; $i < count($product_ids); $i++) {
      // delay to prevent 503 error
      usleep($i * 300);
      $json = $this->api_request('/products/' . (string)$product_ids[$i] . '.json');
      $product = json_decode($json, true);
      if (json_last_error() === JSON_ERROR_NONE) {
        // check if product is available
        if (@$product['available'] === true && @$product['visible'] === true) {
          // convert product to GMC XML entry
          $xml += <<<XML
  {$this->convert($product, $query_string, $set_properties)}
XML;
        }
      }
    }

    $xml += <<<XML
</feed>
XML;
    return $xml;
  }

  function convert ($body, $query_string = '?_=feed', $set_properties, $group_id) {
    if (isset($body['name']) && isset($body['_id'])) {
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
        $entry['description'] = strip_tags($body['body_html']);
      }

      // product page links
      if (isset($body['permalink'])) {
        $entry['link'] = $body['permalink'] . $queryString;
      } else if (isset($body['slug'])) {
        $entry['link'] = $this->base_uri . $body['slug'] . $queryString;
      } else {
        $entry['link'] = $this->base_uri . $queryString + '&_id=' . $body['_id'];
      }
      if (isset($body['mobile_link'])) {
        $entry['mobile_link'] = $body['mobile_link'] . $queryString;
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
        $additional_images = '';
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
              if ($size !== 'small') {
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
              if ($additional_images !== '') {
                $additional_images .= ',';
              }
              $additional_images .= str_replace(',', '%2C', $img_link);
            }
          }
        }
        if ($additional_images !== '') {
          $entry['additional_image_link'] = $additional_images;
        }
      }

      // prices
      if (isset($body['price'])) {
        if (isset($body['base_price'])) {
          // promotional price
          $entry['price'] = $body['base_price'] . ' ' . @$body['currency_id'];
          $entry['sale_price'] = $body['price'] . ' ' . @$body['currency_id'];

          if (isset($body['price_effective_date'])) {
            if (isset($body['price_effective_date']['start'])) {
              $date_range = $body['price_effective_date']['start'];
            } else {
              $date_range = date('Y-m-d\TH:i:s\Z');
            }
            if (isset($body['price_effective_date']['end'])) {
              $date_range .= '/' . $body['price_effective_date']['start'];
            } else {
              // any future date
              $date_range .= '/' . date('Y-m-d\TH:i:s\Z', strtotime('+60 days'));
            }
            $entry['sale_price_effective_date'] = $date_range;
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
        if (isset($body[$key]) && count($body[$key]) > 0) {
          // send first code on array only
          $entry[$key] = $body[$key][0];
          if (!$identifier_exists) {
            $identifier_exists = true;
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
              // send up to 3 colors
              $colors = str_replace('/', ' ', @$value['text']);
              for ($i = 1; $i < count($values); $i++) {
                $colors .= '/' . str_replace('/', ' ', @$values[$i]['text']);
              }
              $entry[$spec] = $colors;
              break;

            default:
              // send as custom label
              if ($custom_label < 5) {
                $entry['custom_label_' . $custom_label] = @$values[$i]['text'];
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
        $xml .= '<g:' . $key . '><![CDATA[' . $value . ']]></g:' . $key . '>';
      }
      $xml .= '</entry>';

      // handle product variations recursively
      if ($body['variations']) {
        foreach ($body['variations'] as $variation) {
          // use default values from product body
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
