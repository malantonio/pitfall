<?php
include "amz.php";
include "pitfall.php";
echo "<pre>";
print_r(Pitfall::search(array("Keywords" => "calvin and hobbes"), "Books"));
?>