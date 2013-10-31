<?php
  include_once "amz.php";
  include_once "pitfall.php";
  $pit = new Pitfall(PUBKEY, PRIKEY, ASSOCKEY);

  //$array = $pit->search(array("Keywords" => "Lord of the Rings"));
  $pit->fields = array("Title", "Author", "Description", "Binding", "ReleaseDate");
  $array = $pit->search(array("Keywords" => "6304119054"), "DVD");
  echo "<pre>";
  print_r($array);

?>