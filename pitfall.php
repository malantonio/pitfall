<?php
/////////////////////////////////////////////
////  PITFALL : an amazon api connector  ////
/////////////////////////////////////////////

class Pitfall {
  private $publicKey; 
  private $privateKey;
  private $associateId;
  public  $searchTerm;
  public  $version = "2010-11-01";
  public  $fields = array("Title", "Author", "ASIN", "ISBN", "Price", "SmallImage", "MediumImage", "ReleaseDate", "Binding", "Publisher", "Description");

  public function __construct($public_key, $private_key, $associate_id) {
    $this->publicKey = $public_key;
    $this->privateKey = $private_key;
    $this->associateId = $associate_id;
  }

  /////////////////////////////////////////////////////
  /////////////////////////////////////////////////////
  ////  SEARCHING takes in two vars, one of which  ////
  ////  is optional: an array of the search, and   ////
  ////  (optional) the searchIndex. format the     ////
  ////  query($search) like so:                    ////
  ////  -----------------------------------------  ////
  ////  array("Keywords" => "calvin and hobbes")   ////
  ////  -----------------------------------------  ////
  ////  the searchIndex is best suited for the     ////
  ////  following indices:                         ////
  ////  -----------------------------------------  ////
  ////          "Books", "DVD", "Music"            ////
  ////  -----------------------------------------  ////
  ////  check out the README file for more info.   ////
  /////////////////////////////////////////////////////
  /////////////////////////////////////////////////////

  public function search($search = array(), $searchIndex = "Books") { 
    $this->search = $search; // search should be an array like this: array("Keywords" => "the shining")
    $this->searchIndex = $searchIndex;

    // get our url to pull from
    $url = $this->buildSearchURL();
    
    // now get some xml all up in heeyah
    $results = file_get_contents($url);
    $xml = new SimpleXMLElement($results);
  
  // DEBUGGING: print out the returned xml array
  // echo "<pre>"; 
  // print_r($xml); // this is for

    $final = array(); // $final is the final associative array
    $count = count($xml->{'Items'}->{'Item'});
    $i = 0;

    // loop through the returned results and build an associative array for each
    for($n = 0; $n < $count; $n++) {
      $item = $xml->{'Items'}->{'Item'}[$n];

      // we're not interested in eBooks or downloadable movies, so we'll skip over those entries
      // (note for future self: have this be a public var, like $this->skip)
      if ( 
          $this->traverse("ProductGroup", $item) == "eBooks"
       || $this->traverse("ProductTypeName", $item) == "DOWNLOADABLE_MOVIE"
       || $this->traverse("Price", $item) == ""
      ) { continue; }

      // go through each field in our array and traverse the item out of there
      foreach($this->fields as $field) {
        $final[$i][$field] = $this->traverse($field, $item);
      }
      // move the $final array counter ahead
      $i++;
    }

    return $final;

  }

  private function buildSearchURL() {
    // builds the funky request url to amazon's liking

    $query = array(
      "AssociateTag" => $this->associateId,
      "AWSAccessKeyId" => $this->publicKey,
      "Operation" => "ItemSearch",
      "ResponseGroup" => "Medium", // the medium response group returns a good amount of information
      "SearchIndex" => $this->searchIndex,
      "Service" => "AWSECommerceService",
      "Timestamp" => rawurlencode(gmdate('Y-m-d\TH:i:s\Z')),
      "Version" => $this->version
    );

    // we'll push each search term into the array here
    foreach($this->search as $key => $val) {
      $query[rawurlencode($key)] = rawurlencode($val); // raw url encode just in case!
    }

    // sort the array before breaking up the signature
    ksort($query);
    $signature = $this->buildSignature($query);

    $queryString = "";
    
    foreach($query as $k => $v) {
      $queryString .= $k . "=" . $v . "&";
    }

    $fullpath = "http://webservices.amazon.com/onca/xml?" . $queryString . "Signature=" . $signature;
    return $fullpath;

  }

  private function buildSignature($array) {
    // as seen above, we'll take in the string as an array, split it up, then return our encoded signature

    $sigString = "GET\nwebservices.amazon.com\n/onca/xml\n"; // these are the headers that go before the query string

    // take a foreach hammer to that array and build a query string from it
    foreach($array as $k => $v) {
      $sigString .= $k . "=" . $v . "&";
    }

    // before we go anywhere, let's strip off that trailing ampersand
    $sigString = rtrim($sigString, "&");
    
    // encode all dat shit, son
    $sig = rawurlencode(base64_encode(hash_hmac('sha256', $sigString, $this->privateKey, true)));

    return $sig;

  }

  private function traverse($field, $item) {
    // the muscle and timesaver. finds the items in the returned xml and returns a string value (or, in the case
    //     of the actors field, an array)

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

        if(count($a) > 1) {
          $value = array();
          for($i = 0; $i < count($a); $i++) {
            $value[] = (string) $a[$i];
          }
        } else {
          $value = (string) $a;
        }
        break;      
    }
    return $value;
  }
}
?>