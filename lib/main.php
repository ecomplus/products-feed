<?php

class Main
{
    private $_data;

    public function __construct()
    {
    }

    public function request($method, $url, $data, $headers)
    {
        $curl = curl_init();
     
        switch ($method) {
           case "POST":
              curl_setopt($curl, CURLOPT_POST, 1);
              if ($data) {
                  curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
              }
              break;
           case "PUT":
              curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
              if ($data) {
                  curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
              }
              break;
           default:
              if ($data) {
                  $url = sprintf("%s?%s", $url, http_build_query($data));
              }
        }
     
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        
        if ($headers) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
               'X-Store-ID: 100',
               'Content-Type: application/json',
            ));
        }

        $result = curl_exec($curl);
        if (!$result) {
            die("Connection Failure");
        }
        curl_close($curl);
        return $result;
    }

    public function convert()
    {
        $product = json_decode('{"_id":"123a5432109876543210cdef","created_at":"2017-12-01T01:00:30.612Z","store_id":100,"sku":"s-MP_2B4","commodity_type":"physical","name":"Mens Pique Polo Shirt","slug":"mens-pique-polo-shirt","available":true,"visible":true,"ad_relevance":0,"short_description":"Red, 100% cotton, large men’s t-shirt","body_html":"<p>Red, 100% cotton, large men’s t-shirt.</p><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.</p>","body_text":"Red, 100% cotton, large men’s t-shirt.\nLorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.","meta_title":"Mens Pique Polo Shirt - My Shirt Shop","meta_description":"Mens Pique Polo Shirt, Red, 100% cotton, large men’s t-shirt","keywords":["tshirt","t-shirt","man"],"price":42.9,"price_effective_date":{"end":"2018-12-01T10:00:00.612Z"},"base_price":60,"currency_id":"BRL","currency_symbol":"R$","quantity":100,"manage_stock":true,"dimensions":{"width":{"value":10,"unit":"cm"},"height":{"value":8,"unit":"cm"},"length":{"value":8,"unit":"cm"}},"weight":{"value":400,"unit":"g"},"condition":"new","adult":false,"brands":[{"_id":"a10000000000000000000001","name":"Shirts Example","slug":"shirts-example","logo":{"url":"https://mycdn.com/shirts-example.jpg","size":"100x50"}}],"categories":[{"_id":"f10000000000000000000001","name":"Polo Shirts","slug":"polo"}],"specifications":{"age_group":[{"text":"Adult","value":"adult"}],"gender":[{"text":"Male","value":"male"}],"size":[{"text":"Large","value":"large"}],"size_type":[{"text":"Regular","value":"regular"}],"size_system":[{"text":"BR","value":"BR"}],"material":[{"text":"Cotton","value":"cotton"}],"colors":[{"text":"Pique","value":"#ff5b00"}]},"auto_fill_related_products":true,"gtin":["12345678901234"],"mpn":["T1230"]}');
        $dominio = $_SERVER['HTTP_HOST'];
        $queryString = '';
        $promotion_id = '';

        $xml = '<?xml version="1.0"?>
            <feed xmlns="http://www.w3.org/2005/Atom" xmlns:g="http://base.google.com/ns/1.0">
            <title>Example - Online Store</title>
            <link rel="self" href="http://www.example.com"/>
            <updated>20011-07-11T12:00:00Z</updated> 
            <entry>';
        
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
        $xml .= '</feed>';

        //echo $xml;
        print_r($xml);
    }

    // not implemented
    public function getCategoriesParent($id)
    {
    }

    // not implemented
    public function setPromotionId(){

    }
}

$t = new Main();
print_r($t->convert());
//print_r($t->request('GET', 'https://api.e-com.plus/v1/products/123a5432109876543210cdef.json', false, true));
