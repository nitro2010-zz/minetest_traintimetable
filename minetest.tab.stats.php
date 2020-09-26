<?php if(!defined('PHP_IS_INTERNAL_SCRIPT')) exit();

$Listtrains = array();
try{
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, preg_replace('#[//]{2,}#', '/', MINETEST_MAPSERVER . '/api/stats'));
	curl_setopt($curl, CURLOPT_VERBOSE, false);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($curl, CURLOPT_ENCODING, true);	
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($curl, CURLOPT_TIMEOUT, 30);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
	$return = curl_exec($curl);
}catch(Exception $e){}
if(!empty($return) || $return != "EOF"):
	$data = json_decode($return);	
	foreach($data->trains as $train):
		$line = (!empty($train->line)) ? $train->line : '---';
		if(!isset($Listtrains[$line])):
			$Listtrains[$line] = array();
		endif;
		if(!isset($Listtrains[$line]['total'])):
			$Listtrains[$line]['total'] = 0;
		endif;
		$Listtrains[$line]['total'] = $Listtrains[$line]['total'] + 1;
	endforeach;			
endif;
?>
<table class="blueTable" style="width:50%;">
	<thead>
		<tr>
			<th>Train</th>
			<th style="width:10%;">#Trains</th>
		</tr>
	</thead>
	<tbody>
		<?php
		$keys = array_keys($Listtrains);
		sort($keys, SORT_NATURAL);
		$total = 0;
		for($i = 0; $i < count($keys); $i++):
			$total += $Listtrains[$keys[$i]]['total'];
		?>
			<tr>
				<td><?php echo $keys[$i]; ?></td>
				<td><?php echo $Listtrains[$keys[$i]]['total']; ?></td>
			</tr>
		<?php endfor; ?>	
			<tr>
				<td style="text-align:center;font-weight:bolder;">TOTAL</td>
				<td><?php echo $total; ?></td>
			</tr>
			<tr>
				<td style="text-align:center;font-weight:bolder;">TOTAL CITIES</td>
				<td><?php echo 	file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cities_num.txt'); ?></td>
			</tr>
	</tbody>
</table>