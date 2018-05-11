
<?php
header('Access-Control-Allow-Origin: *');  

$reqfilename = "json";

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
header('Content-Type: application/json');

class block {
	public $blocknum = 0;
	public $difficulty = 0;
	public $nethash = 0;
	public $blocktime = 0;
}

$servername = "localhost";
$username = "amoveostats";
$password = "3v5tZRqJrgViZIMY";
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

$blocknum = [];
$difficulty = [];
$nethash = [];
$blocktime = [];
$hashpredict = [];

if ($result->num_rows > 0) {
	while($row = $result->fetch_assoc()) {
		array_push($blocknum,$row["block"]);
		array_push($difficulty,$row["difficulty"]);
		array_push($nethash,$row["nethash"]);
		array_push($blocktime,$row["blocktime"]);
		array_push($hashpredict,$row["hashpredict"]);
	}
}
$arr = array(
	"xData"=>$blocknum,
	"datasets"=>array(
		array(
			"name"=>"difficulty",
			"data"=>$difficulty,
			"unit"=>"THash/block",
			"type"=>"line",
			"valueDecimals"=>3
		),
		array(
			"name"=>"nethash",
			"data"=>$nethash,
			"unit"=>"GHash/sec",
			"type"=>"line",
			"valueDecimals"=>0
		),
		array(
			"name"=>"blocktime",
			"data"=>$blocktime,
			"unit"=>"seconds",
			"type"=>"line",
			"valueDecimals"=>0
		),
		array(
			"name"=>"predicted_difficulty",
			"data"=>$hashpredict,
			"unit"=>"THash/block",
			"type"=>"line",
			"valueDecimals"=>3
		))
	);
print json_encode($arr,JSON_NUMERIC_CHECK);
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