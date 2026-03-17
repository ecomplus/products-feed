# products-feed

Products XML feed with GMC specifications for E-Com Plus stores

## XML URL

https://storefront.e-com.plus/products-feed.xml?store_id={storeId}&domain={domain}

### Optional URL params

- `title`
- `qs`
- `set_properties` (JSON object)
- `product_ids` (JSON array)
- `offset`
- `search_field`
- `search_value`
- `base_path` — appended to the store domain to build product URLs (e.g. `/en/`)
- `skip_variations` — when present, product variations are omitted from the feed
- `only_in_stock` — when present, only products with available stock are included
- `discount` — apply a percentage discount to all prices (e.g. `discount=10` for 10% off)
- `api_v` — set to `2` to use E-Com Plus API v2 for search requests

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

> Only in-stock products

```
https://storefront.e-com.plus/products-feed.xml?store_id={storeId}&domain={domain}&only_in_stock
```

> Apply a 10% discount to all prices

```
https://storefront.e-com.plus/products-feed.xml?store_id={storeId}&domain={domain}&discount=10
```

> Skip variations (list only parent products)

```
https://storefront.e-com.plus/products-feed.xml?store_id={storeId}&domain={domain}&skip_variations
```

> Custom base path for product URLs

```
https://storefront.e-com.plus/products-feed.xml?store_id={storeId}&domain={domain}&base_path=/en/
```

## All products XML

https://storefront.e-com.plus/products-feed/all.xml?store_id={storeId}&domain={domain}

Up to 5000 products (may have more items due to variations) at the same XML.

:warning: Long time to load and long cache lifetime, AVOID USING it whenever possible!
