<?php
/**
 * Fonctions du module Notes : règles de calcul, accès des enseignants, évaluations, saisie des notes,
 * historique, clôture des trimestres.
 *
 * Fichier : custom/notes/core/lib/notes.lib.php
 */

dol_include_once('/eleves/class/ecole_eleve.class.php');

/** Types d'évaluation */
define('NOTES_DEVOIR', 1);
define('NOTES_COMPO', 2);

/** Absence à une évaluation */
define('NOTES_ABS', 'ABS');
define('NOTES_DISP', 'DISP');

/* ------------------------------------------------------------------
 * Règles de calcul
 * ---------------------------------------------------------------- */

/**
 * Règles par défaut (niveau sans réglage).
 *
 * @return array<string,mixed>
 */
function notes_regle_defaut()
{
	return array(
		'calcul_devoirs' => 'moyenne',
		'poids_devoirs' => 1.0,
		'poids_compo' => 2.0,
		'devoir_absent' => 'ignore',
		'compo_absent' => 'zero',
		'trimestre_sans_compo' => 'exclure',
		'poids_t1' => 1.0,
		'poids_t2' => 1.0,
		'poids_t3' => 1.0,
		'rang_exaequo' => 'meme',
		'decision' => 'auto',
		'seuil_passage' => 10.0,
	);
}

/**
 * Choix possibles de chaque règle à liste : valeur => clé de traduction.
 *
 * @return array<string,array<string,string>>
 */
function notes_regle_choix()
{
	return array(
		'calcul_devoirs' => array('moyenne' => 'CalculDevoirsMoyenne', 'meilleure' => 'CalculDevoirsMeilleure'),
		'devoir_absent' => array('ignore' => 'DevoirAbsentIgnore', 'zero' => 'DevoirAbsentZero'),
		'compo_absent' => array('zero' => 'CompoAbsentZero', 'devoirs' => 'CompoAbsentDevoirs'),
		'trimestre_sans_compo' => array('exclure' => 'TrimestreSansCompoExclure', 'garder' => 'TrimestreSansCompoGarder'),
		'rang_exaequo' => array('meme' => 'RangExaequoMeme', 'compo' => 'RangExaequoCompo'),
		'decision' => array('aucune' => 'DecisionAucune', 'manuelle' => 'DecisionManuelle', 'auto' => 'DecisionAuto'),
	);
}

/**
 * Règles d'un niveau (réglage enregistré, sinon règles par défaut).
 *
 * @param  DoliDB $db        Handler base
 * @param  int    $fk_niveau Niveau
 * @return array<string,mixed> Règles + 'enregistre' (bool)
 */
function notes_regle($db, $fk_niveau)
{
	static $cache = array();
	if (isset($cache[(int) $fk_niveau])) {
		return $cache[(int) $fk_niveau];
	}
	$r = notes_regle_defaut();
	$r['enregistre'] = false;
	$r['fk_modele'] = 0;
	$resql = $db->query("SELECT * FROM ".$db->prefix()."ecole_note_regle WHERE entity IN (".getEntity('ecole_note_regle').") AND fk_niveau = ".((int) $fk_niveau));
	if ($resql && ($o = $db->fetch_object($resql))) {
		foreach (notes_regle_defaut() as $k => $v) {
			$r[$k] = is_float($v) ? (float) $o->$k : (string) $o->$k;
		}
		$r['fk_modele'] = isset($o->fk_modele) ? (int) $o->fk_modele : 0;
		$r['enregistre'] = true;
	}
	return $cache[(int) $fk_niveau] = $r;
}

/**
 * Règles qui s'appliquent à une classe (celles de son niveau).
 *
 * @param  DoliDB $db        Handler base
 * @param  int    $fk_classe Classe
 * @return array<string,mixed>
 */
function notes_regle_classe($db, $fk_classe)
{
	$resql = $db->query("SELECT fk_niveau FROM ".$db->prefix()."ecole_classe WHERE rowid = ".((int) $fk_classe));
	$o = $resql ? $db->fetch_object($resql) : null;
	return notes_regle($db, $o ? (int) $o->fk_niveau : 0);
}

/**
 * Enregistre les règles d'un niveau.
 *
 * @param  DoliDB $db        Handler base
 * @param  User   $user      Utilisateur
 * @param  int    $fk_niveau Niveau
 * @param  array  $data      Règles (clés de notes_regle_defaut)
 * @param  string $error     Message d'erreur (sortie)
 * @return int               1 si OK, -1 sinon
 */
function notes_regle_enregistrer($db, $user, $fk_niveau, $data, &$error)
{
	global $conf, $langs;
	$choix = notes_regle_choix();
	$set = array();
	foreach (notes_regle_defaut() as $k => $def) {
		$v = isset($data[$k]) ? $data[$k] : $def;
		if (isset($choix[$k])) {
			if (!isset($choix[$k][$v])) {
				$error = $langs->trans('ErrorEcoleBadValue', $k);
				return -1;
			}
			$set[$k] = "'".$db->escape($v)."'";
		} else {
			$v = (float) price2num($v);
			if ($v < 0 || $v > 100 || ($k === 'seuil_passage' && $v > 20)) {
				$error = $langs->trans('ErrorPoidsInvalide');
				return -1;
			}
			$set[$k] = (string) $v;
		}
	}
	$set['fk_modele'] = !empty($data['fk_modele']) && (int) $data['fk_modele'] > 0 ? (string) ((int) $data['fk_modele']) : 'NULL';
	if ((float) $set['poids_devoirs'] + (float) $set['poids_compo'] <= 0 || (float) $set['poids_t1'] + (float) $set['poids_t2'] + (float) $set['poids_t3'] <= 0) {
		$error = $langs->trans('ErrorPoidsTousNuls');
		return -1;
	}
	$now = "'".$db->idate(dol_now())."'";
	$sql = "INSERT INTO ".$db->prefix()."ecole_note_regle (entity, fk_niveau, ".implode(', ', array_keys($set)).", date_creation, fk_user_creat)";
	$sql .= " VALUES (".((int) $conf->entity).", ".((int) $fk_niveau).", ".implode(', ', $set).", ".$now.", ".((int) $user->id).")";
	$upd = array();
	foreach ($set as $k => $v) {
		$upd[] = $k.' = '.$v;
	}
	$sql .= " ON DUPLICATE KEY UPDATE ".implode(', ', $upd).", fk_user_modif = ".((int) $user->id);
	if (!$db->query($sql)) {
		$error = $db->lasterror();
		return -1;
	}
	return 1;
}

/**
 * Exceptions « calcul de la note de devoirs » des matières d'une classe : fk_matiere => 'moyenne' | 'meilleure'.
 *
 * @param  DoliDB $db        Handler base
 * @param  int    $fk_classe Classe
 * @return array<int,string>
 */
function notes_exceptions_classe($db, $fk_classe)
{
	$out = array();
	$resql = $db->query("SELECT fk_matiere, calcul_devoirs FROM ".$db->prefix()."ecole_note_exception WHERE fk_classe = ".((int) $fk_classe));
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[(int) $o->fk_matiere] = (string) $o->calcul_devoirs;
	}
	return $out;
}

/**
 * Calcul de la note de devoirs d'une matière d'une classe : exception, sinon règle du niveau.
 *
 * @param  DoliDB $db         Handler base
 * @param  int    $fk_classe  Classe
 * @param  int    $fk_matiere Matière
 * @return string             'moyenne' | 'meilleure'
 */
function notes_calcul_devoirs($db, $fk_classe, $fk_matiere)
{
	$ex = notes_exceptions_classe($db, $fk_classe);
	if (isset($ex[(int) $fk_matiere])) {
		return $ex[(int) $fk_matiere];
	}
	$r = notes_regle_classe($db, $fk_classe);
	return $r['calcul_devoirs'];
}

/**
 * Enregistre (ou retire avec $calcul = '') l'exception d'une matière d'une classe.
 *
 * @param  DoliDB $db         Handler base
 * @param  User   $user       Utilisateur
 * @param  int    $fk_classe  Classe
 * @param  int    $fk_matiere Matière
 * @param  string $calcul     '' | 'moyenne' | 'meilleure'
 * @return int                1 si OK, -1 sinon
 */
function notes_exception_enregistrer($db, $user, $fk_classe, $fk_matiere, $calcul)
{
	global $conf;
	$p = $db->prefix();
	if ($calcul === '') {
		return $db->query("DELETE FROM ".$p."ecole_note_exception WHERE fk_classe = ".((int) $fk_classe)." AND fk_matiere = ".((int) $fk_matiere)) ? 1 : -1;
	}
	$choix = notes_regle_choix();
	if (!isset($choix['calcul_devoirs'][$calcul])) {
		return -1;
	}
	$sql = "INSERT INTO ".$p."ecole_note_exception (entity, fk_classe, fk_matiere, calcul_devoirs, date_creation, fk_user_creat)";
	$sql .= " VALUES (".((int) $conf->entity).", ".((int) $fk_classe).", ".((int) $fk_matiere).", '".$db->escape($calcul)."', '".$db->idate(dol_now())."', ".((int) $user->id).")";
	$sql .= " ON DUPLICATE KEY UPDATE calcul_devoirs = '".$db->escape($calcul)."'";
	return $db->query($sql) ? 1 : -1;
}

/* ------------------------------------------------------------------
 * Accès : enseignant (ses classes et matières d'après l'emploi du temps) ou toutes les classes
 * ---------------------------------------------------------------- */

/**
 * L'utilisateur peut-il voir les notes de toutes les classes ?
 *
 * @param  User $user Utilisateur
 * @return bool
 */
function notes_voit_tout($user)
{
	return $user->hasRight('notes', 'note', 'lire') || $user->hasRight('notes', 'note', 'saisirtout');
}

/**
 * Classes et matières d'un enseignant d'après l'emploi du temps : fk_classe => array(fk_matiere => true).
 *
 * @param  DoliDB $db      Handler base
 * @param  int    $fk_user Utilisateur
 * @return array<int,array<int,bool>>
 */
function notes_affectations($db, $fk_user)
{
	static $cache = array();
	if (isset($cache[(int) $fk_user])) {
		return $cache[(int) $fk_user];
	}
	$out = array();
	$resql = $db->query("SELECT DISTINCT fk_classe, fk_matiere FROM ".$db->prefix()."ecole_edt_cours WHERE fk_user = ".((int) $fk_user));
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[(int) $o->fk_classe][(int) $o->fk_matiere] = true;
	}
	return $cache[(int) $fk_user] = $out;
}

/**
 * L'utilisateur peut-il saisir les notes de cette matière de cette classe ?
 *
 * @param  DoliDB $db         Handler base
 * @param  User   $user       Utilisateur
 * @param  int    $fk_classe  Classe
 * @param  int    $fk_matiere Matière
 * @return bool
 */
function notes_peut_saisir($db, $user, $fk_classe, $fk_matiere)
{
	if ($user->hasRight('notes', 'note', 'saisirtout')) {
		return true;
	}
	if (!$user->hasRight('notes', 'note', 'saisir')) {
		return false;
	}
	$a = notes_affectations($db, (int) $user->id);
	return !empty($a[(int) $fk_classe][(int) $fk_matiere]);
}

/**
 * L'utilisateur peut-il voir les notes de cette matière de cette classe ?
 *
 * @param  DoliDB $db         Handler base
 * @param  User   $user       Utilisateur
 * @param  int    $fk_classe  Classe
 * @param  int    $fk_matiere Matière (0 = au moins une matière de la classe)
 * @return bool
 */
function notes_peut_voir($db, $user, $fk_classe, $fk_matiere = 0)
{
	if (notes_voit_tout($user)) {
		return true;
	}
	if (!$user->hasRight('notes', 'note', 'saisir')) {
		return false;
	}
	$a = notes_affectations($db, (int) $user->id);
	return $fk_matiere > 0 ? !empty($a[(int) $fk_classe][(int) $fk_matiere]) : !empty($a[(int) $fk_classe]);
}

/**
 * Condition SQL sur les classes (alias c) de l'année consultée : classes actives pour l'année en cours,
 * classes qui ont eu des évaluations pour une année passée (même désactivées depuis).
 *
 * @param  DoliDB $db Handler base
 * @return string
 */
function notes_sql_classes_annee($db)
{
	if (ecole_annee_passee()) {
		return " AND c.rowid IN (SELECT v.fk_classe FROM ".$db->prefix()."ecole_evaluation v WHERE v.status = 1".ecole_annee_sql('v.annee').")";
	}
	return " AND c.status = 1";
}

/**
 * Classes visibles par l'utilisateur (actives), dans l'ordre des niveaux : id => objet (rowid, ref, label_fr, label_ar, fk_niveau).
 *
 * @param  DoliDB $db   Handler base
 * @param  User   $user Utilisateur
 * @return array<int,object>
 */
function notes_classes($db, $user)
{
	$p = $db->prefix();
	$sql = "SELECT c.rowid, c.ref, c.label_fr, c.label_ar, c.fk_niveau FROM ".$p."ecole_classe c LEFT JOIN ".$p."ecole_niveau n ON n.rowid = c.fk_niveau";
	$sql .= " WHERE c.entity IN (".getEntity('ecole_classe').")".notes_sql_classes_annee($db);
	if (!notes_voit_tout($user)) {
		$ids = array_keys(notes_affectations($db, (int) $user->id));
		$sql .= " AND c.rowid IN (".(empty($ids) ? '0' : implode(',', array_map('intval', $ids))).")";
	}
	$sql .= " ORDER BY n.position, c.rowid";
	$out = array();
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[(int) $o->rowid] = $o;
	}
	return $out;
}

/**
 * Matières d'une classe visibles par l'utilisateur, avec coefficient, maximum de la note et enseignants.
 *
 * @param  DoliDB    $db        Handler base
 * @param  User|null $user      Utilisateur (null = toutes les matières)
 * @param  int       $fk_classe Classe
 * @return array<int,object>    fk_matiere => ligne (fk_matiere, ref, label_fr, label_ar, coefficient, bareme, note_max, enseignants)
 */
function notes_matieres_classe($db, $user, $fk_classe)
{
	$p = $db->prefix();
	$sql = "SELECT cm.fk_matiere, cm.coefficient, cm.bareme, cm.note_max, m.ref, m.label_fr, m.label_ar, COALESCE(cm.langue, m.langue) as langue";
	$sql .= " FROM ".$p."ecole_classe_matiere cm INNER JOIN ".$p."ecole_matiere m ON m.rowid = cm.fk_matiere";
	$sql .= " WHERE cm.fk_classe = ".((int) $fk_classe)." ORDER BY m.label_fr";
	$aff = ($user && !notes_voit_tout($user)) ? notes_affectations($db, (int) $user->id) : null;
	$out = array();
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		if ($aff !== null && empty($aff[(int) $fk_classe][(int) $o->fk_matiere])) {
			continue;
		}
		$o->enseignants = array();
		$out[(int) $o->fk_matiere] = $o;
	}
	// Enseignants de chaque matière (emploi du temps)
	$resql = $db->query("SELECT DISTINCT fk_matiere, fk_user FROM ".$p."ecole_edt_cours WHERE fk_classe = ".((int) $fk_classe)." AND fk_user IS NOT NULL");
	while ($resql && ($o = $db->fetch_object($resql))) {
		if (isset($out[(int) $o->fk_matiere])) {
			$out[(int) $o->fk_matiere]->enseignants[] = ecole_user_label($db, (int) $o->fk_user);
		}
	}
	return $out;
}

/* ------------------------------------------------------------------
 * Clôture des trimestres
 * ---------------------------------------------------------------- */

/**
 * État de clôture d'un trimestre d'une classe (null = jamais clôturé).
 *
 * @param  DoliDB $db        Handler base
 * @param  int    $fk_classe Classe
 * @param  int    $trimestre Trimestre (1 à 3)
 * @return object|null
 */
function notes_cloture($db, $fk_classe, $trimestre)
{
	$resql = $db->query("SELECT * FROM ".$db->prefix()."ecole_note_cloture WHERE fk_classe = ".((int) $fk_classe)." AND trimestre = ".((int) $trimestre).ecole_annee_sql());
	$o = $resql ? $db->fetch_object($resql) : null;
	return $o ? $o : null;
}

/**
 * Le trimestre de cette classe est-il clôturé ?
 *
 * @param  DoliDB $db        Handler base
 * @param  int    $fk_classe Classe
 * @param  int    $trimestre Trimestre
 * @return bool
 */
function notes_est_cloture($db, $fk_classe, $trimestre)
{
	$c = notes_cloture($db, $fk_classe, $trimestre);
	return $c && (int) $c->status === 1;
}

/**
 * Clôtures de toutes les classes : 'classe|trimestre' => ligne.
 *
 * @param  DoliDB $db Handler base
 * @return array<string,object>
 */
function notes_clotures($db)
{
	$out = array();
	$resql = $db->query("SELECT * FROM ".$db->prefix()."ecole_note_cloture WHERE entity IN (".getEntity('ecole_note_cloture').")".ecole_annee_sql());
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[((int) $o->fk_classe).'|'.((int) $o->trimestre)] = $o;
	}
	return $out;
}

/**
 * Clôture le trimestre d'une classe.
 *
 * @param  DoliDB $db        Handler base
 * @param  User   $user      Utilisateur
 * @param  int    $fk_classe Classe
 * @param  int    $trimestre Trimestre
 * @return int               1 si OK, -1 sinon
 */
function notes_cloturer($db, $user, $fk_classe, $trimestre)
{
	global $conf;
	$now = "'".$db->idate(dol_now())."'";
	if (ecole_annee_passee()) {
		return -1;
	}
	$sql = "INSERT INTO ".$db->prefix()."ecole_note_cloture (entity, annee, fk_classe, trimestre, status, date_cloture, fk_user_cloture)";
	$sql .= " VALUES (".((int) $conf->entity).", ".ecole_annee_active().", ".((int) $fk_classe).", ".((int) $trimestre).", 1, ".$now.", ".((int) $user->id).")";
	$sql .= " ON DUPLICATE KEY UPDATE status = 1, date_cloture = ".$now.", fk_user_cloture = ".((int) $user->id);
	return $db->query($sql) ? 1 : -1;
}

/**
 * Rouvre le trimestre d'une classe (motif obligatoire, gardé).
 *
 * @param  DoliDB $db        Handler base
 * @param  User   $user      Utilisateur
 * @param  int    $fk_classe Classe
 * @param  int    $trimestre Trimestre
 * @param  string $motif     Motif
 * @param  string $error     Message d'erreur (sortie)
 * @return int               1 si OK, -1 sinon
 */
function notes_rouvrir($db, $user, $fk_classe, $trimestre, $motif, &$error)
{
	global $langs;
	$motif = trim((string) $motif);
	if (ecole_annee_passee()) {
		$error = $langs->trans('AnneePasseeLectureSeule', ecole_annee_label(ecole_annee_vue()));
		return -1;
	}
	if ($motif === '') {
		$error = $langs->trans('ErrorMotifObligatoire');
		return -1;
	}
	$sql = "UPDATE ".$db->prefix()."ecole_note_cloture SET status = 0, date_reouverture = '".$db->idate(dol_now())."', fk_user_reouverture = ".((int) $user->id);
	$sql .= ", motif_reouverture = '".$db->escape(dol_trunc($motif, 250, 'right', 'UTF-8', 1))."'";
	$sql .= " WHERE fk_classe = ".((int) $fk_classe)." AND trimestre = ".((int) $trimestre)." AND status = 1".ecole_annee_sql();
	if (!$db->query($sql)) {
		$error = $db->lasterror();
		return -1;
	}
	return 1;
}

/**
 * Trimestre proposé pour une classe : le premier qui n'est pas clôturé (3 si tous le sont).
 *
 * @param  DoliDB $db        Handler base
 * @param  int    $fk_classe Classe (0 = trimestre 1)
 * @return int
 */
function notes_trimestre_defaut($db, $fk_classe)
{
	for ($t = 1; $t <= 3; $t++) {
		if ($fk_classe <= 0 || !notes_est_cloture($db, $fk_classe, $t)) {
			return $t;
		}
	}
	return 3;
}

/* ------------------------------------------------------------------
 * Évaluations
 * ---------------------------------------------------------------- */

/**
 * Évaluations actives d'une matière d'une classe pour un trimestre : devoirs (par numéro) puis composition.
 *
 * @param  DoliDB $db         Handler base
 * @param  int    $fk_classe  Classe
 * @param  int    $fk_matiere Matière
 * @param  int    $trimestre  Trimestre
 * @return array<int,object>  id => évaluation
 */
function notes_evaluations($db, $fk_classe, $fk_matiere, $trimestre)
{
	$sql = "SELECT * FROM ".$db->prefix()."ecole_evaluation WHERE fk_classe = ".((int) $fk_classe)." AND fk_matiere = ".((int) $fk_matiere);
	$sql .= " AND trimestre = ".((int) $trimestre)." AND status = 1".ecole_annee_sql()." ORDER BY type, numero, rowid";
	$out = array();
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[(int) $o->rowid] = $o;
	}
	return $out;
}

/**
 * Une évaluation (même supprimée).
 *
 * @param  DoliDB $db Handler base
 * @param  int    $id Id
 * @return object|null
 */
function notes_evaluation_fetch($db, $id)
{
	$resql = $db->query("SELECT * FROM ".$db->prefix()."ecole_evaluation WHERE rowid = ".((int) $id));
	$o = $resql ? $db->fetch_object($resql) : null;
	return $o ? $o : null;
}

/**
 * Nom court d'une évaluation : « Devoir 2 » ou « Composition ».
 *
 * @param  object $ev Évaluation
 * @return string
 */
function notes_evaluation_nom($ev)
{
	global $langs;
	return ((int) $ev->type === NOTES_COMPO) ? $langs->trans('Composition') : $langs->trans('DevoirNum', (int) $ev->numero);
}

/**
 * Crée une évaluation. Le maximum de la note est celui de la matière dans la classe (sur 20 ou coefficient × 20).
 * Une seule composition par matière et par trimestre ; elle est rattachée à la session d'examens du trimestre
 * et prend la date de l'épreuve prévue dans l'emploi du temps des examens.
 *
 * @param  DoliDB $db         Handler base
 * @param  User   $user       Utilisateur
 * @param  int    $fk_classe  Classe
 * @param  int    $fk_matiere Matière
 * @param  int    $trimestre  Trimestre
 * @param  int    $type       NOTES_DEVOIR | NOTES_COMPO
 * @param  string $date       Date AAAA-MM-JJ ('' = aucune)
 * @param  string $label      Libellé facultatif (ex. « Contrôle chapitre 3 »)
 * @param  string $error      Message d'erreur (sortie)
 * @return int                Id créé, -1 si erreur
 */
function notes_evaluation_creer($db, $user, $fk_classe, $fk_matiere, $trimestre, $type, $date, $label, &$error)
{
	global $conf, $langs;
	$p = $db->prefix();
	if (!in_array((int) $trimestre, array(1, 2, 3), true) || !in_array((int) $type, array(NOTES_DEVOIR, NOTES_COMPO), true)) {
		$error = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('Trimestre'));
		return -1;
	}
	if (ecole_annee_passee()) {
		$error = $langs->trans('AnneePasseeLectureSeule', ecole_annee_label(ecole_annee_vue()));
		return -1;
	}
	if (notes_est_cloture($db, $fk_classe, $trimestre)) {
		$error = $langs->trans('ErrorTrimestreCloture');
		return -1;
	}
	$resql = $db->query("SELECT note_max FROM ".$p."ecole_classe_matiere WHERE fk_classe = ".((int) $fk_classe)." AND fk_matiere = ".((int) $fk_matiere));
	$cm = $resql ? $db->fetch_object($resql) : null;
	if (!$cm) {
		$error = $langs->trans('ErrorMatiereNonLiee');
		return -1;
	}
	$existantes = notes_evaluations($db, $fk_classe, $fk_matiere, $trimestre);
	$numero = 1;
	foreach ($existantes as $e) {
		if ((int) $e->type === (int) $type) {
			if ((int) $type === NOTES_COMPO) {
				$error = $langs->trans('ErrorCompositionExiste');
				return -1;
			}
			$numero = max($numero, (int) $e->numero + 1);
		}
	}
	$fk_session = 0;
	if ((int) $type === NOTES_COMPO) {
		$resql = $db->query("SELECT rowid FROM ".$p."ecole_session WHERE entity IN (".getEntity('ecole_session').") AND trimestre = ".((int) $trimestre)." AND status = 1 ORDER BY rowid LIMIT 1");
		$s = $resql ? $db->fetch_object($resql) : null;
		$fk_session = $s ? (int) $s->rowid : 0;
		if ($date === '' && $fk_session > 0) {
			$resql = $db->query("SELECT date_examen FROM ".$p."ecole_edt_examen WHERE fk_classe = ".((int) $fk_classe)." AND fk_matiere = ".((int) $fk_matiere)." AND fk_session = ".$fk_session." ORDER BY date_examen LIMIT 1");
			$x = $resql ? $db->fetch_object($resql) : null;
			$date = $x ? substr((string) $x->date_examen, 0, 10) : '';
		}
	}
	$sql = "INSERT INTO ".$p."ecole_evaluation (entity, annee, fk_classe, fk_matiere, trimestre, type, numero, label, date_eval, note_max, fk_session, date_creation, fk_user_creat, status)";
	$sql .= " VALUES (".((int) $conf->entity).", ".ecole_annee_active().", ".((int) $fk_classe).", ".((int) $fk_matiere).", ".((int) $trimestre).", ".((int) $type).", ".$numero;
	$sql .= ", ".(trim((string) $label) !== '' ? "'".$db->escape(dol_trunc(trim($label), 120, 'right', 'UTF-8', 1))."'" : "NULL");
	$sql .= ", ".(notes_date_ok($date) ? "'".$db->escape($date)."'" : "NULL");
	$sql .= ", ".((float) $cm->note_max).", ".($fk_session > 0 ? $fk_session : "NULL").", '".$db->idate(dol_now())."', ".((int) $user->id).", 1)";
	if (!$db->query($sql)) {
		$error = $db->lasterror();
		return -1;
	}
	return (int) $db->last_insert_id($p."ecole_evaluation");
}

/**
 * Une évaluation peut-elle encore être modifiée ? Non si elle appartient à une année scolaire passée.
 *
 * @param  object $ev Évaluation
 * @return bool
 */
function notes_evaluation_modifiable($ev)
{
	return !ecole_annee_passee() && (!isset($ev->annee) || (int) $ev->annee === 0 || (int) $ev->annee === ecole_annee_active());
}

/**
 * Modifie la date et le libellé d'une évaluation.
 *
 * @param  DoliDB $db    Handler base
 * @param  User   $user  Utilisateur
 * @param  object $ev    Évaluation
 * @param  string $date  Date AAAA-MM-JJ ('' = aucune)
 * @param  string $label Libellé
 * @return int           1 si OK, -1 sinon
 */
function notes_evaluation_modifier($db, $user, $ev, $date, $label)
{
	if (!notes_evaluation_modifiable($ev)) {
		return -1;
	}
	$sql = "UPDATE ".$db->prefix()."ecole_evaluation SET date_eval = ".(notes_date_ok($date) ? "'".$db->escape($date)."'" : "NULL");
	$sql .= ", label = ".(trim((string) $label) !== '' ? "'".$db->escape(dol_trunc(trim($label), 120, 'right', 'UTF-8', 1))."'" : "NULL");
	$sql .= ", fk_user_modif = ".((int) $user->id)." WHERE rowid = ".((int) $ev->rowid);
	return $db->query($sql) ? 1 : -1;
}

/**
 * Supprime une évaluation : elle est gardée (statut 0) avec ses notes pour l'historique, mais ne compte plus.
 *
 * @param  DoliDB $db   Handler base
 * @param  User   $user Utilisateur
 * @param  object $ev   Évaluation
 * @return int          1 si OK, -1 sinon
 */
function notes_evaluation_supprimer($db, $user, $ev)
{
	if (!notes_evaluation_modifiable($ev)) {
		return -1;
	}
	$sql = "UPDATE ".$db->prefix()."ecole_evaluation SET status = 0, date_suppression = '".$db->idate(dol_now())."', fk_user_suppression = ".((int) $user->id);
	$sql .= " WHERE rowid = ".((int) $ev->rowid);
	return $db->query($sql) ? 1 : -1;
}

/* ------------------------------------------------------------------
 * Élèves et notes
 * ---------------------------------------------------------------- */

/**
 * Élèves à noter : ceux de la classe (inscrits, suspendus), triés par numéro d'appel, plus les anciens élèves
 * (changés de classe, partis...) qui ont déjà une note dans ces évaluations (marqués 'ancien' = 1).
 *
 * @param  DoliDB $db        Handler base
 * @param  int    $fk_classe Classe
 * @param  int[]  $evalIds   Évaluations affichées
 * @return array<int,object>
 */
function notes_eleves($db, $fk_classe, $evalIds)
{
	$p = $db->prefix();
	$cols = "e.rowid, e.ref, e.nom_fr, e.nom_ar, e.numero_appel, e.status, e.fk_classe";
	$passee = ecole_annee_passee();
	if ($passee) {
		// Année passée : élèves dont c'était la dernière classe de l'année (historique des classes)
		list($debut, $fin) = ecole_annee_bornes(ecole_annee_vue());
		$sql = "SELECT ".$cols." FROM ".$p."ecole_eleve e INNER JOIN ".$p."ecole_eleve_classe h ON h.fk_eleve = e.rowid";
		$sql .= " WHERE e.entity IN (".getEntity('ecole_eleve').") AND h.fk_classe = ".((int) $fk_classe);
		$sql .= " AND h.date_debut <= '".$fin."' AND (h.date_fin IS NULL OR h.date_fin >= '".$debut."')";
		$sql .= " AND NOT EXISTS (SELECT 1 FROM ".$p."ecole_eleve_classe h2 WHERE h2.fk_eleve = h.fk_eleve AND h2.date_debut > h.date_debut AND h2.date_debut <= '".$fin."')";
		$membres = array();
		$resql = $db->query($sql);
		while ($resql && ($o = $db->fetch_object($resql))) {
			$membres[(int) $o->rowid] = true;
		}
	} else {
		$sql = "SELECT ".$cols." FROM ".$p."ecole_eleve e WHERE e.entity IN (".getEntity('ecole_eleve').") AND e.fk_classe = ".((int) $fk_classe);
		$sql .= " AND e.status IN (".implode(',', EcoleEleve::statusOccupantPlace()).")";
	}
	if (!empty($evalIds)) {
		$sql .= " UNION SELECT ".$cols." FROM ".$p."ecole_eleve e INNER JOIN ".$p."ecole_note n ON n.fk_eleve = e.rowid";
		$sql .= " WHERE n.fk_evaluation IN (".implode(',', array_map('intval', $evalIds)).")";
	}
	$out = array();
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		if ($passee) {
			$o->ancien = isset($membres[(int) $o->rowid]) ? 0 : 1;
		} else {
			$o->ancien = ((int) $o->fk_classe !== (int) $fk_classe || !in_array((int) $o->status, EcoleEleve::statusOccupantPlace(), true)) ? 1 : 0;
		}
		$out[(int) $o->rowid] = $o;
	}
	uasort($out, function ($a, $b) {
		if ($a->ancien !== $b->ancien) {
			return $a->ancien - $b->ancien;
		}
		if ((int) $a->numero_appel !== (int) $b->numero_appel) {
			return ($a->numero_appel === null ? 9999 : (int) $a->numero_appel) - ($b->numero_appel === null ? 9999 : (int) $b->numero_appel);
		}
		return strcmp((string) $a->nom_fr, (string) $b->nom_fr);
	});
	return $out;
}

/**
 * Notes saisies pour des évaluations : [fk_evaluation][fk_eleve] => code (voir notes_code).
 *
 * @param  DoliDB $db      Handler base
 * @param  int[]  $evalIds Évaluations
 * @return array<int,array<int,string>>
 */
function notes_valeurs($db, $evalIds)
{
	$out = array();
	if (empty($evalIds)) {
		return $out;
	}
	$resql = $db->query("SELECT fk_evaluation, fk_eleve, valeur, absence FROM ".$db->prefix()."ecole_note WHERE fk_evaluation IN (".implode(',', array_map('intval', $evalIds)).")");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[(int) $o->fk_evaluation][(int) $o->fk_eleve] = notes_code($o->valeur, $o->absence);
	}
	return $out;
}

/**
 * Code d'une note : '' (pas de note), 'ABS', 'DISP' ou le nombre ('12.5').
 *
 * @param  mixed  $valeur  Valeur
 * @param  string $absence Absence
 * @return string
 */
function notes_code($valeur, $absence)
{
	if ($absence === NOTES_ABS || $absence === NOTES_DISP) {
		return $absence;
	}
	if ($valeur === null || $valeur === '') {
		return '';
	}
	return rtrim(rtrim(number_format((float) $valeur, 2, '.', ''), '0'), '.');
}

/**
 * Nombre affiché avec le séparateur décimal de la langue (12,5).
 *
 * @param  float|string $v Nombre
 * @return string
 */
function notes_fmt($v)
{
	global $langs;
	$s = rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
	$sep = $langs->transnoentitiesnoconv('SeparatorDecimal');
	return ($sep && $sep !== 'SeparatorDecimal') ? str_replace('.', $sep, $s) : $s;
}

/**
 * Libellé d'un code de note : « 12,5 », « Absent », « Dispensé », « — ».
 *
 * @param  string $code Code
 * @return string
 */
function notes_code_label($code)
{
	global $langs;
	if ($code === NOTES_ABS) {
		return $langs->trans('NoteAbsent');
	}
	if ($code === NOTES_DISP) {
		return $langs->trans('NoteDispense');
	}
	return ($code === '' || $code === null) ? '—' : notes_fmt($code);
}

/**
 * Lit une note saisie : '' (effacer), 'ABS', 'DISP' ou un nombre entre 0 et le maximum (2 décimales, virgule ou point).
 *
 * @param  string $saisie  Texte saisi
 * @param  string $absence Choix « Abs » / « Disp » ('' = aucun)
 * @param  float  $max     Maximum de la note
 * @param  string $err     Erreur (sortie)
 * @return string|false    Code, ou false si la saisie est invalide
 */
function notes_parse($saisie, $absence, $max, &$err)
{
	global $langs;
	if ($absence === NOTES_ABS || $absence === NOTES_DISP) {
		return $absence;
	}
	$s = str_replace(array(',', ' '), array('.', ''), trim((string) $saisie));
	if ($s === '') {
		return '';
	}
	$up = strtoupper($s);
	if (in_array($up, array('A', 'ABS'), true)) {
		return NOTES_ABS;
	}
	if (in_array($up, array('D', 'DISP'), true)) {
		return NOTES_DISP;
	}
	if (!preg_match('/^\d+(\.\d{1,2})?$/', $s)) {
		$err = $langs->trans('ErrorNoteInvalide', $saisie);
		return false;
	}
	if ((float) $s > (float) $max + 0.00001) {
		$err = $langs->trans('ErrorNoteTropGrande', notes_fmt($s), notes_fmt($max));
		return false;
	}
	return notes_code($s, '');
}

/**
 * Enregistre les notes d'une évaluation. Seules les notes qui changent sont écrites, et chaque changement
 * est gardé dans l'historique (avant / après, qui, quand, motif).
 *
 * @param  DoliDB $db    Handler base
 * @param  User   $user  Utilisateur
 * @param  object $ev    Évaluation
 * @param  array  $codes fk_eleve => code (voir notes_code)
 * @param  string $motif Motif (obligatoire si le trimestre est clôturé : contrôlé par l'appelant)
 * @param  string $error Message d'erreur (sortie)
 * @return int           Nombre de notes changées, -1 si erreur
 */
function notes_enregistrer($db, $user, $ev, $codes, $motif, &$error)
{
	global $conf, $langs;
	$p = $db->prefix();
	if (!notes_evaluation_modifiable($ev)) {
		$error = $langs->trans('AnneePasseeLectureSeule', ecole_annee_label(isset($ev->annee) ? (int) $ev->annee : ecole_annee_vue()));
		return -1;
	}
	$avant = notes_valeurs($db, array((int) $ev->rowid));
	$avant = isset($avant[(int) $ev->rowid]) ? $avant[(int) $ev->rowid] : array();
	$now = "'".$db->idate(dol_now())."'";
	$nb = 0;

	$db->begin();
	foreach ($codes as $eid => $code) {
		$old = isset($avant[(int) $eid]) ? $avant[(int) $eid] : '';
		if ($old === $code) {
			continue;
		}
		$valeur = ($code === '' || $code === NOTES_ABS || $code === NOTES_DISP) ? 'NULL' : (string) ((float) $code);
		$absence = ($code === NOTES_ABS || $code === NOTES_DISP) ? "'".$code."'" : 'NULL';
		if ($code === '') {
			$sql = "DELETE FROM ".$p."ecole_note WHERE fk_evaluation = ".((int) $ev->rowid)." AND fk_eleve = ".((int) $eid);
		} elseif ($old === '') {
			$sql = "INSERT INTO ".$p."ecole_note (entity, fk_evaluation, fk_eleve, valeur, absence, date_creation, fk_user_creat)";
			$sql .= " VALUES (".((int) $conf->entity).", ".((int) $ev->rowid).", ".((int) $eid).", ".$valeur.", ".$absence.", ".$now.", ".((int) $user->id).")";
		} else {
			$sql = "UPDATE ".$p."ecole_note SET valeur = ".$valeur.", absence = ".$absence.", fk_user_modif = ".((int) $user->id);
			$sql .= " WHERE fk_evaluation = ".((int) $ev->rowid)." AND fk_eleve = ".((int) $eid);
		}
		$sqllog = "INSERT INTO ".$p."ecole_note_log (entity, fk_evaluation, fk_eleve, avant, apres, motif, fk_user, date_creation)";
		$sqllog .= " VALUES (".((int) $conf->entity).", ".((int) $ev->rowid).", ".((int) $eid).", '".$db->escape($old)."', '".$db->escape($code)."'";
		$sqllog .= ", ".(trim((string) $motif) !== '' ? "'".$db->escape(dol_trunc(trim($motif), 250, 'right', 'UTF-8', 1))."'" : "NULL").", ".((int) $user->id).", ".$now.")";
		if (!$db->query($sql) || !$db->query($sqllog)) {
			$error = $db->lasterror();
			$db->rollback();
			return -1;
		}
		$nb++;
	}
	$db->commit();
	return $nb;
}

/**
 * Nombre de notes saisies et moyenne (dans le barème de l'évaluation) d'une colonne.
 *
 * @param  array<int,string> $codes fk_eleve => code
 * @return array{nb:int,abs:int,disp:int,moyenne:float|null}
 */
function notes_stats($codes)
{
	$nb = 0;
	$abs = 0;
	$disp = 0;
	$somme = 0.0;
	$n = 0;
	foreach ($codes as $c) {
		if ($c === '') {
			continue;
		}
		$nb++;
		if ($c === NOTES_ABS) {
			$abs++;
		} elseif ($c === NOTES_DISP) {
			$disp++;
		} else {
			$somme += (float) $c;
			$n++;
		}
	}
	return array('nb' => $nb, 'abs' => $abs, 'disp' => $disp, 'moyenne' => $n > 0 ? $somme / $n : null);
}

/* ------------------------------------------------------------------
 * Historique
 * ---------------------------------------------------------------- */

/**
 * Historique des notes filtré (le plus récent d'abord).
 *
 * @param  DoliDB $db      Handler base
 * @param  User   $user    Utilisateur (un enseignant ne voit que ses classes et matières)
 * @param  array  $f       Filtres : fk_classe, fk_matiere, trimestre, fk_evaluation, eleve (texte), fk_user
 * @param  int    $limit   Nombre de lignes
 * @param  int    $offset  Décalage
 * @param  int    $total   Nombre total (sortie)
 * @return object[]
 */
function notes_historique($db, $user, $f, $limit, $offset, &$total)
{
	$p = $db->prefix();
	$from = " FROM ".$p."ecole_note_log l INNER JOIN ".$p."ecole_evaluation v ON v.rowid = l.fk_evaluation";
	$from .= " INNER JOIN ".$p."ecole_eleve e ON e.rowid = l.fk_eleve";
	$from .= " LEFT JOIN ".$p."ecole_classe c ON c.rowid = v.fk_classe LEFT JOIN ".$p."ecole_matiere m ON m.rowid = v.fk_matiere";
	$where = " WHERE l.entity IN (".getEntity('ecole_note_log').")".ecole_annee_sql('v.annee');
	foreach (array('fk_classe' => 'v.fk_classe', 'fk_matiere' => 'v.fk_matiere', 'trimestre' => 'v.trimestre', 'fk_evaluation' => 'l.fk_evaluation', 'fk_user' => 'l.fk_user', 'fk_eleve' => 'l.fk_eleve') as $k => $col) {
		if (!empty($f[$k])) {
			$where .= " AND ".$col." = ".((int) $f[$k]);
		}
	}
	if (!empty($f['eleve'])) {
		$where .= " AND ".natural_search(array('e.nom_fr', 'e.nom_ar', 'e.ref'), $f['eleve'], 0, 1);
	}
	if (!empty($f['type'])) {
		$where .= " AND v.type = ".((int) $f['type']);
	}
	if (!empty($f['du']) && notes_date_ok($f['du'])) {
		$where .= " AND l.date_creation >= '".$db->escape($f['du'])." 00:00:00'";
	}
	if (!empty($f['au']) && notes_date_ok($f['au'])) {
		$where .= " AND l.date_creation <= '".$db->escape($f['au'])." 23:59:59'";
	}
	// Avant / après : « abs », « disp », un nombre (12 ou 12,5) ou « vide »
	foreach (array('avant', 'apres') as $k) {
		if (!isset($f[$k]) || trim((string) $f[$k]) === '') {
			continue;
		}
		$v = strtoupper(str_replace(',', '.', trim((string) $f[$k])));
		if (in_array($v, array('VIDE', '-', '—'), true)) {
			$where .= " AND (l.".$k." = '' OR l.".$k." IS NULL)";
		} elseif (strpos($v, 'ABS') === 0 || $v === 'A') {
			$where .= " AND l.".$k." = 'ABS'";
		} elseif (strpos($v, 'DISP') === 0 || $v === 'D') {
			$where .= " AND l.".$k." = 'DISP'";
		} else {
			$where .= " AND l.".$k." LIKE '".$db->escape($db->escapeforlike($v))."%'";
		}
	}
	if (!empty($f['motif'])) {
		if ($f['motif'] === '__avec') {
			$where .= " AND l.motif IS NOT NULL AND l.motif <> ''";
		} else {
			$where .= " AND ".natural_search('l.motif', $f['motif'], 0, 1);
		}
	}
	if (!notes_voit_tout($user)) {
		$conds = array();
		foreach (notes_affectations($db, (int) $user->id) as $cid => $mats) {
			$conds[] = "(v.fk_classe = ".((int) $cid)." AND v.fk_matiere IN (".implode(',', array_map('intval', array_keys($mats)))."))";
		}
		$where .= " AND ".(empty($conds) ? "1 = 0" : "(".implode(' OR ', $conds).")");
	}
	$total = 0;
	$resql = $db->query("SELECT COUNT(*) as nb".$from.$where);
	if ($resql && ($o = $db->fetch_object($resql))) {
		$total = (int) $o->nb;
	}
	$sql = "SELECT l.rowid, l.avant, l.apres, l.motif, l.fk_user, l.date_creation, l.fk_eleve, v.rowid as fk_evaluation, v.type, v.numero, v.trimestre, v.status as vstatus,";
	$sql .= " v.fk_classe, v.fk_matiere, v.note_max, e.ref as eref, e.nom_fr, e.nom_ar, c.ref as cref, m.label_fr as mlabel_fr, m.label_ar as mlabel_ar";
	$sql .= $from.$where." ORDER BY l.date_creation DESC, l.rowid DESC".$db->plimit($limit, $offset);
	$out = array();
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[] = $o;
	}
	return $out;
}

/* ------------------------------------------------------------------
 * Divers
 * ---------------------------------------------------------------- */

/**
 * Date AAAA-MM-JJ valide ?
 *
 * @param  string $s Date
 * @return bool
 */
function notes_date_ok($s)
{
	return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $s) && checkdate((int) substr($s, 5, 2), (int) substr($s, 8, 2), (int) substr($s, 0, 4));
}

/**
 * Libellé d'un trimestre.
 *
 * @param  int $t Trimestre
 * @return string
 */
function notes_trimestre_label($t)
{
	global $langs;
	return $langs->trans('Trimestre'.((int) $t));
}

/**
 * Onglets de la configuration du module.
 *
 * @return array
 */
function notes_admin_prepare_head()
{
	global $langs;
	$langs->load('notes@notes');
	$head = array();
	$head[] = array(dol_buildpath('/notes/admin/regles.php', 1), $langs->trans('MenuReglesCalcul'), 'regles');
	$head[] = array(dol_buildpath('/notes/mention/list.php', 1), $langs->trans('MenuMentions'), 'mentions');
	$head[] = array(dol_buildpath('/notes/distinction/list.php', 1), $langs->trans('MenuDistinctions'), 'distinctions');
	$head[] = array(dol_buildpath('/notes/modele/list.php', 1), $langs->trans('MenuModelesBulletin'), 'modeles');
	$head[] = array(dol_buildpath('/notes/modele/apercu.php', 1), $langs->trans('ApercuModeles'), 'apercu');
	$head[] = array(dol_buildpath('/notes/admin/suivi.php', 1), $langs->trans('MenuConfigSuivi'), 'suivi');
	return $head;
}

/**
 * Paramètres des pages génériques (liste / fiche de crud.lib.php) des objets du module.
 *
 * @param  string $name mention | distinction | modele
 * @return array|null
 */
function notes_crud_config($name)
{
	$base = array('module' => 'notes', 'perm_read' => 'config.gerer', 'perm_write' => 'config.gerer', 'perm_delete' => 'config.gerer', 'perm_export' => 'config.gerer');
	$cfgs = array(
		'mention' => array('dir' => 'mention', 'class' => 'EcoleNoteMention', 'title' => 'Mentions', 'ficheTitle' => 'Mention', 'newLabel' => 'NouvelleMention'),
		'distinction' => array('dir' => 'distinction', 'class' => 'EcoleNoteDistinction', 'title' => 'Distinctions', 'ficheTitle' => 'Distinction', 'newLabel' => 'NouvelleDistinction'),
		'modele' => array('dir' => 'modele', 'class' => 'EcoleBulletinModele', 'title' => 'ModelesBulletin', 'ficheTitle' => 'ModeleBulletin', 'newLabel' => 'NouveauModele',
			'extra_view' => 'notes_modele_extra_view',
			'extra_columns' => array('apercu' => array('label' => 'Apercu', 'render' => 'notes_col_apercu'))),
	);
	return isset($cfgs[$name]) ? array_merge($base, $cfgs[$name]) : null;
}

/* ------------------------------------------------------------------
 * Filtres par colonne des tableaux affichés (filtrage immédiat dans la page)
 * ---------------------------------------------------------------- */

/**
 * Ligne de filtres d'un tableau : un filtre par colonne, appliqué immédiatement (sans recharger la page).
 * Le tableau doit avoir la classe « notes-filtrable » ; chaque ligne de données la classe « oddeven ».
 * Une cellule peut porter data-f="valeur" : c'est cette valeur qui est filtrée (sinon son texte).
 * Colonnes : null (pas de filtre) | 'text' | 'range' (minimum / maximum, nombres) | array(valeur => libellé) (liste).
 *
 * @param  array $cols Filtre de chaque colonne, dans l'ordre
 * @return string      HTML (<tr>)
 */
function notes_filtres_ligne($cols)
{
	global $langs;
	$out = '<tr class="liste_titre_filter notes-filtres">';
	foreach (array_values($cols) as $i => $c) {
		$out .= '<td class="liste_titre">';
		if ($c === 'text') {
			$out .= '<input type="text" class="flat maxwidth100 notes-f" data-col="'.$i.'" data-type="text" placeholder="&#128269;">';
		} elseif ($c === 'range') {
			$out .= '<input type="number" step="any" class="flat width50 notes-f" data-col="'.$i.'" data-type="min" placeholder="'.dol_escape_htmltag($langs->trans('FiltreMin')).'">';
			$out .= ' <input type="number" step="any" class="flat width50 notes-f" data-col="'.$i.'" data-type="max" placeholder="'.dol_escape_htmltag($langs->trans('FiltreMax')).'">';
		} elseif (is_array($c)) {
			$out .= '<select class="flat maxwidth150 notes-f" data-col="'.$i.'" data-type="select"><option value=""></option>';
			foreach ($c as $v => $l) {
				$out .= '<option value="'.dol_escape_htmltag((string) $v).'">'.dol_escape_htmltag($l).'</option>';
			}
			$out .= '</select>';
		}
		$out .= '</td>';
	}
	$out .= '</tr>';
	return $out.notes_filtres_js();
}

/**
 * Script des filtres par colonne (écrit une seule fois par page).
 *
 * @return string
 */
function notes_filtres_js()
{
	global $langs;
	static $done = false;
	if ($done) {
		return '';
	}
	$done = true;
	$libNb = dol_escape_js($langs->transnoentities('NbLignesAffichees', '__A__', '__B__'), 2);
	return '<script>
(function () {
	function norm(s) { return (s || "").toString().toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "").trim(); }
	function num(s) { var v = parseFloat((s || "").toString().replace(",", ".").replace(/[^0-9.\-]/g, "")); return isNaN(v) ? null : v; }
	function appliquer(table) {
		var fs = table.querySelectorAll(".notes-f"), total = 0, vus = 0;
		table.querySelectorAll("tr.oddeven").forEach(function (tr) {
			var ok = true;
			fs.forEach(function (f) {
				if (!ok || f.value === "") { return; }
				var td = tr.cells[parseInt(f.dataset.col, 10)];
				if (!td) { return; }
				var v = td.hasAttribute("data-f") ? td.getAttribute("data-f") : td.textContent;
				if (f.dataset.type === "text") { ok = norm(v).indexOf(norm(f.value)) !== -1; }
				else if (f.dataset.type === "select") { ok = norm(v) === norm(f.value) || (" " + norm(v) + " ").indexOf(" " + norm(f.value) + " ") !== -1; }
				else { var n = num(v), b = num(f.value); ok = n !== null && (f.dataset.type === "min" ? n >= b : n <= b); }
			});
			tr.style.display = ok ? "" : "none";
			total++; vus += ok ? 1 : 0;
		});
		var info = table.parentNode.querySelector(".notes-f-info");
		if (info) { info.textContent = (vus < total) ? "'.$libNb.'".replace("__A__", vus).replace("__B__", total) : ""; }
	}
	document.addEventListener("DOMContentLoaded", function () {
		document.querySelectorAll("table.notes-filtrable").forEach(function (table) {
			table.querySelectorAll(".notes-f").forEach(function (f) {
				f.addEventListener(f.tagName === "SELECT" ? "change" : "input", function () { appliquer(table); });
				f.addEventListener("keydown", function (ev) { if (ev.key === "Enter") { ev.preventDefault(); } }); // pas d envoi du formulaire
			});
			var info = document.createElement("div");
			info.className = "notes-f-info opacitymedium small";
			table.parentNode.insertBefore(info, table.nextSibling);
		});
	});
})();
</script>';
}

/**
 * Colonne « Aperçu » de la liste des modèles de bulletin.
 *
 * @param  EcoleBulletinModele $rec Modèle
 * @return string
 */
function notes_col_apercu($rec)
{
	global $langs;
	return '<a href="'.dol_buildpath('/notes/modele/apercu.php', 1).'?id='.((int) $rec->id).'">'.img_picto('', 'fa-eye', 'class="pictofixedwidth"').$langs->trans('Apercu').'</a>';
}

/**
 * Sous la fiche d'un modèle de bulletin : boutons d'aperçu (bulletin trimestriel, relevé annuel).
 *
 * @param  EcoleBulletinModele $object Modèle
 * @return void
 */
function notes_modele_extra_view($object)
{
	global $langs;
	$url = dol_buildpath('/notes/modele/apercu.php', 1).'?id='.((int) $object->id);
	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.$url.'&type=bulletin">'.img_picto('', 'fa-eye', 'class="pictofixedwidth"').$langs->trans('ApercuBulletin').'</a>';
	print '<a class="butAction" href="'.$url.'&type=releve">'.img_picto('', 'fa-eye', 'class="pictofixedwidth"').$langs->trans('ApercuReleve').'</a>';
	print '</div>';
}

/**
 * Filtres de l'historique des notes lus dans la requête (page et export).
 *
 * @param  bool $remove true = filtres effacés
 * @return array
 */
function notes_historique_filtres($remove = false)
{
	// Listes de choix : la valeur vide envoyée par Dolibarr est « -1 » (d'où max(0, ...))
	return array(
		'fk_classe' => $remove ? 0 : max(0, GETPOSTINT('fk_classe')),
		'fk_matiere' => $remove ? 0 : max(0, GETPOSTINT('fk_matiere')),
		'trimestre' => $remove ? 0 : max(0, GETPOSTINT('trimestre')),
		'fk_evaluation' => $remove ? 0 : max(0, GETPOSTINT('fk_evaluation')),
		'fk_eleve' => $remove ? 0 : max(0, GETPOSTINT('fk_eleve')),
		'eleve' => $remove ? '' : GETPOST('eleve', 'alphanohtml'),
		'fk_user' => $remove ? 0 : max(0, GETPOSTINT('fk_user')),
		'type' => $remove ? 0 : max(0, GETPOSTINT('type')),
		'du' => $remove ? '' : GETPOST('du', 'alpha'),
		'au' => $remove ? '' : GETPOST('au', 'alpha'),
		'avant' => $remove ? '' : GETPOST('avant', 'alphanohtml'),
		'apres' => $remove ? '' : GETPOST('apres', 'alphanohtml'),
		'motif' => $remove ? '' : (GETPOSTINT('motif_avec') ? '__avec' : GETPOST('motif', 'alphanohtml')),
	);
}
