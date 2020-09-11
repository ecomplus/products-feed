# products-feed

Products XML feed with GMC specifications for E-Com Plus stores

## XML URL

https://storefront.e-com.plus/products-feed.xml?store_id={storeId}&domain={domain}

### Optional URL params

- `title`
- `query_string`
- `set_properties` (JSON object)
- `product_ids` (JSON array)
- `offset`
- `search_field`
- `search_value`

### Additional examples

> Forcing `google_product_category_id`

```
https://storefront.e-com.plus/products-feed.xml?store_id={storeId}&domain={domain}&set_properties={"google_product_category_id":123}
```

> Specific products by IDs

```
https://storefront.e-com.plus/products-feed.xml?store_id={storeId}&domain={domain}&product_ids=[123,234]
```

> Filter products by category

```
https://storefront.e-com.plus/products-feed.xml?store_id={storeId}&domain={domain}&search_field=categories.slug&search_value=my-category-slug&set_properties={"google_product_category_id":123}
```

> Second XML page

```
https://storefront.e-com.plus/products-feed.xml?store_id={storeId}&domain={domain}&offset=500
```
