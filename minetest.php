<?php
//CONFIGURATION
define('MINETEST_ADVTRAINS_AVG_SPEED', 20);
define('MINETEST_MAPSERVER', 'https://URL_TO_MAPSERVER');
//SUPPORT DATABASES
//MYSQL [pdo_mysql] - mysql:host=SERVER;port=PORT;dbname=DB_NAME
//SQLITE [pdo_sqlite] - sqlite:PATH_TO_FILE.sqlite3
//POSTGRESQL [pdo_pgsql] - pgsql:host=SERVER;port=PORT;dbname=DB_NAME
define('DB_CONNECTION' , 'sqlite:minetest_timetable.sqlite3');
define('DB_LOGIN' , 'root');
define('DB_PASSWORD' , '12345');
////////////////////////////////////////////
error_reporting(0);
set_time_limit(60);
ini_set('memory_limit', '512M');
////////////////////////////////////////////
define('PHP_IS_INTERNAL_SCRIPT', 1);
///////////////
//WHEN PHP CALL ERROR [TIMEOUT] - EXECUTE CODE
function shutdown()
{
	$a = error_get_last();
	if ($a != null):
		if(preg_match('/maximum execution time of [0-9]+ seconds exceeded/i', $a['message'])):
?>

<!doctype html>
<html lang="en">
	<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>MINETEST RAILWAY CONNECTIONS SEARCH ENGINE</title>
	<link rel="stylesheet" href="css/jquery-ui.min.css">
	<script src="js/jquery.js"></script>
	<script src="js/jquery-ui.min.js"></script>
	<script src="js/jquery.simplePagination.js"></script>
	<script src="js/selectize.js"></script>
	<link href="css/railway.css" rel="stylesheet">
	<link rel="stylesheet" type="text/css" href="css/selectize.bootstrap3.css" />	
</head>
<body>
	<div id="tabs">
		<ul>
		<li><a href="#tabs-search">SEARCH</a></li>
		<li><a href="#tabs-map">MAP</a></li>
		<li><a href="#tabs-stats">STATS</a></li>
		</ul>
		<div id="tabs-search">
		<?php
			$nofound = 1;
			@require_once('minetest.tab.search.php');
		?>
		</div>
		<div id="tabs-map">
		<iframe src="<?php echo MINETEST_MAPSERVER; ?>" width="100%" height="600" allow="fullscreen" style="border:none"></iframe>
		</div>
		<div id="tabs-stats">	
			<?php @require_once('minetest.tab.stats.php'); ?>
		</div>
	</div>
	</body>
</html>
<script src="js/railway.js"></script>	
<?php
		endif;
	endif;
}
register_shutdown_function('shutdown');
////////////////////////////////////////////
//LOAD DEPENDENCIES
require_once('vendor/autoload.php');

use GraphDS\Graph\UndirectedGraph;
use GraphDS\Algo\DijkstraMulti;
use GraphDS\Algo\FloydWarshall;

//PROCESSING FROM COMMANDLINE
$getopt = new \GetOpt\GetOpt([
	\GetOpt\Option::create('a', 'action', \GetOpt\GetOpt::REQUIRED_ARGUMENT)
		->setDefaultValue(''),
	\GetOpt\Option::create('o', 'overwrite', \GetOpt\GetOpt::NO_ARGUMENT)
		->setDefaultValue(false),
]);
$getopt->process();

//CALCULATE DISTANCE BETWEEN POINTS
function distancePoints($a = array(), $b = array())
{
	if($a == null or $b == null):
		return 0;
	endif;
	if(count($a) == 0 or count($b) == 0):
		return 0;
	endif;

	$calc = sqrt(abs(pow(($b[0] - $a[0]), 2) + pow(($b[1] - $a[1]), 2) + pow(($b[2] - $a[2]), 2)));
	$calc = ceil($calc);
	
	return $calc;
}

//SORT DISTANCE ASC
function cmp($a, $b) {
	if ($a['distance'] == $b['distance']) {
		return 0;
	}
	return ($a['distance'] < $b['distance']) ? -1 : 1;
}

//CONVERT METERS TO METERS OR KILOMETERS
function meters2km($meters){
	if($meters >= 1000):
		return sprintf('%.2f km', $meters/1000);
	else:
		return sprintf('%s m', $meters);
	endif;
}

//CONVERT SECONDS TO FORMAT: HOURS:MINUTES:SECONDS
function seconds2hms($seconds) {
  $t = round($seconds);
  return sprintf('%02d:%02d:%02d', ($t/3600),($t/60%60), $t%60);
}

//IF USER CALL ACTION MAPSERVER
if($getopt->getOption('action') == 'mapserver'):
	$overwrite = $getopt->getOption('overwrite');
	try{
		$pdo = new PDO(DB_CONNECTION, DB_LOGIN, DB_PASSWORD);	
		$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

		//OVERWRITE == FLUSH TABLES
		if($overwrite):
			$tables = array('paths', 'tlines');
			if($driver == 'sqlite'):
				foreach($tables as $table):
					echo "Table: ".$table." was cleared \n";
					$stmt = $pdo->prepare('DELETE FROM ' . $table);
					$stmt->execute(array());
				endforeach;		
			else:
				foreach($tables as $table):
					echo "Table: ".$table." was cleared \n";
					$stmt = $pdo->prepare('TRUNCATE ' . $table);
					$stmt->execute(array());
				endforeach;		
			endif;
		endif;
		
		//GET THE DATA FROM MAP SERVER
		$data = array(
			'pos1'=> array(
				'x' => -30927,
				'y' => -30927,
				'z' => -30927,
			),
			'pos2'=> array(
				'x' => 30927,
				'y' => 30927,
				'z' => 30927,
			),
			'type' => 'train',
		);
		$data = json_encode($data);
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, preg_replace('#[//]{2,}#', '/', MINETEST_MAPSERVER . '/api/mapobjects/'));		
		curl_setopt($curl, CURLOPT_VERBOSE, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		curl_setopt($curl, CURLOPT_ENCODING, true);	
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($curl, CURLOPT_TIMEOUT, 60);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		$return = curl_exec($curl);
		$ListCity = array();
		$ListTLine = array();
		$ListLine = array();
		$dataServer = json_decode($return);

		//if object is mapserver:train block, write station name, position: x, y, z to arrayList
		//write to other arrayList information about separate line - only indexes [indexes - sort stations for each line]	
		for($i = 0; $i < count($dataServer); $i++):
			if($dataServer[$i]->type != 'train'):
				continue;
			endif;

			if(!empty($dataServer[$i]->attributes->station)):
				$ListCity[$dataServer[$i]->attributes->station]['x'] = $dataServer[$i]->x;
				$ListCity[$dataServer[$i]->attributes->station]['y'] = $dataServer[$i]->y;
				$ListCity[$dataServer[$i]->attributes->station]['z'] = $dataServer[$i]->z;
			endif;

			if(!empty($dataServer[$i]->attributes->line) && !empty($dataServer[$i]->attributes->index) && !empty($dataServer[$i]->attributes->station)):
				$ListTLine[$dataServer[$i]->attributes->line][$dataServer[$i]->attributes->index][] = $i;
			endif;
		endfor;

		//sort train lines by their names
		$SortLineNumbers = array_keys($ListTLine);
		sort($SortLineNumbers);

		//LOOP SORT EARLIER TRAIN LINES
		for($i = 0; $i < count($SortLineNumbers); $i++):
			//store information about last city for specific line
			$lastCity = -1;
			$lineNumber = $SortLineNumbers[$i];
			//sort station indexes for specific line
			$SortLineIndexes = array_keys($ListTLine[$lineNumber]);
			sort($SortLineIndexes);
			for($j = 0; $j < count($SortLineIndexes); $j++):
				//get station indexes for specific line
				$indexes = $ListTLine[$lineNumber][$SortLineIndexes[$j]];
				//if it has one station in line, save it to arrayList and save to LastCity
				if(count($indexes) == 1):
					$ListLine[$lineNumber]['city'][] = $dataServer[$ListTLine[$lineNumber][$SortLineIndexes[$j]][0]]->attributes->station;
					$lastCity = $ListTLine[$lineNumber][$SortLineIndexes[$j]][0];
				else:
					//when are cities with the same index - calculate distance between for each and last city - it choose city which is the closest to last city, if last city is not set, save first city from line
					if($lastCity == -1):
						for($k = 0; $k < count($indexes); $k++):
							$ListLine[$lineNumber]['city'][] = $dataServer[$indexes[$k]]->attributes->station;
							$lastCity = $indexes[$k];
						endfor;		
					else:
						$ListTempCity = array();
						for($k = 0; $k < count($indexes); $k++):
							$ListTempCity[] = array(
								'cityIndex' => $indexes[$k],
								'distance' => distancePoints(array($dataServer[$lastCity]->x, $dataServer[$lastCity]->y, $dataServer[$lastCity]->z), array($dataServer[$indexes[$k]]->x, $dataServer[$indexes[$k]]->y, $dataServer[$indexes[$k]]->z)),
							);			
						endfor;	
						//sort cities and their indexes by distance, later save cities from the closest
						usort($ListTempCity,"cmp");
						foreach($ListTempCity as $city):
							$ListLine[$lineNumber]['city'][] = $dataServer[$city['cityIndex']]->attributes->station;
							$lastCity = $city['cityIndex'];
						endforeach;
					endif;
				endif;
			endfor;
		endfor;

		//process train lines and their the data
		foreach($ListLine as $number => $data):
			$NumCities = count($data['city']);
			for($i = 0; $i < $NumCities - 1; $i++):
				//calculate between cities and save it
				$distance = distancePoints(array($ListCity[$data['city'][$i]]['x'], $ListCity[$data['city'][$i]]['y'], $ListCity[$data['city'][$i]]['z']), array($ListCity[$data['city'][$i + 1]]['x'], $ListCity[$data['city'][$i + 1]]['y'], $ListCity[$data['city'][$i + 1]]['z']));
				$distance = number_format($distance, 0, '.', '');
				//calculate travel time between cities
				$time = ceil($distance / MINETEST_ADVTRAINS_AVG_SPEED);
				$ListLine[$number]['distance'][] = $distance;
				$ListLine[$number]['time'][] = $time;
			endfor;
		endforeach;		
			
		//NEXT, GIVE MAP OF CONNECTIONS BETWEEN CITIES BASED ON TRAIN LINES
		$g = new UndirectedGraph();
		//ADD CITIES
		foreach($ListLine as $number => $data):
			$NumCities = count($data['city']);
			for($i = 0; $i < $NumCities; $i++):
				$g->addVertex($data['city'][$i]);
			endfor;
		endforeach;

		//ADD CONNECTION BETWEEN CITIES
		foreach($ListLine as $number => $data):
			$NumCities = count($data['city']);
			for($i = 0; $i < $NumCities - 1; $i++):
				$g->addEdge($data['city'][$i], $data['city'][$i + 1], 1);
			endfor;
		endforeach;

		//ADD CONNECTIONS BETWEEN CITIES TO DATABASE
		foreach($ListCity as $name => $data):
			foreach($g->vertices[$name]->getNeighbors() as $neighboor):	
				$stmt = $pdo->prepare('SELECT count(*) FROM paths WHERE (from_station=:from_station AND to_station=:to_station) OR (from_station=:to_station AND to_station=:from_station)');
				$stmt->execute(array(
					':from_station' => $name,
					':to_station' => $neighboor,
				));
				$nRows = $stmt->fetchColumn();
				if($nRows > 0):
					echo "Route ".implode(' - ', array($name, $neighboor)) ." already exists in database \n";
					continue;
				endif;	
				$stmt = $pdo->prepare('INSERT INTO `paths` (`from_station`, `to_station`) VALUES (:from_station, :to_station)');
				$stmt->execute(array(
					':from_station' => $name,
					':to_station' => $neighboor,
				));
				echo "Route: ".implode(' - ', array($name, $neighboor)) ." was added to database \n";
			endforeach;
		endforeach;

		//ADD TRAIN LINES TO DATABASE
		foreach($ListLine as $name => $data):
			for($i = 0; $i < count($data['city']) - 1; $i++):
				$stmt = $pdo->prepare('SELECT count(*) FROM tlines WHERE ((station_from=:station_from AND station_to=:station_to) OR (station_from=:station_to AND station_to=:station_from)) AND line=:line');
				$stmt->execute(array(
					':station_from' => $data['city'][$i],
					':station_to' => $data['city'][$i + 1],
					':line' => $name,
				));
				$nRows = $stmt->fetchColumn();
				if($nRows > 0):
					echo "Train line " . $name . " (". $data['city'][$i] .") - ". $data['city'][$i + 1] ." already exists in database \n";
					continue;
				endif;

				$stmt = $pdo->prepare('INSERT INTO `tlines` (`station_from`, `station_to`, `line`, `num`, `distance`,`time`) VALUES (:station_from, :station_to, :line, :num, :distance, :time)');
				$stmt->execute(array(
					':station_from' => $data['city'][$i],
					':station_to' => $data['city'][$i + 1],
					':line' => $name,
					':num' => $i,
					':distance' => $data['distance'][$i],
					':time' => $data['time'][$i],
				));

				echo "Train line: " . $name . " (". $data['city'][$i] .") - ". $data['city'][$i + 1] ." was added to database \n";
			endfor;
		endforeach;
	}catch(Exception $each){
		echo $e->getMessage() . "\n";
	}
	exit();
endif;

//CREATE TABLES IN DATABASE
if($getopt->getOption('action') == 'createtables'):
	try{
		$pdo = new PDO(DB_CONNECTION, DB_LOGIN, DB_PASSWORD);	
		$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
		switch($driver):
			case 'mysql':
				$stmt = $pdo->prepare('CREATE TABLE IF NOT EXISTS `paths` (
				  `id` bigint(20) NOT NULL AUTO_INCREMENT,
				  `from_station` text NOT NULL,
				  `to_station` text NOT NULL,
				  PRIMARY KEY (`id`),
				  KEY `from_station` (`from_station`(768))
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
				$stmt->execute(array());
				$stmt = $pdo->prepare('CREATE TABLE IF NOT EXISTS `tlines` (
				  `id` bigint(20) NOT NULL AUTO_INCREMENT,
				  `station_from` text NOT NULL,
				  `station_to` text NOT NULL,
				  `line` text NOT NULL,
				  `num` int(11) NOT NULL,
				  `distance` int(11) NOT NULL,
				  `time` int(11) NOT NULL,
				  PRIMARY KEY (`id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
				$stmt->execute(array());
			break;
			case 'sqlite':
				$stmt = $pdo->prepare('CREATE TABLE "paths" (
		"id"	INTEGER,
		"from_station"	TEXT,
		"to_station"	TEXT,
		PRIMARY KEY("id" AUTOINCREMENT)
	)');
				$stmt->execute(array());
				$stmt = $pdo->prepare('CREATE TABLE "tlines" (
		"id"	INTEGER,
		"station_from"	TEXT,
		"station_to"	TEXT,
		"line"	TEXT,
		"num"	INTEGER,
		"distance"	INTEGER,
		"time"	TEXT,
		PRIMARY KEY("id" AUTOINCREMENT)
	)');
				$stmt->execute(array());
			break;
			case 'pgsql':
				$stmt = $pdo->prepare('CREATE TABLE paths (
		id serial NOT NULL,
		from_station text NOT NULL,
		to_station text NOT NULL,
		CONSTRAINT paths_pk PRIMARY KEY (id)
	);');
				$stmt->execute(array());
				$stmt = $pdo->prepare('CREATE TABLE public.tlines (
		id serial NOT NULL,
		station_from text NOT NULL,
		station_to text NOT NULL,
		line text NOT NULL,
		num int4 NOT NULL,
		distance int4 NOT NULL,
		"time" int4 NOT NULL,
		CONSTRAINT tlines_pk PRIMARY KEY (id)
	);');
				$stmt->execute(array());
			break;
		endswitch;

		echo "Tables were created. \n";
		
	}
	catch(Exception $e)
	{
	  echo $e->getMessage();	
	}
	exit();
endif;


$action = (empty($_GET['action'])) ? '': $_GET['action'];
//GET STATIONS
if($action == "getstations"):
	$pdo = new PDO(DB_CONNECTION, DB_LOGIN, DB_PASSWORD);
	$tcities = array();
	$cities = array();
	//SELECT ALL PATHS BETWEEN CITIES
	$stmt = $pdo->prepare('SELECT * FROM paths');
	$stmt->execute(array());
	$dataLines = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach($dataLines as $station):
		if(!in_array($station['from_station'], $tcities)):
			$tcities[] = $station['from_station'];
		endif;
		if(!in_array($station['to_station'], $tcities)):
			$tcities[] = $station['to_station'];
		endif;
	endforeach;
	sort($tcities);
	@mkdir(__DIR__ . DIRECTORY_SEPARATOR . 'data');
	file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cities_num.txt', count($tcities));
	echo json_encode($tcities);
	exit();
endif;

$pdo = new PDO(DB_CONNECTION, DB_LOGIN, DB_PASSWORD);
$foundLines = array();
$startStation = (empty($_GET['start'])) ? '' : urldecode($_GET['start']);
$endStation = (empty($_GET['end'])) ? '' : urldecode($_GET['end']);
$searchtype = (empty($_GET['searchtype'])) ?  0 : $_GET['searchtype'];
$cities = array();
$nofound = 0;

//process if start station and end station is not empty
if(!empty($startStation) && !empty($endStation)  && !$nofound):
	$g = new UndirectedGraph();

	//SELECT ALL PATHS BETWEEN CITIES
	$stmt = $pdo->prepare('SELECT * FROM paths');
	$stmt->execute(array());
	$dataLines = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if(count($dataLines) == 0):
		echo 'No data found - add the data from mapserver';
		exit();
	endif;

	foreach($dataLines as $station):
	//ADD STATIONS AS VERTEX
		$g->addVertex($station['from_station']);
		$g->addVertex($station['to_station']);
		if(!in_array($station['from_station'], $cities)):
			$cities[] = $station['from_station'];
		endif;
		if(!in_array($station['to_station'], $cities)):
			$cities[] = $station['to_station'];
		endif;
	endforeach;
	sort($cities);

	//depends on search type
	foreach($dataLines as $station):
		switch($searchtype):
		case 2:
			$stmt = $pdo->prepare('SELECT distance FROM tlines WHERE (station_from=:station_from AND station_to=:station_to) OR (station_from=:station_to AND station_to=:station_from) ORDER BY distance ASC LIMIT 1');
			$stmt->execute(array(
				':station_from' => $station['from_station'],
				':station_to' => $station['to_station'],
			));
			$data = $stmt->fetch(PDO::FETCH_ASSOC);	
			$g->addEdge($station['from_station'], $station['to_station'], $data['distance']);	
		break;
		case 3:
			$stmt = $pdo->prepare('SELECT time FROM tlines WHERE (station_from=:station_from AND station_to=:station_to) OR (station_from=:station_to AND station_to=:station_from) ORDER BY time ASC LIMIT 1');
			$stmt->execute(array(
				':station_from' => $station['from_station'],
				':station_to' => $station['to_station'],
			));
			$data = $stmt->fetch(PDO::FETCH_ASSOC);	
			$g->addEdge($station['from_station'], $station['to_station'], $data['time']);	
		break;
		default:
			$g->addEdge($station['from_station'], $station['to_station'], 1);	
		break;
		endswitch;
	endforeach;
	
	$fw = new FloydWarshall($g);
	$fw->run();
	$fw_res = $fw->get($startStation, $endStation);	
	if($fw_res == null):
		$nofound = 1;
	endif;

	if($nofound == 0):
		//SEARCH ALL POSSIBLE SHORT PATHS BETWEEN CITIES
		$dijkstra_mult = new DijkstraMulti($g);
		$dijkstra_mult->run($startStation);
		$results = $dijkstra_mult->get($endStation);

		//NO FOUND RESULTS
		if(count($results['paths']) == 0):
			$nofound = 1;
		endif;

		//NO FOUND RESULTS
		if(count($results['paths']) == 1):
			if(count($results['paths'][0]) == 1):
				$nofound = 1;
			endif;
		endif;
		//PROCESSING IN PATHS
		for($i = 0; $i < count($results['paths']); $i++):
			$paths = $results['paths'][$i];
			$tempLine = array();
			//SUM DISTANCE
			$distance = 0;
			//SUM TIME
			$time = 0;
			//LAST TRAIN CODE
			$trainCode = '';
			$tempLine['cities'] = array();
			for($j = 0; $j < count($paths) - 1; $j++):
				$tempLine['cities'][]['name'] = $paths[$j];
				//WHICH TRAIN SERVE ROUTE BETWEEN SEARCHING CITIES
				$stmt = $pdo->prepare('SELECT * FROM tlines WHERE ((station_from=:station_from AND station_to=:station_to) OR (station_from=:station_to AND station_to=:station_from))');
				$stmt->execute(array(
					':station_from' => $paths[$j],
					':station_to' => $paths[$j+1],
				));
				$data = $stmt->fetch(PDO::FETCH_ASSOC);
				$distance += $data['distance'];
				$time += $data['time'];

				if($trainCode == ''):
					//SAVE TRAIN LINE NAME
					$tempLine['cities'][count($tempLine['cities']) - 1]['line'] = $data['line'];
			
					$tempLine['cities'][count($tempLine['cities']) - 1]['distance'] = $data['distance'];
					$tempLine['cities'][count($tempLine['cities']) - 1]['time'] = $data['time'];
					//SAVE TRAIN CODE TO CHECK LATER
					$trainCode = $data['line'];

					//GET FIRST AND LAST STATION OF SPECIFIC TRAIN
					$stmt2 = $pdo->prepare('SELECT station_from FROM tlines WHERE line=:line ORDER BY num ASC LIMIT 1');
					$stmt2->execute(array(
						':line' => $trainCode,
					));
					$data2 = $stmt2->fetch(PDO::FETCH_ASSOC);
					//SAVE FIRST STATION OF SPECIFIC TRAIN
					$tempLine['cities'][count($tempLine['cities']) - 1]['line_station_from'] = $data2['station_from'];
					$stmt2 = $pdo->prepare('SELECT station_to FROM tlines WHERE line=:line ORDER BY num DESC LIMIT 1');
					$stmt2->execute(array(
						':line' => $trainCode,
					));
					$data2 = $stmt2->fetch(PDO::FETCH_ASSOC);
					//SAVE LAST STATION OF SPECIFIC TRAIN
					$tempLine['cities'][count($tempLine['cities']) - 1]['line_station_to'] = $data2['station_to'];
					//-- GET FIRST AND LAST STATION OF SPECIFIC TRAIN
				elseif($trainCode != $data['line']):
					//SAVE TRAIN LINE NAME
					$tempLine['cities'][count($tempLine['cities']) - 1]['line'] = $data['line'];
					//SAVE TRAIN CODE TO CHECK LATER
					$trainCode = $data['line'];		
					
					$tempLine['cities'][count($tempLine['cities']) - 1]['distance'] = $data['distance'];
					$tempLine['cities'][count($tempLine['cities']) - 1]['time'] = $data['time'];
					
					//GET FIRST AND LAST STATION OF SPECIFIC TRAIN
					$stmt2 = $pdo->prepare('SELECT station_from FROM tlines WHERE line=:line ORDER BY num ASC LIMIT 1');
					$stmt2->execute(array(
						':line' => $trainCode,
					));
					$data2 = $stmt2->fetch(PDO::FETCH_ASSOC);
					//SAVE FIRST STATION OF SPECIFIC TRAIN
					$tempLine['cities'][count($tempLine['cities']) - 1]['line_station_from'] = $data2['station_from'];
					$stmt2 = $pdo->prepare('SELECT station_to FROM tlines WHERE line=:line ORDER BY num DESC LIMIT 1');
					$stmt2->execute(array(
						':line' => $trainCode,
					));
					$data2 = $stmt2->fetch(PDO::FETCH_ASSOC);
					//SAVE LAST STATION OF SPECIFIC TRAIN
					$tempLine['cities'][count($tempLine['cities']) - 1]['line_station_to'] = $data2['station_to'];
					//-- GET FIRST AND LAST STATION OF SPECIFIC TRAIN			
				else:
					//SAVE TRAIN LINE NAME
					$tempLine['cities'][count($tempLine['cities']) - 1]['line'] = $data['line'];
					//SAVE TRAIN CODE TO CHECK LATER
					$trainCode = $data['line'];		
					$tempLine['cities'][count($tempLine['cities']) - 1]['distance'] = $data['distance'];
					$tempLine['cities'][count($tempLine['cities']) - 1]['time'] = $data['time'];
				endif;
			endfor;
			
			//SUM DISTANCES - FROM ONE LINE TO OTHER LINE
			for($k = 0; $k < count($tempLine['cities']) - 1; $k++):
				$tdistance = 0;
				$ttime = 0;
				$line = $tempLine['cities'][$k]['line'];
				$origin = $k;
				$tdistance += $tempLine['cities'][$k]['distance'];
				$ttime += $tempLine['cities'][$k]['time'];
				
				for($l = $k + 1; $l < count($tempLine['cities']); $l++):
					if($line == $tempLine['cities'][$l]['line']):
						$tdistance += $tempLine['cities'][$l]['distance'];
						$ttime += $tempLine['cities'][$l]['time'];
					else:
						$k = $l;
						break;
					endif;
				endfor;
				$tempLine['cities'][$origin]['distance'] = $tdistance;
				$tempLine['cities'][$origin]['time'] = $ttime;
			endfor;	
			//SAVE DATA
			//SAVE DISTANCE BETWEEN CITIES
			$tempLine['distance'] = $distance;
			$tempLine['time'] = $time;
			$tempLine['cities'][]['name'] = $paths[count($paths) - 1];	
			$foundLines[] = $tempLine;
		endfor;
	endif;
endif;
?>
<!doctype html>
<html lang="en">
	<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>MINETEST RAILWAY CONNECTIONS SEARCH ENGINE</title>
	<link rel="stylesheet" href="css/jquery-ui.min.css">
	<script src="js/jquery.js"></script>
	<script src="js/jquery-ui.min.js"></script>
	<script src="js/jquery.simplePagination.js"></script>
	<script src="js/selectize.js"></script>
	<link href="css/railway.css" rel="stylesheet">
	<link rel="stylesheet" type="text/css" href="css/selectize.bootstrap3.css" />	
</head>
<body>
	<div id="tabs">
		<ul>
		<li><a href="#tabs-search">SEARCH</a></li>
		<li><a href="#tabs-map">MAP</a></li>
		<li><a href="#tabs-stats">STATS</a></li>
		</ul>
		<div id="tabs-search">
			<?php @require_once('minetest.tab.search.php'); ?>
		</div>
		<div id="tabs-map">
		<iframe src="<?php echo MINETEST_MAPSERVER; ?>" width="100%" height="600" allow="fullscreen" style="border:none"></iframe>
		</div>
		<div id="tabs-stats">
			<?php @require_once('minetest.tab.stats.php'); ?>
		</div>
	</div>
	</body>
</html>
<script src="js/railway.js"></script>	