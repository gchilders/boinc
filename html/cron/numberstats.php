<?php

/*
 * moulinette automatique de gestion du projet rsals
 * sbastien lorquet, lionel debroux
 * doit se trouver dans $PROJECT/html/cron
 */

/*
 * actions
 pour chaque nombre en base prt  tre gr:
 --creer un dossier pour le nombre
 -- si pas de fichiers jobs, les crer (c'est un nouveau nombre)
 -- calculer le nombre de workunits pretes a envoyer (zro si nouveau nombre)
 -- creer de nouvelles workunits si ncessaire
 -- mettre  jour q_last_pushed
 -- regarder quel results ont t reus
 -- noter la taille du fichier global, et le nombre de
    relations actuel
 -- pour chaque fichier resultat
    -- le compresser si ncessaire
    -- le faire valider par gzip -t
    -- s'il est valide:
         -- faire wc -l pour avoir le nombre de relations, et cumuler
         -- append au fichier global
    -- si erreur dans le process, truncate a la taille
       precedente, restaure nb de relations, signaler erreur
       et quitter (puis dsactiver crontab)
    -- supprimer les results qu'on vient d'ajouter
  --envoi de mail si famine
 */


//===============================================================================
// CONFIG
//===============================================================================
require_once("../inc/util.inc");
require_once("../project/project.inc");
require_once("../inc/db_conn.inc");
require_once("../inc/boinc_db.inc");
require_once("../inc/boinc_db_rsals.inc");

//===============================================================================
// NUMBER ENTRY POINT
//===============================================================================
function count_results($number, $field, $cond) {
	//--------------------
	// count workunits in the UNSENT(2) state
	//--------------------
	$name = trim($number->name);
	$query = "SELECT COUNT(*) AS count FROM result WHERE name like '{$name}_%' AND $cond";
//	echo "$query\n";
	$result = _mysql_query($query);
	if(mysql_errno()!=0) {
		return _mysql_error();
	}
	if(_mysql_num_rows($result)!=1) {
		return "expected one result, got "._mysql_num_rows($result);
	}
	$result = _mysql_fetch_object($result);
	$count = $result->count;
	echo "$field: $count\t";

	$number->update("$field=$count");
}

function manage_number($number) {
	$time_start = microtime(true);
	$name = trim($number->name);

	count_results($number, "results_unsent", "server_state=2");	
	count_results($number, "results_pending", "server_state=4");	

	//--------------------
	// Done, no error 
	//--------------------
	$time_end = microtime(true);
	return null;
}

//===============================================================================
// MAIN ENTRY POINT
//===============================================================================

db_init();

echo "counting results\n";
$numbers = BoincNumber::enum("status=1 OR status=2");
$count = count($numbers);


$index = 1;
foreach($numbers as $number) {
	$name = $number->name;
	echo "$name\t";
	$ok = manage_number($number);
	echo "\n";
	if($ok!=null) {
		echo "Something failed: ".$ok."\n";
	}
	$index++;
}


//use RRDTOOL to update the results stats
//ready to send
function count_global_results($cond) {
	$result = _mysql_query("select count(*) as count from $cond");
	$line = _mysql_fetch_object($result);
	_mysql_free_result($result);
	return $line->count;
}
$rready = count_global_results("result where server_state=2 and appid=6");
$rprogress = count_global_results("result where server_state=4 and appid=6");
echo "ready: $rready - in progress: $rprogress\n";

$out=array();
$cmd="rrdtool updatev /home/boincadm/projects/nfs/html/cron/results.rrd N:$rready:$rprogress";
echo "$cmd\n";
exec($cmd, $out, $err);
if($err==0) {
	echo "rrdtool: success\n";
} else {
	echo "rrdtool: error $err\n";
}
// foreach($out as $line) {
//	echo "$line\n";
// }

//disk space stats
$out=array();
$cmd = "df | grep escatter | awk 'BEGIN { FS=\" \" } ; { print $2 }'";
exec($cmd,$out,$err);
if($err==0) {
	$size=$out[0];
} else {
	$size='U';
}
$out=array();
$cmd = "df | grep escatter | awk 'BEGIN { FS=\" \" } ; { print $3 }'";
exec($cmd,$out,$err);
if($err==0) {
	$used=$out[0];
} else {
	$used='U';
}
if($used!='U' && $size!='U') {
	$frac = 100.0 * $used / $size;
}
echo "disk: size=$size used=$used frac=$frac \n";

$cmd="rrdtool updatev /home/boincadm/projects/nfs/html/cron/disk.rrd N:$frac";
echo "$cmd\n";
exec($cmd,$out,$err);
if($err==0) {
	echo "rrdtool: success\n";
} else {
	echo "rrdtool: error $err\n";
}
foreach($out as $line) {
	echo "$line\n";
}
$frac = (int)(10*$frac)/10;
if ($frac > 90) mail("gchilders@fullerton.edu","WARNING: Disk at $frac percent","WARNING: BOINC server disk space is at $frac percent!\n");

//draw graphs
$out=array();
$cmd="/home/boincadm/projects/nfs/html/cron/make_graphs.sh";
exec($cmd,$out,$err);
foreach($out as $line) {
	echo "$line\n";
}

echo "done\n";
?>
