<?php
include "amz.php";
include "pitfall.php";
echo "<pre>";
print_r(Pitfall::search(array("Title" => "the days are just packed", "Author" => "bill watterson")));

?>