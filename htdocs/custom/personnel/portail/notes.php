<?php
/**
 * Saisie des notes par l'enseignant dans son espace, adaptée au téléphone. Seulement ses classes et ses matières
 * (emploi du temps), avec les règles du module Notes : maximum de la note fixé par la matière, une composition par
 * trimestre, Absent / Dispensé, historique de chaque changement. Un trimestre clôturé ne se modifie plus ici
 * (correction avec motif seulement dans l'interface de gestion). Si la direction a bloqué la saisie sur sa fiche,
 * l'enseignant voit ses évaluations et ses notes sans rien pouvoir créer ni modifier.
 *
 *   notes                                             ses classes et matières
 *   notes/<classe>/<matière>[/t1|t2|t3]               évaluations du trimestre (+ devoir, + composition)
 *   notes/<classe>/<matière>/<t>/<évaluation>         grille de saisie
 *
 * Fichier : custom/personnel/portail/notes.php
 */

require_once __DIR__.'/init_espace.php';
dol_include_once('/notes/core/lib/notes.lib.php');

list($acces, $emp, $moi, $rubriques) = pe_session($db, 'notes');
$aff = pe_affectations($db, $emp);
$classes = pe_classes($db, $emp);

// Classe et matière choisies (seulement celles de l'enseignant)
$fk_classe = pe_arg(0) !== '' ? espace_id_par_jeton('classe', pe_arg(0), array_keys($classes)) : 0;
$fk_matiere = ($fk_classe && pe_arg(1) !== '') ? espace_id_par_jeton('matiere', pe_arg(1), array_keys($aff[$fk_classe])) : 0;
$periodes = array('t1' => 1, 't2' => 2, 't3' => 3);
$trimestre = isset($periodes[pe_arg(2)]) ? $periodes[pe_arg(2)] : ($fk_classe ? notes_trimestre_defaut($db, $fk_classe) : 1);
$urlListe = espace_page_url('notes');

if (!$fk_classe || !$fk_matiere) {
	// Liste des classes et matières
	pe_header($langs->trans('EspSaisieNotes'), $acces, $emp, $rubriques, 'notes');
	if (!personnel_saisie_notes_autorisee($emp)) {
		print espace_msg($langs->trans('EspSaisieNotesBloquee'), 'warn');
	}
	if (empty($aff)) {
		print espace_msg($langs->trans('AucunCoursEdt'), 'info');
	}
	print '<div class="es-cards">';
	foreach ($classes as $cid => $c) {
		$t = notes_trimestre_defaut($db, $cid);
		foreach (array_keys($aff[$cid]) as $mid) {
			$evals = notes_evaluations($db, $cid, $mid, $t);
			$url = espace_page_url('notes/'.espace_jeton('classe', $cid).'/'.espace_jeton('matiere', $mid));
			print '<a class="es-card" href="'.dol_escape_htmltag($url).'"><div class="es-item-top"><b>'.dol_escape_htmltag($c->ref.' · '.ecole_row_label($db, 'ecole_matiere', $mid)).'</b>';
			print '<i class="fas fa-chevron-'.(espace_rtl() ? 'left' : 'right').' es-chev"></i></div>';
			print '<div class="es-chips"><span class="es-chip">'.dol_escape_htmltag(notes_trimestre_label($t)).'</span><span class="es-chip es-chip-blue">'.$langs->trans('EspNbEvaluations', count($evals)).'</span>';
			if (notes_est_cloture($db, $cid, $t)) {
				print '<span class="es-chip es-chip-purple"><i class="fas fa-lock"></i> '.$langs->trans('TrimestreCloture').'</span>';
			}
			print '</div></a>';
		}
	}
	print '</div>';
	espace_footer();
	$db->close();
	exit;
}

$classe = $classes[$fk_classe];
$matieres = notes_matieres_classe($db, null, $fk_classe);
$mat = isset($matieres[$fk_matiere]) ? $matieres[$fk_matiere] : null;
$cloture = notes_est_cloture($db, $fk_classe, $trimestre);
$bloque = !personnel_saisie_notes_autorisee($emp);
$lecture = $cloture || $bloque; // consultation seulement
$evals = notes_evaluations($db, $fk_classe, $fk_matiere, $trimestre);
$jc = espace_jeton('classe', $fk_classe);
$jm = espace_jeton('matiere', $fk_matiere);
$tcode = array_search($trimestre, $periodes, true);
$urlTrim = espace_page_url('notes/'.$jc.'/'.$jm.'/'.$tcode);
$evid = pe_arg(3) !== '' ? espace_id_par_jeton('eval', pe_arg(3), array_keys($evals)) : 0;
$ev = $evid ? $evals[$evid] : null;
$urlEval = $ev ? espace_page_url('notes/'.$jc.'/'.$jm.'/'.$tcode.'/'.espace_jeton('eval', $evid)) : $urlTrim;

/*
 * Actions
 */
$action = GETPOST('action', 'aZ09');
if ($action === 'addeval' && $mat && !$lecture) {
	$type = GETPOSTINT('type') === NOTES_COMPO ? NOTES_COMPO : NOTES_DEVOIR;
	$error = '';
	$id = notes_evaluation_creer($db, $moi, $fk_classe, $fk_matiere, $trimestre, $type, GETPOST('date_eval', 'alpha'), GETPOST('label', 'alphanohtml'), $error);
	if ($id > 0) {
		header('Location: '.espace_page_url('notes/'.$jc.'/'.$jm.'/'.$tcode.'/'.espace_jeton('eval', $id)));
		exit;
	}
	pe_flash($error, false);
	header('Location: '.$urlTrim);
	exit;
}
$erreurs = array();
if ($action === 'savenotes' && $ev && !$lecture) {
	$saisies = GETPOST('note', 'array');
	$absences = GETPOST('abs', 'array');
	$codes = array();
	foreach (notes_eleves($db, $fk_classe, array_keys($evals)) as $eid => $e) {
		$k = espace_jeton('eleve', $eid);
		if ($e->ancien || (!array_key_exists($k, $saisies) && !array_key_exists($k, $absences))) {
			continue;
		}
		$err = '';
		$code = notes_parse(isset($saisies[$k]) ? $saisies[$k] : '', isset($absences[$k]) ? (string) $absences[$k] : '', (float) $ev->note_max, $err);
		if ($code === false) {
			$erreurs[$eid] = $err;
		} else {
			$codes[$eid] = $code;
		}
	}
	if (empty($erreurs)) {
		$error = '';
		$nb = notes_enregistrer($db, $moi, $ev, $codes, '', $error);
		if ($nb >= 0) {
			pe_flash($nb > 0 ? $langs->transnoentities('NotesEnregistrees', $nb, notes_evaluation_nom($ev)) : $langs->transnoentities('AucuneNoteChangee'));
			header('Location: '.$urlTrim);
			exit;
		}
		pe_flash($error, false);
		header('Location: '.$urlEval);
		exit;
	}
}

/*
 * Affichage
 */
pe_header($langs->trans('EspSaisieNotes'), $acces, $emp, $rubriques, 'notes', $ev ? $urlTrim : $urlListe);

print '<section class="es-card"><h2>'.dol_escape_htmltag($classe->ref.' · '.($mat ? ecole_label($mat) : '')).'</h2>';
if ($mat) {
	print '<div class="es-muted es-small">'.$langs->trans('NoteSur').' <span dir="ltr">'.notes_fmt($mat->note_max).'</span>'.((float) $mat->coefficient > 0 ? ' · '.$langs->trans('Coefficient').' '.notes_fmt($mat->coefficient) : '').'</div>';
}
print '</section>';

// Trimestres
print '<div class="es-seg">';
foreach ($periodes as $code => $t) {
	print '<a class="'.($t === $trimestre ? 'active' : '').'" href="'.dol_escape_htmltag(espace_page_url('notes/'.$jc.'/'.$jm.'/'.$code)).'">'.dol_escape_htmltag(notes_trimestre_label($t)).'</a>';
}
print '</div>';
if ($bloque) {
	print espace_msg($langs->trans('EspSaisieNotesBloquee'), 'warn');
} elseif ($cloture) {
	print espace_msg($langs->trans('EspTrimestreClotureLecture'), 'info');
}
if (!$mat) {
	print espace_msg($langs->trans('ErrorMatiereNonLiee'), 'warn');
}

if (!$ev) {
	// Évaluations du trimestre
	$vals = notes_valeurs($db, array_keys($evals));
	$nbEleves = count(notes_eleves($db, $fk_classe, array()));
	print '<div class="es-list">';
	foreach ($evals as $id => $e) {
		$codes = isset($vals[$id]) ? $vals[$id] : array();
		$st = notes_stats($codes);
		print '<a class="es-card es-item" href="'.dol_escape_htmltag(espace_page_url('notes/'.$jc.'/'.$jm.'/'.$tcode.'/'.espace_jeton('eval', $id))).'">';
		print '<div class="es-item-main"><b>'.dol_escape_htmltag(notes_evaluation_nom($e).($e->label ? ' — '.$e->label : '')).'</b>';
		print '<span class="es-muted es-small">'.($e->date_eval ? dol_print_date($db->jdate($e->date_eval), 'day') : '').'</span></div>';
		print '<div class="es-item-side"><span class="es-chip '.(count($codes) >= $nbEleves && $nbEleves ? 'es-chip-green' : 'es-chip-orange').'">'.$langs->trans('EspNotesSaisies', count($codes), $nbEleves).'</span>';
		if ($st['moyenne'] !== null) {
			print '<span class="es-small es-muted">'.$langs->trans('Moyenne').' <span dir="ltr">'.notes_fmt(round($st['moyenne'], 2)).' / '.notes_fmt($e->note_max).'</span></span>';
		}
		print '</div></a>';
	}
	if (empty($evals)) {
		print espace_msg($langs->trans('EspAucuneEvaluation'), 'info');
	}
	print '</div>';

	if (!$lecture && $mat) {
		$compo = false;
		foreach ($evals as $e) {
			$compo = $compo || ((int) $e->type === NOTES_COMPO);
		}
		print '<section class="es-card"><h2><i class="fas fa-plus-circle"></i> '.$langs->trans('EspNouvelleEvaluation').'</h2>';
		print '<form method="POST" action="'.dol_escape_htmltag($urlTrim).'" class="es-form">';
		print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="addeval">';
		print '<label>'.$langs->trans('Type').'</label><div class="es-toggles">';
		print '<label class="es-toggle"><input type="radio" name="type" value="'.NOTES_DEVOIR.'" checked> '.$langs->trans('Devoir').'</label>';
		if (!$compo) {
			print '<label class="es-toggle"><input type="radio" name="type" value="'.NOTES_COMPO.'"> '.$langs->trans('Composition').'</label>';
		}
		print '</div>';
		print '<label for="date_eval">'.$langs->trans('Date').'</label><input type="date" id="date_eval" name="date_eval" value="'.dol_escape_htmltag(personnel_aujourdhui()).'">';
		print '<label for="label">'.$langs->trans('EspLibelleFacultatif').'</label><input type="text" id="label" name="label" maxlength="120" dir="auto">';
		print '<button type="submit" class="es-btn es-btn-primary es-btn-block">'.$langs->trans('EspCreerEtSaisir').'</button></form></section>';
	}
	espace_footer();
	$db->close();
	exit;
}

// Grille de saisie d'une évaluation
$vals = notes_valeurs($db, array($evid));
$vals = isset($vals[$evid]) ? $vals[$evid] : array();
$eleves = notes_eleves($db, $fk_classe, array($evid));
$postNotes = !empty($erreurs) ? GETPOST('note', 'array') : null;
$postAbs = !empty($erreurs) ? GETPOST('abs', 'array') : null;
print '<h2 class="es-h2">'.dol_escape_htmltag(notes_evaluation_nom($ev).($ev->label ? ' — '.$ev->label : '')).' <span class="es-muted es-small">'.$langs->trans('NoteSur').' <span dir="ltr">'.notes_fmt($ev->note_max).'</span>'.'</span></h2>';
if (!empty($erreurs)) {
	print espace_msg($langs->trans('ErrorNotesNonEnregistrees', count($erreurs)), 'error');
}
if (!$lecture) {
	print '<form method="POST" action="'.dol_escape_htmltag($urlEval).'" class="es-form es-notes-form">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="savenotes">';
	print '<p class="es-muted es-small">'.$langs->trans('EspAideSaisieNotes').'</p>';
}
print '<div class="es-card es-notes">';
foreach ($eleves as $eid => $e) {
	$k = espace_jeton('eleve', $eid);
	$code = isset($vals[$eid]) ? $vals[$eid] : '';
	$saisie = ($postNotes !== null && isset($postNotes[$k])) ? $postNotes[$k] : (($code !== NOTES_ABS && $code !== NOTES_DISP && $code !== '') ? notes_fmt($code) : '');
	$abs = ($postAbs !== null && isset($postAbs[$k])) ? $postAbs[$k] : (($code === NOTES_ABS || $code === NOTES_DISP) ? $code : '');
	print '<div class="es-note-row'.(isset($erreurs[$eid]) ? ' es-note-err' : '').'"><span class="es-note-name"><b dir="ltr">'.($e->numero_appel ? (int) $e->numero_appel.'.' : '').'</b> '.dol_escape_htmltag(ecole_label($e));
	if ($e->ancien) {
		print ' <span class="es-chip">'.$langs->trans('AncienEleve').'</span>';
	}
	if (isset($erreurs[$eid])) {
		print '<br><span class="es-small es-ko">'.dol_escape_htmltag($erreurs[$eid]).'</span>';
	}
	print '</span>';
	if ($lecture || $e->ancien) {
		print espace_note_html($code, $ev->note_max);
	} else {
		print '<span class="es-note-inputs"><input type="text" inputmode="decimal" name="note['.$k.']" value="'.dol_escape_htmltag($saisie).'" dir="ltr" class="es-note-input" autocomplete="off"'.($abs !== '' ? ' disabled' : '').'>';
		print '<select name="abs['.$k.']" class="es-note-abs"><option value=""></option><option value="'.NOTES_ABS.'"'.($abs === NOTES_ABS ? ' selected' : '').'>'.$langs->trans('NoteAbsentCourt').'</option>';
		print '<option value="'.NOTES_DISP.'"'.($abs === NOTES_DISP ? ' selected' : '').'>'.$langs->trans('NoteDispenseCourt').'</option></select></span>';
	}
	print '</div>';
}
if (empty($eleves)) {
	print '<div class="es-muted">'.$langs->trans('AucunEleveClasseDate').'</div>';
}
print '</div>';
if (!$lecture) {
	print '<button type="submit" class="es-btn es-btn-primary es-btn-block es-sticky-save"><i class="fas fa-save"></i> '.$langs->trans('Enregistrer').'</button></form>';
	print '<script>document.querySelectorAll(".es-note-abs").forEach(function(s){s.addEventListener("change",function(){var i=s.parentNode.querySelector(".es-note-input");i.disabled=s.value!=="";if(s.value!==""){i.value="";}});});</script>';
}

espace_footer();
$db->close();
