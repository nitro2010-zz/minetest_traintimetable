<?php if(!defined('PHP_IS_INTERNAL_SCRIPT')) exit(); ?>
<form enctype="application/x-www-form-urlencoded" method="GET" target="_self">
	<table style="width:50%;">
		<tr>
			<td style="width:30%;"><b>Start station: </b></td>
			<td>
				<div class="ui-widget">
					<select id="combobox_start" name="start">
					</select>
				</div>
			</td>
		</tr>
		<tr>
			<td><b>End station: </b></td>
			<td>
				<div class="ui-widget">
					<select id="combobox_end" name="end"></select>
				</div>
			</td>
		</tr>
		<tr>
			<td><b>Search type: </b></td>
			<td>
				<input type="radio" name="searchtype" value="0" checked id="radio-1">
				<label for="radio-1">normal</label>
				<input type="radio" name="searchtype" value="1" id="radio-2">
				<label for="radio-2">the shortest route</label>
				<input type="radio" name="searchtype" value="2" id="radio-3">
				<label for="radio-3">the shortest time</label>
			</td>
		</tr>
		<tr><td>&nbsp;</td></tr>
		<tr>
			<td colspan="2" style="padding-left:200px;">
				<button class="ui-button ui-widget ui-corner-all" id="buttonsearch">Search</button>
			</td>
		</tr>
	</table>	
</form>	
<br/><br/>			
<?php if($nofound): ?>
<h2 style="text-align:center;">--- no route found ---</h2>
<?php
else:
	foreach($foundLines as $line):
	$citiesWithoutBeginAndEnd = count($line['cities']) - 2;
	$cities = $line['cities'];
	$via = array();
	for($x = 0; $x < count($cities); $x++):
		if($x == 0):
			continue;
		endif;
		if(isset($cities[$x]['line_from'])):
			$via[] = $cities[$x]['name'];
		endif;
	endfor;
	?>
	<table class="blueTable" id="tableroute">
		<thead>
			<tr>
				<th colspan="7">
					<?php echo $cities[0]['name'] . ' &#x279D; ' . $cities[count($cities) - 1]['name'] . ", via: " . ((count($via) == 0) ? '---': implode(', ', $via)) . ", distance: " . meters2km($line['distance']).', travel time: ' . seconds2hms($line['time']); ?>
				</th>
			</tr>
			<tr></tr>
			<tr>
				<th></th>
				<th></th>
				<th style="width:10%;text-align:center;">Station</th>
				<th></th>
				<th>Train</th>
				<th>Distance</th>
				<th>Time</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td rowspan="<?php echo count($line['cities']);?>" style="width:3%;" style="display: inline-block; vertical-align: top;">
					<div class="circle circle-begin">&nbsp;</div>
					<?php for($k = 0; $k < $citiesWithoutBeginAndEnd; $k++): ?>
						<div class="circle">&nbsp;</div>
					<?php endfor; ?>
					<div class="circle circle-end">&nbsp;</div>		
			</td>
				<td style="width:2%;text-align:center;"><img src="image/getin.png"/></td>
				<td><?php echo $cities[0]['name'];?></td>
				<td>catch the train</td>
				<td style="text-align:center;">
					<b>LINE:</b> <?php echo $cities[0]['line'];?>
					</br>
					(route: <?php echo $cities[0]['line_station_from']. ' &#x279D; ' . $cities[0]['line_station_to']; ?>)
				</td>
				<td style="text-align:center;">
					<?php  echo meters2km($cities[0]['distance']); ?>
				</td>
				<td style="text-align:center;">
					<?php  echo seconds2hms($cities[0]['time']); ?>
				</td>
			</tr>
			<?php
			for($k = 1; $k <= $citiesWithoutBeginAndEnd; $k++):
				if(isset($cities[$k]['line_station_from'])):
			?>
			<tr>
				<td><img src="image/transfer.png"/></td>
				<td><?php echo $cities[$k]['name'];?></td>
				<td>get off the train and catch the next train</td>
				<td style="text-align:center;">
				<b>LINE:</b> <?php echo $cities[$k]['line'];?>
				<br/>(route: <?php echo $cities[$k]['line_station_from']. ' &#x279D ' . $cities[$k]['line_station_to']; ?>)
				</td>
				<td style="text-align:center;width:8%;"><?php  echo meters2km($cities[$k]['distance']); ?></td>
				<td style="text-align:center;">
					<?php  echo seconds2hms($cities[$k]['time']); ?>
				</td>
			</tr>
			<?php
				else:
			?>
			<tr>
				<td><img src="image/station.png"/></td>
				<td><?php echo $cities[$k]['name'];?></td>
				<td style="text-align:center;">&#x21a1;</td>
				<td style="text-align:center;">&#x21a1;</td>
				<td style="text-align:center;">&#x21a1;</td>
				<td style="text-align:center;">&#x21a1;</td>
			</tr>
			<?php
				endif;
			endfor;
			?>		
			<tr>
				<td><img src="image/exit.png"/></td>
				<td><?php echo $cities[count($cities) - 1]['name'];?></td>
				<td> Get off at the station</td>
				<td style="text-align:center;">&#8677;</td>
				<td style="text-align:center;">&#8677;</td>
				<td style="text-align:center;">&#8677;</td>
			</tr>
		</tbody>
	</table>
	<br/>
	<hr size="1" />
	<br/>
<?php
	endforeach;
endif;
?>	