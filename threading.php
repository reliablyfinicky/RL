<?php
session_start();
if (!isset($_SESSION['loggedIn'])) { header('Location: login.php'); }
if (isset($_GET['thread'])) { include('threadFromGet.php'); }
?><!DOCTYPE html>
<html lang="en">
<head>
	<title>Hayden Engineering &mdash; Thread Calculator</title>
	<link rel="stylesheet" href="/css/screen.css" />
	<script src="/js/jquery.min.js"></script>
	<script src="/js/jquery-ui.min.js"></script>
	<script>
		$(document).ready(function(){
			<?php if (isset($_GET['thread'])) { ?>
			prePopulate(<?php echo base64_decode($_GET['thread']); ?>);
			fulfillTable(<?php echo json_encode($vars['PandT']); ?>);
			<?php } else { ?>
			prePopulate({"r":{"tTS":true},"t":{"f":"5","b":"5","q":"0"}});
			<?php } ?>
			$('div.form li').on('click',function(e){
				if ((e.target.nodeName == 'LI') && ($(e.target).find('input').length == 1)) {
					$(e.target).find('input').click();
				}
			});
			$('h2').on('click',function(e){
				$('li.show').not($(e.target).closest('li')).toggleClass('show hide');
				$(this).closest('li').toggleClass('show hide');
			});
			$('a').on('click',function(e){
				if ($(this).attr('href') == '#') {
					e.preventDefault();
					if ($(this).attr('id') == 'sbmt') {
						handleSubmit();
					} else if ($(this).attr('id').substr(0,4) == 'copy') {
						if ($(this).attr('id').substr(4) == 'Link') {
							var str = window.location.href;
							var succ = 'A link to this thread has been placed on your clipboard.';
						} else if ($(this).attr('id').substr(4) == 'Code') {
							var str = $('code').html();
							var succ = 'The generated G-Code has been placed on your clipboard.';
						}
					}
					$('#copy').val(str).focus().select();
					document.execCommand('copy');
				}
			});
			$('input').on('input change blur',function(){
				doSelf($(this),isThisValid($(this)));
				if (($(this).attr('data-special')) && (!$(this).prop('readonly'))) { handleSpecial($(this)); }
			});
			$.each($('input'),function(){
				doSelf($(this),isThisValid($(this)));
				if ($(this).attr('data-special')) { handleSpecial($(this)); }
			});
			updateQV($('#q').val());
		});
		$(document).on('input', '#q', function() {
			updateQV($(this).val());
		});
		function updateQV(val) {
			if (val.length == 1) {
				$('#qualityValue').html( '0' + val );
			} else {
				$('#qualityValue').html( val );
			}
		}
		function fulfillTable(json) {
			$.each(json,function(threadPart,values){
				$.each(values,function(pORt,val){
					$('#pandt_'+threadPart+'_'+pORt).html(val);
				});
			});
		}
		function handleSubmit() {
			var dtls = {"r":{},"t":{}};
			$.each($('input'),function(k,v){
				switch($(v).attr('type')) {
					case 'radio':
						if ($(v).prop('checked')) {
							dtls['r'][$(v).attr('id')] = true;
						}
						break;
					default:
						if ($(v).val().length > 0) {
							dtls['t'][$(v).attr('id')] = $(v).val();
						}
						break;
				}
			});
			<?php if (count($_GET) > 0) { ?>
			var newUrl = window.location.href.substr(0,window.location.href.indexOf('?')) + '?thread=' + window.btoa(JSON.stringify(dtls));
			<?php } else { ?>
			var newUrl = window.location.href + '?thread=' + window.btoa(JSON.stringify(dtls));
			<?php } ?>
			window.location.href = newUrl;
		}
		function handleSpecial($elem) {
			var spcl = $elem.attr('data-special');
			switch(spcl) {
				case 'tT':
					var dispOpts = $('[name="tT"]:checked').val();
					if (dispOpts == 'On') { $('#taperOptions').css({'display':'flex'}).closest('li[data-needs]').attr('data-needs',3); }
					else { $('#taperOptions').css({'display':'none'}).closest('li[data-needs]').attr('data-needs',1); }
					break;
				case 'calc':
					var $fields = $('[data-special="calc"]');
					var valFld = $fields.filter('.val').length;
					var clcFld = $fields.filter('.calc').length;
					if ((valFld == 1) && (clcFld == 1)) {
						$fields.filter('.calc').removeClass().val('').prop('readonly',false);
					} else if (valFld == 2) {
						$targ = $fields.not('.val');
						$targ.removeClass().addClass('calc').prop('readonly',true).val(calculateValue($targ));
						doParent($targ);
					} else if (valFld == 3) { // prepopulated all the fields
						console.log('prepop?');
					}
					break;
				default:
					console.log('WHAT ARE WE DOING CALLING THIS?!'+spcl);
					break;
			}
		}
		function doSelf($elem,valid) {
			switch(valid) {
				case true:
					if (!$elem.hasClass('calc')) {
						$elem.removeClass('inval').addClass('val');
					}
					break;
				case false:
					$elem.removeClass('val').addClass('inval');
					break;
				case 'EMPTY':
					$elem.removeClass('val inval').closest('li:not(.noval)').removeClass('val inval');
					break;
			}
			doParent($elem);
		}
		function showSubmit() {
			if ($('li[data-needs]').length == $('#threadMain > ol > li.val').length) { // ==
				$('#sbmt').css({'display':'block'});
			} else {
				$('#sbmt').css({'display':'none'});
			}
		}
		function doMain($elem) {
			var $mainLi = $elem.closest('li[data-needs]');
			if ($mainLi.find('input.val, input.calc').length >= $mainLi.attr('data-needs')) {
				$mainLi.removeClass('inval').addClass('val');
			} else if ($mainLi.find('input.inval').length > 0) {
				$mainLi.removeClass('val').addClass('inval');
			} else {
				$mainLi.removeClass('val inval');
			}
			populateSpan($elem);
		}
		function doParent($elem) {
			if ($elem.attr('type') == 'radio') {
				$.each($('input[name="'+$elem.attr('name')+'"]').not($elem),function(k,v){
					if (!$(v).prop('checked')) {
						$(v).removeClass('val').closest('li:not(.noval)').removeClass('val inval');
					}
				});
			}
			if (($elem.hasClass('val')) || ($elem.hasClass('calc'))) { $elem.closest('li:not(.noval)').removeClass('inval').addClass('val'); }
			else if ($elem.hasClass('inval')) { $elem.closest('li:not(.noval)').removeClass('val').addClass('inval'); }
			doMain($elem);
		}
		function isThisValid($elem) {
			switch($elem.attr('type')) {
				case 'radio':
					return ($elem.prop('checked')) ? true : 'EMPTY';
					break;
				case 'range':
					return true;
					break;
				case 'text':
					return doTesting($elem);
					break;
				default:
					console.log($elem.attr('type') + ' is not handled!');
					break;
			}
		}
		function doTesting($elem) {
			var value = $elem.val();
			if (value.length == 0) { return 'EMPTY'; }
			var tests = JSON.parse($elem.attr('data-validInput'));
			var passed = 0;
			$.each(tests,function(typeOfTest,checkAgainst){
				switch(typeOfTest) {
					case 'type':
						if (checkAgainst == 'float') {
							if (parseFloat(value) == value) { passed++; }
						} else {
							console.log('NO HANDLING FOR THIS TYPE COMPARISON: ' + checkAgainst);
						}
						break;
					case 'min':
						if (value >= checkAgainst) { passed++; }
						break;
					case 'max':
						if (value <= checkAgainst) { passed++; }
						break;
					case 'limitDecimals':
						$elem.val(limitDecimals(value,checkAgainst)); passed++;
						break;
					default:
						console.log('NO HANDLING FOR THIS TEST: ' + typeOfTest);
						break;
				}
			});
			if (passed == Object.keys(tests).length) {
				return true;
			} else {
				return false;
			}
		}
		function populateSpan($elem) {
			var $li = $elem.closest('[data-needs]');
			var disp = new Array();
			var $validFields = $li.find('input.val, input.calc');
			if ($validFields.length > 0) {
				$.each($validFields,function(kk,input){
					if ($(input).attr('data-display').indexOf('_') != -1) {
						disp.push('[' + $(input).attr('data-display').replace('_',$(input).val()) + ']');
					} else {
						disp.push('[' + $(input).attr('data-display') + ']');
					}
				});
			}
			if (disp.length > 0) { $li.find('h2 span').html(disp.join(', ')).css({'opacity':1}); }
			else { $li.find('h2 span').html('').css({'opacity':0}); }
			showSubmit();
		}
		function limitDecimals(val,num) {
			if (val.indexOf('.') == -1) {
				return val;
			} else {
				return (
					val.substr(0,val.indexOf('.')+1)
					+
					val.substr(val.indexOf('.')+1,num)
				);
			}
		}
		function calculateValue($elem) {
			var major = parseFloat($('#j').val());
			var minor = parseFloat($('#n').val());
			var height = parseFloat($('#g').val());
			switch($elem.attr('id')) {
				case 'j': var newVal = (minor + (height*2)); break;
				case 'n': var newVal = (major - (height*2)); break;
				case 'g': var newVal = ((major - minor)/2); break;
			}
			newVal = Math.round(newVal*10000)/10000;
			return newVal;
		}
		function prePopulate(str) {
			$.each(str,function(type,pairs){
				switch(type) {
					case 'r':
						$.each(pairs,function(elemId,bool){
							if (bool) {
								var $elem = $('#'+elemId);
								$elem.prop('checked',true);
								doSelf($elem);
							}
						});
						break;
					default:
						$.each(pairs,function(elemId,theVal){
							var $elem = $('#'+elemId);
							$elem.val(theVal);
							doSelf($elem);
						});
						break;
				}
			});
		}
	</script>
</head>
<body>
<form method="POST" action="threading.php">
	<textarea id="copy"></textarea>
	<?php include("navmenu.php"); ?>
	<div id="wrapper">
		<div id="threadMain">
			<ol>
				<li class="show" data-needs="1">
					<h2>Machine<span></span></h2>
					<div class="form">
						<ul class="oneLine">
							<li>
								<input type="radio" id="h" name="m" value="haas" data-display="Haas" />
								<label for="haas">Haas</label>
							</li>
							<li>
								<input type="radio" id="o" name="m" value="okuma" data-display="Okuma" />
								<label for="okuma">Okuma</label>
							</li>
						</ul>
					</div>
				</li>
				<li class="hide" data-needs="1">
					<h2>Insert<span></span></h2>
					<div class="form">
						<ul class="oneLine">
							<li>
								<input type="radio" id="i047" name="i" value="0.047" data-display="_&quot;" />
								<label for="ins047">0.047"</label>
							</li>
							<li>
								<input type="radio" id="i072" name="i" value="0.072" data-display="_&quot;" />
								<label for="ins072">0.072"</label>
							</li>
							<li>
								<input type="radio" id="i094" name="i" value="0.094" data-display="_&quot;" />
								<label for="ins094">0.094"</label>
							</li>
						</ul>
					</div>
				</li>
				<li class="hide" data-needs="3">
					<h2>Major / Minor / Height<span></span></h2>
					<div class="form">
						<ul class="oneLine">
							<li>
								<label for="j">Major</label>
								<input type="text" id="j" name="j" placeholder="2.665" data-validInput='{"type":"float","min":0,"max":10,"limitDecimals":4}' data-special="calc" data-display="Maj: _" />
							</li>
							<li>
								<label for="n">Minor</label>
								<input type="text" id="n" name="n" placeholder="2.601" data-validInput='{"type":"float","min":0,"max":10,"limitDecimals":4}' data-special="calc" data-display="Min: _" />
							</li>
							<li>
								<label for="g">Height</label>
								<input type="text" id="g" name="g" placeholder="0.032" data-validInput='{"type":"float","min":0.001,"max":0.5,"limitDecimals":4}' data-special="calc" data-display="Hgt: _" />
							</li>
						</ul>
					</div>
				</li>
				<li class="hide" data-needs="2">
					<h2>TPI / Depth<span></span></h2>
					<div class="form">
						<ul class="oneLine">
							<li>
								<label for="t">Threads/Inch</label>
								<input type="text" id="t" name="t" placeholder="4" data-validInput='{"type":"float","min":1.5,"max":15,"limitDecimals":3}' data-display="_ TPI" />
							</li>
							<li>
								<label for="z">Z Depth</label>
								<input type="text" id="z" name="z" placeholder="1.7" data-validInput='{"type":"float","min":-5,"max":5,"limitDecimals":4}' data-display="Z-_" />
							</li>
						</ul>
					</div>
				</li>
				<li class="hide" data-needs="1">
					<h2>Taper<span></span></h2>
					<div class="form">
						<ul class="oneLine">
							<li>
								<input type="radio" id="tTS" name="tT" value="Off" data-display="_" data-special="tT" />
								<label for="tTS">Straight</label>
							</li>
							<li>
								<input type="radio" id="tTT" name="tT" value="On" data-display="_" data-special="tT" />
								<label for="tTT">Tapered</label>
							</li>
						</ul>
						<ul id="taperOptions">
							<li>
								<label for="tA">Angle (radial)</label>
								<input type="text" id="tA" name="tA" placeholder="1" data-validInput='{"type":"float","min":0,"max":10,"limitDecimals":3}' data-display="_&deg; taper" />
							</li>
							<li>
								<h3>X = <em>&lt;major&gt;</em> @</h3>
								<ul>
									<li class="noval">
										<input type="radio" id="tM_Z" name="tM" value="max depth" data-display="major meets @ _" />
										<label for="tM_Z">Max Depth</label>
									</li>
									<li class="noval">
										<input type="radio" id="tM_0" name="tM" value="Z0." data-display="major meets @ _" />
										<label for="tM_0">Z0.</label>
									</li>
									<li class="noval">
										<input type="radio" id="tM_1" name="tM" value="Z0.2" data-display="major meets @ _" />
										<label for="tM_1">Z0.2</label>
									</li>
								</ul>
							</li>
						</ul>
					</div>
				</li>
				<li class="hide" data-needs="3">
					<h2>Options<span></span></h2>
					<div class="form">
						<ul class="oneLine">
							<li class="yay">
								<label for="f">Thread Wall (front)</label>
								<input type="text" id="f" name="f" placeholder="0" data-validInput='{"type":"float","min":0,"max":67.5,"limitDecimals":1}' data-display="Front wall _&deg;" />
							</li>
							<li class="yay">
								<label for="b">Thread Wall (back)</label>
								<input type="text" id="b" name="b" placeholder="0" data-validInput='{"type":"float","min":0,"max":10,"limitDecimals":1}' data-display="Back wall _&deg;" />
							</li>
						</ul>
						<ul>
							<li class="yay">
								<label for="q">Speed &lt;&mdash; <output id="qualityValue">05</output> &mdash;&gt; Tool Life</label>
								<input type="range" min="0" max="10" id="q" name="q" data-display="_/10 tool life" />
							</li>
						</ul>
					</div>
				</li>
				</ol>
				<a href="#" id="sbmt">
					<h2>Submit</h2>
				</a>
		</div>
		<?php if (isset($threadCode)) { ?>
		<div id="threadInfo">
		<table>
			<thead>
				<tr>
					<th></th>
					<th>passes</th>
					<th>est.time</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>Total</td>
					<td id="pandt_total_p"></td>
					<td id="pandt_total_t"></td>
					<td rowspan="6">
						<div class="flexem">
							<a href="#" id="copyLink">Copy permalink</a>
							<hr />
							<a href="#" id="copyCode">Copy threading code</a>
						</div>
					</td>
				</tr>
				<tr>
					<td>Middle</td>
					<td id="pandt_middle_p"></td>
					<td id="pandt_middle_t"></td>
				</tr>
				<tr>
					<td>Front Wall</td>
					<td id="pandt_main_front_p"></td>
					<td id="pandt_main_front_t"></td>
				</tr>
				<tr>
					<td>Back Wall</td>
					<td id="pandt_main_back_p"></td>
					<td id="pandt_main_back_t"></td>
				</tr>
				<tr>
					<td>Chamfer Front</td>
					<td id="pandt_chmf_front_p"></td>
					<td id="pandt_chmf_front_t"></td>
				</tr>
				<tr>
					<td>Chamfer Back</td>
					<td id="pandt_chmf_back_p"></td>
					<td id="pandt_chmf_back_t"></td>
				</tr>
			</tbody>
		</table>
		<code><?php echo $finalCode; ?></code>
		</div>
		<?php } ?>
	</div>
</form>
</body>
</html><!--<?php
//print_r($vars);
function numOnly($str) {
	$rtrn = (string) null; for ($i=0; $i<strlen($str); $i++) { if (((ord($str[$i]) >= 48) && (ord($str[$i]) <= 57)) || (ord($str[$i]) == 46)) { $rtrn .= $str[$i]; } } return $rtrn;
}
function alphaOnly($str) {
	$rtrn = (string) null; for ($i=0; $i<strlen($str); $i++) { if (((ord($str[$i]) >= 65) && (ord($str[$i]) <= 90)) || ((ord($str[$i]) >= 97) && (ord($str[$i]) <= 122))) { $rtrn .= $str[$i]; } } return $rtrn;
}
function RotatePoints($x,$z,$angle,$identity) {
	$rtrn = array('x'=>($x * cos(deg2rad($angle)) - $z * sin(deg2rad($angle))),'z'=>($x * sin(deg2rad($angle)) + $z * cos(deg2rad($angle))));
	if ($identity == 'a') {
		$rtrn['z'] = $z;
	}
	return $rtrn;
}
function ifNotExistAddDec($int) {
	return (strpos($int,'.') !== false) ? $int : $int . '.';
}
//print_r($runHere);
?>-->