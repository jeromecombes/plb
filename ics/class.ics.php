<?php
/**
Planning Biblio, Version 2.4
Licence GNU/GPL (version 2 et au dela)
Voir les fichiers README.md et LICENSE
@copyright 2011-2016 Jérôme Combes

Fichier : ics/class.ics.php
Création : 29 mai 2016
Dernière modification : 29 mai 2016
@author Jérôme Combes <jerome@planningbiblio.fr>

Description :
Classe permettant le traitement des fichiers ICS 
*/


/**
 * @note : 
 * Clés pour la MAJ de la base de données : UID + LAST-MODIFIED
 * - Si UID n'existe pas dans la base : INSERT (voir fonctionnement de UPDATE INTO)
 * - Si UID existe et LAST-MODIFIED ICS > LAST-MODIFIED BDD => UPDATE
 * à tester : récurrences : voir EXDATE et RECURRENCE-ID, RRUle
 * RRULE => FREQ=WEEKLY;COUNT=6;BYDAY=TU,TH
 * RRULE => FREQ=WEEKLY;UNTIL=20150709T073000Z;BYDAY=MO,TU,WE,TH
 * EXDATE : exception dates
 */
 
/*
 if(!isset($version)){
  include_once "../include/accessDenied.php";
}
*/

// TODO : loguer les imports / Modifs dans la table logs
// TODO : récurrences : interval weekly : vérifier s'il faut compter 1 semaine depuis le premier jour (soit 7 jours, option actuellement choisie) ou s'il faut compter une semaine depuis le lundi (ou dimanche)
// NOTE : interval monthly : Interval calculé sur des mois complets

// TEST
$version="test";

require_once "include/config.php";

// TODO : $defaultTimeZone dans la config
$defaultTimeZone="Europe/Paris";
global $defaultTimeZone;

date_default_timezone_set($defaultTimeZone);

class CJICS{

  public $calendar=null;
  public $events=null;
  public $error=null;
  public $src=null;

  
  /**
   * parse
   * Parse les événements d'un fichier ICS : créé un tableaux PHP contenant les événements
   * @param string $this->src : fichier ICS : chemin relatif ou absolu ou URL
   * @result array $this->calendar : informations sur le calendrier ICS parsé
   * @result array $this->events : tableaux des événements
   */
  public function parse(){
    if(!$this->src){
      $this->error="Fichier ICS absent";
      return false;
    }

    // Ouverture du fichier ICS
    $Fnm=$this->src;
    ini_set("auto_detect_line_endings", "1");

    $inF=fopen($Fnm,"r");

    if(!$inF){
      $this->error="Impossible d'ouvrir le fichier";
      return false;
    }
    
    // Construction du tableau $events contenant les événements
    $events=array();
    $id=0;
    // Lecture du fichier ICS, Pour chaque ligne ...
    while($line=fgets($inF)){

      // $done : indique si la ligne a été traitée, de façon la laisser de coté et passer à la ligne suivante (équivalent du "continue" d'une boucle for)
      $done=false;;
      
      // Si BEGIN;VEVENT : création d'un nouvel événement : nouvelle entrée dans le tableau $events
      if(substr($line,0,12)=="BEGIN:VEVENT"){
	// Incrémentation de l'id, clé du tableau events
	$id++;
	// Initialisation de la nouvelle entrée
	$events[$id]=array();
      }
      
      // Récupération des éléments qui ont débordés sur plusieurs lignes
      // Ces éléments sont réperés en fonction de la casses des premières lettres de la ligne.
      // Si elles ne sont pas en majuscules, la ligne ne commence pas par un index, donc c'est un débordement.
      if(!ctype_upper(substr($line,0,3)) and !in_array(substr($line,0,3),array("X-W","X-L")) and isset($key)){
	if(!is_array($events[$id][$key])){
	  $events[$id][$key].=$line;
	}else{
	  $tmp=array_keys($events[$id][$key]);
	  $tmp=$tmp[count($tmp)-1];
	  $events[$id][$key][$tmp].=$line;
	}
	$done=true;
      }
      
      
      // On n'enregistre pas les lignes BEGIN/END:VEVENT, BEGIN/END:VALARM, END:VCALENDAR, etc.
      // Donc, si la ligne ne correspond pas à ces critères ...
      // if(substr($line,0,6) != "BEGIN:" and substr($line,0,4) != "END:" and substr($line,0,5) != "RRULE" and !$done){
      if(substr($line,0,6) != "BEGIN:" and substr($line,0,4) != "END:" and !$done){
	// ... on créé on nouvelle entrée dans le tableau "événement" en définissant une clé (SUMMARY, UID, etc.) et une valeur (string ou array selon le cas)
	if(substr($line,0,7)=="DTSTART"){
	  $key="DTSTART";
	  $value=substr($line,strlen($key)+1);
	  $value=ICSDateConversion($value);
	}elseif(substr($line,0,10)=="TZOFFSETFR"){
	  $key="TZOFFSETFROM";
	  $value=substr($line,strlen($key)+1);
	}elseif(substr($line,0,10)=="TZOFFSETTO"){
	  $key="TZOFFSETTO";
	  $value=substr($line,strlen($key)+1);
	}elseif(substr($line,0,10)=="X-LIC-LOCA"){
	  $key="X-LIC-LOCATION";
	  $value=substr($line,strlen($key)+1);
	}elseif(substr($line,0,10)=="X-WR-CALID"){
	  $key="X-WR-CALID";
	  $value=substr($line,strlen($key)+1);
	}elseif (substr($line,0,10)=="X-WR-CALNA"){
	  $key="X-WR-CALNAME";
	  $value=substr($line,strlen($key)+1);
	}elseif(substr($line,0,10)=="X-WR-TIMEZ"){
	  $key="X-WR-TIMEZONE";
	  $value=substr($line,strlen($key)+1);
	}

	else{
	  switch(substr($line,0,3)){
	    // Valeurs simples
	    case "ATT" : $key="ATTENDEE";	$value=substr($line,strlen($key)+1);	break;
	    case "CAL" : $key="CALSCALE";	$value=substr($line,strlen($key)+1); 	break;
	    case "CLA" : $key="CLASS";		$value=substr($line,strlen($key)+1); 	break;
	    case "DES" : $key="DESCRIPTION";	$value=substr($line,strlen($key)+1); 	break;
	    case "LOC" : $key="LOCATION";	$value=substr($line,strlen($key)+1); 	break;
	    case "MET" : $key="METHOD";		$value=substr($line,strlen($key)+1); 	break;
	    case "PRO" : $key="PRODID";		$value=substr($line,strlen($key)+1); 	break;
	    case "SEQ" : $key="SEQUENCE";	$value=substr($line,strlen($key)+1); 	break;
	    case "STA" : $key="STATUS";		$value=substr($line,strlen($key)+1); 	break;
	    case "SUM" : $key="SUMMARY";	$value=substr($line,strlen($key)+1); 	break;
	    case "TRA" : $key="TRANSP";		$value=substr($line,strlen($key)+1); 	break;
	    case "TZI" : $key="TZID";		$value=substr($line,strlen($key)+1); 	break;
	    case "TZN" : $key="TZNAME";		$value=substr($line,strlen($key)+1); 	break;
	    case "UID" : $key="UID";		$value=substr($line,strlen($key)+1); 	break;
	    case "VER" : $key="VERSION";	$value=substr($line,strlen($key)+1); 	break;
	    case "X-M" : $key="X-MICROSOFT-CDO-INTENDEDSTATUS";	$value=substr($line,strlen($key)+1); 	break;

	    // Dates
	    case "CRE" : $key="CREATED";	$value=substr($line,strlen($key)+1); 	$value=ICSDateConversion($value);	break;
	    case "DTE" : $key="DTEND";		$value=substr($line,strlen($key)+1);	$value=ICSDateConversion($value);	break;
	    case "DTS" : $key="DTSTAMP";	$value=substr($line,strlen($key)+1);	$value=ICSDateConversion($value);	break;
	    case "EXD" : $key="EXDATE";		$value=substr($line,strlen($key)+1);    $value=ICSDateConversion($value);    	break;
	    case "LAS" : $key="LAST-MODIFIED";	$value=substr($line,strlen($key)+1); 	$value=ICSDateConversion($value);	break;
	    case "REC" : $key="RECURRENCE-ID";	$value=substr($line,strlen($key)+1);	$value=ICSDateConversion($value);	break;


	    // ORGANIZER : Nom et e-mail de l'organisateur
	    case "ORG" : $key="ORGANIZER";
			$value=substr($line,strlen($key)+1);
			$tmp=explode(":",$value);
			$cn=str_replace("CN=",null,$tmp[0]);
			$value=array("CN"=>trim($cn), "mail"=>trim($tmp[2]));
			break;
	    // Recurrency rules
	    case "RRU" : $key="RRULE";
			$value=substr($line,strlen($key)+1);
			$tmp=explode(";",$value);
			$tmp3=array();
			foreach($tmp as $elem){
			  $tmp2=explode("=",$elem);
			  $tmp3[$tmp2[0]]=trim($tmp2[1]);
			}
			if(array_key_exists("UNTIL",$tmp3)){
			  $tmp3["UNTIL"]=ICSDateConversion($tmp3["UNTIL"]);
			}
			$value=$tmp3;
			break;

	    default : $key="Undefined";	$value=$line;	break;
	  }
	}
	
	if(!is_array($value)){
	  $value=trim($value);
	}
	
	// Les informations sont ajoutés dans le tableau $events et liés à l'événement auquel elles appartiennent grace à la clé $id
	// Il peut y avoir plusieurs valeurs pour les champs ATTENDEE, EXDATE, RECURRENCE-ID, donc insertion sous forme de tableau
	if(in_array($key,array("ATTENDEE","EXDATE","RECURRENCE-ID","Undefined"))){
	  $events[$id][$key][]=$value;
	// Pour les autres, insertion sous forme d'une chaine de caractère
	}else{
	  $events[$id][$key]=$value;
	}
	
      }
      
      // Les informations VALARM ont été ignorées plus haut, on les traite ici
      // Si la ligne est BEGIN:VALARM ...
      elseif(substr($line,0,12) == "BEGIN:VALARM"){
	// ... On créé un tableau $alarm
	$alarm=array();
	// Et tant que END:VALARM n'est pas trouvé ...
	while($line=fgets($inF) and substr($line,0,10) != "END:VALARM"){
	  // ... On ajoute les éléments dans ce tableau, en définissant les clés de ce dernier      
	  if(substr($line,0,3)=="UID"){
	    $aKey="UID";
	    $value=substr($line,strlen($aKey)+1);
	  }else{
	    switch(substr($line,0,5)){
	      case "ACTIO" : $aKey="ACTION";			$value=substr($line,strlen($aKey)+1); 	break;
	      case "ATTAC" : $aKey="ATTACH";			$value=substr($line,strlen($aKey)+1); 	break;
	      case "ATTEN" : $aKey="ATTENDEE";			$value=substr($line,strlen($aKey)+1); 	break;
	      case "ACKNO" : $aKey="ACKNOWLEDGED";		$value=substr($line,strlen($aKey)+1); 	break;
	      case "DESCR" : $aKey="DESCRIPTION";		$value=substr($line,strlen($aKey)+1); 	break;
	      case "SUMMA" : $aKey="SUMMARY";			$value=substr($line,strlen($aKey)+1); 	break;
	      case "X-APP" : $aKey="X-APPLE-DEFAULT-ALARM";	$value=substr($line,strlen($aKey)+1); 	break;
	      case "X-WR-" : $aKey="X-WR-ALARMUID";		$value=substr($line,strlen($aKey)+1); 	break;
	      
	      case "TRIGG" : $aKey="TRIGGER";
			  $value=substr($line,strlen($aKey)+1);
			  $tmp=explode(":",$value);
			  $rel=str_replace('RELATED=',null,$tmp[0]);
			  $value=array("RELATED"=>$rel, "time"=>$tmp[1]);
			  break;

	      default : $aKey="Undefined";	$value=$line;	break;
	    }
	  }

	  if(in_array($aKey,array("Undefined"))){
	    $alarm[$aKey][]=$value;
	  }else{
	    $alarm[$aKey]=$value;
	  }

	}

	// On ajoute le tableau $alarm dans le tableau $events en le liant à l'événement auquel il appartient grace à la clé $id
	$events[$id]["VALARM"]=$alarm;
      }
      
      // Si la ligne est END:VEVENT : On termnie l'événement : on remplace son id temporaire ($id) par son UID
      elseif(substr($line,0,10) == "END:VEVENT"){
	// Création de tableaux pour le champ "ATTENDEE"
	// On le fait une fois que l'événement est bien constitué pour éviter les problèmes avec les débordements sur plusieurs lignes
	if(array_key_exists("ATTENDEE",$events[$id])){
	  foreach($events[$id]["ATTENDEE"] as $k => $v){
	    $value=array();
	    
	    // Récupération du mail (se trouve après :mailto: avec google)
	    $tmp=explode(":mailto:",$v);
	    $value["mail"]=$tmp[1];
	    
	    // Récupération des autres informations. Elles sont séparées par des ; sous cette forme : KEY:VALUE
	    $tmp=explode(";",$tmp[0]);
	    foreach($tmp as $elem){
	      $tmp2=explode("=",$elem);
	      $value[$tmp2[0]]=$tmp2[1];
	    }
	    $events[$id]["ATTENDEE"][$k]=$value;
	  }
	}
	
	// Création d'un tableaux contenant les jours concernés par la récurrence
	if(array_key_exists("RRULE",$events[$id]) and !empty($events[$id]["RRULE"])){
	  $this->currentEvent=$events[$id];
	  $this->recurrences();
	  $events[$id]["DAYS"]=$this->days;
	  $events[$id]["INFINITE"]=$this->infinite;
	}
      
      
	// On remplace l'id temporairement ($id) de l'événement par son UID
	if(array_key_exists($id,$events) and array_key_exists("UID",$events[$id]) and !empty($events[$id]["UID"])){
	  $uid=$events[$id]["UID"];
	  $events[$uid]=$events[$id];
	  unset($events[$id]);
	}
      }
      
    }
    // On ferme le fichier
    fclose($inF);


    // Le premier élément du tableau contient les premières lignes du fichier ICS.
    // Ce sont des informations générales relatives à l'ensemble des événements (timezone, etc.) et non un événement en particulier.
    // On transfert ces éléments dans un tableau $calendar
    $calendar=$events[0];
    unset($events[0]);


    // Tri des événements par dates
    uasort($events,"cmp_DTStart_Desc");
    
    $this->calendar=$calendar;
    $this->events=$events;
    
  }
  
  
  
  /**
  * recurrences
  * Créé un tableau contenant les jours concernés par la récurrence avec pour chaque jour la date de début et de fin de l'événement
  * @param Array $event : un événement ICS parsé (tableau PHP)
  */
  private function recurrences(){
    $event=$this->currentEvent;
    
    $rrule=$event['RRULE'];
    $exdate=array_key_exists("EXDATE",$event)?$event['EXDATE']:null;
    $start=$event["DTSTART"]['Time'];
    $end=$event['DTEND']['Time'];
    $duration=$end-$start;
    
    $freq=$rrule['FREQ'];
    $until=array_key_exists("UNTIL",$rrule)?$rrule['UNTIL']["Time"]:null;
    $count=array_key_exists("COUNT",$rrule)?$rrule['COUNT']:null;
    $interval=array_key_exists("INTERVAL",$rrule)?$rrule['INTERVAL']:1;
    $byday=array_key_exists("BYDAY",$rrule)?explode(",",$rrule['BYDAY']):null;
    $bymonthday=array_key_exists("BYMONTHDAY",$rrule)?explode(",",$rrule['BYMONTHDAY']):null;
    
    // Pour EXDATE (dates à exclure), on ne garde que le champ Time pour vérification ultérieure
    $tmp=array();
    if(is_array($exdate)){
      foreach($exdate as $elem){
	$tmp[]=$elem['Time'];
      }
    }
    $exdate=$tmp;
    
    // Si l'événément se répète à l'infini : on défini une date de fin ( J + 1 an ) et marque l'événement comme infini pour compléter régulièrement les dates
    $this->infinite="0";
    if(!$count and !$until){
      $until=strtotime(date("Y-m-d H:i:s")." + 1 year");
      $this->infinite="1";
    }
    
    
    $days=array();
      
    $d=$start;
    
    // Recherche des occurences avec si le paramètre UNTIL est présent
    if($until){
      while($d<$until){
	
	// En fonction de la fréquence
	switch($freq){
	  // Daily
	  case "DAILY": $d=strtotime(date("Y-m-d H:i:s",$d)." + $interval day");	break;
	  
	  // Weekly
	  case "WEEKLY": 
	    // Si BYDAY est présent, recherche tous les jours de la semaine. Les jours non désirés seront exclus ensuite (+ 1 day)
	    if(is_array($byday)){
	      $d=strtotime(date("Y-m-d H:i:s",$d)." + 1 day");
	      
	      // Si un interval est défini, on passe les semaines qui ne nous intérressent pas
	      if($interval){
		$diff = $d - $start;
		$oneWeek = strtotime("01/01/1970 + 1 week");
		$weekNumber = (int) ($diff / $oneWeek);
		$modulo = $weekNumber % $interval;
		if($modulo){
		  continue 2;
		}
	      }
	      

	    // SI BYDAY est absent, on passe au même jour de la semaine suivante (+ 1 week)
	    }else{
	      $d=strtotime(date("Y-m-d H:i:s",$d)." + $interval week");
	    }
	    break;
	    
	    // Monthly
	    case "MONTHLY":
	    // BYDAY : 1FR, -1SU, 2TH, etc.
	    // BYMONTHDAY : 1, 15, -1
	    if(is_array($byday) or is_array($bymonthday)){
	      $d=strtotime(date("Y-m-d H:i:s",$d)." + 1 day");

	      // Si un interval est défini, on passe les semaines qui ne nous intérressent pas
	      if($interval){
		$month0=date("n",$start);
		$month1=date("n",$d);
		$diff=$month1-$month0;
		$modulo = $diff % $interval;
		if($modulo){
		  continue 2;
		}
	      }

	    }else{
	      $d=strtotime(date("Y-m-d H:i:s",$d)." + $interval month");
	    }
	    
	
	    break;
	    
	    
	    
	}

	// On re-vérifie qu'on est bien inférieur à UNTIL car les sauts de semaines ou mois peuvent nous faire aller trop loin.
	if($d<=$until){

	  // Exclusion des dates ne correspondant pas au paramètre byday (MO,TU,WE, etc)
	  if(!byDay($d,$byday)){
	    continue;
	  }
/*
	  $day=strtoupper(substr(date("D",$d),0,2));
	  if(is_array($byday) and !in_array($day,$byday)){
	    continue;
	  }
*/
	  
	  // Exclusion des dates EXDATE
	  if(is_array($exdate) and in_array($d,$exdate)){
	    continue;
	  }
	  
	  // On ajoute au tableau les jours concernés avec les heures de début et de fin d'événement
	  $start1=date("Y-m-d H:i:s",$d);
	  $end1=date("Y-m-d H:i:s", $d+$duration );
	  $days[]=array($start1,$end1,date("D",$d));
	}

      }

    // Recherche des occurences avec si le paramètre COUNT est présent
    }elseif($count){
      for($i=0;$i<$count-1;$i++){

	// En fonction de la fréquence
	switch($freq){
	  // Daily
	  case "DAILY": $d=strtotime(date("Y-m-d H:i:s",$d)." + $interval day");	break;
	  
	  // Weekly
	  case "WEEKLY": 
	    // Si BYDAY est présent, recherche tous les jours de la semaine. Les jours non désirés seront exclus ensuite (+ 1 day)
	    if(is_array($byday)){
	      $d=strtotime(date("Y-m-d H:i:s",$d)." + 1 day");
	      
	      // Si un interval est défini, on passe les semaines qui ne nous intérressent pas
	      if($interval){
		$diff = $d - $start;
		$oneWeek = strtotime("01/01/1970 + 1 week");
		$weekNumber = (int) ($diff / $oneWeek);
		$modulo = $weekNumber % $interval;
		if($modulo){
		  $i--;
		  continue 2;
		}
	      }
	      
	    // Si BYDAY est absent, on passe au même jour de la semaine suivante (+ 1 week)
	    }else{
	      $d=strtotime(date("Y-m-d H:i:s",$d)." + $interval week");
	    }
	    break;
	    
	    // Monthly
	    case "MONTHLY":
	    // BYDAY : 1FR, -1SU, 2TH, etc.
	    // BYMONTHDAY : 1, 15, -1
/*
	    if(is_array($byday) and is_array($bymonthday)){
	    
	    }elseif(is_array($byday)){
	    
	    }elseif(is_array($bymonthday)){
	    
*/
	    if(is_array($byday) or is_array($bymonthday)){
	      $d=strtotime(date("Y-m-d H:i:s",$d)." + 1 day");
	      
	      // Si un interval est défini, on passe les semaines qui ne nous intérressent pas
	      if($interval){
		$month0=date("n",$start);
		$month1=date("n",$d);
		$diff=$month1-$month0;
		$modulo = $diff % $interval;
		if($modulo){
		  $i--;
		  continue 2;
		}
	      }

	    }else{
	      $d=strtotime(date("Y-m-d H:i:s",$d)." + $interval month");
	    }
	    
	
	    break;
	}

/*
	// $day : MO,TU,WE, TH, FR, SA, SU
	// $day2 : ex : 1MO (first monday), 2WE (2nd Wednesday), 3TH (3rd Thursday), etc.
	// $day3 : ex : -1SU (Last Sunday), 
	$day=strtoupper(substr(date("D",$d),0,2));
	$dayOfMonth=date("j",$d);

	if($dayOfMonth<8){		// de 1 à 7
	  $day2="1$day";
	}elseif($dayOfMonth<15){	// de 8 à 14
	  $day2="2$day";
	}elseif($dayOfMonth<22){	// de 15 à 21
	  $day2="3$day";
	}elseif($dayOfMonth<29){	// de 22 à 28
	  $day2="4$day";
	}else{				// de 29 à 31
	  $day2="5$day";
	}
	
	$daysInMonth = date("t",$d);
	if( $daysInMonth - $dayOfMonth < 7 ){		// Pour un mois de 31 jours : du 25 au 31
	  $day3="-1$day";
	}elseif( $daysInMonth - $dayOfMonth < 14 ){	// Pour un mois de 31 jours : du 18 au 24
	  $day3="-2$day";
	}elseif( $daysInMonth - $dayOfMonth < 21 ){	// Pour un mois de 31 jours : du 11 au 17
	  $day3="-3$day";
	}elseif( $daysInMonth - $dayOfMonth < 28 ){	// Pour un mois de 31 jours : du 4 au 10
	  $day3="-4$day";
	}else{						// Pour un mois de 31 jours : du 1 au 3
	  $day3="-5$day";
	}

	
	if(is_array($byday)){
	  $keep=false;
	  if(in_array($day,$byday) or in_array($day2,$byday) or in_array($day3,$byday)){
	    $keep=true;
	  }
	   
	  if(!$keep){
	    // Dans ce cas, le tour ne doit pas être compté : $i ne devrait pas être incrémenté, donc on le décrémente
	    $i--;
	    continue;
	  }
	}
	*/
	
	// Exclusion des dates ne correspondant pas au paramètre byday (MO,TU,WE, etc)
	if(!byDay($d,$byday)){
	  // Dans ce cas, le tour ne doit pas être compté : $i ne devrait pas être incrémenté, donc on le décrémente
	  $i--;
	  continue;
	}
	
	// Exclusion des dates EXDATE
	if(is_array($exdate) and in_array($d,$exdate)){
	  continue;
	}
	
	// On ajoute au tableau les jours concernés avec les heures de début et de fin d'événement
	$start1=date("Y-m-d H:i:s",$d);
	$end1=date("Y-m-d H:i:s", $d+$duration );
	$days[]=array($start1,$end1,date("D",$d));
      }
    }
    
    $this->days=$days;
    
    
    // TODO : traiter les BYDAY : OK  pour weekly
    // TODO : traiter les EXDATE : OK pour weekly
    // TODO : INTERVAL : OK pour DAILY et WEEKLY
    // TODO : INTERVAL
    // TODO : BYMONTH : january=1, peut être utilisé avec BYDAY et FREQ=YEARLY ou avec FREQ=DAILY (ex: DAILY BYMONTH=1 : tous les jours de janvier)
    // NOTE : WKST=SU; avec WEEKLY, Week start 
    // NOTE : BYDAY=1FR : first friday, with monthly
    // NOTE : BYDAY=-1SU : last sunday
    // NOTE : BYMONTHDAY=-2 (2 jours avant la fin du mois), BYMONTHDAY=2,15 : 2ème et 15ème jour
    // NOTE : BYYEARDAY, BYWEEKNO, BYSETPOS
    // TODO : FREQ=MONTHLY, YEARLY
    // TODO : FREQ=HOURLY,INTERVAL
    // TODO : FREQ=MINUTELY; INTERVAL;
    // TODO : FREQ=DAILY; BYHOUR=9,10,11; BYMINUTE=0,20,40
    // TODO : pas de fin : si ni COUNT ni UNTIL : définir une date de fin est préciser qq part que l'événement se répète indéfiniement : pour traitement ultérieur des dates à venir via cron : Fait : UNTIL = now + 1 year et champ INFINITE = 1
    // NOTE : événéments infinis : sont calculés les jours de maintenant à + 1 an, et champs INFINITE = 1 (0 par défaut)
    // TODO : événements infinis : recalculer 1 fois par jours les événemts infinis pour ajouter les nouvelles dates
    // REF  : http://www.kanzaki.com/docs/ical/rrule.html
    
    // TODO : A continuer
  }
  
  
  /**
   * updateDB
   * Enregistre les nouveaux événements d'un fichier ICS dans la base de données
   * Met à jour les événements modifiés
   * Marque les événements supprimés
   * @param string @this->src
   * @note : utilise la method $this->parse pour la lecture des fichiers ICS
   */
  public function updateDB(){
  
    // TEST
//     $time0=time();
  
    if(!$this->src){
      $this->error="Fichier ICS absent";
      return false;
    }
    
    // Lit le fichier ICS et le parse
    $this->parse();
    if($this->error){
      return false;
    }
    
    $calendar=$this->calendar;
    $events=$this->events;
    
    
    // TEST
    /*
    $time=time() - $time0;
    $time=date("i:s",$time);
    echo "<br>ICS Parser : $time";
    */
    
    $calName=$calendar['X-WR-CALNAME'];

    // Pour chaque événement
    // Si l'événement n'existe pas dans la base de données, on l'insère
    // Si l'événement existe et qu'il a été modifié (comparaison des champs LAST-MODIFIED), on le met à jour.
    $insert=array();
    $update=array();
    $uidsDB=array();
    $keep=array();
    
    
    // TODO : A continuer : Ajouter les autres champs dans la base de données (si besoin)
    // TODO : Créer la table via script PHP dans maj et setup/db_structure. Penser aux index
    $keys=array("UID","DESCRIPTION","LOCATION","SUMMARY","SEQUENCES","STATUS","DTSTART","DTEND","DTSTAMP","CREATED","LAST-MODIFIED","RRULE","DAYS","INFINITE");
    
    // Recherche des événements enregistrés dans la base de données
    $calDB=array();
    $db=new db();
    $db->select2("ics",null,array("CALNAME"=>$calName));
    if($db->result){
      foreach($db->result as $elem){
	// Evénéments de la base de données
	$calDB[$elem['UID']]=$elem;
	// Listes des UIDs enregistrés dans la base de données
	$uidsDB[]=$elem['UID'];
      }
    }

    // Pour chaque événment du fichier ICS
    foreach($this->events as $event){
      // Si le status n'est pas confimé, on ignore l'événement
      if(!in_array($event["STATUS"],array("CONFIRMED"))){
	continue;
      }

      // Marque les événements à la fois présents dans la base de données et dans le fichier ICS
      // Afin de supprimer les événements qui ne sont plus dans le fichier ICS
      if(in_array($event['UID'],$uidsDB)){
	$keep[]=$event['UID'];
      }

      // Si l'événement n'est pas dans la base de données ou s'il a été modifié : on copie les données dans les tableaux $insert ou $update
      // Comparaison des dates : on utilise != au lieu de > car permet de restaurer un événement marqué comme supprimé
      // Voir ligne : $req="UPDATE `{$GLOBALS['dbprefix']}ics` SET `STATUS`='DELETED', LASTMODIFIED=SYSDATE() WHERE `CALNAME`='$calName' AND `UID`=:UID;";
    
      if(!in_array($event["UID"],$uidsDB)
	or ( in_array($event["UID"],$uidsDB) and $event['LAST-MODIFIED']['YMDTime'] != $calDB[$event['UID']]['LASTMODIFIED'] )){

	$tmp=array(":CALNAME"=>$calName);
	foreach($keys as $k){
	  // Symbole - problématique avec PDO-SQL, on le supprime dans les champs MySQL
	  $k1=str_replace("-",null,$k);
	  if(is_array($event[$k]) and array_key_exists("YMDTime",$event[$k])){
	    $tmp[":$k1"]=$event[$k]["YMDTime"];
	  }elseif(is_array($event[$k])){
	    $tmp[":$k1"]=json_encode($event[$k]);
	  }elseif($event[$k]){
	    $tmp[":$k1"]=$event[$k];
	  }else{
	    $tmp[":$k1"]="";
	  }
	}
	
	// Si l'événement n'est pas dans la base de données, on l'insère
	if(!in_array($event["UID"],$uidsDB)){ // and $event['LAST-MODIFIED']>$calDB[$event['UID']]['LASTMODIFIED']){
	  $insert[]=$tmp;

	// Si l'événement est dans la base de données et qu'il a été modifié, on le met à jour
	}elseif( in_array($event["UID"],$uidsDB) and $event['LAST-MODIFIED']['YMDTime'] != $calDB[$event['UID']]['LASTMODIFIED'] ){
// 	  echo $event['LAST-MODIFIED']['YMDTime']." - ".$calDB[$event['UID']]['LASTMODIFIED']."<br/>\n";
	  $update[]=$tmp;
	}
      }
    }
    
    // TEST
    /*
    $time=time() - $time0;
    $time=date("i:s",$time);
    echo "<br>Tableaux PHP : $time";
    */
    
    // Insertion des nouveaux événments
    if(!empty($insert)){
      $k=array_keys($insert[0]);
      $fields="`".implode("`, `",$k)."`";
      $fields=str_replace(":",null,$fields);
      $values=implode(", ",$k);
      
      $req="INSERT INTO `{$GLOBALS['dbprefix']}ics` ($fields) VALUES ($values);";
      $db=new dbh();
      $db->prepare($req);
      foreach($insert as $elem){
	$db->execute($elem);
      }
    }
    
    // TEST
    /*
    $time=time() - $time0;
    $time=date("i:s",$time);
    echo "<br>INSERT DB : $time";
    */

    // Mise à jour des événements modifiés
    if(!empty($update)){
      $set=array();
      $k=array_keys($update[0]);
      foreach($k as $value){
	if(in_array($value,array(":CALNAME",":UID"))){
	  continue;
	}
	$field=str_replace(":",null,$value);
	$set[]="`$field`=$value";
      }
      $set=implode(", ",$set);
      
      $req="UPDATE `{$GLOBALS['dbprefix']}ics` set $set WHERE `CALNAME`=:CALNAME AND `UID`=:UID ;";
      $db=new dbh();
      $db->prepare($req);
      foreach($update as $elem){
	$db->execute($elem);
      }
    }

    // TEST
    /*
    $time=time() - $time0;
    $time=date("i:s",$time);
    echo "<br>UPDATE DB :$time";
    */
    
    
    // Recherche des événements supprimés (qui ne sont plus dans le fichier ICS) ou qui n'ont plus le status "CONFIMED"
    // Et marque ces événements comme supprimés dans la base de données
    $req="UPDATE `{$GLOBALS['dbprefix']}ics` SET `STATUS`='DELETED', LASTMODIFIED=SYSDATE() WHERE `CALNAME`='$calName' AND `UID`=:UID;";
    $db=new dbh();
    $db->prepare($req);
    foreach($uidsDB as $elem){
      if(!in_array($elem,$keep) and $calDB[$elem]['STATUS']!="DELETED") {
	$db->execute(array(":UID"=>$elem));
	echo "<br/>";
	echo $elem;
      }
    }
    
    
    // TEST
    /*
    $time=time() - $time0;
    $time=date("i:s",$time);
    echo "<br>DELETE DB :$time";
    */

  }
}


/** byDay
 * @param time $d : date courante, format time
 * @param array $byday : liste des jours à conserver : tableau contenant les éléments suivants : MO, TU, WE, TH, FR, SA, SU, 1MO, 2WE, 3FR, -1SU, -2SA, etc
 * La fonction retourne true si $byday n'est pas un tableau ou si $d correspond à un élément contenant dans $byday
 */
function byDay($d,$byday){

  // Si byday n'est pas un tableau (null), pas de filtre byday, donc retroune true
  if(!is_array($byday)){
    return true;
  }

  // $day1 : MO,TU,WE, TH, FR, SA, SU
  $day1=strtoupper(substr(date("D",$d),0,2));

  // day of month and days in month pour calculer les positions du jour $d dans le mois
  $dayOfMonth=date("j",$d);
  $daysInMonth = date("t",$d);

  // $day2 : ex : 1MO (first monday), 2WE (2nd Wednesday), 3TH (3rd Thursday), etc.
  if($dayOfMonth<8){		// de 1 à 7
    $day2="1$day1";
  }elseif($dayOfMonth<15){	// de 8 à 14
    $day2="2$day1";
  }elseif($dayOfMonth<22){	// de 15 à 21
    $day2="3$day1";
  }elseif($dayOfMonth<29){	// de 22 à 28
    $day2="4$day1";
  }else{			// de 29 à 31
    $day2="5$day1";
  }
    
  // $day3 : ex : -1SU (Last Sunday), etc.
  if( $daysInMonth - $dayOfMonth < 7 ){		// Pour un mois de 31 jours : du 25 au 31
    $day3="-1$day1";
  }elseif( $daysInMonth - $dayOfMonth < 14 ){	// Pour un mois de 31 jours : du 18 au 24
    $day3="-2$day1";
  }elseif( $daysInMonth - $dayOfMonth < 21 ){	// Pour un mois de 31 jours : du 11 au 17
    $day3="-3$day1";
  }elseif( $daysInMonth - $dayOfMonth < 28 ){	// Pour un mois de 31 jours : du 4 au 10
    $day3="-4$day1";
  }else{					// Pour un mois de 31 jours : du 1 au 3
    $day3="-5$day1";
  }

  
  $return=false;
  if(in_array($day1,$byday) or in_array($day2,$byday) or in_array($day3,$byday)){
    $return=true;
  }
  
  return $return;
}





function cmp_DTStart_Desc($a,$b){
  if(!array_key_exists("DTSTART",$a) or !array_key_exists("DTSTART",$b)){
    return 0;
  }
  if(strcmp($a["DTSTART"]["Time"],$b["DTSTART"]["Time"])==0 and array_key_exists("DTEND",$a) and array_key_exists("DTEND",$b)){
    return (int) strcmp($b["DTEND"]["Time"],$a["DTEND"]["Time"]);
  }
  return (int) strcmp($b["DTSTART"]["Time"],$a["DTSTART"]["Time"]);
}


/**
 * ICSDateConversion
 * @param string $value
 * @return Array("TZID"=> timezone, "DTime"=> Date_format_ICS", "Time"=> timestamp, "YMDTime"=> Date_format_Y-m-d H:i:s) 
 * Convertie une date au format ICS en "date PHP"
 * Retourne un tableau contenant le TimeZone, la date au format ICS, la date au format "time" (timestamp), la date au format Y-m-d H:i:s 
 * Prend en compte les time zones, @param global string $defaultTimeZone précisé en début de script
 */
function ICSDateConversion($value){
  // Avec Zimbra, le TimeZone est renseigné dans ce champ. On créé donc un tableau array(TZID, time)
  // Avec Google, le timeZone n'est pas spécifié

  
  $value=str_replace(array('VALUE=DATE:','"'),null,$value);
  
  if(substr($value,0,5)=="TZID="){
    $tmp=explode(":",$value);
    $tz=str_replace(array('TZID=','"'),null,$tmp[0]);
    
    // Gestion des time zones : date_default_timezone_set doit être utilisée pour utiliser le bon time zone si précisé
    date_default_timezone_set($tz);
    $time=strtotime($tmp[1]);
    $value=array("TZID"=>$tz, "DTime"=>$tmp[1], "Time"=>$time, "YMDTime"=> date("Y-m-d H:i:s",$time));

  }else{

    // Gestion des time zones : date_default_timezone_set doit être utilisée pour utiliser remettre le timezone par défaut si rien n'est précisé
    date_default_timezone_set($GLOBALS['defaultTimeZone']);
    $time=strtotime($value);
    $value=array("TZID"=>null, "DTime"=>$value, "Time"=>$time, "YMDTime"=> date("Y-m-d H:i:s",$time));
    
  }
  return $value;
}