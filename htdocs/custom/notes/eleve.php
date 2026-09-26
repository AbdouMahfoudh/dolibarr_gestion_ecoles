<?php
/**
 * Onglet « Notes » de la fiche élève : pour chaque période, les notes de chaque matière (devoirs, composition),
 * la moyenne, le rang, la mention, la distinction et l'observation du conseil ; impression du bulletin,
 * du relevé annuel et du certificat de scolarité.
 *
 * Fichier : custom/notes/eleve.php
 */

require 'init.php';
dol_include_once('/eleves/class/ecole_eleve.class.php');
dol_include_once('/notes/core/lib/resultats.lib.php');

$langs->loadLangs(array('notes@notes', 'eleves@eleves', 'classes@classes', 'other'));

if (!$user->hasRight('notes', 'bulletin', 'lire') && !$user->hasRight('notes', 'note', 'lire')) {
	accessforbidden();
}
$object = new EcoleEleve($db);
if (GETPOSTINT('id') <= 0 || $object->fetch(GETPOSTINT('id')) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$id = (int) $object->id;
$fk_classe = ecole_eleve_classe_annee($db, $id, (int) $object->fk_classe);
$periode = notes_periode_requete($db, $fk_classe);
$annee = ($periode === 0);
$self = $_SERVER['PHP_SELF'].'?id='.$id;

llxHeader('', $langs->trans('Notes').' - '.$object->ref, '', '', 0, 0, '', '', '', 'mod-notes page-eleve-notes');
eleve_print_banner($object, 'notes');

print '<div class="fichecenter"><br>';
print ecole_annee_selecteur(array('id', 'periode'));
if ($fk_classe <= 0) {
	print '<div class="info">'.$langs->trans('EleveNonClasse').'</div></div>';
	print dol_get_fiche_end();
	llxFooter();
	$db->close();
	exit;
}
print '<div class="right">'.ecole_export_buttons('eleve', '&id='.$id.'&periode='.$periode, 'notes').'</div>';
print notes_periode_tabs($db, $self, $periode, $fk_classe);

$calc = notes_calcul($db, $fk_classe, $periode);
if (!isset($calc['res'][$id])) {
	print '<div class="info">'.$langs->trans('EleveNonClasse').'</div></div>';
	print dol_get_fiche_end();
	llxFooter();
	$db->close();
	exit;
}
$r = $calc['res'][$id];
$close = notes_periode_close($db, $fk_classe, $periode);
$conseil = notes_conseil($db, $fk_classe, $periode);
$c = isset($conseil[$id]) ? $conseil[$id] : null;

// Boutons d'impression
$peutImprimer = $user->hasRight('notes', 'bulletin', 'lire');
print '<div class="tabsAction" style="margin-top:0">';
if ($peutImprimer) {
	$urlB = dol_buildpath('/notes/bulletin.php', 1).'?fk_classe='.$fk_classe.'&periode='.$periode.'&fk_eleve='.$id;
	$lib = $langs->trans($annee ? 'ReleveAnnuel' : 'BulletinDeNotes');
	if ($close) {
		print '<a class="butAction" href="'.dol_escape_htmltag($urlB).'" target="_blank">'.img_picto('', 'fa-file-pdf', 'class="pictofixedwidth"').$lib.'</a>';
	} else {
		print '<a class="butActionRefused classfortooltip" href="#" title="'.dol_escape_htmltag($langs->trans($annee ? 'BulletinsApresClotureAnnee' : 'BulletinsApresCloture')).'">'.img_picto('', 'fa-file-pdf', 'class="pictofixedwidth"').$lib.'</a>';
	}
	if (in_array((int) $object->status, EcoleEleve::statusOccupantPlace(), true)) {
		print '<a class="butAction" href="'.dol_buildpath('/notes/certificat.php', 1).'?id='.$id.'" target="_blank">'.img_picto('', 'fa-certificate', 'class="pictofixedwidth"').$langs->trans('CertificatScolarite').'</a>';
	}
}
print '</div>';

if (!$close) {
	print '<span class="badge badge-status1">'.$langs->trans($annee ? 'ResultatsProvisoiresAnnee' : 'ResultatsProvisoires').'</span><br><br>';
}

print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
if ($annee) {
	print '<tr class="liste_titre"><td>'.$langs->trans('Matiere').'</td><td class="center">'.$langs->trans('Coefficient').'</td>';
	for ($t = 1; $t <= 3; $t++) {
		print '<td class="center">'.$langs->trans('Trimestre'.$t).'</td>';
	}
	print '<td class="center"><b>'.$langs->trans('MoyenneAnnuelle').'</b></td><td class="center">'.$langs->trans('Rang').'</td><td class="center">'.$langs->trans('MoyClasse').'</td></tr>';
	foreach ($calc['matieres'] as $mid => $m) {
		$rm = $r['matieres'][$mid];
		print '<tr class="oddeven"><td>'.dol_escape_htmltag(ecole_label($m)).'</td><td class="center">'.notes_fmt($m->coefficient).'</td>';
		for ($t = 1; $t <= 3; $t++) {
			print '<td class="center">'.notes_moy($rm['t'][$t]).'</td>';
		}
		print '<td class="center"><b>'.notes_moy($rm['moyenne']).'</b></td><td class="center">'.($rm['rang'] !== null ? (int) $rm['rang'] : '—').'</td><td class="center">'.notes_moy($calc['stats']['matieres'][$mid]['moy']).'</td></tr>';
	}
	print '<tr class="liste_total"><td>'.$langs->trans('MoyenneGenerale').'</td><td></td>';
	for ($t = 1; $t <= 3; $t++) {
		print '<td class="center">'.notes_moy($r['trimestres'][$t]).(!empty($r['exclus'][$t]) ? ' <span title="'.dol_escape_htmltag($langs->trans('TrimestreNonCompte')).'">*</span>' : '').'</td>';
	}
	print '<td class="center"><b>'.notes_moy($r['moyenne']).'</b></td><td></td><td class="center">'.notes_moy($calc['stats']['generale']['moy']).'</td></tr>';
} else {
	// Notes de chaque évaluation
	$evals = array();
	$resql = $db->query("SELECT rowid, fk_matiere, type, numero, note_max FROM ".$db->prefix()."ecole_evaluation WHERE fk_classe = ".$fk_classe." AND trimestre = ".$periode." AND status = 1".ecole_annee_sql()." ORDER BY type, numero");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$evals[(int) $o->rowid] = $o;
	}
	$vals = notes_valeurs($db, array_keys($evals));
	print '<tr class="liste_titre"><td>'.$langs->trans('Matiere').'</td><td class="center">'.$langs->trans('Coefficient').'</td><td>'.$langs->trans('Devoirs').'</td><td class="center">'.$langs->trans('NoteDevoirs').'</td>';
	print '<td class="center">'.$langs->trans('Composition').'</td><td class="center"><b>'.$langs->trans('MoyenneSur20').'</b></td><td class="center">'.$langs->trans('Rang').'</td><td class="center">'.$langs->trans('MoyClasse').'</td></tr>';
	foreach ($calc['matieres'] as $mid => $m) {
		$rm = $r['matieres'][$mid];
		$dv = array();
		$compo = '—';
		foreach ($evals as $evid => $ev) {
			if ((int) $ev->fk_matiere !== $mid) {
				continue;
			}
			$code = isset($vals[$evid][$id]) ? $vals[$evid][$id] : '';
			$txt = ($code === '') ? '·' : notes_code_label($code).((int) $ev->note_max != 20 && $code !== NOTES_ABS && $code !== NOTES_DISP ? '/'.notes_fmt($ev->note_max) : '');
			if ((int) $ev->type === NOTES_COMPO) {
				$compo = $txt;
			} else {
				$dv[] = '<span title="'.dol_escape_htmltag(notes_evaluation_nom($ev)).'">'.dol_escape_htmltag($txt).'</span>';
			}
		}
		print '<tr class="oddeven"><td>'.dol_escape_htmltag(ecole_label($m)).'</td><td class="center">'.notes_fmt($m->coefficient).'</td>';
		print '<td>'.(empty($dv) ? '<span class="opacitymedium">—</span>' : implode(' &nbsp;·&nbsp; ', $dv)).'</td>';
		print '<td class="center">'.notes_moy($rm['devoirs']).'</td><td class="center">'.dol_escape_htmltag($compo).'</td>';
		print '<td class="center'.($rm['moyenne'] !== null && $rm['moyenne'] < 10 ? ' error' : '').'"><b>'.notes_moy($rm['moyenne']).'</b></td>';
		print '<td class="center">'.($rm['rang'] !== null ? (int) $rm['rang'] : '—').'</td><td class="center">'.notes_moy($calc['stats']['matieres'][$mid]['moy']).'</td></tr>';
	}
	print '<tr class="liste_total"><td>'.$langs->trans('MoyenneGenerale').'</td><td></td><td></td><td></td><td></td><td class="center"><b>'.notes_moy($r['moyenne']).'</b></td><td></td><td class="center">'.notes_moy($calc['stats']['generale']['moy']).'</td></tr>';
}
print '</table></div><br>';

// Résumé
$mention = notes_mention($db, $r['moyenne']);
$dist = notes_distinction($db, $c, $r['moyenne']);
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans($annee ? 'MoyenneAnnuelle' : 'MoyenneGenerale').'</td><td><b>'.notes_moy($r['moyenne']).'</b> / 20</td></tr>';
print '<tr><td>'.$langs->trans('Rang').'</td><td><b>'.dol_escape_htmltag(notes_rang_label($r['rang'], $r['exaequo'])).'</b> / '.$calc['nb_classes'].'</td></tr>';
print '<tr><td>'.$langs->trans('Mention').'</td><td>'.($mention ? dol_escape_htmltag(ecole_label($mention)) : '—').'</td></tr>';
print '<tr><td>'.$langs->trans('Distinction').'</td><td>'.($dist ? '<span class="badge badge-status4">'.dol_escape_htmltag(ecole_label($dist)).'</span>' : '—').'</td></tr>';
if ($annee && $calc['regle']['decision'] !== 'aucune') {
	$decision = notes_decision($calc['regle'], $c, $r['moyenne']);
	print '<tr><td>'.$langs->trans('DecisionFinAnneeCourt').'</td><td>'.($decision !== '' ? dol_escape_htmltag($langs->trans(notes_decisions()[$decision])) : '—').'</td></tr>';
}
print '<tr><td>'.$langs->trans('ObservationDirection').'</td><td>'.($c && $c->observation ? dol_nl2br(dol_escape_htmltag($c->observation)) : '<span class="opacitymedium">—</span>').'</td></tr>';
print '</table>';
print '</div>';

print dol_get_fiche_end();
llxFooter();
$db->close();
