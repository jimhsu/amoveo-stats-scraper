<?php

$reqfilename = "data";

$cachefile = "cache/".$reqfilename.".html";

$cachetime = 60 * 60;

// Serve from the cache if it is younger than $cachetime

      if (file_exists($cachefile) && (time() - $cachetime
         < filemtime($cachefile))) 
      {

         include($cachefile);

         echo "<!-- Cached " . date('jS F Y H:i', filemtime($cachefile)). "-->";

         exit;
      }
      ob_start(); // start the output buffer
	  
?>

<html><body>

<?php
//
// A very simple PHP example that sends a HTTP POST to a remote site
//
function curl_request($url, $post)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec ($ch);
	curl_close ($ch);
	return $result;
}

function process_int($input, $nth=1)
{
	$array = explode(",", $input);
	return (int)$array[$nth];
}

function process_nethash($interval)
{
	$request = curl_request("http://localhost:8081",'["mining_data", ' . $interval . ']');
	$str = substr($request,10,-2);
	$array = array_map('intval', explode(",",$str));
	return $array;
}

function bin_to_int_diff($d) {
	$a = (int) ($d / 256);
	$b = $d % 256;
	return 2 ** $a * (256 + $b) / 256;
}

$url_local = "http://localhost:8081";
$url_remote = "http://localhost:8080";
$interval = 20;

$blockNum = [];
$diff = [];
$nethash = [];

$diffI = 0;
$diffT = 0;

$height = process_int(curl_request($url_remote,'["height"]'));
for($i=0;$i<$height;$i+=$interval) {
	if($i % 2000 == 0)
	{
		$diffI = process_int(curl_request($url_remote,'["block", ' . ($i+1) . ']'),6);
		$diffT = (float)bin_to_int_diff($diffI) / (10 ** 12); // conversion from bin diff to Terahashes
	}
	array_push($blockNum,$i);
	array_push($diff,$diffT);
}
$nethash = array_merge([1], process_nethash($interval));

print("<Table><tr><th>Block</th><th>Difficulty (TH/b)</th><th>Nethash (GH/s)</th></tr>");
for($i=0;$i<count($blockNum);$i++) {
	print("<tr><td>" . $blockNum[$i] . "</td><td>" . round((float)$diff[$i],3) . "</td><td>" . $nethash[$i] . "</td></tr>");
}
print("</table>");

?>
</body>

<?php
       // open the cache file for writing
       $fp = fopen($cachefile, 'w'); 


       // save the contents of output buffer to the file
	    fwrite($fp, ob_get_contents());

		// close the file

        fclose($fp); 

		// Send the output to the browser
        ob_end_flush(); 
?>