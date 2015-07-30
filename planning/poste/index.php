<?php
/*
Planning Biblio, Version 2.0.1
Licence GNU/GPL (version 2 et au dela)
Voir les fichiers README.md et LICENSE
Copyright (C) 2011-2015 - Jérôme Combes

Fichier : planning/poste/index.php
Création : mai 2011
Dernière modification : 30 juillet 2015
Auteur : Jérôme Combes, jerome@planningbilbio.fr

Description :
Cette page affiche le planning. Par défaut, le planning du jour courant est affiché. On peut choisir la date voulue avec le
calendrier ou les jours de la semaine.

Cette page est appelée par la page index.php
*/

require_once "class.planning.php";
require_once "planning/postes_cfg/class.tableaux.php";
include_once "absences/class.absences.php";
include_once "personnel/class.personnel.php";
require_once "include/horaires.php";
echo "<div id='planning'>\n";

include "fonctions.php";

// Initialisation des variables
$groupe=filter_input(INPUT_GET,"groupe",FILTER_SANITIZE_NUMBER_INT);
$site=filter_input(INPUT_GET,"site",FILTER_SANITIZE_NUMBER_INT);
$tableau=filter_input(INPUT_GET,"tableau",FILTER_SANITIZE_NUMBER_INT);
$verrou=false;

//		------------------		DATE		-----------------------//
$date=filter_input(INPUT_GET,"date",FILTER_CALLBACK,array("options"=>"sanitize_dateSQL"));
if(!$date and array_key_exists('PLdate',$_SESSION)){
  $date=$_SESSION['PLdate'];
}
elseif(!$date and !array_key_exists('PLdate',$_SESSION)){
  $date=date("Y-m-d");
}
$_SESSION['PLdate']=$date;
$dateFr=dateFr($date);
$d=new datePl($date);
$semaine=$d->semaine;
$semaine3=$d->semaine3;
$jour=$d->jour;
$dates=$d->dates;
$datesSemaine=join(",",$dates);
$j1=$dates[0];
$j2=$dates[1];
$j3=$dates[2];
$j4=$dates[3];
$j5=$dates[4];
$j6=$dates[5];
$j7=$dates[6];
$dateAlpha=dateAlpha($date);

$_SESSION['oups']['week']=false;
//		------------------		FIN DATE		-----------------------//
//		------------------		TABLEAU		-----------------------//
$t=new tableau();
$t->fetchAllGroups();
$groupes=$t->elements;

// Multisites : la variable $site est égale à 1 par défaut.
// Elle prend la valeur GET['site'] si elle existe, sinon la valeur de la SESSION ['site']
// En dernier lieu, la valeur du site renseignée dans la fiche de l'agent
if(!$site and array_key_exists("site",$_SESSION['oups'])){
  $site=$_SESSION['oups']['site'];
}
if(!$site){
  $p=new personnel();
  $p->fetchById($_SESSION['login_id']);
  $site=$p->elements[0]['sites'][0];
}
$site=$site?$site:1;
$_SESSION['oups']['site']=$site;

$db=new db();
$db->select2("pl_poste","*",array("date"=>"IN$datesSemaine", "site"=>$site));
$pasDeDonneesSemaine=$db->result?false:true;
//		------------------		FIN TABLEAU		-----------------------//
global $idCellule;
$idCellule=0;
//		------------------		Vérification des droits de modification (Autorisation)	------------------//
$autorisation=false;
if($config['Multisites-nombre']>1){
  if(in_array((300+$site),$droits)){
    $autorisation=true;
  }
}
else{
  $autorisation=in_array(12,$droits)?true:false;
}

//		-----------------		FIN Vérification des droits de modification (Autorisation)	----------//
// Catégories
$categories=array();
$db=new db();
$db->select2("select_categories");
if($db->result){
  foreach($db->result as $elem){
    $categories[$elem['id']]=$elem['valeur'];
  }
}
//		---------------		changement de couleur du menu et de la periode en fonction du jour sélectionné	---------//
$class=array('menu','menu','menu','menu','menu','menu','menu','menu');

switch($jour){
  case "lun":	$jour3="Lundi";		$periode2='semaine';	$class[0]='menuRed';	break;
  case "mar":	$jour3="Mardi";		$periode2='semaine';	$class[1]='menuRed';	break;
  case "mer":	$jour3="Mercredi";	$periode2='semaine';	$class[2]='menuRed';	break;
  case "jeu":	$jour3="Jeudi";		$periode2='semaine';	$class[3]='menuRed';	break;
  case "ven":	$jour3="Vendredi";	$periode2='semaine';	$class[4]='menuRed';	break;
  case "sam":	$jour3="Samedi";	$periode2='samedi';	$class[5]='menuRed';	break;
  case "dim":	$jour3="Dimanche";	$periode2='samedi';	$class[6]='menuRed';	break;
}
	
//-----------------------------			Verrouillage du planning			-----------------------//
$db=new db();
$db->select2("pl_poste_verrou","*", array("date"=>$date, "site"=>$site));
if($db->result){
  $verrou=$db->result[0]['verrou2'];
  $perso=nom($db->result[0]['perso']);
  $perso2=nom($db->result[0]['perso2']);
  $date_validation=dateFr(substr($db->result[0]['validation'],0,10));
  $heure_validation=substr($db->result[0]['validation'],11,5);
  $date_validation2=dateFr(substr($db->result[0]['validation2'],0,10));
  $heure_validation2=substr($db->result[0]['validation2'],11,5);
  $validation2=$db->result[0]['validation2'];
}else{
  $perso2=null;
  $date_validation2=null;
  $heure_validation2=null;
  $validation2=null;
}
//	---------------		FIN changement de couleur du menu et de la periode en fonction du jour sélectionné	--------------------------//

//	Selection des messages d'informations
$db=new db();
$db->select2("infos","*",array("debut"=>"<={$date}", "fin"=>">={$date}"),"ORDER BY `debut`,`fin`");
$messages_infos=null;
if($db->result){
  foreach($db->result as $elem){
    $messages_infos[]=$elem['texte'];
  }
  $messages_infos=join($messages_infos," - ");
}

//		---------------		Affichage du titre et du calendrier	--------------------------//
echo "<div id='divcalendrier' class='text'>\n";

echo "<form name='form' method='get' action='#'>\n";
echo "<input type='hidden' id='date' name='date' value='$date' />\n";
echo "<input type='hidden' id='site' name='date' value='$site' />\n";
echo "</form>\n";

echo "<table id='tab_titre'>\n";
echo "<tr><td><div class='noprint'>\n";
?>
<div id='pl-calendar' class='datepicker'></div>
<?php
echo "</div></td><td class='titreSemFixe'>\n";
echo "<div class='noprint'>\n";

switch($config['nb_semaine']){
  case 2 :	$type_sem=$semaine%2?"Impaire":"Paire";	$affSem="$type_sem ($semaine)";	break;
  case 3 : 	$type_sem=$semaine3;			$affSem="$type_sem ($semaine)";	break;
  default :	$affSem=$semaine;	break;	
}
echo "<b>Semaine $affSem</b>\n";
echo "</div>";
echo "<div id='semaine_planning'><b>Du ".dateFr($j1)." au ".dateFr($j7)."</b>\n";
echo "</div>\n";
echo "<div id='date_planning'>Planning du $dateAlpha";
if(jour_ferie($date)){
  echo " - <font id='ferie'>".jour_ferie($date)."</font>";
}
echo <<<EOD
  </div>
  <table class='noprint' id='tab_jours'><tr valign='top'>
    <td><a href='index.php?date=$j1'  class='{$class[0]}' >Lundi</a> / </td>
    <td><a href='index.php?date=$j2'  class='{$class[1]}' >Mardi</a> / </td>
    <td><a href='index.php?date=$j3'  class='{$class[2]}' >Mercredi</a> / </td>
    <td><a href='index.php?date=$j4'  class='{$class[3]}' >Jeudi</a> / </td>
    <td><a href='index.php?date=$j5'  class='{$class[4]}' >Vendredi</a> / </td>
    <td><a href='index.php?date=$j6'  class='{$class[5]}' >Samedi</a></td>
EOD;
if($config['Dimanche']){
    echo "<td align='center'> / <a href='index.php?date=$j7'  class='".$class[6]."' >Dimanche</a> </td>";
}

echo "<td> / <a href='index.php?page=planning/poste/semaine.php' class='{$class[7]}' >Semaine</a></td>\n";

echo "</tr></table>";
  
if($config['Multisites-nombre']>1){
  echo "<h3 id='h3-Multisites'>{$config['Multisites-site'.$site]}</h3>";
}
//	---------------------		Affichage des messages d'informations		-----------------//
echo "<div id='messages_infos'>\n";
echo "<marquee>\n";
echo $messages_infos;
echo "</marquee>\n";
echo "</div>";

echo "</td><td id='td_boutons'>\n";

//	----------------------------	Récupération des postes		-----------------------------//
$postes=Array();
$db=new db();
$db->select2("postes","*","1","ORDER BY `id`");
if($db->result){
  foreach($db->result as $elem){
    $postes[$elem['id']]=Array("nom"=>$elem['nom'],"etage"=>$elem['etage'],"obligatoire"=>$elem['obligatoire'],"categories"=>is_serialized($elem['categories'])?unserialize($elem['categories']):array());
  }
}
//	-----------------------		FIN Récupération des postes	-----------------------------//

// Vérifie si Catégorie A en fin de service si admin et config CatAFinDeService
if($autorisation and $config['CatAFinDeService']){
  echo "<div id='pl-verif-categorie-A'></div>\n";
}

echo "<div id='validation'>\n";
if($autorisation){
  $display1=$verrou?null:"display:none";
  $display2=$verrou?"display:none":null;

  echo "<div class='pl-validation' style='$display1'><u>Validation</u><br/>$perso2 $date_validation2 $heure_validation2</div>\n";
  echo "<span id='icon-lock' class='pl-icon pl-icon-lock pointer' data-date='$date' data-site='$site' title='Déverrouiller le planning' style='$display1'></span></a>\n";
  echo "<span id='icon-unlock' class='pl-icon pl-icon-unlock pointer' data-date='$date' data-site='$site' title='Verrouiller le planning' style='$display2'></span></a>\n";
}

if($autorisation){
  echo "<a href='javascript:popup(\"planning/poste/enregistrer.php&date=$date&site=$site\",500,270);' title='Enregistrer comme modèle'><span class='pl-icon pl-icon-save'></span></a>";
  if(!$verrou){
    echo "<a href='javascript:popup(\"planning/poste/importer.php&date=$date&site=$site\",500,270);' title='Importer un modèle'><span class='pl-icon pl-icon-open'></span></a>";
    echo "<a href='javascript:popup(\"planning/poste/supprimer.php&date=$date&site=$site\",500,200);' title='Supprimer le planning'><span class='pl-icon pl-icon-drop'></span></a>";
  }
}
if($verrou){
  if(!$autorisation){
    echo "<div class='pl-validation'><u>Validation</u><br/>$perso2 $date_validation2 $heure_validation2</div>\n";
  }
  echo "<a href='javascript:print();' title='Imprimer le planning'><span class='pl-icon pl-icon-printer'></span></a>\n";
}

echo "<a href='index.php?date=$date&amp;site=$site' title='Actualiser'><span class='pl-icon pl-icon-refresh'></a>\n";
echo "</div>\n";

echo "<div id='planningTips'>&nbsp;</div>";
echo "</td></tr>\n";

//----------------------	FIN Verrouillage du planning		-----------------------//
echo "</table></div>\n";

//		---------------		FIN Affichage du titre et du calendrier		--------------------------//
//		---------------		Choix du tableau	-----------------------------//	
$db=new db();
$db->select2("pl_poste_tab_affect","tableau",array("date"=>$date, "site"=>$site));

if(!$db->result[0]['tableau'] and !$tableau and !$groupe and $autorisation){
  $db=new db();
  $db->select2("pl_poste_tab","*","1","order by `nom` DESC");
  if($db->result){
    echo <<<EOD
    <div id='choix_tableaux'>
    <b>Choisissez un tableau pour le $dateAlpha</b><br/>
    <form name='form' action='index.php' method='get'>
    <input type='hidden' name='page' value='planning/poste/index.php' />
    <input type='hidden' name='site' value='$site' />
    <table>
    <tr><td>Choix d'un tableau : </td>
      <td>
      <select name='tableau'>
      <option value=''>&nbsp;</option>
EOD;
      foreach($db->result as $elem){
	if($elem['site']==$site or $config['Multisites-nombre']<2){
	  echo "<option value='{$elem['tableau']}'>{$elem['nom']}</option>\n";
	}
      }
      echo <<<EOD
      </select></td>
      <td><input type='submit' value='Valider' /></td></tr>
    </table>
    </form>
EOD;
    if($pasDeDonneesSemaine and $groupes){
      echo <<<EOD
      <br/><br/><b>OU un groupe de tableaux pour la semaine $semaine</b><br/>
      <form name='form' action='index.php' method='get'>
      <input type='hidden' name='page' value='planning/poste/index.php' />
      <input type='hidden' name='site' value='$site' />
      <table>
      <tr><td>Choix d'un groupe : </td>
	<td><select name='groupe'>
	<option value=''>&nbsp;</option>
EOD;
	foreach($groupes as $elem){
	  if($elem['site']==$site or $config['Multisites-nombre']<2){
	    echo "<option value='{$elem['id']}'>{$elem['nom']}</option>\n";
	  }
	}
	echo <<<EOD
	</select></td>
	<td><input type='submit' value='Valider' /></td></tr>
      </table>
      </form>
EOD;
    }
  }
  echo "</div>\n";
  include "include/footer.php";
  exit;
}
elseif($groupe and $autorisation){	//	Si Groupe en argument
  $t=new tableau();
  $t->fetchGroup($groupe);
  $groupeTab=$t->elements;
  $tmp=array();
  $tmp[$dates[0]]=array($dates[0],$groupeTab['Lundi']);
  $tmp[$dates[1]]=array($dates[1],$groupeTab['Mardi']);
  $tmp[$dates[2]]=array($dates[2],$groupeTab['Mercredi']);
  $tmp[$dates[3]]=array($dates[3],$groupeTab['Jeudi']);
  $tmp[$dates[4]]=array($dates[4],$groupeTab['Vendredi']);
  $tmp[$dates[5]]=array($dates[5],$groupeTab['Samedi']);
  if(array_key_exists("Dimanche",$groupeTab)){
    $tmp[$dates[6]]=array($dates[6],$groupeTab['Dimanche']);
  }
  foreach($tmp as $elem){
    $db=new db();
    $db->delete2("pl_poste_tab_affect",array("date"=>$elem[0], "site"=>$site));
    $db=new db();
    $db->insert2("pl_poste_tab_affect",array("date"=>$elem[0], "tableau"=>$elem[1], "site"=>$site));
  }
  $tab=$tmp[$date][1];

}
elseif($tableau and $autorisation){	//	Si tableau en argument
  $tab=$tableau;
  $db=new db();
  $db->delete2("pl_poste_tab_affect", array("date"=>$date, "site"=>$site));
  $db=new db();
  $db->insert2("pl_poste_tab_affect",array("date"=>$date, "tableau"=>$tab, "site"=>$site));
}
else{
  $tab=$db->result[0]['tableau'];
}
if(!$tab){
  echo "Le planning n'est pas validé.\n";
  include "include/footer.php";
  exit;
}

//-------------------------------	FIN Choix du tableau	-----------------------------//	
//-------------------------------	Vérification si le planning semaine fixe est validé	------------------//

// Div planning-data : permet de transmettre les valeurs $verrou et $autorisation à la fonction affichant le menudiv
// data-validation pour les fonctions refresh_poste et verrouillage du planning
// Lignes vides pour l'affichage ou non des lignes vides au chargement de la page et après validation (selon la config)

$lignesVides=$config['Planning-lignesVides'];

echo "<div id='planning-data' data-verrou='$verrou' data-autorisation='$autorisation' data-validation='$validation2' 
  data-lignesVides='$lignesVides' data-sr-debut='{$config['Planning-SR-debut']}' data-sr-fin='{$config['Planning-SR-fin']}' 
  style='display:none;'>&nbsp;</div>\n";

// Actualisation du planning si validé et mis à jour depuis un autre poste
if($verrou){
  echo "<script type='text/JavaScript'>refresh_poste();</script>";
}

if(!$verrou and !$autorisation){
  echo "<br/><br/><font color='red'>Le planning du $dateFr n'est pas validé !</font><br/>\n";
  include "include/footer.php";
  exit;
}
else{
  //--------------	Recherche des infos cellules	------------//
  // Toutes les infos seront stockées danx un tableau et utilisées par les fonctions cellules_postes
  $db=new db();
  $db->selectInnerJoin(array("pl_poste","perso_id"),array("personnel","id"),
    array("perso_id","debut","fin","poste","absent","supprime"),
    array("nom","prenom","statut","service"),
    array("date"=>$date, "site"=>$site),
    array(),
    "ORDER BY `{$dbprefix}personnel`.`nom`, `{$dbprefix}personnel`.`prenom`");

  global $cellules;
  $cellules=$db->result;

  // Informations sur les congés
  if(in_array("conges",$plugins)){
    include "plugins/conges/planning_cellules.php";
  }
  //--------------	FIN Recherche des infos cellules	------------//
  
// Recherche des heures des service public à efectuer par semaine pour chaque agents

/*
// Recherche des heures de SP des agents pour cette semaine
if($autorisation){
  // Recherche si les tableaux contenant les heures de SP existe
  $db=new db();
  $db->select2("heures_SP","*",array("semaine"=>$j1));
  $heuresSPUpdate=0;
  if($db->result){
    $heuresSPUpdate=$db->result[0]["update_time"];
    $heuresSP=json_decode((html_entity_decode($db->result[0]["heures"],ENT_QUOTES|ENT_IGNORE,"utf-8")));
  }

  // Recherche des heures de SP avec le module planningHebdo
  if($config['PlanningHebdo']){
    require_once("planningHebdo/class.planningHebdo.php");

    // Vérifie si la table planningHebdo a été mise à jour depuis le dernier calcul
    $p=new planningHebdo();
    $pHUpdate=strtotime($p->update_time());
    
    // Si la table planningHebdo a été modifiée depuis la Création du tableaux des heures
    // Ou si le tableau des heures n'a pas été créé ($heuresSPUpdate=0), on le (re)fait.
    if($pHUpdate>$heuresSPUpdate){
      $heuresSP=array();
    
      // Recherche de tous les agents pouvant faire du service public
      $p=new personnel();
      $p->fetch("nom","Actif");
      if(!empty($p->elements)){
	// Pour chaque agents
	foreach($p->elements as $key1 => $value1){
	  $heuresSP[$key1]=$value1["heuresHebdo"];

	  if(strpos($value1["heuresHebdo"],"%")){
	    $minutesHebdo=0;
	    $ph=new planningHebdo();
	    $ph->debut=$j1;
	    $ph->fin=$j7;
	    $ph->valide=true;
	    $ph->fetch();
	    if($ph->elements and !empty($ph->elements)){
	      // Calcul des heures depuis les plannings de présence
	      // Pour chaque jour de la semaine
	      foreach($dates as $key2 => $jour){
		// On cherche le planning de présence valable pour chaque journée
		foreach($ph->elements as $edt){
		  if($edt['perso_id']==$value1["id"]){
		    // Planning de présence trouvé
		    if($jour>=$edt['debut'] and $jour<=$edt['fin']){
		      // $pause = true si pause détectée le midi
		      $pause=false;
		      // Offset : pour semaines 1,2,3 ...
		      $offset=($semaine3*7)-7;
		      $key3=$key2+$offset;
		      // Si heure de début et de fin de matiné
		      if(array_key_exists($key3,$edt['temps']) and $edt['temps'][$key3][0] and $edt['temps'][$key3][1]){
			$minutesHebdo+=diff_heures($edt['temps'][$key3][0],$edt['temps'][$key3][1],"minutes");
			$pause=true;
		      }
		      // Si heure de début et de fin d'après midi
		      if(array_key_exists($key3,$edt['temps']) and $edt['temps'][$key3][2] and $edt['temps'][$key3][3]){
			$minutesHebdo+=diff_heures($edt['temps'][$key3][2],$edt['temps'][$key3][3],"minutes");
			$pause=true;
		      }
		      // Si pas de pause le midi
		      if(!$pause){
			// Et heure de début et de fin de journée
			if(array_key_exists($key3,$edt['temps']) and $edt['temps'][$key3][0] and $edt['temps'][$key3][3]){
			  $minutesHebdo+=diff_heures($edt['temps'][$key3][0],$edt['temps'][$key3][3],"minutes");
			}
		      }
		    }
		  }
		}
	      }
	    }

	    $heuresRelles=$minutesHebdo/60;
	    // On applique le pourcentage
	    $pourcent=(float) str_replace("%",null,$value1["heuresHebdo"]);
	    $heuresRelles=$heuresRelles*$pourcent/100;
	    $heuresSP[$key1]=$heuresRelles;
	  }
	}
      }
      
      // Enregistrement des horaires dans la base de données
      $db=new db();
      $db->delete2("heures_SP",array("semaine"=>$j1));
      $db=new db();
      $db->insert2("heures_SP",array("semaine"=>$j1,"update_time"=>time(),"heures"=>json_encode($heuresSP)));
    }

  // Recherche des heures de SP sans le module planningHebdo
  }else{
    // Vérifie si la table personnel a été mise à jour depuis le dernier calcul
    $p=new personnel();
    $pUpdate=strtotime($p->update_time());

    // Si la table personnel a été modifiée depuis la Création du tableaux des heures
    // Ou si le tableau des heures n'a pas été créé ($heuresSPUpdate=0), on le (re)fait.
    if($pUpdate>$heuresSPUpdate){
      $heuresSP=array();
      $p=new personnel();
      $p->fetch("nom","Actif");
      if(!empty($p->elements)){
	// Pour chaque agents
	foreach($p->elements as $key1 => $value1){
	  $heuresSP[$key1]=$value1["heuresHebdo"];

	  if(strpos($value1["heuresHebdo"],"%")){
	    $minutesHebdo=0;
	    if($value1['temps'] and is_serialized($value1['temps'])){
	      $temps=unserialize($value1['temps']);

	      // Calcul des heures
	      // Pour chaque jour de la semaine
	      foreach($dates as $key2 => $jour){
		// $pause = true si pause détectée le midi
		$pause=false;
		// Offset : pour semaines 1,2,3 ...
		$offset=($semaine3*7)-7;
		$key3=$key2+$offset;
		// Si heure de début et de fin de matiné
		if(array_key_exists($key3,$temps) and $temps[$key3][0] and $temps[$key3][1]){
		  $minutesHebdo+=diff_heures($temps[$key3][0],$temps[$key3][1],"minutes");
		  $pause=true;
		}
		// Si heure de début et de fin d'après midi
		if(array_key_exists($key3,$temps) and $temps[$key3][2] and $temps[$key3][3]){
		  $minutesHebdo+=diff_heures($temps[$key3][2],$temps[$key3][3],"minutes");
		  $pause=true;
		}
		// Si pas de pause le midi
		if(!$pause){
		  // Et heure de début et de fin de journée
		  if(array_key_exists($key3,$temps) and $temps[$key3][0] and $temps[$key3][3]){
		    $minutesHebdo+=diff_heures($temps[$key3][0],$temps[$key3][3],"minutes");
		  }
		}
	      }
	    }

	    $heuresRelles=$minutesHebdo/60;
	    // On applique le pourcentage
	    $pourcent=(float) str_replace("%",null,$value1["heuresHebdo"]);
	    $heuresRelles=$heuresRelles*$pourcent/100;
	    $heuresSP[$key1]=$heuresRelles;
	  }
	}
      }

      // Enregistrement des horaires dans la base de données
      $db=new db();
      $db->delete2("heures_SP",array("semaine"=>$j1));
      $db=new db();
      $db->insert2("heures_SP",array("semaine"=>$j1,"update_time"=>time(),"heures"=>json_encode($heuresSP)));
    }
  }
  $_SESSION['oups']['heuresSP'] = (array) $heuresSP;
}
*/

  //	------------		Affichage du tableau			--------------------//
  //	Lignes de separation
  $db=new db();
  $db->select2("lignes");
  if($db->result){
    foreach($db->result as $elem){
      $lignes_sep[$elem['id']]=$elem['nom'];
    }
  }

  // Récupération de la structure du tableau
  $t=new tableau();
  $t->id=$tab;
  $t->get();
  $tabs=$t->elements;

  // affichage du tableau :
  // affichage de la lignes des horaires
  echo "<div id='tableau'>\n";
  echo "<table id='tabsemaine1' cellspacing='0' cellpadding='0' class='text tabsemaine1'>\n";
  $k=0;
  foreach($tabs as $tab){
    //		Lignes horaires
    echo "<tr class='tr_horaires'>\n";
    echo "<td class='td_postes'>{$tab['titre']}</td>\n";
    $colspan=0;
    foreach($tab['horaires'] as $horaires){
      echo "<td colspan='".nb30($horaires['debut'],$horaires['fin'])."'>".heure3($horaires['debut'])."-".heure3($horaires['fin'])."</td>";
      $colspan+=nb30($horaires['debut'],$horaires['fin']);
    }
    echo "</tr>\n";
    
    //	Lignes postes et grandes lignes
    foreach($tab['lignes'] as $ligne){
      // Regardons si la ligne est vide afin de ne pas l'afficher si $config['Planning-lignes-vides']=0
      $displayTR=null;
      if(!$config['Planning-lignesVides'] and $verrou and isAnEmptyLine($ligne['poste'])){
	$displayTR="style='display:none;'";
      }

      // Lignes postes
      if($ligne['type']=="poste" and $ligne['poste']){
	// Classe de la première cellule en fonction du type de poste (obligatoire ou de renfort)
	$classTD=$postes[$ligne['poste']]['obligatoire']=="Obligatoire"?"td_obligatoire":"td_renfort";

	// Classe de la ligne en fonction des catégories requises par le poste
	$classTR=array();
	if(!empty($postes[$ligne['poste']]['categories'])){
	  foreach($postes[$ligne['poste']]['categories'] as $cat){
	    if(array_key_exists($cat,$categories)){
	      $classTR[]="tr_".str_replace(" ","",removeAccents(html_entity_decode($categories[$cat],ENT_QUOTES|ENT_IGNORE,"UTF-8")));
	    }
	  }
	}
	$classTR=join(" ",$classTR);

	// Affichage de la ligne
	echo "<tr class='pl-line $classTR' $displayTR >\n";
	echo "<td class='td_postes $classTD'>{$postes[$ligne['poste']]['nom']}";
	// Affichage ou non des étages
	if($config['Affichage-etages'] and $postes[$ligne['poste']]['etage']){
	  echo " ({$postes[$ligne['poste']]['etage']})";
	}
	echo "</td>\n";
	$i=1;
	foreach($tab['horaires'] as $horaires){
	  // recherche des infos à afficher dans chaque cellule 
	  // Cellules grisées
	  if(in_array("{$ligne['ligne']}_{$i}",$tab['cellules_grises'])){
	    echo "<td colspan='".nb30($horaires['debut'],$horaires['fin'])."' class='cellule_grise'>&nbsp;</td>";
	  }
	  // fonction cellule_poste(date,debut,fin,colspan,affichage,poste,site)
	  else{
	    echo cellule_poste($date,$horaires["debut"],$horaires["fin"],nb30($horaires['debut'],$horaires['fin']),"noms",$ligne['poste'],$site);
	  }
	$i++;
	}
	echo "</tr>\n";
      }
      // Lignes de séparation
      if($ligne['type']=="ligne"){
	echo "<tr class='tr_separation'>\n";
	echo "<td>{$lignes_sep[$ligne['poste']]}</td><td colspan='$colspan'>&nbsp;</td></tr>\n";
      }
    }
    $k++;
  }
  echo "</table>\n";

  // Notes : Affichage
  $p=new planning();
  $p->date=$date;
  $p->site=$site;
  $p->getNotes();
  $notes=$p->notes;
  $notesTextarea=$p->notesTextarea;

  echo <<<EOD
  <div id='pl-notes-div1'>
  $notes
  </div>
EOD;

  // Notes : Modifications
  if($autorisation){
    echo <<<EOD
    <div id='pl-notes-div2' class='noprint'>
    <input type='button' class='ui-button' id='pl-notes-button' value='Ajouter un commentaire' />
    </div>

    <div id="pl-notes-form" title="Notes" class='noprint' style='display:none;'>
      <p class="validateTips">Vous pouvez écrire ici un commentaire qui sera affiché en bas du planning.</p>
      <form>
      <fieldset>
      <textarea id='pl-notes-text'>$notesTextarea</textarea>
      </fieldset>
      </form>
    </div>
EOD;
  }

  // Affichage des absences
  if($config['Absences-planning']){
    $a=new absences();
    $a->valide=true;
    $a->fetch("`nom`,`prenom`,`debut`,`fin`",null,null,$date,$date);
    $absences=$a->elements;

    // Ajout des congés
    if(in_array("conges",$plugins)){
      include "plugins/conges/planning.php";
    }

    // Tri des absences par nom
    usort($absences,"cmp_nom_prenom_debut_fin");

    switch($config['Absences-planning']){
      case "simple" :
	if(!empty($absences)){
	  echo "<h3 style='text-align:left;margin:40px 0 0 0;'>Liste des absents</h3>\n";
	  echo "<table class='tableauStandard'>\n";
	  $class="tr1";
	  foreach($absences as $elem){
	    $heures=null;
	    $debut=null;
	    $fin=null;
	    if($elem['debut']>"$date 00:00:00"){
	      $debut=substr($elem['debut'],-8);
	    }
	    if($elem['fin']<"$date 23:59:59"){
	      $fin=substr($elem['fin'],-8);
	    }
	    if($debut and $fin){
	      $heures="de ".heure2($debut)." à ".heure2($fin);
	    }
	    elseif($debut){
	      $heures="à partir de ".heure2($debut);
	    }
	    elseif($fin){
	      $heures="jusqu'à ".heure2($fin);
	    }

	    $class=$class=="tr1"?"tr2":"tr1";
	    echo "<tr class='$class'><td style='text-align:left;'>{$elem['nom']} {$elem['prenom']} $heures</td></tr>\n";
	  }
	  echo "</table>\n";
	}
	break;

      case "détaillé" :
	if(!empty($absences)){
	  echo "<h3 style='text-align:left;margin:40px 0 0 0;'>Liste des absents</h3>\n";
	  echo "<table id='tablePlanningAbsences' class='CJDataTable' data-sort='[[0],[1]]'><thead>\n";
	  echo "<tr><th>Nom</th><th>Pr&eacute;nom</th>\n";
	  echo "<th class='dataTableDateFR'>D&eacute;but</th>\n";
	  echo "<th class='dataTableDateFR'>Fin</th>\n";
	  echo "<th>Motif</th></tr></thead>\n";
	  echo "<tbody>\n";
	  foreach($absences as $elem){
	    echo "<tr><td>{$elem['nom']}</td><td>{$elem['prenom']}</td>";
	    echo "<td>{$elem['debutAff']}</td><td>{$elem['finAff']}</td>";
	    echo "<td>{$elem['motif']}</td></tr>\n";
	  }
	  echo "</tbody></table>\n";
	}
	break;

      case "absents et présents" :
	// Sélection des agents présents
	$heures=null;
	$presents=array();
	$absents=array(2);	// 2 = Utilisateur "Tout le monde", on le supprime

	// On exclus ceux qui sont absents toute la journée
	if(!empty($absences)){
	  foreach($absences as $elem){
	    if($elem['debut']<=$date." 00:00:00" and $elem['fin']>=$date." 23:59:59"){
	      $absents[]=$elem['perso_id'];
	    }
	  }
	}

	// recherche des personnes à exclure (ne travaillant ce jour)
	$db=new db();
	$dateSQL=$db->escapeString($date);
	$db->select("personnel","*","`actif` LIKE 'Actif' AND (`depart` > $dateSQL OR `depart` = '0000-00-00')","ORDER BY `nom`,`prenom`");

	$verif=true;	// verification des heures des agents
	if(!$config['ctrlHresAgents'] and ($d->position==6 or $d->position==0)){
	  $verif=false; // on ne verifie pas les heures des agents le samedi et le dimanche (Si ctrlHresAgents est desactivé)
	}

	// Si il y a des agents et verification des heures de présences
	if($db->result and $verif){

	  // Si module PlanningHebdo : recherche des plannings correspondant à la date actuelle
	  if($config['PlanningHebdo']){
	    include "planningHebdo/planning.php";
	  }

	  // Pour chaque agent
	  foreach($db->result as $elem){
	    $heures=null;

	    // Récupération du planning de présence
	    $temps=array();

	    // Si module PlanningHebdo : emploi du temps récupéré à partir de planningHebdo
	    if($config['PlanningHebdo']){
	      if(array_key_exists($elem['id'],$tempsPlanningHebdo)){
		$temps=$tempsPlanningHebdo[$elem['id']];
	      }
	    }else{
	      // Emploi du temps récupéré à partir de la table personnel
	      $temps=unserialize($elem['temps']);
	    }

	    $jour=$d->position-1;		// jour de la semaine lundi = 0 ,dimanche = 6
	    if($jour==-1){
	      $jour=6;
	    }

	    // Si semaine paire, position +7 : lundi A = 0 , lundi B = 7 , dimanche B = 13
	    if($config['nb_semaine']=="2" and !($semaine%2)){
	      $jour+=7;
	    }
	    // Si utilisation de 3 plannings hebdo
	    elseif($config['nb_semaine']=="3"){
	      if($semaine3==2){
		$jour+=7;
	      }
	      elseif($semaine3==3){
		$jour+=14;
	      }
	    }

	    // Si l'emploi du temps est renseigné
	    if(!empty($temps) and array_key_exists($jour,$temps)){
	      // S'il y a une heure de début (matin ou midi)
	      if($temps[$jour][0] or $temps[$jour][2]){
		$heures=$temps[$jour];
	      }
	    }

	    // S'il y a des horaires correctement renseignés
	    $siteAgent=null;
	    if($heures and !in_array($elem['id'],$absents)){
	      if($config['Multisites-nombre']>1){
		if(isset($heures[4])){
		  $siteAgent=$config['Multisites-site'.$heures[4]];
		}
	      }
	      $siteAgent=$siteAgent?$siteAgent.", ":null;


	      $horaires=null;
	      if(!$heures[1] and !$heures[2]){		// Pas de pause le midi
		$horaires=heure2($heures[0])." - ".heure2($heures[3]);
	      }
	      elseif(!$heures[2] and !$heures[3]){	// matin seulement
		$horaires=heure2($heures[0])." - ".heure2($heures[1]);
	      }
	      elseif(!$heures[0] and !$heures[1]){	// après midi seulement
		$horaires=heure2($heures[2])." - ".heure2($heures[3]);
	      }
	      else{		// matin et après midi avec pause
		$horaires=heure2($heures[0])." - ".heure2($heures[1])." &amp; ".heure2($heures[2])." - ".heure2($heures[3]);
	      }
	      $presents[]=array("id"=>$elem['id'],"nom"=>$elem['nom']." ".$elem['prenom'],"site"=>$siteAgent,"heures"=>$horaires);
	    }
	  }
	}

	echo "<table class='tableauStandard'>\n";
	echo "<tr><td><h3 style='text-align:left;margin:40px 0 0 0;'>Liste des présents</h3></td>\n";
	if(!empty($absences)){
	  echo "<td><h3 style='text-align:left;margin:40px 0 0 0;'>Liste des absents</h3></td>";
	}
	echo "</tr>\n";

	// Liste des présents
	echo "<tr style='vertical-align:top;'><td>";
	echo "<table cellspacing='0'> ";
	$class="tr1";
	foreach($presents as $elem){
	  $class=$class=="tr1"?"tr2":"tr1";
	  echo "<tr class='$class'><td>{$elem['nom']}</td><td style='padding-left:15px;'>{$elem['site']}{$elem['heures']}</td></tr>\n";
	}
	echo "</table>\n";
	echo "</td>\n";

	// Liste des absents
	echo "<td>";
	echo "<table cellspacing='0'>";
	$class="tr1";
	foreach($absences as $elem){
	  $heures=null;
	  $debut=null;
	  $fin=null;
	  if($elem['debut']>"$date 00:00:00"){
	    $debut=substr($elem['debut'],-8);
	  }
	  if($elem['fin']<"$date 23:59:59"){
	    $fin=substr($elem['fin'],-8);
	  }
	  if($debut and $fin){
	    $heures=", ".heure2($debut)." - ".heure2($fin);
	  }
	  elseif($debut){
	    $heures=" à partir de ".heure2($debut);
	  }
	  elseif($fin){
	    $heures=" jusqu'à ".heure2($fin);
	  }

	  $class=$class=="tr1"?"tr2":"tr1";
	  echo "<tr class='$class'><td>{$elem['nom']} {$elem['prenom']}</td><td style='padding-left:15px;'>{$elem['motif']}{$heures}</td></tr>\n";
	}
	echo "</table>\n";
	echo "</td></tr>\n";
	echo "</table>\n";
	break;

    }
  }
}
					//---------------	FIN Affichage des absences		-----------------//
?>
</div>
</div>