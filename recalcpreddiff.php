<html><body>

<?php
//
// Manual recalc predicted diff
//

require('config.inc.php');

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

// initial diff for block at $starting or block 1
$diffI = process_int(curl_request($url_remote,'["block", ' . max($starting,1) . ']'),6);
$diffT = (float)bin_to_int_diff($diffI) / (10 ** 12); // conversion from bin diff to Terahashes

for($i=0;$i<$height;$i+=$interval) {
	// commit c1b57c11853c42c337cc7c1cc5681646d9d7e5da: now we retarget twice as often. This is a hard update for height 27000 
	if($i % 1000 == 0)
	{
		$diffI = process_int(curl_request($url_remote,'["block", ' . ($i+1) . ']'),6);
		$diffT = (float)bin_to_int_diff($diffI) / (10 ** 12); // conversion from bin diff to Terahashes
	}
	// Predicted difficulty
	$estDiff = $diffT; // base case - estimated diff = current diff
	if ($i <= 26900) {
		$firstBlock = intdiv($i,2000)*2000; // Find first block of current diff
	}
	elseif ($i > 26900) {
		$firstBlock = intdiv($i,1000)*1000; // Find first block of current diff (retarget twice as often)
	}	
	$measure1 = $firstBlock+500;
	$measure2 = $firstBlock+1500;
	// commit c1b57c11853c42c337cc7c1cc5681646d9d7e5da: now we retarget twice as often. This is a hard update for height 27000 
	if ($i <= 26900) 
		{
		if ($i < $measure1) {
		// For blocks 1-480 in each interval, do estimate as originally
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
		}
		elseif ($i >= $measure1 && $i <= $measure2) {
		// For blocks 500-1500, reset from block 500 and do median
			// Get SQL
			$sql = "SELECT blocktime FROM `hashrate` WHERE block <= " . $i . " AND block >= " . $measure1;
			$blocktimeArray = [];
			$result = $conn->query($sql);
			if ($result->num_rows > 0) {
				while($row = $result->fetch_assoc()) {
					array_push($blocktimeArray,$row["blocktime"]);
				}
			}
			$medBlockTime = calculateMedian($blocktimeArray);
			$estDiff = 600/$medBlockTime * $diffT;
		}
		else {
			// For blocks 1520-2000, read predicted hash value from block 1500
			$sql = "SELECT hashpredict FROM `hashrate` WHERE block = " . $measure2;
			$result = $conn->query($sql);
			if ($result->num_rows > 0) {
				while($row = $result->fetch_assoc()) {
					$estDiff = $row["hashpredict"];
				}
			}
		}
	}
	elseif ($i > 26900) { // after the fork, we simple take median of 1000 block period
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
	}
	// commit 4462135a40de9cdc2db4d35974666f8597056a20: limit how quickly difficulty can decrease by 
	$estDiff = max($estDiff,6*$diffT/7);
	array_push($diffPredict,$estDiff);
	print($i . "," . $estDiff . "<br>\n");
	
	$sql = "UPDATE hashrate SET hashpredict=" . $estDiff . " WHERE block=" . $i;

	if ($conn->query($sql) === FALSE) {
		echo "Error: " . $sql . "<br>" . $conn->error;
		break;
	}
	
}

?>
</body>