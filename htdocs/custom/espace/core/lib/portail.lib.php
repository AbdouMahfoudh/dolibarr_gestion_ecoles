<?php
/**
 * Fonctions des pages de l'espace parents et élèves (hors interface de gestion) :
 * session de l'espace, langue, en-tête et pied de page adaptés au téléphone.
 *
 * La session de l'espace est séparée de celle de l'interface de gestion ($_SESSION['ecole_espace'] pour les parents
 * et élèves, $_SESSION['ecole_espace_personnel'] pour le personnel : les deux zones ne partagent jamais une connexion) :
 * un compte de l'espace n'ouvre jamais de session Dolibarr classique.
 *
 * Fichier : custom/espace/core/lib/portail.lib.php
 */

/**
 * Clé de session de la zone en cours (parents et élèves, ou personnel).
 *
 * @return string
 */
function espace_session_cle()
{
	return espace_zone() === ESPACE_ZONE_PERSONNEL ? 'ecole_espace_personnel' : 'ecole_espace';
}

/**
 * Ouvre la session de l'espace pour un accès (dans la zone de cet accès) et compte la connexion.
 *
 * @param  DoliDB $db    Handler base
 * @param  object $acces Accès
 * @return void
 */
function espace_session_ouvrir($db, $acces)
{
	if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
		session_regenerate_id(true);
	}
	$cle = espace_zone_du_type($acces->type) === ESPACE_ZONE_PERSONNEL ? 'ecole_espace_personnel' : 'ecole_espace';
	$_SESSION[$cle] = array('acces' => (int) $acces->rowid, 'uid' => (int) $acces->fk_user, 'debut' => dol_now());
	$db->query("UPDATE ".$db->prefix()."ecole_acces SET date_derniere_connexion = '".$db->idate(dol_now())."', nb_connexions = nb_connexions + 1 WHERE rowid = ".((int) $acces->rowid));
	$db->query("UPDATE ".$db->prefix()."user SET datepreviouslogin = datelastlogin, datelastlogin = '".$db->idate(dol_now())."' WHERE rowid = ".((int) $acces->fk_user));
}

/**
 * Ferme la session de l'espace.
 *
 * @return void
 */
function espace_session_fermer()
{
	unset($_SESSION[espace_session_cle()]);
}

/**
 * Accès de la session en cours, s'il est toujours valable (compte actif, au moins un élève inscrit).
 * Sinon la session est fermée et null est renvoyé ($raison dit pourquoi : '', 'coupe', 'desactive').
 *
 * @param  DoliDB $db     Handler base
 * @param  string $raison Raison du refus (sortie)
 * @return object|null
 */
function espace_session_acces($db, &$raison = '')
{
	$raison = '';
	$cle = espace_session_cle();
	if (empty($_SESSION[$cle]['acces'])) {
		return null;
	}
	$acces = espace_acces_fetch_id($db, (int) $_SESSION[$cle]['acces']);
	// un accès ne vaut que dans sa zone (un employé n'entre jamais dans l'espace des parents, ni l'inverse)
	$ok = $acces && (int) $acces->fk_user === (int) $_SESSION[$cle]['uid'] && espace_zone_du_type($acces->type) === espace_zone();
	if ($ok) {
		$resql = $db->query("SELECT statut FROM ".$db->prefix()."user WHERE rowid = ".((int) $acces->fk_user));
		$o = $resql ? $db->fetch_object($resql) : null;
		$ok = $o && (int) $o->statut === 1;
	}
	if ($ok) {
		$etat = espace_etat($db, $acces);
		if ($etat !== 'actif') {
			$raison = $etat;
			$ok = false;
		}
	} else {
		$raison = 'desactive';
	}
	if (!$ok) {
		espace_session_fermer();
		return null;
	}
	return $acces;
}

/**
 * Exige une session valable (sinon renvoi vers la page de connexion) et le changement du mot de passe provisoire.
 *
 * @param  DoliDB $db            Handler base
 * @param  bool   $pageMotDePasse true sur la page de changement du mot de passe
 * @return object                Accès
 */
function espace_exiger_session($db, $pageMotDePasse = false)
{
	$raison = '';
	$acces = espace_session_acces($db, $raison);
	if (!$acces) {
		header('Location: '.espace_page_url('connexion', $raison !== '' ? array('refus' => 1) : array()));
		exit;
	}
	if ((int) $acces->mdp_provisoire && !$pageMotDePasse) {
		header('Location: '.espace_page_url('mot-de-passe'));
		exit;
	}
	return $acces;
}

/**
 * Code de langue de l'espace : choix du bouton (?langue=ar|fr, gardé sur l'accès et dans la session),
 * sinon celui de l'accès, sinon celui de la configuration.
 *
 * @param  DoliDB      $db    Handler base
 * @param  object|null $acces Accès connecté
 * @return string             'ar_SA' ou 'fr_FR'
 */
function espace_langue_code($db, $acces = null)
{
	$codes = array('ar' => 'ar_SA', 'fr' => 'fr_FR');
	$choix = GETPOST('langue', 'aZ09');
	if (isset($codes[$choix])) {
		$_SESSION['ecole_espace_langue'] = $codes[$choix];
		if ($acces) {
			$db->query("UPDATE ".$db->prefix()."ecole_acces SET langue = '".$db->escape($codes[$choix])."' WHERE rowid = ".((int) $acces->rowid));
			$acces->langue = $codes[$choix];
		}
		return $codes[$choix];
	}
	if ($acces && in_array((string) $acces->langue, $codes, true)) {
		return $acces->langue;
	}
	if (!empty($_SESSION['ecole_espace_langue']) && in_array($_SESSION['ecole_espace_langue'], $codes, true)) {
		return $_SESSION['ecole_espace_langue'];
	}
	$def = getDolGlobalString('ESPACE_LANGUE', 'ar_SA');
	return in_array($def, $codes, true) ? $def : 'ar_SA';
}

/**
 * Charge les traductions de l'espace dans la langue choisie (remplace la langue globale).
 *
 * @param  string $code 'ar_SA' ou 'fr_FR'
 * @return Translate
 */
function espace_langs_init($code)
{
	global $conf;
	$l = new Translate('', $conf);
	$l->setDefaultLang($code);
	$files = array('main', 'other', 'espace@espace', 'eleves@eleves', 'classes@classes');
	if (isModEnabled('notes')) {
		$files[] = 'notes@notes';
	}
	if (isModEnabled('personnel')) {
		$files[] = 'personnel@personnel';
	}
	$l->loadLangs($files);
	$GLOBALS['langs'] = $l;
	return $l;
}

/**
 * La langue de l'espace est-elle l'arabe ?
 *
 * @return bool
 */
function espace_rtl()
{
	global $langs;
	return strpos((string) $langs->defaultlang, 'ar') === 0;
}

/**
 * Adresse d'une page de l'espace (adresse propre, voir espace_route_url et portail/router.php).
 *
 * @param  string $route  Route (ex. 'connexion', 'eleve/<code>/notes') ; '' = accueil
 * @param  array  $params Paramètres
 * @return string
 */
function espace_page_url($route, $params = array())
{
	return espace_route_url($route, $params);
}

/**
 * Adresse d'une page d'un élève dans l'espace : eleve/<code>[/<onglet>[/<période>]].
 *
 * @param  int    $id      Élève
 * @param  string $onglet  Onglet interne (notes, edt, absences, paiements, dossier) ; '' = premier onglet
 * @param  int    $periode Période des notes (0 = année, 1 à 3), null = aucune
 * @return string
 */
function espace_eleve_url($id, $onglet = '', $periode = null)
{
	$slugs = array('notes' => 'notes', 'edt' => 'emploi-du-temps', 'absences' => 'absences', 'paiements' => 'paiements', 'dossier' => 'dossier');
	$route = 'eleve/'.espace_jeton('eleve', $id);
	if ($onglet !== '' && isset($slugs[$onglet])) {
		$route .= '/'.$slugs[$onglet];
		if ($onglet === 'notes' && $periode !== null) {
			$route .= '/'.array(0 => 'annee', 1 => 't1', 2 => 't2', 3 => 't3')[(int) $periode];
		}
	}
	return espace_page_url($route);
}

/**
 * Adresse d'un document PDF d'un élève : document/<code>/certificat | bulletin/<période> | recu/<code du reçu>.
 *
 * @param  int    $id     Élève
 * @param  string $doc    certificat | bulletin | recu
 * @param  int    $valeur Période (bulletin) ou reçu (recu)
 * @return string
 */
function espace_document_url($id, $doc, $valeur = 0)
{
	$route = 'document/'.espace_jeton('eleve', $id).'/'.$doc;
	if ($doc === 'bulletin') {
		$route .= '/'.array(0 => 'annee', 1 => 't1', 2 => 't2', 3 => 't3')[(int) $valeur];
	} elseif ($doc === 'recu') {
		$route .= '/'.espace_jeton('recu', $valeur);
	}
	return espace_page_url($route);
}

/**
 * Lien de changement de langue vers la page courante.
 *
 * @return string HTML
 */
function espace_langue_switch()
{
	$autre = espace_rtl() ? 'fr' : 'ar';
	$params = $_GET;
	unset($params['langue'], $params['jeton'], $params['onglet'], $params['periode'], $params['doc'], $params['recu'], $params['fichier']);
	$params['langue'] = $autre;
	$url = strtok((string) $_SERVER['REQUEST_URI'], '?').'?'.http_build_query($params);
	return '<a class="es-lang" href="'.dol_escape_htmltag($url).'" hreflang="'.$autre.'">'.($autre === 'ar' ? 'العربية' : 'Français').'</a>';
}

/**
 * URL du logo de l'établissement ('' sans logo).
 *
 * @return string
 */
function espace_logo_url()
{
	global $mysoc, $conf;
	foreach (array('logos/thumbs/'.$mysoc->logo_small, 'logos/'.$mysoc->logo) as $rel) {
		if (basename($rel) !== '' && basename($rel) !== 'thumbs' && is_readable($conf->mycompany->dir_output.'/'.$rel)) {
			return espace_page_url('logo');
		}
	}
	return '';
}

/**
 * Début d'une page de l'espace : en-tête HTML, barre du haut (établissement, langue, déconnexion).
 *
 * @param  string      $title Titre de la page
 * @param  object|null $acces Accès connecté (null sur la page de connexion)
 * @param  string      $back  URL du bouton retour ('' = aucun)
 * @return void
 */
function espace_header($title, $acces = null, $back = '')
{
	global $langs, $mysoc;
	$rtl = espace_rtl();
	top_httphead();
	print '<!doctype html>'."\n";
	print '<html lang="'.($rtl ? 'ar' : 'fr').'" dir="'.($rtl ? 'rtl' : 'ltr').'"><head>';
	print '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
	print '<meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#1f5f8b">';
	print '<title>'.dol_escape_htmltag($title.' - '.$mysoc->name).'</title>';
	print '<link rel="stylesheet" href="'.dol_escape_htmltag(espace_page_url('assets/fa/css/all.min.css')).'">';
	print '<link rel="stylesheet" href="'.dol_escape_htmltag(espace_page_url('assets/emploi.css')).'">';
	print '<link rel="stylesheet" href="'.dol_escape_htmltag(espace_page_url('assets/portail.css', array('v' => 6))).'">';
	print '</head><body class="es'.($rtl ? ' es-rtl' : '').'">';

	print '<header class="es-top"><div class="es-top-in">';
	if ($back !== '') {
		print '<a class="es-back" href="'.dol_escape_htmltag($back).'" aria-label="'.dol_escape_htmltag($langs->trans('Retour')).'"><i class="fas fa-'.($rtl ? 'arrow-right' : 'arrow-left').'"></i></a>';
	}
	$logo = espace_logo_url();
	print '<a class="es-brand" href="'.dol_escape_htmltag(espace_page_url($acces ? '' : 'connexion')).'">';
	if ($logo !== '') {
		print '<img src="'.dol_escape_htmltag($logo).'" alt="">';
	}
	print '<span dir="auto">'.dol_escape_htmltag($mysoc->name).'</span></a>';
	print '<div class="es-top-actions">'.espace_langue_switch();
	if ($acces) {
		print '<a class="es-icon" href="'.dol_escape_htmltag(espace_page_url('mot-de-passe')).'" title="'.dol_escape_htmltag($langs->trans('ChangerMotDePasse')).'"><i class="fas fa-key"></i></a>';
		print '<a class="es-icon" href="'.dol_escape_htmltag(espace_page_url('deconnexion')).'" title="'.dol_escape_htmltag($langs->trans('SeDeconnecter')).'"><i class="fas fa-sign-out-alt"></i></a>';
	}
	print '</div></div></header>';
	print '<main class="es-main">';
}

/**
 * Fin d'une page de l'espace.
 *
 * @return void
 */
function espace_footer()
{
	global $langs, $mysoc;
	print '</main>';
	print '<footer class="es-foot">'.dol_escape_htmltag($mysoc->name).($mysoc->phone ? ' · <a href="tel:'.dol_escape_htmltag(preg_replace('/[^0-9+]/', '', $mysoc->phone)).'">'.dol_escape_htmltag($mysoc->phone).'</a>' : '').'</footer>';
	print '</body></html>';
}

/**
 * Message (information, erreur, succès) dans une page de l'espace.
 *
 * @param  string $text Texte (déjà échappé si besoin)
 * @param  string $type info | error | ok | warn
 * @return string       HTML
 */
function espace_msg($text, $type = 'info')
{
	$icons = array('info' => 'info-circle', 'error' => 'exclamation-triangle', 'ok' => 'check-circle', 'warn' => 'exclamation-circle');
	return '<div class="es-msg es-msg-'.$type.'"><i class="fas fa-'.(isset($icons[$type]) ? $icons[$type] : 'info-circle').'"></i><div>'.$text.'</div></div>';
}

/**
 * Montant avec la devise, dans le sens de lecture de la langue.
 *
 * @param  float $v Montant
 * @return string   HTML
 */
function espace_montant($v)
{
	global $conf;
	return '<span class="es-num" dir="ltr">'.price($v, 0, '', 1, -1, -1, $conf->currency).'</span>';
}

/**
 * URL de la photo d'un élève dans l'espace ('' sans photo).
 *
 * @param  EcoleEleve $e Élève
 * @return string
 */
function espace_photo_url($e)
{
	global $conf;
	if (empty($e->photo) || !is_file($conf->eleves->dir_output.'/'.$e->getPhotoSubdir().'/'.$e->photo)) {
		return '';
	}
	return espace_page_url('photo/'.espace_jeton('eleve', $e->id));
}

/**
 * Vignette ronde de l'élève : photo, sinon initiale.
 *
 * @param  EcoleEleve $e     Élève
 * @param  string     $class Classe CSS en plus
 * @return string            HTML
 */
function espace_avatar($e, $class = '')
{
	$url = espace_photo_url($e);
	if ($url !== '') {
		return '<span class="es-avatar '.$class.'"><img src="'.dol_escape_htmltag($url).'" alt=""></span>';
	}
	$nom = trim(ecole_label($e));
	$ini = function_exists('mb_substr') ? mb_substr($nom, 0, 1, 'UTF-8') : substr($nom, 0, 1);
	return '<span class="es-avatar es-avatar-ini '.$class.'">'.dol_escape_htmltag(dol_strtoupper($ini)).'</span>';
}

/**
 * Dernières notes saisies d'un élève dans sa classe actuelle (évaluations actives) : visibles dès la saisie.
 *
 * @param  DoliDB     $db Handler base
 * @param  EcoleEleve $e  Élève
 * @param  int        $n  Nombre maximum (0 = toutes)
 * @return object[]       Lignes : code, note_max, type, numero, trimestre, date_eval, label_fr, label_ar, date_note
 */
function espace_dernieres_notes($db, $e, $n = 5)
{
	if (!isModEnabled('notes')) {
		return array();
	}
	dol_include_once('/notes/core/lib/notes.lib.php');
	$p = $db->prefix();
	$sql = "SELECT n.valeur, n.absence, n.tms, ev.rowid as evid, ev.type, ev.numero, ev.trimestre, ev.date_eval, ev.note_max, ev.label, m.label_fr, m.label_ar";
	$sql .= " FROM ".$p."ecole_note n INNER JOIN ".$p."ecole_evaluation ev ON ev.rowid = n.fk_evaluation";
	$sql .= " INNER JOIN ".$p."ecole_matiere m ON m.rowid = ev.fk_matiere";
	$sql .= " WHERE n.fk_eleve = ".((int) $e->id)." AND ev.fk_classe = ".((int) $e->fk_classe)." AND ev.status = 1";
	$sql .= " ORDER BY n.tms DESC, n.rowid DESC";
	if ($n > 0) {
		$sql .= $db->plimit($n, 0);
	}
	$out = array();
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$o->code = notes_code($o->valeur, $o->absence);
		$out[] = $o;
	}
	return $out;
}

/**
 * Note affichée dans l'espace : « 14,5 / 20 », « Absent », « Dispensé », avec une pastille de couleur.
 *
 * @param  string $code Code de la note
 * @param  float  $max  Maximum
 * @return string       HTML
 */
function espace_note_html($code, $max)
{
	if ($code === '' || $code === null) {
		return '<span class="es-note es-note-vide">·</span>';
	}
	if ($code === NOTES_ABS || $code === NOTES_DISP) {
		return '<span class="es-note es-note-abs">'.dol_escape_htmltag(notes_code_label($code)).'</span>';
	}
	$ratio = ((float) $max > 0) ? (float) $code / (float) $max : 0;
	$cls = ($ratio < 0.5) ? 'es-note-bas' : (($ratio < 0.7) ? 'es-note-moyen' : 'es-note-bon');
	return '<span class="es-note '.$cls.'" dir="ltr"><b>'.dol_escape_htmltag(notes_fmt($code)).'</b><small>/'.dol_escape_htmltag(notes_fmt($max)).'</small></span>';
}

/**
 * Moyenne sur 20 avec pastille de couleur.
 *
 * @param  float|null $v Moyenne
 * @return string        HTML
 */
function espace_moy_html($v)
{
	if ($v === null) {
		return '<span class="es-note es-note-vide">—</span>';
	}
	$cls = ($v < 10) ? 'es-note-bas' : (($v < 14) ? 'es-note-moyen' : 'es-note-bon');
	return '<span class="es-note '.$cls.'" dir="ltr"><b>'.dol_escape_htmltag(notes_moy($v)).'</b><small>/20</small></span>';
}
