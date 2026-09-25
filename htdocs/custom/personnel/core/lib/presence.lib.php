<?php
/**
 * Présence du personnel (étape 2 du module Personnel).
 *
 * Enseignants : chaque cours de l'emploi du temps est FAIT PAR DÉFAUT (aucune ligne enregistrée).
 *   - l'enseignant peut déclarer son cours (sujet traité facultatif) ; s'il oublie, le cours reste fait ;
 *   - le surveillant le marque absent ou en retard pendant l'appel : absent = déclaration impossible ;
 *   - la direction enregistre les remplacements (le cours compte pour le remplaçant) et peut tout corriger ;
 *   - chaque correction est gardée (qui, quand, avant, après).
 * Tous les employés : présents par défaut, seules les absences sont enregistrées (journée, demi-journée ou période).
 * Jours sans cours (vacances, fêtes) : aucun cours attendu.
 * Heures du mois : cours prévus, faits, absences, retards, remplacements, heures supplémentaires, et une
 * estimation de la paie selon les règles de la configuration (le calcul définitif se fera dans le module Salaires).
 *
 * Fichier : custom/personnel/core/lib/presence.lib.php
 */

dol_include_once('/classes/core/lib/edt.lib.php');
dol_include_once('/personnel/class/ecole_employe.class.php');
dol_include_once('/personnel/class/ecole_employe_motif.class.php');

/** Présent (cours fait) */
define('PERSONNEL_PRESENT', 0);
/** Absent */
define('PERSONNEL_ABSENT', 1);
/** En retard */
define('PERSONNEL_RETARD', 2);

/* ------------------------------------------------------------------
 * Dates
 * ---------------------------------------------------------------- */

/**
 * Date AAAA-MM-JJ valide, sinon ''.
 *
 * @param  string $s Date
 * @return string
 */
function personnel_date_ok($s)
{
	$s = trim((string) $s);
	if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
		return '';
	}
	return $s;
}

/**
 * Mois AAAA-MM valide, sinon ''.
 *
 * @param  string $s Mois
 * @return string
 */
function personnel_mois_ok($s)
{
	$s = trim((string) $s);
	return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $s) ? $s : '';
}

/**
 * Date du jour AAAA-MM-JJ (fuseau de l'utilisateur).
 *
 * @return string
 */
function personnel_aujourdhui()
{
	return dol_print_date(dol_now(), '%Y-%m-%d', 'tzuserrel');
}

/**
 * Horodatage (midi) d'une date AAAA-MM-JJ.
 *
 * @param  string $d Date
 * @return int
 */
function personnel_date_ts($d)
{
	$p = explode('-', $d);
	return dol_mktime(12, 0, 0, (int) $p[1], (int) $p[2], (int) $p[0]);
}

/**
 * Libellé d'une date : « Lundi 24/09/2026 ».
 *
 * @param  string $d Date AAAA-MM-JJ
 * @return string
 */
function personnel_date_label($d)
{
	global $langs;
	$jours = ecole_jours();
	return $langs->trans($jours[(int) date('N', strtotime($d))]).' '.dol_print_date(personnel_date_ts($d), 'day');
}

/**
 * Libellé d'un mois : « Octobre 2026 ».
 *
 * @param  string $mois AAAA-MM
 * @return string
 */
function personnel_mois_label($mois)
{
	global $langs;
	$p = explode('-', $mois);
	return ucfirst($langs->trans('Month'.sprintf('%02d', (int) $p[1]))).' '.$p[0];
}

/**
 * Premier et dernier jour d'un mois.
 *
 * @param  string $mois AAAA-MM
 * @return array{0:string,1:string}
 */
function personnel_mois_bornes($mois)
{
	$t = strtotime($mois.'-01 12:00:00');
	return array(date('Y-m-01', $t), date('Y-m-t', $t));
}

/**
 * Dates d'une période (au plus 62 jours) : 'AAAA-MM-JJ' => numéro du jour de la semaine (1 = lundi).
 *
 * @param  string $du Début AAAA-MM-JJ
 * @param  string $au Fin AAAA-MM-JJ
 * @return array<string,int>
 */
function personnel_dates($du, $au)
{
	$out = array();
	if ($du === '' || $au === '' || $au < $du) {
		return $out;
	}
	$t = strtotime($du.' 12:00:00');
	$tend = strtotime($au.' 12:00:00');
	for ($n = 0; $t <= $tend && $n < 400; $n++, $t += 86400) {
		$out[date('Y-m-d', $t)] = (int) date('N', $t);
	}
	return $out;
}

/**
 * Jours sans cours (vacances, fêtes) d'une période : 'AAAA-MM-JJ' => libellé.
 *
 * @param  DoliDB $db Handler base
 * @param  string $du Début
 * @param  string $au Fin
 * @return array<string,string>
 */
function personnel_jours_sans_cours($db, $du, $au)
{
	static $cache = array();
	$key = $du.'|'.$au;
	if (isset($cache[$key])) {
		return $cache[$key];
	}
	$out = array();
	$sql = "SELECT label_fr, label_ar, date_debut, date_fin FROM ".$db->prefix()."ecole_jour_sans_cours WHERE entity IN (".getEntity('ecole_jour_sans_cours').")";
	$sql .= " AND status = 1 AND date_debut <= '".$db->escape($au)."' AND date_fin >= '".$db->escape($du)."'";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$d1 = max($du, substr($o->date_debut, 0, 10));
		$d2 = min($au, substr($o->date_fin, 0, 10));
		foreach (personnel_dates($d1, $d2) as $d => $j) {
			$out[$d] = ecole_label($o);
		}
	}
	return $cache[$key] = $out;
}

/* ------------------------------------------------------------------
 * États des cours
 * ---------------------------------------------------------------- */

/**
 * Matricule d'un employé (avec cache).
 *
 * @param  DoliDB $db Handler base
 * @param  int    $id Employé
 * @return string
 */
function personnel_employe_ref($db, $id)
{
	static $cache = array();
	if ((int) $id <= 0) {
		return '';
	}
	if (!isset($cache[$id])) {
		$resql = $db->query("SELECT ref FROM ".$db->prefix()."ecole_employe WHERE rowid = ".((int) $id));
		$cache[$id] = ($resql && ($o = $db->fetch_object($resql))) ? $o->ref : '';
	}
	return $cache[$id];
}

/**
 * Nom d'un employé dans la langue de l'utilisateur (avec cache).
 *
 * @param  DoliDB $db Handler base
 * @param  int    $id Employé
 * @return string
 */
function personnel_employe_nom($db, $id)
{
	static $cache = array();
	if ((int) $id <= 0) {
		return '';
	}
	if (!isset($cache[$id])) {
		$resql = $db->query("SELECT nom_fr, nom_ar FROM ".$db->prefix()."ecole_employe WHERE rowid = ".((int) $id));
		$cache[$id] = ($resql && ($o = $db->fetch_object($resql))) ? ecole_label($o) : '';
	}
	return $cache[$id];
}

/**
 * Code court d'un état (historique des corrections) : P (présent), D (déclaré), A (absent), R|minutes,
 * suivi de « >matricule » si un remplaçant a fait le cours.
 *
 * @param  DoliDB      $db Handler base
 * @param  object|null $c  Ligne de ecole_cours_prof (null = présent par défaut)
 * @return string
 */
function personnel_cours_code($db, $c)
{
	if (!$c) {
		return 'P';
	}
	$e = (int) $c->etat;
	$code = ($e === PERSONNEL_ABSENT) ? 'A' : (($e === PERSONNEL_RETARD) ? 'R|'.((int) $c->minutes_retard) : ((int) $c->declare_cours ? 'D' : 'P'));
	if ((int) $c->fk_remplacant > 0) {
		$code .= '>'.personnel_employe_ref($db, (int) $c->fk_remplacant);
	}
	return $code;
}

/**
 * Libellé d'un code d'état : « Présent », « Cours déclaré », « Absent », « En retard (15 min) », « … — remplacé par P00003 ».
 *
 * @param  string $code Code
 * @return string
 */
function personnel_cours_code_label($code)
{
	global $langs;
	$parts = explode('>', (string) $code, 2);
	$p = explode('|', $parts[0]);
	if ($p[0] === 'A') {
		$l = $langs->transnoentities('Absent');
	} elseif ($p[0] === 'R') {
		$l = $langs->transnoentities('EnRetardMinutes', isset($p[1]) ? (int) $p[1] : 0);
	} elseif ($p[0] === 'D') {
		$l = $langs->transnoentities('CoursDeclare');
	} else {
		$l = $langs->transnoentities('Present');
	}
	if (isset($parts[1]) && $parts[1] !== '') {
		$l .= ' — '.$langs->transnoentities('RemplacePar', $parts[1]);
	}
	return $l;
}

/**
 * Badge coloré de l'état d'un cours.
 *
 * @param  string $etat present | declare | absent | retard | avenir | sanscours | suspendu | remplacement
 * @param  string $info Texte ajouté (minutes, remplaçant...)
 * @return string       HTML
 */
function personnel_etat_badge($etat, $info = '')
{
	global $langs;
	$map = array(
		'present' => array('badge-status4', 'Present'),
		'declare' => array('badge-status4', 'CoursDeclare'),
		'absent' => array('badge-danger', 'Absent'),
		'retard' => array('badge-warning', 'EnRetard'),
		'avenir' => array('badge-status0', 'AVenir'),
		'sanscours' => array('badge-status0', 'JourSansCours'),
		'suspendu' => array('badge-status7', 'StatutSuspenduConge'),
		'remplacement' => array('badge-status1', 'Remplacement'),
	);
	$m = isset($map[$etat]) ? $map[$etat] : array('badge-status0', $etat);
	$icon = ($etat === 'declare') ? img_picto('', 'fa-check-double', 'class="pictofixedwidth"') : '';
	return '<span class="badge '.$m[0].'">'.$icon.dol_escape_htmltag($langs->trans($m[1]).($info !== '' ? ' '.$info : '')).'</span>';
}

/* ------------------------------------------------------------------
 * Lecture et enregistrement de la présence à un cours
 * ---------------------------------------------------------------- */

/**
 * Colonnes lues dans ecole_cours_prof.
 *
 * @return string
 */
function personnel_cours_cols()
{
	return "rowid, date_cours, fk_creneau, fk_classe, fk_matiere, fk_employe, etat, minutes_retard, declare_cours, date_declaration, fk_user_declare, sujet, fk_remplacant, justifiee, fk_motif, note, source, date_creation, fk_user_creat, fk_user_modif, tms";
}

/**
 * Présence enregistrée pour un cours (null = aucune ligne : cours fait par défaut).
 *
 * @param  DoliDB $db         Handler base
 * @param  string $date       Date AAAA-MM-JJ
 * @param  int    $fk_creneau Créneau
 * @param  int    $fk_classe  Classe
 * @return object|null
 */
function personnel_cours_fetch($db, $date, $fk_creneau, $fk_classe)
{
	$sql = "SELECT ".personnel_cours_cols()." FROM ".$db->prefix()."ecole_cours_prof WHERE entity IN (".getEntity('ecole_cours_prof').")";
	$sql .= " AND date_cours = '".$db->escape($date)."' AND fk_creneau = ".((int) $fk_creneau)." AND fk_classe = ".((int) $fk_classe);
	$resql = $db->query($sql);
	return ($resql && ($o = $db->fetch_object($resql))) ? $o : null;
}

/**
 * Cours de l'emploi du temps d'une classe à une date et un créneau (matière, enseignant, employé relié).
 *
 * @param  DoliDB $db         Handler base
 * @param  string $date       Date AAAA-MM-JJ
 * @param  int    $fk_creneau Créneau
 * @param  int    $fk_classe  Classe
 * @return object|null        (fk_matiere, fk_user, fk_employe, heure_debut, heure_fin)
 */
function personnel_cours_edt($db, $date, $fk_creneau, $fk_classe)
{
	$p = $db->prefix();
	$sql = "SELECT e.fk_matiere, e.fk_user, emp.rowid as fk_employe, c.heure_debut, c.heure_fin FROM ".$p."ecole_edt_cours e";
	$sql .= " INNER JOIN ".$p."ecole_creneau c ON c.rowid = e.fk_creneau";
	$sql .= " LEFT JOIN ".$p."ecole_employe emp ON emp.fk_user = e.fk_user AND e.fk_user IS NOT NULL";
	$sql .= " WHERE e.fk_classe = ".((int) $fk_classe)." AND e.fk_creneau = ".((int) $fk_creneau)." AND e.jour = ".((int) date('N', strtotime($date)));
	$resql = $db->query($sql);
	return ($resql && ($o = $db->fetch_object($resql))) ? $o : null;
}

/**
 * Enregistre la présence de l'enseignant à un cours (création ou correction). Une correction garde
 * l'ancien et le nouvel état dans l'historique. Revenir à « présent » sans déclaration, sans remplaçant
 * ni remarque supprime la ligne (retour à l'état par défaut).
 *
 * @param  DoliDB $db         Handler base
 * @param  User   $user       Utilisateur
 * @param  string $date       Date AAAA-MM-JJ
 * @param  int    $fk_creneau Créneau
 * @param  int    $fk_classe  Classe
 * @param  array  $data       Valeurs à changer : etat, minutes_retard, declare_cours, sujet, fk_remplacant, justifiee, fk_motif, note
 * @param  string $source     appel | declaration | direction
 * @param  string $error      Message d'erreur (sortie)
 * @return int                Id de la ligne (0 si revenue à l'état par défaut), <0 si erreur
 */
function personnel_cours_enregistrer($db, $user, $date, $fk_creneau, $fk_classe, $data, $source, &$error)
{
	global $conf, $langs;
	$p = $db->prefix();
	$error = '';
	$edt = personnel_cours_edt($db, $date, $fk_creneau, $fk_classe);
	$old = personnel_cours_fetch($db, $date, $fk_creneau, $fk_classe);
	if (!$edt && !$old) {
		$error = $langs->trans('ErrorPasDeCoursEdt');
		return -1;
	}

	// Nouvel état = ancien état (ou défaut) + valeurs changées
	$n = $old ? clone $old : (object) array('etat' => PERSONNEL_PRESENT, 'minutes_retard' => null, 'declare_cours' => 0, 'sujet' => null, 'fk_remplacant' => null,
		'justifiee' => 0, 'fk_motif' => null, 'note' => null, 'fk_employe' => $edt ? $edt->fk_employe : null, 'fk_matiere' => $edt ? $edt->fk_matiere : null);
	foreach (array('etat', 'minutes_retard', 'declare_cours', 'sujet', 'fk_remplacant', 'justifiee', 'fk_motif', 'note') as $k) {
		if (array_key_exists($k, $data)) {
			$n->$k = $data[$k];
		}
	}
	$n->etat = in_array((int) $n->etat, array(PERSONNEL_PRESENT, PERSONNEL_ABSENT, PERSONNEL_RETARD), true) ? (int) $n->etat : PERSONNEL_PRESENT;
	$n->minutes_retard = ((int) $n->etat === PERSONNEL_RETARD) ? max(1, min(600, (int) $n->minutes_retard)) : null;
	if ((int) $n->etat === PERSONNEL_ABSENT) {
		$n->declare_cours = 0; // absent : pas de déclaration
	} else {
		$n->fk_remplacant = null; // un remplaçant seulement si le titulaire est absent
		$n->justifiee = ((int) $n->etat === PERSONNEL_RETARD) ? (int) $n->justifiee : 0;
		$n->fk_motif = ((int) $n->etat === PERSONNEL_RETARD) ? $n->fk_motif : null;
	}
	if ((int) $n->fk_remplacant > 0 && (int) $n->fk_remplacant === (int) $n->fk_employe) {
		$error = $langs->trans('ErrorRemplacantTitulaire');
		return -1;
	}
	$avant = personnel_cours_code($db, $old);
	$apres = personnel_cours_code($db, $n);
	$vide = ((int) $n->etat === PERSONNEL_PRESENT && empty($n->declare_cours) && trim((string) $n->sujet) === '' && trim((string) $n->note) === '');
	if ($vide && (int) $n->fk_employe > 0) {
		// Présent malgré une absence du personnel saisie ce jour-là : la ligne est gardée (elle l'emporte sur l'absence)
		$heure = $edt ? $edt->heure_debut : '';
		$vide = !personnel_absence_couvre(personnel_absences_periode($db, $date, $date, (int) $n->fk_employe), $date, $heure);
	}

	$now = "'".$db->idate(dol_now())."'";
	$db->begin();
	if ($old && $vide) {
		$ok = (bool) $db->query("DELETE FROM ".$p."ecole_cours_prof WHERE rowid = ".((int) $old->rowid));
		$id = 0;
	} elseif ($old) {
		$sql = "UPDATE ".$p."ecole_cours_prof SET etat = ".((int) $n->etat).", minutes_retard = ".($n->minutes_retard ? (int) $n->minutes_retard : "NULL");
		$sql .= ", declare_cours = ".((int) $n->declare_cours ? 1 : 0).", sujet = ".(trim((string) $n->sujet) !== '' ? "'".$db->escape(dol_trunc(trim($n->sujet), 250, 'right', 'UTF-8', 1))."'" : "NULL");
		if ((int) $n->declare_cours && !(int) $old->declare_cours) {
			$sql .= ", date_declaration = ".$now.", fk_user_declare = ".((int) $user->id);
		}
		$sql .= ", fk_remplacant = ".((int) $n->fk_remplacant > 0 ? (int) $n->fk_remplacant : "NULL").", justifiee = ".((int) $n->justifiee ? 1 : 0);
		$sql .= ", fk_motif = ".((int) $n->fk_motif > 0 ? (int) $n->fk_motif : "NULL").", note = ".(trim((string) $n->note) !== '' ? "'".$db->escape(dol_trunc(trim($n->note), 250, 'right', 'UTF-8', 1))."'" : "NULL");
		$sql .= ", source = '".$db->escape($source)."', fk_user_modif = ".((int) $user->id)." WHERE rowid = ".((int) $old->rowid);
		$ok = (bool) $db->query($sql);
		$id = (int) $old->rowid;
	} elseif (!$vide) {
		$sql = "INSERT INTO ".$p."ecole_cours_prof (entity, date_cours, fk_creneau, fk_classe, fk_matiere, fk_employe, etat, minutes_retard, declare_cours, date_declaration, fk_user_declare,";
		$sql .= " sujet, fk_remplacant, justifiee, fk_motif, note, source, date_creation, fk_user_creat) VALUES (".((int) $conf->entity).", '".$db->escape($date)."', ".((int) $fk_creneau).", ".((int) $fk_classe);
		$sql .= ", ".((int) $n->fk_matiere > 0 ? (int) $n->fk_matiere : "NULL").", ".((int) $n->fk_employe > 0 ? (int) $n->fk_employe : "NULL").", ".((int) $n->etat);
		$sql .= ", ".($n->minutes_retard ? (int) $n->minutes_retard : "NULL").", ".((int) $n->declare_cours ? 1 : 0).", ".((int) $n->declare_cours ? $now : "NULL").", ".((int) $n->declare_cours ? (int) $user->id : "NULL");
		$sql .= ", ".(trim((string) $n->sujet) !== '' ? "'".$db->escape(dol_trunc(trim($n->sujet), 250, 'right', 'UTF-8', 1))."'" : "NULL").", ".((int) $n->fk_remplacant > 0 ? (int) $n->fk_remplacant : "NULL");
		$sql .= ", ".((int) $n->justifiee ? 1 : 0).", ".((int) $n->fk_motif > 0 ? (int) $n->fk_motif : "NULL").", ".(trim((string) $n->note) !== '' ? "'".$db->escape(dol_trunc(trim($n->note), 250, 'right', 'UTF-8', 1))."'" : "NULL");
		$sql .= ", '".$db->escape($source)."', ".$now.", ".((int) $user->id).")";
		$ok = (bool) $db->query($sql);
		$id = $ok ? (int) $db->last_insert_id($p.'ecole_cours_prof') : 0;
	} else {
		$db->commit();
		return 0; // rien à enregistrer : présent par défaut
	}
	// Historique : chaque changement d'une présence déjà enregistrée (ou d'un état saisi puis corrigé)
	if ($ok && $old && $avant !== $apres) {
		$sql = "INSERT INTO ".$p."ecole_cours_prof_log (entity, fk_cours, avant, apres, fk_user, date_creation) VALUES (".((int) $conf->entity).", ".((int) $old->rowid);
		$sql .= ", '".$db->escape($avant)."', '".$db->escape($apres)."', ".((int) $user->id).", ".$now.")";
		$ok = (bool) $db->query($sql);
	}
	if (!$ok) {
		$error = $db->lasterror();
		$db->rollback();
		return -1;
	}
	$db->commit();
	return $id;
}

/**
 * L'enseignant déclare son cours (sujet traité facultatif). Impossible si le surveillant ou la direction
 * l'a marqué absent, pour un cours à venir, ou pour un cours qui n'est pas le sien (sauf droit « gérer la présence »).
 *
 * @param  DoliDB $db         Handler base
 * @param  User   $user       Utilisateur
 * @param  string $date       Date AAAA-MM-JJ
 * @param  int    $fk_creneau Créneau
 * @param  int    $fk_classe  Classe
 * @param  string $sujet      Sujet traité
 * @param  string $error      Message d'erreur (sortie)
 * @return int                >0 si OK
 */
function personnel_cours_declarer($db, $user, $date, $fk_creneau, $fk_classe, $sujet, &$error)
{
	global $langs;
	$error = '';
	if ($date > personnel_aujourdhui()) {
		$error = $langs->trans('ErrorCoursAVenir');
		return -1;
	}
	$old = personnel_cours_fetch($db, $date, $fk_creneau, $fk_classe);
	if ($old && (int) $old->etat === PERSONNEL_ABSENT) {
		$error = $langs->trans('ErrorDeclarationAbsent');
		return -1;
	}
	if (!$user->hasRight('personnel', 'presence', 'gerer')) {
		$moi = personnel_employe_de($db, $user);
		$edt = personnel_cours_edt($db, $date, $fk_creneau, $fk_classe);
		$titulaire = $old ? (int) $old->fk_employe : ($edt ? (int) $edt->fk_employe : 0);
		if (!$moi || $titulaire !== (int) $moi->id) {
			$error = $langs->trans('ErrorPasVotreCours');
			return -1;
		}
	}
	$res = personnel_cours_enregistrer($db, $user, $date, $fk_creneau, $fk_classe, array('declare_cours' => 1, 'sujet' => $sujet), 'declaration', $error);
	return $res < 0 ? -1 : 1;
}

/**
 * Corrections d'une présence (plus récentes en premier).
 *
 * @param  DoliDB $db       Handler base
 * @param  int    $fk_cours Ligne de présence
 * @return array<int,object>
 */
function personnel_cours_corrections($db, $fk_cours)
{
	$out = array();
	$resql = $db->query("SELECT avant, apres, fk_user, date_creation FROM ".$db->prefix()."ecole_cours_prof_log WHERE fk_cours = ".((int) $fk_cours)." ORDER BY date_creation DESC, rowid DESC");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[] = $o;
	}
	return $out;
}

/* ------------------------------------------------------------------
 * Absences du personnel
 * ---------------------------------------------------------------- */

/**
 * Durées d'une absence : clé => clé de traduction.
 *
 * @return array<string,string>
 */
function personnel_durees()
{
	return array('jour' => 'JourneeEntiere', 'matin' => 'Matin', 'apresmidi' => 'ApresMidi');
}

/**
 * Absences (valides) d'employés qui touchent une période : liste de lignes.
 *
 * @param  DoliDB $db         Handler base
 * @param  string $du         Début
 * @param  string $au         Fin
 * @param  int    $fk_employe 0 = tous
 * @return array<int,object>
 */
function personnel_absences_periode($db, $du, $au, $fk_employe = 0)
{
	$out = array();
	$sql = "SELECT rowid, fk_employe, date_debut, date_fin, duree, justifiee, fk_motif, note FROM ".$db->prefix()."ecole_absence_perso";
	$sql .= " WHERE entity IN (".getEntity('ecole_absence_perso').") AND status = 1 AND date_debut <= '".$db->escape($au)."' AND date_fin >= '".$db->escape($du)."'";
	if ($fk_employe > 0) {
		$sql .= " AND fk_employe = ".((int) $fk_employe);
	}
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$o->date_debut = substr($o->date_debut, 0, 10);
		$o->date_fin = substr($o->date_fin, 0, 10);
		$out[] = $o;
	}
	return $out;
}

/**
 * Absence qui couvre une date (et une heure de début de cours pour les demi-journées).
 *
 * @param  object[] $absences Absences de l'employé
 * @param  string   $date     Date AAAA-MM-JJ
 * @param  string   $heure    Heure HH:MM ('' = la journée)
 * @return object|null
 */
function personnel_absence_couvre($absences, $date, $heure = '')
{
	$midi = getDolGlobalString('PERSONNEL_HEURE_MIDI', '12:00');
	foreach ($absences as $a) {
		if ($date < $a->date_debut || $date > $a->date_fin) {
			continue;
		}
		if ($a->duree === 'matin' && $heure !== '' && $heure >= $midi) {
			continue;
		}
		if ($a->duree === 'apresmidi' && $heure !== '' && $heure < $midi) {
			continue;
		}
		return $a;
	}
	return null;
}

/**
 * Enregistre une absence du personnel (une date ou une période ; demi-journée seulement pour une seule date).
 *
 * @param  DoliDB $db   Handler base
 * @param  User   $user Utilisateur
 * @param  array  $a    fk_employe, date_debut, date_fin, duree, justifiee, fk_motif, note
 * @param  string $error Message d'erreur (sortie)
 * @return int          Id, <0 si erreur
 */
function personnel_absence_creer($db, $user, $a, &$error)
{
	global $conf, $langs;
	$error = '';
	$emp = new EcoleEmploye($db);
	if ((int) $a['fk_employe'] <= 0 || $emp->fetch((int) $a['fk_employe']) <= 0) {
		$error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('Employe'));
		return -1;
	}
	$du = personnel_date_ok($a['date_debut']);
	$au = personnel_date_ok($a['date_fin'] !== '' ? $a['date_fin'] : $a['date_debut']);
	if ($du === '' || $au === '') {
		$error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('Date'));
		return -1;
	}
	if ($au < $du) {
		$error = $langs->trans('ErrorDateFinAvantDebut');
		return -1;
	}
	$duree = isset(personnel_durees()[$a['duree']]) ? $a['duree'] : 'jour';
	if ($duree !== 'jour' && $au !== $du) {
		$error = $langs->trans('ErrorDemiJourneeUneDate');
		return -1;
	}
	// Pas deux absences sur les mêmes jours
	foreach (personnel_absences_periode($db, $du, $au, (int) $emp->id) as $x) {
		if ($x->duree === 'jour' || $duree === 'jour' || $x->duree === $duree) {
			$error = $langs->trans('ErrorAbsenceDejaSaisie', dol_print_date(personnel_date_ts($x->date_debut), 'day'), dol_print_date(personnel_date_ts($x->date_fin), 'day'));
			return -1;
		}
	}
	$sql = "INSERT INTO ".$db->prefix()."ecole_absence_perso (entity, fk_employe, date_debut, date_fin, duree, justifiee, fk_motif, note, date_creation, fk_user_creat, status)";
	$sql .= " VALUES (".((int) $conf->entity).", ".((int) $emp->id).", '".$db->escape($du)."', '".$db->escape($au)."', '".$db->escape($duree)."'";
	$sql .= ", ".(!empty($a['justifiee']) ? 1 : 0).", ".((int) $a['fk_motif'] > 0 ? (int) $a['fk_motif'] : "NULL");
	$sql .= ", ".(trim((string) $a['note']) !== '' ? "'".$db->escape(dol_trunc(trim($a['note']), 250, 'right', 'UTF-8', 1))."'" : "NULL").", '".$db->idate(dol_now())."', ".((int) $user->id).", 1)";
	if (!$db->query($sql)) {
		$error = $db->lasterror();
		return -1;
	}
	return (int) $db->last_insert_id($db->prefix().'ecole_absence_perso');
}

/**
 * Justifie (ou non) une absence du personnel, avec son motif.
 *
 * @param  DoliDB $db        Handler base
 * @param  User   $user      Utilisateur
 * @param  int    $id        Absence
 * @param  bool   $justifiee Justifiée ?
 * @param  int    $fk_motif  Motif
 * @param  string $note      Remarque
 * @return int               1 si OK, -1 sinon
 */
function personnel_absence_justifier($db, $user, $id, $justifiee, $fk_motif, $note)
{
	$sql = "UPDATE ".$db->prefix()."ecole_absence_perso SET justifiee = ".($justifiee ? 1 : 0).", fk_motif = ".((int) $fk_motif > 0 ? (int) $fk_motif : "NULL");
	$sql .= ", note = ".(trim((string) $note) !== '' ? "'".$db->escape(dol_trunc(trim($note), 250, 'right', 'UTF-8', 1))."'" : "NULL");
	$sql .= ", fk_user_modif = ".((int) $user->id)." WHERE rowid = ".((int) $id)." AND status = 1";
	return $db->query($sql) ? 1 : -1;
}

/**
 * Annule une absence saisie par erreur (la ligne est gardée, barrée, avec le motif).
 *
 * @param  DoliDB $db    Handler base
 * @param  User   $user  Utilisateur
 * @param  int    $id    Absence
 * @param  string $motif Motif de l'annulation
 * @param  string $error Message d'erreur (sortie)
 * @return int           1 si OK, -1 sinon
 */
function personnel_absence_annuler($db, $user, $id, $motif, &$error)
{
	global $langs;
	$error = '';
	if (trim((string) $motif) === '') {
		$error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('MotifAnnulation'));
		return -1;
	}
	$sql = "UPDATE ".$db->prefix()."ecole_absence_perso SET status = 0, motif_annulation = '".$db->escape(dol_trunc(trim($motif), 250, 'right', 'UTF-8', 1))."'";
	$sql .= ", date_annulation = '".$db->idate(dol_now())."', fk_user_annul = ".((int) $user->id)." WHERE rowid = ".((int) $id)." AND status = 1";
	if (!$db->query($sql)) {
		$error = $db->lasterror();
		return -1;
	}
	return 1;
}

/* ------------------------------------------------------------------
 * Cours d'une journée (tableau de la présence des enseignants)
 * ---------------------------------------------------------------- */

/**
 * Cours de l'emploi du temps d'une journée avec la présence de l'enseignant.
 * Chaque ligne : creneau, classe, matière, employé titulaire, ligne enregistrée, état calculé
 * (present | declare | absent | retard | avenir | sanscours), absence du personnel qui couvre le cours.
 *
 * @param  DoliDB $db         Handler base
 * @param  string $date       Date AAAA-MM-JJ
 * @param  int    $fk_employe 0 = tous les enseignants
 * @return array<string,object> « créneau|classe » => ligne, dans l'ordre de la journée
 */
function personnel_cours_jour($db, $date, $fk_employe = 0)
{
	$p = $db->prefix();
	$jour = (int) date('N', strtotime($date));
	$out = array();
	if (!in_array($jour, ecole_jours_ouvrables(), true)) {
		return $out;
	}
	$sanscours = personnel_jours_sans_cours($db, $date, $date);
	$absences = array();
	foreach (personnel_absences_periode($db, $date, $date) as $a) {
		$absences[(int) $a->fk_employe][] = $a;
	}
	$records = array();
	$sql = "SELECT ".personnel_cours_cols()." FROM ".$p."ecole_cours_prof WHERE entity IN (".getEntity('ecole_cours_prof').") AND date_cours = '".$db->escape($date)."'";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$records[$o->fk_creneau.'|'.$o->fk_classe] = $o;
	}

	$sql = "SELECT e.fk_creneau, e.fk_classe, e.fk_matiere, e.fk_user, e.fk_salle, c.ref as crref, c.heure_debut, c.heure_fin, cl.ref as cref, cl.label_fr as cl_fr, cl.label_ar as cl_ar,";
	$sql .= " m.ref as mref, m.label_fr as m_fr, m.label_ar as m_ar, emp.rowid as fk_employe, emp.ref as eref, emp.nom_fr, emp.nom_ar";
	$sql .= " FROM ".$p."ecole_edt_cours e INNER JOIN ".$p."ecole_creneau c ON c.rowid = e.fk_creneau AND c.status = 1";
	$sql .= " INNER JOIN ".$p."ecole_classe cl ON cl.rowid = e.fk_classe INNER JOIN ".$p."ecole_matiere m ON m.rowid = e.fk_matiere";
	$sql .= " LEFT JOIN ".$p."ecole_employe emp ON emp.fk_user = e.fk_user AND e.fk_user IS NOT NULL";
	$sql .= " WHERE e.jour = ".$jour;
	if ($fk_employe > 0) {
		$sql .= " AND (emp.rowid = ".((int) $fk_employe)." OR EXISTS (SELECT 1 FROM ".$p."ecole_cours_prof r WHERE r.date_cours = '".$db->escape($date)."' AND r.fk_creneau = e.fk_creneau AND r.fk_classe = e.fk_classe AND (r.fk_employe = ".((int) $fk_employe)." OR r.fk_remplacant = ".((int) $fk_employe).")))";
	}
	$sql .= " ORDER BY c.heure_debut, cl.rowid";
	$resql = $db->query($sql);
	$aujourdhui = personnel_aujourdhui();
	while ($resql && ($o = $db->fetch_object($resql))) {
		$k = $o->fk_creneau.'|'.$o->fk_classe;
		$o->date = $date;
		$o->rec = isset($records[$k]) ? $records[$k] : null;
		if ($o->rec && (int) $o->rec->fk_employe > 0 && (int) $o->rec->fk_employe !== (int) $o->fk_employe) {
			// L'emploi du temps a changé depuis : l'enseignant enregistré avec le cours fait foi
			$o->fk_employe = (int) $o->rec->fk_employe;
			$o->eref = personnel_employe_ref($db, $o->fk_employe);
			$o->nom_fr = personnel_employe_nom($db, $o->fk_employe);
			$o->nom_ar = '';
		}
		$o->absence = ((int) $o->fk_employe > 0 && isset($absences[(int) $o->fk_employe])) ? personnel_absence_couvre($absences[(int) $o->fk_employe], $date, $o->heure_debut) : null;
		$o->etat = personnel_etat_cours($o->rec, $o->absence, isset($sanscours[$date]), $date > $aujourdhui);
		$out[$k] = $o;
	}
	return $out;
}

/**
 * État calculé d'un cours.
 *
 * @param  object|null $rec       Présence enregistrée
 * @param  object|null $absence   Absence du personnel qui couvre le cours
 * @param  bool        $sanscours Jour sans cours
 * @param  bool        $avenir    Cours à venir
 * @return string                 present | declare | absent | retard | avenir | sanscours
 */
function personnel_etat_cours($rec, $absence, $sanscours, $avenir)
{
	if ($sanscours) {
		return 'sanscours';
	}
	if ($rec) {
		if ((int) $rec->etat === PERSONNEL_ABSENT) {
			return 'absent';
		}
		if ((int) $rec->etat === PERSONNEL_RETARD) {
			return 'retard';
		}
		if ((int) $rec->declare_cours) {
			return 'declare';
		}
		return $avenir ? 'avenir' : 'present'; // présence enregistrée : elle l'emporte sur une absence du personnel
	}
	if ($absence) {
		return 'absent';
	}
	return $avenir ? 'avenir' : 'present';
}

/* ------------------------------------------------------------------
 * Heures du mois d'un employé
 * ---------------------------------------------------------------- */

/**
 * Règles de paie qui s'appliquent à un employé : sa règle propre (fiche, onglet « Salaires ») si elle est
 * choisie, sinon la règle générale de la configuration (Personnel > Configuration > Règles de présence et de paie).
 *
 * @param  EcoleEmploye $emp Employé
 * @return array{retenue:string,retard:string,retard_seuil:int,hsup:bool,remplacement:string,remplacement_montant:float,suspension_payee:bool}
 */
function personnel_regles_employe($emp)
{
	$propre = function ($v, $valides, $defaut) {
		return in_array((string) $v, $valides, true) ? (string) $v : $defaut;
	};
	$gen = personnel_regles_generales();
	return array(
		'retenue' => $propre($emp->regle_retenue, array('aucune', 'prorata'), $gen['retenue']),
		'retard' => $propre($emp->regle_retard, array('aucun', 'minutes', 'seuil'), $gen['retard']),
		'retard_seuil' => $gen['retard_seuil'],
		'hsup' => ($emp->regle_hsup === null || $emp->regle_hsup === '') ? $gen['hsup'] : (bool) (int) $emp->regle_hsup,
		'remplacement' => $propre($emp->regle_remplacement, array('taux', 'forfait', 'aucun'), $gen['remplacement']),
		'remplacement_montant' => $gen['remplacement_montant'],
		'suspension_payee' => $gen['suspension_payee'],
	);
}

/**
 * Règles générales de paie (configuration du module Personnel).
 *
 * @return array{retenue:string,retard:string,retard_seuil:int,hsup:bool,remplacement:string,remplacement_montant:float,suspension_payee:bool}
 */
function personnel_regles_generales()
{
	return array(
		'retenue' => getDolGlobalString('PERSONNEL_RETENUE_ABSENCE', 'aucune'),
		'retard' => getDolGlobalString('PERSONNEL_RETARD_EFFET', 'aucun'),
		'retard_seuil' => getDolGlobalInt('PERSONNEL_RETARD_SEUIL', 15),
		'hsup' => getDolGlobalString('PERSONNEL_HEURES_SUP', 'oui') !== 'non',
		'remplacement' => getDolGlobalString('PERSONNEL_REMPLACEMENT_PAIE', 'taux'),
		'remplacement_montant' => (float) getDolGlobalString('PERSONNEL_REMPLACEMENT_MONTANT', '0'),
		'suspension_payee' => getDolGlobalString('PERSONNEL_SUSPENSION_PAYEE', 'oui') !== 'non',
	);
}

/**
 * Heures et présence d'un employé sur un mois, avec une estimation de la paie.
 *
 * @param  DoliDB       $db      Handler base
 * @param  EcoleEmploye $emp     Employé
 * @param  string       $mois    AAAA-MM
 * @return array
 */
function personnel_heures_mois($db, $emp, $mois)
{
	list($d1, $d2) = personnel_mois_bornes($mois);
	$h = personnel_heures_periode($db, $emp, $d1, $d2);
	$h['mois'] = $mois;
	return $h;
}

/**
 * Heures et présence d'un employé sur une période (un mois ou les dates d'un bulletin de paie), avec une
 * estimation de la paie.
 *
 * Enseignant : chaque cours de son emploi du temps (jours ouvrables, hors jours sans cours et périodes de
 * suspension / départ, depuis son embauche) est prévu ; il est fait sauf absence (surveillant, direction ou absence
 * du personnel). Les remplacements qu'il a faits s'ajoutent. Autres employés : jours ouvrables et absences.
 *
 * Heures supplémentaires (salaire fixe avec des heures prévues par semaine) : semaine par semaine, heures
 * réellement faites au-delà du volume de la semaine. Ce volume est réduit au prorata des jours ouvrables qui
 * comptent : une semaine coupée par le début ou la fin de la période (fin de mois), un jour sans cours, une
 * suspension ou l'embauche en cours de semaine réduisent le volume d'autant.
 *
 * Salaire de base (salaire fixe) : un salaire correspond toujours à un mois, quelle que soit la longueur de la
 * période (du 25/09 au 25/10 = un mois). Il n'est réduit que pour les jours non payés de la période :
 * fraction = jours payés ÷ jours de la période (avant l'embauche et après le départ : non payés ;
 * suspension / congé : selon la configuration).
 *
 * @param  DoliDB       $db  Handler base
 * @param  EcoleEmploye $emp Employé
 * @param  string       $d1  Début AAAA-MM-JJ
 * @param  string       $d2  Fin AAAA-MM-JJ
 * @return array{du:string,au:string,seances:array,totaux:array,regles:array,estimation:array}
 */
function personnel_heures_periode($db, $emp, $d1, $d2)
{
	$p = $db->prefix();
	$aujourdhui = personnel_aujourdhui();
	$debut = $d1;
	if (!empty($emp->date_embauche)) {
		$debut = max($d1, dol_print_date($emp->date_embauche, '%Y-%m-%d'));
	}
	$fin = $d2;
	$sanstravail = $emp->periodesSansTravail();
	$ouvrables = ecole_jours_ouvrables();
	$sanscours = personnel_jours_sans_cours($db, $d1, $d2);
	$absences = personnel_absences_periode($db, $d1, $d2, (int) $emp->id);
	$regles = personnel_regles_employe($emp);
	$seuil = $regles['retard_seuil'];
	$retardEffet = $regles['retard'];

	$estArret = function ($d) use ($sanstravail) {
		foreach ($sanstravail as $per) {
			if ($d >= $per[0] && ($per[1] === '' || $d <= $per[1])) {
				return true;
			}
		}
		return false;
	};

	// Emploi du temps de l'enseignant (jour => cours)
	$edt = array();
	if ((int) $emp->fk_user > 0) {
		$sql = "SELECT e.jour, e.fk_creneau, e.fk_classe, e.fk_matiere, c.ref as crref, c.heure_debut, c.heure_fin, cl.ref as cref, m.label_fr, m.label_ar";
		$sql .= " FROM ".$p."ecole_edt_cours e INNER JOIN ".$p."ecole_creneau c ON c.rowid = e.fk_creneau AND c.status = 1";
		$sql .= " INNER JOIN ".$p."ecole_classe cl ON cl.rowid = e.fk_classe INNER JOIN ".$p."ecole_matiere m ON m.rowid = e.fk_matiere";
		$sql .= " WHERE e.fk_user = ".((int) $emp->fk_user)." ORDER BY c.heure_debut";
		$resql = $db->query($sql);
		while ($resql && ($o = $db->fetch_object($resql))) {
			$edt[(int) $o->jour][] = $o;
		}
	}
	// Présences enregistrées du mois (titulaire ou remplaçant)
	$records = array();
	$sql = "SELECT r.".str_replace(', ', ', r.', personnel_cours_cols()).", c.ref as crref, c.heure_debut, c.heure_fin, cl.ref as cref, m.label_fr, m.label_ar";
	$sql .= " FROM ".$p."ecole_cours_prof r LEFT JOIN ".$p."ecole_creneau c ON c.rowid = r.fk_creneau LEFT JOIN ".$p."ecole_classe cl ON cl.rowid = r.fk_classe";
	$sql .= " LEFT JOIN ".$p."ecole_matiere m ON m.rowid = r.fk_matiere";
	$sql .= " WHERE r.entity IN (".getEntity('ecole_cours_prof').") AND r.date_cours BETWEEN '".$db->escape($d1)."' AND '".$db->escape($d2)."'";
	$sql .= " AND (r.fk_employe = ".((int) $emp->id)." OR r.fk_remplacant = ".((int) $emp->id);
	if ($edt) {
		$sql .= " OR r.fk_employe IS NULL";
	}
	$sql .= ")";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$records[substr($o->date_cours, 0, 10).'|'.$o->fk_creneau.'|'.$o->fk_classe] = $o;
	}

	$seances = array();
	$vus = array();
	$t = array('prevues' => 0, 'faites' => 0, 'payees' => 0, 'absent_j' => 0, 'absent_nj' => 0, 'retard_min' => 0, 'nb_retards' => 0,
		'remplacement' => 0, 'nb_remplacements' => 0, 'avenir' => 0, 'nb_prevues' => 0, 'nb_faites' => 0, 'nb_absences' => 0, 'nb_declares' => 0);
	$semaines = array();

	foreach (personnel_dates($d1, $d2) as $d => $jour) {
		if (!in_array($jour, $ouvrables, true)) {
			continue;
		}
		foreach (isset($edt[$jour]) ? $edt[$jour] : array() as $c) {
			$k = $d.'|'.$c->fk_creneau.'|'.$c->fk_classe;
			$rec = isset($records[$k]) ? $records[$k] : null;
			if ($rec && (int) $rec->fk_employe > 0 && (int) $rec->fk_employe !== (int) $emp->id) {
				continue; // cours d'un autre enseignant ce jour-là (emploi du temps changé depuis)
			}
			$vus[$k] = true;
			$seances[] = personnel_seance($d, $c, $rec, $d < $debut || $d > $fin || $estArret($d) ? 'suspendu' : null, isset($sanscours[$d]), $d > $aujourdhui,
				personnel_absence_couvre($absences, $d, $c->heure_debut), $seuil, $retardEffet);
		}
	}
	// Cours enregistrés avec cet enseignant mais absents de son emploi du temps actuel
	foreach ($records as $k => $rec) {
		if (isset($vus[$k]) || (int) $rec->fk_employe !== (int) $emp->id) {
			continue;
		}
		$d = substr($rec->date_cours, 0, 10);
		$seances[] = personnel_seance($d, $rec, $rec, null, isset($sanscours[$d]), $d > $aujourdhui, personnel_absence_couvre($absences, $d, (string) $rec->heure_debut), $seuil, $retardEffet);
	}
	// Remplacements faits par cet employé
	foreach ($records as $k => $rec) {
		if ((int) $rec->fk_remplacant !== (int) $emp->id) {
			continue;
		}
		$d = substr($rec->date_cours, 0, 10);
		$min = max(0, ecole_hhmm_to_min($rec->heure_fin) - ecole_hhmm_to_min($rec->heure_debut));
		$seances[] = (object) array('date' => $d, 'heure_debut' => $rec->heure_debut, 'heure_fin' => $rec->heure_fin, 'crref' => $rec->crref, 'cref' => $rec->cref,
			'matiere' => ecole_label((object) array('label_fr' => $rec->label_fr, 'label_ar' => $rec->label_ar)), 'fk_creneau' => (int) $rec->fk_creneau, 'fk_classe' => (int) $rec->fk_classe,
			'minutes' => $min, 'etat' => 'remplacement', 'retard' => 0, 'justifiee' => 0, 'rec' => $rec, 'compte' => 0, 'titulaire' => (int) $rec->fk_employe);
		$t['remplacement'] += $min;
		$t['nb_remplacements']++;
	}
	usort($seances, function ($a, $b) {
		return strcmp($a->date.$a->heure_debut, $b->date.$b->heure_debut);
	});

	foreach ($seances as $s) {
		if ($s->etat === 'remplacement' || $s->etat === 'suspendu' || $s->etat === 'sanscours') {
			continue;
		}
		if ($s->etat === 'avenir') {
			$t['avenir'] += $s->minutes;
			continue;
		}
		$t['prevues'] += $s->minutes;
		$t['nb_prevues']++;
		if ($s->etat === 'absent') {
			$t['nb_absences']++;
			$t[$s->justifiee ? 'absent_j' : 'absent_nj'] += $s->minutes;
			continue;
		}
		$t['faites'] += $s->minutes;
		$t['nb_faites']++;
		$t['payees'] += $s->compte;
		$t['nb_declares'] += ($s->etat === 'declare') ? 1 : 0;
		if ($s->etat === 'retard') {
			$t['retard_min'] += $s->retard;
			$t['nb_retards']++;
		}
		$w = date('o-W', strtotime($s->date));
		$semaines[$w] = (isset($semaines[$w]) ? $semaines[$w] : 0) + $s->minutes;
	}

	// Jours ouvrables qui comptent (chaque semaine) et fraction du mois payée pour le salaire fixe
	$arrets = $emp->periodesArret();
	$statutDu = function ($d) use ($arrets) {
		foreach ($arrets as $a) {
			if ($d >= $a[0] && ($a[1] === '' || $d <= $a[1])) {
				return (int) $a[2];
			}
		}
		return 0;
	};
	$t['jours_ouvrables'] = 0;
	$t['jours_absent_j'] = 0;
	$t['jours_absent_nj'] = 0;
	$t['fraction_mois'] = 0;
	$t['jours_payes'] = 0;
	$t['jours_periode'] = 0;
	$joursSemaine = array();
	foreach (personnel_dates($d1, $d2) as $d => $jour) {
		$t['jours_periode']++;
		$st = $statutDu($d);
		if ($d >= $debut && $st !== EcoleEmploye::STATUS_PARTI && ($st !== EcoleEmploye::STATUS_SUSPENDU || $regles['suspension_payee'])) {
			$t['jours_payes']++;
		}
		if (!in_array($jour, $ouvrables, true) || $d < $debut || $d > $fin || $estArret($d)) {
			continue;
		}
		if (!isset($sanscours[$d])) {
			$w = date('o-W', strtotime($d.' 12:00:00'));
			$joursSemaine[$w] = (isset($joursSemaine[$w]) ? $joursSemaine[$w] : 0) + 1;
		}
		// Jours d'absence du personnel (tous les employés) : journées ouvrables couvertes, demi-journée = 0,5
		$t['jours_ouvrables']++;
		foreach ($absences as $a) {
			if ($d >= $a->date_debut && $d <= $a->date_fin) {
				$t[(int) $a->justifiee ? 'jours_absent_j' : 'jours_absent_nj'] += ($a->duree === 'jour') ? 1 : 0.5;
			}
		}
	}

	$t['fraction_mois'] = $t['jours_periode'] > 0 ? $t['jours_payes'] / $t['jours_periode'] : 0;

	// Heures supplémentaires : semaine par semaine, au-delà du volume de la semaine réduit au prorata des jours qui comptent
	$t['heures_sup'] = 0;
	$t['hsup_semaines'] = array();
	if ($emp->mode_paie === 'fixe' && (float) $emp->heures_semaine > 0 && $regles['hsup'] && count($ouvrables) > 0) {
		foreach ($semaines as $w => $min) {
			$volume = (float) $emp->heures_semaine * 60 * (isset($joursSemaine[$w]) ? $joursSemaine[$w] : 0) / count($ouvrables);
			$sup = (int) round(max(0, $min - $volume));
			$t['heures_sup'] += $sup;
			$t['hsup_semaines'][$w] = array('faites' => $min, 'volume' => (int) round($volume), 'sup' => $sup);
		}
	}

	return array('du' => $d1, 'au' => $d2, 'seances' => $seances, 'totaux' => $t, 'regles' => $regles, 'estimation' => personnel_estimation($emp, $t, $regles));
}

/**
 * Une séance de l'emploi du temps avec son état.
 *
 * @param  string      $d           Date
 * @param  object      $c           Cours (fk_creneau, fk_classe, crref, cref, heure_debut, heure_fin, label_fr, label_ar)
 * @param  object|null $rec         Présence enregistrée
 * @param  string|null $force       État imposé (suspendu)
 * @param  bool        $sanscours   Jour sans cours
 * @param  bool        $avenir      À venir
 * @param  object|null $absence     Absence du personnel qui couvre le cours
 * @param  int         $seuil       Retard au-delà duquel le cours compte comme absent (règle « seuil »)
 * @param  string      $retardEffet aucun | minutes | seuil
 * @return object
 */
function personnel_seance($d, $c, $rec, $force, $sanscours, $avenir, $absence, $seuil, $retardEffet)
{
	$min = max(0, ecole_hhmm_to_min($c->heure_fin) - ecole_hhmm_to_min($c->heure_debut));
	$etat = $force ? $force : personnel_etat_cours($rec, $absence, $sanscours, $avenir);
	$retard = ($etat === 'retard' && $rec) ? (int) $rec->minutes_retard : 0;
	$justifiee = 0;
	if ($etat === 'absent') {
		$justifiee = $rec && (int) $rec->etat === PERSONNEL_ABSENT ? (int) $rec->justifiee : ($absence ? (int) $absence->justifiee : 0);
	}
	// Minutes payées (paiement à l'heure) selon la règle des retards
	$compte = in_array($etat, array('present', 'declare', 'retard'), true) ? $min : 0;
	if ($etat === 'retard') {
		if ($retardEffet === 'minutes') {
			$compte = max(0, $min - $retard);
		} elseif ($retardEffet === 'seuil' && $retard > $seuil) {
			$compte = 0;
		}
	}
	return (object) array('date' => $d, 'heure_debut' => $c->heure_debut, 'heure_fin' => $c->heure_fin, 'crref' => $c->crref, 'cref' => $c->cref,
		'matiere' => ecole_label((object) array('label_fr' => $c->label_fr, 'label_ar' => $c->label_ar)), 'fk_creneau' => (int) $c->fk_creneau, 'fk_classe' => (int) $c->fk_classe,
		'minutes' => $min, 'etat' => $etat, 'retard' => $retard, 'justifiee' => $justifiee, 'rec' => $rec, 'compte' => $compte, 'absence' => $absence);
}

/**
 * Lignes de paie calculées depuis la présence (même calcul pour l'estimation du mois et pour le bulletin de paie
 * du module Salaires), selon les règles de l'employé (personnel_regles_employe) :
 *  - salaire fixe : salaire de base (fraction de mois payée), retenue pour absences non justifiées et pour retards
 *    (si la règle le prévoit), heures supplémentaires ;
 *  - à l'heure : heures faites × prix de l'heure (retards selon la règle) ;
 *  - tous : remplacements faits (selon la règle).
 * Montants positifs ; « type » dit s'il s'agit d'un gain ou d'une retenue. Chaque texte est donné par sa clé de
 * traduction et ses paramètres (« cle », « dcle », « dargs ») pour être réécrit dans une autre langue (PDF arabe),
 * et déjà traduit dans la langue de l'utilisateur (« libelle », « detail »).
 *
 * @param  EcoleEmploye $emp    Employé
 * @param  array        $t      Totaux (personnel_heures_periode)
 * @param  array|null   $regles Règles (défaut : personnel_regles_employe)
 * @return array{possible:bool,lignes:array<int,array>,alertes:array<int,array{0:string,1:array}>}
 */
function personnel_paie_lignes($emp, $t, $regles = null)
{
	global $langs;
	$langs->load('personnel@personnel');
	$regles = $regles ? $regles : personnel_regles_employe($emp);
	$lignes = array();
	$alertes = array();
	$taux = (float) $emp->taux_horaire;
	$ligne = function ($code, $type, $cle, $dcle, $dargs, $montant) use ($langs) {
		return array('code' => $code, 'type' => $type, 'cle' => $cle, 'dcle' => $dcle, 'dargs' => $dargs,
			'libelle' => $langs->transnoentities($cle), 'detail' => personnel_paie_texte($langs, $dcle, $dargs),
			'montant' => (float) price2num(max(0, $montant), 'MT'));
	};
	$impossible = array('possible' => false, 'lignes' => array(), 'alertes' => array(array('EstimationImpossible', array())));

	if ($emp->mode_paie === 'heure') {
		if ($taux <= 0) {
			return $impossible;
		}
		$lignes[] = $ligne('HEURES', 'gain', 'LigneHeuresFaites', 'DetailHeuresTaux', array(array('d', $t['payees']), array('m', $taux)), $t['payees'] / 60 * $taux);
		if ($t['faites'] > $t['payees']) {
			$alertes[] = array('AlerteRetardsNonPayes', array(array('d', $t['faites'] - $t['payees'])));
		}
	} elseif ($emp->mode_paie === 'fixe') {
		$sal = (float) $emp->salaire_base;
		if ($sal <= 0) {
			return $impossible;
		}
		$fraction = isset($t['fraction_mois']) ? (float) $t['fraction_mois'] : 1;
		$base = $sal * $fraction;
		$entier = abs($fraction - round($fraction)) <= 0.0005 && $fraction >= 0.9995 && $fraction <= 1.0005;
		$lignes[] = $ligne('BASE', 'gain', 'LigneSalaireBase', $entier ? '' : 'DetailFractionMois', array(array('n', round($fraction, 2)), array('m', $sal)), $base);
		$retenu = 0;
		if ($regles['retenue'] === 'prorata') {
			if ($t['prevues'] > 0 && $t['absent_nj'] > 0) {
				$m = min($base, $base * $t['absent_nj'] / $t['prevues']);
				$retenu += $m;
				$lignes[] = $ligne('ABSENCE', 'retenue', 'LigneRetenueAbsences', 'DetailAbsSeances', array(array('d', $t['absent_nj']), array('d', $t['prevues'])), $m);
			} elseif ($t['prevues'] <= 0 && $t['jours_absent_nj'] > 0 && $t['jours_ouvrables'] > 0) {
				$m = min($base, $base * $t['jours_absent_nj'] / $t['jours_ouvrables']);
				$retenu += $m;
				$lignes[] = $ligne('ABSENCE', 'retenue', 'LigneRetenueAbsences', 'DetailAbsJours', array(array('n', $t['jours_absent_nj']), array('n', $t['jours_ouvrables'])), $m);
			}
		} elseif ($t['absent_nj'] > 0 || $t['jours_absent_nj'] > 0) {
			$alertes[] = array('AlerteAbsencesSansRetenue', array());
		}
		if ($t['retard_min'] > 0 && $t['prevues'] > 0 && $regles['retard'] !== 'aucun') {
			// Retards : minutes perdues (règle « minutes ») ou séances comptées absentes au-delà du seuil
			$perdu = $t['faites'] - $t['payees'];
			if ($perdu > 0) {
				$lignes[] = $ligne('RETARD', 'retenue', 'LigneRetenueRetards', 'DetailRetards', array(array('d', $perdu), array('d', $t['prevues'])), min($base - $retenu, $base * $perdu / $t['prevues']));
			}
		}
		if ($t['heures_sup'] > 0) {
			$tsup = (float) $emp->taux_heure_sup > 0 ? (float) $emp->taux_heure_sup : $taux;
			if ($tsup > 0) {
				$lignes[] = $ligne('HSUP', 'gain', 'LigneHeuresSup', 'DetailHeuresTaux', array(array('d', $t['heures_sup']), array('m', $tsup)), $t['heures_sup'] / 60 * $tsup);
			} else {
				$alertes[] = array('AlertePrixHeureSup', array(array('d', $t['heures_sup'])));
			}
		}
	} else {
		return $impossible;
	}

	if ($t['nb_remplacements'] > 0) {
		if ($regles['remplacement'] === 'forfait') {
			$lignes[] = $ligne('REMPL', 'gain', 'LigneRemplacements', 'DetailRemplForfait', array(array('n', $t['nb_remplacements']), array('m', $regles['remplacement_montant'])), $t['nb_remplacements'] * $regles['remplacement_montant']);
		} elseif ($regles['remplacement'] === 'taux') {
			$tr = $taux > 0 ? $taux : (float) $emp->taux_heure_sup;
			if ($tr > 0) {
				$lignes[] = $ligne('REMPL', 'gain', 'LigneRemplacements', 'DetailHeuresTaux', array(array('d', $t['remplacement']), array('m', $tr)), $t['remplacement'] / 60 * $tr);
			} else {
				$alertes[] = array('AlertePrixRemplacement', array(array('n', $t['nb_remplacements'])));
			}
		}
	}
	return array('possible' => true, 'lignes' => $lignes, 'alertes' => $alertes);
}

/**
 * Texte d'une ligne ou d'une alerte de paie dans une langue : clé de traduction + paramètres typés
 * ('d' = durée en minutes, 'm' = montant, 'n' = nombre, 't' = texte).
 *
 * @param  Translate $l    Traductions
 * @param  string    $cle  Clé ('' = pas de texte)
 * @param  array     $args Paramètres : liste de array(type, valeur)
 * @return string
 */
function personnel_paie_texte($l, $cle, $args)
{
	if ((string) $cle === '') {
		return '';
	}
	$vals = array();
	foreach ((array) $args as $a) {
		$type = is_array($a) ? $a[0] : 'n';
		$v = is_array($a) ? $a[1] : $a;
		if ($type === 't') {
			$vals[] = (string) $v; // texte tel quel (numéro, date)
		} else {
			$vals[] = ($type === 'd') ? personnel_duree((int) $v) : (($type === 'm') ? price((float) $v, 0, $l) : price2num((float) $v));
		}
	}
	return $l->transnoentities($cle, ...$vals);
}

/**
 * Estimation de la paie selon les règles (le bulletin définitif, avec primes, avances et prêts, se fait dans
 * le module Salaires).
 *
 * @param  EcoleEmploye $emp    Employé
 * @param  array        $t      Totaux (personnel_heures_periode)
 * @param  array|null   $regles Règles de l'employé
 * @return array{lignes:array,total:float,possible:bool}
 */
function personnel_estimation($emp, $t, $regles = null)
{
	$calc = personnel_paie_lignes($emp, $t, $regles);
	$lignes = array();
	$total = 0;
	foreach ($calc['lignes'] as $l) {
		$m = ($l['type'] === 'retenue') ? -$l['montant'] : $l['montant'];
		$lignes[] = array($l['libelle'].($l['detail'] !== '' ? ' ('.$l['detail'].')' : ''), $m);
		$total += $m;
	}
	return array('lignes' => $lignes, 'total' => max(0, $total), 'possible' => $calc['possible']);
}

/* ------------------------------------------------------------------
 * Présence de l'enseignant pendant l'appel des élèves (page Appel du module Élèves)
 * ---------------------------------------------------------------- */

/**
 * Ligne « Enseignant : présent / absent / en retard » de la page d'appel.
 *
 * @param  DoliDB $db         Handler base
 * @param  string $date       Date AAAA-MM-JJ
 * @param  int    $fk_classe  Classe
 * @param  int    $fk_creneau Créneau
 * @param  bool   $editable   Formulaire modifiable
 * @return string             HTML (ligne de tableau), '' si le cours n'a pas d'enseignant relié à une fiche
 */
function personnel_appel_ligne($db, $date, $fk_classe, $fk_creneau, $editable)
{
	global $langs;
	$langs->load('personnel@personnel');
	$edt = personnel_cours_edt($db, $date, $fk_creneau, $fk_classe);
	$rec = personnel_cours_fetch($db, $date, $fk_creneau, $fk_classe);
	$fk_employe = $rec && (int) $rec->fk_employe > 0 ? (int) $rec->fk_employe : ($edt ? (int) $edt->fk_employe : 0);
	if ($fk_employe <= 0) {
		return '';
	}
	$absences = personnel_absences_periode($db, $date, $date, $fk_employe);
	$absence = personnel_absence_couvre($absences, $date, $edt ? $edt->heure_debut : '');
	$etat = personnel_etat_cours($rec, $absence, false, false);
	$out = '<tr><td>'.img_picto('', 'fa-chalkboard-teacher', 'class="pictofixedwidth"').$langs->trans('Enseignant').'</td><td>';
	$out .= '<b>'.dol_escape_htmltag(personnel_employe_nom($db, $fk_employe)).'</b> ';
	if ($editable) {
		$e = $rec ? (int) $rec->etat : ($absence ? PERSONNEL_ABSENT : PERSONNEL_PRESENT);
		$min = ($rec && (int) $rec->etat === PERSONNEL_RETARD) ? (int) $rec->minutes_retard : '';
		$out .= '<input type="hidden" name="prof_employe" value="'.$fk_employe.'">';
		foreach (array(PERSONNEL_PRESENT => array('Present', ''), PERSONNEL_ABSENT => array('Absent', 'appel-abs'), PERSONNEL_RETARD => array('EnRetard', 'appel-ret')) as $v => $l) {
			$out .= '<label class="appel-tog '.$l[1].($e === $v ? ' on' : '').'"><input type="radio" name="prof_etat" value="'.$v.'"'.($e === $v ? ' checked' : '').'>'.$langs->trans($l[0]).'</label>';
		}
		$out .= '<input type="number" name="prof_retard" min="1" max="600" class="flat maxwidth75" placeholder="'.dol_escape_htmltag($langs->trans('Minutes')).'" value="'.dol_escape_htmltag((string) $min).'"'.($e === PERSONNEL_RETARD ? '' : ' style="display:none"').'> ';
		if ($absence && !$rec) {
			$out .= '<span class="opacitymedium small">'.$langs->trans('AbsenceDejaSaisie').'</span>';
		}
		if ($rec && (int) $rec->declare_cours) {
			$out .= personnel_etat_badge('declare');
		}
		$out .= '<script>$(function(){var f=$("input[name=prof_etat]");f.on("change",function(){var v=$("input[name=prof_etat]:checked").val();f.each(function(){$(this).parent().toggleClass("on",this.checked);});$("input[name=prof_retard]").toggle(v==="'.PERSONNEL_RETARD.'");});});</script>';
	} else {
		$info = ($etat === 'retard' && $rec) ? '('.$langs->trans('NbMinutes', (int) $rec->minutes_retard).')' : '';
		$out .= personnel_etat_badge($etat, $info);
	}
	if ($rec && (int) $rec->fk_remplacant > 0) {
		$out .= ' <span class="opacitymedium">'.$langs->trans('RemplacePar', dol_escape_htmltag(personnel_employe_nom($db, (int) $rec->fk_remplacant))).'</span>';
	}
	$out .= '</td></tr>';
	return $out;
}

/**
 * Enregistre la présence de l'enseignant envoyée avec l'appel (champs prof_etat / prof_retard).
 * Rien n'est enregistré si l'état n'a pas changé.
 *
 * @param  DoliDB $db         Handler base
 * @param  User   $user       Utilisateur
 * @param  string $date       Date AAAA-MM-JJ
 * @param  int    $fk_classe  Classe
 * @param  int    $fk_creneau Créneau
 * @param  string $error      Message d'erreur (sortie)
 * @return int                >=0 si OK, <0 si erreur
 */
function personnel_appel_enregistrer($db, $user, $date, $fk_classe, $fk_creneau, &$error)
{
	$error = '';
	if (!GETPOSTISSET('prof_etat')) {
		return 0;
	}
	$etat = GETPOSTINT('prof_etat');
	$rec = personnel_cours_fetch($db, $date, $fk_creneau, $fk_classe);
	$data = array('etat' => $etat, 'minutes_retard' => GETPOSTINT('prof_retard'));
	if ($etat === PERSONNEL_PRESENT && !$rec) {
		$edt = personnel_cours_edt($db, $date, $fk_creneau, $fk_classe);
		$abs = ($edt && (int) $edt->fk_employe > 0) ? personnel_absence_couvre(personnel_absences_periode($db, $date, $date, (int) $edt->fk_employe), $date, $edt->heure_debut) : null;
		if (!$abs) {
			return 0; // présent par défaut : rien à enregistrer
		}
	}
	if ($rec && (int) $rec->etat === $etat && ($etat !== PERSONNEL_RETARD || (int) $rec->minutes_retard === GETPOSTINT('prof_retard'))) {
		return 0;
	}
	return personnel_cours_enregistrer($db, $user, $date, $fk_creneau, $fk_classe, $data, 'appel', $error);
}

/* ------------------------------------------------------------------
 * Listes (écran + exports PDF / Excel, mêmes filtres)
 * ---------------------------------------------------------------- */

/**
 * Filtres d'une liste de présence, lus dans la requête.
 *
 * @param  string[] $keys Filtres utilisés (du, au, employe, classe, creneau, type, etat, categorie, mode, mois, motif, source)
 * @return array{f:array,param:string}
 */
function personnel_filtres($keys)
{
	$remove = GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha');
	$types = array('du' => 'date', 'au' => 'date', 'employe' => 'alphanohtml', 'classe' => 'int', 'creneau' => 'int', 'type' => 'aZ09', 'etat' => 'aZ09',
		'categorie' => 'aZ09', 'mode' => 'aZ09', 'motif' => 'int', 'source' => 'aZ09', 'fk_employe' => 'int', 'duree' => 'aZ09', 'matiere' => 'int', 'statut' => 'aZ09');
	$f = array();
	$param = '';
	foreach ($keys as $k) {
		$t = isset($types[$k]) ? $types[$k] : 'alphanohtml';
		if ($remove) {
			$v = ($t === 'int') ? 0 : '';
		} elseif ($t === 'int') {
			$v = max(0, GETPOSTINT('f_'.$k));
		} elseif ($t === 'date') {
			$v = personnel_date_ok(GETPOST('f_'.$k, 'alpha'));
		} else {
			$v = trim(GETPOST('f_'.$k, $t));
			if ($v === '-1') {
				$v = '';
			}
		}
		$f[$k] = $v;
		if ($v !== '' && $v !== 0) {
			$param .= '&f_'.$k.'='.urlencode((string) $v);
		}
	}
	return array('f' => $f, 'param' => $param);
}

/**
 * Lignes de la liste des cours signalés (absences, retards, déclarations, remplacements des enseignants).
 *
 * @param  DoliDB $db     Handler base
 * @param  array  $f      Filtres
 * @param  int    $limit  0 = tout
 * @param  int    $offset Décalage
 * @param  int    $total  Nombre total (sortie)
 * @return array<int,object>
 */
function personnel_cours_liste($db, $f, $limit, $offset, &$total)
{
	$p = $db->prefix();
	$w = array("r.entity IN (".getEntity('ecole_cours_prof').")");
	if ($f['du'] !== '') {
		$w[] = "r.date_cours >= '".$db->escape($f['du'])."'";
	}
	if ($f['au'] !== '') {
		$w[] = "r.date_cours <= '".$db->escape($f['au'])."'";
	}
	if ($f['classe'] > 0) {
		$w[] = "r.fk_classe = ".((int) $f['classe']);
	}
	if ($f['creneau'] > 0) {
		$w[] = "r.fk_creneau = ".((int) $f['creneau']);
	}
	if ($f['matiere'] > 0) {
		$w[] = "r.fk_matiere = ".((int) $f['matiere']);
	}
	if ($f['fk_employe'] > 0) {
		$w[] = "(r.fk_employe = ".((int) $f['fk_employe'])." OR r.fk_remplacant = ".((int) $f['fk_employe']).")";
	}
	if ($f['employe'] !== '') {
		$w[] = "(r.fk_employe IN (SELECT x.rowid FROM ".$p."ecole_employe x WHERE ".natural_search(array('x.ref', 'x.nom_fr', 'x.nom_ar'), $f['employe'], 0, 1).")"
			." OR r.fk_remplacant IN (SELECT x.rowid FROM ".$p."ecole_employe x WHERE ".natural_search(array('x.ref', 'x.nom_fr', 'x.nom_ar'), $f['employe'], 0, 1)."))";
	}
	$types = array('absent' => "r.etat = ".PERSONNEL_ABSENT, 'retard' => "r.etat = ".PERSONNEL_RETARD, 'declare' => "r.declare_cours = 1", 'remplacement' => "r.fk_remplacant IS NOT NULL",
		'nonremplace' => "r.etat = ".PERSONNEL_ABSENT." AND r.fk_remplacant IS NULL");
	if (isset($types[$f['type']])) {
		$w[] = $types[$f['type']];
	}
	if ($f['etat'] === 'oui' || $f['etat'] === 'non') {
		$w[] = "r.etat IN (".PERSONNEL_ABSENT.", ".PERSONNEL_RETARD.") AND r.justifiee = ".($f['etat'] === 'oui' ? 1 : 0);
	}
	if (in_array($f['source'], array('appel', 'declaration', 'direction'), true)) {
		$w[] = "r.source = '".$db->escape($f['source'])."'";
	}
	$from = " FROM ".$p."ecole_cours_prof r LEFT JOIN ".$p."ecole_creneau c ON c.rowid = r.fk_creneau LEFT JOIN ".$p."ecole_classe cl ON cl.rowid = r.fk_classe";
	$from .= " LEFT JOIN ".$p."ecole_matiere m ON m.rowid = r.fk_matiere LEFT JOIN ".$p."ecole_employe e ON e.rowid = r.fk_employe";
	$from .= " LEFT JOIN ".$p."ecole_employe_motif mo ON mo.rowid = r.fk_motif WHERE ".implode(' AND ', $w);

	$total = 0;
	$resql = $db->query("SELECT COUNT(*) as nb".$from);
	if ($resql && ($o = $db->fetch_object($resql))) {
		$total = (int) $o->nb;
	}
	$sql = "SELECT r.".str_replace(', ', ', r.', personnel_cours_cols()).", c.ref as crref, c.heure_debut, c.heure_fin, cl.ref as cref, m.label_fr as m_fr, m.label_ar as m_ar,";
	$sql .= " e.ref as eref, e.nom_fr, e.nom_ar, mo.label_fr as motif_fr, mo.label_ar as motif_ar".$from;
	$sql .= " ORDER BY r.date_cours DESC, c.heure_debut DESC, cl.ref";
	if ($limit > 0) {
		$sql .= $db->plimit($limit, $offset);
	}
	$out = array();
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[] = $o;
	}
	return $out;
}

/**
 * Lignes de la liste des absences du personnel.
 *
 * @param  DoliDB $db     Handler base
 * @param  array  $f      Filtres
 * @param  int    $limit  0 = tout
 * @param  int    $offset Décalage
 * @param  int    $total  Nombre total (sortie)
 * @return array<int,object>
 */
function personnel_absences_liste($db, $f, $limit, $offset, &$total)
{
	$p = $db->prefix();
	$w = array("a.entity IN (".getEntity('ecole_absence_perso').")");
	if ($f['du'] !== '') {
		$w[] = "a.date_fin >= '".$db->escape($f['du'])."'";
	}
	if ($f['au'] !== '') {
		$w[] = "a.date_debut <= '".$db->escape($f['au'])."'";
	}
	if ($f['fk_employe'] > 0) {
		$w[] = "a.fk_employe = ".((int) $f['fk_employe']);
	}
	if ($f['employe'] !== '') {
		$w[] = natural_search(array('e.ref', 'e.nom_fr', 'e.nom_ar'), $f['employe'], 0, 1);
	}
	if ($f['categorie'] !== '' && isset(ecole_categories_utilisateur()[$f['categorie']])) {
		$w[] = "FIND_IN_SET('".$db->escape($f['categorie'])."', e.categories) > 0";
	}
	if (isset(personnel_durees()[$f['duree']])) {
		$w[] = "a.duree = '".$db->escape($f['duree'])."'";
	}
	if ($f['motif'] > 0) {
		$w[] = "a.fk_motif = ".((int) $f['motif']);
	}
	if ($f['etat'] === 'oui' || $f['etat'] === 'non') {
		$w[] = "a.justifiee = ".($f['etat'] === 'oui' ? 1 : 0);
	}
	if ($f['statut'] === 'annulee') {
		$w[] = "a.status = 0";
	} elseif ($f['statut'] !== 'toutes') {
		$w[] = "a.status = 1";
	}
	$from = " FROM ".$p."ecole_absence_perso a INNER JOIN ".$p."ecole_employe e ON e.rowid = a.fk_employe";
	$from .= " LEFT JOIN ".$p."ecole_employe_motif mo ON mo.rowid = a.fk_motif WHERE ".implode(' AND ', $w);

	$total = 0;
	$resql = $db->query("SELECT COUNT(*) as nb".$from);
	if ($resql && ($o = $db->fetch_object($resql))) {
		$total = (int) $o->nb;
	}
	$sql = "SELECT a.rowid, a.fk_employe, a.date_debut, a.date_fin, a.duree, a.justifiee, a.fk_motif, a.note, a.status, a.motif_annulation, a.date_annulation, a.fk_user_annul,";
	$sql .= " a.date_creation, a.fk_user_creat, e.ref, e.nom_fr, e.nom_ar, e.categories, e.telephone, mo.label_fr as motif_fr, mo.label_ar as motif_ar".$from;
	$sql .= " ORDER BY a.date_debut DESC, a.rowid DESC";
	if ($limit > 0) {
		$sql .= $db->plimit($limit, $offset);
	}
	$out = array();
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$o->date_debut = substr($o->date_debut, 0, 10);
		$o->date_fin = substr($o->date_fin, 0, 10);
		$out[] = $o;
	}
	return $out;
}

/**
 * Nombre de jours ouvrables d'une absence (demi-journée = 0,5).
 *
 * @param  object $a Absence
 * @return float
 */
function personnel_absence_nb_jours($a)
{
	$ouv = ecole_jours_ouvrables();
	$n = 0;
	foreach (personnel_dates($a->date_debut, $a->date_fin) as $d => $j) {
		if (in_array($j, $ouv, true)) {
			$n++;
		}
	}
	return ($a->duree === 'jour') ? $n : $n * 0.5;
}

/**
 * Période d'une absence en texte : « 24/09/2026 », « 24/09/2026 (matin) », « du 24/09 au 26/09/2026 ».
 *
 * @param  object $a Absence
 * @return string
 */
function personnel_absence_periode_label($a)
{
	global $langs;
	if ($a->date_debut === $a->date_fin) {
		$out = dol_print_date(personnel_date_ts($a->date_debut), 'day');
		if ($a->duree !== 'jour') {
			$out .= ' ('.$langs->trans(personnel_durees()[$a->duree]).')';
		}
		return $out;
	}
	return $langs->trans('DuAu', dol_print_date(personnel_date_ts($a->date_debut), 'day'), dol_print_date(personnel_date_ts($a->date_fin), 'day'));
}

/**
 * Employés pour le récapitulatif du mois (hors partis avant le mois), filtrés.
 *
 * @param  DoliDB $db   Handler base
 * @param  array  $f    Filtres (employe, categorie, mode)
 * @param  string $mois AAAA-MM
 * @return EcoleEmploye[]
 */
function personnel_employes_mois($db, $f, $mois)
{
	list($d1) = personnel_mois_bornes($mois);
	$w = array("t.entity IN (".getEntity('ecole_employe').")", "(t.status <> ".EcoleEmploye::STATUS_PARTI." OR t.date_statut >= '".$db->escape($d1)."')");
	if ($f['employe'] !== '') {
		$w[] = natural_search(array('t.ref', 't.nom_fr', 't.nom_ar'), $f['employe'], 0, 1);
	}
	if ($f['categorie'] !== '' && isset(ecole_categories_utilisateur()[$f['categorie']])) {
		$w[] = "FIND_IN_SET('".$db->escape($f['categorie'])."', t.categories) > 0";
	}
	if ($f['mode'] === 'fixe' || $f['mode'] === 'heure') {
		$w[] = "t.mode_paie = '".$db->escape($f['mode'])."'";
	} elseif ($f['mode'] === 'aucun') {
		$w[] = "(t.mode_paie IS NULL OR t.mode_paie = '')";
	}
	$e = new EcoleEmploye($db);
	$list = $e->fetchAllObjects('t.nom_fr', 'ASC', 0, 0, array(), implode(' AND ', array_slice($w, 1)));
	return is_array($list) ? $list : array();
}

/* ------------------------------------------------------------------
 * Emploi du temps d'un enseignant
 * ---------------------------------------------------------------- */

/**
 * Emploi du temps de la semaine d'un enseignant (toutes ses classes), même présentation que celui d'une classe.
 *
 * @param  DoliDB $db      Handler base
 * @param  int    $fk_user Utilisateur Dolibarr de l'enseignant
 * @return array{columns:array,bands:array,events:array,minutes:int}
 */
function personnel_edt_enseignant_data($db, $fk_user)
{
	$creneaux = ecole_creneaux_actifs($db);
	list($columns, $bands) = ecole_edt_semaine($creneaux);
	$salles = ecole_salles($db);
	$events = array();
	$minutes = 0;
	if ((int) $fk_user > 0) {
		$sql = "SELECT e.jour, e.fk_creneau, e.fk_classe, e.fk_matiere, e.fk_salle, c.ref as cref, m.label_fr, m.label_ar";
		$sql .= " FROM ".$db->prefix()."ecole_edt_cours e INNER JOIN ".$db->prefix()."ecole_classe c ON c.rowid = e.fk_classe";
		$sql .= " INNER JOIN ".$db->prefix()."ecole_matiere m ON m.rowid = e.fk_matiere WHERE e.fk_user = ".((int) $fk_user);
		$resql = $db->query($sql);
		while ($resql && ($o = $db->fetch_object($resql))) {
			if (!isset($columns[(int) $o->jour]) || !isset($creneaux[(int) $o->fk_creneau])) {
				continue;
			}
			$cr = $creneaux[(int) $o->fk_creneau];
			$minutes += max(0, ecole_hhmm_to_min($cr->heure_fin) - ecole_hhmm_to_min($cr->heure_debut));
			$events[] = array(
				'col' => (int) $o->jour,
				'start' => $cr->heure_debut,
				'end' => $cr->heure_fin,
				'title' => $o->cref,
				'lines' => array(ecole_label($o), isset($salles[(int) $o->fk_salle]) ? $salles[(int) $o->fk_salle] : ''),
				'color' => (int) $o->fk_classe,
			);
		}
	}
	return array('columns' => $columns, 'bands' => $bands, 'events' => $events, 'minutes' => $minutes);
}

/**
 * Matières et classes qu'un enseignant a dans l'emploi du temps : lignes (classe, matière, minutes par semaine).
 *
 * @param  DoliDB $db      Handler base
 * @param  int    $fk_user Utilisateur Dolibarr de l'enseignant
 * @return array<int,object>
 */
function personnel_cours_enseignes($db, $fk_user)
{
	$out = array();
	if ((int) $fk_user <= 0) {
		return $out;
	}
	$p = $db->prefix();
	$sql = "SELECT e.fk_classe, e.fk_matiere, cl.ref as cref, cl.label_fr as cl_fr, cl.label_ar as cl_ar, m.ref as mref, m.label_fr, m.label_ar,";
	$sql .= " SUM(TIME_TO_SEC(TIMEDIFF(c.heure_fin, c.heure_debut)) / 60) as minutes, COUNT(*) as nb";
	$sql .= " FROM ".$p."ecole_edt_cours e INNER JOIN ".$p."ecole_creneau c ON c.rowid = e.fk_creneau AND c.status = 1";
	$sql .= " INNER JOIN ".$p."ecole_classe cl ON cl.rowid = e.fk_classe INNER JOIN ".$p."ecole_matiere m ON m.rowid = e.fk_matiere";
	$sql .= " WHERE e.fk_user = ".((int) $fk_user)." GROUP BY e.fk_classe, e.fk_matiere, cl.ref, cl.label_fr, cl.label_ar, m.ref, m.label_fr, m.label_ar ORDER BY cl.ref, m.label_fr";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[] = $o;
	}
	return $out;
}

/* ------------------------------------------------------------------
 * Filtres des listes et jeux de données des exports
 * ---------------------------------------------------------------- */

/**
 * Filtres de la liste des cours signalés.
 *
 * @return string[]
 */
function personnel_cours_filtres_cles()
{
	return array('du', 'au', 'creneau', 'classe', 'matiere', 'employe', 'fk_employe', 'type', 'etat', 'source');
}

/**
 * Filtres de la liste des absences du personnel.
 *
 * @return string[]
 */
function personnel_absences_filtres_cles()
{
	return array('du', 'au', 'employe', 'fk_employe', 'categorie', 'duree', 'etat', 'motif', 'statut');
}

/**
 * Filtres du récapitulatif des heures du mois.
 *
 * @return string[]
 */
function personnel_heures_filtres_cles()
{
	return array('employe', 'categorie', 'mode');
}

/**
 * Classes pour un filtre : id => référence (ordre des niveaux).
 *
 * @param  DoliDB $db Handler base
 * @return array<int,string>
 */
function personnel_classes_filtre($db)
{
	$out = array();
	$sql = "SELECT c.rowid, c.ref FROM ".$db->prefix()."ecole_classe c LEFT JOIN ".$db->prefix()."ecole_niveau n ON n.rowid = c.fk_niveau";
	$sql .= " WHERE c.entity IN (".getEntity('ecole_classe').") ORDER BY n.position, c.rowid";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[(int) $o->rowid] = $o->ref;
	}
	return $out;
}

/**
 * Sous-titre d'un export : filtres appliqués + nombre de lignes.
 *
 * @param  array  $f  Filtres
 * @param  int    $nb Nombre de lignes
 * @return string
 */
function personnel_export_sous_titre($f, $nb)
{
	global $langs;
	$parts = array();
	if (!empty($f['du']) || !empty($f['au'])) {
		$parts[] = trim((!empty($f['du']) ? dol_print_date(personnel_date_ts($f['du']), 'day') : '…').' → '.(!empty($f['au']) ? dol_print_date(personnel_date_ts($f['au']), 'day') : '…'));
	}
	if (!empty($f['employe'])) {
		$parts[] = $f['employe'];
	}
	if (!empty($f['categorie'])) {
		$parts[] = personnel_categories_texte($f['categorie']);
	}
	$parts[] = ecole_pdf_trans($langs, 'NbEnregistrements', $nb);
	return implode(' — ', $parts);
}

/**
 * Jeu de données « Cours signalés » (export PDF / Excel, mêmes filtres que l'écran).
 *
 * @param  DoliDB $db Handler base
 * @return array
 */
function personnel_export_dataset_cours($db)
{
	global $langs;
	$fl = personnel_filtres(personnel_cours_filtres_cles());
	$total = 0;
	$rows = array();
	foreach (personnel_cours_liste($db, $fl['f'], 0, 0, $total) as $r) {
		$d = substr($r->date_cours, 0, 10);
		$etat = personnel_cours_code_label(personnel_cours_code($db, $r));
		$just = in_array((int) $r->etat, array(PERSONNEL_ABSENT, PERSONNEL_RETARD), true)
			? ecole_pdf_trans($langs, (int) $r->justifiee ? 'Justifiee' : 'NonJustifiee').($r->motif_fr ? ' — '.ecole_label((object) array('label_fr' => $r->motif_fr, 'label_ar' => $r->motif_ar)) : '') : '';
		$rows[] = array(dol_print_date(personnel_date_ts($d), 'day'), $r->crref.' '.$r->heure_debut, (string) $r->cref, ecole_label((object) array('label_fr' => $r->m_fr, 'label_ar' => $r->m_ar)),
			trim($r->eref.' '.ecole_label($r)), $etat, $just, trim((string) $r->sujet.((string) $r->note !== '' ? ' — '.$r->note : '')));
	}
	return array(
		'title' => ecole_pdf_trans($langs, 'CoursSignales'),
		'subtitle' => personnel_export_sous_titre($fl['f'], count($rows)),
		'ref' => 'COURS-'.dol_print_date(dol_now(), '%Y%m%d'),
		'headers' => array(ecole_pdf_trans($langs, 'Date'), ecole_pdf_trans($langs, 'Creneau'), ecole_pdf_trans($langs, 'Classe'), ecole_pdf_trans($langs, 'Matiere'),
			ecole_pdf_trans($langs, 'Enseignant'), ecole_pdf_trans($langs, 'Presence'), ecole_pdf_trans($langs, 'Justification'), ecole_pdf_trans($langs, 'SujetOuRemarque')),
		'ratios' => array(1.1, 1, 0.8, 1.5, 2.2, 2.2, 1.6, 2.2),
		'aligns' => array('C', 'C', 'C', 'L', 'L', 'L', 'L', 'L'),
		'rows' => $rows,
		'filename' => 'cours_signales',
	);
}

/**
 * Jeu de données « Absences du personnel » (export PDF / Excel, mêmes filtres que l'écran).
 *
 * @param  DoliDB $db Handler base
 * @return array
 */
function personnel_export_dataset_absences($db)
{
	global $langs;
	$fl = personnel_filtres(personnel_absences_filtres_cles());
	$total = 0;
	$rows = array();
	foreach (personnel_absences_liste($db, $fl['f'], 0, 0, $total) as $a) {
		$just = !(int) $a->status ? ecole_pdf_trans($langs, 'Annulee') : ecole_pdf_trans($langs, (int) $a->justifiee ? 'Justifiee' : 'NonJustifiee');
		$rows[] = array($a->ref, ecole_label($a), personnel_categories_texte((string) $a->categories), personnel_absence_periode_label($a), (string) price2num(personnel_absence_nb_jours($a)),
			$just, $a->motif_fr ? ecole_label((object) array('label_fr' => $a->motif_fr, 'label_ar' => $a->motif_ar)) : '', !(int) $a->status ? (string) $a->motif_annulation : (string) $a->note);
	}
	return array(
		'title' => ecole_pdf_trans($langs, 'AbsencesPersonnel'),
		'subtitle' => personnel_export_sous_titre($fl['f'], count($rows)),
		'ref' => 'ABS-'.dol_print_date(dol_now(), '%Y%m%d'),
		'headers' => array(ecole_pdf_trans($langs, 'MatriculeEmploye'), ecole_pdf_trans($langs, 'NomComplet'), ecole_pdf_trans($langs, 'CategoriesEmploye'), ecole_pdf_trans($langs, 'Periode'),
			ecole_pdf_trans($langs, 'NombreJours'), ecole_pdf_trans($langs, 'Justification'), ecole_pdf_trans($langs, 'Motif'), ecole_pdf_trans($langs, 'Remarque')),
		'ratios' => array(0.9, 2.2, 1.5, 2, 0.8, 1.2, 1.5, 2),
		'aligns' => array('C', 'L', 'L', 'C', 'C', 'C', 'L', 'L'),
		'rows' => $rows,
		'filename' => 'absences_personnel',
	);
}

/**
 * Lignes du récapitulatif des heures du mois (une par employé).
 *
 * @param  DoliDB $db   Handler base
 * @param  array  $f    Filtres
 * @param  string $mois AAAA-MM
 * @return array<int,array{emp:EcoleEmploye,h:array}>
 */
function personnel_heures_lignes($db, $f, $mois)
{
	$out = array();
	foreach (personnel_employes_mois($db, $f, $mois) as $emp) {
		$out[] = array('emp' => $emp, 'h' => personnel_heures_mois($db, $emp, $mois));
	}
	return $out;
}

/**
 * Jeu de données « Heures du mois » (export PDF / Excel, mêmes filtres que l'écran).
 *
 * @param  DoliDB $db   Handler base
 * @param  string $mois AAAA-MM
 * @return array
 */
function personnel_export_dataset_heures($db, $mois)
{
	global $langs, $user;
	$fl = personnel_filtres(personnel_heures_filtres_cles());
	$paie = $user->hasRight('personnel', 'paie', 'lire');
	$rows = array();
	$modes = array('fixe' => ecole_pdf_trans($langs, 'PaieFixe'), 'heure' => ecole_pdf_trans($langs, 'PaieHeure'));
	foreach (personnel_heures_lignes($db, $fl['f'], $mois) as $l) {
		$e = $l['emp'];
		$t = $l['h']['totaux'];
		$row = array($e->ref, ecole_label($e), personnel_categories_texte((string) $e->categories), isset($modes[$e->mode_paie]) ? $modes[$e->mode_paie] : '',
			personnel_duree($t['prevues']), personnel_duree($t['faites']), $t['nb_absences'] ? personnel_duree($t['absent_nj']).' / '.personnel_duree($t['absent_j']) : '',
			$t['nb_retards'] ? $t['nb_retards'].' ('.$t['retard_min'].' min)' : '', $t['nb_remplacements'] ? personnel_duree($t['remplacement']) : '',
			$t['heures_sup'] ? personnel_duree($t['heures_sup']) : '', ($t['jours_absent_j'] + $t['jours_absent_nj']) ? price2num($t['jours_absent_nj']).' / '.price2num($t['jours_absent_j']) : '');
		if ($paie) {
			$row[] = $l['h']['estimation']['possible'] ? price($l['h']['estimation']['total']) : '';
		}
		$rows[] = $row;
	}
	$headers = array(ecole_pdf_trans($langs, 'MatriculeEmploye'), ecole_pdf_trans($langs, 'NomComplet'), ecole_pdf_trans($langs, 'CategoriesEmploye'), ecole_pdf_trans($langs, 'ModePaie'),
		ecole_pdf_trans($langs, 'CoursPrevus'), ecole_pdf_trans($langs, 'CoursFaits'), ecole_pdf_trans($langs, 'AbsencesNjJ'), ecole_pdf_trans($langs, 'Retards'),
		ecole_pdf_trans($langs, 'RemplacementsFaits'), ecole_pdf_trans($langs, 'HeuresSup'), ecole_pdf_trans($langs, 'JoursAbsenceNjJ'));
	$ratios = array(0.9, 2, 1.4, 0.9, 0.9, 0.9, 1.1, 1, 1, 0.9, 1);
	$aligns = array('C', 'L', 'L', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C');
	if ($paie) {
		$headers[] = ecole_pdf_trans($langs, 'EstimationMois');
		$ratios[] = 1.1;
		$aligns[] = 'R';
	}
	return array(
		'title' => ecole_pdf_trans($langs, 'HeuresDuMois').' — '.personnel_mois_label($mois),
		'subtitle' => personnel_export_sous_titre($fl['f'], count($rows)),
		'ref' => 'HEURES-'.$mois,
		'headers' => $headers,
		'ratios' => $ratios,
		'aligns' => $aligns,
		'rows' => $rows,
		'filename' => 'heures_'.$mois,
	);
}

/**
 * Jeu de données « Présence du mois » d'un employé (cours et absences).
 *
 * @param  DoliDB       $db   Handler base
 * @param  EcoleEmploye $emp  Employé
 * @param  string       $mois AAAA-MM
 * @return array
 */
function personnel_export_dataset_presence_employe($db, $emp, $mois)
{
	global $langs, $user;
	$h = personnel_heures_mois($db, $emp, $mois);
	$t = $h['totaux'];
	$rows = array();
	$etats = array('present' => 'Present', 'declare' => 'CoursDeclare', 'absent' => 'Absent', 'retard' => 'EnRetard', 'avenir' => 'AVenir', 'sanscours' => 'JourSansCours',
		'suspendu' => 'StatutSuspenduConge', 'remplacement' => 'Remplacement');
	foreach ($h['seances'] as $s) {
		$etat = ecole_pdf_trans($langs, $etats[$s->etat]);
		if ($s->etat === 'retard') {
			$etat .= ' ('.$s->retard.' min)';
		} elseif ($s->etat === 'absent') {
			$etat .= ' — '.ecole_pdf_trans($langs, $s->justifiee ? 'Justifiee' : 'NonJustifiee');
		}
		$rows[] = array(dol_print_date(personnel_date_ts($s->date), 'day'), $s->crref.' '.$s->heure_debut.'-'.$s->heure_fin, (string) $s->cref, $s->matiere, $etat,
			$s->rec ? trim((string) $s->rec->sujet) : '', personnel_duree($s->minutes));
	}
	list($d1, $d2) = personnel_mois_bornes($mois);
	foreach (personnel_absences_periode($db, $d1, $d2, (int) $emp->id) as $a) {
		$rows[] = array(personnel_absence_periode_label($a), '', '', ecole_pdf_trans($langs, 'AbsencePersonnel'), ecole_pdf_trans($langs, (int) $a->justifiee ? 'Justifiee' : 'NonJustifiee'),
			(string) $a->note, price2num(personnel_absence_nb_jours($a)).' '.ecole_pdf_trans($langs, 'JoursCourt'));
	}
	$sous = $emp->ref.' '.ecole_label($emp).' — '.ecole_pdf_trans($langs, 'CoursFaits').' : '.personnel_duree($t['faites']).' / '.personnel_duree($t['prevues']);
	if ($user->hasRight('personnel', 'paie', 'lire') && $h['estimation']['possible']) {
		$sous .= ' — '.ecole_pdf_trans($langs, 'EstimationMois').' : '.price($h['estimation']['total']);
	}
	return array(
		'title' => ecole_pdf_trans($langs, 'PresenceHeures').' — '.personnel_mois_label($mois),
		'subtitle' => $sous,
		'ref' => 'PRES-'.$emp->ref.'-'.$mois,
		'headers' => array(ecole_pdf_trans($langs, 'Date'), ecole_pdf_trans($langs, 'Creneau'), ecole_pdf_trans($langs, 'Classe'), ecole_pdf_trans($langs, 'Matiere'),
			ecole_pdf_trans($langs, 'Presence'), ecole_pdf_trans($langs, 'SujetOuRemarque'), ecole_pdf_trans($langs, 'Duree')),
		'ratios' => array(1.4, 1.4, 0.8, 1.6, 1.8, 2.4, 0.8),
		'aligns' => array('C', 'C', 'C', 'L', 'L', 'L', 'C'),
		'rows' => $rows,
		'filename' => 'presence_'.$emp->ref.'_'.$mois,
	);
}
