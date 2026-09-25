<?php
/**
 * Résultats.
 *
 * Sans classe : suivi de toutes les classes pour la période (moyenne de la classe, plus forte, plus faible,
 * élèves en difficulté, clôture).
 * Avec une classe : tableau des moyennes (rang, moyenne de chaque matière, moyenne générale, mention,
 * distinction, décision pour l'année), impression des bulletins / relevés, exports PDF et Excel.
 * Chaque colonne a son filtre.
 *
 * Fichier : custom/notes/resultats.php
 */

require 'init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/notes/core/lib/resultats.lib.php');

$langs->loadLangs(array('notes@notes', 'eleves@eleves', 'classes@classes', 'other'));

if (!$user->hasRight('notes', 'bulletin', 'lire')) {
	accessforbidden();
}

$form = new Form($db);
$self = $_SERVER['PHP_SELF'];
$classes = notes_classes_resultats($db, $user);
$fk_classe = GETPOSTINT('fk_classe');
if (!isset($classes[$fk_classe])) {
	$fk_classe = 0;
}
$periode = notes_periode_requete($db, $fk_classe);

llxHeader('', $langs->trans('Resultats'), '', '', 0, 0, '', '', '', 'mod-notes page-resultats');
print load_fiche_titre($langs->trans('Resultats'), $fk_classe ? '' : ecole_export_buttons('suivi_resultats', '&periode='.$periode, 'notes'), 'fa-poll');

$choix = array();
foreach ($classes as $cid => $c) {
	$choix[$cid] = $c->ref.' - '.ecole_label($c);
}
print '<form method="GET" action="'.dol_escape_htmltag($self).'" class="marginbottomonly">';
print '<input type="hidden" name="periode" value="'.$periode.'">';
print img_picto('', 'fa-chalkboard', 'class="pictofixedwidth"').$form->selectarray('fk_classe', $choix, $fk_classe, $langs->trans('ToutesLesClasses'), 0, 0, 'onchange="this.form.submit()"', 0, 0, 0, '', 'minwidth200 maxwidth300', 1);
print '<noscript><input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Refresh')).'"></noscript>';
print '</form>';
print notes_periode_tabs($db, $self.'?fk_classe='.$fk_classe, $periode, $fk_classe);

if ($fk_classe) {
	notes_resultats_html($db, $user, $classes[$fk_classe], $periode);
	llxFooter();
	$db->close();
	exit;
}

/*
 * Suivi de toutes les classes
 */
$seuil = (float) getDolGlobalString('NOTES_SEUIL_DIFFICULTE', '10');
$etats = array('ouvert' => $langs->trans('Ouvert'), 'cloture' => $langs->trans('Cloture'));
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent notes-filtrable">';
print notes_filtres_ligne(array('text', 'range', 'range', 'range', 'range', 'range', 'range', $etats, null));
print '<tr class="liste_titre"><td>'.$langs->trans('Classe').'</td><td class="center">'.$langs->trans('ElevesClasses').'</td><td class="center">'.$langs->trans('MoyenneClasse').'</td>';
print '<td class="center">'.$langs->trans('PlusForte').'</td><td class="center">'.$langs->trans('PlusFaible').'</td><td class="center">'.$langs->trans('TauxReussite').'</td>';
print '<td class="center">'.$langs->trans('EnDifficulte').'</td><td>'.$langs->trans('Etat').'</td><td></td></tr>';
foreach ($classes as $cid => $c) {
	$calc = notes_calcul($db, $cid, $periode);
	$sg = $calc['stats']['generale'];
	$reussis = 0;
	$diff = 0;
	foreach ($calc['res'] as $r) {
		if ($r['moyenne'] !== null) {
			$reussis += round($r['moyenne'], 2) >= 10 ? 1 : 0;
			$diff += round($r['moyenne'], 2) < $seuil ? 1 : 0;
		}
	}
	$taux = $sg['nb'] > 0 ? round($reussis / $sg['nb'] * 100) : null;
	$close = notes_periode_close($db, $cid, $periode);
	$url = $self.'?fk_classe='.$cid.'&periode='.$periode;
	print '<tr class="oddeven"><td class="nowraponall"><a href="'.dol_escape_htmltag($url).'">'.dol_escape_htmltag($c->ref.' - '.ecole_label($c)).'</a></td>';
	print '<td class="center" data-f="'.$sg['nb'].'">'.$sg['nb'].' / '.count($calc['eleves']).'</td>';
	foreach (array('moy', 'max', 'min') as $k) {
		print '<td class="center" data-f="'.($sg[$k] !== null ? round($sg[$k], 2) : '').'">'.notes_moy($sg[$k]).'</td>';
	}
	print '<td class="center" data-f="'.($taux !== null ? $taux : '').'">'.($taux !== null ? $taux.' %' : '—').'</td>';
	print '<td class="center" data-f="'.$diff.'">'.($diff > 0 ? '<a href="'.dol_buildpath('/notes/difficultes.php', 1).'?fk_classe='.$cid.'&periode='.$periode.'"><span class="badge badge-danger">'.$diff.'</span></a>' : '<span class="opacitymedium">0</span>').'</td>';
	print '<td data-f="'.($close ? 'cloture' : 'ouvert').'">'.($close ? '<span class="badge badge-status6">'.img_picto('', 'fa-lock', 'class="pictofixedwidth"').$langs->trans('Cloture').'</span>' : '<span class="badge badge-status0">'.$langs->trans('Ouvert').'</span>').'</td>';
	print '<td class="right"><a class="button smallpaddingimp" href="'.dol_escape_htmltag($url).'">'.$langs->trans('VoirResultats').'</a></td></tr>';
}
print '</table></div>';
print '<span class="opacitymedium small">'.$langs->trans('AideSuiviResultats', notes_fmt($seuil)).'</span>';

llxFooter();
$db->close();
