<?php
/**
 * Jeux de données des exports PDF / Excel du module Notes (mêmes contenus que les listes affichées).
 * Chaque fonction renvoie : title, subtitle, ref, headers, ratios, aligns, rows, filename.
 * Textes dans la langue de l'export (la langue globale a déjà été changée par export.php).
 *
 * Fichier : custom/notes/core/lib/exports.lib.php
 */

dol_include_once('/notes/core/lib/resultats.lib.php');

/**
 * Jeu de données standard.
 *
 * @param  string $title    Titre
 * @param  string $subtitle Sous-titre
 * @param  string $ref      Référence
 * @param  array  $cols     Colonnes : array(titre, largeur relative, alignement)
 * @param  array  $rows     Lignes
 * @param  string $filename Nom du fichier (sans extension)
 * @return array
 */
function notes_ds($title, $subtitle, $ref, $cols, $rows, $filename)
{
	global $langs;
	$ds = array('title' => $title, 'subtitle' => trim($subtitle.' — '.ecole_pdf_trans($langs, 'NbEnregistrements', count($rows)), ' —'), 'ref' => $ref,
		'headers' => array(), 'ratios' => array(), 'aligns' => array(), 'rows' => $rows, 'filename' => $filename);
	foreach ($cols as $c) {
		$ds['headers'][] = $c[0];
		$ds['ratios'][] = $c[1];
		$ds['aligns'][] = $c[2];
	}
	return $ds;
}

/**
 * Texte d'une note pour un export : « 12,5 », « Abs », « Disp » ou vide.
 *
 * @param  string $code Code
 * @return string
 */
function notes_export_code($code)
{
	global $langs;
	if ($code === NOTES_ABS) {
		return ecole_pdf_trans($langs, 'NoteAbsentCourt');
	}
	if ($code === NOTES_DISP) {
		return ecole_pdf_trans($langs, 'NoteDispenseCourt');
	}
	return ($code === '' || $code === null) ? '' : notes_fmt($code);
}

/**
 * Historique des notes (mêmes filtres que la page).
 *
 * @param  DoliDB $db   Handler base
 * @param  User   $user Utilisateur
 * @param  array  $f    Filtres
 * @return array
 */
function notes_dataset_historique($db, $user, $f)
{
	global $langs;
	$total = 0;
	$rows = array();
	foreach (notes_historique($db, $user, $f, 100000, 0, $total) as $l) {
		$rows[] = array(dol_print_date($db->jdate($l->date_creation), 'dayhour', 'tzuserrel'), ecole_user_label($db, $l->fk_user), (string) $l->cref,
			ecole_label((object) array('label_fr' => $l->mlabel_fr, 'label_ar' => $l->mlabel_ar)), ecole_pdf_text(notes_evaluation_nom($l)).' - '.ecole_pdf_text(notes_trimestre_label($l->trimestre)).((int) $l->vstatus === 0 ? ' ('.ecole_pdf_trans($langs, 'EvaluationSupprimeeCourt').')' : ''),
			ecole_label($l), ecole_pdf_text(notes_code_label((string) $l->avant)), ecole_pdf_text(notes_code_label((string) $l->apres)).' /'.notes_fmt($l->note_max), (string) $l->motif);
	}
	return notes_ds(ecole_pdf_trans($langs, 'HistoriqueNotes'), '', 'HIST-'.dol_print_date(dol_now(), '%Y%m%d'), array(
		array(ecole_pdf_trans($langs, 'Date'), 1.3, 'C'), array(ecole_pdf_trans($langs, 'User'), 1.3, 'L'), array(ecole_pdf_trans($langs, 'Classe'), 0.7, 'C'),
		array(ecole_pdf_trans($langs, 'Matiere'), 1.6, 'L'), array(ecole_pdf_trans($langs, 'Evaluation'), 1.7, 'L'), array(ecole_pdf_trans($langs, 'Eleve'), 2, 'L'),
		array(ecole_pdf_trans($langs, 'Avant'), 0.8, 'C'), array(ecole_pdf_trans($langs, 'Apres'), 0.9, 'C'), array(ecole_pdf_trans($langs, 'Motif'), 2, 'L'),
	), $rows, 'historique_notes');
}

/**
 * Clôture des trimestres : avancement et état de chaque classe.
 *
 * @param  DoliDB $db        Handler base
 * @param  User   $user      Utilisateur
 * @param  int    $trimestre Trimestre
 * @return array
 */
function notes_dataset_cloture($db, $user, $trimestre)
{
	global $langs;
	$p = $db->prefix();
	$av = array();
	$resql = $db->query("SELECT v.fk_classe, v.type, (SELECT COUNT(*) FROM ".$p."ecole_note n WHERE n.fk_evaluation = v.rowid) as nb FROM ".$p."ecole_evaluation v WHERE v.status = 1 AND v.trimestre = ".((int) $trimestre).ecole_annee_sql('v.annee'));
	while ($resql && ($o = $db->fetch_object($resql))) {
		$c = (int) $o->fk_classe;
		if (!isset($av[$c])) {
			$av[$c] = array('devoirs' => 0, 'compos' => 0, 'notes' => 0);
		}
		if ((int) $o->type === NOTES_COMPO) {
			$av[$c]['compos']++;
			$av[$c]['notes'] += (int) $o->nb;
		} else {
			$av[$c]['devoirs']++;
		}
	}
	$clotures = notes_clotures($db);
	$rows = array();
	foreach (notes_classes($db, $user) as $cid => $c) {
		$nbE = count(notes_calc_eleves($db, $cid));
		$nbM = count(notes_matieres_classe($db, null, $cid));
		$a = isset($av[$cid]) ? $av[$cid] : array('devoirs' => 0, 'compos' => 0, 'notes' => 0);
		$cl = isset($clotures[$cid.'|'.$trimestre]) ? $clotures[$cid.'|'.$trimestre] : null;
		$etat = ($cl && (int) $cl->status === 1) ? ecole_pdf_trans($langs, 'Cloture').' ('.dol_print_date($db->jdate($cl->date_cloture), 'day').')' : ecole_pdf_trans($langs, 'Ouvert');
		$rows[] = array($c->ref.' - '.ecole_label($c), (string) $nbE, (string) $a['devoirs'], $a['compos'].' / '.$nbM, (string) max(0, $nbE * $nbM - $a['notes']), $etat);
	}
	return notes_ds(ecole_pdf_trans($langs, 'ClotureTrimestres'), ecole_pdf_text(notes_trimestre_label($trimestre)), 'CLO-T'.((int) $trimestre), array(
		array(ecole_pdf_trans($langs, 'Classe'), 2.6, 'L'), array(ecole_pdf_trans($langs, 'NbElevesCol'), 0.8, 'C'), array(ecole_pdf_trans($langs, 'Devoirs'), 0.8, 'C'),
		array(ecole_pdf_trans($langs, 'CompositionsSaisies'), 1.2, 'C'), array(ecole_pdf_trans($langs, 'NotesCompoManquantes'), 1.3, 'C'), array(ecole_pdf_trans($langs, 'Etat'), 1.4, 'C'),
	), $rows, 'cloture_T'.((int) $trimestre));
}

/**
 * Suivi des résultats de toutes les classes pour une période.
 *
 * @param  DoliDB $db      Handler base
 * @param  User   $user    Utilisateur
 * @param  int    $periode 0 à 3
 * @return array
 */
function notes_dataset_suivi_resultats($db, $user, $periode)
{
	global $langs;
	$seuil = (float) getDolGlobalString('NOTES_SEUIL_DIFFICULTE', '10');
	$rows = array();
	foreach (notes_classes_resultats($db, $user) as $cid => $c) {
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
		$rows[] = array($c->ref.' - '.ecole_label($c), $sg['nb'].' / '.count($calc['eleves']), notes_moy($sg['moy']), notes_moy($sg['max']), notes_moy($sg['min']),
			$sg['nb'] > 0 ? round($reussis / $sg['nb'] * 100).' %' : '', (string) $diff, ecole_pdf_trans($langs, notes_periode_close($db, $cid, $periode) ? 'Cloture' : 'Ouvert'));
	}
	return notes_ds(ecole_pdf_trans($langs, 'Resultats'), ecole_pdf_text(notes_periode_label($periode)), 'RES-'.((int) $periode === 0 ? 'A' : 'T'.((int) $periode)), array(
		array(ecole_pdf_trans($langs, 'Classe'), 2.6, 'L'), array(ecole_pdf_trans($langs, 'ElevesClasses'), 1, 'C'), array(ecole_pdf_trans($langs, 'MoyenneClasse'), 1, 'C'),
		array(ecole_pdf_trans($langs, 'PlusForte'), 1, 'C'), array(ecole_pdf_trans($langs, 'PlusFaible'), 1, 'C'), array(ecole_pdf_trans($langs, 'TauxReussite'), 1, 'C'),
		array(ecole_pdf_trans($langs, 'EnDifficulte'), 0.9, 'C'), array(ecole_pdf_trans($langs, 'Etat'), 0.9, 'C'),
	), $rows, 'resultats_classes_'.((int) $periode === 0 ? 'annee' : 'T'.((int) $periode)));
}

/**
 * Saisie : avancement de toutes les classes (devoirs et compositions de chaque trimestre).
 *
 * @param  DoliDB $db   Handler base
 * @param  User   $user Utilisateur
 * @return array
 */
function notes_dataset_saisie_classes($db, $user)
{
	global $langs;
	$p = $db->prefix();
	$suivi = array();
	$resql = $db->query("SELECT fk_classe, trimestre, type, COUNT(*) as nb FROM ".$p."ecole_evaluation WHERE status = 1".ecole_annee_sql()." GROUP BY fk_classe, trimestre, type");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$suivi[(int) $o->fk_classe][(int) $o->trimestre][(int) $o->type] = (int) $o->nb;
	}
	$clotures = notes_clotures($db);
	$rows = array();
	foreach (notes_classes($db, $user) as $cid => $c) {
		$nbM = count(notes_matieres_classe($db, $user, $cid));
		$row = array($c->ref.' - '.ecole_label($c), (string) count(notes_calc_eleves($db, $cid)), (string) $nbM);
		for ($t = 1; $t <= 3; $t++) {
			$d = isset($suivi[$cid][$t][NOTES_DEVOIR]) ? $suivi[$cid][$t][NOTES_DEVOIR] : 0;
			$k = isset($suivi[$cid][$t][NOTES_COMPO]) ? $suivi[$cid][$t][NOTES_COMPO] : 0;
			$closed = isset($clotures[$cid.'|'.$t]) && (int) $clotures[$cid.'|'.$t]->status === 1;
			$row[] = ecole_pdf_trans($langs, 'SuiviDevoirsCompos', $d, $k, $nbM).($closed ? ' - '.ecole_pdf_trans($langs, 'Cloture') : '');
		}
		$rows[] = $row;
	}
	return notes_ds(ecole_pdf_trans($langs, 'SaisieNotes'), '', 'SAI-'.dol_print_date(dol_now(), '%Y%m%d'), array(
		array(ecole_pdf_trans($langs, 'Classe'), 2.6, 'L'), array(ecole_pdf_trans($langs, 'NbElevesCol'), 0.8, 'C'), array(ecole_pdf_trans($langs, 'Matieres'), 0.8, 'C'),
		array(ecole_pdf_text(notes_trimestre_label(1)), 1.6, 'C'), array(ecole_pdf_text(notes_trimestre_label(2)), 1.6, 'C'), array(ecole_pdf_text(notes_trimestre_label(3)), 1.6, 'C'),
	), $rows, 'saisie_classes');
}

/**
 * Saisie : matières d'une classe pour un trimestre (coefficient, enseignant, devoirs, composition).
 *
 * @param  DoliDB $db        Handler base
 * @param  User   $user      Utilisateur
 * @param  object $classe    Classe
 * @param  int    $trimestre Trimestre
 * @return array
 */
function notes_dataset_saisie_matieres($db, $user, $classe, $trimestre)
{
	global $langs;
	$p = $db->prefix();
	$cid = (int) $classe->rowid;
	$regle = notes_regle_classe($db, $cid);
	$choix = notes_regle_choix();
	$exceptions = notes_exceptions_classe($db, $cid);
	$nbEl = count(notes_calc_eleves($db, $cid));
	$par = array();
	$resql = $db->query("SELECT v.fk_matiere, v.type, (SELECT COUNT(*) FROM ".$p."ecole_note n WHERE n.fk_evaluation = v.rowid) as nb FROM ".$p."ecole_evaluation v WHERE v.fk_classe = ".$cid." AND v.trimestre = ".((int) $trimestre)." AND v.status = 1".ecole_annee_sql('v.annee'));
	while ($resql && ($o = $db->fetch_object($resql))) {
		$m = (int) $o->fk_matiere;
		if ((int) $o->type === NOTES_COMPO) {
			$par[$m]['compo'] = (int) $o->nb;
		} else {
			$par[$m]['devoirs'] = (isset($par[$m]['devoirs']) ? $par[$m]['devoirs'] : 0) + 1;
		}
	}
	$rows = array();
	foreach (notes_matieres_classe($db, $user, $cid) as $mid => $m) {
		$calc = isset($exceptions[$mid]) ? $exceptions[$mid] : $regle['calcul_devoirs'];
		$rows[] = array(ecole_label($m), notes_fmt($m->coefficient), notes_fmt($m->note_max), implode(', ', $m->enseignants), ecole_pdf_trans($langs, $choix['calcul_devoirs'][$calc]),
			(string) (isset($par[$mid]['devoirs']) ? $par[$mid]['devoirs'] : 0), isset($par[$mid]['compo']) ? $par[$mid]['compo'].' / '.$nbEl : ecole_pdf_trans($langs, 'PasEncore'));
	}
	return notes_ds(ecole_pdf_trans($langs, 'SaisieNotes').' - '.$classe->ref, ecole_label($classe).' — '.ecole_pdf_text(notes_trimestre_label($trimestre)), 'SAI-'.$classe->ref.'-T'.((int) $trimestre), array(
		array(ecole_pdf_trans($langs, 'Matiere'), 2.4, 'L'), array(ecole_pdf_trans($langs, 'Coefficient'), 0.8, 'C'), array(ecole_pdf_trans($langs, 'NoteSur'), 0.8, 'C'),
		array(ecole_pdf_trans($langs, 'Enseignant'), 1.8, 'L'), array(ecole_pdf_trans($langs, 'NoteDevoirs'), 1.5, 'C'), array(ecole_pdf_trans($langs, 'Devoirs'), 0.7, 'C'),
		array(ecole_pdf_trans($langs, 'Composition'), 1, 'C'),
	), $rows, 'matieres_'.$classe->ref.'_T'.((int) $trimestre));
}

/**
 * Grille des notes d'une matière d'une classe pour un trimestre : chaque évaluation, moyenne du trimestre.
 * Sans évaluation : liste des élèves avec des colonnes vides (fiche de notes à remplir à la main).
 *
 * @param  DoliDB $db         Handler base
 * @param  object $classe     Classe
 * @param  int    $fk_matiere Matière
 * @param  int    $trimestre  Trimestre
 * @return array
 */
function notes_dataset_grille($db, $classe, $fk_matiere, $trimestre)
{
	global $langs;
	$cid = (int) $classe->rowid;
	$mats = notes_matieres_classe($db, null, $cid);
	$mat = isset($mats[$fk_matiere]) ? $mats[$fk_matiere] : null;
	$evals = notes_evaluations($db, $cid, $fk_matiere, $trimestre);
	$eleves = notes_eleves($db, $cid, array_keys($evals));
	$valeurs = notes_valeurs($db, array_keys($evals));
	$calc = notes_calcul_trimestre($db, $cid, $trimestre);
	$cols = array(array(ecole_pdf_trans($langs, 'NumeroAppelCourt'), 0.5, 'C'), array(ecole_pdf_trans($langs, 'NomComplet'), 3, 'L'));
	foreach ($evals as $e) {
		$cols[] = array(ecole_pdf_text(notes_evaluation_nom($e)).' /'.notes_fmt($e->note_max), 1, 'C');
	}
	if (empty($evals)) {
		foreach (array(1, 2, 3) as $n) {
			$cols[] = array(ecole_pdf_trans($langs, 'DevoirNum', $n), 1, 'C');
		}
		$cols[] = array(ecole_pdf_trans($langs, 'Composition'), 1, 'C');
	}
	$cols[] = array(ecole_pdf_trans($langs, 'MoyTrimestre').' /20', 1.1, 'C');
	$rows = array();
	foreach ($eleves as $eid => $el) {
		$row = array($el->numero_appel && !$el->ancien ? (string) $el->numero_appel : '', ecole_label($el));
		foreach ($evals as $evid => $e) {
			$row[] = notes_export_code(isset($valeurs[$evid][$eid]) ? $valeurs[$evid][$eid] : '');
		}
		if (empty($evals)) {
			array_push($row, '', '', '', '');
		}
		$moy = isset($calc['res'][$eid]['matieres'][$fk_matiere]) ? $calc['res'][$eid]['matieres'][$fk_matiere]['moyenne'] : null;
		$row[] = $moy !== null ? notes_moy($moy) : '';
		$rows[] = $row;
	}
	$lib = $mat ? ecole_label($mat) : '';
	return notes_ds($lib.' - '.$classe->ref, ecole_label($classe).' — '.ecole_pdf_text(notes_trimestre_label($trimestre)).($mat ? ' — '.ecole_pdf_trans($langs, 'CoefficientNoteSur', notes_fmt($mat->coefficient), notes_fmt($mat->note_max)) : ''),
		'NOT-'.$classe->ref.'-T'.((int) $trimestre), $cols, $rows, 'notes_'.$classe->ref.'_'.preg_replace('/[^A-Za-z0-9]/', '', $mat ? $mat->label_fr : 'matiere').'_T'.((int) $trimestre));
}

/**
 * Conseil de classe : rang, moyenne, mention, distinction, décision (année) et observation de chaque élève.
 *
 * @param  DoliDB $db      Handler base
 * @param  object $classe  Classe
 * @param  int    $periode 0 à 3
 * @return array
 */
function notes_dataset_conseil($db, $classe, $periode)
{
	global $langs;
	$cid = (int) $classe->rowid;
	$calc = notes_calcul($db, $cid, $periode);
	$conseil = notes_conseil($db, $cid, $periode);
	$avecDecision = ((int) $periode === 0 && $calc['regle']['decision'] !== 'aucune');
	$cols = array(array(ecole_pdf_trans($langs, 'Rang'), 0.7, 'C'), array(ecole_pdf_trans($langs, 'Eleve'), 2.6, 'L'), array(ecole_pdf_trans($langs, (int) $periode === 0 ? 'MoyenneAnnuelle' : 'MoyenneGenerale'), 1, 'C'),
		array(ecole_pdf_trans($langs, 'Mention'), 1.2, 'C'), array(ecole_pdf_trans($langs, 'Distinction'), 1.4, 'C'));
	if ($avecDecision) {
		$cols[] = array(ecole_pdf_trans($langs, 'DecisionFinAnneeCourt'), 1.3, 'C');
	}
	$cols[] = array(ecole_pdf_trans($langs, 'ObservationDirection'), 3, 'L');
	$rows = array();
	foreach (notes_ordre_rang($calc) as $eid) {
		$r = $calc['res'][$eid];
		$c = isset($conseil[$eid]) ? $conseil[$eid] : null;
		$mention = notes_mention($db, $r['moyenne']);
		$dist = notes_distinction($db, $c, $r['moyenne']);
		$row = array(ecole_pdf_text(notes_rang_label($r['rang'], $r['exaequo'])), ecole_label($calc['eleves'][$eid]), notes_moy($r['moyenne']), $mention ? ecole_label($mention) : '', $dist ? ecole_label($dist) : '');
		if ($avecDecision) {
			$dec = notes_decision($calc['regle'], $c, $r['moyenne']);
			$row[] = $dec !== '' ? ecole_pdf_trans($langs, notes_decisions()[$dec]) : '';
		}
		$row[] = $c ? (string) $c->observation : '';
		$rows[] = $row;
	}
	return notes_ds(ecole_pdf_trans($langs, 'ConseilDeClasse').' - '.$classe->ref, ecole_label($classe).' — '.ecole_pdf_text(notes_periode_label($periode)),
		'CONS-'.$classe->ref.'-'.((int) $periode === 0 ? 'A' : 'T'.((int) $periode)), $cols, $rows, 'conseil_'.$classe->ref.'_'.((int) $periode === 0 ? 'annee' : 'T'.((int) $periode)));
}

/**
 * Notes d'un élève pour une période (onglet « Notes » de la fiche élève).
 *
 * @param  DoliDB     $db      Handler base
 * @param  EcoleEleve $eleve   Élève
 * @param  int        $periode 0 à 3
 * @return array
 */
function notes_dataset_eleve($db, $eleve, $periode)
{
	global $langs;
	$cid = (int) $eleve->fk_classe;
	$calc = notes_calcul($db, $cid, $periode);
	$r = isset($calc['res'][(int) $eleve->id]) ? $calc['res'][(int) $eleve->id] : null;
	$rows = array();
	if ((int) $periode === 0) {
		$cols = array(array(ecole_pdf_trans($langs, 'Matiere'), 2.6, 'L'), array(ecole_pdf_trans($langs, 'Coefficient'), 0.8, 'C'));
		for ($t = 1; $t <= 3; $t++) {
			$cols[] = array(ecole_pdf_trans($langs, 'Trimestre'.$t), 1, 'C');
		}
		array_push($cols, array(ecole_pdf_trans($langs, 'MoyenneAnnuelle'), 1.1, 'C'), array(ecole_pdf_trans($langs, 'Rang'), 0.7, 'C'), array(ecole_pdf_trans($langs, 'MoyClasse'), 1, 'C'));
		foreach ($calc['matieres'] as $mid => $m) {
			$rm = $r ? $r['matieres'][$mid] : null;
			$rows[] = array(ecole_label($m), notes_fmt($m->coefficient), notes_moy($rm ? $rm['t'][1] : null), notes_moy($rm ? $rm['t'][2] : null), notes_moy($rm ? $rm['t'][3] : null),
				notes_moy($rm ? $rm['moyenne'] : null), $rm && $rm['rang'] !== null ? (string) $rm['rang'] : '', notes_moy($calc['stats']['matieres'][$mid]['moy']));
		}
		$rows[] = array(ecole_pdf_trans($langs, 'MoyenneGenerale'), '', notes_moy($r ? $r['trimestres'][1] : null), notes_moy($r ? $r['trimestres'][2] : null), notes_moy($r ? $r['trimestres'][3] : null),
			notes_moy($r ? $r['moyenne'] : null), $r ? ecole_pdf_text(notes_rang_label($r['rang'], $r['exaequo'])) : '', notes_moy($calc['stats']['generale']['moy']));
	} else {
		$cols = array(array(ecole_pdf_trans($langs, 'Matiere'), 2.6, 'L'), array(ecole_pdf_trans($langs, 'Coefficient'), 0.8, 'C'), array(ecole_pdf_trans($langs, 'Devoirs'), 2, 'C'),
			array(ecole_pdf_trans($langs, 'NoteDevoirs'), 1, 'C'), array(ecole_pdf_trans($langs, 'Composition'), 1, 'C'), array(ecole_pdf_trans($langs, 'MoyenneSur20'), 1, 'C'),
			array(ecole_pdf_trans($langs, 'Rang'), 0.7, 'C'), array(ecole_pdf_trans($langs, 'MoyClasse'), 1, 'C'));
		$evals = array();
		$resql = $db->query("SELECT rowid, fk_matiere, type FROM ".$db->prefix()."ecole_evaluation WHERE fk_classe = ".$cid." AND trimestre = ".((int) $periode)." AND status = 1".ecole_annee_sql()." ORDER BY type, numero");
		while ($resql && ($o = $db->fetch_object($resql))) {
			$evals[(int) $o->rowid] = $o;
		}
		$vals = notes_valeurs($db, array_keys($evals));
		foreach ($calc['matieres'] as $mid => $m) {
			$dv = array();
			$compo = '';
			foreach ($evals as $evid => $ev) {
				if ((int) $ev->fk_matiere !== $mid) {
					continue;
				}
				$txt = notes_export_code(isset($vals[$evid][(int) $eleve->id]) ? $vals[$evid][(int) $eleve->id] : '');
				if ((int) $ev->type === NOTES_COMPO) {
					$compo = $txt;
				} elseif ($txt !== '') {
					$dv[] = $txt;
				}
			}
			$rm = $r ? $r['matieres'][$mid] : null;
			$rows[] = array(ecole_label($m), notes_fmt($m->coefficient), implode(' · ', $dv), notes_moy($rm ? $rm['devoirs'] : null), $compo, notes_moy($rm ? $rm['moyenne'] : null),
				$rm && $rm['rang'] !== null ? (string) $rm['rang'] : '', notes_moy($calc['stats']['matieres'][$mid]['moy']));
		}
		$rows[] = array(ecole_pdf_trans($langs, 'MoyenneGenerale'), '', '', '', '', notes_moy($r ? $r['moyenne'] : null), $r ? ecole_pdf_text(notes_rang_label($r['rang'], $r['exaequo'])) : '', notes_moy($calc['stats']['generale']['moy']));
	}
	return notes_ds(ecole_pdf_trans($langs, 'Notes').' - '.ecole_label($eleve), $eleve->ref.' — '.ecole_pdf_text(notes_periode_label($periode)), 'NOT-'.$eleve->ref, $cols, $rows,
		'notes_'.$eleve->ref.'_'.((int) $periode === 0 ? 'annee' : 'T'.((int) $periode)));
}
