<?php

$reqfilename = "csv";

$cachefile = "cache/".$reqfilename.".html";

$cachetime = 60;

// Serve from the cache if it is younger than $cachetime

      if (file_exists($cachefile) && (time() - $cachetime
         < filemtime($cachefile))) 
      {

         include($cachefile);

         // echo "<!-- Cached " . date('jS F Y H:i', filemtime($cachefile)). "-->";

         exit;
      }
      ob_start(); // start the output buffer
	  
?>

<?php
//
// A very simple PHP example that sends a HTTP POST to a remote site
//

ob_clean();

print("block,difficulty,nethash,blocktime\n");

class block {
	public $blocknum = 0;
	public $difficulty = 0;
	public $nethash = 0;
	public $blocktime = 0;
}

$servername = "localhost";
$username = "amoveostats";
$password = "";
$dbname = "amoveostats";


// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT * FROM hashrate";
$result = $conn->query($sql);

$arr = [];
$b = new block();



if ($result->num_rows > 0) {
	while($row = $result->fetch_assoc()) {
		print($row["block"] . "," . $row["difficulty"] . "," . $row["nethash"] . "," . $row["blocktime"] . "\n");
	}
}
?>


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