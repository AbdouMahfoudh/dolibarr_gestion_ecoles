<?php
/**
 * Années scolaires dans une seule base : année active (celle où l'on travaille), année consultée
 * (sélecteur, années passées en lecture seule avec le droit « Consulter les années scolaires passées »),
 * bornes d'une année, liste des années connues, mise à niveau des tables (colonne « annee »).
 *
 * Une année scolaire est désignée par l'année de la rentrée : 2026 = 2026-2027 (1er août 2026 → 31 juillet 2027).
 *
 * Fichier : custom/classes/core/lib/annee.lib.php
 */

/** Paramètre d'adresse du sélecteur d'année */
define('ECOLE_ANNEE_PARAM', 'annee_vue');

/** Version du schéma « année scolaire » (colonnes annee, clés uniques) */
define('ECOLE_ANNEE_SCHEMA', 2);

/**
 * Année scolaire active (réglage ELEVES_ANNEE_SCOLAIRE, sinon d'après la date : à partir d'août, année en cours).
 *
 * @return int
 */
function ecole_annee_active()
{
	$a = getDolGlobalInt('ELEVES_ANNEE_SCOLAIRE');
	if ($a > 2000) {
		return $a;
	}
	return ((int) date('n') >= 8) ? (int) date('Y') : (int) date('Y') - 1;
}

/**
 * Libellé d'une année scolaire : « 2026-2027 ».
 *
 * @param  int $annee Année de la rentrée
 * @return string
 */
function ecole_annee_label($annee)
{
	return ((int) $annee).'-'.((int) $annee + 1);
}

/**
 * Bornes d'une année scolaire : array('AAAA-08-01', 'AAAA+1-07-31').
 *
 * @param  int $annee Année de la rentrée
 * @return string[]
 */
function ecole_annee_bornes($annee)
{
	return array(sprintf('%04d-08-01', (int) $annee), sprintf('%04d-07-31', (int) $annee + 1));
}

/**
 * Année scolaire d'une date (AAAA-MM-JJ ou AAAA-MM).
 *
 * @param  string $date Date
 * @return int
 */
function ecole_annee_de_date($date)
{
	$y = (int) substr((string) $date, 0, 4);
	$m = (int) substr((string) $date, 5, 2);
	return $m >= 8 ? $y : $y - 1;
}

/**
 * L'utilisateur connecté peut-il consulter les années passées ?
 *
 * @return bool
 */
function ecole_annee_peut_consulter()
{
	global $user;
	return is_object($user) && !empty($user->id) && ($user->admin || $user->hasRight('classes', 'annees'));
}

/**
 * Années scolaires connues (la plus récente d'abord) : année active + années ayant des données.
 *
 * @return int[]
 */
function ecole_annees()
{
	global $db;
	static $cache = null;
	if ($cache !== null) {
		return $cache;
	}
	$active = ecole_annee_active();
	$annees = array($active => $active);
	foreach (array('ecole_evaluation', 'ecole_bulletin', 'ecole_note_cloture', 'ecole_edt_annee', 'ecole_classe_tarif') as $t) {
		if (!function_exists('ecole_table_exists') || !ecole_table_exists($db, $t)) {
			continue;
		}
		$resql = $db->query("SELECT DISTINCT annee FROM ".$db->prefix().$t." WHERE annee > 2000 AND annee <= ".$active);
		while ($resql && ($o = $db->fetch_object($resql))) {
			$annees[(int) $o->annee] = (int) $o->annee;
		}
	}
	krsort($annees);
	return $cache = array_values($annees);
}

/**
 * Force l'année consultée pour la suite de la page (ex. espace parents).
 *
 * @param  int|null $annee Année (null = retour au fonctionnement normal)
 * @return void
 */
function ecole_annee_vue_forcer($annee)
{
	$GLOBALS['ecole_annee_vue_forcee'] = $annee === null ? null : (int) $annee;
}

/**
 * Année scolaire consultée : choisie dans le sélecteur (gardée en session), sinon l'année active.
 * Sans le droit de consulter les années passées, c'est toujours l'année active.
 *
 * @return int
 */
function ecole_annee_vue()
{
	if (!empty($GLOBALS['ecole_annee_vue_forcee'])) {
		return (int) $GLOBALS['ecole_annee_vue_forcee'];
	}
	$active = ecole_annee_active();
	if (!ecole_annee_peut_consulter()) {
		return $active;
	}
	if (isset($_GET[ECOLE_ANNEE_PARAM]) || isset($_POST[ECOLE_ANNEE_PARAM])) {
		$a = GETPOSTINT(ECOLE_ANNEE_PARAM);
		if ($a === $active || in_array($a, ecole_annees(), true)) {
			$_SESSION['ecole_annee_vue'] = $a;
		}
	}
	$a = isset($_SESSION['ecole_annee_vue']) ? (int) $_SESSION['ecole_annee_vue'] : $active;
	if ($a !== $active && !in_array($a, ecole_annees(), true)) {
		$a = $active;
	}
	return $a;
}

/**
 * L'année consultée est-elle une année passée (lecture seule) ?
 *
 * @return bool
 */
function ecole_annee_passee()
{
	return ecole_annee_vue() !== ecole_annee_active();
}

/**
 * Sélecteur d'année scolaire (affiché seulement avec le droit et s'il existe plusieurs années)
 * suivi, pour une année passée, du bandeau « lecture seule ».
 *
 * @param  string[] $garder Paramètres de la page à garder dans l'adresse (ex. array('fk_classe', 'trimestre'))
 * @return string           HTML
 */
function ecole_annee_selecteur($garder = array())
{
	global $langs;
	$langs->load('classes@classes');
	$out = '';
	$annees = ecole_annees();
	if (ecole_annee_peut_consulter() && count($annees) > 1) {
		$vue = ecole_annee_vue();
		$out .= '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="marginbottomonly ecole-annee-form">';
		foreach ($garder as $k) {
			if (GETPOSTISSET($k) && GETPOST($k, 'alphanohtml') !== '') {
				$out .= '<input type="hidden" name="'.dol_escape_htmltag($k).'" value="'.dol_escape_htmltag(GETPOST($k, 'alphanohtml')).'">';
			}
		}
		$out .= '<span class="fas fa-calendar-alt pictofixedwidth opacitymedium"></span>'.$langs->trans('AnneeScolaire').' ';
		$out .= '<select name="'.ECOLE_ANNEE_PARAM.'" class="flat" onchange="this.form.submit()">';
		foreach ($annees as $a) {
			$lib = ecole_annee_label($a).($a === ecole_annee_active() ? ' ('.$langs->trans('AnneeEnCours').')' : '');
			$out .= '<option value="'.$a.'"'.($a === $vue ? ' selected' : '').'>'.dol_escape_htmltag($lib).'</option>';
		}
		$out .= '</select> <noscript><input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Refresh')).'"></noscript>';
		$out .= '</form>';
	}
	$out .= ecole_annee_bandeau();
	return $out;
}

/**
 * Bandeau d'une année passée : « Année 2025-2026 : consultation seulement ».
 *
 * @return string HTML ('' pour l'année active)
 */
function ecole_annee_bandeau()
{
	global $langs;
	if (!ecole_annee_passee()) {
		return '';
	}
	$langs->load('classes@classes');
	$url = $_SERVER['PHP_SELF'].'?'.ECOLE_ANNEE_PARAM.'='.ecole_annee_active();
	return '<div class="warning ecole-annee-bandeau"><span class="fas fa-history pictofixedwidth"></span>'
		.$langs->trans('AnneePasseeLectureSeule', ecole_annee_label(ecole_annee_vue()))
		.' <a href="'.dol_escape_htmltag($url).'">'.$langs->trans('RevenirAnneeEnCours').'</a></div>';
}

/**
 * Refuse une modification sur une année passée.
 *
 * @return void
 */
function ecole_annee_verifier_ecriture()
{
	global $langs;
	if (ecole_annee_passee()) {
		$langs->load('classes@classes');
		accessforbidden($langs->trans('AnneePasseeLectureSeule', ecole_annee_label(ecole_annee_vue())), 0);
	}
}

/**
 * Condition SQL « de l'année consultée » sur une colonne annee.
 *
 * @param  string $col Colonne (ex. 'v.annee')
 * @return string      Ex. " AND v.annee = 2026"
 */
function ecole_annee_sql($col = 'annee')
{
	return " AND ".$col." = ".((int) ecole_annee_vue());
}

/**
 * Mise à niveau des tables pour les années scolaires (sans devoir réactiver les modules) :
 * colonne annee, clés uniques par année, rattachement des données existantes à l'année active.
 * Ne fait rien si le schéma est déjà à jour (constante ECOLE_ANNEE_SCHEMA).
 *
 * @param  DoliDB $db Handler base
 * @return void
 */
function ecole_annee_migrer($db)
{
	global $conf;
	if (getDolGlobalInt('ECOLE_ANNEE_SCHEMA') >= ECOLE_ANNEE_SCHEMA || !function_exists('ecole_table_exists')) {
		return;
	}
	$p = $db->prefix();
	$active = ecole_annee_active();
	$ok = true;
	// Tables des années (tarifs, emplois du temps passés) : créées d'après leur fichier sql/
	foreach (array('ecole_classe_tarif', 'ecole_edt_annee') as $t) {
		if (!ecole_table_exists($db, $t)) {
			$sql = preg_replace('/^\s*--.*$/m', '', (string) @file_get_contents(dirname(__DIR__, 2).'/sql/llx_'.$t.'.sql'));
			$sql = str_replace('CREATE TABLE llx_', 'CREATE TABLE IF NOT EXISTS '.$p, trim($sql));
			$ok = ($sql !== '' && $db->query(rtrim($sql, "; \n"))) && $ok;
		}
	}
	// table => array(ancienne clé unique, nouvelle clé unique (colonnes))
	$tables = array(
		'ecole_evaluation' => array('', ''),
		'ecole_note_cloture' => array('uk_ecole_note_cloture', 'fk_classe, trimestre, annee'),
		'ecole_bulletin' => array('uk_ecole_bulletin', 'fk_eleve, trimestre, annee'),
		'ecole_paiement' => array('', ''),
	);
	foreach ($tables as $t => $cle) {
		if (!ecole_table_exists($db, $t)) {
			continue;
		}
		$resql = $db->query("SHOW COLUMNS FROM ".$p.$t." LIKE 'annee'");
		if (!$resql || !$db->num_rows($resql)) {
			$ok = $db->query("ALTER TABLE ".$p.$t." ADD COLUMN annee INTEGER NOT NULL DEFAULT 0") && $ok;
			$db->query("ALTER TABLE ".$p.$t." ADD INDEX idx_".$t."_annee (annee)");
		}
		if ($t === 'ecole_paiement') {
			// Mensualités : année de la période ; autres lignes : année de la date du reçu
			$db->query("UPDATE ".$p."ecole_paiement SET annee = IF(CAST(SUBSTRING(periode, 6, 2) AS UNSIGNED) >= 8, CAST(LEFT(periode, 4) AS UNSIGNED), CAST(LEFT(periode, 4) AS UNSIGNED) - 1) WHERE annee = 0 AND periode LIKE '____-__'");
			$db->query("UPDATE ".$p."ecole_paiement l INNER JOIN ".$p."ecole_recu r ON r.rowid = l.fk_recu SET l.annee = IF(MONTH(r.date_recu) >= 8, YEAR(r.date_recu), YEAR(r.date_recu) - 1) WHERE l.annee = 0 AND r.date_recu IS NOT NULL");
		}
		$db->query("UPDATE ".$p.$t." SET annee = ".$active." WHERE annee = 0");
		if ($cle[0] !== '') {
			$resql = $db->query("SHOW INDEX FROM ".$p.$t." WHERE Key_name = '".$db->escape($cle[0])."'");
			$cols = array();
			while ($resql && ($o = $db->fetch_object($resql))) {
				$cols[] = $o->Column_name;
			}
			if (!in_array('annee', $cols, true)) {
				if (!empty($cols)) {
					$db->query("ALTER TABLE ".$p.$t." DROP INDEX ".$cle[0]);
				}
				$ok = $db->query("ALTER TABLE ".$p.$t." ADD UNIQUE KEY ".$cle[0]." (".$cle[1].")") && $ok;
			}
		}
	}
	if ($ok) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
		dolibarr_set_const($db, 'ECOLE_ANNEE_SCHEMA', ECOLE_ANNEE_SCHEMA, 'chaine', 0, '', $conf->entity);
	}
}

/**
 * Classe d'un élève pendant une année scolaire : classe actuelle pour l'année active, sinon sa dernière classe
 * de l'année d'après l'historique des classes (0 si l'élève n'était pas à l'école cette année-là).
 *
 * @param  DoliDB $db        Handler base
 * @param  int    $fk_eleve  Élève
 * @param  int    $fk_actuel Classe actuelle de l'élève
 * @param  int    $annee     Année scolaire (null = année consultée)
 * @return int
 */
function ecole_eleve_classe_annee($db, $fk_eleve, $fk_actuel, $annee = null)
{
	$annee = $annee === null ? ecole_annee_vue() : (int) $annee;
	if ($annee === ecole_annee_active()) {
		return (int) $fk_actuel;
	}
	list($debut, $fin) = ecole_annee_bornes($annee);
	$sql = "SELECT fk_classe FROM ".$db->prefix()."ecole_eleve_classe WHERE fk_eleve = ".((int) $fk_eleve);
	$sql .= " AND date_debut <= '".$fin."' AND (date_fin IS NULL OR date_fin >= '".$debut."') ORDER BY date_debut DESC, rowid DESC LIMIT 1";
	$resql = $db->query($sql);
	$o = $resql ? $db->fetch_object($resql) : null;
	return $o ? (int) $o->fk_classe : 0;
}

/* ------------------------------------------------------------------
 * Tarifs des classes par année
 * ---------------------------------------------------------------- */

/**
 * Tarifs enregistrés pour une année : fk_classe => ligne (mensualite, frais_inscription, frais_reinscription).
 *
 * @param  DoliDB $db    Handler base
 * @param  int    $annee Année scolaire
 * @return array<int,object>
 */
function ecole_tarifs_annee($db, $annee)
{
	$out = array();
	if (!ecole_table_exists($db, 'ecole_classe_tarif')) {
		return $out;
	}
	$resql = $db->query("SELECT fk_classe, mensualite, frais_inscription, frais_reinscription FROM ".$db->prefix()."ecole_classe_tarif WHERE annee = ".((int) $annee)." AND entity IN (".getEntity('ecole_classe').")");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[(int) $o->fk_classe] = $o;
	}
	return $out;
}

/**
 * Tarifs d'une classe pour une année : fiche de la classe pour l'année en cours, tarifs enregistrés sinon
 * (à défaut, ceux de la fiche). Retourne un objet (mensualite, frais_inscription, frais_reinscription, enregistre).
 *
 * @param  DoliDB $db        Handler base
 * @param  int    $fk_classe Classe
 * @param  int    $annee     Année scolaire (null = année consultée)
 * @return object
 */
function ecole_classe_tarif($db, $fk_classe, $annee = null)
{
	static $cache = array();
	$annee = $annee === null ? ecole_annee_vue() : (int) $annee;
	$k = ((int) $fk_classe).'|'.$annee;
	if (isset($cache[$k])) {
		return $cache[$k];
	}
	$out = (object) array('mensualite' => 0.0, 'frais_inscription' => 0.0, 'frais_reinscription' => null, 'enregistre' => false);
	$resql = $db->query("SELECT mensualite, frais_inscription FROM ".$db->prefix()."ecole_classe WHERE rowid = ".((int) $fk_classe));
	if ($resql && ($o = $db->fetch_object($resql))) {
		$out->mensualite = (float) $o->mensualite;
		$out->frais_inscription = (float) $o->frais_inscription;
	}
	$t = ecole_tarifs_annee($db, $annee);
	if (isset($t[(int) $fk_classe])) {
		$r = $t[(int) $fk_classe];
		if ($annee !== ecole_annee_active()) {
			$out->mensualite = (float) $r->mensualite;
			$out->frais_inscription = (float) $r->frais_inscription;
		}
		$out->frais_reinscription = $r->frais_reinscription === null ? null : (float) $r->frais_reinscription;
		$out->enregistre = true;
	}
	return $cache[$k] = $out;
}

/**
 * Enregistre les tarifs d'une classe pour une année. Pour l'année en cours, la fiche de la classe est aussi mise à jour.
 *
 * @param  DoliDB     $db           Handler base
 * @param  User       $user         Utilisateur
 * @param  int        $fk_classe    Classe
 * @param  int        $annee        Année scolaire (année en cours ou suivante)
 * @param  float      $mensualite   Mensualité
 * @param  float      $inscription  Frais d'inscription
 * @param  float|null $reinscription Frais de réinscription (null = règle générale)
 * @return int                       1 si OK, -1 sinon
 */
function ecole_tarif_enregistrer($db, $user, $fk_classe, $annee, $mensualite, $inscription, $reinscription = null)
{
	global $conf;
	$annee = (int) $annee;
	if ($annee < ecole_annee_active() || $annee > ecole_annee_active() + 1 || $mensualite < 0 || $inscription < 0 || ($reinscription !== null && $reinscription < 0)) {
		return -1;
	}
	$m = (float) price2num($mensualite, 'MT');
	$i = (float) price2num($inscription, 'MT');
	$r = $reinscription === null ? 'NULL' : (string) ((float) price2num($reinscription, 'MT'));
	$sql = "INSERT INTO ".$db->prefix()."ecole_classe_tarif (entity, annee, fk_classe, mensualite, frais_inscription, frais_reinscription, date_creation, fk_user_creat)";
	$sql .= " VALUES (".((int) $conf->entity).", ".$annee.", ".((int) $fk_classe).", ".$m.", ".$i.", ".$r.", '".$db->idate(dol_now())."', ".((int) $user->id).")";
	$sql .= " ON DUPLICATE KEY UPDATE mensualite = ".$m.", frais_inscription = ".$i.", frais_reinscription = ".$r.", fk_user_modif = ".((int) $user->id);
	if (!$db->query($sql)) {
		return -1;
	}
	if ($annee === ecole_annee_active()) {
		if (!$db->query("UPDATE ".$db->prefix()."ecole_classe SET mensualite = ".$m.", frais_inscription = ".$i.", fk_user_modif = ".((int) $user->id)." WHERE rowid = ".((int) $fk_classe))) {
			return -1;
		}
	}
	return 1;
}

/**
 * Garde les tarifs et l'emploi du temps de l'année en cours (fait au passage à l'année suivante, et refait sans risque).
 *
 * @param  DoliDB $db    Handler base
 * @param  User   $user  Utilisateur
 * @param  int    $annee Année scolaire à garder (l'année en cours)
 * @return int           1 si OK, -1 sinon
 */
function ecole_annee_archiver($db, $user, $annee)
{
	$p = $db->prefix();
	$annee = (int) $annee;
	$now = "'".$db->idate(dol_now())."'";
	$ok = $db->query("INSERT INTO ".$p."ecole_classe_tarif (entity, annee, fk_classe, mensualite, frais_inscription, date_creation, fk_user_creat)"
		." SELECT c.entity, ".$annee.", c.rowid, c.mensualite, c.frais_inscription, ".$now.", ".((int) $user->id)." FROM ".$p."ecole_classe c"
		." ON DUPLICATE KEY UPDATE mensualite = VALUES(mensualite), frais_inscription = VALUES(frais_inscription)");
	$ok = $ok && $db->query("DELETE FROM ".$p."ecole_edt_annee WHERE annee = ".$annee);
	$ok = $ok && $db->query("INSERT INTO ".$p."ecole_edt_annee (entity, annee, fk_classe, jour, fk_creneau, heure_debut, heure_fin, fk_matiere, fk_user, fk_salle, date_creation)"
		." SELECT e.entity, ".$annee.", e.fk_classe, e.jour, e.fk_creneau, cr.heure_debut, cr.heure_fin, e.fk_matiere, e.fk_user, e.fk_salle, ".$now
		." FROM ".$p."ecole_edt_cours e INNER JOIN ".$p."ecole_creneau cr ON cr.rowid = e.fk_creneau");
	return $ok ? 1 : -1;
}
