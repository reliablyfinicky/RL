<?php
session_start();

	$fauxpost = json_decode(base64_decode($_GET['thread']),true);
	if (json_last_error() != 0) { echo '<a href="https://jsonformatter.org/">Link</a><br><br>' . base64_decode($_GET['thread']); die(); }
	$vars = array(
		'SFM_target' => 322,
		'zAdjust' => 0.2, // initial threading z start location
		'chamfer_height' => 0.025, // diameter
		'groove_width' => round(((1/($fauxpost['t']['t']*2))*1.025),4), // add 2 percent for clearance (0.125 --> 0.1275)
		'mid_thread_doc' => 0.0015, // radial DoC for the initial threading pass (full engagement)
		'supplied' => array(
			'machine' => ($fauxpost['r']['h'] == true) ? 'haas' : 'okuma',
			'max_z' => round($fauxpost['t']['z'],4),
			'taper_angle' => ($fauxpost['r']['tTS'] == true) ? 0 : round($fauxpost['t']['tA'],3),
			'major' => round($fauxpost['t']['j'],4),
			'minor' => round($fauxpost['t']['n'],4),
			'height' => round($fauxpost['t']['g'],4),
			'front_wall' => round($fauxpost['t']['f'],3),
			'front_wall_chmf' => round(($fauxpost['t']['f']+90)/2,3),
			'back_wall' => round($fauxpost['t']['b'],3),
			'back_wall_chmf' => round(($fauxpost['t']['b']+90)/2,3),
			'tpi' => round($fauxpost['t']['t'],3),
			'quality' => round($fauxpost['t']['q'],0),
			/*
				Quality is a scale from 0 to 10 and affects the # of passes of walls and chamfers (NOT MID!)
				Max quality = 0.003" DoC (dia)
				Min quality = 0.007" DoC (dia)
			*/
			'depth_of_cut' => round((35-(round($fauxpost['t']['q'],0)*2))/10000,4)
		),
		'pre' => array(
			0 => ($fauxpost['r']['h'] == true) ? 'N7' : 'NTHRD',
			1 => '(INSERT: %s")',
			2 => '(MAJOR: %s")',
			3 => '(MINOR: %s")',
			4 => '(TPI: %s)',
			5 => '(DEPTH: %s")',
			6 => '(FRONT WALL: %s°)',
			7 => '(BACK WALL: %s°)',
			8 => '(SURFACE: %s)',
			9 => '(TAPER: %s°)',
			10=> ($fauxpost['r']['h'] == true) ? 'G28' : 'G00 X30. Z20.',
			11=> 'T0909',
			12=> 'G97 S%s M03',
			13=> 'G00 Z1.',
			14=> 'G00 X%s',
			15=> 'G00 Z%s M08'
		),
		'post' => array(
			0 => 'G00 X%s Z1. M09',
			1 => ($fauxpost['r']['h'] == true) ? 'G28' : 'G00 X30. Z20.'
		),
		/*
			sample lines of finished output
			%s and %d are variables
			chr(10) is <enter> / new line
		*/
		'code' => array(
			'haas' => 'G00 Z%s (#%d/%d, %s)'.chr(10).'G92 X%s Z-%s F%s I%s'.chr(10), 
			'okuma' => 'G00 Z%s (#%d/%d, %s)'.chr(10).'G33 X%s Z-%s F1 J%s I%s'.chr(10)
		),
	);
	foreach(array('Z','0','1') as $what) {
		if ($fauxpost['r']['tM_'.$what] == true) {
			$vars['supplied']['taper_meets'] = $what;
		}
	}
	foreach(array('047','072','094') as $size) {
		if ($fauxpost['r']['i'.$size] == true) {
			$vars['supplied']['insert'] = $size/1000;
		}
	}
	$vars['spindle'] = round(($vars['SFM_target'] * 12 / pi()) / $vars['supplied']['major'],0);

	$vars['passes'] = array(
		'middle' => (integer) ceil($vars['supplied']['height']/$vars['mid_thread_doc']),
		'main_front' => (integer) ceil(($vars['supplied']['height']-($vars['chamfer_height']/2))/cos(deg2rad($vars['supplied']['front_wall']))/$vars['supplied']['depth_of_cut']), // hypoteneuse divided by DoC, for narrow angles will basically not change the number of passes, for wider angles add passes for better quality 
		'main_back' => (integer) ceil(($vars['supplied']['height']-($vars['chamfer_height']/2))/cos(deg2rad($vars['supplied']['back_wall']))/$vars['supplied']['depth_of_cut']),
		'chmf_front' => 7, // hard coded # of passes for the 2 chamfers
		'chmf_back' => 7
	);
	
	$vars['feedrate'] = ($vars['supplied']['machine'] == 'haas') ? round(1/$vars['supplied']['tpi'],3) : $vars['supplied']['tpi'];
	foreach($vars['passes'] as $k => $v) {
		$vars['PandT'][$k] = array(
			'p' => $v,
			't' => round(
				round(
					(
						(
							$vars['supplied']['max_z'] + $vars['zAdjust']
						)
						/
						(
							round(
								(
									$vars['SFM_target'] * 12
								) / (
									$vars['supplied']['major'] * pi()
								),
								0
							) * (
								1/$vars['supplied']['tpi']
							)
						)
					)*60,3
				) * $v * 1.375,
				1
			)
		);
	}
	$vars['PandT']['total'] = array(
		'p' => array_sum($vars['passes']),
		't' => round(round((($vars['supplied']['max_z']+$vars['zAdjust'])/(round(($vars['SFM_target']*12)/($vars['supplied']['major']*pi()),0)*(1/$vars['supplied']['tpi'])))*60,3)*array_sum($vars['passes'])*1.375,1)
	);
	
	$vars['angle_to_radial_diff'] = round((tan(deg2rad($vars['supplied']['taper_angle'])) * ($vars['supplied']['max_z']+$vars['zAdjust'])),4);

	/*
			these are the 8 points of a thread we need to know:
			A (minor dia / middle)
			B (major dia / middle) [0,0]
			C (major dia / end of front wall)
			D (major dia / end of back wall)
			E (meeting point of front chamfer and front wall)
			F (meeting point of back chamfer and back wall)
			G (minor dia / start of front chamfer)
			H (minor dia / start of back chamfer)
	*/
	
	/*
	($vars['height']-($vars['chamfer_height']/2))
	($vars['chamfer_height']/2)
	($vars['front_wall'])
	($vars['front_wall_chmf'])
	($vars['back_wall'])
	($vars['back_wall_chmf'])
	($vars['groove_width']/2)
	*/
	
	$threadPoints = array(
		'a' => array(
			'z' => 0,
			'x' => 0-$vars['supplied']['height']
		),
		'b' => array(
			'z' => 0,
			'x' => 0
		),
		'c' => array(
			'z' => 0-($vars['groove_width']/2)+($vars['supplied']['insert']/2),
			'x' => 0
		),
		'd' => array(
			'z' => ($vars['groove_width']/2)-($vars['supplied']['insert']/2),
			'x' => 0
		),
		'e' => array(
			'z' => 0-(($vars['groove_width']/2)+(tan(deg2rad($vars['supplied']['front_wall']))*($vars['supplied']['height']-($vars['chamfer_height']/2))))+($vars['supplied']['insert']/2),
			'x' => 0-($vars['supplied']['height']-($vars['chamfer_height']/2))
		),
		'f' => array(
			'z' => (($vars['groove_width']/2)+(tan(deg2rad($vars['supplied']['back_wall']))*($vars['supplied']['height']-($vars['chamfer_height']/2))))-($vars['supplied']['insert']/2),
			'x' => 0-($vars['supplied']['height']-($vars['chamfer_height']/2))
		),
		'g' => array(
			'z' => 0-(
				($vars['groove_width']/2)+
				(tan(deg2rad($vars['supplied']['front_wall']))*($vars['supplied']['height']-($vars['chamfer_height']/2)))+
				(tan(deg2rad($vars['supplied']['front_wall_chmf']))*($vars['chamfer_height']/2))
			)+($vars['supplied']['insert']/2),
			'x' => 0-($vars['supplied']['height'])
		),
		'h' => array(
			'z' => (
				($vars['groove_width']/2)+
				(tan(deg2rad($vars['supplied']['back_wall']))*($vars['supplied']['height']-($vars['chamfer_height']/2)))+
				(tan(deg2rad($vars['supplied']['back_wall_chmf']))*($vars['chamfer_height']/2))
			)-($vars['supplied']['insert']/2),
			'x' => 0-($vars['supplied']['height'])
		)
	);
	//var_dump($threadPoints); die();
	foreach($threadPoints as $ltr => $arr) { foreach($arr as $xorz => $val) { $threadPoints[$ltr][$xorz] = round($val,4); } }
	
	if ($fauxpost['r']['tTT'] == true) {
		//var_dump($fauxpost,$vars); die();
		switch($vars['supplied']['machine'].$vars['supplied']['taper_meets']) {
			case 'haasZ': $vars['xAdjust'] = 0; $vars['majorAt0'] = round($vars['supplied']['major'] + ((tan(deg2rad($vars['supplied']['taper_angle']))*2)*($vars['supplied']['max_z'])),4); break;
			case 'haas0': $vars['xAdjust'] = 0-((tan(deg2rad($vars['supplied']['taper_angle']))*2)*($vars['supplied']['max_z'])); $vars['majorAt0'] = round($vars['supplied']['major'],4); break;
			case 'haas1': $vars['xAdjust'] = 0-((tan(deg2rad($vars['supplied']['taper_angle']))*2)*($vars['zAdjust']+$vars['supplied']['max_z'])); $vars['majorAt0'] = round($vars['supplied']['major'] - ((tan(deg2rad($vars['supplied']['taper_angle']))*2)*($vars['zAdjust'])),4); break;
			case 'okumaZ': $vars['xAdjust'] = 0+((tan(deg2rad($vars['supplied']['taper_angle']))*2)*($vars['zAdjust']+$vars['supplied']['max_z'])); $vars['majorAt0'] = round($vars['supplied']['major'] + ((tan(deg2rad($vars['supplied']['taper_angle']))*2)*($vars['supplied']['max_z'])),4); break;
			case 'okuma0': $vars['xAdjust'] = 0+((tan(deg2rad($vars['supplied']['taper_angle']))*2)*($vars['zAdjust'])); $vars['majorAt0'] = round($vars['supplied']['major'],4); break;
			case 'okuma1': $vars['xAdjust'] = 0; $vars['majorAt0'] = round($vars['supplied']['major'] - ((tan(deg2rad($vars['supplied']['taper_angle']))*2)*($vars['zAdjust'])),4); break;
		}
		$vars['xAdjust'] = round($vars['xAdjust'],4);
	} else {
		$vars['majorAt0'] = null;
	}

	$vars['initial_x'] = ($fauxpost['r']['tTS'] == true) ? $vars['supplied']['minor']-0.1 : 'tpr';

	foreach(array('middle','chmf_front','chmf_back','main_front','main_back') as $wat) {
		for ($i=1; $i<=$vars['passes'][$wat]; $i++) {
			switch($wat) {
				case 'middle':
					$startPoint = $threadPoints['a'];
					$endPoint = $threadPoints['b'];
					$quant = ($vars['passes'][$wat]-1);
					$mult = ($i-1);
					break;
				case 'chmf_front':
					$startPoint = $threadPoints['g'];
					$endPoint = $threadPoints['e'];
					$quant = ($vars['passes'][$wat]-1);
					$mult = ($i-1);
					break;
				case 'chmf_back':
					$startPoint = $threadPoints['h'];
					$endPoint = $threadPoints['f'];
					$quant = ($vars['passes'][$wat]-1);
					$mult = ($i-1);
					break;
				case 'main_front':
					$startPoint = $threadPoints['e'];
					$endPoint = $threadPoints['c'];
					$quant = $vars['passes'][$wat];
					$mult = $i;
					break;
				case 'main_back':
					$startPoint = $threadPoints['f'];
					$endPoint = $threadPoints['d'];
					$quant = $vars['passes'][$wat];
					$mult = $i;
					$doing = $wat;
					break;
			}
			$runHere[] = array(
				'x' => round($vars['supplied']['major']+(($startPoint['x'] + ((($endPoint['x']-$startPoint['x'])/$quant)*$mult))*2),4)+$vars['xAdjust'],
				'z' => round($vars['zAdjust']+($startPoint['z']+((($endPoint['z']-$startPoint['z'])/$quant)*$mult)),4),
				'what' => $wat
			);
		}
	}

	$preCode = sprintf(implode(chr(10),$vars['pre']),
		$vars['supplied']['insert'],
		$vars['supplied']['major'],
		$vars['supplied']['minor'],
		$vars['supplied']['tpi'],
		$vars['supplied']['max_z'],
		$vars['supplied']['front_wall'],
		$vars['supplied']['back_wall'],
		$vars['supplied']['quality'],
		$vars['supplied']['taper_angle'],
		$vars['spindle'],
		$vars['initial_x'],
		$vars['zAdjust']
	) . chr(10);
	$postCode = sprintf(implode(chr(10),$vars['post']),
		$vars['initial_x']
	);
	
	if ($fauxpost['r']['tTS'] == true) {
		$preCode = str_replace('(MAJ @ Z0: ")'.chr(10),'',$preCode);
	}

	$threadCode = (string) null;
	foreach($runHere as $k => $v) {
		switch($vars['supplied']['machine']) {
			case 'haas':
				$theCode = sprintf($vars['code']['haas'],
					$v['z'],
					$k+1,
					count($runHere),
					$v['what'],
					ifNotExistAddDec($v['x']),
					ifNotExistAddDec($vars['supplied']['max_z']),
					ifNotExistAddDec($vars['feedrate']),
					ifNotExistAddDec($vars['angle_to_radial_diff'])
				);
				break;
			case 'okuma':
				$theCode = sprintf($vars['code']['okuma'],
					$v['z'],
					$k+1,
					count($runHere),
					$v['what'],
					ifNotExistAddDec($v['x']),
					ifNotExistAddDec($vars['supplied']['max_z']),
					ifNotExistAddDec($vars['feedrate']),
					ifNotExistAddDec($vars['angle_to_radial_diff'])
				);
				break;
		}
		$threadCode .= $theCode;
	}
	$finalCode = $preCode . $threadCode . $postCode;
?>