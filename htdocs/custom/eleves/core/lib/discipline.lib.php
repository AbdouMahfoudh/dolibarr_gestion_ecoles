<?php
/**
 * Absences, retards et sanctions des élèves (étape 3).
 *
 * Outil de SUIVI seulement : rien n'est obligatoire, rien ne bloque, aucun effet sur les notes, bulletins
 * ou paiements. Un créneau sans appel = tous les élèves présents (sauf les exclus). Seule exception :
 * une exclusion définitive fait passer l'élève au statut « exclu » (arrêt des mensualités).
 *
 * - Appel : une classe, un jour, un créneau (selon l'emploi du temps de la classe ; tous les créneaux si
 *   la classe n'a pas encore d'emploi du temps). Tout le monde est présent par défaut, on coche les absents
 *   et les retards (heure d'arrivée facultative), puis on valide.
 * - Correction d'un appel déjà validé : droit « modifier », chaque changement est gardé (qui, quand, avant, après).
 * - Justification : case + motif (liste configurable), droit « justifier ».
 * - Seuils réglables (absences / retards non justifiés) : élèves signalés.
 *
 * Fichier : custom/eleves/core/lib/discipline.lib.php
 */

dol_include_once('/classes/core/lib/edt.lib.php');
dol_include_once('/eleves/class/ecole_eleve.class.php');
dol_include_once('/eleves/class/ecole_motif_absence.class.php');
dol_include_once('/eleves/class/ecole_sanction_type.class.php');

/** Présent (aucune ligne enregistrée) */
define('ELEVES_PRESENT', 0);
/** Absent */
define('ELEVES_ABSENT', 1);
/** En retard */
define('ELEVES_RETARD', 2);
/** Renvoyé du cours par le professeur : compté comme une absence simple (ce n'est pas une sanction) */
define('ELEVES_RENVOYE', 3);

/* ------------------------------------------------------------------
 * Dates et créneaux
 * ---------------------------------------------------------------- */

/**
 * Date AAAA-MM-JJ valide, sinon ''.
 *
 * @param  string $s Date
 * @return string
 */
function eleves_date_ok($s)
{
	$s = trim((string) $s);
	if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
		return '';
	}
	return $s;
}

/**
 * Horodatage (midi) d'une date AAAA-MM-JJ.
 *
 * @param  string $d Date
 * @return int
 */
function eleves_date_ts($d)
{
	$p = explode('-', $d);
	return dol_mktime(12, 0, 0, (int) $p[1], (int) $p[2], (int) $p[0]);
}

/**
 * Date du jour AAAA-MM-JJ (fuseau de l'utilisateur).
 *
 * @return string
 */
function eleves_aujourdhui()
{
	return dol_print_date(dol_now(), '%Y-%m-%d', 'tzuserrel');
}

/**
 * Libellé d'une date : « Lundi 24/09/2026 ».
 *
 * @param  string $d Date AAAA-MM-JJ
 * @return string
 */
function eleves_date_label($d)
{
	global $langs;
	$jours = ecole_jours();
	$ts = eleves_date_ts($d);
	return $langs->trans($jours[(int) date('N', strtotime($d))]).' '.dol_print_date($ts, 'day');
}

/**
 * Créneaux où l'appel se fait pour une classe à une date : ceux où la classe a cours ce jour-là selon
 * son emploi du temps. Si la classe n'a encore aucun cours dans son emploi du temps, tous les créneaux
 * actifs sont proposés. Jour non ouvrable : aucun créneau.
 *
 * @param  DoliDB $db       Handler base
 * @param  int    $fk_classe Classe
 * @param  string $date     Date AAAA-MM-JJ
 * @return array<int,array{creneau:object,matiere:string,enseignant:string}>  id créneau => infos
 */
function eleves_appel_creneaux($db, $fk_classe, $date)
{
	static $cache = array();
	$key = $fk_classe.'|'.$date;
	if (isset($cache[$key])) {
		return $cache[$key];
	}
	$creneaux = ecole_creneaux_actifs($db);
	$jour = (int) date('N', strtotime($date));
	$out = array();
	if (!in_array($jour, ecole_jours_ouvrables(), true)) {
		return $cache[$key] = $out;
	}
	$sql = "SELECT e.jour, e.fk_creneau, e.fk_user, e.fk_matiere, m.label_fr, m.label_ar FROM ".$db->prefix()."ecole_edt_cours e";
	$sql .= " INNER JOIN ".$db->prefix()."ecole_matiere m ON m.rowid = e.fk_matiere WHERE e.fk_classe = ".((int) $fk_classe);
	$resql = $db->query($sql);
	$aedt = false;
	while ($resql && ($o = $db->fetch_object($resql))) {
		if (!isset($creneaux[(int) $o->fk_creneau])) {
			continue;
		}
		$aedt = true;
		if ((int) $o->jour === $jour) {
			$out[(int) $o->fk_creneau] = array('creneau' => $creneaux[(int) $o->fk_creneau], 'matiere' => ecole_label($o), 'fk_matiere' => (int) $o->fk_matiere,
				'enseignant' => $o->fk_user > 0 ? ecole_user_label($db, $o->fk_user) : '');
		}
	}
	if (!$aedt) {
		foreach ($creneaux as $cid => $c) {
			$out[$cid] = array('creneau' => $c, 'matiere' => '', 'fk_matiere' => 0, 'enseignant' => '');
		}
	}
	// Dans l'ordre de la journée
	uasort($out, function ($a, $b) {
		return strcmp($a['creneau']->heure_debut, $b['creneau']->heure_debut);
	});
	return $cache[$key] = $out;
}

/**
 * Créneau proposé par défaut : celui en cours, sinon le prochain de la journée, sinon le dernier.
 *
 * @param  array $creneaux id => infos (eleves_appel_creneaux) ou id => ligne de créneau
 * @return int             0 si aucun
 */
function eleves_creneau_courant($creneaux)
{
	$now = dol_print_date(dol_now(), '%H:%M', 'tzuserrel');
	$last = 0;
	foreach ($creneaux as $cid => $c) {
		$c = is_array($c) ? $c['creneau'] : $c;
		if ($now < $c->heure_fin) {
			return (int) $cid;
		}
		$last = (int) $cid;
	}
	return $last;
}

/**
 * Libellé court d'un créneau : « S1 (08:15-10:00) ».
 *
 * @param  object $c Ligne de créneau
 * @return string
 */
function eleves_creneau_label($c)
{
	return $c->ref.' ('.$c->heure_debut.'-'.$c->heure_fin.')';
}

/* ------------------------------------------------------------------
 * Élèves d'un appel
 * ---------------------------------------------------------------- */

/**
 * Exclusions temporaires en cours à une date : id élève => sanction (type, date de fin).
 *
 * @param  DoliDB $db   Handler base
 * @param  string $date Date AAAA-MM-JJ
 * @return array<int,object>
 */
function eleves_exclus_date($db, $date)
{
	$out = array();
	$sql = "SELECT s.rowid, s.fk_eleve, s.date_sanction, s.date_fin, t.label_fr, t.label_ar FROM ".$db->prefix()."ecole_sanction s";
	$sql .= " INNER JOIN ".$db->prefix()."ecole_sanction_type t ON t.rowid = s.fk_type";
	$sql .= " WHERE s.entity IN (".getEntity('ecole_sanction').") AND s.status = 1 AND t.exclusion = ".EcoleSanctionType::EXCLUSION_TEMPORAIRE;
	$sql .= " AND s.date_sanction <= '".$db->escape($date)."' AND (s.date_fin IS NULL OR s.date_fin >= '".$db->escape($date)."')";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[(int) $o->fk_eleve] = $o;
	}
	return $out;
}

/**
 * Élèves d'une classe à une date (période de classe qui couvre la date, élève inscrit, suspendu ou sorti
 * après cette date), triés par nom. Chaque ligne porte 'etat_special' : '' | 'exclu' | 'suspendu'.
 *
 * @param  DoliDB $db        Handler base
 * @param  int    $fk_classe Classe
 * @param  string $date      Date AAAA-MM-JJ
 * @return array<int,object> id élève => ligne (rowid, ref, nom_fr, nom_ar, status, fk_responsable, etat_special)
 */
function eleves_appel_eleves($db, $fk_classe, $date)
{
	$exclus = eleves_exclus_date($db, $date);
	$out = array();
	$sql = "SELECT DISTINCT e.rowid, e.ref, e.nom_fr, e.nom_ar, e.status, e.fk_responsable, e.numero_appel, r.nom_fr as rnom_fr, r.nom_ar as rnom_ar, r.telephone, r.whatsapp";
	$sql .= " FROM ".$db->prefix()."ecole_eleve e INNER JOIN ".$db->prefix()."ecole_eleve_classe h ON h.fk_eleve = e.rowid";
	$sql .= " LEFT JOIN ".$db->prefix()."ecole_responsable r ON r.rowid = e.fk_responsable";
	$sql .= " WHERE e.entity IN (".getEntity('ecole_eleve').") AND h.fk_classe = ".((int) $fk_classe);
	$sql .= " AND h.date_debut <= '".$db->escape($date)."' AND (h.date_fin IS NULL OR h.date_fin >= '".$db->escape($date)."')";
	$sql .= " AND e.status NOT IN (".EcoleEleve::STATUS_PREINSCRIT.", ".EcoleEleve::STATUS_ATTENTE.")";
	$sql .= " ORDER BY (e.numero_appel IS NULL), e.numero_appel, e.nom_fr";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$o->etat_special = '';
		if (isset($exclus[(int) $o->rowid])) {
			$o->etat_special = 'exclu';
		} elseif ((int) $o->status === EcoleEleve::STATUS_SUSPENDU) {
			$o->etat_special = 'suspendu';
		}
		$out[(int) $o->rowid] = $o;
	}
	return $out;
}

/* ------------------------------------------------------------------
 * Appels
 * ---------------------------------------------------------------- */

/**
 * Appel déjà fait pour une classe, une date et un créneau.
 *
 * @param  DoliDB $db         Handler base
 * @param  int    $fk_classe  Classe
 * @param  string $date       Date AAAA-MM-JJ
 * @param  int    $fk_creneau Créneau
 * @return object|null
 */
function eleves_appel_fetch($db, $fk_classe, $date, $fk_creneau)
{
	$sql = "SELECT rowid, fk_classe, date_appel, fk_creneau, fk_matiere, nb_absents, nb_retards, date_creation, tms, fk_user_creat, fk_user_modif FROM ".$db->prefix()."ecole_appel";
	$sql .= " WHERE entity IN (".getEntity('ecole_appel').") AND fk_classe = ".((int) $fk_classe)." AND date_appel = '".$db->escape($date)."' AND fk_creneau = ".((int) $fk_creneau);
	$resql = $db->query($sql);
	return ($resql && ($o = $db->fetch_object($resql))) ? $o : null;
}

/**
 * Appels faits un jour donné : « classe|créneau » => appel.
 *
 * @param  DoliDB $db   Handler base
 * @param  string $date Date AAAA-MM-JJ
 * @return array<string,object>
 */
function eleves_appels_du_jour($db, $date)
{
	$out = array();
	$sql = "SELECT rowid, fk_classe, fk_creneau, nb_absents, nb_retards, fk_user_creat FROM ".$db->prefix()."ecole_appel";
	$sql .= " WHERE entity IN (".getEntity('ecole_appel').") AND date_appel = '".$db->escape($date)."'";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[$o->fk_classe.'|'.$o->fk_creneau] = $o;
	}
	return $out;
}

/**
 * Lignes (absents et retards) d'un appel : id élève => ligne.
 *
 * @param  DoliDB $db       Handler base
 * @param  int    $fk_appel Appel
 * @return array<int,object>
 */
function eleves_appel_lignes($db, $fk_appel)
{
	$out = array();
	$resql = $db->query("SELECT rowid, fk_eleve, type, heure_arrivee, justifiee, fk_motif FROM ".$db->prefix()."ecole_absence WHERE fk_appel = ".((int) $fk_appel));
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[(int) $o->fk_eleve] = $o;
	}
	return $out;
}

/**
 * Code court d'un état (historique des corrections) : P, A, R ou R|HH:MM.
 *
 * @param  int    $type  Type
 * @param  string $heure Heure d'arrivée
 * @return string
 */
function eleves_presence_code($type, $heure = '')
{
	if ((int) $type === ELEVES_ABSENT) {
		return 'A';
	}
	if ((int) $type === ELEVES_RETARD) {
		return 'R'.((string) $heure !== '' ? '|'.$heure : '');
	}
	if ((int) $type === ELEVES_RENVOYE) {
		return 'X';
	}
	return 'P';
}

/**
 * Libellé d'un état : « Présent », « Absent », « En retard (08:35) ».
 *
 * @param  string $code Code (eleves_presence_code)
 * @return string
 */
function eleves_presence_label($code)
{
	global $langs;
	$p = explode('|', (string) $code);
	if ($p[0] === 'A') {
		return $langs->transnoentities('Absent');
	}
	if ($p[0] === 'R') {
		return $langs->transnoentities('EnRetard').(!empty($p[1]) ? ' ('.$p[1].')' : '');
	}
	if ($p[0] === 'X') {
		return $langs->transnoentities('RenvoyeCours');
	}
	return $langs->transnoentities('Present');
}

/**
 * Badge coloré d'une ligne d'absence : absent (rouge), en retard (orange), renvoyé du cours (violet).
 *
 * @param  object $l Ligne (type, heure_arrivee)
 * @return string    HTML
 */
function eleves_presence_badge($l)
{
	$css = array(ELEVES_ABSENT => 'badge-danger', ELEVES_RETARD => 'badge-warning', ELEVES_RENVOYE => 'badge-status6');
	$c = isset($css[(int) $l->type]) ? $css[(int) $l->type] : 'badge-status0';
	$style = ((int) $l->type === ELEVES_RENVOYE) ? ' style="background:#7b52ab;color:#fff"' : '';
	return '<span class="badge '.$c.'"'.$style.'>'.dol_escape_htmltag(eleves_presence_label(eleves_presence_code($l->type, (string) $l->heure_arrivee))).'</span>';
}

/**
 * Enregistre un appel (nouveau ou correction). Les élèves non cités restent inchangés.
 * Une correction garde chaque changement dans l'historique (qui, quand, avant, après).
 * La justification d'un absent qui reste absent (ou en retard) est conservée.
 *
 * @param  DoliDB $db         Handler base
 * @param  User   $user       Utilisateur
 * @param  int    $fk_classe  Classe
 * @param  string $date       Date AAAA-MM-JJ
 * @param  int    $fk_creneau Créneau
 * @param  array  $etats      id élève => array('type' => 0|1|2|3, 'heure' => 'HH:MM' ou '')
 * @param  string $error      Message d'erreur (sortie)
 * @param  int    $fk_matiere Matière du cours (emploi du temps), gardée avec l'appel
 * @return int                Id de l'appel, <0 si erreur
 */
function eleves_appel_enregistrer($db, $user, $fk_classe, $date, $fk_creneau, $etats, &$error, $fk_matiere = 0)
{
	global $conf;
	$p = $db->prefix();
	$now = "'".$db->idate(dol_now())."'";

	$db->begin();
	$appel = eleves_appel_fetch($db, $fk_classe, $date, $fk_creneau);
	$nouveau = ($appel === null);
	if ($nouveau) {
		$sql = "INSERT INTO ".$p."ecole_appel (entity, fk_classe, date_appel, fk_creneau, fk_matiere, date_creation, fk_user_creat)";
		$sql .= " VALUES (".((int) $conf->entity).", ".((int) $fk_classe).", '".$db->escape($date)."', ".((int) $fk_creneau).", ".($fk_matiere > 0 ? (int) $fk_matiere : "NULL").", ".$now.", ".((int) $user->id).")";
		if (!$db->query($sql)) {
			$error = $db->lasterror();
			$db->rollback();
			return -1;
		}
		$id = (int) $db->last_insert_id($p.'ecole_appel');
	} else {
		$id = (int) $appel->rowid;
	}

	$old = eleves_appel_lignes($db, $id);
	foreach ($etats as $eid => $new) {
		$eid = (int) $eid;
		$type = (int) $new['type'];
		if (!in_array($type, array(ELEVES_PRESENT, ELEVES_ABSENT, ELEVES_RETARD, ELEVES_RENVOYE), true)) {
			$type = ELEVES_PRESENT;
		}
		$heure = ($type === ELEVES_RETARD && ecole_hhmm_ok((string) $new['heure'])) ? (string) $new['heure'] : '';
		$o = isset($old[$eid]) ? $old[$eid] : null;
		$avant = $o ? eleves_presence_code($o->type, $o->heure_arrivee) : 'P';
		$apres = eleves_presence_code($type, $heure);
		if ($avant === $apres) {
			continue;
		}
		if ($type === ELEVES_PRESENT) {
			$sql = "DELETE FROM ".$p."ecole_absence WHERE rowid = ".((int) $o->rowid);
		} elseif ($o) {
			$sql = "UPDATE ".$p."ecole_absence SET type = ".$type.", heure_arrivee = ".($heure !== '' ? "'".$db->escape($heure)."'" : "NULL")." WHERE rowid = ".((int) $o->rowid);
		} else {
			$sql = "INSERT INTO ".$p."ecole_absence (entity, fk_appel, fk_eleve, fk_classe, date_appel, fk_creneau, type, heure_arrivee, date_creation, fk_user_creat)";
			$sql .= " VALUES (".((int) $conf->entity).", ".$id.", ".$eid.", ".((int) $fk_classe).", '".$db->escape($date)."', ".((int) $fk_creneau).", ".$type;
			$sql .= ", ".($heure !== '' ? "'".$db->escape($heure)."'" : "NULL").", ".$now.", ".((int) $user->id).")";
		}
		if (!$db->query($sql)) {
			$error = $db->lasterror();
			$db->rollback();
			return -1;
		}
		if (!$nouveau) {
			$sql = "INSERT INTO ".$p."ecole_absence_log (entity, fk_appel, fk_eleve, avant, apres, fk_user, date_creation)";
			$sql .= " VALUES (".((int) $conf->entity).", ".$id.", ".$eid.", '".$db->escape($avant)."', '".$db->escape($apres)."', ".((int) $user->id).", ".$now.")";
			if (!$db->query($sql)) {
				$error = $db->lasterror();
				$db->rollback();
				return -1;
			}
		}
	}

	// Compteurs de l'appel
	$sql = "UPDATE ".$p."ecole_appel SET nb_absents = (SELECT COUNT(*) FROM ".$p."ecole_absence WHERE fk_appel = ".$id." AND type IN (".ELEVES_ABSENT.", ".ELEVES_RENVOYE."))";
	$sql .= ", nb_retards = (SELECT COUNT(*) FROM ".$p."ecole_absence WHERE fk_appel = ".$id." AND type = ".ELEVES_RETARD.")";
	if (!$nouveau) {
		$sql .= ", fk_user_modif = ".((int) $user->id);
	}
	$sql .= " WHERE rowid = ".$id;
	if (!$db->query($sql)) {
		$error = $db->lasterror();
		$db->rollback();
		return -1;
	}
	$db->commit();
	return $id;
}

/**
 * Corrections d'un appel (plus récentes en premier).
 *
 * @param  DoliDB $db       Handler base
 * @param  int    $fk_appel Appel
 * @return array<int,object>
 */
function eleves_appel_corrections($db, $fk_appel)
{
	$out = array();
	$sql = "SELECT l.fk_eleve, l.avant, l.apres, l.fk_user, l.date_creation, e.ref, e.nom_fr, e.nom_ar FROM ".$db->prefix()."ecole_absence_log l";
	$sql .= " LEFT JOIN ".$db->prefix()."ecole_eleve e ON e.rowid = l.fk_eleve WHERE l.fk_appel = ".((int) $fk_appel)." ORDER BY l.date_creation DESC, l.rowid DESC";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[] = $o;
	}
	return $out;
}

/* ------------------------------------------------------------------
 * Justification
 * ---------------------------------------------------------------- */

/**
 * Justifie une absence ou un retard (ou retire la justification).
 *
 * @param  DoliDB $db        Handler base
 * @param  User   $user      Utilisateur
 * @param  int    $id        Ligne d'absence
 * @param  bool   $justifiee true = justifiée
 * @param  int    $fk_motif  Motif (obligatoire si justifiée)
 * @param  string $note      Remarque facultative
 * @param  string $error     Message d'erreur (sortie)
 * @return int               1 si OK, -1 sinon
 */
function eleves_absence_justifier($db, $user, $id, $justifiee, $fk_motif, $note, &$error)
{
	global $langs;
	if ($justifiee && $fk_motif <= 0) {
		$error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('MotifAbsence'));
		return -1;
	}
	$sql = "UPDATE ".$db->prefix()."ecole_absence SET justifiee = ".($justifiee ? 1 : 0);
	$sql .= ", fk_motif = ".($justifiee ? (int) $fk_motif : "NULL");
	$sql .= ", justif_note = ".($justifiee && trim($note) !== '' ? "'".$db->escape(dol_trunc(trim($note), 250, 'right', 'UTF-8', 1))."'" : "NULL");
	$sql .= ", fk_user_justif = ".((int) $user->id).", date_justif = '".$db->idate(dol_now())."'";
	$sql .= " WHERE rowid = ".((int) $id)." AND entity IN (".getEntity('ecole_absence').")";
	if (!$db->query($sql)) {
		$error = $db->lasterror();
		return -1;
	}
	return 1;
}

/**
 * Traite les actions « justifier » / « retirer la justification » d'une page (fiche élève, liste).
 * Formulaire : action=justifier|dejustifier, absid, fk_motif, justif_note, token.
 *
 * @param  DoliDB $db     Handler base
 * @param  User   $user   Utilisateur
 * @param  string $action Action demandée
 * @return bool           true si une action a été traitée
 */
function eleves_absence_actions($db, $user, $action)
{
	global $langs;
	if (!in_array($action, array('justifier', 'dejustifier'), true)) {
		return false;
	}
	if (!$user->hasRight('eleves', 'absence', 'justifier')) {
		accessforbidden();
	}
	$error = '';
	$ok = eleves_absence_justifier($db, $user, GETPOSTINT('absid'), $action === 'justifier', GETPOSTINT('fk_motif'), GETPOST('justif_note', 'alphanohtml'), $error);
	if ($ok > 0) {
		setEventMessages($langs->trans($action === 'justifier' ? 'AbsenceJustifiee' : 'JustificationRetiree'), null, 'mesgs');
	} else {
		setEventMessages($error, null, 'errors');
	}
	return true;
}

/**
 * Petit formulaire de justification (motif + remarque), affiché dans une ligne de tableau.
 *
 * @param  object $a       Ligne d'absence (rowid, justifiee, fk_motif, justif_note)
 * @param  string $action  Adresse de la page (paramètres de retour compris)
 * @param  array  $motifs  Motifs id => libellé
 * @return string          HTML
 */
function eleves_justifier_form($a, $action, $motifs)
{
	global $langs, $eleves_justifier_forms;
	// Les champs sont rattachés (attribut « form ») à un formulaire écrit plus loin par eleves_justifier_forms_flush() :
	// la ligne peut ainsi se trouver dans le formulaire des filtres d'une liste (formulaires imbriqués interdits).
	$fid = 'fjust'.((int) $a->rowid);
	$eleves_justifier_forms[] = '<form method="POST" id="'.$fid.'" action="'.dol_escape_htmltag($action).'"><input type="hidden" name="token" value="'.newToken().'">'
		.'<input type="hidden" name="absid" value="'.((int) $a->rowid).'"><input type="hidden" name="action" value="justifier"></form>';
	$out = '<span><select name="fk_motif" form="'.$fid.'" class="flat maxwidth100" required><option value="">'.dol_escape_htmltag($langs->trans('MotifAbsence')).'</option>';
	foreach ($motifs as $mid => $ml) {
		$out .= '<option value="'.((int) $mid).'"'.((int) $a->fk_motif === (int) $mid ? ' selected' : '').'>'.dol_escape_htmltag($ml).'</option>';
	}
	$out .= '</select> <input type="text" name="justif_note" form="'.$fid.'" class="flat maxwidth75" maxlength="250" placeholder="'.dol_escape_htmltag($langs->trans('RemarqueFacultative')).'" value="'.dol_escape_htmltag((string) $a->justif_note).'">';
	$out .= ' <input type="submit" form="'.$fid.'" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Justifier')).'"></span>';
	return $out;
}

/**
 * Écrit les formulaires de justification préparés par eleves_justifier_form() (après le formulaire de la page).
 *
 * @return void
 */
function eleves_justifier_forms_flush()
{
	global $eleves_justifier_forms;
	if (!empty($eleves_justifier_forms)) {
		print implode("\n", $eleves_justifier_forms);
	}
	$eleves_justifier_forms = array();
}

/**
 * Case « Justifiée » d'une ligne : badge + motif, et bouton pour justifier / retirer selon le droit.
 *
 * @param  object $a      Ligne d'absence (rowid, justifiee, fk_motif, justif_note, motif_fr, motif_ar)
 * @param  string $self   Adresse de la page (avec ses paramètres, sans action)
 * @param  array  $motifs Motifs id => libellé
 * @return string         HTML
 */
function eleves_justification_cell($a, $self, $motifs)
{
	global $langs, $user;
	$can = $user->hasRight('eleves', 'absence', 'justifier');
	$sep = (strpos($self, '?') === false) ? '?' : '&';
	if ((int) $a->justifiee) {
		$motif = ecole_label((object) array('label_fr' => $a->motif_fr, 'label_ar' => $a->motif_ar));
		$out = '<span class="badge badge-status4">'.$langs->trans('Justifiee').'</span> '.dol_escape_htmltag($motif);
		if ((string) $a->justif_note !== '') {
			$out .= ' <span class="opacitymedium small">— '.dol_escape_htmltag($a->justif_note).'</span>';
		}
		if ($can) {
			$out .= ' <a class="marginleftonly" href="'.dol_escape_htmltag($self.$sep.'action=dejustifier&absid='.((int) $a->rowid).'&token='.newToken()).'" title="'.dol_escape_htmltag($langs->trans('RetirerJustification')).'">'.img_picto($langs->trans('RetirerJustification'), 'fa-undo').'</a>';
		}
		return $out;
	}
	if (!$can) {
		return '<span class="opacitymedium">'.$langs->trans('NonJustifiee').'</span>';
	}
	return eleves_justifier_form($a, $self, $motifs);
}

/* ------------------------------------------------------------------
 * Compteurs et seuils
 * ---------------------------------------------------------------- */

/**
 * Seuils d'alerte (0 = pas d'alerte).
 *
 * @return array{absences:int,retards:int}
 */
function eleves_seuils()
{
	return array('absences' => max(0, getDolGlobalInt('ELEVES_SEUIL_ABSENCES', 10)), 'retards' => max(0, getDolGlobalInt('ELEVES_SEUIL_RETARDS', 5)));
}

/**
 * Compteurs par élève sur une période (dates facultatives) : absences, absences non justifiées,
 * retards, retards non justifiés, sanctions (non annulées).
 *
 * @param  DoliDB      $db     Handler base
 * @param  string      $where  Condition SQL sur les élèves (colonne fk_eleve), ex. « fk_eleve = 3 » ou « fk_classe = 5 »
 * @param  string      $du     Date de début AAAA-MM-JJ ('' = sans)
 * @param  string      $au     Date de fin AAAA-MM-JJ ('' = sans)
 * @return array<int,array{absences:int,absences_nj:int,retards:int,retards_nj:int,sanctions:int}>
 */
function eleves_compteurs($db, $where, $du = '', $au = '')
{
	$out = array();
	$vide = array('absences' => 0, 'absences_nj' => 0, 'retards' => 0, 'retards_nj' => 0, 'renvois' => 0, 'sanctions' => 0);
	$per = '';
	if ($du !== '') {
		$per .= " AND date_appel >= '".$db->escape($du)."'";
	}
	if ($au !== '') {
		$per .= " AND date_appel <= '".$db->escape($au)."'";
	}
	// Un élève renvoyé du cours compte comme une absence simple
	$sql = "SELECT fk_eleve, SUM(type IN (1, 3)) as a, SUM(type IN (1, 3) AND justifiee = 0) as anj, SUM(type = 2) as r, SUM(type = 2 AND justifiee = 0) as rnj, SUM(type = 3) as x";
	$sql .= " FROM ".$db->prefix()."ecole_absence WHERE entity IN (".getEntity('ecole_absence').") AND (".$where.")".$per." GROUP BY fk_eleve";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[(int) $o->fk_eleve] = array('absences' => (int) $o->a, 'absences_nj' => (int) $o->anj, 'retards' => (int) $o->r, 'retards_nj' => (int) $o->rnj, 'renvois' => (int) $o->x, 'sanctions' => 0);
	}
	$per = str_replace('date_appel', 'date_sanction', $per);
	$sql = "SELECT fk_eleve, COUNT(*) as nb FROM ".$db->prefix()."ecole_sanction WHERE entity IN (".getEntity('ecole_sanction').") AND status = 1 AND (".$where.")".$per." GROUP BY fk_eleve";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		if (!isset($out[(int) $o->fk_eleve])) {
			$out[(int) $o->fk_eleve] = $vide;
		}
		$out[(int) $o->fk_eleve]['sanctions'] = (int) $o->nb;
	}
	return $out;
}

/**
 * Compteurs d'un élève (toute l'année : une base = une année scolaire).
 *
 * @param  DoliDB $db       Handler base
 * @param  int    $fk_eleve Élève
 * @return array{absences:int,absences_nj:int,retards:int,retards_nj:int,sanctions:int}
 */
function eleves_compteurs_eleve($db, $fk_eleve)
{
	$c = eleves_compteurs($db, 'fk_eleve = '.((int) $fk_eleve));
	return isset($c[(int) $fk_eleve]) ? $c[(int) $fk_eleve] : array('absences' => 0, 'absences_nj' => 0, 'retards' => 0, 'retards_nj' => 0, 'renvois' => 0, 'sanctions' => 0);
}

/**
 * L'élève dépasse-t-il un seuil ? Renvoie les raisons (vide = non signalé).
 *
 * @param  array $c Compteurs
 * @return string[]
 */
function eleves_raisons_signalement($c)
{
	global $langs;
	$s = eleves_seuils();
	$out = array();
	if ($s['absences'] > 0 && $c['absences_nj'] >= $s['absences']) {
		$out[] = $langs->transnoentities('SeuilAbsencesAtteint', $c['absences_nj'], $s['absences']);
	}
	if ($s['retards'] > 0 && $c['retards_nj'] >= $s['retards']) {
		$out[] = $langs->transnoentities('SeuilRetardsAtteint', $c['retards_nj'], $s['retards']);
	}
	return $out;
}

/**
 * Élèves signalés (inscrits ou suspendus qui dépassent un seuil), triés par classe puis nom.
 *
 * @param  DoliDB $db Handler base
 * @return array<int,array{eleve:object,classe:string,responsable:object|null,compteurs:array,raisons:string[]}>
 */
function eleves_signales($db)
{
	$s = eleves_seuils();
	if ($s['absences'] <= 0 && $s['retards'] <= 0) {
		return array();
	}
	$p = $db->prefix();
	$actifs = "SELECT rowid FROM ".$p."ecole_eleve WHERE status IN (".EcoleEleve::STATUS_INSCRIT.", ".EcoleEleve::STATUS_SUSPENDU.")";
	$compteurs = eleves_compteurs($db, 'fk_eleve IN ('.$actifs.')');
	$out = array();
	foreach ($compteurs as $eid => $c) {
		$raisons = eleves_raisons_signalement($c);
		if (empty($raisons)) {
			continue;
		}
		$sql = "SELECT e.rowid, e.ref, e.nom_fr, e.nom_ar, e.fk_classe, e.fk_responsable, c.ref as cref, n.position as npos,";
		$sql .= " r.nom_fr as rnom_fr, r.nom_ar as rnom_ar, r.telephone, r.whatsapp FROM ".$p."ecole_eleve e";
		$sql .= " LEFT JOIN ".$p."ecole_classe c ON c.rowid = e.fk_classe LEFT JOIN ".$p."ecole_niveau n ON n.rowid = c.fk_niveau";
		$sql .= " LEFT JOIN ".$p."ecole_responsable r ON r.rowid = e.fk_responsable WHERE e.rowid = ".((int) $eid);
		$resql = $db->query($sql);
		if ($resql && ($o = $db->fetch_object($resql))) {
			$out[] = array('eleve' => $o, 'compteurs' => $c, 'raisons' => $raisons);
		}
	}
	usort($out, function ($a, $b) {
		$x = array((int) $a['eleve']->npos, (int) $a['eleve']->fk_classe, $a['eleve']->nom_fr);
		$y = array((int) $b['eleve']->npos, (int) $b['eleve']->fk_classe, $b['eleve']->nom_fr);
		return $x <=> $y;
	});
	return $out;
}

/* ------------------------------------------------------------------
 * WhatsApp
 * ---------------------------------------------------------------- */

/**
 * Numéro WhatsApp international (chiffres seulement) : WhatsApp du responsable, sinon son téléphone.
 * Un numéro local (8 chiffres en Mauritanie) reçoit l'indicatif du pays réglé dans la configuration.
 *
 * @param  string $whatsapp  WhatsApp
 * @param  string $telephone Téléphone
 * @return string            '' si aucun numéro
 */
function eleves_whatsapp_numero($whatsapp, $telephone)
{
	$n = preg_replace('/[^0-9+]/', '', (string) ($whatsapp !== '' && $whatsapp !== null ? $whatsapp : $telephone));
	if ($n === '') {
		return '';
	}
	if (strpos($n, '+') === 0) {
		return preg_replace('/[^0-9]/', '', $n);
	}
	$n = preg_replace('/[^0-9]/', '', $n);
	if (strpos($n, '00') === 0) {
		return substr($n, 2);
	}
	$indicatif = preg_replace('/[^0-9]/', '', getDolGlobalString('ELEVES_WHATSAPP_INDICATIF', '222'));
	if ($indicatif !== '' && strlen($n) <= 9 && strpos($n, $indicatif) !== 0) {
		$n = $indicatif.ltrim($n, '0');
	}
	return $n;
}

/**
 * Message par défaut prévenant le responsable (arabe puis français). {creneaux} / {creneaux_ar} donnent
 * les créneaux du jour avec la matière (ex. « S3 12:15 - Langue arabe »).
 *
 * @param  int    $type ELEVES_ABSENT, ELEVES_RETARD ou ELEVES_RENVOYE
 * @return string
 */
function eleves_message_defaut($type)
{
	if ((int) $type === ELEVES_RETARD) {
		return "السلام عليكم،\nنحيطكم علما بأن التلميذ(ة) {eleve_ar} ({classe}) تأخر(ت) يوم {date} عن الحصة : {creneaux_ar}.\n\nBonjour,\nNous vous informons que {eleve} ({classe}) est arrivé(e) en retard le {date} au cours de : {creneaux}.\n\n{ecole}";
	}
	if ((int) $type === ELEVES_RENVOYE) {
		return "السلام عليكم،\nنحيطكم علما بأن التلميذ(ة) {eleve_ar} ({classe}) أُخرج(ت) من الحصة من طرف الأستاذ يوم {date} : {creneaux_ar}.\n\nBonjour,\nNous vous informons que {eleve} ({classe}) a été renvoyé(e) du cours par le professeur le {date} : {creneaux}.\n\n{ecole}";
	}
	return "السلام عليكم،\nنحيطكم علما بأن التلميذ(ة) {eleve_ar} ({classe}) غاب(ت) يوم {date} عن الحصة : {creneaux_ar}.\n\nBonjour,\nNous vous informons que {eleve} ({classe}) était absent(e) le {date} au cours de : {creneaux}.\n\n{ecole}";
}

/**
 * Constante du message d'un type (modifiable dans Configuration > Absences et discipline).
 *
 * @param  int    $type Type
 * @return string
 */
function eleves_message_const($type)
{
	if ((int) $type === ELEVES_RETARD) {
		return 'ELEVES_MSG_RETARD';
	}
	return ((int) $type === ELEVES_RENVOYE) ? 'ELEVES_MSG_RENVOI' : 'ELEVES_MSG_ABSENCE';
}

/**
 * Lien WhatsApp avec un message prêt pour le responsable d'un élève : absences (retards ou renvois) de ce jour,
 * avec pour chaque créneau l'heure et la matière.
 *
 * @param  DoliDB $db       Handler base
 * @param  int    $fk_eleve Élève
 * @param  string $date     Date AAAA-MM-JJ
 * @param  int    $type     ELEVES_ABSENT, ELEVES_RETARD ou ELEVES_RENVOYE
 * @return string           Adresse wa.me, '' si pas de numéro
 */
function eleves_whatsapp_url($db, $fk_eleve, $date, $type = ELEVES_ABSENT)
{
	global $mysoc;
	$p = $db->prefix();
	$sql = "SELECT e.nom_fr, e.nom_ar, c.ref as cref, r.telephone, r.whatsapp FROM ".$p."ecole_eleve e LEFT JOIN ".$p."ecole_classe c ON c.rowid = e.fk_classe";
	$sql .= " LEFT JOIN ".$p."ecole_responsable r ON r.rowid = e.fk_responsable WHERE e.rowid = ".((int) $fk_eleve);
	$resql = $db->query($sql);
	$o = $resql ? $db->fetch_object($resql) : null;
	if (!$o) {
		return '';
	}
	$num = eleves_whatsapp_numero((string) $o->whatsapp, (string) $o->telephone);
	if ($num === '') {
		return '';
	}
	// Créneaux concernés ce jour-là, avec la matière (français / arabe)
	$fr = array();
	$ar = array();
	$sql = "SELECT cr.ref, cr.heure_debut, a.heure_arrivee, m.label_fr, m.label_ar FROM ".$p."ecole_absence a INNER JOIN ".$p."ecole_creneau cr ON cr.rowid = a.fk_creneau";
	$sql .= " LEFT JOIN ".$p."ecole_appel ap ON ap.rowid = a.fk_appel LEFT JOIN ".$p."ecole_matiere m ON m.rowid = ap.fk_matiere";
	$sql .= " WHERE a.fk_eleve = ".((int) $fk_eleve)." AND a.date_appel = '".$db->escape($date)."' AND a.type = ".((int) $type)." ORDER BY cr.heure_debut";
	$resql = $db->query($sql);
	while ($resql && ($c = $db->fetch_object($resql))) {
		$base = $c->ref.' '.((int) $type === ELEVES_RETARD && $c->heure_arrivee ? $c->heure_arrivee : $c->heure_debut);
		$fr[] = $base.($c->label_fr ? ' - '.$c->label_fr : '');
		$ar[] = $base.(($c->label_ar ? $c->label_ar : $c->label_fr) ? ' - '.($c->label_ar ? $c->label_ar : $c->label_fr) : '');
	}
	$msg = getDolGlobalString(eleves_message_const($type), eleves_message_defaut($type));
	$msg = strtr($msg, array(
		'{eleve}' => $o->nom_fr,
		'{eleve_ar}' => $o->nom_ar ? $o->nom_ar : $o->nom_fr,
		'{classe}' => (string) $o->cref,
		'{date}' => dol_print_date(eleves_date_ts($date), 'day'),
		'{creneaux}' => implode(', ', $fr),
		'{creneaux_ar}' => implode('، ', $ar),
		'{ecole}' => is_object($mysoc) ? (string) $mysoc->name : '',
	));
	return 'https://wa.me/'.$num.'?text='.rawurlencode($msg);
}

/**
 * Bouton WhatsApp : pastille verte avec le logo WhatsApp, le numéro dans l'infobulle. Il ouvre WhatsApp
 * avec le message prêt (l'envoi reste manuel) ; une fois cliqué, il devient blanc avec une coche (« ouvert »).
 *
 * @param  string $url   Adresse (eleves_whatsapp_url)
 * @param  bool   $label true = avec le texte « WhatsApp », false = logo seul (listes)
 * @return string        HTML
 */
function eleves_whatsapp_button($url, $label = true)
{
	global $langs;
	static $css = false;
	$out = '';
	if (!$css) {
		$css = true;
		$out .= '<style>
.wa-btn{display:inline-flex;align-items:center;gap:6px;background:#25D366;color:#fff !important;border:1px solid #25D366;border-radius:20px;padding:4px 12px;font-weight:600;text-decoration:none !important;white-space:nowrap;line-height:1.6;box-shadow:0 1px 2px rgba(0,0,0,.15);transition:background .15s}
.wa-btn:hover{background:#1da851;border-color:#1da851}
.wa-btn .fab{font-size:1.3em}
.wa-btn.wa-seul{padding:4px 8px}
.wa-btn .wa-ok{display:none}
.wa-btn.wa-ouvert{background:#fff;color:#1da851 !important}
.wa-btn.wa-ouvert .wa-ok{display:inline}
.wa-off{display:inline-flex;align-items:center;gap:5px;color:#999;border:1px dashed #ccc;border-radius:20px;padding:3px 10px;white-space:nowrap;font-size:.9em}
</style>';
	}
	if ($url === '') {
		return $out.'<span class="wa-off" title="'.dol_escape_htmltag($langs->trans('PasDeNumeroResponsable')).'"><span class="fab fa-whatsapp"></span>'.($label ? ' '.$langs->trans('PasDeNumero') : '').'</span>';
	}
	$num = preg_replace('/^https:\/\/wa\.me\/(\d+).*$/', '$1', $url);
	$title = $langs->trans('PrevenirWhatsAppAide').' — +'.$num;
	$out .= '<a class="wa-btn'.($label ? '' : ' wa-seul').'" href="'.dol_escape_htmltag($url).'" target="_blank" rel="noopener" title="'.dol_escape_htmltag($title).'" onclick="this.classList.add(\'wa-ouvert\')">';
	$out .= '<span class="fab fa-whatsapp"></span>'.($label ? '<span>'.$langs->trans('PrevenirWhatsApp').'</span>' : '').'<span class="wa-ok fas fa-check"></span></a>';
	return $out;
}

/* ------------------------------------------------------------------
 * Sanctions
 * ---------------------------------------------------------------- */

/**
 * Enregistre une sanction. Exclusion temporaire : date de fin obligatoire (élève « exclu » à l'appel pendant
 * la période). Exclusion définitive : le statut de l'élève passe à « exclu » (historique, arrêt des mensualités).
 *
 * @param  DoliDB     $db       Handler base
 * @param  User       $user     Utilisateur
 * @param  EcoleEleve $eleve    Élève
 * @param  int        $fk_type  Type de sanction
 * @param  string     $date     Date AAAA-MM-JJ
 * @param  string     $date_fin Date de fin AAAA-MM-JJ (exclusion temporaire)
 * @param  string     $motif    Motif
 * @param  string     $error    Message d'erreur (sortie)
 * @param  string     $info     Information pour l'utilisateur (sortie)
 * @return int                  Id, <0 si erreur
 */
function eleves_sanction_creer($db, $user, $eleve, $fk_type, $date, $date_fin, $motif, &$error, &$info)
{
	global $conf, $langs;
	$types = EcoleSanctionType::actifs($db);
	if (!isset($types[(int) $fk_type])) {
		$error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('TypeSanction'));
		return -1;
	}
	$type = $types[(int) $fk_type];
	if ($date === '') {
		$error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('Date'));
		return -1;
	}
	if ($date > eleves_aujourdhui()) {
		$error = $langs->trans('ErrorDateFuture');
		return -1;
	}
	if ((int) $type->exclusion === EcoleSanctionType::EXCLUSION_TEMPORAIRE) {
		if ($date_fin === '') {
			$error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('ExclusionJusquau'));
			return -1;
		}
		if ($date_fin < $date) {
			$error = $langs->trans('ErrorDateFinAvantDebut');
			return -1;
		}
	} else {
		$date_fin = '';
	}
	if (trim($motif) === '') {
		$error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('MotifSanction'));
		return -1;
	}

	$db->begin();
	$sql = "INSERT INTO ".$db->prefix()."ecole_sanction (entity, fk_eleve, fk_classe, fk_type, date_sanction, date_fin, motif, status, date_creation, fk_user_creat)";
	$sql .= " VALUES (".((int) $conf->entity).", ".((int) $eleve->id).", ".((int) $eleve->fk_classe ?: "NULL").", ".((int) $fk_type).", '".$db->escape($date)."'";
	$sql .= ", ".($date_fin !== '' ? "'".$db->escape($date_fin)."'" : "NULL").", '".$db->escape(trim($motif))."', 1, '".$db->idate(dol_now())."', ".((int) $user->id).")";
	if (!$db->query($sql)) {
		$error = $db->lasterror();
		$db->rollback();
		return -1;
	}
	$id = (int) $db->last_insert_id($db->prefix().'ecole_sanction');

	if ((int) $type->exclusion === EcoleSanctionType::EXCLUSION_DEFINITIVE) {
		$trans = EcoleEleve::transitions();
		if (isset($trans[(int) $eleve->status]) && in_array(EcoleEleve::STATUS_EXCLU, $trans[(int) $eleve->status], true)) {
			$m = $langs->transnoentities('SanctionLabelMotif', ecole_label($type), dol_trunc(trim($motif), 180));
			if ($eleve->changerStatut($user, EcoleEleve::STATUS_EXCLU, eleves_date_ts($date), $m) < 0) {
				$error = $eleve->error;
				$db->rollback();
				return -1;
			}
			$info = $langs->trans('EleveStatutExclu');
		} elseif ((int) $eleve->status !== EcoleEleve::STATUS_EXCLU) {
			$info = $langs->trans('StatutNonModifieExclusion', $eleve->getLibStatut(1));
		}
	}
	$db->commit();
	return $id;
}

/**
 * Annule une sanction (motif obligatoire) : elle reste visible, barrée. Le statut de l'élève n'est pas
 * modifié automatiquement (une réintégration se fait par « Changer le statut »).
 *
 * @param  DoliDB $db    Handler base
 * @param  User   $user  Utilisateur
 * @param  int    $id    Sanction
 * @param  string $motif Motif de l'annulation
 * @param  string $error Message d'erreur (sortie)
 * @return int           1 si OK, -1 sinon
 */
function eleves_sanction_annuler($db, $user, $id, $motif, &$error)
{
	global $langs;
	if (trim($motif) === '') {
		$error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('MotifAnnulation'));
		return -1;
	}
	$sql = "UPDATE ".$db->prefix()."ecole_sanction SET status = 0, motif_annulation = '".$db->escape(dol_trunc(trim($motif), 250, 'right', 'UTF-8', 1))."'";
	$sql .= ", date_annulation = '".$db->idate(dol_now())."', fk_user_annul = ".((int) $user->id).", fk_user_modif = ".((int) $user->id);
	$sql .= " WHERE rowid = ".((int) $id)." AND status = 1 AND entity IN (".getEntity('ecole_sanction').")";
	$resql = $db->query($sql);
	if (!$resql) {
		$error = $db->lasterror();
		return -1;
	}
	if ($db->affected_rows($resql) < 1) {
		$error = $langs->trans('ErrorSanctionDejaAnnulee');
		return -1;
	}
	return 1;
}

/**
 * Libellé de la période d'une sanction : « 24/09/2026 » ou « 24/09/2026 → 27/09/2026 ».
 *
 * @param  object $s Ligne (date_sanction, date_fin) avec des dates SQL
 * @param  DoliDB $db Handler base
 * @return string
 */
function eleves_sanction_periode($s, $db)
{
	$out = dol_print_date($db->jdate($s->date_sanction), 'day');
	if (!empty($s->date_fin)) {
		$out .= ' → '.dol_print_date($db->jdate($s->date_fin), 'day');
	}
	return $out;
}

/* ------------------------------------------------------------------
 * Listes générales (écran + exports PDF / Excel, mêmes filtres)
 * ---------------------------------------------------------------- */

/**
 * Filtres d'une liste (absences ou sanctions), lus dans la requête.
 *
 * @param  string $kind 'absences' | 'sanctions'
 * @return array{f:array,param:string}
 */
function eleves_disc_filtres($kind)
{
	$remove = GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha');
	$keys = array('du' => 'alpha', 'au' => 'alpha', 'classe' => 'int', 'eleve' => 'alphanohtml', 'type' => 'int', 'etat' => 'alpha', 'creneau' => 'int', 'fk_eleve' => 'int');
	$f = array();
	$param = '';
	foreach ($keys as $k => $t) {
		$v = $remove ? '' : ($t === 'int' ? GETPOSTINT('f_'.$k) : trim(GETPOST('f_'.$k, $t)));
		if ($k === 'du' || $k === 'au') {
			$v = eleves_date_ok($v);
		}
		if ($t === 'int' && (int) $v <= 0) {
			$v = 0;
		}
		$f[$k] = $v;
		if ($v !== '' && $v !== 0) {
			$param .= '&f_'.$k.'='.urlencode((string) $v);
		}
	}
	return array('f' => $f, 'param' => $param);
}

/**
 * Lignes de la liste des absences et retards selon les filtres.
 *
 * @param  DoliDB $db     Handler base
 * @param  array  $f      Filtres (eleves_disc_filtres)
 * @param  int    $limit  0 = tout
 * @param  int    $offset Décalage
 * @param  int    $total  Nombre total (sortie)
 * @return array<int,object>
 */
function eleves_absences_liste($db, $f, $limit, $offset, &$total)
{
	$p = $db->prefix();
	$w = array("a.entity IN (".getEntity('ecole_absence').")");
	if ($f['du'] !== '') {
		$w[] = "a.date_appel >= '".$db->escape($f['du'])."'";
	}
	if ($f['au'] !== '') {
		$w[] = "a.date_appel <= '".$db->escape($f['au'])."'";
	}
	if ($f['classe'] > 0) {
		$w[] = "a.fk_classe = ".((int) $f['classe']);
	}
	if ($f['fk_eleve'] > 0) {
		$w[] = "a.fk_eleve = ".((int) $f['fk_eleve']);
	}
	if ($f['creneau'] > 0) {
		$w[] = "a.fk_creneau = ".((int) $f['creneau']);
	}
	if (in_array((int) $f['type'], array(ELEVES_ABSENT, ELEVES_RETARD, ELEVES_RENVOYE), true)) {
		$w[] = "a.type = ".((int) $f['type']);
	}
	if ($f['etat'] === 'oui' || $f['etat'] === 'non') {
		$w[] = "a.justifiee = ".($f['etat'] === 'oui' ? 1 : 0);
	}
	if ($f['eleve'] !== '') {
		$w[] = natural_search(array('e.ref', 'e.nom_fr', 'e.nom_ar'), $f['eleve'], 0, 1);
	}
	$from = " FROM ".$p."ecole_absence a INNER JOIN ".$p."ecole_eleve e ON e.rowid = a.fk_eleve";
	$from .= " LEFT JOIN ".$p."ecole_classe c ON c.rowid = a.fk_classe LEFT JOIN ".$p."ecole_creneau cr ON cr.rowid = a.fk_creneau";
	$from .= " LEFT JOIN ".$p."ecole_motif_absence m ON m.rowid = a.fk_motif";
	$from .= " LEFT JOIN ".$p."ecole_appel ap ON ap.rowid = a.fk_appel LEFT JOIN ".$p."ecole_matiere mt ON mt.rowid = ap.fk_matiere";
	$from .= " LEFT JOIN ".$p."ecole_responsable r ON r.rowid = e.fk_responsable WHERE ".implode(' AND ', $w);

	$total = 0;
	$resql = $db->query("SELECT COUNT(*) as nb".$from);
	if ($resql && ($o = $db->fetch_object($resql))) {
		$total = (int) $o->nb;
	}
	$sql = "SELECT a.rowid, a.fk_eleve, a.fk_classe, a.date_appel, a.fk_creneau, a.type, a.heure_arrivee, a.justifiee, a.fk_motif, a.justif_note,";
	$sql .= " e.ref, e.nom_fr, e.nom_ar, e.numero_appel, c.ref as cref, cr.ref as crref, cr.heure_debut, cr.heure_fin, m.label_fr as motif_fr, m.label_ar as motif_ar,";
	$sql .= " mt.label_fr as matiere_fr, mt.label_ar as matiere_ar, r.nom_fr as rnom_fr, r.nom_ar as rnom_ar, r.telephone, r.whatsapp".$from;
	$sql .= " ORDER BY a.date_appel DESC, cr.heure_debut DESC, e.nom_fr";
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
 * Lignes de la liste des sanctions selon les filtres.
 *
 * @param  DoliDB $db     Handler base
 * @param  array  $f      Filtres (eleves_disc_filtres) ; etat = 'valide' | 'annulee'
 * @param  int    $limit  0 = tout
 * @param  int    $offset Décalage
 * @param  int    $total  Nombre total (sortie)
 * @return array<int,object>
 */
function eleves_sanctions_liste($db, $f, $limit, $offset, &$total)
{
	$p = $db->prefix();
	$w = array("s.entity IN (".getEntity('ecole_sanction').")");
	if ($f['du'] !== '') {
		$w[] = "s.date_sanction >= '".$db->escape($f['du'])."'";
	}
	if ($f['au'] !== '') {
		$w[] = "s.date_sanction <= '".$db->escape($f['au'])."'";
	}
	if ($f['classe'] > 0) {
		$w[] = "s.fk_classe = ".((int) $f['classe']);
	}
	if ($f['fk_eleve'] > 0) {
		$w[] = "s.fk_eleve = ".((int) $f['fk_eleve']);
	}
	if ($f['type'] > 0) {
		$w[] = "s.fk_type = ".((int) $f['type']);
	}
	if ($f['etat'] === 'valide' || $f['etat'] === 'annulee') {
		$w[] = "s.status = ".($f['etat'] === 'valide' ? 1 : 0);
	}
	if ($f['eleve'] !== '') {
		$w[] = natural_search(array('e.ref', 'e.nom_fr', 'e.nom_ar'), $f['eleve'], 0, 1);
	}
	$from = " FROM ".$p."ecole_sanction s INNER JOIN ".$p."ecole_eleve e ON e.rowid = s.fk_eleve";
	$from .= " LEFT JOIN ".$p."ecole_classe c ON c.rowid = s.fk_classe LEFT JOIN ".$p."ecole_sanction_type t ON t.rowid = s.fk_type WHERE ".implode(' AND ', $w);

	$total = 0;
	$resql = $db->query("SELECT COUNT(*) as nb".$from);
	if ($resql && ($o = $db->fetch_object($resql))) {
		$total = (int) $o->nb;
	}
	$sql = "SELECT s.rowid, s.fk_eleve, s.fk_classe, s.fk_type, s.date_sanction, s.date_fin, s.motif, s.status, s.motif_annulation, s.date_annulation, s.fk_user_annul, s.fk_user_creat, s.date_creation,";
	$sql .= " e.ref, e.nom_fr, e.nom_ar, c.ref as cref, t.label_fr as type_fr, t.label_ar as type_ar, t.exclusion".$from;
	$sql .= " ORDER BY s.date_sanction DESC, s.rowid DESC";
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
 * Classes pour un filtre : id => référence (ordre des niveaux).
 *
 * @param  DoliDB $db Handler base
 * @return array<int,string>
 */
function eleves_classes_filtre($db)
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
 * Libellé du type d'une ligne d'absence : « Absent » ou « En retard (08:35) ».
 *
 * @param  object $a Ligne
 * @return string
 */
function eleves_absence_type_label($a)
{
	return eleves_presence_label(eleves_presence_code($a->type, (string) $a->heure_arrivee));
}

/**
 * Jeu de données « Absences et retards » (export PDF / Excel, mêmes filtres que l'écran).
 *
 * @param  DoliDB $db Handler base
 * @return array
 */
function eleves_export_dataset_absences($db)
{
	global $langs;
	$fl = eleves_disc_filtres('absences');
	$total = 0;
	$lignes = eleves_absences_liste($db, $fl['f'], 0, 0, $total);
	$rows = array();
	$n = 0;
	foreach ($lignes as $a) {
		$rows[] = array((string) (++$n), dol_print_date($db->jdate($a->date_appel), 'day'), (string) $a->crref, eleves_absence_matiere($a), (string) $a->cref, $a->ref, ecole_label($a),
			eleves_absence_responsable($a), (string) $a->telephone,
			eleves_absence_type_label($a), (int) $a->justifiee ? ecole_label((object) array('label_fr' => $a->motif_fr, 'label_ar' => $a->motif_ar)).($a->justif_note ? ' — '.$a->justif_note : '') : ecole_pdf_trans($langs, 'NonJustifiee'));
	}
	return array(
		'title' => ecole_pdf_trans($langs, 'AbsencesEtRetards'),
		'subtitle' => eleves_disc_sous_titre($db, $fl['f'], count($rows)),
		'ref' => 'ABS-'.dol_print_date(dol_now(), '%Y%m%d'),
		'headers' => array('#', ecole_pdf_trans($langs, 'Date'), ecole_pdf_trans($langs, 'Creneau'), ecole_pdf_trans($langs, 'Matiere'), ecole_pdf_trans($langs, 'Classe'), ecole_pdf_trans($langs, 'Matricule'),
			ecole_pdf_trans($langs, 'NomComplet'), ecole_pdf_trans($langs, 'Responsable'), ecole_pdf_trans($langs, 'TelephoneResponsable'), ecole_pdf_trans($langs, 'Presence'), ecole_pdf_trans($langs, 'Justification')),
		'ratios' => array(0.5, 1.2, 0.7, 1.5, 0.8, 1.1, 2.4, 2.2, 1.3, 1.3, 1.8),
		'aligns' => array('C', 'C', 'C', 'L', 'C', 'C', 'L', 'L', 'C', 'L', 'L'),
		'rows' => $rows,
		'filename' => 'absences',
	);
}

/**
 * Matière d'une ligne d'absence (celle du cours au moment de l'appel), dans la langue de l'utilisateur.
 *
 * @param  object $a Ligne (matiere_fr, matiere_ar)
 * @return string
 */
function eleves_absence_matiere($a)
{
	return ecole_label((object) array('label_fr' => (string) $a->matiere_fr, 'label_ar' => (string) $a->matiere_ar));
}

/**
 * Responsable d'une ligne d'absence (nom dans la langue de l'utilisateur).
 *
 * @param  object $a Ligne (rnom_fr, rnom_ar)
 * @return string
 */
function eleves_absence_responsable($a)
{
	return ecole_label((object) array('nom_fr' => (string) $a->rnom_fr, 'nom_ar' => (string) $a->rnom_ar));
}

/**
 * Jeu de données « Sanctions » (export PDF / Excel, mêmes filtres que l'écran).
 *
 * @param  DoliDB $db Handler base
 * @return array
 */
function eleves_export_dataset_sanctions($db)
{
	global $langs;
	$fl = eleves_disc_filtres('sanctions');
	$total = 0;
	$lignes = eleves_sanctions_liste($db, $fl['f'], 0, 0, $total);
	$rows = array();
	$n = 0;
	foreach ($lignes as $s) {
		$rows[] = array((string) (++$n), eleves_sanction_periode($s, $db), (string) $s->cref, $s->ref, ecole_label($s),
			ecole_label((object) array('label_fr' => $s->type_fr, 'label_ar' => $s->type_ar)), (string) $s->motif,
			(int) $s->status ? '' : ecole_pdf_trans($langs, 'SanctionAnnulee').($s->motif_annulation ? ' : '.$s->motif_annulation : ''));
	}
	return array(
		'title' => ecole_pdf_trans($langs, 'Sanctions'),
		'subtitle' => eleves_disc_sous_titre($db, $fl['f'], count($rows)),
		'ref' => 'SAN-'.dol_print_date(dol_now(), '%Y%m%d'),
		'headers' => array('#', ecole_pdf_trans($langs, 'Date'), ecole_pdf_trans($langs, 'Classe'), ecole_pdf_trans($langs, 'Matricule'), ecole_pdf_trans($langs, 'NomComplet'),
			ecole_pdf_trans($langs, 'TypeSanction'), ecole_pdf_trans($langs, 'MotifSanction'), ecole_pdf_trans($langs, 'Etat')),
		'ratios' => array(0.5, 1.6, 0.9, 1.1, 2.4, 1.5, 3, 1.5),
		'aligns' => array('C', 'C', 'C', 'C', 'L', 'L', 'L', 'L'),
		'rows' => $rows,
		'filename' => 'sanctions',
	);
}

/**
 * Sous-titre d'un export : période et classe filtrées, nombre de lignes.
 *
 * @param  DoliDB $db Handler base
 * @param  array  $f  Filtres
 * @param  int    $nb Nombre de lignes
 * @return string
 */
function eleves_disc_sous_titre($db, $f, $nb)
{
	global $langs;
	$parts = array();
	if ($f['du'] !== '' || $f['au'] !== '') {
		$parts[] = ($f['du'] !== '' ? dol_print_date(eleves_date_ts($f['du']), 'day') : '…').' → '.($f['au'] !== '' ? dol_print_date(eleves_date_ts($f['au']), 'day') : '…');
	}
	if ($f['classe'] > 0) {
		$cl = eleves_classes_filtre($db);
		if (isset($cl[$f['classe']])) {
			$parts[] = ecole_pdf_trans($langs, 'Classe').' '.$cl[$f['classe']];
		}
	}
	$parts[] = ecole_pdf_trans($langs, 'NbEnregistrements', $nb);
	return implode(' — ', $parts);
}

/**
 * Jeu de données « Élèves signalés ».
 *
 * @param  DoliDB $db Handler base
 * @return array
 */
function eleves_export_dataset_signales($db)
{
	global $langs;
	$rows = array();
	$n = 0;
	foreach (eleves_signales($db) as $l) {
		$e = $l['eleve'];
		$c = $l['compteurs'];
		$rows[] = array((string) (++$n), $e->ref, ecole_label($e), (string) $e->cref, ecole_label((object) array('nom_fr' => $e->rnom_fr, 'nom_ar' => $e->rnom_ar)), (string) $e->telephone,
			(string) $c['absences_nj'], (string) $c['retards_nj'], (string) $c['sanctions']);
	}
	$s = eleves_seuils();
	return array(
		'title' => ecole_pdf_trans($langs, 'ElevesSignales'),
		'subtitle' => ecole_pdf_trans($langs, 'SeuilsResume', $s['absences'] ?: '-', $s['retards'] ?: '-').' — '.ecole_pdf_trans($langs, 'NbEnregistrements', count($rows)),
		'ref' => 'SIG-'.dol_print_date(dol_now(), '%Y%m%d'),
		'headers' => array('#', ecole_pdf_trans($langs, 'Matricule'), ecole_pdf_trans($langs, 'NomComplet'), ecole_pdf_trans($langs, 'Classe'), ecole_pdf_trans($langs, 'Responsable'),
			ecole_pdf_trans($langs, 'TelephoneResponsable'), ecole_pdf_trans($langs, 'AbsencesNonJustifiees'), ecole_pdf_trans($langs, 'RetardsNonJustifies'), ecole_pdf_trans($langs, 'Sanctions')),
		'ratios' => array(0.5, 1.1, 2.6, 0.9, 2.2, 1.4, 1.2, 1.2, 1),
		'aligns' => array('C', 'C', 'L', 'C', 'L', 'C', 'C', 'C', 'C'),
		'rows' => $rows,
		'filename' => 'eleves_signales',
	);
}

/**
 * Récapitulatif d'une classe sur une période : un élève par ligne avec ses compteurs.
 *
 * @param  DoliDB      $db     Handler base
 * @param  EcoleClasse $classe Classe
 * @param  string      $du     Début AAAA-MM-JJ
 * @param  string      $au     Fin AAAA-MM-JJ
 * @return array<int,array{eleve:object,compteurs:array,raisons:string[]}>
 */
function eleves_classe_discipline($db, $classe, $du, $au)
{
	$sql = "SELECT rowid, ref, nom_fr, nom_ar, status, fk_responsable FROM ".$db->prefix()."ecole_eleve WHERE fk_classe = ".((int) $classe->id);
	$sql .= " AND status IN (".implode(',', EcoleEleve::statusOccupantPlace()).") ORDER BY nom_fr";
	$ids = array();
	$eleves = array();
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$eleves[(int) $o->rowid] = $o;
		$ids[] = (int) $o->rowid;
	}
	$c = empty($ids) ? array() : eleves_compteurs($db, 'fk_eleve IN ('.implode(',', $ids).')', $du, $au);
	// Les seuils s'appliquent à toute l'année
	$annee = empty($ids) ? array() : eleves_compteurs($db, 'fk_eleve IN ('.implode(',', $ids).')');
	$vide = array('absences' => 0, 'absences_nj' => 0, 'retards' => 0, 'retards_nj' => 0, 'renvois' => 0, 'sanctions' => 0);
	$out = array();
	foreach ($eleves as $id => $e) {
		$out[$id] = array('eleve' => $e, 'compteurs' => isset($c[$id]) ? $c[$id] : $vide, 'raisons' => eleves_raisons_signalement(isset($annee[$id]) ? $annee[$id] : $vide));
	}
	return $out;
}

/**
 * Période par défaut d'un récapitulatif : du 1er septembre de l'année scolaire à aujourd'hui.
 *
 * @return array{0:string,1:string}
 */
function eleves_periode_defaut()
{
	dol_include_once('/eleves/core/lib/paiements.lib.php');
	return array(sprintf('%04d-09-01', eleves_annee_scolaire()), eleves_aujourdhui());
}

/**
 * Jeu de données « Absences et discipline » d'une classe sur une période.
 *
 * @param  DoliDB      $db     Handler base
 * @param  EcoleClasse $classe Classe
 * @param  string      $du     Début
 * @param  string      $au     Fin
 * @return array
 */
function eleves_export_dataset_classe_discipline($db, $classe, $du, $au)
{
	global $langs;
	$rows = array();
	$n = 0;
	foreach (eleves_classe_discipline($db, $classe, $du, $au) as $l) {
		$c = $l['compteurs'];
		$rows[] = array((string) (++$n), $l['eleve']->ref, ecole_label($l['eleve']), (string) $c['absences'], (string) $c['absences_nj'], (string) $c['retards'], (string) $c['retards_nj'], (string) $c['sanctions'],
			empty($l['raisons']) ? '' : ecole_pdf_trans($langs, 'Signale'));
	}
	return array(
		'title' => ecole_pdf_trans($langs, 'DisciplineClasseTitre', $classe->ref),
		'subtitle' => dol_print_date(eleves_date_ts($du), 'day').' → '.dol_print_date(eleves_date_ts($au), 'day'),
		'ref' => 'DISC-'.$classe->ref,
		'headers' => array('#', ecole_pdf_trans($langs, 'Matricule'), ecole_pdf_trans($langs, 'NomComplet'), ecole_pdf_trans($langs, 'Absences'), ecole_pdf_trans($langs, 'AbsencesNonJustifiees'),
			ecole_pdf_trans($langs, 'Retards'), ecole_pdf_trans($langs, 'RetardsNonJustifies'), ecole_pdf_trans($langs, 'Sanctions'), ecole_pdf_trans($langs, 'Signale')),
		'ratios' => array(0.5, 1.1, 3, 1, 1.2, 1, 1.2, 1, 1),
		'aligns' => array('C', 'C', 'L', 'C', 'C', 'C', 'C', 'C', 'C'),
		'rows' => $rows,
		'filename' => 'discipline_'.$classe->ref,
	);
}
