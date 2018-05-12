<html><body>

<?php
//
// A very simple PHP example that sends a HTTP POST to a remote site
//

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

function process_nethash($interval,$starting)
{
	$request = curl_request("http://localhost:8081",'["mining_data", ' . $interval . ',' . $starting . ']');
	$str = substr($request,10,-2);
	$array = array_map('intval', explode(",",$str));
	return $array;
}

function bin_to_int_diff($d) {
	$a = (int) ($d / 256);
	$b = $d % 256;
	return 2 ** $a * (256 + $b) / 256;
}

function calculateMedian($aValues) {
    $aToCareAbout = array();
    foreach ($aValues as $mValue) {
        if ($mValue >= 0) {
            $aToCareAbout[] = $mValue;
        }
    }
    $iCount = count($aToCareAbout);
    if ($iCount == 0) return 0;

    // if we're down here it must mean $aToCareAbout
    // has at least 1 item in the array.
    $middle_index = floor($iCount / 2);
    sort($aToCareAbout, SORT_NUMERIC);
    $median = $aToCareAbout[$middle_index]; // assume an odd # of items

    // Handle the even case by averaging the middle 2 items
    if ($iCount % 2 == 0)
        $median = ($median + $aToCareAbout[$middle_index - 1]) / 2;

    return $median;
}

$sql = "SELECT max(block) FROM hashrate";
$result = $conn->query($sql);
$maxblock = 40;

if ($result->num_rows > 0) {
	while($row = $result->fetch_assoc()) {
		if (!is_null($row["max(block)"])) {
			$maxblock = $row["max(block)"];
		}
		else { //null, insert first 2 rows
			$sql = "INSERT INTO `hashrate` (`block`, `difficulty`, `nethash`, `blocktime`, `hashpredict`) VALUES
			(0, '0.027', 1, 27, '0.591'),
			(20, '0.027', 1, 27, '0.591');";
		}
	}
}

$starting = $maxblock;

$url_local = "http://localhost:8081";
$url_remote = "http://localhost:8080";
$interval = 20;

$blockNum = [];
$diff = [];
$diffPredict = [];
$nethash = [];
$period = [];

$diffI = 0;
$diffT = 0;

$height = process_int(curl_request($url_remote,'["height"]'));
//$height = 2040;

if ($height - $starting < $interval) {
	$starting -= $interval; // make sure there is something to process if current block data incomplete
}	

$nethash = array_merge(process_nethash($interval,$starting)); 
//print_r(array_values($nethash));
// starting from block 40
// assume first 40 blocks have nethash of 1 (which is true)

// initial diff for block at $starting or block 1
$diffI = process_int(curl_request($url_remote,'["block", ' . max($starting,1) . ']'),6);
$diffT = (float)bin_to_int_diff($diffI) / (10 ** 12); // conversion from bin diff to Terahashes

for($i=$starting;$i<$height;$i+=$interval) {
	if($i % 2000 == 0)
	{
		$diffI = process_int(curl_request($url_remote,'["block", ' . ($i+1) . ']'),6);
		$diffT = (float)bin_to_int_diff($diffI) / (10 ** 12); // conversion from bin diff to Terahashes
	}
	if ($i < 40) // manually set first 2 block intervals
	{
		$periodT = $diffT * 1000 / 1; // nethash of 1 gh/s
		array_push($period,$periodT);
	}
	else {
		if(isset($nethash[($i-$starting)/$interval])) {
			if($nethash[($i-$starting)/$interval] == 0) { // hacky
				$periodT = $diffT * 1000 / 1;
			}
			else {
				$periodT = $diffT * 1000 / $nethash[($i-$starting)/$interval];
			}
			array_push($period,$periodT);
		}	
	}
	array_push($blockNum,$i);
	array_push($diff,$diffT);
	
	if (is_null($nethash[($i-$starting)/$interval])) {
		$nh = 1;
	}
	else {
		$nh = max(1,$nethash[($i-$starting)/$interval]);
	}
	
	$sql = "REPLACE INTO hashrate (block, difficulty, nethash, blocktime)
	VALUES (". $i .", ". $diffT .", ". $nh .", ". $periodT .")";
	if ($conn->query($sql) === FALSE) {
		echo "Error: " . $sql . "<br>" . $conn->error;
		break;
	}
	
	// Predicted difficulty
	$estDiff = $diffT; // base case - estimated diff = current diff
	$firstBlock = intdiv($i,2000)*2000; // Find first block of current diff
	// Get SQL
	$sql = "SELECT blocktime FROM `hashrate` WHERE block <= " . $i . " AND block >= " . $firstBlock;
	$blocktimeArray = [];
	$result = $conn->query($sql);
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			array_push($blocktimeArray,$row["blocktime"]);
		}
	}
	$medBlockTime = calculateMedian($blocktimeArray);
	$estDiff = 600/$medBlockTime * $diffT;
	
	array_push($diffPredict,$estDiff);
	
	$sql = "UPDATE hashrate SET hashpredict=" . $estDiff . " WHERE block=" . $i;

	if ($conn->query($sql) === FALSE) {
		echo "Error: " . $sql . "<br>" . $conn->error;
		break;
	}
	
}


print("<Table><tr><th>Block</th><th>Difficulty (TH/b)</th><th>Nethash (GH/s)</th><th>Blocktime</th><th>Predicted Diff</th></tr>");
for($i=0;$i<count($blockNum);$i++) {
	print("<tr><td>" . $blockNum[$i] . "</td><td>" . round((float)$diff[$i],3) . "</td><td>" . $nethash[$i] . "</td><td>" . round((float)$period[$i],0) . "</td><td>" . round((float)$diffPredict[$i],3) ."</td></tr>");
}
print("</table>");

print("Pushed to SQL");

?>
</body>