<?php
/*
Planning Biblio, Version 1.8
Licence GNU/GPL (version 2 et au dela)
Voir les fichiers README.md et LICENSE
Copyright (C) 2011-2014 - Jérôme Combes

Fichier : planning/poste/class.planning.php
Création : 16 janvier 2013
Dernière modification : 6 juin 2014
Auteur : Jérôme Combes, jerome@planningbilbio.fr

Description :
Classe planning 

Utilisée par les fichiers du dossier "planning/poste"
*/

// Securité : Traitement pour une reponse Ajax
if(array_key_exists('HTTP_X_REQUESTED_WITH', $_SERVER) and strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'){
  $version="ajax";
}

// Si pas de $version ou pas de reponseAjax => acces direct aux pages de ce dossier => redirection vers la page index.php
if(!$version){
  header("Location: ../../index.php");
}


class planning{
  public $date=null;
  public $site=1;
  public $categorieA=false;
  public $notes=null;
  public $notesTextarea=null;


  // Recherche les agents de catégorie A en fin de service
  public function finDeService(){
    $date=$this->date;
    $site=$this->site;

    // Sélection du tableau utilisé
    $db=new db();
    $db->select("pl_poste_tab_affect","tableau","date='$date' AND site='$site'");
    $tableau=$db->result[0]["tableau"];

    // Sélection de l'heure de fin
    $db=new db();
    $db->select("pl_poste_horaires","MAX(fin) AS maxFin","numero='$tableau'");
    $fin=$db->result[0]["maxFin"];

    // Sélection des agents en fin de service
    $perso_ids=array();
    $db=new db();
    $db->select("pl_poste","perso_id","fin='$fin' and site='$site' and `date`='$date' and supprime='0' and absent='0'");
    if($db->result){
      foreach($db->result as $elem){
	$perso_ids[]=$elem['perso_id'];
      }
    }
    if(empty($perso_ids)){
      return false;
    }
    $perso_ids=join(",",$perso_ids);

    // Sélection des statuts des agents en fin de service
    $statuts=array();
    $db=new db();
    $db->select("personnel","statut","id IN ($perso_ids)");
    if($db->result){
      foreach($db->result as $elem){
	if(in_array($elem['statut'],$statuts)){
	  continue;
	}
	$statuts[]=$elem['statut'];
      }
    }
    if(empty($statuts)){
      return false;
    }
    $statuts=join("','",$statuts);

    // Recherche des statuts de catégorie A parmis les statuts fournis
    $db=new db();
    $db->select("select_statuts","*","valeur IN ('$statuts') AND categorie='1'");
    if($db->result){
      $this->categorieA=true;
    }
  }

  // Affiche la liste des agents dans le menudiv
  public function menudivAfficheAgents($agents,$date,$debut,$fin,$deja,$stat,$cellule_vide,$max_perso,$sr_init,$hide,$deuxSP,$motifExclusion){
    $msg_deja_place="<font style='color:red;font-weight:bold;'>(DP)</font>";
    $msg_deuxSP="<font style='color:red;font-weight:bold;'>(2 SP)</font>";
    $config=$GLOBALS['config'];
    $dbprefix=$config['dbprefix'];
    $d=new datePl($date);
    $j1=$d->dates[0];
    $j7=$d->dates[6];
    $semaine=$d->semaine;
    $semaine3=$d->semaine3;
    $ligneAdd=0;

    if($hide){
      $display="display:none;";
      $groupe_hide=null;
      $addClass="$(this).addClass(\"tr_liste\");";
      $classTrListe="tr_liste";
    }else{
      $display=null;
      $groupe_hide="groupe_tab_hide();";
      $addClass=null;
      $classTrListe=null;
    }

    foreach($agents as $elem){
      $hres_jour=0;
      $hres_sem=0;
      $sr=0;
      $color='black';

      if(!$config['ClasseParService']){
	if($elem['id']==2){		// on retire l'utilisateur "tout le monde"
	  continue;
	}
      }

      $nom=$elem['nom'];
      if($elem['prenom']){
	$nom.=" ".substr($elem['prenom'],0,1).".";
      }


      //			----------------------		Sans repas		------------------------------------------//
      //			(Peut être amélioré : vérifie si l'agent est déjà placé entre 11h30 et 14h30 
      //			mais ne vérfie pas la continuité. Ne marque pas la 2ème cellule en javascript (rafraichissement OK))
      if($config['Planning-sansRepas']){
	if($debut>="11:30:00" and $fin<="14:30:00"){
	  $db_sr=new db();
	  $db_sr->query("SELECT * FROM `{$dbprefix}pl_poste` WHERE `date`='$date' AND `perso_id`='{$elem['id']}' AND `debut` >='11:30:00' AND `fin`<='14:30:00';");
	  if($db_sr->result){
	    $sr=1;
	    $nom.=" (SR)";
	    $color='red';
	  }
	}
      }
	      
      $nom_menu=$nom;
      //			----------------------		Déjà placés		-----------------------------------------------------//
      if($config['Planning-dejaPlace']){
	if(in_array($elem['id'],$deja)){	// Déjà placé pour ce poste
	  $nom_menu.=" ".$msg_deja_place;
	  $color='red';
	}
      }
      //			----------------------		FIN Déjà placés		-----------------------------------------------------//

      // Vérifie si l'agent fera 2 plages de service public de suite
      if($config['Alerte2SP']){
	if(in_array($elem['id'],$deuxSP)){
	  $nom_menu.=" ".$msg_deuxSP;
	  $color='red';
	}
      }

      // Motifs d'indisponibilité
      if(array_key_exists($elem['id'],$motifExclusion)){
	$nom_menu.=" (".join(", ",$motifExclusion[$elem['id']]).")";
      }

      // affihage des heures faites ce jour + les heures de la cellule
      $db_heures = new db();
      $db_heures->query("SELECT `{$dbprefix}pl_poste`.`debut` AS `debut`,`{$dbprefix}pl_poste`.`fin` AS `fin` FROM `{$dbprefix}pl_poste` INNER JOIN `{$dbprefix}postes` ON `{$dbprefix}pl_poste`.`poste`=`{$dbprefix}postes`.`id` WHERE `{$dbprefix}pl_poste`.`perso_id`='{$elem['id']}' AND `{$dbprefix}pl_poste`.`absent`<>'1' AND `{$dbprefix}pl_poste`.`date`='$date' AND `{$dbprefix}postes`.`statistiques`='1';");
      if($stat){ 	// vérifier si le poste est compté dans les stats
	$hres_jour=diff_heures($debut,$fin,"decimal");
      }
      if($db_heures->result){
	foreach($db_heures->result as $hres){
	  $hres_jour=$hres_jour+diff_heures($hres['debut'],$hres['fin'],"decimal");
	}
      }
      
      // affihage des heures faites cette semaine + les heures de la cellule
      $db_heures = new db();
      $db_heures->query("SELECT `{$dbprefix}pl_poste`.`debut` AS `debut`,`{$dbprefix}pl_poste`.`fin` AS `fin` FROM `{$dbprefix}pl_poste` INNER JOIN `{$dbprefix}postes` ON `{$dbprefix}pl_poste`.`poste`=`{$dbprefix}postes`.`id` WHERE `{$dbprefix}pl_poste`.`perso_id`='{$elem['id']}' AND `{$dbprefix}pl_poste`.`absent`<>'1' AND `{$dbprefix}pl_poste`.`date` BETWEEN '$j1' AND '$j7' AND `{$dbprefix}postes`.`statistiques`='1';");

      if($stat){ 	// vérifier si le poste est compté dans les stats
	$hres_sem=diff_heures($debut,$fin,"decimal");
      }
      if($db_heures->result){
	foreach($db_heures->result as $hres){
	  $hres_sem=$hres_sem+diff_heures($hres['debut'],$hres['fin'],"decimal");
	}
      }

      // affihage des heures faites les 4 dernières semaines + les heures de la cellule
      $hres_4sem=0;
      if($config['hres4semaines']){
	$date1=date("Y-m-d",strtotime("-3 weeks",strtotime($j1)));
	$date2=$j7;	// fin de semaine courante
	$db_hres4 = new db();
	$db_hres4->query("SELECT `{$dbprefix}pl_poste`.`debut` AS `debut`,`{$dbprefix}pl_poste`.`fin` AS `fin` FROM `{$dbprefix}pl_poste` INNER JOIN `{$dbprefix}postes` ON `{$dbprefix}pl_poste`.`poste`=`{$dbprefix}postes`.`id` WHERE `{$dbprefix}pl_poste`.`perso_id`='{$elem['id']}' AND `{$dbprefix}pl_poste`.`absent`<>'1' AND `{$dbprefix}pl_poste`.`date` BETWEEN '$date1' AND '$date2' AND `{$dbprefix}postes`.`statistiques`='1';");
	if($stat){ 	// vérifier si le poste est compté dans les stats
	  $hres_4sem=diff_heures($debut,$fin,"decimal");
	}
	if($db_hres4->result){
	  foreach($db_hres4->result as $hres){
	    $hres_4sem=$hres_4sem+diff_heures($hres['debut'],$hres['fin'],"decimal");
	  }
	}
	$hres_4sem=" / <font title='Heures des 4 derni&egrave;res semaines'>$hres_4sem</font>";
      }

      //	Mise en forme de la ligne avec le nom et les heures et la couleur en fonction des heures faites
      $nom_menu.="&nbsp;<font title='Heures du jour'>$hres_jour</font> / ";
      $nom_menu.="<font title='Heures de la semaine'>$hres_sem</font> / ";
      $nom_menu.="<font title='Quota hebdomadaire'>{$elem['heuresHebdo']}</font>";
      $nom_menu.=$hres_4sem;

      if($hres_jour>7)			// plus de 7h:jour : rouge
	$nom_menu="<font style='color:red'>$nom_menu</font>\n";
      elseif(($elem['heuresHebdo']-$hres_sem)<=0.5 and ($hres_sem-$elem['heuresHebdo'])<=0.5)		// 0,5 du quota hebdo : vert
	$nom_menu="<font style='color:green'>$nom_menu</font>\n";
      elseif($hres_sem>$elem['heuresHebdo'])			// plus du quota hebdo : rouge
	$nom_menu="<font style='color:red'>$nom_menu</font>\n";
      
      
      // Classe en fonction du statut et du service
      $class_tmp=array();
      if($elem['statut']){
	$class_tmp[]="statut_".strtolower(removeAccents(str_replace(" ","_",$elem['statut'])));
      }
      if($elem['service']){
	$class_tmp[]="service_".strtolower(removeAccents(str_replace(" ","_",$elem['service'])));
      }
      $classe=empty($class_tmp)?null:join(" ",$class_tmp);

      //	Affichage des lignes
      echo "<tr id='tr{$elem['id']}' style='height:21px;$display' onmouseover='$(this).removeClass();$(this).addClass(\"menudiv-gris\"); $groupe_hide' onmouseout='$(this).removeClass();$addClass' class='$classe $classTrListe'>\n";
      echo "<td style='width:200px;color:$color;' onclick='bataille_navale({$elem['id']},\"$nom\",0,0,\"$classe\");'>";
      echo $nom_menu;

      //	Afficher ici les horaires si besoin
      echo "</td><td style='text-align:right;width:20px'>";
      
      //	Affichage des liens d'ajout et de remplacement
      if(!$cellule_vide and !$max_perso and !$sr and !$sr_init)
	echo "<a href='javascript:bataille_navale(".$elem['id'].",\"$nom\",0,1,\"$classe\");'>+</a>";
      if(!$cellule_vide and !$max_perso)
	echo "&nbsp;<a style='color:red' href='javascript:bataille_navale(".$elem['id'].",\"$nom\",1,1,\"$classe\");'>x</a>&nbsp;";
      echo "</td></tr>\n";
    }

  }

  // Notes
  // Récupère les notes (en bas des plannings)
  public function getNotes(){
    $db=new db();
    $db->select("pl_notes","text","date='{$this->date}' AND site='{$this->site}'");
    if($db->result){
      $notes=$db->result[0]['text'];
      $notes=str_replace("&lt;br/&gt;","<br/>",$notes);
      $this->notes=$notes;
      $this->notesTextarea=str_replace("<br/>","\n",$notes);
    }
  }

  // Insertion, mise à jour des notes
  public function updateNotes(){
    $date=$this->date;
    $site=$this->site;
    $text=$this->notes;

    $db=new db();
    $db->delete2("pl_notes",array("date"=>$date,"site"=>$site));

    $db=new db();
    $db->insert2("pl_notes",array("date"=>$date,"site"=>$site,"text"=>$text));
  }

}
?>
