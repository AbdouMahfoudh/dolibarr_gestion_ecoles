<?php
/**
 * Saisie des notes.
 *
 * Sans classe : tableau de suivi des classes (devoirs et compositions par trimestre, clôtures).
 * Avec une classe : ses matières pour le trimestre choisi (coefficient, barème, enseignant, avancement).
 * Avec une classe et une matière : la grille des élèves × évaluations du trimestre. On crée un devoir
 * (autant qu'on veut) ou la composition, puis on saisit la colonne d'une évaluation : une note, « Abs »
 * (absent) ou « Disp » (dispensé). Chaque changement est gardé dans l'historique. Un trimestre clôturé
 * ne se modifie qu'avec le droit « corriger », avec un motif.
 *
 * Fichier : custom/notes/saisie.php
 */

require 'init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

$langs->loadLangs(array('notes@notes', 'eleves@eleves', 'classes@classes', 'other'));

if (!notes_voit_tout($user) && !$user->hasRight('notes', 'note', 'saisir')) {
	accessforbidden();
}

$form = new Form($db);
$self = $_SERVER['PHP_SELF'];
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');

// Classe, matière, trimestre (seulement ce que l'utilisateur a le droit de voir)
$classes = notes_classes($db, $user);
$fk_classe = GETPOSTINT('fk_classe');
if (!isset($classes[$fk_classe])) {
	$fk_classe = 0;
}
$matieres = $fk_classe ? notes_matieres_classe($db, $user, $fk_classe) : array();
$fk_matiere = GETPOSTINT('fk_matiere');
if (!isset($matieres[$fk_matiere])) {
	$fk_matiere = 0;
}
$trimestre = GETPOSTINT('trimestre');
if (!in_array($trimestre, array(1, 2, 3), true)) {
	$trimestre = notes_trimestre_defaut($db, $fk_classe);
}
$urlBase = $self.'?fk_classe='.$fk_classe.'&fk_matiere='.$fk_matiere.'&trimestre='.$trimestre;

$cloture = $fk_classe ? notes_est_cloture($db, $fk_classe, $trimestre) : false;
$peutSaisir = ($fk_classe && $fk_matiere && !ecole_annee_passee()) ? notes_peut_saisir($db, $user, $fk_classe, $fk_matiere) : false;
$peutCorriger = $peutSaisir && $user->hasRight('notes', 'note', 'corriger');
$editable = $peutSaisir && (!$cloture || $peutCorriger);

$evals = ($fk_classe && $fk_matiere) ? notes_evaluations($db, $fk_classe, $fk_matiere, $trimestre) : array();
$evalId = GETPOSTINT('eval');
$ev = ($evalId > 0 && isset($evals[$evalId])) ? $evals[$evalId] : null;

/*
 * Actions
 */
$erreursNotes = array(); // fk_eleve => message (saisie refusée : on réaffiche ce qui a été tapé)
if ($fk_classe && $fk_matiere) {
	if ($action === 'addeval' && $peutSaisir) {
		$type = GETPOSTINT('type') === NOTES_COMPO ? NOTES_COMPO : NOTES_DEVOIR;
		$error = '';
		$id = notes_evaluation_creer($db, $user, $fk_classe, $fk_matiere, $trimestre, $type, GETPOST('date_eval', 'alpha'), GETPOST('label', 'alphanohtml'), $error);
		if ($id > 0) {
			header('Location: '.$urlBase.'&eval='.$id.'#grille');
			exit;
		}
		setEventMessages($error, null, 'errors');
	}

	if ($action === 'saveeval' && $ev && $editable) {
		if (notes_evaluation_modifier($db, $user, $ev, GETPOST('date_eval', 'alpha'), GETPOST('label', 'alphanohtml')) > 0) {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			header('Location: '.$urlBase.'&eval='.((int) $ev->rowid).'#grille');
			exit;
		}
		setEventMessages($db->lasterror(), null, 'errors');
	}

	if ($action === 'confirm_deleteeval' && $confirm === 'yes' && $ev && $peutSaisir) {
		if ($cloture) {
			setEventMessages($langs->trans('ErrorTrimestreCloture'), null, 'errors');
		} elseif (notes_evaluation_supprimer($db, $user, $ev) > 0) {
			setEventMessages($langs->trans('EvaluationSupprimee', notes_evaluation_nom($ev)), null, 'mesgs');
			header('Location: '.$urlBase.'#grille');
			exit;
		}
	}

	if ($action === 'savenotes' && $ev && $editable) {
		$motif = trim(GETPOST('motif', 'alphanohtml'));
		if ($cloture && $motif === '') {
			setEventMessages($langs->trans('ErrorMotifObligatoireCloture'), null, 'errors');
			$erreursNotes[0] = '';
		} else {
			$saisies = GETPOST('note', 'array');
			$absences = GETPOST('abs', 'array');
			$codes = array();
			foreach (notes_eleves($db, $fk_classe, array_keys($evals)) as $eid => $e) {
				if (!array_key_exists($eid, $saisies) && !array_key_exists($eid, $absences)) {
					continue; // élève absent du formulaire (arrivé entre-temps) : rien à changer
				}
				$err = '';
				$code = notes_parse(isset($saisies[$eid]) ? $saisies[$eid] : '', isset($absences[$eid]) ? (string) $absences[$eid] : '', (float) $ev->note_max, $err);
				if ($code === false) {
					$erreursNotes[$eid] = $err;
				} else {
					$codes[$eid] = $code;
				}
			}
			if (!empty($erreursNotes)) {
				setEventMessages($langs->trans('ErrorNotesNonEnregistrees', count($erreursNotes)), null, 'errors');
			} else {
				$error = '';
				$nb = notes_enregistrer($db, $user, $ev, $codes, $cloture ? $motif : '', $error);
				if ($nb >= 0) {
					setEventMessages($nb > 0 ? $langs->trans('NotesEnregistrees', $nb, notes_evaluation_nom($ev)) : $langs->trans('AucuneNoteChangee'), null, $nb > 0 ? 'mesgs' : 'warnings');
					header('Location: '.$urlBase.'#grille');
					exit;
				}
				setEventMessages($error, null, 'errors');
			}
		}
	}
}

/*
 * Affichage
 */
dol_include_once('/notes/core/lib/calcul.lib.php');
llxHeader('', $langs->trans('SaisieNotes'), '', '', 0, 0, '', array('/notes/css/notes.css'), '', 'mod-notes page-saisie');

// Exports de la vue affichée : toutes les classes, matières d'une classe, ou grille des notes d'une matière
if ($fk_classe && $fk_matiere) {
	$exports = ecole_export_buttons('grille', '&fk_classe='.$fk_classe.'&fk_matiere='.$fk_matiere.'&trimestre='.$trimestre, 'notes');
} elseif ($fk_classe) {
	$exports = ecole_export_buttons('saisie_matieres', '&fk_classe='.$fk_classe.'&trimestre='.$trimestre, 'notes');
} else {
	$exports = ecole_export_buttons('saisie_classes', '', 'notes');
}
print load_fiche_titre($langs->trans('SaisieNotes'), $exports, 'fa-marker');
print ecole_annee_selecteur(array('fk_classe', 'fk_matiere', 'trimestre'));

// Barre de navigation : toutes les classes › classe › matière, et trimestres
$choixClasses = array();
foreach ($classes as $cid => $c) {
	$choixClasses[$cid] = $c->ref.' - '.ecole_label($c);
}
print '<div class="nt-toolbar">';
print '<form method="GET" action="'.dol_escape_htmltag($self).'" class="nt-crumbs">';
print '<input type="hidden" name="trimestre" value="'.$trimestre.'">';
print '<a class="nt-home" href="'.dol_escape_htmltag($self.'?trimestre='.$trimestre).'">'.img_picto('', 'fa-th-large', 'class="pictofixedwidth"').$langs->trans('ToutesLesClasses').'</a>';
if (!empty($classes)) {
	print '<span class="nt-sep">›</span>';
	print $form->selectarray('fk_classe', $choixClasses, $fk_classe, $langs->trans('ChoisirClasse'), 0, 0, 'onchange="this.form.fk_matiere && (this.form.fk_matiere.value=\'\'); this.form.submit()"', 0, 0, 0, '', 'minwidth200 maxwidth300', 1);
}
if ($fk_classe && !empty($matieres)) {
	$choixMat = array();
	foreach ($matieres as $mid => $m) {
		$choixMat[$mid] = ecole_label($m);
	}
	print '<span class="nt-sep">›</span>';
	print $form->selectarray('fk_matiere', $choixMat, $fk_matiere, $langs->trans('ChoisirMatiere'), 0, 0, 'onchange="this.form.submit()"', 0, 0, 0, '', 'minwidth200 maxwidth300', 1);
}
print '<noscript><input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Refresh')).'"></noscript>';
print '</form>';
print '<div class="nt-seg">';
for ($t = 1; $t <= 3; $t++) {
	$lock = ($fk_classe && notes_est_cloture($db, $fk_classe, $t)) ? ' '.img_picto($langs->trans('TrimestreCloture'), 'fa-lock') : '';
	print '<a class="'.($t === $trimestre ? 'on' : '').'" href="'.dol_escape_htmltag($self.'?fk_classe='.$fk_classe.'&fk_matiere='.$fk_matiere.'&trimestre='.$t).'">'.notes_trimestre_label($t).$lock.'</a>';
}
print '</div>';
print '</div>';

if (empty($classes)) {
	// Enseignant sans cours dans l'emploi du temps
	print '<div class="info">'.$langs->trans(notes_voit_tout($user) ? 'AucuneClasse' : 'AucuneClasseEnseignant').'</div>';
	llxFooter();
	$db->close();
	exit;
}

/*
 * Toutes les classes : une carte par classe (avancement de chaque trimestre)
 */
if (!$fk_classe) {
	$p = $db->prefix();
	$suivi = array();
	$resql = $db->query("SELECT fk_classe, trimestre, type, COUNT(*) as nb FROM ".$p."ecole_evaluation WHERE status = 1".ecole_annee_sql()." GROUP BY fk_classe, trimestre, type");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$suivi[(int) $o->fk_classe][(int) $o->trimestre][(int) $o->type] = (int) $o->nb;
	}
	$nbEleves = array();
	$resql = $db->query("SELECT fk_classe, COUNT(*) as nb FROM ".$p."ecole_eleve WHERE status IN (".implode(',', EcoleEleve::statusOccupantPlace()).") GROUP BY fk_classe");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$nbEleves[(int) $o->fk_classe] = (int) $o->nb;
	}
	$clotures = notes_clotures($db);

	notes_saisie_filtres(array('' => $langs->trans('Toutes'), 'sanscompo' => $langs->trans('CompositionsManquantes'), 'avecnotes' => $langs->trans('EvaluationsCreees'), 'cloture' => $langs->trans('Cloture')), 'RechercherClasse');
	print '<div class="nt-cards">';
	foreach ($classes as $cid => $c) {
		$nbMat = count(notes_matieres_classe($db, $user, $cid));
		$nbE = isset($nbEleves[$cid]) ? $nbEleves[$cid] : 0;
		$etat = array();
		$lignes = '';
		for ($t = 1; $t <= 3; $t++) {
			$d = isset($suivi[$cid][$t][NOTES_DEVOIR]) ? $suivi[$cid][$t][NOTES_DEVOIR] : 0;
			$k = isset($suivi[$cid][$t][NOTES_COMPO]) ? $suivi[$cid][$t][NOTES_COMPO] : 0;
			$closed = isset($clotures[$cid.'|'.$t]) && (int) $clotures[$cid.'|'.$t]->status === 1;
			if ($t === $trimestre) {
				$etat[] = ($k < $nbMat) ? 'sanscompo' : '';
				$etat[] = ($d || $k) ? 'avecnotes' : '';
				$etat[] = $closed ? 'cloture' : '';
			}
			$pct = $nbMat > 0 ? min(100, round($k / $nbMat * 100)) : 0;
			$lignes .= '<div class="nt-trim-row"'.($t === $trimestre ? ' style="font-weight:600"' : '').'><span>'.$langs->trans('TrimestreCourt'.$t).'</span>';
			$lignes .= '<div class="nt-bar'.($pct >= 100 ? ' nt-bar-ok' : '').'" title="'.dol_escape_htmltag($langs->trans('SuiviDevoirsCompos', $d, $k, $nbMat)).'"><span style="width:'.$pct.'%"></span></div>';
			$lignes .= '<span class="nowraponall">'.$k.'/'.$nbMat.($closed ? ' '.img_picto($langs->trans('TrimestreCloture'), 'fa-lock') : '').'</span></div>';
		}
		print '<a class="nt-card" href="'.dol_escape_htmltag($self.'?fk_classe='.$cid.'&trimestre='.$trimestre).'" data-search="'.dol_escape_htmltag($c->ref.' '.$c->label_fr.' '.$c->label_ar).'" data-etat="'.trim(implode(' ', $etat)).'">';
		print '<div class="nt-card-head"><div><div class="nt-card-title">'.dol_escape_htmltag($c->ref).'</div><div class="nt-card-sub">'.dol_escape_htmltag(ecole_label($c)).'</div></div>';
		print '<span class="nt-tag nt-accent">'.img_picto('', 'fa-user-graduate').' '.$nbE.'</span></div>';
		print '<div class="nt-card-meta"><span class="nt-tag">'.img_picto('', 'fa-book').' '.$langs->trans('NbMatieresN', $nbMat).'</span></div>';
		print '<div class="nt-trims"><span class="nt-card-sub">'.$langs->trans('CompositionsParTrimestre').'</span>'.$lignes.'</div>';
		print '</a>';
	}
	print '</div>';
	print '<br><span class="opacitymedium small">'.$langs->trans('AideSuiviClasses').'</span>';
	notes_saisie_filtres_js();
	llxFooter();
	$db->close();
	exit;
}

$classe = $classes[$fk_classe];
$regle = notes_regle_classe($db, $fk_classe);
$choixRegles = notes_regle_choix();

if ($cloture) {
	$c = notes_cloture($db, $fk_classe, $trimestre);
	print '<div class="warning">'.img_picto('', 'fa-lock', 'class="pictofixedwidth"').$langs->trans('TrimestreClotureLe', notes_trimestre_label($trimestre), dol_print_date($db->jdate($c->date_cloture), 'dayhour', 'tzuserrel'), ecole_user_label($db, $c->fk_user_cloture));
	print $peutCorriger ? ' '.$langs->trans('TrimestreClotureCorrection') : '';
	print '</div>';
}

/*
 * Une classe : une carte par matière (devoirs, avancement de la composition)
 */
if (!$fk_matiere) {
	if (empty($matieres)) {
		print '<div class="info">'.$langs->trans('AucuneMatiereClasse').' <a href="'.dol_buildpath('/classes/classe/matieres.php', 1).'?id='.$fk_classe.'">'.$langs->trans('MatieresCoefficients').'</a></div>';
		llxFooter();
		$db->close();
		exit;
	}
	$p = $db->prefix();
	$parMat = array();
	$compoIds = array();
	$resql = $db->query("SELECT rowid, fk_matiere, type FROM ".$p."ecole_evaluation WHERE fk_classe = ".$fk_classe." AND trimestre = ".$trimestre." AND status = 1".ecole_annee_sql());
	while ($resql && ($o = $db->fetch_object($resql))) {
		$parMat[(int) $o->fk_matiere][(int) $o->type][] = (int) $o->rowid;
		if ((int) $o->type === NOTES_COMPO) {
			$compoIds[(int) $o->fk_matiere] = (int) $o->rowid;
		}
	}
	$nbNotesCompo = array();
	if (!empty($compoIds)) {
		$resql = $db->query("SELECT fk_evaluation, COUNT(*) as nb FROM ".$p."ecole_note WHERE fk_evaluation IN (".implode(',', $compoIds).") GROUP BY fk_evaluation");
		while ($resql && ($o = $db->fetch_object($resql))) {
			$nbNotesCompo[(int) $o->fk_evaluation] = (int) $o->nb;
		}
	}
	$nbEl = count(notes_eleves($db, $fk_classe, array()));
	$exceptions = notes_exceptions_classe($db, $fk_classe);

	print '<div class="nt-hero"><div><h2>'.dol_escape_htmltag($classe->ref).' <span class="opacitymedium" style="font-weight:normal">'.dol_escape_htmltag(ecole_label($classe)).'</span></h2>';
	print '<div class="nt-card-meta"><span class="nt-tag">'.img_picto('', 'fa-user-graduate').' '.$langs->trans('NbElevesN', $nbEl).'</span><span class="nt-tag">'.img_picto('', 'fa-book').' '.$langs->trans('NbMatieresN', count($matieres)).'</span>';
	print '<span class="nt-tag">'.$langs->trans('NoteTrimestreFormule', notes_fmt($regle['poids_devoirs']), notes_fmt($regle['poids_compo']), notes_fmt($regle['poids_devoirs'] + $regle['poids_compo'])).'</span></div></div>';
	print '<div>'.($cloture ? '<span class="badge badge-status6">'.img_picto('', 'fa-lock', 'class="pictofixedwidth"').$langs->trans('Cloture').'</span>' : '<span class="badge badge-status4">'.$langs->trans('Ouvert').'</span>');
	if ($user->hasRight('notes', 'bulletin', 'lire')) {
		print ' <a class="button smallpaddingimp" href="'.dol_buildpath('/notes/resultats.php', 1).'?fk_classe='.$fk_classe.'&periode='.$trimestre.'">'.img_picto('', 'fa-poll', 'class="pictofixedwidth"').$langs->trans('VoirResultats').'</a>';
	}
	print '</div></div>';

	notes_saisie_filtres(array('' => $langs->trans('Toutes'), 'aucune' => $langs->trans('CompositionPasEncore'), 'incomplete' => $langs->trans('CompositionIncomplete'), 'complete' => $langs->trans('CompositionComplete')), 'RechercherMatiere');
	print '<div class="nt-cards">';
	foreach ($matieres as $mid => $m) {
		$url = $self.'?fk_classe='.$fk_classe.'&fk_matiere='.$mid.'&trimestre='.$trimestre;
		$calc = isset($exceptions[$mid]) ? $exceptions[$mid] : $regle['calcul_devoirs'];
		$n = isset($compoIds[$mid], $nbNotesCompo[$compoIds[$mid]]) ? $nbNotesCompo[$compoIds[$mid]] : 0;
		$etat = !isset($compoIds[$mid]) ? 'aucune' : ($n >= $nbEl ? 'complete' : 'incomplete');
		$nbD = isset($parMat[$mid][NOTES_DEVOIR]) ? count($parMat[$mid][NOTES_DEVOIR]) : 0;
		$pct = ($nbEl > 0 && isset($compoIds[$mid])) ? min(100, round($n / $nbEl * 100)) : 0;
		print '<a class="nt-card" href="'.dol_escape_htmltag($url).'" data-search="'.dol_escape_htmltag($m->label_fr.' '.$m->label_ar.' '.implode(' ', $m->enseignants)).'" data-etat="'.$etat.'">';
		print '<div class="nt-card-head"><div><div class="nt-card-title" dir="auto">'.dol_escape_htmltag(ecole_label($m)).'</div></div>';
		print '<span class="nt-tag nt-accent" title="'.dol_escape_htmltag($langs->trans('Coefficient')).'">×'.notes_fmt($m->coefficient).'</span></div>';
		print '<div class="nt-card-meta"><span class="nt-tag">'.$langs->trans('NoteSur').' '.notes_fmt($m->note_max).'</span>';
		print '<span class="nt-tag'.(isset($exceptions[$mid]) ? ' nt-warn' : '').'" title="'.dol_escape_htmltag($langs->trans('NoteDevoirs')).'">'.$langs->trans($choixRegles['calcul_devoirs'][$calc]).'</span>';
		print '<span class="nt-tag">'.img_picto('', 'fa-chalkboard-teacher').' '.(empty($m->enseignants) ? '—' : dol_escape_htmltag(implode(', ', $m->enseignants))).'</span></div>';
		print '<div class="nt-card-meta"><span class="nt-tag'.($nbD ? ' nt-accent' : '').'">'.$langs->trans('NbDevoirsN', $nbD).'</span></div>';
		print '<div><div class="nt-card-sub" style="display:flex;justify-content:space-between"><span>'.$langs->trans('Composition').'</span><span>'.(isset($compoIds[$mid]) ? $n.' / '.$nbEl : $langs->trans('PasEncore')).'</span></div>';
		print '<div class="nt-bar'.($etat === 'complete' ? ' nt-bar-ok' : '').'"><span style="width:'.$pct.'%"></span></div></div>';
		print '<div class="nt-card-foot"><span></span><span class="button smallpaddingimp">'.img_picto('', notes_peut_saisir($db, $user, $fk_classe, $mid) ? 'fa-pen' : 'fa-eye', 'class="pictofixedwidth"').$langs->trans(notes_peut_saisir($db, $user, $fk_classe, $mid) ? 'SaisirNotes' : 'VoirNotes').'</span></div>';
		print '</a>';
	}
	print '</div>';
	print '<br><span class="opacitymedium small">'.$langs->trans('AideMatieresClasse').'</span>';
	notes_saisie_filtres_js();
	llxFooter();
	$db->close();
	exit;
}

/*
 * Une matière : évaluations du trimestre et grille des élèves
 */
$mat = $matieres[$fk_matiere];
$calc = notes_calcul_devoirs($db, $fk_classe, $fk_matiere);
$eleves = notes_eleves($db, $fk_classe, array_keys($evals));
$valeurs = notes_valeurs($db, array_keys($evals));
$resultats = notes_calcul_trimestre($db, $fk_classe, $trimestre);
$nbActuels = 0;
foreach ($eleves as $el) {
	$nbActuels += $el->ancien ? 0 : 1;
}
$aCompo = false;
foreach ($evals as $e) {
	$aCompo = $aCompo || (int) $e->type === NOTES_COMPO;
}

// En-tête : matière, classe, règles
print '<div class="nt-hero"><div>';
print '<h2 dir="auto">'.dol_escape_htmltag(ecole_label($mat)).'</h2>';
print '<div class="nt-card-meta">';
print '<span class="nt-tag nt-accent">'.img_picto('', 'fa-chalkboard').' '.dol_escape_htmltag($classe->ref.' - '.ecole_label($classe)).'</span>';
print '<span class="nt-tag">'.$langs->trans('Coefficient').' '.notes_fmt($mat->coefficient).'</span>';
print '<span class="nt-tag">'.$langs->trans('NoteSur').' '.notes_fmt($mat->note_max).'</span>';
if (!empty($mat->enseignants)) {
	print '<span class="nt-tag">'.img_picto('', 'fa-chalkboard-teacher').' '.dol_escape_htmltag(implode(', ', $mat->enseignants)).'</span>';
}
print '<span class="nt-tag" title="'.dol_escape_htmltag($langs->trans('RegleCalcul')).'">'.$langs->trans('NoteDevoirs').' : '.$langs->trans($choixRegles['calcul_devoirs'][$calc]).'</span>';
print '<span class="nt-tag">'.$langs->trans('NoteTrimestreFormule', notes_fmt($regle['poids_devoirs']), notes_fmt($regle['poids_compo']), notes_fmt($regle['poids_devoirs'] + $regle['poids_compo'])).'</span>';
print '</div></div><div class="nowraponall">';
print ($cloture ? '<span class="badge badge-status6">'.img_picto('', 'fa-lock', 'class="pictofixedwidth"').$langs->trans('Cloture').'</span>' : '<span class="badge badge-status4">'.$langs->trans('Ouvert').'</span>');
print ' <a class="button smallpaddingimp" href="'.dol_escape_htmltag(dol_buildpath('/notes/historique.php', 1).'?fk_classe='.$fk_classe.'&fk_matiere='.$fk_matiere.'&trimestre='.$trimestre).'">'.img_picto('', 'fa-history', 'class="pictofixedwidth"').$langs->trans('Historique').'</a>';
print '</div></div>';

// Évaluations du trimestre (onglets) et création
print '<a id="grille"></a><div class="nt-evals">';
foreach ($evals as $eid => $e) {
	$s = notes_stats(isset($valeurs[$eid]) ? $valeurs[$eid] : array());
	$pct = $nbActuels > 0 ? min(100, round($s['nb'] / $nbActuels * 100)) : 0;
	$compo = (int) $e->type === NOTES_COMPO;
	$lien = $editable ? $urlBase.'&eval='.$eid.'#grille' : '#grille';
	print '<a class="nt-eval '.($compo ? 'nt-compo' : 'nt-devoir').($ev && (int) $ev->rowid === $eid ? ' on' : '').'" href="'.dol_escape_htmltag($lien).'"'.($editable ? ' title="'.dol_escape_htmltag($langs->trans('SaisirNotes')).'"' : '').'>';
	print '<span class="nt-eval-title">'.($editable ? img_picto('', $ev && (int) $ev->rowid === $eid ? 'fa-pen' : ($compo ? 'fa-file-signature' : 'fa-clipboard'), 'class="pictofixedwidth"') : '').dol_escape_htmltag(notes_evaluation_nom($e)).'</span>';
	print '<span class="nt-eval-sub">'.($e->date_eval ? dol_print_date($db->jdate($e->date_eval), 'day') : '—').' · /'.notes_fmt($e->note_max).(!empty($e->label) ? ' · '.dol_escape_htmltag(dol_trunc($e->label, 22)) : '').'</span>';
	print '<div class="nt-bar'.($pct >= 100 ? ' nt-bar-ok' : '').'"><span style="width:'.$pct.'%"></span></div>';
	print '<span class="nt-eval-sub">'.$s['nb'].' / '.$nbActuels.($s['moyenne'] !== null ? ' · '.$langs->trans('MoyenneCourt').' '.notes_fmt(round($s['moyenne'], 2)) : '').'</span>';
	print '</a>';
}
if ($peutSaisir && !$cloture) {
	print '<button type="button" class="nt-eval nt-eval-add" data-type="'.NOTES_DEVOIR.'">'.img_picto('', 'fa-plus', 'class="pictofixedwidth"').$langs->trans('NouveauDevoir').'</button>';
	if (!$aCompo) {
		print '<button type="button" class="nt-eval nt-eval-add" data-type="'.NOTES_COMPO.'">'.img_picto('', 'fa-plus', 'class="pictofixedwidth"').$langs->trans('NouvelleComposition').'</button>';
	}
}
print '</div>';
if ($peutSaisir && !$cloture) {
	print '<form method="POST" action="'.dol_escape_htmltag($self).'" class="nt-newform'.(empty($evals) ? ' open' : '').'" id="nt-newform">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="addeval">';
	print '<input type="hidden" name="fk_classe" value="'.$fk_classe.'"><input type="hidden" name="fk_matiere" value="'.$fk_matiere.'"><input type="hidden" name="trimestre" value="'.$trimestre.'">';
	$types = array(NOTES_DEVOIR => $langs->trans('Devoir'));
	if (!$aCompo) {
		$types[NOTES_COMPO] = $langs->trans('Composition');
	}
	print '<b>'.$langs->trans('NouvelleEvaluation').'</b> '.$form->selectarray('type', $types, NOTES_DEVOIR, 0, 0, 0, 'id="nt-newtype"', 0, 0, 0, '', 'minwidth100');
	print '<input type="date" name="date_eval" class="flat" value="'.dol_print_date(dol_now(), '%Y-%m-%d', 'tzuserrel').'" title="'.dol_escape_htmltag($langs->trans('DateEvaluation')).'">';
	print '<input type="text" name="label" class="flat minwidth200" maxlength="120" placeholder="'.dol_escape_htmltag($langs->trans('LibelleFacultatif')).'">';
	print '<input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('CreerEtSaisir')).'">';
	print '</form>';
}

if (empty($evals)) {
	print '<div class="opacitymedium marginbottomonly">'.$langs->trans($peutSaisir && !$cloture ? 'AucuneEvaluationCreer' : 'AucuneEvaluation').'</div>';
}
if (empty($eleves)) {
	print '<div class="opacitymedium">'.$langs->trans('AucunEleveClasse').'</div>';
	llxFooter();
	$db->close();
	exit;
}

$saisie = ($ev && $editable);
if ($saisie) {
	if ($action === 'deleteeval') {
		print $form->formconfirm($urlBase.'&eval='.((int) $ev->rowid), $langs->trans('SupprimerEvaluation'), $langs->trans('ConfirmSupprimerEvaluation', notes_evaluation_nom($ev)), 'confirm_deleteeval', '', 0, 1);
	}
	// Panneau de l'évaluation en cours : statistiques en direct, date / libellé / suppression
	print '<div class="nt-panel">';
	print '<div class="nt-stat"><b>'.dol_escape_htmltag(notes_evaluation_nom($ev)).'</b><span>'.$langs->trans('EnSaisie').' · /'.notes_fmt($ev->note_max).'</span></div>';
	print '<div class="nt-stat nt-stat-progress"><span>'.$langs->trans('NotesSaisies').' : <b id="nt-st-nb">0</b> / '.$nbActuels.'</span><div class="nt-bar nt-bar-big"><span id="nt-st-bar" style="width:0%"></span></div></div>';
	print '<div class="nt-stat"><b id="nt-st-moy">—</b><span>'.$langs->trans('MoyenneEvaluation').'</span></div>';
	print '<div class="nt-stat"><b id="nt-st-minmax">—</b><span>'.$langs->trans('MinMax').'</span></div>';
	print '<div class="nt-stat"><b id="nt-st-sous">0</b><span>'.$langs->trans('SousLaMoyenne').'</span></div>';
	print '<div class="nt-stat"><b id="nt-st-abs">0</b><span>'.$langs->trans('NoteAbsent').'</span></div>';
	print '<details class="nt-evalprops"><summary class="opacitymedium" style="cursor:pointer">'.img_picto('', 'fa-cog', 'class="pictofixedwidth"').$langs->trans('ModifierEvaluation').'</summary>';
	print '<form method="POST" action="'.dol_escape_htmltag($self).'" class="nt-evalprops" style="margin-top:6px">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="saveeval">';
	print '<input type="hidden" name="fk_classe" value="'.$fk_classe.'"><input type="hidden" name="fk_matiere" value="'.$fk_matiere.'"><input type="hidden" name="trimestre" value="'.$trimestre.'"><input type="hidden" name="eval" value="'.((int) $ev->rowid).'">';
	print '<input type="date" name="date_eval" class="flat" value="'.dol_escape_htmltag(substr((string) $ev->date_eval, 0, 10)).'">';
	print '<input type="text" name="label" class="flat minwidth200" maxlength="120" value="'.dol_escape_htmltag((string) $ev->label).'" placeholder="'.dol_escape_htmltag($langs->trans('LibelleFacultatif')).'">';
	print '<input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
	if (!$cloture) {
		print '<a class="button button-delete smallpaddingimp" href="'.dol_escape_htmltag($urlBase.'&eval='.((int) $ev->rowid).'&action=deleteeval&token='.newToken()).'">'.$langs->trans('Delete').'</a>';
	}
	if ($cloture) {
		print '<span class="butActionRefused classfortooltip smallpaddingimp" title="'.dol_escape_htmltag($langs->trans('ErrorTrimestreCloture')).'">'.$langs->trans('Delete').'</span>';
	}
	print '</form></details>';
	print '</div>';
	print '<div class="nt-keys"><span><kbd>'.$langs->trans('ToucheEntree').'</kbd> / <kbd>↓</kbd> '.$langs->trans('EleveSuivant').'</span><span><kbd>↑</kbd> '.$langs->trans('ElevePrecedent').'</span>';
	print '<span><kbd>a</kbd> '.$langs->trans('NoteAbsent').'</span><span><kbd>d</kbd> '.$langs->trans('NoteDispense').'</span><span><kbd>'.$langs->trans('ToucheEchap').'</kbd> '.$langs->trans('AnnulerCase').'</span>';
	print '<span>'.$langs->trans('AideCouleurs').'</span></div>';

	print '<form method="POST" action="'.dol_escape_htmltag($self).'" id="formnotes">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="savenotes">';
	print '<input type="hidden" name="fk_classe" value="'.$fk_classe.'"><input type="hidden" name="fk_matiere" value="'.$fk_matiere.'"><input type="hidden" name="trimestre" value="'.$trimestre.'"><input type="hidden" name="eval" value="'.((int) $ev->rowid).'">';
}

$postNotes = ($action === 'savenotes' && !empty($erreursNotes)) ? GETPOST('note', 'array') : null;
$postAbs = ($action === 'savenotes' && !empty($erreursNotes)) ? GETPOST('abs', 'array') : null;
$statuts = EcoleEleve::statusLabels();

// Grille : élèves × évaluations + moyenne du trimestre dans la matière
$etatsNote = array('vide' => $langs->trans('NonSaisie'), 'note' => $langs->trans('Notee'), 'abs' => $langs->trans('NoteAbsent'), 'disp' => $langs->trans('NoteDispense'), 'sous' => $langs->trans('SousLaMoyenne'));
$filtresGrille = array(null, 'text');
foreach ($evals as $e) {
	$filtresGrille[] = $etatsNote;
}
$filtresGrille[] = 'range';
print '<div class="nt-gridwrap"><table class="noborder centpercent nt-grid notes-grid notes-filtrable" id="nt-grid">';
print notes_filtres_ligne($filtresGrille);
print '<tr class="liste_titre"><td class="nt-num" title="'.dol_escape_htmltag($langs->trans('NumeroAppel')).'">'.$langs->trans('NumeroAppelCourt').'</td><td>'.$langs->trans('NomComplet').'</td>';
foreach ($evals as $eid => $e) {
	$enCours = $saisie && (int) $ev->rowid === $eid;
	print '<td class="center'.($enCours ? ' nt-colcur' : '').'"><b>'.dol_escape_htmltag(notes_evaluation_nom($e)).'</b><div class="notes-head-sub opacitymedium small">/'.notes_fmt($e->note_max).'</div></td>';
}
print '<td class="center" title="'.dol_escape_htmltag($langs->trans('MoyTrimestreAide')).'">'.$langs->trans('MoyTrimestre').'<div class="opacitymedium small">/20</div></td>';
print '</tr>';

foreach ($eleves as $elid => $el) {
	print '<tr class="oddeven'.($el->ancien ? ' notes-ancien' : '').'">';
	print '<td class="nt-num">'.($el->numero_appel && !$el->ancien ? '<span class="nt-num-badge">'.(int) $el->numero_appel.'</span>' : '').'</td>';
	print '<td class="nt-nom" title="'.dol_escape_htmltag($el->ref).'"><span class="nt-name" dir="auto">'.dol_escape_htmltag(ecole_label($el)).'</span>';
	if ($el->ancien) {
		$lib = ((int) $el->fk_classe !== $fk_classe && in_array((int) $el->status, EcoleEleve::statusOccupantPlace(), true)) ? $langs->trans('ChangeDeClasse') : (isset($statuts[(int) $el->status]) ? $langs->trans($statuts[(int) $el->status][0]) : '');
		print ' <span class="badge badge-status8 small">'.dol_escape_htmltag($lib).'</span>';
	} elseif ((int) $el->status === EcoleEleve::STATUS_SUSPENDU) {
		print ' <span class="badge badge-status7 small">'.$langs->trans('StatutSuspendu').'</span>';
	}
	print '</td>';
	foreach ($evals as $eid => $e) {
		$code = isset($valeurs[$eid][$elid]) ? $valeurs[$eid][$elid] : '';
		$enCours = $saisie && (int) $ev->rowid === $eid;
		$etatCell = ($code === '') ? 'vide' : ($code === NOTES_ABS ? 'abs' : ($code === NOTES_DISP ? 'disp' : 'note'.((float) $code < (float) $e->note_max / 2 ? ' sous' : '')));
		print '<td class="center nowraponall'.($enCours ? ' nt-colcur' : '').'" data-f="'.$etatCell.'">';
		if ($enCours) {
			$absSel = ($code === NOTES_ABS || $code === NOTES_DISP) ? $code : '';
			$txt = ($absSel === '' && $code !== '') ? notes_fmt($code) : '';
			$orig = $absSel !== '' ? $absSel : $txt;
			if ($postNotes !== null) {
				$txt = isset($postNotes[$elid]) ? (string) $postNotes[$elid] : '';
				$absSel = isset($postAbs[$elid]) ? (string) $postAbs[$elid] : '';
			}
			$err = isset($erreursNotes[$elid]) ? $erreursNotes[$elid] : '';
			print '<span class="nt-cell" data-orig="'.dol_escape_htmltag($orig).'">';
			print '<input type="text" inputmode="decimal" autocomplete="off" name="note['.$elid.']" class="flat nt-in notes-in'.($err ? ' nt-err' : '').'" data-max="'.((float) $ev->note_max).'" value="'.dol_escape_htmltag($txt).'"'.($err ? ' title="'.dol_escape_htmltag($err).'"' : '').($absSel !== '' ? ' disabled' : '').'>';
			print '<input type="hidden" name="abs['.$elid.']" class="nt-absval" value="'.dol_escape_htmltag($absSel).'">';
			print '<button type="button" tabindex="-1" class="nt-tog nt-tog-abs'.($absSel === NOTES_ABS ? ' on' : '').'" data-v="'.NOTES_ABS.'" title="'.dol_escape_htmltag($langs->trans('NoteAbsent')).'">'.$langs->trans('NoteAbsentCourt').'</button>';
			print '<button type="button" tabindex="-1" class="nt-tog nt-tog-disp'.($absSel === NOTES_DISP ? ' on' : '').'" data-v="'.NOTES_DISP.'" title="'.dol_escape_htmltag($langs->trans('NoteDispense')).'">'.$langs->trans('NoteDispenseCourt').'</button>';
			print '</span>';
			if ($err) {
				print '<span class="nt-errmsg">'.dol_escape_htmltag($err).'</span>';
			}
		} else {
			print notes_cellule($code, (float) $e->note_max);
		}
		print '</td>';
	}
	$moyT = isset($resultats['res'][$elid]['matieres'][$fk_matiere]) ? $resultats['res'][$elid]['matieres'][$fk_matiere]['moyenne'] : null;
	print '<td class="center nt-moycol" data-f="'.($moyT !== null ? round($moyT, 2) : '').'">'.($moyT !== null ? notes_cellule(notes_code(round($moyT, 2), ''), 20.0, notes_moy($moyT)) : '<span class="nt-vide">·</span>').'</td>';
	print '</tr>';
}

// Bas de colonnes : notes saisies, moyenne de la classe à chaque évaluation
if (!empty($evals)) {
	print '<tr class="liste_total"><td></td><td>'.$langs->trans('NotesSaisies').'</td>';
	foreach ($evals as $eid => $e) {
		$s = notes_stats(isset($valeurs[$eid]) ? $valeurs[$eid] : array());
		print '<td class="center">'.$s['nb'].' / '.$nbActuels.($s['abs'] ? ' <span class="nt-pill nt-abs">'.$s['abs'].' '.$langs->trans('NoteAbsentCourt').'</span>' : '').'</td>';
	}
	print '<td></td></tr><tr class="liste_total"><td></td><td>'.$langs->trans('MoyenneEvaluation').'</td>';
	foreach ($evals as $eid => $e) {
		$s = notes_stats(isset($valeurs[$eid]) ? $valeurs[$eid] : array());
		print '<td class="center">'.($s['moyenne'] !== null ? notes_fmt(round($s['moyenne'], 2)).' / '.notes_fmt($e->note_max) : '—').'</td>';
	}
	$sm = isset($resultats['stats']['matieres'][$fk_matiere]) ? $resultats['stats']['matieres'][$fk_matiere]['moy'] : null;
	print '<td class="center"><b>'.notes_moy($sm).'</b></td></tr>';
}
print '</table></div>';

if ($saisie) {
	// Barre d'enregistrement (toujours visible en bas de l'écran)
	print '<div class="nt-savebar">';
	if ($cloture) {
		print '<div class="nt-motif"><b>'.$langs->trans('MotifCorrection').'</b><input type="text" name="motif" class="flat" maxlength="250" required value="'.dol_escape_htmltag(GETPOST('motif', 'alphanohtml')).'" placeholder="'.dol_escape_htmltag($langs->trans('MotifCorrectionAide')).'"></div>';
	}
	print '<span class="nt-dirty nt-clean" id="nt-dirty">'.$langs->trans('AucuneModification').'</span>';
	print '<span><a class="button button-cancel" href="'.dol_escape_htmltag($urlBase.'#grille').'">'.$langs->trans('Cancel').'</a> ';
	print '<button type="submit" class="button button-save">'.img_picto('', 'fa-check', 'class="pictofixedwidth"').$langs->trans('EnregistrerNotes', dol_escape_htmltag(notes_evaluation_nom($ev))).'</button></span>';
	print '</div>';
	print '</form>';
} elseif (!empty($evals) && $peutSaisir && $cloture && !$peutCorriger) {
	print '<br><span class="opacitymedium">'.$langs->trans('TrimestreClotureLectureSeule').'</span>';
} elseif (!empty($evals) && $editable) {
	print '<br><span class="opacitymedium">'.img_picto('', 'fa-info-circle', 'class="pictofixedwidth"').$langs->trans('AideChoisirEvaluation').'</span>';
}

$libModifs = dol_escape_js($langs->transnoentities('ModificationsNonEnregistrees', '__N__'), 2);
$libAucune = dol_escape_js($langs->transnoentities('AucuneModification'), 2);
$sep = dol_escape_js($langs->transnoentitiesnoconv('SeparatorDecimal') !== 'SeparatorDecimal' ? $langs->transnoentitiesnoconv('SeparatorDecimal') : ',', 2);
print '<script>
(function () {
	// Création d\'une évaluation : les boutons « + Devoir » / « + Composition » ouvrent le formulaire
	var nf = document.getElementById("nt-newform");
	document.querySelectorAll(".nt-eval-add").forEach(function (b) {
		b.addEventListener("click", function () {
			if (!nf) { return; }
			nf.classList.add("open");
			var sel = document.getElementById("nt-newtype");
			if (sel) { sel.value = b.getAttribute("data-type"); if (window.jQuery) { jQuery(sel).trigger("change"); } }
			var lab = nf.querySelector("input[name=label]");
			if (lab) { lab.focus(); }
		});
	});
	// Hauteur de la ligne de filtres (titres collés en haut de la grille pendant le défilement)
	var g = document.getElementById("nt-grid");
	if (g) {
		var fr = g.querySelector("tr.liste_titre_filter");
		if (fr) { g.style.setProperty("--nt-filter-h", fr.getBoundingClientRect().height + "px"); }
	}
	var form = document.getElementById("formnotes");
	if (!form) { return; }
	var ins = Array.prototype.slice.call(form.querySelectorAll(".nt-in"));
	var SEP = "'.$sep.'";
	function fmt(v) { return (Math.round(v * 100) / 100).toFixed(2).replace(".", SEP); }
	function parse(s) {
		s = (s || "").replace(",", ".").replace(/\s/g, "");
		if (s === "") { return ""; }
		return /^\d+(\.\d{1,2})?$/.test(s) ? parseFloat(s) : NaN;
	}
	function etat(inp) {
		var cell = inp.parentNode, abs = cell.querySelector(".nt-absval").value;
		return abs !== "" ? abs : inp.value.trim();
	}
	function colorer(inp) {
		var v = parse(inp.value), max = parseFloat(inp.getAttribute("data-max")) || 20;
		inp.classList.remove("nt-bas", "nt-moy", "nt-bon", "nt-err", "notes-err");
		if (inp.disabled || v === "") { return; }
		if (isNaN(v) || v > max) { inp.classList.add("nt-err"); return; }
		var r = v / max;
		inp.classList.add(r < 0.5 ? "nt-bas" : (r < 0.7 ? "nt-moy" : "nt-bon"));
	}
	function maj() {
		var nb = 0, tot = 0, somme = 0, n = 0, sous = 0, abs = 0, min = null, max = null, modifs = 0;
		ins.forEach(function (inp) {
			var tr = inp.closest("tr"), cell = inp.parentNode;
			var a = cell.querySelector(".nt-absval").value, m = parseFloat(inp.getAttribute("data-max")) || 20;
			if (etat(inp) !== cell.getAttribute("data-orig")) { modifs++; inp.classList.add("nt-mod"); } else { inp.classList.remove("nt-mod"); }
			if (tr.classList.contains("notes-ancien")) { return; }
			tot++;
			if (a === "ABS") { nb++; abs++; return; }
			if (a === "DISP") { nb++; return; }
			var v = parse(inp.value);
			if (v === "" || isNaN(v) || v > m) { return; }
			nb++; n++; somme += v; sous += (v < m / 2) ? 1 : 0;
			min = (min === null) ? v : Math.min(min, v); max = (max === null) ? v : Math.max(max, v);
		});
		document.getElementById("nt-st-nb").textContent = nb;
		document.getElementById("nt-st-bar").style.width = (tot ? Math.round(nb / tot * 100) : 0) + "%";
		document.getElementById("nt-st-bar").parentNode.classList.toggle("nt-bar-ok", tot > 0 && nb >= tot);
		document.getElementById("nt-st-moy").textContent = n ? fmt(somme / n) : "—";
		document.getElementById("nt-st-minmax").textContent = n ? fmt(min) + " – " + fmt(max) : "—";
		document.getElementById("nt-st-sous").textContent = sous;
		document.getElementById("nt-st-abs").textContent = abs;
		var d = document.getElementById("nt-dirty");
		d.textContent = modifs ? "'.$libModifs.'".replace("__N__", modifs) : "'.$libAucune.'";
		d.classList.toggle("nt-clean", !modifs);
		form.dataset.dirty = modifs ? "1" : "";
	}
	function absence(inp, v) {
		var cell = inp.parentNode, h = cell.querySelector(".nt-absval");
		h.value = (h.value === v) ? "" : v;
		cell.querySelectorAll(".nt-tog").forEach(function (b) { b.classList.toggle("on", b.getAttribute("data-v") === h.value); });
		inp.disabled = h.value !== "";
		if (h.value !== "") { inp.value = ""; }
		colorer(inp); maj();
	}
	function aller(i, dir) {
		var j = i + dir;
		while (ins[j] && ins[j].disabled) { j += dir; }
		if (ins[j]) { ins[j].focus(); ins[j].select(); }
	}
	ins.forEach(function (inp, i) {
		inp.addEventListener("keydown", function (ev) {
			if (ev.key === "Enter" || ev.key === "ArrowDown") { ev.preventDefault(); aller(i, 1); }
			else if (ev.key === "ArrowUp") { ev.preventDefault(); aller(i, -1); }
			else if (ev.key === "Escape") {
				// Annule la modification de cette case
				var cell = inp.parentNode, o = cell.getAttribute("data-orig");
				var h = cell.querySelector(".nt-absval");
				h.value = (o === "ABS" || o === "DISP") ? o : "";
				inp.value = (o === "ABS" || o === "DISP") ? "" : o;
				inp.disabled = h.value !== "";
				cell.querySelectorAll(".nt-tog").forEach(function (b) { b.classList.toggle("on", b.getAttribute("data-v") === h.value); });
				colorer(inp); maj();
			}
		});
		inp.addEventListener("input", function () {
			var v = inp.value.trim().toLowerCase();
			if (v === "a" || v === "d") { inp.value = ""; absence(inp, v === "a" ? "ABS" : "DISP"); aller(i, 1); return; }
			colorer(inp); maj();
		});
		inp.addEventListener("focus", function () { inp.closest("tr").classList.add("nt-row-focus"); });
		inp.addEventListener("blur", function () { inp.closest("tr").classList.remove("nt-row-focus"); });
		inp.parentNode.querySelectorAll(".nt-tog").forEach(function (b) {
			b.addEventListener("click", function () { absence(inp, b.getAttribute("data-v")); if (!inp.disabled) { inp.focus(); } });
		});
		colorer(inp);
	});
	form.addEventListener("submit", function () {
		form.dataset.dirty = "";
		ins.forEach(function (inp) { inp.disabled = false; }); // un champ désactivé ne serait pas envoyé
	});
	window.addEventListener("beforeunload", function (ev) { if (form.dataset.dirty) { ev.preventDefault(); ev.returnValue = ""; } });
	maj();
	var first = ins.filter(function (x) { return !x.disabled && x.value === ""; })[0] || ins[0];
	if (first) { first.focus(); }
})();
</script>';

llxFooter();
$db->close();

/**
 * Cellule d'une note (hors saisie) : pastille colorée selon la note (rouge sous la moitié, orange, vert).
 *
 * @param  string $code  Code de la note
 * @param  float  $max   Maximum de la note
 * @param  string $texte Texte affiché (facultatif, sinon la note)
 * @return string
 */
function notes_cellule($code, $max = 20.0, $texte = '')
{
	global $langs;
	if ($code === NOTES_ABS) {
		return '<span class="nt-pill nt-abs" title="'.dol_escape_htmltag($langs->trans('NoteAbsent')).'">'.$langs->trans('NoteAbsentCourt').'</span>';
	}
	if ($code === NOTES_DISP) {
		return '<span class="nt-pill nt-disp" title="'.dol_escape_htmltag($langs->trans('NoteDispense')).'">'.$langs->trans('NoteDispenseCourt').'</span>';
	}
	if ($code === '') {
		return '<span class="nt-vide">·</span>';
	}
	$r = (float) $code / max(0.01, (float) $max);
	return '<span class="nt-pill '.($r < 0.5 ? 'nt-bas' : ($r < 0.7 ? 'nt-moy' : 'nt-bon')).'">'.($texte !== '' ? $texte : notes_fmt($code)).'</span>';
}

/**
 * Barre de recherche des cartes (classes ou matières) : texte + choix d'un état.
 *
 * @param  array  $etats       valeur => libellé ('' = tout)
 * @param  string $placeholder Clé de traduction du champ de recherche
 * @return void
 */
function notes_saisie_filtres($etats, $placeholder)
{
	global $langs;
	print '<div class="nt-filterbar"><input type="search" id="nt-q" placeholder="&#128269; '.dol_escape_htmltag($langs->trans($placeholder)).'">';
	foreach ($etats as $v => $l) {
		print '<span class="nt-chip'.($v === '' ? ' on' : '').'" data-etat="'.dol_escape_htmltag($v).'">'.dol_escape_htmltag($l).'</span>';
	}
	print '<span class="nt-count" id="nt-count"></span></div>';
}

/**
 * Script de la barre de recherche des cartes.
 *
 * @return void
 */
function notes_saisie_filtres_js()
{
	global $langs;
	$lib = dol_escape_js($langs->transnoentities('NbLignesAffichees', '__A__', '__B__'), 2);
	print '<script>
(function () {
	var q = document.getElementById("nt-q"), etat = "", cards = document.querySelectorAll(".nt-card");
	function norm(s) { return (s || "").toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, ""); }
	function appliquer() {
		var t = norm(q.value), vus = 0;
		cards.forEach(function (c) {
			var ok = norm(c.getAttribute("data-search")).indexOf(t) !== -1 && (etat === "" || (" " + c.getAttribute("data-etat") + " ").indexOf(" " + etat + " ") !== -1);
			c.style.display = ok ? "" : "none";
			vus += ok ? 1 : 0;
		});
		document.getElementById("nt-count").textContent = (vus < cards.length) ? "'.$lib.'".replace("__A__", vus).replace("__B__", cards.length) : "";
	}
	q.addEventListener("input", appliquer);
	document.querySelectorAll(".nt-chip").forEach(function (ch) {
		ch.addEventListener("click", function () {
			document.querySelectorAll(".nt-chip").forEach(function (x) { x.classList.remove("on"); });
			ch.classList.add("on");
			etat = ch.getAttribute("data-etat");
			appliquer();
		});
	});
})();
</script>';
}
