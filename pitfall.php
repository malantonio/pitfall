<?php
class Pitfall {

    /**
     *  Associate Tag + AWSAccessKeyId + Private Key
     *  --------------------------------------------
     *  unique keys provided by amazon
     */

    static $associateId = AMZ_ASSOCIATE_ID;
    static $publicKey = AMZ_PUBLIC_KEY;
    static $privateKey = AMZ_PRIVATE_KEY;


    /**
     *  Base URL
     *  --------
     *  the base of the request url
     */

    static $baseURL = "http://webservices.amazon.com/onca/xml?";


    /**
     *  Response Group
     *  --------------
     *  size of results response (ie. the fields provided)
     *  Pitfall::traverse() uses fields returned from the default, Medium,
     *  which might be a problem w/ the Small response group
     */

    static $responseGroup = "Medium";


    /**
     *  Version
     *  -------
     */

    static $version = "2011-08-01";

    
    /**
     *  Fields Returned
     *  ---------------
     *  an array of fields returned from the search. the names need to be formatted
     *  as amazon does. options include:
     *
     *    Actor             NumberOfDiscs
     *    Artist            Publisher
     *    ASIN              Price
     *    AudioFormat       ProductGroup
     *    Author            ProductTypeName
     *    Binding           RegionCode
     *    Description       ReleaseDate
     *    DetailPageURL     RunningTime
     *    Director          SmallImage
     *    Edition           Studio
     *    Genre             Title
     *    ISBN              UPC
     *    LargeImage
     *    MediumImage
     */

    static $fields = array(
        "Title",
        "Author",
        "ASIN",
        "ISBN",
        "Price",
        "SmallImage",
        "MediumImage",
        "LargeImage",
        "ReleaseDate",
        "Binding",
        "Publisher",
        "Description",
        "DetailPageURL"
    );


    /**
     *  Skip
     *  ----
     *  an associative array of fields => values to skip over
     *  ~~ TODO: would be better suited w/ three options: field, operator, value
     *  ~~ ex: ["Price", "<", 7.99]
     */

    static $skip = array(
        "ProductGroup" => "eBooks",
        "ProductTypeName" => "DOWNLOADABLE_MOVIE",
        "Price" => ""
    );


    /**
     *  search:
     *  -------
     *
     *      @param array $terms => an associative array of search terms
     *                            ** keys need to be title-cased **
     *                            (eg: "Keywords" => "calvin and hobbes")
     *      @param string $searchIndex => best suited for the following indicies:
     *                                  "Books", "DVD", "Music"
     */

    static function search($terms = array(), $searchIndex = "Books") {
        
        $url = self::buildSearchURL($terms, $searchIndex);

        $results = file_get_contents($url);
        $xml = new SimpleXMLElement($results);

        $final = array(); // final associative array
        $count = count($xml->{'Items'}->{'Item'});
        $i = 0;

        for($n = 0; $n < $count; $n++) {

            $item = $xml->{'Items'}->{'Item'}[$n];
            $skipme = false;

            // here we'll skip items that have field/values defined in Pitfall::$skip
            if (!empty(self::$skip)) {
                foreach(self::$skip as $field => $value) {
                    if (self::traverse($field, $item) == $value) {
                        $skipme = true;
                    }
                }
            }

            if ($skipme) { continue; }            

            // go through each field in our array and traverse the item out of there
            foreach(self::$fields as $field) {
                $final[$i][$field] = Pitfall::traverse($field, $item);
            }
            // move the $final array counter ahead
            $i++;
        }

        return $final;
    }


    /**
     *  batch:
     *  ------
     *  takes in an array of values and conducts Pitfall searches with each.
     * 
     *      @param array $input
     */

    static function batch($input) {

        $batchResults = array();

        foreach($input as $item) {
            
            // sort-of future proofing: we'll more-than-likely be working with just a set of values,
            // but we do want to make sure to be nimble enough to accept pre-formatted arrays of searches,
            // like "Title", "Author", etc.

            $query = !is_array($item) ? array("Keywords" => $item) : $item;
            $results = Pitfall::search($query);
            array_push($batchResults, $results);
        }

        return $batchResults;

    }


    /**
     *  buildSearchURL:
     *  ---------------
     *  constructs the url to search amazon with
     *      @param $terms & @param $searchIndex are passed from Pitfall::search
     */

    static function buildSearchURL($terms, $searchIndex) {
        $query = array(
            "AssociateTag" => self::$associateId,
            "AWSAccessKeyId" => self::$publicKey,
            "Operation" => "ItemSearch",
            "ResponseGroup" => self::$responseGroup,
            "SearchIndex" => $searchIndex,
            "Service" => "AWSECommerceService",
            "Timestamp" => rawurlencode(gmdate('Y-m-d\TH:i:s\Z')),
            "Version" => self::$version
        );

        // we'll push each search term into the query array
        foreach($terms as $key => $value) {

            // if our $value is a 13-digit ISBN number w/ a dash, let's remove the dash
            $value = preg_match("/\d{3}-\d+/", $value) ? str_replace("-", "", $value) : $value;
            $query[$key] = rawurlencode($value);
        }

        //sort the array before breaking up the signature
        ksort($query);
        $signature = self::buildSignature($query);
        $queryString = "";
        
        foreach($query as $k => $v) {
            $queryString .= $k . "=" . $v . "&";
        }

        $queryString = substr($queryString, 0, -1);

        return self::$baseURL . $queryString . "&Signature=" . $signature;
    }

    /**
     *  buildSignature:
     *  ---------------
     *  does what it says
     *      @param array $array
     */

    private static function buildSignature($array) {
        $sigString = "GET\nwebservices.amazon.com\n/onca/xml\n";

        foreach($array as $k => $v) {
            $sigString .= $k . "=" . $v . "&";
        }

        $sigString = substr($sigString, 0, -1);

        return rawurlencode(base64_encode(hash_hmac('sha256', $sigString, self::$privateKey, true)));
    }


    /**
     *  traverse:
     *  ---------
     *  used to parse through a returned item to prune out fields
     *      @param string $field => the field desired
     *      @param SimpleXMLElement object $item  => the item being searched
     */

    private static function traverse($field, $item) {
        // some fields are completely missing from the xml tree
        // (example: pre-ordered items might lack an <EditorialReviews> tag completely)
        // in that case, we'll return an empty string
        try {
            switch($field) {
                case "ASIN":            $value = (string) $item->{'ASIN'}; break;
                case "AudioFormat":     $value = (string) $item->{'ItemAttributes'}->{'AudioFormat'}; break;
                case "Binding":         $value = (string) $item->{'ItemAttributes'}->{'Binding'}; break;
                case "Description":     $value = (string) $item->{'EditorialReviews'}->{'EditorialReview'}->{'Content'}; break;
                case "DetailPageURL":   $value = (string) $item->{'DetailPageURL'}; break;      
                case "Director":        $value = (string) $item->{'ItemAttributes'}->{'Director'}; break;
                case "Edition":         $value = (string) $item->{'ItemAttributes'}->{'Edition'}; break;      
                case "Genre":           $value = (string) $item->{'ItemAttributes'}->{'Genre'}; break;
                case "ISBN":            $value = (string) $item->{'ItemAttributes'}->{'ISBN'}; break;
                case "LargeImage":      $value = (string) $item->{'LargeImage'}->{'URL'}; break;
                case "MediumImage":     $value = (string) $item->{'MediumImage'}->{'URL'}; break;
                case "NumberOfDiscs":   $value = (string) $item->{'ItemAttributes'}->{'NumberOfDiscs'}; break;
                case "Publisher":       $value = (string) $item->{'ItemAttributes'}->{'Label'}; break;
                case "Price":           $value = (string) $item->{'ItemAttributes'}->{'ListPrice'}->{'FormattedPrice'}; break;
                case "ProductGroup":    $value = (string) $item->{'ItemAttributes'}->{'ProductGroup'}; break;
                case "ProductTypeName": $value = (string) $item->{'ItemAttributes'}->{'ProductTypeName'}; break;
                case "RegionCode":      $value = (string) $item->{'ItemAttributes'}->{'RegionCode'}; break;
                case "ReleaseDate":     $value = (string) $item->{'ItemAttributes'}->{'ReleaseDate'}; break;
                case "RunningTime":     $value = (string) $item->{'ItemAttributes'}->{'RunningTime'}; break;
                case "SmallImage":      $value = (string) $item->{'SmallImage'}->{'URL'}; break;
                case "Studio":          $value = (string) $item->{'ItemAttributes'}->{'Studio'}; break;
                case "Title":           $value = (string) $item->{'ItemAttributes'}->{'Title'}; break;
                case "UPC":             $value = (string) $item->{'ItemAttributes'}->{'UPC'}; break;
                case "Actor":         
                    $value = array();
                    $count = count($item->{'ItemAttributes'}->{'Actor'});
                    
                    for($i = 0; $i < $count; $i++) {
                        $value[] = (string) $item->{'ItemAttributes'}->{'Actor'}[$i];
                    }
    
                    break;
              
                case "Artist":
                case "Author":
                    $au = $item->{'ItemAttributes'}->{'Author'};
                    $ar = $item->{'ItemAttributes'}->{'Artist'};
    
                    // because it's generally one or the other, we
                    //  can use the non-empty value for the var
                    $a = empty($au) ? $ar : $au;
    
                    if (count($a) > 1) {
                        $value = array();
                        
                        for($i = 0; $i < count($a); $i++) {
                            $value[] = (string) $a[$i];
                        }
                    } else {
                        $value = (string) $a;
                    }
                    
                    break;
            }
    
            // we're returning strings, so if $value is an array of authors/artists/actors/etc.
            // we'll smoosh it down
            if (is_array($value)) { $value = implode(", ", $value); }
        
        } catch ( \Exception $e ) {
            $value = "";
        }
        
        return $value;
    }
}
?>
