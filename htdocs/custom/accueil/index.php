<?php
/**
 * Accueil de Dolibarr pour une école : remplace l'accueil standard (inclus par htdocs/index.php, puis exit).
 * Tableau de bord de l'établissement selon les droits de l'utilisateur :
 *  - chiffres clés (élèves, présence du jour, paiements scolaires, personnel, salaires) ;
 *  - aujourd'hui (appels, absents, retards, enseignants absents, examens à venir) ;
 *  - graphiques (élèves par niveau, encaissements par mois) ;
 *  - notes (clôture des trimestres), salaires du mois ;
 *  - fil des derniers événements, du plus récent au plus ancien, chacun avec son lien ;
 *  - raccourcis.
 * L'accueil standard de Dolibarr reste accessible avec ?accueil_dolibarr=1.
 *
 * Fichier : custom/accueil/index.php
 */

if (!defined('DOL_DOCUMENT_ROOT')) {
	// Ouvert directement : on passe par l'accueil de Dolibarr
	header('Location: ../../index.php');
	exit;
}

dol_include_once('/classes/core/lib/classes.lib.php');
dol_include_once('/classes/core/lib/dashboard.lib.php');
ecole_annee_vue_forcer(ecole_annee_active()); // l'accueil montre toujours l'année en cours
$langs->loadLangs(array('main', 'other', 'classes@classes', 'accueil@accueil'));
if (isModEnabled('eleves')) {
	dol_include_once('/eleves/class/ecole_eleve.class.php');
	dol_include_once('/eleves/core/lib/paiements.lib.php');
	$langs->load('eleves@eleves');
}
if (isModEnabled('notes')) {
	$langs->load('notes@notes');
}
if (isModEnabled('personnel')) {
	$langs->load('personnel@personnel');
	dol_include_once('/personnel/core/lib/personnel.lib.php');
}
if (isModEnabled('salaires')) {
	$langs->load('salaires@salaires');
}

$canEleves = isModEnabled('eleves') && $user->hasRight('eleves', 'eleve', 'lire');
$canPaie = $canEleves && $user->hasRight('eleves', 'paiement', 'lire');
$canAbs = $canEleves && $user->hasRight('eleves', 'absence', 'lire');
$canNotes = isModEnabled('notes') && ($user->hasRight('notes', 'note', 'lire') || $user->hasRight('notes', 'bulletin', 'lire'));
$canPerso = isModEnabled('personnel') && $user->hasRight('personnel', 'employe', 'lire');
$canSal = isModEnabled('salaires') && $user->hasRight('salaires', 'bulletin', 'lire');
$canClasses = isModEnabled('classes') && $user->hasRight('classes', 'lire');

llxHeader('', $langs->trans('AccueilEcole').' - '.ecole_nom_etablissement(), '', '', 0, 0, '', '', '', 'mod-accueil page-index');

$bonjour = $langs->trans((int) dol_print_date(dol_now(), '%H', 'tzuser') < 13 ? 'AccueilBonjour' : 'AccueilBonsoir', trim($user->firstname) !== '' ? $user->firstname : $user->login);
$annee = function_exists('eleves_annee_label') ? $langs->trans('AccueilAnneeScolaire', eleves_annee_label(eleves_annee_scolaire())) : '';
print ecole_dash_hero(ecole_nom_etablissement() !== '' ? ecole_nom_etablissement() : $langs->trans('AccueilEcole'), dol_escape_htmltag($bonjour).($annee !== '' ? ' · '.dol_escape_htmltag($annee) : ''));

// Données
$el = $canEleves ? ecole_dash_eleves() : null;
$auj = ($canAbs || $canPerso || $canClasses) ? ecole_dash_aujourdhui() : null;
$pay = $canPaie ? ecole_dash_paiements() : null;
$per = $canPerso ? ecole_dash_personnel() : null;
$sal = $canSal ? ecole_dash_salaires() : null;
$not = $canNotes ? ecole_dash_notes() : null;

// Chiffres clés
$kpis = array();
if ($el) {
	$inscrits = (isset($el['statut'][1]) ? $el['statut'][1] : 0) + (isset($el['statut'][3]) ? $el['statut'][3] : 0);
	$kpis[] = array($langs->trans('EdElevesInscrits'), $inscrits, 'fa-user-graduate', 'blue', dol_buildpath('/eleves/eleve/list.php', 1).'?search_status=1,3', $langs->trans('EdFillesGarcons', $el['filles'], $el['garcons']));
	$kpis[] = array($langs->trans('StatutPreinscrit'), isset($el['statut'][0]) ? $el['statut'][0] : 0, 'fa-user-plus', 'orange', dol_buildpath('/eleves/eleve/list.php', 1).'?search_status=0', $langs->trans('EdNouveaux30j', $el['nouveaux']));
}
if ($auj && $canAbs) {
	$kpis[] = array($langs->trans('EdAbsentsAujourdhui'), $auj['absents'], 'fa-user-clock', $auj['absents'] ? 'red' : 'green', dol_buildpath('/eleves/absence/list.php', 1), $langs->trans('EdRetardsN', $auj['retards']));
}
if ($pay) {
	$kpis[] = array($langs->trans('EdEncaisseAujourdhui'), ecole_dash_montant($pay['jour']), 'fa-cash-register', 'green', dol_buildpath('/eleves/recu/list.php', 1), $langs->trans('EdNbRecus', $pay['nb_jour']));
	$kpis[] = array($langs->trans('EdEncaisseMois'), ecole_dash_montant($pay['mois']), 'fa-coins', 'teal', dol_buildpath('/eleves/recu/list.php', 1));
	if ($user->hasRight('eleves', 'paiement', 'impayes')) {
		$kpis[] = array($langs->trans('EdImpayes'), ecole_dash_montant($pay['impaye']), 'fa-exclamation-triangle', 'red', dol_buildpath('/eleves/impayes.php', 1), $langs->trans('EdNbEleves', $pay['nb_impayes']));
	}
}
if ($per) {
	$kpis[] = array($langs->trans('EdEmployesActifs'), $per['actifs'], 'fa-id-badge', 'purple', dol_buildpath('/personnel/index.php', 1), $langs->trans('EdProfsAbsents', $auj ? $auj['profs_absents'] : 0));
}
if ($sal) {
	$kpis[] = array($langs->trans('EdMasseSalarialeMois'), ecole_dash_montant($sal['net']), 'fa-money-check-alt', 'pink', dol_buildpath('/salaires/index.php', 1), $langs->trans('EdBulletinsPayes', $sal['paye'], $sal['brouillon'] + $sal['valide'] + $sal['paye']));
}
if (!empty($kpis)) {
	print ecole_dash_kpis($kpis);
}

print '<div class="ed-cols">';

// Aujourd'hui
if ($auj) {
	$lignes = array();
	if ($canAbs) {
		$lignes[] = array('<a href="'.dol_buildpath('/eleves/appel.php', 1).'">'.$langs->trans('EdAppelsFaits').'</a>', $auj['appels'].' / '.$auj['cours'], $auj['cours'] > 0 ? round(100 * $auj['appels'] / $auj['cours']) : null);
		$lignes[] = array($langs->trans('EdAbsents'), $auj['absents']);
		$lignes[] = array($langs->trans('EdRetards'), $auj['retards']);
		$lignes[] = array($langs->trans('EdRenvoyes'), $auj['renvoyes']);
	}
	if ($canPerso) {
		$lignes[] = array('<a href="'.dol_buildpath('/personnel/presence.php', 1).'">'.$langs->trans('EdProfsAbsentsLabel').'</a>', $auj['profs_absents']);
	}
	if ($canClasses) {
		$lignes[] = array($langs->trans('EdCoursAujourdhui'), $auj['cours']);
	}
	$html = ecole_dash_rows($lignes);
	if (!empty($auj['examens'])) {
		$html .= '<div class="opacitymedium" style="margin-top:10px"><i class="fas fa-file-signature"></i> '.$langs->trans('EdExamensAVenir').'</div>';
		foreach (array_slice($auj['examens'], 0, 4) as $e) {
			$html .= '<div class="ed-row"><span><b>'.dol_escape_htmltag($e->cref).'</b> · '.dol_escape_htmltag(ecole_label($e)).'</span><span class="opacitymedium">'.dol_print_date($db->jdate($e->date_examen), 'day').' '.dol_escape_htmltag($e->heure_debut).'</span></div>';
		}
	}
	print ecole_dash_box($langs->trans('EdAujourdhui'), 'fa-calendar-day', $html);
}

// Derniers événements
$ev = ecole_dash_evenements(40);
print ecole_dash_box($langs->trans('EdDerniersEvenements'), 'fa-stream', ecole_dash_feed($ev, 5));

// Élèves par niveau
if ($el) {
	$data = array();
	foreach ($el['niveaux'] as $n) {
		$data[] = array($n[0], $n[1]);
	}
	print ecole_dash_box($langs->trans('EdElevesParNiveau'), 'fa-chart-bar', ecole_dash_graph('accueil_niveaux', 'bars', $data, array($langs->trans('EdElevesInscrits'))), dol_buildpath('/eleves/index.php', 1));
}

// Paiements scolaires par mois + derniers reçus
if ($pay) {
	$html = ecole_dash_graph('accueil_encaisse', 'bars', $pay['par_mois'], array($langs->trans('EdEncaisse')), 200);
	foreach (array_slice($pay['derniers'], 0, 4) as $r) {
		$html .= '<div class="ed-row"><a href="'.dol_buildpath('/eleves/recu/card.php', 1).'?id='.((int) $r->rowid).'"><i class="fas fa-receipt"></i> '.dol_escape_htmltag($r->ref).'</a><span>'.ecole_dash_montant($r->montant).' <span class="opacitymedium">'.dol_print_date($db->jdate($r->date_recu), 'day').'</span></span></div>';
	}
	print ecole_dash_box($langs->trans('EdPaiementsScolaires'), 'fa-coins', $html, dol_buildpath('/eleves/recu/list.php', 1));
}

// Notes : clôture des trimestres
if ($not) {
	$lignes = array(array($langs->trans('EdEvaluations'), $not['evaluations']), array($langs->trans('EdNotesSaisies'), $not['notes']));
	for ($t = 1; $t <= 3; $t++) {
		$lignes[] = array($langs->trans('EdClotureTrimestre', $t), $not['cloturees'][$t].' / '.$not['classes'], $not['classes'] > 0 ? round(100 * $not['cloturees'][$t] / $not['classes']) : 0);
	}
	print ecole_dash_box($langs->trans('EdNotesEtBulletins'), 'fa-star', ecole_dash_rows($lignes), dol_buildpath('/notes/index.php', 1));
}

// Personnel et salaires
if ($per || $sal) {
	$lignes = array();
	if ($per) {
		$cats = function_exists('personnel_categories_choix') ? personnel_categories_choix() : array();
		foreach (array_slice($per['categories'], 0, 5, true) as $k => $n) {
			$lignes[] = array(dol_escape_htmltag(isset($cats[$k]) ? $cats[$k] : $k), $n);
		}
	}
	if ($sal) {
		$lignes[] = array($langs->trans('EdBulletinsBrouillon'), $sal['brouillon']);
		$lignes[] = array($langs->trans('EdBulletinsAPayer'), $sal['valide']);
		$lignes[] = array($langs->trans('EdBulletinsPayesMois'), $sal['paye'].' · '.ecole_dash_montant($sal['net_paye']));
	}
	print ecole_dash_box($langs->trans('EdPersonnelEtSalaires'), 'fa-id-badge', ecole_dash_rows($lignes), $per ? dol_buildpath('/personnel/index.php', 1) : dol_buildpath('/salaires/index.php', 1));
}

// Raccourcis
print ecole_dash_box($langs->trans('AccesRapides'), 'fa-bolt', ecole_dash_links(array(
	array($canEleves && $user->hasRight('eleves', 'eleve', 'creer'), dol_buildpath('/eleves/eleve/card.php', 1).'?action=create', $langs->trans('MenuNouvelEleve'), 'fa-user-plus'),
	array($canPaie && $user->hasRight('eleves', 'paiement', 'encaisser'), dol_buildpath('/eleves/caisse.php', 1), $langs->trans('MenuCaisse'), 'fa-cash-register'),
	array($canEleves && $user->hasRight('eleves', 'absence', 'appel'), dol_buildpath('/eleves/appel.php', 1), $langs->trans('MenuAppel'), 'fa-clipboard-check'),
	array(isModEnabled('notes') && ($user->hasRight('notes', 'note', 'saisir') || $user->hasRight('notes', 'note', 'saisirtout')), dol_buildpath('/notes/saisie.php', 1), $langs->trans('MenuSaisieNotes'), 'fa-pen'),
	array($canClasses, dol_buildpath('/classes/classe/emplois.php', 1), $langs->trans('MenuEmploisDuTemps'), 'fa-calendar-alt'),
	array($canPerso && $user->hasRight('personnel', 'presence', 'lire'), dol_buildpath('/personnel/presence.php', 1), $langs->trans('EdPresenceDuJour'), 'fa-chalkboard-teacher'),
	array($canSal, dol_buildpath('/salaires/index.php', 1), $langs->trans('MenuSalaires'), 'fa-money-check-alt'),
	array($canClasses, dol_buildpath('/classes/index.php', 1), $langs->trans('MenuEtablissement'), 'fa-school'),
)));

print '</div>';

llxFooter();
$db->close();
