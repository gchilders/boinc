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
	$name = $number->name;
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
	$name = $number->name;

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
$numbers = BoincNumber_e::enum("status=1 OR status=2");
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


echo "done\n";
?>
