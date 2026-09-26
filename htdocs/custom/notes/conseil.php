<?php
/**
 * Conseil de classe (direction) : pour chaque élève d'une classe et une période, l'observation de la direction
 * et la distinction (proposée automatiquement selon la moyenne, modifiable) ; pour l'année, la décision de
 * fin d'année (proposée selon le seuil si les règles le prévoient, modifiable).
 * Un trimestre clôturé n'est plus modifiable (le rouvrir d'abord).
 *
 * Fichier : custom/notes/conseil.php
 */

require 'init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/notes/core/lib/resultats.lib.php');

$langs->loadLangs(array('notes@notes', 'eleves@eleves', 'classes@classes', 'other'));

if (!$user->hasRight('notes', 'bulletin', 'conseil')) {
	accessforbidden();
}

$form = new Form($db);
$self = $_SERVER['PHP_SELF'];
$action = GETPOST('action', 'aZ09');
$classes = notes_classes_resultats($db, $user);
$fk_classe = GETPOSTINT('fk_classe');
if (!isset($classes[$fk_classe])) {
	$fk_classe = 0;
}
$periode = notes_periode_requete($db, $fk_classe);
$annee = ($periode === 0);
$close = $fk_classe ? (ecole_annee_passee() || (!$annee && notes_est_cloture($db, $fk_classe, $periode))) : false;
$url = $self.'?fk_classe='.$fk_classe.'&periode='.$periode;

/*
 * Actions
 */
if ($action === 'save' && $fk_classe && !$close) {
	$calc = notes_calcul($db, $fk_classe, $periode);
	$obs = GETPOST('obs', 'array');
	$dist = GETPOST('dist', 'array');
	$dec = GETPOST('dec', 'array');
	$err = 0;
	$db->begin();
	foreach ($calc['eleves'] as $eid => $e) {
		if (!isset($obs[$eid]) && !isset($dist[$eid])) {
			continue;
		}
		$o = isset($obs[$eid]) ? dol_string_nohtmltag((string) $obs[$eid]) : '';
		$d = isset($dist[$eid]) ? (string) $dist[$eid] : 'auto';
		$decision = $annee ? (isset($dec[$eid]) ? (string) $dec[$eid] : '') : null;
		if ($decision !== null && $decision !== '' && !isset(notes_decisions()[$decision])) {
			$decision = '';
		}
		if (notes_conseil_enregistrer($db, $user, $eid, $fk_classe, $periode, $o, $d, $decision) < 0) {
			$err++;
		}
	}
	if ($err) {
		$db->rollback();
		setEventMessages($db->lasterror(), null, 'errors');
	} else {
		$db->commit();
		setEventMessages($langs->trans('ConseilEnregistre'), null, 'mesgs');
		header('Location: '.$url);
		exit;
	}
}

/*
 * Affichage
 */
llxHeader('', $langs->trans('ConseilDeClasse'), '', '', 0, 0, '', '', '', 'mod-notes page-conseil');
print load_fiche_titre($langs->trans('ConseilDeClasse'), $fk_classe ? ecole_export_buttons('conseil', '&fk_classe='.$fk_classe.'&periode='.$periode, 'notes') : '', 'fa-users');
print ecole_annee_selecteur(array('fk_classe', 'periode'));

$choix = array();
foreach ($classes as $cid => $c) {
	$choix[$cid] = $c->ref.' - '.ecole_label($c);
}
print '<form method="GET" action="'.dol_escape_htmltag($self).'" class="marginbottomonly">';
print '<input type="hidden" name="periode" value="'.$periode.'">';
print img_picto('', 'fa-chalkboard', 'class="pictofixedwidth"').$form->selectarray('fk_classe', $choix, $fk_classe, $langs->trans('ChoisirClasse'), 0, 0, 'onchange="this.form.submit()"', 0, 0, 0, '', 'minwidth200 maxwidth300', 1);
print '</form>';
print notes_periode_tabs($db, $self.'?fk_classe='.$fk_classe, $periode, $fk_classe);

if (!$fk_classe) {
	print '<div class="opacitymedium">'.$langs->trans('AideConseilChoisirClasse').'</div>';
	llxFooter();
	$db->close();
	exit;
}

$calc = notes_calcul($db, $fk_classe, $periode);
$conseil = notes_conseil($db, $fk_classe, $periode);
$regle = $calc['regle'];
if (empty($calc['eleves'])) {
	print '<div class="info">'.$langs->trans('AucunEleveClasse').'</div>';
	llxFooter();
	$db->close();
	exit;
}
if ($close) {
	print '<div class="warning">'.img_picto('', 'fa-lock', 'class="pictofixedwidth"').$langs->trans('ConseilClotureLectureSeule').'</div>';
}
print '<div class="opacitymedium marginbottomonly">'.$langs->trans($annee ? 'AideConseilAnnee' : 'AideConseil').' <a href="'.dol_buildpath('/notes/resultats.php', 1).'?fk_classe='.$fk_classe.'&periode='.$periode.'">'.$langs->trans('VoirResultats').'</a></div>';

$distinctions = notes_distinctions($db);
$avecDecision = $annee && $regle['decision'] !== 'aucune';
$mentionsF = array();
foreach (notes_mentions($db) as $mo) {
	$mentionsF[$mo->ref] = ecole_label($mo);
}
$distF = array('-' => $langs->trans('Aucune'));
foreach ($distinctions as $do) {
	$distF[$do->ref] = ecole_label($do);
}
$decF = array();
foreach (notes_decisions() as $k => $l) {
	$decF[$k] = $langs->trans($l);
}

if (!$close) {
	print '<form method="POST" action="'.dol_escape_htmltag($self).'">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save">';
	print '<input type="hidden" name="fk_classe" value="'.$fk_classe.'"><input type="hidden" name="periode" value="'.$periode.'">';
}
$filtres = array('range', 'text', 'range', $mentionsF, $distF);
if ($avecDecision) {
	$filtres[] = $decF;
}
$filtres[] = 'text';
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent notes-filtrable">';
print notes_filtres_ligne($filtres);
print '<tr class="liste_titre"><td class="center">'.$langs->trans('Rang').'</td><td>'.$langs->trans('Eleve').'</td><td class="center">'.$langs->trans($annee ? 'MoyenneAnnuelle' : 'MoyenneGenerale').'</td>';
print '<td>'.$langs->trans('Mention').'</td><td>'.$langs->trans('Distinction').'</td>'.($avecDecision ? '<td>'.$langs->trans('DecisionFinAnneeCourt').'</td>' : '').'<td>'.$langs->trans('ObservationDirection').'</td></tr>';

foreach (notes_ordre_rang($calc) as $eid) {
	$e = $calc['eleves'][$eid];
	$r = $calc['res'][$eid];
	$c = isset($conseil[$eid]) ? $conseil[$eid] : null;
	$mention = notes_mention($db, $r['moyenne']);
	$auto = notes_distinction_auto($db, $r['moyenne']);
	$retenue = notes_distinction($db, $c, $r['moyenne']);
	print '<tr class="oddeven">';
	print '<td class="center" data-f="'.($r['rang'] !== null ? (int) $r['rang'] : '').'"><b>'.dol_escape_htmltag(notes_rang_label($r['rang'], $r['exaequo'])).'</b></td>';
	print '<td class="nowraponall" dir="auto">'.dol_escape_htmltag(ecole_label($e)).'</td>';
	print '<td class="center" data-f="'.($r['moyenne'] !== null ? round($r['moyenne'], 2) : '').'"><b>'.notes_moy($r['moyenne']).'</b></td>';
	print '<td data-f="'.($mention ? dol_escape_htmltag($mention->ref) : '').'">'.($mention ? dol_escape_htmltag(ecole_label($mention)) : '').'</td>';
	print '<td data-f="'.($retenue ? dol_escape_htmltag($retenue->ref) : '-').'">';
	if ($close) {
		print $retenue ? dol_escape_htmltag(ecole_label($retenue)) : '';
	} else {
		$sel = ($c && (int) $c->distinction_forcee) ? ((int) $c->fk_distinction > 0 ? (string) ((int) $c->fk_distinction) : '') : 'auto';
		print '<select name="dist['.$eid.']" class="flat minwidth150">';
		print '<option value="auto"'.($sel === 'auto' ? ' selected' : '').'>'.dol_escape_htmltag($langs->trans('DistinctionAuto', $auto ? ecole_label($auto) : $langs->transnoentities('Aucune'))).'</option>';
		print '<option value=""'.($sel === '' ? ' selected' : '').'>'.dol_escape_htmltag($langs->trans('Aucune')).'</option>';
		foreach ($distinctions as $did => $do) {
			print '<option value="'.$did.'"'.($sel === (string) $did ? ' selected' : '').'>'.dol_escape_htmltag(ecole_label($do)).'</option>';
		}
		print '</select>';
	}
	print '</td>';
	if ($avecDecision) {
		$decision = notes_decision($regle, $c, $r['moyenne']);
		print '<td data-f="'.$decision.'">';
		$auto = ($regle['decision'] === 'auto' && $r['moyenne'] !== null) ? (round($r['moyenne'], 2) >= (float) $regle['seuil_passage'] ? 'admis' : 'redouble') : '';
		print '<select name="dec['.$eid.']" class="flat minwidth150">';
		print '<option value="">'.dol_escape_htmltag($auto !== '' ? $langs->trans('DecisionProposee', $langs->transnoentities(notes_decisions()[$auto])) : $langs->trans('AChoisir')).'</option>';
		foreach (notes_decisions() as $k => $l) {
			print '<option value="'.$k.'"'.($c && $c->decision === $k ? ' selected' : '').'>'.dol_escape_htmltag($langs->trans($l)).'</option>';
		}
		print '</select></td>';
	}
	$obs = $c ? (string) $c->observation : '';
	print '<td data-f="'.dol_escape_htmltag($obs).'">';
	if ($close) {
		print dol_nl2br(dol_escape_htmltag($obs));
	} else {
		print '<textarea name="obs['.$eid.']" rows="2" class="flat centpercent" dir="auto" maxlength="2000">'.dol_escape_htmltag($obs).'</textarea>';
	}
	print '</td></tr>';
}
print '</table></div>';
if (!$close) {
	print '<div class="center"><br><input type="submit" class="button button-save" value="'.dol_escape_htmltag($langs->trans('Save')).'"></div>';
	print '</form>';
}

llxFooter();
$db->close();
