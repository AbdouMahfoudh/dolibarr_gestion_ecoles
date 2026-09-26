<?php
/**
 * Page d'un élève dans l'espace, en onglets :
 *  - Notes : notes de chaque évaluation dès la saisie ; moyennes, rang, mention et bulletin après la clôture ;
 *  - Emploi du temps : cours du jour par jour (téléphone) ou grille de la semaine, matières et enseignants ;
 *  - Absences : absences, retards, renvois et sanctions (les sanctions annulées ne sont pas montrées) ;
 *  - Paiements : situation mois par mois, impayés, reçus (PDF) ;
 *  - Dossier (responsable seulement) : pièces du dossier fournies et manquantes (noms des pièces seulement).
 * Certificat de scolarité téléchargeable en haut de la page.
 *
 *   eleve.php?id=<élève>&onglet=<notes|edt|absences|paiements|dossier>[&periode=<0..3>]
 *
 * Fichier : custom/espace/portail/eleve.php
 */

require 'boot.php';
dol_include_once('/classes/class/ecole_classe.class.php');
dol_include_once('/classes/core/lib/edt.lib.php');
dol_include_once('/eleves/core/lib/paiements.lib.php');
dol_include_once('/eleves/core/lib/discipline.lib.php');
dol_include_once('/eleves/class/ecole_recu.class.php');
$avecNotes = isModEnabled('notes');
if ($avecNotes) {
	dol_include_once('/notes/core/lib/notes.lib.php');
	dol_include_once('/notes/core/lib/calcul.lib.php');
}

$acces = espace_exiger_session($db);
$langs = espace_langs_init(espace_langue_code($db, $acces));
$eleves = espace_eleves_visibles($db, $acces->type, (int) $acces->fk_cible);
$id = espace_id_par_jeton('eleve', GETPOST('jeton', 'alphanohtml'), array_keys($eleves));
if (!isset($eleves[$id])) {
	header('Location: '.espace_page_url(''));
	exit;
}
$e = $eleves[$id];
$parent = ($acces->type === ESPACE_PARENT);
$classe = new EcoleClasse($db);
$classe->fetch((int) $e->fk_classe);
$fk_classe = (int) $e->fk_classe;
$rtl = espace_rtl();

$onglets = array();
if ($avecNotes) {
	$onglets['notes'] = array('OngletNotes', 'fa-star');
}
$onglets['edt'] = array('EmploiDuTemps', 'fa-calendar-alt');
$onglets['absences'] = array('AbsencesEtSanctions', 'fa-user-clock');
$onglets['paiements'] = array('Paiements', 'fa-coins');
if ($parent) {
	$onglets['dossier'] = array('DossierPieces', 'fa-folder-open');
}
$onglet = GETPOST('onglet', 'aZ09');
if (!isset($onglets[$onglet])) {
	$onglet = key($onglets);
}
$url = function ($periode) use ($id) {
	return espace_eleve_url($id, 'notes', $periode);
};

espace_header(ecole_label($e), $acces, ($parent && count($eleves) > 0) ? espace_page_url('') : '');

if (!empty($_SESSION['ecole_espace_msg'])) {
	print espace_msg(dol_escape_htmltag($_SESSION['ecole_espace_msg']), 'ok');
	unset($_SESSION['ecole_espace_msg']);
}

// En-tête de l'élève
print '<section class="es-card es-student">';
print espace_avatar($e, 'es-avatar-lg');
print '<div class="es-student-info"><h1>'.dol_escape_htmltag(ecole_label($e)).'</h1>';
print '<div class="es-small"><i class="fas fa-chalkboard"></i> '.dol_escape_htmltag(ecole_label($classe)).' &nbsp;·&nbsp; <span dir="ltr">'.dol_escape_htmltag($e->ref).'</span></div>';
if ((int) $e->status === EcoleEleve::STATUS_SUSPENDU) {
	print '<span class="es-chip es-chip-orange">'.dol_escape_htmltag($e->LibStatut($e->status, 0)).'</span>';
}
print '</div>';
if ($avecNotes) {
	print '<a class="es-btn es-btn-light es-btn-sm" href="'.dol_escape_htmltag(espace_document_url($id, 'certificat')).'" target="_blank" rel="noopener"><i class="fas fa-certificate"></i> '.$langs->trans('CertificatScolarite').'</a>';
}
print '</section>';

// Onglets
print '<nav class="es-tabs">';
foreach ($onglets as $k => $o) {
	print '<a class="es-tab'.($k === $onglet ? ' active' : '').'" href="'.dol_escape_htmltag(espace_eleve_url($id, $k)).'"><i class="fas '.$o[1].'"></i><span>'.$langs->trans($o[0]).'</span></a>';
}
print '</nav>';

/*
 * Notes
 */
if ($onglet === 'notes') {
	$periode = GETPOSTISSET('periode') ? GETPOSTINT('periode') : notes_trimestre_defaut($db, $fk_classe);
	if (!in_array($periode, array(0, 1, 2, 3), true)) {
		$periode = 1;
	}
	print '<div class="es-seg">';
	foreach (array(1, 2, 3, 0) as $p) {
		print '<a class="'.($p === $periode ? 'active' : '').'" href="'.dol_escape_htmltag($url($p)).'">'.dol_escape_htmltag(notes_periode_label($p)).'</a>';
	}
	print '</div>';

	$close = notes_periode_close($db, $fk_classe, $periode);
	$calc = notes_calcul($db, $fk_classe, $periode);
	$r = isset($calc['res'][$id]) ? $calc['res'][$id] : null;
	$conseil = $close ? notes_conseil($db, $fk_classe, $periode) : array();
	$c = isset($conseil[$id]) ? $conseil[$id] : null;

	if ($periode === 0) {
		// Bilan de l'année : seulement quand les trois trimestres sont clôturés
		if (!$close || !$r) {
			print espace_msg($langs->trans('BilanAnnuelApresCloture'), 'info');
		} else {
			print '<div class="es-card"><div class="es-table-wrap"><table class="es-table"><thead><tr><th>'.$langs->trans('Matiere').'</th>';
			for ($t = 1; $t <= 3; $t++) {
				print '<th class="es-c">'.$langs->trans('TrimestreCourt', $t).'</th>';
			}
			print '<th class="es-c">'.$langs->trans('MoyenneAnnuelle').'</th></tr></thead><tbody>';
			foreach ($calc['matieres'] as $mid => $m) {
				$rm = $r['matieres'][$mid];
				print '<tr><td>'.dol_escape_htmltag(ecole_label($m)).'</td>';
				for ($t = 1; $t <= 3; $t++) {
					print '<td class="es-c" dir="ltr">'.notes_moy($rm['t'][$t]).'</td>';
				}
				print '<td class="es-c">'.espace_moy_html($rm['moyenne']).'</td></tr>';
			}
			print '<tr class="es-total"><td>'.$langs->trans('MoyenneGenerale').'</td>';
			for ($t = 1; $t <= 3; $t++) {
				print '<td class="es-c" dir="ltr">'.notes_moy($r['trimestres'][$t]).'</td>';
			}
			print '<td class="es-c">'.espace_moy_html($r['moyenne']).'</td></tr>';
			print '</tbody></table></div></div>';
		}
	} else {
		// Notes de chaque évaluation, visibles dès la saisie
		$evals = array();
		$resql = $db->query("SELECT rowid, fk_matiere, type, numero, label, date_eval, note_max FROM ".$db->prefix()."ecole_evaluation WHERE fk_classe = ".$fk_classe." AND trimestre = ".((int) $periode)." AND status = 1 ORDER BY type, numero");
		while ($resql && ($o = $db->fetch_object($resql))) {
			$evals[(int) $o->rowid] = $o;
		}
		$vals = notes_valeurs($db, array_keys($evals));
		if (!$close) {
			print espace_msg($langs->trans('MoyennesApresCloture'), 'info');
		}
		if (empty($calc['matieres'])) {
			print espace_msg($langs->trans('AucuneMatiere'), 'info');
		}
		print '<div class="es-subjects">';
		foreach ($calc['matieres'] as $mid => $m) {
			$rm = ($r && isset($r['matieres'][$mid])) ? $r['matieres'][$mid] : null;
			print '<div class="es-card es-subject"><div class="es-subject-head"><span class="es-subject-name">'.dol_escape_htmltag(ecole_label($m)).'</span>';
			if ($close && $rm) {
				print espace_moy_html($rm['moyenne']);
			}
			print '</div>';
			$lignes = 0;
			foreach ($evals as $evid => $ev) {
				if ((int) $ev->fk_matiere !== $mid) {
					continue;
				}
				$lignes++;
				$code = isset($vals[$evid][$id]) ? $vals[$evid][$id] : '';
				$nom = notes_evaluation_nom($ev).($ev->label ? ' — '.$ev->label : '');
				print '<div class="es-eval"><span>'.dol_escape_htmltag($nom);
				if ($ev->date_eval) {
					print ' <span class="es-muted es-small">'.dol_print_date($db->jdate($ev->date_eval), 'day').'</span>';
				}
				print '</span>'.espace_note_html($code, $ev->note_max).'</div>';
			}
			if (!$lignes) {
				print '<div class="es-muted es-small">'.$langs->trans('PasEncoreDeNote').'</div>';
			}
			if ($close && $rm) {
				print '<div class="es-subject-foot es-small es-muted">'.$langs->trans('Rang').' : <b>'.($rm['rang'] !== null ? (int) $rm['rang'] : '—').'</b>';
				print ' &nbsp;·&nbsp; '.$langs->trans('MoyClasse').' : <span dir="ltr">'.notes_moy($calc['stats']['matieres'][$mid]['moy']).'</span></div>';
			}
			print '</div>';
		}
		print '</div>';
	}

	// Résultats de la période (après clôture) et bulletin
	if ($close && $r) {
		$annee = ($periode === 0);
		$mention = notes_mention($db, $r['moyenne']);
		$dist = notes_distinction($db, $c, $r['moyenne']);
		print '<div class="es-card es-result"><h2>'.$langs->trans($annee ? 'ResultatsAnnee' : 'ResultatsTrimestre').'</h2>';
		print '<div class="es-kpis">';
		print '<div class="es-kpi"><span>'.$langs->trans($annee ? 'MoyenneAnnuelle' : 'MoyenneGenerale').'</span>'.espace_moy_html($r['moyenne']).'</div>';
		print '<div class="es-kpi"><span>'.$langs->trans('Rang').'</span><b>'.dol_escape_htmltag(notes_rang_label($r['rang'], $r['exaequo'])).'</b><small> / '.((int) $calc['nb_classes']).'</small></div>';
		print '<div class="es-kpi"><span>'.$langs->trans('MoyClasse').'</span><b dir="ltr">'.notes_moy($calc['stats']['generale']['moy']).'</b></div>';
		print '</div>';
		if ($mention) {
			print '<div class="es-line"><span class="es-muted">'.$langs->trans('Mention').'</span><b>'.dol_escape_htmltag(ecole_label($mention)).'</b></div>';
		}
		if ($dist) {
			print '<div class="es-line"><span class="es-muted">'.$langs->trans('Distinction').'</span><span class="es-chip es-chip-green">'.dol_escape_htmltag(ecole_label($dist)).'</span></div>';
		}
		if ($annee && $calc['regle']['decision'] !== 'aucune') {
			$decision = notes_decision($calc['regle'], $c, $r['moyenne']);
			if ($decision !== '') {
				print '<div class="es-line"><span class="es-muted">'.$langs->trans('DecisionFinAnneeCourt').'</span><b>'.dol_escape_htmltag($langs->trans(notes_decisions()[$decision])).'</b></div>';
			}
		}
		if ($c && $c->observation) {
			print '<div class="es-obs"><div class="es-muted es-small">'.$langs->trans('ObservationDirection').'</div>'.dol_nl2br(dol_escape_htmltag($c->observation)).'</div>';
		}
		print '<a class="es-btn es-btn-primary es-btn-block" href="'.dol_escape_htmltag(espace_document_url($id, 'bulletin', $periode)).'" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i> '.$langs->trans($annee ? 'ReleveAnnuel' : 'BulletinDeNotes').'</a>';
		print '</div>';
	}
}

/*
 * Emploi du temps
 */
if ($onglet === 'edt') {
	$data = ecole_edt_classe_data($db, $fk_classe);
	if (empty($data['events'])) {
		print espace_msg($langs->trans('AucunCoursClasse'), 'info');
	} else {
		// Téléphone : jour par jour, aujourd'hui en premier ouvert
		$jd = dol_getdate(dol_now(), true);
		$aujourdhui = ((int) $jd['wday'] === 0) ? 7 : (int) $jd['wday'];
		$parJour = array();
		foreach ($data['events'] as $ev) {
			$parJour[$ev['col']][] = $ev;
		}
		print '<div class="es-days">';
		foreach ($data['columns'] as $j => $col) {
			$liste = isset($parJour[$j]) ? $parJour[$j] : array();
			usort($liste, function ($a, $b) {
				return strcmp($a['start'], $b['start']);
			});
			print '<details class="es-card es-day"'.($j === $aujourdhui || ($aujourdhui > max(array_keys($data['columns'])) && $j === min(array_keys($data['columns']))) ? ' open' : '').'>';
			print '<summary>'.dol_escape_htmltag($col['label']).($j === $aujourdhui ? ' <span class="es-chip es-chip-blue">'.$langs->trans('Aujourdhui').'</span>' : '').' <span class="es-muted es-small">('.count($liste).')</span></summary>';
			if (empty($liste)) {
				print '<div class="es-muted es-small">'.$langs->trans('PasDeCours').'</div>';
			}
			foreach ($liste as $ev) {
				print '<div class="es-course es-c'.(((int) $ev['color']) % 10).'"><span class="es-course-time" dir="ltr">'.dol_escape_htmltag($ev['start'].' - '.$ev['end']).'</span>';
				print '<span class="es-course-title">'.dol_escape_htmltag($ev['title']).'</span>';
				$details = array_filter($ev['lines']);
				if (!empty($details)) {
					print '<span class="es-muted es-small">'.dol_escape_htmltag(implode(' · ', $details)).'</span>';
				}
				print '</div>';
			}
			print '</details>';
		}
		print '</div>';
		// Écran large : grille de la semaine
		print '<div class="es-card es-week"><h2>'.$langs->trans('EmploiDuTempsSemaine').'</h2><div class="es-table-wrap">';
		ecole_timetable_render($data['columns'], $data['events'], array('bands' => $data['bands']));
		print '</div></div>';
	}
}

/*
 * Absences, retards et sanctions
 */
if ($onglet === 'absences') {
	$cpt = eleves_compteurs_eleve($db, $id);
	print '<div class="es-kpis es-kpis-4">';
	print '<div class="es-card es-kpi"><span>'.$langs->trans('Absences').'</span><b>'.$cpt['absences'].'</b><small>'.$langs->trans('DontNonJustifiees', $cpt['absences_nj']).'</small></div>';
	print '<div class="es-card es-kpi"><span>'.$langs->trans('Retards').'</span><b>'.$cpt['retards'].'</b><small>'.$langs->trans('DontNonJustifies', $cpt['retards_nj']).'</small></div>';
	print '<div class="es-card es-kpi"><span>'.$langs->trans('RenvoisCours').'</span><b>'.$cpt['renvois'].'</b></div>';
	print '<div class="es-card es-kpi"><span>'.$langs->trans('Sanctions').'</span><b>'.$cpt['sanctions'].'</b></div>';
	print '</div>';

	$f = array('du' => '', 'au' => '', 'classe' => 0, 'eleve' => '', 'type' => 0, 'etat' => '', 'creneau' => 0, 'fk_eleve' => $id);
	$total = 0;
	$lignes = eleves_absences_liste($db, $f, 0, 0, $total);
	print '<h2 class="es-h2">'.$langs->trans('AbsencesEtRetards').' ('.$total.')</h2>';
	if (empty($lignes)) {
		print espace_msg($langs->trans('AucuneAbsence'), 'ok');
	}
	print '<div class="es-list">';
	$couleurs = array(ELEVES_ABSENT => 'es-chip-red', ELEVES_RETARD => 'es-chip-orange', ELEVES_RENVOYE => 'es-chip-purple');
	foreach ($lignes as $a) {
		print '<div class="es-card es-item"><div class="es-item-main"><b>'.dol_escape_htmltag(eleves_date_label(dol_print_date($db->jdate($a->date_appel), '%Y-%m-%d'))).'</b>';
		print '<span class="es-muted es-small"><span dir="ltr">'.dol_escape_htmltag($a->crref.' '.$a->heure_debut).'</span>'.(eleves_absence_matiere($a) !== '' ? ' · '.dol_escape_htmltag(eleves_absence_matiere($a)) : '').'</span></div>';
		print '<div class="es-item-side"><span class="es-chip '.(isset($couleurs[(int) $a->type]) ? $couleurs[(int) $a->type] : '').'">'.dol_escape_htmltag(eleves_absence_type_label($a)).'</span>';
		if ((int) $a->justifiee) {
			$motif = ecole_label((object) array('label_fr' => (string) $a->motif_fr, 'label_ar' => (string) $a->motif_ar));
			print '<span class="es-small es-ok"><i class="fas fa-check"></i> '.$langs->trans('Justifiee').($motif !== '' ? ' — '.dol_escape_htmltag($motif) : '').'</span>';
		} else {
			print '<span class="es-small es-ko">'.$langs->trans('NonJustifiee').'</span>';
		}
		print '</div></div>';
	}
	print '</div>';

	$f['etat'] = 'valide';
	$sanctions = eleves_sanctions_liste($db, $f, 0, 0, $total);
	print '<h2 class="es-h2">'.$langs->trans('Sanctions').' ('.$total.')</h2>';
	if (empty($sanctions)) {
		print espace_msg($langs->trans('AucuneSanction'), 'ok');
	}
	print '<div class="es-list">';
	foreach ($sanctions as $s) {
		print '<div class="es-card es-item es-item-col"><div class="es-item-top"><b>'.dol_escape_htmltag(ecole_label((object) array('label_fr' => $s->type_fr, 'label_ar' => $s->type_ar))).'</b>';
		print '<span class="es-muted es-small">'.dol_escape_htmltag(eleves_sanction_periode($s, $db)).'</span></div>';
		if ($s->motif) {
			print '<div class="es-small">'.dol_nl2br(dol_escape_htmltag($s->motif)).'</div>';
		}
		print '</div>';
	}
	print '</div>';
}

/*
 * Paiements
 */
if ($onglet === 'paiements') {
	$s = eleves_situation($db, $e);
	print '<div class="es-kpis">';
	print '<div class="es-card es-kpi'.($s['impaye'] > 0 ? ' es-kpi-red' : ' es-kpi-green').'"><span>'.$langs->trans('Impaye').'</span><b>'.($s['impaye'] > 0 ? espace_montant($s['impaye']) : $langs->trans('AJour')).'</b></div>';
	print '<div class="es-card es-kpi"><span>'.$langs->trans('TotalPaye').'</span><b>'.espace_montant($s['total_paye']).'</b></div>';
	print '<div class="es-card es-kpi"><span>'.$langs->trans('ResteAnnee').'</span><b>'.espace_montant($s['reste']).'</b></div>';
	print '</div>';
	if ($s['impaye'] > 0 && !empty($s['impaye_mois'])) {
		$mois = array();
		foreach ($s['impaye_mois'] as $per) {
			$mois[] = eleves_periode_label($per);
		}
		print espace_msg($langs->trans('MoisEnRetard').' : '.dol_escape_htmltag(implode(', ', $mois)), 'warn');
	}

	$classesEtat = array('paye' => 'es-chip-green', 'gratuit' => 'es-chip-blue', 'partiel' => 'es-chip-orange', 'partiel_retard' => 'es-chip-orange', 'impaye' => 'es-chip-red', 'a_venir' => '');
	print '<h2 class="es-h2">'.$langs->trans('SituationMoisParMois').'</h2>';
	// Une ligne par mois : libellé, montant dû et payé en dessous, état à côté (lisible sur un téléphone)
	$lignesMois = array();
	$ins = $s['inscription'];
	$etatIns = ($ins['du'] <= 0) ? 'gratuit' : ($ins['reste'] <= 0 ? 'paye' : ($ins['paye'] > 0 ? 'partiel' : ($s['actif'] ? 'impaye' : 'a_venir')));
	$lignesMois[] = array($langs->trans('FraisInscription'), $ins['du'], $ins['paye'], $ins['reste'], $etatIns);
	foreach ($s['mois'] as $m) {
		if ($m['dans']) {
			$lignesMois[] = array($m['label'], $m['du'], $m['paye'], $m['reste'], $m['etat']);
		}
	}
	print '<div class="es-card es-months">';
	foreach ($lignesMois as $lm) {
		print '<div class="es-month"><div class="es-item-main"><b>'.dol_escape_htmltag($lm[0]).'</b>';
		print '<span class="es-muted es-small">'.$langs->trans('MontantDu').' '.espace_montant($lm[1]).' · '.$langs->trans('DejaPaye').' '.espace_montant($lm[2]).'</span></div>';
		print '<span class="es-chip '.(isset($classesEtat[$lm[4]]) ? $classesEtat[$lm[4]] : '').'">'.dol_escape_htmltag(eleves_etat_label($lm[4], $lm[3])).'</span></div>';
	}
	print '<div class="es-month es-month-total"><b>'.$langs->trans('Total').'</b><span class="es-small">'.$langs->trans('MontantDu').' '.espace_montant($s['total_du']).' · '.$langs->trans('DejaPaye').' '.espace_montant($s['total_paye']).'</span></div>';
	print '</div>';
	print '<p class="es-muted es-small">'.$langs->trans('AideJourLimite', min(28, max(1, getDolGlobalInt('ELEVES_JOUR_LIMITE', 10)))).'</p>';

	// Reçus : montant payé pour cet élève sur chaque reçu
	print '<h2 class="es-h2">'.$langs->trans('RecusDePaiement').'</h2>';
	$sql = "SELECT r.rowid, r.ref, r.date_recu, r.status, r.fk_mode, SUM(l.montant) as montant, COUNT(DISTINCT l.fk_eleve) as nb";
	$sql .= " FROM ".$db->prefix()."ecole_paiement l INNER JOIN ".$db->prefix()."ecole_recu r ON r.rowid = l.fk_recu";
	$sql .= " WHERE l.fk_eleve = ".$id." GROUP BY r.rowid, r.ref, r.date_recu, r.status, r.fk_mode ORDER BY r.date_recu DESC, r.rowid DESC";
	$resql = $db->query($sql);
	$nb = 0;
	print '<div class="es-list">';
	while ($resql && ($o = $db->fetch_object($resql))) {
		$nb++;
		$annule = ((int) $o->status === EcoleRecu::STATUS_ANNULE);
		print '<div class="es-card es-item'.($annule ? ' es-annule' : '').'"><div class="es-item-main"><b dir="ltr">'.dol_escape_htmltag($o->ref).'</b>';
		print '<span class="es-muted es-small">'.dol_print_date($db->jdate($o->date_recu), 'day').' · '.dol_escape_htmltag(eleves_mode_label($db, $o->fk_mode)).'</span></div>';
		print '<div class="es-item-side">'.espace_montant($o->montant);
		if ($annule) {
			print '<span class="es-chip">'.$langs->trans('RecuAnnule').'</span>';
		} else {
			print '<a class="es-btn es-btn-light es-btn-sm" href="'.dol_escape_htmltag(espace_document_url($id, 'recu', (int) $o->rowid)).'" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i> PDF</a>';
		}
		print '</div></div>';
	}
	print '</div>';
	if (!$nb) {
		print espace_msg($langs->trans('AucunPaiement'), 'info');
	}
}

/*
 * Pièces du dossier (responsable seulement) : noms des pièces, jamais les fichiers scannés
 */
if ($onglet === 'dossier') {
	$pieces = $e->getPieces();
	$manquantes = $e->getPiecesManquantes();
	if (!empty($manquantes)) {
		print espace_msg($langs->trans('PiecesManquantesAide', count($manquantes)), 'warn');
	} else {
		print espace_msg($langs->trans('DossierComplet'), 'ok');
	}
	print '<div class="es-list">';
	foreach ($pieces as $tid => $pc) {
		if ((int) $pc->type_status !== 1) {
			continue;
		}
		$ok = !empty($pc->fourni);
		print '<div class="es-card es-item"><div class="es-item-main"><b>'.dol_escape_htmltag(ecole_label($pc)).'</b></div>';
		print '<div class="es-item-side">'.($ok ? '<span class="es-chip es-chip-green"><i class="fas fa-check"></i> '.$langs->trans('PieceFournie').'</span>' : '<span class="es-chip es-chip-red"><i class="fas fa-times"></i> '.$langs->trans('PieceManquante').'</span>').'</div></div>';
	}
	print '</div>';
}

espace_footer();
$db->close();
