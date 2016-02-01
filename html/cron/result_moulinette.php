<?php
/*
moulinette des resultats

changelog
 2010 apr 26 also integrating numbers in "queued for postprocessing" state.
 2010 jun 21 add the pair of umask calls, so that result folders in the "managed"
             folder are created with 0775 permission (due to umask 0022, they
             used to be created with 0755)
*/

//===============================================================================
// CONFIG
//===============================================================================
require_once("../inc/util.inc");
require_once("../project/project.inc");
require_once("../inc/db_conn.inc");
require_once("../inc/boinc_db.inc");
require_once("../inc/boinc_db_rsals.inc");

require_once("./config.php");


//===============================================================================
// RESULTS POSTPROCESSING
//===============================================================================

function manage_result_file($number, $file, $destination) {
	$boinc=PROJECTPATH;
	$name = trim($number->name);

	//--------------------
	//check if the file is compressed
	// old clients cant compress, so do it if not done
	//--------------------
	$output=Array();
	exec("gzip -t $file",$output,$error);
	if($error!=0) {
		rename($file, "$file.tmp");
		exec("gzip -dcf $file.tmp > $file",$output,$error);
		unlink("$file.tmp");
		exec("gzip $file",$output,$error);
		if($error!=0) {
			return "error gzipping $file";
		}
		rename("$file.gz", $file);
		echo "$file was not gzipped, just did it\n";
	}

	//--------------------
	//check this is now a valid gzip file
	//if not valid, delete and return , manage next file
	//TODO: we cant' do that! how to check received files are valid relation data?
	//--------------------

	//--------------------
	//count file size
	//--------------------
	$output = stat($file);
	$file_size = $output["size"];

	//--------------------
	//count relations
	//--------------------
	$output=Array();
	exec("zcat $file | wc -l", $output, $error);
	if($error!=0 || count($output)!=1) {
		return "$file: failed to count relations";
	}
	$file_relations = (int)$output[0];
	echo "file size: $file_size, relations: $file_relations\n";

	//--------------------
	//append file to big result
	//--------------------

	$output=array();
	exec("cat $file >> '$destination'",$output,$error);
	if($error!=0) {
		return "could not append result to destination";
	}

	//--------------------
	//Add information to db: nr of results, nr of relations, total file size
	//if that fails, we have to truncate the output file to the size it had before the append.
	//TODO: test failure
	//--------------------
	$number->globresult_size += $file_size;
	$number->globresult_relations += $file_relations;
	$number->results_received += 1;
	$number->update("globresult_size=globresult_size+$file_size, globresult_relations=globresult_relations+$file_relations, results_received=results_received+1");

	//--------------------
	//delete processed result
	//--------------------
	unlink($file);

	return null;
}

function find_files_in($folder, $wildcard) {
	$results = Array();
	$cmd="find $folder -maxdepth 1 -name '$wildcard'";
	exec($cmd,$results,$error);
	if($error!=0) {
		echo "error: find returned $error\n";
		return null;
	} else {
		if($results==null) { //if exec returns nothing it's not an empty array but null!
			$results=Array();
		}
	}
	return $results;
}

function bigfilesize($path) {
	return (double)exec("stat -c %s ".escapeshellarg($path));
}

function manage_results($number) {
	global $stamp;
	$boinc=PROJECTPATH;
	$name = trim($number->name);
	$folder = "$boinc/sample_results/managed/$name";
	//--------------------
	// Check job folder and create if required
	//--------------------
	if(!file_exists($folder)) {
		echo "created folder for new number\n";
		$old = umask(0002);
		if(mkdir($folder,0775,true)!=true) {
			return "mkdir $folder failed!";
		}
		umask($old);
	} else {
		if(!is_dir($folder)) {
			return "$folder exists but is not a folder, cannot continue";
		}
	}

	$destination = "$boinc/sample_results/managed/$name/".trim($name).".dat.gz";
	$size_before = bigfilesize($destination);

	//--------------------
	// Find all received result files so far
	//--------------------
	$results = find_files_in("$boinc/sample_results", trim($name)."_*");
	if(count($results)==0) {
		echo "no files\n";
	} else {
		//--------------------
		//check and create big result file
		//--------------------

		$count = count($results);
		echo "found $count results\n";

		//--------------------
		// Process each file in manage_result_file
		//--------------------
		foreach($results as $result) {
			echo "=> $result: ";
			$ok = manage_result_file($number, $result, $destination);
			if($ok!=null) return $ok;
		}
	}

	//--------------------
	// use total nr of relations and total nr of results managed so far to compute mean number of relations per result.
	// compute projected number of relations based on previous mean and sieving range.
	//--------------------
	$results_total = ($number->q_end - $number->q_start) / Q_PER_WU;
	$results_now = $number->results_received;
	$fraction = $results_now / $results_total;
	$projected_size = $number->globresult_size / $fraction;
	$projected_rels = $number->globresult_relations / $fraction;
	echo "fraction done: $fraction, projected : size = $projected_size, rels = $projected_rels\n";

	//--------------------
	// log some stats about the final file size
	//--------------------
	if(count($results)>0) {
		//--------------------
		//first of all, SYNC so that kernel buffers are flushed
		//--------------------
		exec("sync");

		//--------------------
		//append data to projection log
		//--------------------
		$size_after = bigfilesize($destination);

		exec("touch $folder/$name.projection");
		$log=fopen("$folder/$name.projection","a");
		echo "output file size : $size_after bytes (was $size_before)\n";
		fputs($log,"$stamp size_before=$size_before size_after=$size_after size_projected=$projected_size rels_projected=$projected_rels\n");
		fclose($log);
	}
	

	return null;
}

//===============================================================================
// MAIN ENTRY POINT
//===============================================================================
//get lock
$lockfile = "/tmp/result_moulinette.lock";
$res = @stat($lockfile);
if($res===false) {
    $lock = fopen($lockfile,"wb");
} else {
    echo "moulinette already running\n";
    exit(0);
}

$now=time();
$stamp=strftime("%Y%m%d%H%M%S",$now);
db_init();
set_time_limit(0);

echo "$stamp: starting rsals results moulinette\n";
$time_start = microtime(true);


$numbers = BoincNumber::enum("(status=1 OR status=2) AND q_start<>0 AND q_end<>0");
$count = count($numbers);

$index = 1;

foreach($numbers as $number) {
	$name = trim($number->name);
	echo "Managing: #$index of $count: $name\n";
	$ok = manage_results($number);
	if($ok!=null) {
		echo "Something failed: ".$ok."\n";
	} else {
		echo "OK\n";
	}
	$index++;
}

$time_end = microtime(true);

echo "done in ".(($time_end-$time_start)*1000)." ms ----------------\n";
fclose($lock);
unlink($lockfile);
?>
