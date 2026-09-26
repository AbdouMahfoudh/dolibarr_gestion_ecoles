<?php
/**
 * Fonctions communes du module Classes.
 *
 * Fichier : custom/classes/core/lib/classes.lib.php
 */

/**
 * Onglets de la fiche d'une classe.
 *
 * @param  EcoleClasse $object Classe
 * @return array               Format dol_get_fiche_head()
 */
function classe_prepare_head($object)
{
	global $langs, $user;
	$langs->load('classes@classes');

	$id = (int) $object->id;
	$head = array();
	$h = 0;

	$head[$h][0] = dol_buildpath('/classes/classe/card.php', 1).'?id='.$id;
	$head[$h][1] = $langs->trans('Fiche');
	$head[$h][2] = 'card';
	$h++;

	// Élèves de la classe par numéro d'appel (module Élèves)
	if (isModEnabled('eleves') && $user->hasRight('eleves', 'eleve', 'lire')) {
		$langs->load('eleves@eleves');
		$head[$h][0] = dol_buildpath('/eleves/classe/eleves.php', 1).'?id='.$id;
		$head[$h][1] = $langs->trans('Eleves');
		$head[$h][2] = 'eleves';
		$h++;
	}

	$head[$h][0] = dol_buildpath('/classes/classe/matieres.php', 1).'?id='.$id;
	$head[$h][1] = $langs->trans('MatieresCoefficients');
	$head[$h][2] = 'matieres';
	$h++;

	$head[$h][0] = dol_buildpath('/classes/classe/edt.php', 1).'?id='.$id;
	$head[$h][1] = $langs->trans('EmploiDuTempsCours');
	$head[$h][2] = 'edt';
	$h++;

	$head[$h][0] = dol_buildpath('/classes/classe/examens.php', 1).'?id='.$id;
	$head[$h][1] = $langs->trans('EmploiDuTempsExamens');
	$head[$h][2] = 'examens';
	$h++;

	// Paiements des élèves de la classe, mois par mois (module Élèves)
	if (isModEnabled('eleves') && $user->hasRight('eleves', 'paiement', 'lire')) {
		$langs->load('eleves@eleves');
		$head[$h][0] = dol_buildpath('/eleves/classe/paiements.php', 1).'?id='.$id;
		$head[$h][1] = $langs->trans('PaiementsClasse');
		$head[$h][2] = 'paiements';
		$h++;
	}

	// Absences, retards et sanctions des élèves de la classe (module Élèves)
	if (isModEnabled('eleves') && $user->hasRight('eleves', 'absence', 'lire')) {
		$langs->load('eleves@eleves');
		$head[$h][0] = dol_buildpath('/eleves/classe/discipline.php', 1).'?id='.$id;
		$head[$h][1] = $langs->trans('AbsencesDiscipline');
		$head[$h][2] = 'discipline';
		$h++;
	}

	// Moyennes, rangs et bulletins de la classe (module Notes)
	if (isModEnabled('notes') && $user->hasRight('notes', 'bulletin', 'lire')) {
		$langs->load('notes@notes');
		$head[$h][0] = dol_buildpath('/notes/classe.php', 1).'?id='.$id;
		$head[$h][1] = $langs->trans('NotesResultats');
		$head[$h][2] = 'notes';
		$h++;
	}

	return $head;
}

/**
 * Affiche l'en-tête d'un onglet de la fiche classe (onglets + bannière). Le code appelant
 * doit fermer avec dol_get_fiche_end().
 *
 * @param  EcoleClasse $object Classe
 * @param  string      $tab    Onglet actif (card, matieres, edt, examens)
 * @return void
 */
function classe_print_header($object, $tab)
{
	global $langs;

	print dol_get_fiche_head(classe_prepare_head($object), $tab, $langs->trans('Classe'), -1, $object->picto);
	$linkback = '<a href="'.dol_buildpath('/classes/classe/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
	dol_banner_tab($object, 'id', $linkback, 0, 'rowid', 'ref', '<div class="refidno">'.dol_escape_htmltag(ecole_label($object)).'</div>', '', 0, '', ''); // le statut est ajouté par Dolibarr
}

/**
 * Résumé affiché sous la fiche d'une classe : matières, coefficients, emploi du temps, examens.
 *
 * @param  EcoleClasse $object Classe
 * @return void
 */
function classe_extra_view($object)
{
	global $langs;

	$r = $object->getResume();
	$id = (int) $object->id;
	$base = dol_buildpath('/classes/classe/', 1);

	print '<div class="fichecenter"><br>';
	print load_fiche_titre($langs->trans('ResumeClasse'), '', '');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Rubrique').'</td><td class="right">'.$langs->trans('Nombre').'</td><td></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('MatieresCoefficients').'</td><td class="right">'.$r['matieres'].'</td>';
	print '<td><a href="'.$base.'matieres.php?id='.$id.'">'.$langs->trans('Gerer').'</a> &nbsp; <span class="opacitymedium">'.$langs->trans('TotalCoefficients').' : '.price2num($r['coefficients']).'</span></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('EmploiDuTempsCours').'</td><td class="right">'.$r['cours'].'</td>';
	print '<td><a href="'.$base.'edt.php?id='.$id.'">'.$langs->trans('Gerer').'</a></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('EmploiDuTempsExamens').'</td><td class="right">'.$r['examens'].'</td>';
	print '<td><a href="'.$base.'examens.php?id='.$id.'">'.$langs->trans('Gerer').'</a></td></tr>';
	print '</table>';
	print '</div><br>';
}

/**
 * Choix de la langue d'enseignement d'une matière dans une classe : '' = celle de la matière (rappelée entre parenthèses).
 *
 * @param  string $langueMatiere Langue de la matière dans le catalogue ('fr' / 'ar', '' si inconnue)
 * @return array<string,string>
 */
function classe_matiere_langues($langueMatiere)
{
	global $langs;
	$noms = array('fr' => $langs->trans('LangueFrancais'), 'ar' => $langs->trans('LangueArabe'));
	$defaut = $langs->trans('CommeLaMatiere');
	if (isset($noms[$langueMatiere])) {
		$defaut .= ' ('.$noms[$langueMatiere].')';
	}
	return array('' => $defaut) + $noms;
}

/**
 * Jours de la semaine (1 = lundi ... 7 = dimanche) => clé de traduction Dolibarr.
 *
 * @return array<int,string>
 */
function ecole_jours()
{
	return array(1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday');
}

/**
 * Jours ouvrables de l'école (constante ECOLE_JOURS_OUVRABLES, ex. « 1,2,3,4,5,6 »).
 *
 * @return int[]
 */
function ecole_jours_ouvrables()
{
	$out = array();
	foreach (explode(',', getDolGlobalString('ECOLE_JOURS_OUVRABLES', '1,2,3,4,5,6')) as $j) {
		$j = (int) trim($j);
		if ($j >= 1 && $j <= 7) {
			$out[$j] = $j;
		}
	}
	ksort($out);
	return array_values($out);
}

/**
 * Heure au format HH:MM valide ?
 *
 * @param  string $s Heure
 * @return bool
 */
function ecole_hhmm_ok($s)
{
	return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $s);
}

/**
 * Salles de l'établissement : id => référence (ou « référence - libellé »).
 *
 * @param  DoliDB $db         Handler base
 * @param  bool   $onlyactive true = seulement les salles actives (pour les listes de choix)
 * @param  bool   $withlabel  true = « REF - Libellé »
 * @param  int    $keep       Id d'une salle à garder même si elle est inactive (valeur actuelle d'un formulaire)
 * @return array<int,string>
 */
function ecole_salles($db, $onlyactive = false, $withlabel = false, $keep = 0)
{
	$out = array();
	$sql = "SELECT rowid, ref, label_fr, label_ar FROM ".$db->prefix()."ecole_salle WHERE entity IN (".getEntity('ecole_salle').")";
	if ($onlyactive) {
		$sql .= " AND (status = 1".($keep > 0 ? " OR rowid = ".((int) $keep) : "").")";
	}
	$sql .= " ORDER BY ref";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$out[(int) $obj->rowid] = $withlabel ? $obj->ref.' - '.ecole_label($obj) : $obj->ref;
		}
	}
	return $out;
}

/**
 * Vérifie qu'une salle choisie est active (ajoute l'erreur à $errors sinon).
 *
 * @param  DoliDB $db     Handler base
 * @param  int    $id     Id de la salle (0 = aucune)
 * @param  array  $errors Liste d'erreurs à compléter
 * @return void
 */
function ecole_check_salle_active($db, $id, &$errors)
{
	global $langs;
	if ((int) $id <= 0) {
		return;
	}
	$r = $db->query("SELECT ref, status FROM ".$db->prefix()."ecole_salle WHERE rowid = ".((int) $id));
	$o = $r ? $db->fetch_object($r) : null;
	if (!$o) {
		$errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('Salle'));
	} elseif ((int) $o->status !== 1) {
		$errors[] = $langs->trans('ErrorEcoleSalleInactive', $o->ref);
	}
}

/**
 * Catégories d'utilisateurs de l'école (champ complémentaire « ecole_categorie » de la fiche utilisateur).
 * Clé => clé de traduction.
 *
 * @return array<string,string>
 */
function ecole_categories_utilisateur()
{
	return array(
		'direction' => 'EcoleCatDirection',
		'enseignant' => 'EcoleCatEnseignant',
		'surveillant' => 'EcoleCatSurveillant',
		'secretariat' => 'EcoleCatSecretariat',
		'comptable' => 'EcoleCatComptable',
		'agent' => 'EcoleCatAgent',
		'chauffeur' => 'EcoleCatChauffeur',
		'autre' => 'EcoleCatAutre',
	);
}

/**
 * Utilisateurs actifs appartenant à au moins une des catégories données : id => nom.
 *
 * @param  DoliDB   $db   Handler base
 * @param  string[] $cats Catégories (ex. array('enseignant', 'surveillant'))
 * @param  int      $keep Id d'un utilisateur à garder (valeur actuelle d'un formulaire)
 * @return array<int,string>
 */
function ecole_users_categories($db, $cats, $keep = 0)
{
	$conds = array();
	foreach ($cats as $c) {
		$conds[] = "FIND_IN_SET('".$db->escape($c)."', ef.ecole_categorie) > 0";
	}
	$emp = ecole_table_exists($db, 'ecole_employe');
	$sql = "SELECT u.rowid, u.firstname, u.lastname, u.login".($emp ? ", e.nom_fr, e.nom_ar" : "")." FROM ".$db->prefix()."user as u";
	$sql .= " LEFT JOIN ".$db->prefix()."user_extrafields as ef ON ef.fk_object = u.rowid";
	if ($emp) {
		$sql .= " LEFT JOIN ".$db->prefix()."ecole_employe as e ON e.fk_user = u.rowid";
	}
	$sql .= " WHERE u.entity IN (".getEntity('user').")";
	$sql .= " AND ((u.statut = 1 AND (".implode(' OR ', $conds)."))".((int) $keep > 0 ? " OR u.rowid = ".((int) $keep) : "").")";
	$sql .= " ORDER BY u.lastname, u.firstname";
	$out = array();
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$name = ecole_user_nom($o);
		$out[(int) $o->rowid] = $name !== '' ? $name : $o->login;
	}
	return $out;
}

/**
 * Vérifie qu'un utilisateur choisi appartient à une des catégories données (ajoute l'erreur sinon).
 *
 * @param  DoliDB   $db     Handler base
 * @param  int      $id     Id utilisateur (0 = aucun)
 * @param  string[] $cats   Catégories acceptées
 * @param  array    $errors Liste d'erreurs à compléter
 * @return void
 */
function ecole_check_user_categorie($db, $id, $cats, &$errors)
{
	global $langs;
	if ((int) $id <= 0) {
		return;
	}
	$ok = ecole_users_categories($db, $cats);
	if (!isset($ok[(int) $id])) {
		$all = ecole_categories_utilisateur();
		$labels = array();
		foreach ($cats as $c) {
			$labels[] = $langs->transnoentities($all[$c]);
		}
		$errors[] = $langs->trans('ErrorEcoleUserCategorie', ecole_user_label($db, $id), implode(' / ', $labels));
	}
}

/**
 * Message et lien affichés quand aucun utilisateur n'a la catégorie voulue.
 *
 * @param  string[] $cats Catégories
 * @return string
 */
function ecole_users_categories_aide($cats)
{
	global $langs;
	$all = ecole_categories_utilisateur();
	$labels = array();
	foreach ($cats as $c) {
		$labels[] = $langs->trans($all[$c]);
	}
	return '<span class="opacitymedium">'.$langs->trans('AucunUtilisateurCategorie', implode(' / ', $labels))
		.' <a href="'.DOL_URL_ROOT.'/user/list.php">'.$langs->trans('Users').'</a></span>';
}

/**
 * Jours ouvrables d'une période (au plus 62 jours) : 'AAAA-MM-JJ' => horodatage.
 *
 * @param  string $debut Date de début 'AAAA-MM-JJ'
 * @param  string $fin   Date de fin 'AAAA-MM-JJ'
 * @return array<string,int>
 */
function ecole_jours_periode($debut, $fin)
{
	$out = array();
	if (!$debut || !$fin || $fin < $debut) {
		return $out;
	}
	$ouvrables = ecole_jours_ouvrables();
	$t = strtotime(substr($debut, 0, 10).' 12:00:00');
	$tend = strtotime(substr($fin, 0, 10).' 12:00:00');
	for ($n = 0; $t <= $tend && $n < 62; $n++, $t += 86400) {
		if (in_array((int) date('N', $t), $ouvrables)) {
			$out[date('Y-m-d', $t)] = $t;
		}
	}
	return $out;
}

/**
 * Lien vers un objet du module affiché par son libellé (avec cache).
 *
 * @param  DoliDB $db        Handler base
 * @param  string $classname Classe (ex. EcoleNiveau)
 * @param  string $classpath Chemin relatif du fichier de la classe
 * @param  int    $id        Id de l'objet
 * @return string            HTML
 */
function ecole_link_label($db, $classname, $classpath, $id)
{
	static $cache = array();
	if ((int) $id <= 0) {
		return '';
	}
	$k = $classname.'_'.((int) $id);
	if (!isset($cache[$k])) {
		dol_include_once('/'.$classpath);
		$cache[$k] = '';
		if (class_exists($classname)) {
			$o = new $classname($db);
			if ($o->fetch((int) $id) > 0) {
				$cache[$k] = $o->getNomUrl(0, 'label');
			}
		}
	}
	return $cache[$k];
}

/**
 * Minutes de cours par semaine selon l'emploi du temps (filtre sur une classe, une salle...).
 *
 * @param  DoliDB $db    Handler base
 * @param  string $where Condition SQL déjà sécurisée sur la table e (ex. « e.fk_salle = 3 »)
 * @return int
 */
function ecole_minutes_semaine($db, $where)
{
	$total = 0;
	$sql = "SELECT c.heure_debut, c.heure_fin FROM ".$db->prefix()."ecole_edt_cours e INNER JOIN ".$db->prefix()."ecole_creneau c ON c.rowid = e.fk_creneau WHERE ".$where;
	$resql = $db->query($sql);
	if ($resql) {
		while ($o = $db->fetch_object($resql)) {
			$total += max(0, ecole_hhmm_to_min($o->heure_fin) - ecole_hhmm_to_min($o->heure_debut));
		}
	}
	return $total;
}

/**
 * Durée en minutes au format « 12h30 ».
 *
 * @param  int $minutes Minutes
 * @return string
 */
function ecole_duree($minutes)
{
	return intdiv((int) $minutes, 60).'h'.sprintf('%02d', ((int) $minutes) % 60);
}

/**
 * Colonne « Emploi du temps » de la liste des classes : heures par semaine + lien.
 *
 * @param  EcoleClasse $rec Classe
 * @return string
 */
function classe_col_edt($rec)
{
	global $db;
	$min = ecole_minutes_semaine($db, 'e.fk_classe = '.((int) $rec->id));
	return '<a href="'.dol_buildpath('/classes/classe/edt.php', 1).'?id='.((int) $rec->id).'">'.img_picto('', 'fa-calendar-alt', 'class="pictofixedwidth"').($min > 0 ? ecole_duree($min) : '—').'</a>';
}

/**
 * Colonne « Nombre max » de la liste des classes : élèves inscrits / effectif maximum
 * (100 par défaut), avec un lien vers la liste des élèves de la classe.
 *
 * @param  EcoleClasse $rec Classe
 * @return string
 */
function classe_col_effectif($rec)
{
	global $db, $user, $langs;
	static $inscrits = null;

	$max = ((int) $rec->effectif_max > 0) ? (int) $rec->effectif_max : EcoleClasse::effectifMaxDefaut();
	if (!isModEnabled('eleves')) {
		return '<span class="badge badge-status0">'.$max.'</span>';
	}
	dol_include_once('/eleves/class/ecole_eleve.class.php');
	$statuts = implode(',', EcoleEleve::statusOccupantPlace()); // inscrit, suspendu : les élèves qui occupent une place
	if ($inscrits === null) {
		$inscrits = array();
		$resql = $db->query("SELECT fk_classe, COUNT(*) as nb FROM ".$db->prefix()."ecole_eleve WHERE status IN (".$statuts.") GROUP BY fk_classe");
		while ($resql && ($o = $db->fetch_object($resql))) {
			$inscrits[(int) $o->fk_classe] = (int) $o->nb;
		}
	}
	$nb = isset($inscrits[(int) $rec->id]) ? $inscrits[(int) $rec->id] : 0;
	$txt = '<span class="badge '.(($nb >= $max) ? 'badge-danger' : 'badge-status4').'">'.$nb.' / '.$max.'</span>';
	if (!$user->hasRight('eleves', 'eleve', 'lire')) {
		return $txt;
	}
	$url = dol_buildpath('/eleves/eleve/list.php', 1).'?search_fk_classe='.((int) $rec->id).'&search_status='.$statuts;
	return '<a href="'.$url.'" title="'.dol_escape_htmltag($langs->trans('VoirElevesClasse')).'">'.$txt.'</a>';
}

/**
 * Colonne « Classe attitrée » de la liste des salles.
 *
 * @param  EcoleSalle $rec Salle
 * @return string
 */
function salle_col_attitree($rec)
{
	$out = array();
	foreach ($rec->getClassesAttitrees() as $cid => $c) {
		$out[] = '<a href="'.dol_buildpath('/classes/classe/card.php', 1).'?id='.$cid.'" title="'.dol_escape_htmltag(ecole_label($c)).'">'.dol_escape_htmltag($c->ref).'</a>';
	}
	return implode(', ', $out);
}

/**
 * Colonne « Occupation » de la liste des salles : heures de cours par semaine + lien.
 *
 * @param  EcoleSalle $rec Salle
 * @return string
 */
function salle_col_heures($rec)
{
	global $db;
	$min = ecole_minutes_semaine($db, 'e.fk_salle = '.((int) $rec->id));
	return '<a href="'.dol_buildpath('/classes/salle/occupation.php', 1).'?id='.((int) $rec->id).'">'.img_picto('', 'fa-calendar-alt', 'class="pictofixedwidth"').($min > 0 ? ecole_duree($min) : '—').'</a>';
}

/**
 * Onglets de la fiche d'une salle.
 *
 * @param  EcoleSalle $object Salle
 * @return array
 */
function salle_prepare_head($object)
{
	global $langs;
	$langs->load('classes@classes');

	$head = array();
	$head[0][0] = dol_buildpath('/classes/salle/card.php', 1).'?id='.((int) $object->id);
	$head[0][1] = $langs->trans('Fiche');
	$head[0][2] = 'card';
	$head[1][0] = dol_buildpath('/classes/salle/occupation.php', 1).'?id='.((int) $object->id);
	$head[1][1] = $langs->trans('OccupationSalle');
	$head[1][2] = 'occupation';
	return $head;
}

/**
 * Informations sous la fiche d'une salle : classe(s) attitrée(s) et occupation hebdomadaire.
 *
 * @param  EcoleSalle $object Salle
 * @return void
 */
function salle_extra_view($object)
{
	global $db, $langs;

	print '<div class="fichecenter"><br>';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('ClasseAttitree').'</td><td>'.$langs->trans('OccupationSalle').'</td></tr>';
	print '<tr class="oddeven"><td>';
	$classes = $object->getClassesAttitrees();
	if (empty($classes)) {
		print '<span class="opacitymedium">'.$langs->trans('AucuneClasseAttitree').'</span>';
	}
	foreach ($classes as $cid => $c) {
		print '<a href="'.dol_buildpath('/classes/classe/card.php', 1).'?id='.$cid.'">'.img_picto('', 'fa-chalkboard', 'class="pictofixedwidth"').dol_escape_htmltag($c->ref.' - '.ecole_label($c)).'</a><br>';
	}
	print '</td><td>';
	print $langs->trans('HeuresParSemaine', ecole_duree(ecole_minutes_semaine($db, 'e.fk_salle = '.((int) $object->id))));
	print ' &nbsp; <a href="'.dol_buildpath('/classes/salle/occupation.php', 1).'?id='.((int) $object->id).'">'.$langs->trans('VoirOccupation').'</a>';
	print '</td></tr>';
	print '</table>';
	print '</div><br>';
}

/**
 * Informations sous la fiche d'une matière, calculées depuis l'emploi du temps des cours :
 * classes où elle est attribuée (nombre de cours et heures par semaine, enseignants) et enseignants qui l'enseignent.
 *
 * @param  EcoleMatiere $object Matière
 * @return void
 */
function matiere_extra_view($object)
{
	global $db, $langs;

	$p = $db->prefix();
	$id = (int) $object->id;

	// Classes où la matière est attribuée (coefficients), même sans cours à l'emploi du temps
	$classes = array();
	$sql = "SELECT c.rowid, c.ref, c.label_fr, c.label_ar FROM ".$p."ecole_classe_matiere cm INNER JOIN ".$p."ecole_classe c ON c.rowid = cm.fk_classe";
	$sql .= " LEFT JOIN ".$p."ecole_niveau n ON n.rowid = c.fk_niveau WHERE cm.fk_matiere = ".$id." ORDER BY n.position, c.rowid";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$classes[(int) $o->rowid] = (object) array('ref' => $o->ref, 'label_fr' => $o->label_fr, 'label_ar' => $o->label_ar, 'nb' => 0, 'minutes' => 0, 'profs' => array());
	}

	// Cours de l'emploi du temps
	$profs = array();
	$sql = "SELECT e.fk_classe, e.fk_user, cr.heure_debut, cr.heure_fin, c.ref, c.label_fr, c.label_ar FROM ".$p."ecole_edt_cours e";
	$sql .= " INNER JOIN ".$p."ecole_creneau cr ON cr.rowid = e.fk_creneau INNER JOIN ".$p."ecole_classe c ON c.rowid = e.fk_classe";
	$sql .= " WHERE e.fk_matiere = ".$id;
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$cid = (int) $o->fk_classe;
		if (!isset($classes[$cid])) {
			$classes[$cid] = (object) array('ref' => $o->ref, 'label_fr' => $o->label_fr, 'label_ar' => $o->label_ar, 'nb' => 0, 'minutes' => 0, 'profs' => array());
		}
		$min = max(0, ecole_hhmm_to_min($o->heure_fin) - ecole_hhmm_to_min($o->heure_debut));
		$classes[$cid]->nb++;
		$classes[$cid]->minutes += $min;
		$uid = (int) $o->fk_user;
		if ($uid > 0) {
			$classes[$cid]->profs[$uid] = 1;
			if (!isset($profs[$uid])) {
				$profs[$uid] = array('classes' => array(), 'nb' => 0, 'minutes' => 0);
			}
			$profs[$uid]['classes'][$cid] = $o->ref;
			$profs[$uid]['nb']++;
			$profs[$uid]['minutes'] += $min;
		}
	}

	print '<div class="fichecenter"><br>';

	// Classes
	print load_fiche_titre($langs->trans('MatiereClasses').' ('.count($classes).')', '', 'fa-chalkboard');
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Classe').'</td><td class="center">'.$langs->trans('NbCoursSemaine').'</td><td class="center">'.$langs->trans('HeuresSemaine').'</td><td>'.$langs->trans('Enseignants').'</td><td></td></tr>';
	if (empty($classes)) {
		print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">'.$langs->trans('MatiereAucuneClasse').'</span></td></tr>';
	}
	$totnb = 0;
	$totmin = 0;
	foreach ($classes as $cid => $c) {
		$totnb += $c->nb;
		$totmin += $c->minutes;
		$noms = array();
		foreach (array_keys($c->profs) as $uid) {
			$noms[] = dol_escape_htmltag(ecole_user_label($db, $uid));
		}
		print '<tr class="oddeven"><td class="nowraponall"><a href="'.dol_buildpath('/classes/classe/card.php', 1).'?id='.$cid.'">'.img_picto('', 'fa-chalkboard', 'class="pictofixedwidth"').dol_escape_htmltag($c->ref).'</a> <span class="opacitymedium" dir="auto">'.dol_escape_htmltag(ecole_label($c)).'</span></td>';
		print '<td class="center">'.($c->nb ? '<b>'.$c->nb.'</b>' : '<span class="opacitymedium">0</span>').'</td>';
		print '<td class="center">'.($c->minutes ? ecole_duree($c->minutes) : '<span class="opacitymedium">—</span>').'</td>';
		print '<td>'.(empty($noms) ? '<span class="opacitymedium">—</span>' : implode(', ', $noms)).'</td>';
		print '<td class="right"><a href="'.dol_buildpath('/classes/classe/edt.php', 1).'?id='.$cid.'">'.$langs->trans('EcoleEmploiDuTemps').'</a></td></tr>';
	}
	if (count($classes) > 1) {
		print '<tr class="liste_total"><td>'.$langs->trans('Total').'</td><td class="center">'.$totnb.'</td><td class="center">'.ecole_duree($totmin).'</td><td colspan="2"></td></tr>';
	}
	print '</table></div>';

	// Enseignants
	print '<br>'.load_fiche_titre($langs->trans('MatiereEnseignants').' ('.count($profs).')', '', 'fa-chalkboard-teacher');
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Enseignant').'</td><td>'.$langs->trans('Classes').'</td><td class="center">'.$langs->trans('NbCoursSemaine').'</td><td class="center">'.$langs->trans('HeuresSemaine').'</td></tr>';
	if (empty($profs)) {
		print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans('MatiereAucunEnseignant').'</span></td></tr>';
	}
	foreach ($profs as $uid => $x) {
		print '<tr class="oddeven"><td class="nowraponall">'.img_picto('', 'fa-chalkboard-teacher', 'class="pictofixedwidth"').dol_escape_htmltag(ecole_user_label($db, $uid)).'</td>';
		print '<td>'.dol_escape_htmltag(implode(', ', $x['classes'])).'</td>';
		print '<td class="center"><b>'.$x['nb'].'</b></td><td class="center">'.ecole_duree($x['minutes']).'</td></tr>';
	}
	print '</table></div>';
	print '<div class="opacitymedium small">'.$langs->trans('MatiereSourceEdt').'</div>';
	print '</div><br>';
}

/**
 * Colonne « Aperçu » de la liste des modèles de PDF : liens FR / AR.
 *
 * @param  EcolePdfModele $rec Modèle
 * @return string
 */
function pdf_modele_col_apercu($rec)
{
	$url = dol_buildpath('/classes/pdf_modele/apercu.php', 1).'?id='.((int) $rec->id);
	return '<a href="'.$url.'&lang=fr" target="_blank" rel="noopener">'.img_picto('', 'fa-file-pdf', 'class="pictofixedwidth"').'FR</a> &nbsp; <a href="'.$url.'&lang=ar" target="_blank" rel="noopener">'.img_picto('', 'fa-file-pdf', 'class="pictofixedwidth"').'AR</a>';
}

/**
 * Sous la fiche d'un modèle de PDF : aperçu en français et en arabe, duplication.
 *
 * @param  EcolePdfModele $object Modèle
 * @return void
 */
function pdf_modele_extra_view($object)
{
	global $langs, $user;
	$url = dol_buildpath('/classes/pdf_modele/apercu.php', 1).'?id='.((int) $object->id);
	print '<div class="tabsAction">';
	print dolGetButtonAction('', $langs->trans('ApercuFr'), 'default', $url.'&lang=fr', '', 1, array('attr' => array('target' => '_blank')));
	print dolGetButtonAction('', $langs->trans('ApercuAr'), 'default', $url.'&lang=ar', '', 1, array('attr' => array('target' => '_blank')));
	print dolGetButtonAction('', $langs->trans('Dupliquer'), 'default', dol_buildpath('/classes/pdf_modele/card.php', 1).'?id='.((int) $object->id).'&action=dupliquer&token='.newToken(), '', $user->hasRight('classes', 'config'));
	print '</div>';
	print '<span class="opacitymedium small">'.$langs->trans('AideModelesPdf').'</span>';
}

/**
 * Nom complet d'un utilisateur Dolibarr.
 *
 * @param  DoliDB $db Handler base
 * @param  int    $id Id utilisateur
 * @return string
 */
function ecole_user_label($db, $id)
{
	global $langs;
	static $cache = array();
	if ((int) $id <= 0) {
		return '';
	}
	$k = (int) $id.'-'.(is_object($langs) ? $langs->defaultlang : ''); // le nom dépend de la langue
	if (!isset($cache[$k])) {
		$cache[$k] = '';
		$emp = ecole_table_exists($db, 'ecole_employe');
		$sql = "SELECT u.firstname, u.lastname, u.login".($emp ? ", e.nom_fr, e.nom_ar" : "")." FROM ".$db->prefix()."user as u";
		if ($emp) {
			$sql .= " LEFT JOIN ".$db->prefix()."ecole_employe as e ON e.fk_user = u.rowid";
		}
		$sql .= " WHERE u.rowid = ".((int) $id);
		$resql = $db->query($sql);
		if ($resql && ($o = $db->fetch_object($resql))) {
			$name = ecole_user_nom($o);
			$cache[$k] = $name !== '' ? $name : (string) $o->login;
		}
	}
	return $cache[$k];
}

/**
 * Nom d'un utilisateur dans la langue de l'interface : nom de sa fiche employé (arabe ou français, repli
 * sur l'autre langue), sinon prénom et nom du compte Dolibarr.
 *
 * @param  object $o Ligne (firstname, lastname et, si fiche employé, nom_fr, nom_ar)
 * @return string
 */
function ecole_user_nom($o)
{
	if ((isset($o->nom_fr) && trim((string) $o->nom_fr) !== '') || (isset($o->nom_ar) && trim((string) $o->nom_ar) !== '')) {
		return ecole_label((object) array('nom_fr' => (string) $o->nom_fr, 'nom_ar' => (string) $o->nom_ar));
	}
	return trim($o->firstname.' '.$o->lastname);
}

/**
 * La table existe-t-elle ? (un module peut ne pas être installé : ses données n'empêchent alors rien.)
 *
 * @param  DoliDB $db    Handler base
 * @param  string $table Table sans préfixe
 * @return bool
 */
function ecole_table_exists($db, $table)
{
	static $cache = array();
	$key = $db->prefix().$table;
	if (!isset($cache[$key])) {
		$resql = $db->query("SHOW TABLES LIKE '".$db->escape(str_replace(array('_', '%'), array('\\_', '\\%'), $key))."'");
		$cache[$key] = ($resql && $db->num_rows($resql) > 0);
	}
	return $cache[$key];
}

/**
 * Bouton d'action « Supprimer » (ou « Annuler ») à la façon native de Dolibarr : si l'action est impossible,
 * le bouton est grisé, non cliquable, et les causes s'affichent en infobulle.
 *
 * @param  string   $label   Libellé du bouton
 * @param  string   $url     Adresse de l'action (avec token)
 * @param  bool|int $droit   L'utilisateur a-t-il le droit ? (sinon infobulle « droits insuffisants »)
 * @param  string[] $raisons Causes qui empêchent l'action (vide = possible)
 * @return string            HTML
 */
function ecole_bouton_supprimer($label, $url, $droit = true, $raisons = array())
{
	if (!$droit) {
		return dolGetButtonAction('', $label, 'delete', $url, '', 0);
	}
	if (!empty($raisons)) {
		return dolGetButtonAction(implode('<br>', $raisons), $label, 'delete', $url, '', -1);
	}
	return dolGetButtonAction('', $label, 'delete', $url, '', 1);
}

/**
 * Icône « corbeille » d'une ligne de tableau : lien si la suppression est possible, sinon icône grisée avec les causes en infobulle.
 *
 * @param  string   $url     Adresse de l'action (avec token)
 * @param  string[] $raisons Causes qui empêchent la suppression (vide = possible)
 * @param  string   $titre   Infobulle quand c'est possible
 * @return string            HTML
 */
function ecole_icone_supprimer($url, $raisons = array(), $titre = '')
{
	global $langs;
	if (!empty($raisons)) {
		return '<span class="opacitymedium" style="cursor:not-allowed">'.img_picto(implode(' - ', $raisons), 'delete', 'class="pictodelete classfortooltip"').'</span>';
	}
	return '<a href="'.$url.'" title="'.dol_escape_htmltag($titre !== '' ? $titre : $langs->trans('Delete')).'">'.img_delete().'</a>';
}

/**
 * Libellé d'une ligne d'une table du module (« REF - Libellé »).
 *
 * @param  DoliDB $db    Handler base
 * @param  string $table Table sans préfixe
 * @param  int    $id    Id
 * @return string
 */
function ecole_row_label($db, $table, $id)
{
	if ((int) $id <= 0) {
		return '';
	}
	$resql = $db->query("SELECT ref, label_fr, label_ar FROM ".$db->prefix().$db->sanitize($table)." WHERE rowid = ".((int) $id));
	if ($resql && ($o = $db->fetch_object($resql))) {
		$l = ecole_label($o);
		return $o->ref.($l !== '' ? ' - '.$l : '');
	}
	return '';
}

/**
 * Conserve l'ancienne version d'une ligne d'emploi du temps (historique).
 *
 * @param  DoliDB $db        Handler base
 * @param  User   $user      Utilisateur
 * @param  string $type      'cours' ou 'examen'
 * @param  string $action    'update' ou 'delete'
 * @param  int    $fk_classe Classe concernée
 * @param  array  $data      Ancienne version (identifiants + libellés)
 * @return int               1 si OK, -1 sinon
 */
function ecole_archive_edt($db, $user, $type, $action, $fk_classe, $data)
{
	global $conf;

	$sql = "INSERT INTO ".$db->prefix()."ecole_edt_archive (entity, type, action, fk_classe, donnees, fk_user, date_archive)";
	$sql .= " VALUES (".((int) $conf->entity).", '".$db->escape($type)."', '".$db->escape($action)."', ".((int) $fk_classe);
	$sql .= ", '".$db->escape(json_encode($data, JSON_UNESCAPED_UNICODE))."', ".((int) $user->id).", '".$db->idate(dol_now())."')";
	return $db->query($sql) ? 1 : -1;
}

/**
 * Libellé d'une ligne dans la langue de l'utilisateur (arabe si l'interface est en arabe).
 *
 * @param  object $o Objet ou ligne SQL avec label_fr / label_ar (ou nom_fr / nom_ar pour une personne)
 * @return string
 */
function ecole_label($o)
{
	global $langs;
	$fr = isset($o->label_fr) ? 'label_fr' : (isset($o->nom_fr) ? 'nom_fr' : 'label_fr');
	$ar = ($fr === 'nom_fr') ? 'nom_ar' : 'label_ar';
	$vfr = isset($o->$fr) ? trim((string) $o->$fr) : '';
	$var = isset($o->$ar) ? trim((string) $o->$ar) : '';
	if (strpos((string) $langs->defaultlang, 'ar') === 0) {
		return $var !== '' ? $var : $vfr; // arabe, repli sur le français
	}
	return $vfr !== '' ? $vfr : $var; // français, repli sur l'arabe
}

/**
 * Heure HH:MM en minutes depuis minuit.
 *
 * @param  string $s Heure
 * @return int
 */
function ecole_hhmm_to_min($s)
{
	$p = explode(':', (string) $s);
	return ((int) $p[0]) * 60 + (isset($p[1]) ? (int) $p[1] : 0);
}

/**
 * Affiche un emploi du temps « visuel » : colonnes (jours ou dates), axe horaire à gauche,
 * cours dessinés en blocs colorés dont la hauteur suit la durée.
 * Utilisé par l'emploi du temps des cours et par celui des examens.
 * Nécessite la feuille /classes/css/timetable.css (à passer à llxHeader).
 *
 * @param array $columns clé => array('label' => texte, 'sublabel' => texte, 'addurl' => url facultative)
 * @param array $events  liste de array('col', 'start' HH:MM, 'end' HH:MM, 'title', 'lines' => texte[],
 *                       'color' => int, 'editurl' => url, 'deleteurl' => url)
 * @param array $opts    'bands' => liste de array('start', 'end', 'label') : créneaux fixes (fond + axe) ;
 *                       'bandaddurl' => callable(clé colonne, index créneau) : url d'ajout dans une case vide ;
 *                       'addlabel' => texte de l'info-bulle d'ajout ; 'scale' => pixels par minute
 * @return void
 */
function ecole_timetable_render($columns, $events, $opts = array())
{
	$scale = isset($opts['scale']) ? (float) $opts['scale'] : 1.25;
	$bands = isset($opts['bands']) ? $opts['bands'] : array();
	$addlabel = isset($opts['addlabel']) ? $opts['addlabel'] : '+';

	// Amplitude horaire affichée
	$min = null;
	$max = null;
	foreach (array_merge($bands, $events) as $x) {
		$s = ecole_hhmm_to_min($x['start']);
		$e = ecole_hhmm_to_min($x['end']);
		$min = ($min === null) ? $s : min($min, $s);
		$max = ($max === null) ? $e : max($max, $e);
	}
	if ($min === null) {
		$min = 8 * 60;
		$max = 12 * 60;
	}
	if (empty($bands)) {
		$min = (int) floor($min / 60) * 60;
		$max = (int) ceil($max / 60) * 60;
	}
	$px = function ($minutes) use ($scale) {
		return (int) round($minutes * $scale);
	};

	// Position verticale d'une heure. Avec des créneaux, les pauses entre créneaux sont
	// raccourcies à l'affichage (au plus 22 px) pour qu'une longue pause ne prenne pas de place.
	$sorted = $bands;
	usort($sorted, function ($a, $b) {
		return strcmp($a['start'], $b['start']);
	});
	$ypos = function ($t) use ($sorted, $min, $px) {
		if (empty($sorted)) {
			return $px($t - $min);
		}
		$y = 0;
		$prevEnd = null;
		foreach ($sorted as $b) {
			$bs = ecole_hhmm_to_min($b['start']);
			$be = ecole_hhmm_to_min($b['end']);
			if ($prevEnd !== null && $bs > $prevEnd) {
				$gap = min($px($bs - $prevEnd), 22);
				if ($t < $bs) {
					return $y + (int) round($gap * ($t - $prevEnd) / ($bs - $prevEnd));
				}
				$y += $gap;
			}
			if ($t <= $be) {
				return $y + $px(max(0, $t - $bs));
			}
			$y += $px($be - $bs);
			$prevEnd = $be;
		}
		return $y;
	};
	$height = $ypos($max);

	$bycol = array();
	foreach ($events as $ev) {
		$bycol[$ev['col']][] = $ev;
	}

	print '<div class="ecole-tt-wrap">';
	print '<div class="ecole-tt" style="--tt-cols:'.count($columns).';--tt-h:'.$height.'px;--tt-hour:'.$px(60).'px">';

	// En-têtes
	print '<div class="ecole-tt-corner"></div>';
	foreach ($columns as $col) {
		print '<div class="ecole-tt-head">'.dol_escape_htmltag($col['label']);
		if (!empty($col['sublabel'])) {
			print '<div class="ecole-tt-sub">'.dol_escape_htmltag($col['sublabel']).'</div>';
		}
		if (!empty($col['addurl'])) {
			print ' <a class="ecole-tt-headadd" href="'.$col['addurl'].'" title="'.dol_escape_htmltag($addlabel).'">'.img_picto('', 'add').'</a>';
		}
		print '</div>';
	}

	// Axe horaire
	print '<div class="ecole-tt-axis'.(empty($bands) ? ' ecole-tt-hours' : '').'">';
	if (!empty($bands)) {
		foreach ($bands as $b) {
			$top = $ypos(ecole_hhmm_to_min($b['start']));
			$h = $ypos(ecole_hhmm_to_min($b['end'])) - $top;
			print '<div class="ecole-tt-axislabel" style="top:'.$top.'px;height:'.$h.'px">';
			print '<b>'.dol_escape_htmltag($b['start']).'</b><span>'.dol_escape_htmltag($b['end']).'</span>';
			if (!empty($b['label'])) {
				print '<em>'.dol_escape_htmltag($b['label']).'</em>';
			}
			print '</div>';
		}
	} else {
		for ($t = $min; $t < $max; $t += 60) {
			print '<div class="ecole-tt-axislabel" style="top:'.$px($t - $min).'px;height:'.$px(60).'px"><b>'.sprintf('%02d:00', $t / 60).'</b></div>';
		}
	}
	print '</div>';

	// Colonnes
	foreach ($columns as $key => $col) {
		print '<div class="ecole-tt-col'.(empty($bands) ? ' ecole-tt-hours' : '').'">';

		// Créneaux (fond) : case vide cliquable pour ajouter
		foreach ($bands as $i => $b) {
			$bs = ecole_hhmm_to_min($b['start']);
			$be = ecole_hhmm_to_min($b['end']);
			$busy = false;
			foreach (isset($bycol[$key]) ? $bycol[$key] : array() as $ev) {
				if (ecole_hhmm_to_min($ev['start']) < $be && ecole_hhmm_to_min($ev['end']) > $bs) {
					$busy = true;
					break;
				}
			}
			$style = 'top:'.$ypos($bs).'px;height:'.($ypos($be) - $ypos($bs)).'px';
			$url = (!$busy && !empty($opts['bandaddurl'])) ? call_user_func($opts['bandaddurl'], $key, $i) : '';
			if ($url) {
				print '<a class="ecole-tt-band ecole-tt-add" style="'.$style.'" href="'.$url.'" title="'.dol_escape_htmltag($addlabel).'">+</a>';
			} elseif (!$busy && !empty($opts['freelabel'])) {
				print '<div class="ecole-tt-band ecole-tt-free" style="'.$style.'">'.dol_escape_htmltag($opts['freelabel']).'</div>';
			} else {
				print '<div class="ecole-tt-band" style="'.$style.'"></div>';
			}
		}

		// Blocs
		foreach (isset($bycol[$key]) ? $bycol[$key] : array() as $ev) {
			$s = ecole_hhmm_to_min($ev['start']);
			$e = ecole_hhmm_to_min($ev['end']);
			$tip = $ev['title'].' - '.$ev['start'].'-'.$ev['end'];
			foreach ($ev['lines'] as $l) {
				$tip .= ' - '.$l;
			}
			print '<div class="ecole-tt-ev ecole-tt-c'.(((int) $ev['color']) % 10).'" style="top:'.($ypos($s) + 1).'px;height:'.max(18, $ypos($e) - $ypos($s) - 2).'px" title="'.dol_escape_htmltag($tip).'">';
			if (!empty($ev['editurl'])) {
				print '<a class="ecole-tt-edit" href="'.$ev['editurl'].'"></a>';
			}
			if (!empty($ev['deleteurl'])) {
				print '<a class="ecole-tt-del" href="'.$ev['deleteurl'].'">'.img_delete().'</a>';
			}
			print '<div class="ecole-tt-title">'.dol_escape_htmltag($ev['title']).'</div>';
			print '<div class="ecole-tt-line ecole-tt-time"><span dir="ltr">'.dol_escape_htmltag($ev['start'].' - '.$ev['end']).'</span></div>'; // dir="ltr" : sinon l'heure de fin passe avant celle de début en arabe
			foreach ($ev['lines'] as $l) {
				if ($l !== '') {
					print '<div class="ecole-tt-line">'.dol_escape_htmltag($l).'</div>';
				}
			}
			print '</div>';
		}

		print '</div>';
	}

	print '</div>';
	print '</div>';
}

/**
 * Bouton « PDF » d'un emploi du temps (ouvre le PDF dans un nouvel onglet, dans la langue de l'utilisateur).
 *
 * @param  string $url Adresse du PDF
 * @return string      HTML
 */
function ecole_pdf_button($url)
{
	global $langs;
	return '<div class="right" style="margin:4px 0">'
		.'<a class="butAction" href="'.dol_escape_htmltag($url).'" target="_blank" rel="noopener">'
		.img_picto('', 'fa-file-pdf', 'class="pictofixedwidth"').dol_escape_htmltag($langs->trans('ExportPdf')).'</a></div>';
}

/**
 * Deux plages horaires HH:MM se chevauchent-elles ?
 *
 * @param  string $d1 Début 1
 * @param  string $f1 Fin 1
 * @param  string $d2 Début 2
 * @param  string $f2 Fin 2
 * @return bool
 */
function ecole_plages_chevauchent($d1, $f1, $d2, $f2)
{
	return $d1 < $f2 && $f1 > $d2;
}
