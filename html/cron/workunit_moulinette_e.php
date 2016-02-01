<?php

/*
 * moulinette automatique de gestion du projet rsals
 * sbastien lorquet, lionel debroux
 * doit se trouver dans $PROJECT/html/cron
 */

/*
 * changelog
squalyl 	26 apr 2010 - only manage numbers with q_last<q_end
debrouxl	10 may 2010 - always print how many WUs remain in q_last..q_end
debrouxl	12 jan 2011 - mark a todo item in manage_creation
debrouxl	14 oct 2011 - after a discussion with Greg Childers, make the WUs
        	              have batch and priority equal to the number's ID.
        	              Mark a todo item for future numbers.
*/

////temporarily disabled
//die("plop");

//===============================================================================
// CONFIG
//===============================================================================
require_once("../inc/util.inc");
require_once("../project/project.inc");
require_once("../inc/db_conn.inc");
require_once("../inc/boinc_db.inc");
require_once("../inc/boinc_db_rsals.inc");

require_once("./config_e.php");

//===============================================================================
// WORK GENERATION
//===============================================================================

function generate_work($number, $q_first, $q_interval, $num_wus) {
	$now=time();
	$stamp=strftime("%Y%m%d%H%M%S",$now);
	$boinc="/home/boincadm/projects/nfs";

	$numname=$number->name;
        $polyname = sprintf("%s.poly",$number->name);
	$results=Array();
	exec("cd $boinc && $boinc/bin/dir_hier_path $polyname", $results, $retcode);
	if($retcode!=0) {
		echo "ret code from dir_hier_path: $retcode\n";
		print_r($results);
		return $results[0];
	}
        $destination=$results[0];
        if (file_exists($destination)) {
                echo "$polyname already exists\n";
	} else {
		$polytext = $number->polyfile;
		$polytext .= "\n";
		file_put_contents($polyname,$polytext);
		echo "generating: $destination\n";
		rename($polyname, $destination);
	}
	$folder = "$boinc/sample_results/managed/$numname";
	if(!file_exists($folder)) {
                echo "created folder for $numname\n";
                $old = umask(0002);
                if(mkdir($folder,0775,true)!=true) {
                        return "mkdir $folder failed!";
                }
                umask($old);
        }
	if(!file_exists("$folder/$polyname")) {
                $polytext = $number->polyfile;
                $polytext .= "\n";
                file_put_contents($polyname,$polytext);
                $results=Array();
                exec("/home/boincadm/projects/nfs/html/cron/g2m $numname",$results,$retcode);
                echo "generating: $folder/$polyname\n";
                rename($polyname, "$folder/$polyname");
                rename("$numname.ini", "$folder/$numname.ini");
                rename("$numname.fb", "$folder/$numname.fb");
	}
        if (strpos($number->polyfile,"lss: 0")) {
		$sieveside = "-a";
                echo "sieving on algebraic side\n";
	} else {
		$sieveside = "-r";
                echo "sieving on rational side\n";
	}
	for($i=0; $i<$num_wus; $i++) {
		$wuid = $number->id + 100;
                $wuname = sprintf("%s_%d",$number->name,$q_first/1000);

		$command ="cd $boinc && bin/create_work ";
		$command.="--batch $wuid ";
		$command.="--priority $wuid ";
		$command.="--appname lasievee ";
		$command.="--wu_name $wuname ";
		$command.="--wu_template templates/la_wu4 ";
		$command.="--result_template templates/la_resultz ";
		$command.="--command_line \"$sieveside -f $q_first -c $q_interval\" ";
		$command.="--rsc_memory_bound 500000000 ";
		$command.="--additional_xml '<credit>44</credit>' ";
		$command.="$polyname";
		$results=Array();
		exec($command,$results,$retcode);
		if($retcode!=0) {
			echo "ret code from create_work: $retcode\n";
			print_r($results);
			return $results[0];
		}
		$q_first += Q_PER_WU; //remember this one has been done
		$number->q_last = $q_first;
		$number->update("q_last=$number->q_last");
	}
	//hmm peut etre il faut mettre a jour a chaque fois? sinon c'est un peu brutal.
	if($num_wus>0) {
		$number->generation_time=$now;
		$number->update("generation_time=$number->generation_time");
	}
	//--------------------
	// Done, no error 
	//--------------------
	return null;	
}

function manage_creation($number,$rerate) {
	$now=time();
	$name = $number->name;
	
	//--------------------
	// count workunits in the UNSENT(2) state
	// TODO: use batch ID.
	//--------------------
	$query = "SELECT COUNT(*) AS count FROM result WHERE name like '{$name}_%' AND server_state=2";
	$result = _mysql_query($query);
	if(mysql_errno()!=0) {
		return _mysql_error();
	}
	if(_mysql_num_rows($result)!=1) {
		return "expected one result, got "._mysql_num_rows($result);
	}
	$result = _mysql_fetch_object($result);
	$count_unsent = $result->count;
	echo "unsent results: $count_unsent\n";


	$q_last = $number->q_last;
	if($q_last==0) {
		$q_last = $number->q_start;
	}
	$q_end = $number->q_end;
	$q_count = $q_end - $q_last;
	if($q_count<0) {
		echo "wtf? negative count!\n";
		$q_count=0;
	}
	$q_interval = Q_PER_WU;
	$required = $q_count / $q_interval;
	echo "remaining WU creations in q range [$q_last..$q_end] (length $q_count): $required.\n";

	//--------------------
	// Compute how much WUs should be generated according to the last run time
	//--------------------
	if($count_unsent < $rerate) {
		//echo "work generation threshold reached\n";
		if($required>0) {
			$time_last_updated = $number->update_time;
			$time_now = time();
			$delta = $time_now - $time_last_updated;
			//echo "last work generation was executed $delta seconds ago\n";
			$results_required = $delta / 3600 * $rerate;
			$wus_required = $results_required / RESULTS_PER_WU;
			echo "based on the delay since last generation ($delta seconds) : $wus_required WUs required\n";
			$wus_required = (int)$wus_required;
			if($wus_required > $required) {
				echo "work generation limited to $required because time bound request is too big\n";
				$wus_required = $required;
			}
			if($wus_required>$rerate) {
				echo "work generation limited to $rerate because value is bigger than rate\n";
				$wus_required = $rerate;
			}	
			echo "Final count: $wus_required WUs with $q_interval Q values/WU\n";
			$ok = generate_work($number, $q_last, $q_interval, $wus_required); 
			if($ok!=null) return $ok;
		}

	} else {
		echo "no new work needed\n";
	}
	$number->update_time=$now;
	$number->update("update_time=$number->update_time");

	//--------------------
	// Done, no error 
	//--------------------
	return null;
}

//===============================================================================
// MAIN ENTRY POINT
//===============================================================================

$lockfile = "/tmp/workunit_moulinette_e.lock";              
$res = @stat($lockfile);
if($res===false) {           
    $lock = fopen($lockfile,"wb");
} else {
    echo "moulinette already running\n";
    exit(0);        
}
db_init();
$now=time();
$stamp=strftime("%Y%m%d%H%M%S",$now);
set_time_limit(0);

echo "$stamp: starting rsals workunits moulinette\n";
$time_start = microtime(true);


$numbers = BoincNumber_e::enum("status=1 AND q_start<>0 AND q_end<>0 AND q_last<q_end");
$count = count($numbers);
if($count>0) {

	$rerate = WUS_PER_DAY / (24 * $count);
	$wurate = $rerate / RESULTS_PER_WU;

	echo "generation rate per job: $wurate WUs/hour, $rerate results/hour\n";
	$index = 1;
	foreach($numbers as $number) {
		$name = $number->name;
		echo "Managing: #$index of $count: $name\n";
		$ok = manage_creation($number, $rerate);
		if($ok!=null) {
			echo "Something failed: ".$ok."\n";
		} else {
			echo "OK\n";
		}
		$index++;
	}
} else {
	echo "grid is starving\n";
}

$time_end = microtime(true);
echo "done in ".(($time_end-$time_start)*1000)." ms ------------------------------\n";

fclose($lock);
unlink($lockfile);  
?>
