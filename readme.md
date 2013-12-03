# Pitfall - amazon.com traversing in php

Pitfall is designed to conduct a search using amazon.com's ecommerce api and return the results in an easy-to-browse associtative array. 

## Getting started

Define your Amazon credential variables like so:

```PHP
<?php
define('PUBLIC_KEY', '');
define('PRIVATE_KEY', '');
define('ASSOCIATE_ID', '');
?>
```

## Pitfall::search()

This takes in an associative array where the key is the scope (eg. "Keywords", "Title", "Author") and the value is the search terms. It also takes in an optional searchIndex (defaults to "Books", others include "DVD", "Music", etc.)

[Amazon documentation on search scopes (for ItemSearch) to use](http://docs.aws.amazon.com/AWSECommerceService/latest/DG/ItemSearch.html)

[Amazon documentation on Search Indecies, US](http://docs.aws.amazon.com/AWSECommerceService/latest/DG/USSearchIndexParamForItemsearch.html)

__NOTE:__ when defining the scope or searchIndex, be sure to match the case used by Amazon.

####example

```PHP
$searchTerms = array("Title" => "the days are just packed", "Author" => "bill watterson"); 
$results = Pitfall::search($searchTerms, $searchIndex);
```

## Pitfall::buildSearchURL()

If you want to just generate an url to the Amazon xml search results, use `Pitfall::buildSearchUrl($searchTerms, $searchIndex)` (using the same search terms and index vars as `search()`). This does the messy work of generating the search query and signature.


## Pitfall vars

`Pitfall::$fields` is an array of fields to be returned from the search

`Pitfall::$skip` is an array of fields to look for to qualify an item to be skipped. As of now, this only skips items where the key matches the value. Example:

```PHP
/**
 *  default value
 *  skips ebooks & downloadable movies & items with no price
 */
 
Pitfall::$skip = array(
    "ProductGroup" => "eBooks",
    "ProductTypeName" => "DOWNLOADABLE_MOVIE",
    "Price" => ""
);
```
