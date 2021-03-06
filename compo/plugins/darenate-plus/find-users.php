<?php
// - ----------------------------------------------------------------------------------------- - //
# Shim for PHP <= 5.4.0, from here: http://stackoverflow.com/a/12018482
if (!function_exists('http_response_code')) {
	function http_response_code($newcode = NULL) {
		static $code = 200;
		if($newcode !== NULL) {
			header('X-PHP-Response-Code: '.$newcode, true, $newcode);
			if(!headers_sent())
			$code = $newcode;
		}       
		return $code;
	}
}
# PHP Shim End #
// - ----------------------------------------------------------------------------------------- - //
# Shim for PHP < 5.3, from http://php.net/manual/en/function.str-getcsv.php#98088
if (!function_exists('str_getcsv')) { 
    function str_getcsv($input, $delimiter = ',', $enclosure = '"', $escape = '\\', $eol = '\n') { 
        if (is_string($input) && !empty($input)) { 
            $output = array(); 
            $tmp    = preg_split("/".$eol."/",$input); 
            if (is_array($tmp) && !empty($tmp)) { 
                while (list($line_num, $line) = each($tmp)) { 
                    if (preg_match("/".$escape.$enclosure."/",$line)) { 
                        while ($strlen = strlen($line)) { 
                            $pos_delimiter       = strpos($line,$delimiter); 
                            $pos_enclosure_start = strpos($line,$enclosure); 
                            if ( 
                                is_int($pos_delimiter) && is_int($pos_enclosure_start) 
                                && ($pos_enclosure_start < $pos_delimiter) 
                                ) { 
                                $enclosed_str = substr($line,1); 
                                $pos_enclosure_end = strpos($enclosed_str,$enclosure); 
                                $enclosed_str = substr($enclosed_str,0,$pos_enclosure_end); 
                                $output[$line_num][] = $enclosed_str; 
                                $offset = $pos_enclosure_end+3; 
                            } else { 
                                if (empty($pos_delimiter) && empty($pos_enclosure_start)) { 
                                    $output[$line_num][] = substr($line,0); 
                                    $offset = strlen($line); 
                                } else { 
                                    $output[$line_num][] = substr($line,0,$pos_delimiter); 
                                    $offset = ( 
                                                !empty($pos_enclosure_start) 
                                                && ($pos_enclosure_start < $pos_delimiter) 
                                                ) 
                                                ?$pos_enclosure_start 
                                                :$pos_delimiter+1; 
                                } 
                            } 
                            $line = substr($line,$offset); 
                        } 
                    } else { 
                        $line = preg_split("/".$delimiter."/",$line); 
    
                        /* 
                         * Validating against pesky extra line breaks creating false rows. 
                         */ 
                        if (is_array($line) && !empty($line[0])) { 
                            $output[$line_num] = $line; 
                        }  
                    } 
                } 
                return $output; 
            } else { 
                return false; 
            } 
        } else { 
            return false; 
        } 
    } 
}
# PHP Shim End #
// - ----------------------------------------------------------------------------------------- - //

// Use Wordpress config.
require "../../../wp-config.php";

// - ----------------------------------------------------------------------------------------- - //
// HTTP HEAD response //
function rest_head($request) {
	http_response_code(200);
}
// HTTP GET response //
function rest_get($request) {
	rest_head($request);
	
	// ... //

	$db = mysqli_connect(DB_HOST,DB_USER,DB_PASSWORD,DB_NAME);

	// ... //

	if ( $db ) {	
		// Get Donations //
		$donation = NULL;
		{
			$result = mysqli_query($db,"SELECT * FROM wp_donations");
			while ($row = mysqli_fetch_array($result)) {
				$donation[] = $row;	// same as array_push(...), but faster;
			}
		}
		
		echo "Total: " . count($donation) . "\n";
		
		// Eliminate duplicate e-mail addresses, to build database of users // 
		$byAddress = array();
		{
			$donation_count = count($donation);
			for ($idx = 0; $idx < $donation_count; $idx++ ) {
				$mail = strtolower( $donation[$idx]["email"] );
				
				if ( array_key_exists($mail,$byAddress) ) {
					$byAddress[$mail]['total'] += floatval($donation[$idx]["amount"]);
					$byAddress[$mail]['donations'] ++;
					if ( $byAddress[$mail]['user'] ) {
						$byAddress[$mail]['user'] = intval($donation[$idx]["user_id"]);
					}
				}
				else {
					$byAddress[$mail] = array(
						id => $idx,
						donation_id => intval($donation[$idx]["ID"]),
						user => intval($donation[$idx]["user_id"]),
						total => 0.0 + floatval($donation[$idx]["amount"]),
						donations => 1,
						newuser => 0
					);
				}
			}
		}
		
		// Search all missing users //
		$userless = 0;
		$newusers = 0;
		{
			$byAddress_values = array_keys($byAddress);
			$byAddress_count = count($byAddress);
			for ($idx = 0; $idx < $byAddress_count; $idx++ ) {
				$key = $byAddress_values[$idx];
				$item = &$byAddress[$key];
				
				// If no user set, Search //
				if ( $item["user"] === 0 ) {
					$userless++;
					if ( $item["newuser"] === 0 ) {
						$result = mysqli_query($db,"SELECT ID FROM wp_users WHERE user_email='".$key."'" );
						while ($row = mysqli_fetch_array($result)) {
							//print_r($row);
							$item["newuser"] = $row['ID'];
							$newusers++;
						}
						
					}
				}
			}
		}
		
		// Output //
		{
			echo "Unique: " . count($byAddress) . "\n\n";
			$byAddress_values = array_keys($byAddress);
			$byAddress_count = count($byAddress);
			for ($idx = 0; $idx < $byAddress_count; $idx++ ) {
				$key = $byAddress_values[$idx];
				$item = $byAddress[$key];
				echo "[".$idx."] " . $key . " [" . $item["id"] . "] = " . $item["total"] . " (" . $item["donations"] . ")" . 
					" -- " . $item["user"] . " [" . $item["newuser"] . "]". ($item["total"] > 200 ? " * * * *" : "") . "\n";
			}
			
			echo "Userless: " . $userless . "\n";
			echo "New Users Found: " . $newusers . "\n";
		}
	
		// * * * //
		
		mysqli_close($db);

		// * * * //
	}
		
	echo "\n";
}
// - ----------------------------------------------------------------------------------------- - //
// HTTP ERROR response //
function rest_error($request) {
	http_response_code(400);
}
// - ----------------------------------------------------------------------------------------- - //


// - ----------------------------------------------------------------------------------------- - //
// START! //
// - ----------------------------------------------------------------------------------------- - //
// http://stackoverflow.com/a/897311
$request = explode("/", substr(@$_SERVER['PATH_INFO'], 1));

$rest_func = 'rest_'.strtolower($_SERVER['REQUEST_METHOD']);
if (function_exists($rest_func)) {
	// Call the appropriate HTTP response function //
	call_user_func($rest_func, $request);
}
// - ----------------------------------------------------------------------------------------- - //

?>