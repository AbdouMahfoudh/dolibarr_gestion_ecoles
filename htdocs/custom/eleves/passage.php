<?php
/**
 * Assistant de passage à l'année scolaire suivante :
 *   1. vérifications et classe suivante de chaque classe ;
 *   2. proposition pour chaque élève (d'après la décision du conseil), corrigée par la direction ;
 *   3. passage (irréversible) : élèves repris en « ancien attendu », les autres archivés, arriérés reportés.
 * Ainsi que « Vider les attendus » (anciens élèves qui ne sont pas revenus → archivés).
 *
 * Fichier : custom/eleves/passage.php
 */

require 'init.php';
dol_include_once('/eleves/core/lib/passage.lib.php');

$langs->loadLangs(array('eleves@eleves', 'classes@classes', 'notes@notes', 'other'));

if (!$user->hasRight('eleves', 'annee', 'passage')) {
	accessforbidden();
}
ecole_annee_vue_forcer(ecole_annee_active()); // décisions et notes de l'année en cours
$form = new Form($db);
$self = $_SERVER['PHP_SELF'];
$action = GETPOST('action', 'aZ09');
$etape = GETPOSTINT('etape') === 2 ? 2 : 1;
$annee = ecole_annee_active();
$suivante = $annee + 1;

/*
 * Actions
 */
if ($action === 'confirm_vider' && GETPOST('confirm', 'alpha') === 'yes') {
	$n = passage_vider_attendus($db, $user);
	if ($n < 0) {
		setEventMessages($langs->trans('Error'), null, 'errors');
	} else {
		setEventMessages($langs->trans('AttendusVides', $n), null);
	}
	header('Location: '.$self);
	exit;
}
if ($action === 'executer') {
	// Choix de la direction, sinon proposition (élève ajouté depuis l'affichage de la page)
	$cibles = array();
	$cl = passage_classes($db);
	foreach (passage_eleves($db, $cl, passage_suivantes($cl)) as $liste) {
		foreach ($liste as $e) {
			$cibles[(int) $e->rowid] = (int) $e->cible;
		}
	}
	$error = '';
	$bilan = passage_executer($db, $user, $cibles, $error);
	if (!is_array($bilan)) {
		setEventMessages($langs->trans('PassageEchec'), array($error), 'errors');
		$etape = 2;
	} else {
		setEventMessages($langs->trans('PassageReussi', ecole_annee_label($suivante)), array(
			$langs->trans('PassageBilanRepris', $bilan['repris']),
			$langs->trans('PassageBilanArchives', $bilan['archives']),
			$langs->trans('PassageBilanArrieres', $bilan['arrieres'], price($bilan['montant']).' '.$conf->currency),
		));
		header('Location: '.$self.'?fait=1');
		exit;
	}
}

/*
 * Affichage
 */
llxHeader('', $langs->trans('PassageAnnee'), '', '', 0, 0, '', '', '', 'mod-eleves page-passage');
print load_fiche_titre($langs->trans('PassageAnnee'), '', 'fa-forward');

// Anciens attendus : vider
$resql = $db->query("SELECT COUNT(*) as nb FROM ".$db->prefix()."ecole_eleve WHERE entity IN (".getEntity('ecole_eleve').") AND status = ".EcoleEleve::STATUS_ANCIEN_ATTENDU);
$o = $resql ? $db->fetch_object($resql) : null;
$nbAttendus = $o ? (int) $o->nb : 0;
if ($action === 'vider') {
	print $form->formconfirm($self, $langs->trans('ViderAttendus'), $langs->trans('ConfirmViderAttendus', $nbAttendus), 'confirm_vider', '', 0, 1);
}
if ($nbAttendus > 0) {
	print '<div class="info">'.img_picto('', 'fa-user-clock', 'class="pictofixedwidth"').$langs->trans('AnciensAttendusInfo', $nbAttendus);
	print ' <a href="'.dol_buildpath('/eleves/eleve/list.php', 1).'?search_status='.EcoleEleve::STATUS_ANCIEN_ATTENDU.'">'.$langs->trans('VoirLaListe').'</a>';
	print ' &nbsp; <a class="butActionDelete smallpaddingimp" href="'.$self.'?action=vider&token='.newToken().'">'.$langs->trans('ViderAttendus').'</a></div><br>';
}
if (GETPOSTINT('fait')) {
	print '<div class="ok">'.$langs->trans('PassageFaitInfo', ecole_annee_label($annee)).'</div><br>';
}

print '<div class="opacitymedium">'.$langs->trans('PassageAide', ecole_annee_label($annee), ecole_annee_label($suivante)).'</div><br>';

$classes = passage_classes($db);
$suivantes = passage_suivantes($classes);
$eleves = passage_eleves($db, $classes, $suivantes);
$verif = passage_verifications($db, $classes, $eleves);

// Étapes
$head = array(
	array($self.'?etape=1', '1. '.$langs->trans('PassageEtapeClasses'), 'e1'),
	array('#', '2. '.$langs->trans('PassageEtapeEleves'), 'e2'),
);
print dol_get_fiche_head($head, 'e'.$etape, '', -1);

foreach ($verif['avertissements'] as $m) {
	print '<div class="warning">'.dol_escape_htmltag($m).'</div>';
}
foreach ($verif['infos'] as $m) {
	print '<div class="info">'.dol_escape_htmltag($m).'</div>';
}

$choixClasses = array();
foreach ($classes as $cid => $c) {
	$choixClasses[$cid] = $c->ref.' - '.ecole_label($c);
}

if ($etape === 1) {
	// Classe suivante de chaque classe
	print '<form method="POST" action="'.dol_escape_htmltag($self).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="etape" value="2">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Classe').'</td><td class="right">'.$langs->trans('Eleves').'</td><td>'.$langs->trans('ClasseSuivante').'</td></tr>';
	$choix = array(0 => $langs->trans('FinDeCycle')) + $choixClasses;
	foreach ($classes as $cid => $c) {
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($c->ref.' - '.ecole_label($c)).'</td>';
		print '<td class="right">'.(isset($eleves[$cid]) ? count($eleves[$cid]) : 0).'</td>';
		print '<td>'.$form->selectarray('suivante['.$cid.']', $choix, $suivantes[$cid], 0, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
	}
	print '</table>';
	print '<div class="opacitymedium">'.$langs->trans('ClasseSuivanteAide').'</div>';
	print '<div class="center"><br><input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('PassageVoirEleves')).'"></div>';
	print '</form>';
} else {
	// Proposition pour chaque élève
	print '<form method="POST" action="'.dol_escape_htmltag($self).'" onsubmit="return confirm(\''.dol_escape_js($langs->transnoentities('ConfirmPassage', ecole_annee_label($suivante))).'\');">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="executer">';
	print '<input type="hidden" name="etape" value="2">';
	foreach ($suivantes as $cid => $sid) {
		print '<input type="hidden" name="suivante['.$cid.']" value="'.((int) $sid).'">';
	}
	$choix = array(0 => $langs->trans('NeRevientPas')) + $choixClasses;
	$decisions = array('admis' => 'DecisionAdmis', 'redouble' => 'DecisionRedouble');
	print '<div class="div-table-responsive"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('NumeroAppel').'</td><td>'.$langs->trans('Matricule').'</td><td>'.$langs->trans('NomComplet').'</td>';
	print '<td class="right">'.$langs->trans('MoyenneAnnuelle').'</td><td>'.$langs->trans('DecisionConseil').'</td><td>'.$langs->trans('ClasseRentree', ecole_annee_label($suivante)).'</td></tr>';
	$total = 0;
	foreach ($classes as $cid => $c) {
		if (empty($eleves[$cid])) {
			continue;
		}
		print '<tr class="liste_titre_sub"><td colspan="6"><b>'.dol_escape_htmltag($c->ref.' - '.ecole_label($c)).'</b> <span class="opacitymedium">('.count($eleves[$cid]).')</span>';
		print ' → '.dol_escape_htmltag($suivantes[$cid] > 0 ? $choixClasses[$suivantes[$cid]] : $langs->trans('FinDeCycle')).'</td></tr>';
		foreach ($eleves[$cid] as $e) {
			$url = dol_buildpath('/eleves/eleve/card.php', 1).'?id='.((int) $e->rowid);
			print '<tr class="oddeven"><td>'.($e->numero_appel ? (int) $e->numero_appel : '').'</td>';
			print '<td class="nowraponall"><a href="'.$url.'">'.dol_escape_htmltag($e->ref).'</a></td><td>'.dol_escape_htmltag(ecole_label($e));
			if ((int) $e->status === EcoleEleve::STATUS_SUSPENDU) {
				print ' <span class="badge badge-status7">'.$langs->trans('StatutSuspendu').'</span>';
			}
			print '</td><td class="right">'.($e->moyenne !== null && function_exists('notes_moy') ? notes_moy($e->moyenne) : '<span class="opacitymedium">—</span>').'</td>';
			print '<td>'.($e->decision !== '' ? $langs->trans($decisions[$e->decision]) : '<span class="opacitymedium">'.$langs->trans('SansDecision').'</span>').'</td>';
			print '<td>'.$form->selectarray('cible['.((int) $e->rowid).']', $choix, $e->cible, 0, 0, 0, '', 0, 0, 0, '', 'minwidth250'.($e->cible === 0 ? ' error' : '')).'</td></tr>';
			$total++;
		}
	}
	if (!$total) {
		print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	print '</table></div>';
	print '<div class="info">'.$langs->trans('PassageExplication', ecole_annee_label($suivante));
	if (isModEnabled('espace')) {
		print '<br>'.$langs->trans('PassageCodesExpirent');
	}
	print '</div>';
	print '<div class="center"><br><a class="button button-cancel" href="'.$self.'?'.dol_escape_htmltag(http_build_query(array('etape' => 1, 'suivante' => $suivantes))).'">'.$langs->trans('Back').'</a> ';
	print '<input type="submit" class="button button-save" value="'.dol_escape_htmltag($langs->trans('PasserAnnee', ecole_annee_label($suivante))).'"></div>';
	print '</form>';
}
print dol_get_fiche_end();

llxFooter();
$db->close();
