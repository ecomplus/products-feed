<?php
class ProductsFeed {
  public $xml;

  private $store_id;
  private $base_uri;
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

  function xml ($title = 'Products feed', $promotion_id, $product_ids) {
    $xml = <<<XML
<?xml version="1.0"?>
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:g="http://base.google.com/ns/1.0">
  <title><![CDATA[$title]]></title>
  <link rel="self" href="this->$base_uri"/>
  <updated>{date('Y-m-d\TH:i:s\Z')}</updated>
XML;

    if (!$product_ids) {
      // get all products
      $json = $this->api_request('/products.json');
      $product_ids = [];
      $products = json_decode($json);
      if (json_last_error() === JSON_ERROR_NONE) {
        for ($i = 0; $i < count($products); $i++) {
          $product_ids[] = $products[$i]->_id;
        }
      }
    }

    // get each product body
    for ($i = 0; $i < count($product_ids); $i++) {
      // delay to prevent 503 error
      usleep($i * 300);
      $json = $this->api_request('/products' . $product_ids[$i] . '.json');
      $product = json_decode($json);
      if (json_last_error() === JSON_ERROR_NONE) {
        // convert product to GMC XML entry
        $xml += <<<XML
  {$this->convert($product, $promotion_id)}
XML;
      }
    }

    $xml += <<<XML
</feed>
XML;
    return $xml;
  }

  public function convert ($product, $promotion_id) {
    $xml = '<entry>';
    if (isset($product->_id)) {
      $xml .= '<g:id>' . $product->_id . '<g:id>';
    }
    if (isset($product->name)) {
      $xml .= '<g:title>' . $product->name . '<g:title>';
    }
    if (isset($product->body_text)) {
      $xml .= '<g:description>' . $product->body_text . '<g:description>';
    }
    if (isset($product->slug)) {
      $xml .= '<g:link>' . $dominio . '/' . $product->slug . '?' . $queryString . '<g:link>';
    }
    if (isset($product->pictures)) {
      $xml .= '<g:image_link>' . $product->pictures[0]['zoom']  . '<g:image_link>';
      $xml .= '<g:additional_image_link>' . $product->pictures[0]['zoom'] . '<g:additional_image_link>';
    }
    if (isset($product->mobile_link)) {
      $xml .= '<g:mobile_link>' . $product->mobile_link . $queryString . '<g:mobile_link>';
    }
    if (isset($product->available)) {
      $xml .= '<g:availability>' . $product->available == true ? 'in stock' : 'out of stock' . '<g:availability>';
    }
    //$xml .= '<g:availability_date>' . . '<g:availability_date>'; // optional
    //$xml .= '<g:expiration_date>' . . '<g:expiration_date>'; // optional

    if (isset($product->price)) {
      $xml .= '<g:price>' . $product->price . '<g:price>';
    }
    if (isset($product->other_price)) {
      $xml .= '<g:sale_price>' . $product->other_price->price . '<g:sale_price>';
    }
    if (isset($product->price_change_records)) {
      $xml .= '<g:sale_price_effective_date>' . $product->price_change_records->date_of_change . '<g:sale_price_effective_date>';
    }

    //$xml .= '<g:cost_of_goods_sold>' . . '<g:cost_of_goods_sold>'; // optional

    if (isset($product->measurement)) {
      $xml .= '<g:unit_pricing_measure>' . $product->measurement->unit . '<g:unit_pricing_measure>';
      $xml .= '<g:unit_pricing_base_measure>' . $product->measurement->pricing_base_measure  . '<g:unit_pricing_base_measure>';
    }
    if (isset($product->installments)) {
      $xml .= '<g:installment>' . '<g:months>'. $product->installments->number .'</g:months>'.'<g:amount>'. $product->installments->number .'</g:amount>'. '<g:installment>';
    }
    if (isset($product->google_product_category_id)) {
      $xml .= '<g:google_product_category>' . $product->google_product_category_id . '<g:google_product_category>';
    }
    if (isset($product->categories)) {
      $xml .= '<g:product_type>' . $this->getCategoriesParent($product->categories[0]->_id) . '<g:product_type>';
    }
    if (isset($product->brands)) {
      $xml .= '<g:brand>' . $product->brands[0]->name. '<g:brand>';
    }
    if (isset($product->gtin)) {
      $xml .= '<g:gtin>' . $product->gtin[0] . '<g:gtin>';
    }
    if (isset($product->mpn)) {
      $xml .= '<g:mpn>' .$product->mpn[0] . '<g:mpn>';
    }

    $xml .= '<g:identifier_exists>' . !isset($product->brands[0]->name) && !isset($product->gtin[0]) && isset($product->mpn[0]) ? 'no' : 'yes' . '<g:identifier_exists>';

    if (isset($product->condition)) {
      $xml .= '<g:condition>' . $product->condition . '<g:condition>';
    }
    if (isset($product->adult)) {
      $xml .= '<g:adult>' . $product->adult . '<g:adult>';
    }
    if (isset($product->multipack)) {
      $xml .= '<g:multipack>' . $product->multipack . '<g:multipack>';
    }

    $xml .= '<g:is_bundle>' . 'no' . '<g:is_bundle>';

    if (isset($product->specifications->energy_efficiency_class)) {
      $xml .= '<g:energy_efficiency_class>' . $product->specifications->energy_efficiency_class->text . '<g:energy_efficiency_class>';  //optional
      $xml .= '<g:min_energy_efficiency_class>' . $product->specifications->energy_efficiency_class->value. '<g:min_energy_efficiency_class>'; //optional
      //$xml .= '<g:max_energy_efficiency_class>' . . '<g:max_energy_efficiency_class>'; //optional
    }

    if (isset($product->specifications)) {
      $xml .= '<g:age_group>' . $product->specifications->age_group[0]->text . '<g:age_group>';
      $xml .= '<g:color>' . $product->specifications->colors[0]->text . '<g:color>';
      $xml .= '<g:gender>' . $product->specifications->gender[0]->value . '<g:gender>';
      $xml .= '<g:material>' . $product->specifications->material[0]->value . '<g:material>';
      $xml .= '<g:pattern>' . $product->specifications->pattern->value. '<g:pattern>';
      $xml .= '<g:size>' . $product->specifications->size[0]->value . '<g:size>';
      $xml .= '<g:size_type>' . $product->specifications->size_type[0]->value . '<g:size_type>';
      $xml .= '<g:size_system>' . $product->specifications->size_system[0]->value. '<g:size_system>';
    }

    //$xml .= '<g:item_group_id>' . . '<g:item_group_id>'; //
    foreach ($product->specifications as $key => $value) {
      $xml .= "<g:custom_label_{$key}>" .$value. "<g:custom_label_{$key}>"; //optional
    }

    if ($promotion_id) {
      $xml .= '<g:promotion_id>' . $promotion_id . '<g:promotion_id>'; //optional
    }

    if (isset($product->destinations)) {
      $xml .= '<g:excluded_destination>' .$product->destinations->excluded->country . '<g:excluded_destination>'; // optional
      $xml .= '<g:included_destination>' . $product->destinations->included->country . '<g:included_destination>'; // optional
    }
    if (isset($product->shipping_methods)) {
      foreach ($product->shipping_methods as $value) {
        $xml .= '<g:shipping>' . $value->label . '<g:shipping>';
        $xml .= '<g:shipping_label>' .  $value->label . '<g:shipping_label>';
      }
    }
    if (isset($product->weight)) {
      $xml .= '<g:shipping_weight>' . $product->weight->value . $product->weight->unit . '<g:shipping_weight>';
    }
    if (isset($product->dimensions)) {
      $xml .= '<g:shipping_length>' . $product->dimensions->length->value . '<g:shipping_length>';
    }
    if (isset($product->production_time)) {
      $xml .= '<g:min_handling_time>' . $product->production_time->days . '<g:min_handling_time>'; //optional
      $xml .= '<g:max_handling_time>' . $product->production_time->max_time . '<g:max_handling_time>'; //optional
    }

    //$xml .= '<g:tax>' . . '<g:tax>';
    //$xml .= '<g:tax_category>' . . '<g:tax_category>';

    $xml .= '</entry>';
    return $xml;
  }

  public function getCategoriesParent ($id) {
  }
}
