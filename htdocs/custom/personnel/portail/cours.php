<?php
/**
 * Mes cours (enseignant) : les cours d'une journée (les siens et ses remplacements) avec leur état.
 * En choisissant un cours :
 *  - déclarer le cours avec le sujet traité (facultatif) ; impossible s'il a été marqué absent ou s'il est à venir ;
 *  - voir les élèves de la classe avec leur présence à ce cours (absent, en retard, renvoyé) ;
 *  - le jour même, marquer un élève « renvoyé du cours » (ou annuler) : c'est enregistré dans l'appel du créneau.
 *
 *   cours[/<AAAA-MM-JJ>[/<code du cours>]]
 *
 * Fichier : custom/personnel/portail/cours.php
 */

require_once __DIR__.'/init_espace.php';
dol_include_once('/eleves/core/lib/discipline.lib.php');

list($acces, $emp, $moi, $rubriques) = pe_session($db, 'cours');
$aujourdhui = personnel_aujourdhui();
$date = personnel_date_ok(pe_arg(0));
if ($date === '') {
	$date = $aujourdhui;
}
$cours = personnel_cours_jour($db, $date, (int) $emp->id);
$sel = null;
if (pe_arg(1) !== '') {
	$k = pe_cours_par_jeton(pe_arg(1), $cours);
	if ($k && isset($cours[$k[1].'|'.$k[0]])) {
		$sel = $cours[$k[1].'|'.$k[0]];
	}
}
$urlJour = espace_page_url('cours/'.$date);
$urlSel = $sel ? espace_page_url('cours/'.$date.'/'.pe_jeton_cours($sel->fk_classe, $sel->fk_creneau)) : $urlJour;
$titulaire = $sel && (int) $sel->fk_employe === (int) $emp->id;
$remplacant = $sel && $sel->rec && (int) $sel->rec->fk_remplacant === (int) $emp->id;

/*
 * Actions
 */
$action = GETPOST('action', 'aZ09');
if ($sel && $action === 'declarer' && $titulaire) {
	$error = '';
	if (personnel_cours_declarer($db, $moi, $date, (int) $sel->fk_creneau, (int) $sel->fk_classe, GETPOST('sujet', 'alphanohtml'), $error) > 0) {
		pe_flash($langs->trans('CoursDeclareOk'));
	} else {
		pe_flash($error, false);
	}
	header('Location: '.$urlSel);
	exit;
}
if ($sel && $action === 'renvoi' && ($titulaire || $remplacant) && $date === $aujourdhui && $sel->etat !== 'absent') {
	$eleves = eleves_appel_eleves($db, (int) $sel->fk_classe, $date);
	$eid = espace_id_par_jeton('eleve', GETPOST('eleve', 'alphanohtml'), array_keys($eleves));
	if ($eid > 0 && $eleves[$eid]->etat_special === '') {
		$appel = eleves_appel_fetch($db, (int) $sel->fk_classe, $date, (int) $sel->fk_creneau);
		$lignes = $appel ? eleves_appel_lignes($db, (int) $appel->rowid) : array();
		$type = (isset($lignes[$eid]) && (int) $lignes[$eid]->type === ELEVES_RENVOYE) ? ELEVES_PRESENT : ELEVES_RENVOYE;
		$error = '';
		if (eleves_appel_enregistrer($db, $moi, (int) $sel->fk_classe, $date, (int) $sel->fk_creneau, array($eid => array('type' => $type, 'heure' => '')), $error, (int) $sel->fk_matiere) > 0) {
			pe_flash($langs->trans($type === ELEVES_RENVOYE ? 'EspEleveRenvoye' : 'EspRenvoiAnnule', ecole_label($eleves[$eid])));
		} else {
			pe_flash($error, false);
		}
	}
	header('Location: '.$urlSel.'#eleves');
	exit;
}

/*
 * Affichage
 */
pe_header($langs->trans('EspMesCours'), $acces, $emp, $rubriques, 'cours', $sel ? $urlJour : '');

// Choix du jour
$veille = date('Y-m-d', strtotime($date.' -1 day'));
$lendemain = date('Y-m-d', strtotime($date.' +1 day'));
print '<div class="es-daynav"><a class="es-btn es-btn-light es-btn-sm" href="'.dol_escape_htmltag(espace_page_url('cours/'.$veille)).'"><i class="fas fa-chevron-'.(espace_rtl() ? 'right' : 'left').'"></i></a>';
print '<b>'.dol_escape_htmltag(personnel_date_label($date)).'</b>';
print '<a class="es-btn es-btn-light es-btn-sm" href="'.dol_escape_htmltag(espace_page_url('cours/'.$lendemain)).'"><i class="fas fa-chevron-'.(espace_rtl() ? 'left' : 'right').'"></i></a></div>';
if ($date !== $aujourdhui) {
	print '<p class="es-center"><a href="'.dol_escape_htmltag(espace_page_url('cours')).'">'.$langs->trans('Aujourdhui').'</a></p>';
}

if (!$sel) {
	$sanscours = personnel_jours_sans_cours($db, $date, $date);
	if (isset($sanscours[$date])) {
		print espace_msg(dol_escape_htmltag($langs->transnoentities('JourSansCoursInfo', $sanscours[$date])), 'info');
	}
	if (empty($cours)) {
		print espace_msg($langs->trans('EspPasDeCoursCeJour'), 'info');
	}
	foreach ($cours as $c) {
		$rempl = $c->rec && (int) $c->rec->fk_remplacant === (int) $emp->id;
		$etat = $rempl ? 'remplacement' : $c->etat;
		$info = ($etat === 'retard' && $c->rec) ? '('.$langs->trans('NbMinutes', (int) $c->rec->minutes_retard).')' : '';
		print '<a class="es-card es-course-card" href="'.dol_escape_htmltag(espace_page_url('cours/'.$date.'/'.pe_jeton_cours($c->fk_classe, $c->fk_creneau))).'">';
		print '<div class="es-item-top"><b>'.dol_escape_htmltag($c->cref.' · '.pe_label($c, 'm_')).'</b><span class="es-muted es-small" dir="ltr">'.dol_escape_htmltag($c->crref.' '.$c->heure_debut.'-'.$c->heure_fin).'</span></div>';
		print '<div class="es-chips">'.pe_etat_chip($etat, $info);
		if ($rempl) {
			print '<span class="es-chip">'.$langs->trans('AuLieuDe', dol_escape_htmltag(ecole_label($c))).'</span>';
		} elseif ($c->rec && (int) $c->rec->fk_remplacant > 0) {
			print '<span class="es-chip">'.$langs->trans('RemplacePar', dol_escape_htmltag(personnel_employe_nom($db, (int) $c->rec->fk_remplacant))).'</span>';
		}
		if ($c->rec && trim((string) $c->rec->sujet) !== '') {
			print '<span class="es-chip es-chip-blue"><i class="fas fa-book-open"></i> '.dol_escape_htmltag($c->rec->sujet).'</span>';
		}
		print '</div></a>';
	}
	print '<p class="es-muted es-small">'.$langs->trans('EspAideCours').'</p>';
	espace_footer();
	$db->close();
	exit;
}

// Cours choisi
$rec = $sel->rec;
$etat = $remplacant ? 'remplacement' : $sel->etat;
$info = ($etat === 'retard' && $rec) ? '('.$langs->trans('NbMinutes', (int) $rec->minutes_retard).')' : '';
print '<section class="es-card"><h2>'.dol_escape_htmltag($sel->cref.' · '.pe_label($sel, 'm_')).'</h2>';
print '<div class="es-muted" dir="ltr">'.dol_escape_htmltag($sel->crref.' '.$sel->heure_debut.' - '.$sel->heure_fin).'</div>';
print '<div class="es-chips">'.pe_etat_chip($etat, $info).'</div>';
if ($rec && (int) $rec->declare_cours) {
	print '<div class="es-line"><span class="es-muted">'.$langs->trans('Declaration').'</span><span>'.dol_print_date($db->jdate($rec->date_declaration), 'dayhour', 'tzuserrel').'</span></div>';
}
if ($rec && trim((string) $rec->sujet) !== '') {
	print '<div class="es-line"><span class="es-muted">'.$langs->trans('SujetTraite').'</span><span>'.dol_escape_htmltag($rec->sujet).'</span></div>';
}
if ($titulaire) {
	if ($sel->etat === 'absent') {
		print espace_msg($langs->trans('DeclarationImpossibleAbsent'), 'warn');
	} elseif ($date > $aujourdhui) {
		print espace_msg($langs->trans('ErrorCoursAVenir'), 'info');
	} elseif (!$rec || !(int) $rec->declare_cours) {
		print '<form method="POST" action="'.dol_escape_htmltag($urlSel).'" class="es-form">';
		print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="declarer">';
		print '<label for="sujet">'.$langs->trans('SujetTraiteFacultatif').'</label>';
		print '<input type="text" id="sujet" name="sujet" maxlength="250" dir="auto" value="'.dol_escape_htmltag($rec ? (string) $rec->sujet : '').'">';
		print '<button type="submit" class="es-btn es-btn-primary es-btn-block"><i class="fas fa-check-double"></i> '.$langs->trans('DeclarerCours').'</button>';
		print '</form>';
	}
}
print '</section>';

// Élèves de la classe et leur présence à ce cours
$eleves = eleves_appel_eleves($db, (int) $sel->fk_classe, $date);
$appel = eleves_appel_fetch($db, (int) $sel->fk_classe, $date, (int) $sel->fk_creneau);
$lignes = $appel ? eleves_appel_lignes($db, (int) $appel->rowid) : array();
$peutRenvoyer = ($titulaire || $remplacant) && $date === $aujourdhui && $sel->etat !== 'absent';
print '<a id="eleves"></a><h2 class="es-h2"><i class="fas fa-users"></i> '.$langs->trans('EspElevesDuCours').' <span class="es-muted es-small">('.count($eleves).')</span></h2>';
print '<p class="es-muted es-small">'.($appel ? $langs->trans('EspAppelFaitPar', dol_escape_htmltag(ecole_user_label($db, $appel->fk_user_creat))) : $langs->trans('EspAppelPasFait')).'</p>';
print '<div class="es-list">';
foreach ($eleves as $eid => $e) {
	$l = isset($lignes[$eid]) ? $lignes[$eid] : null;
	print '<div class="es-card es-item"><div class="es-item-main"><span><b dir="ltr">'.($e->numero_appel ? (int) $e->numero_appel.'.' : '').'</b> '.dol_escape_htmltag(ecole_label($e)).'</span>';
	if ($e->etat_special !== '') {
		print '<span class="es-small es-muted">'.$langs->trans($e->etat_special === 'exclu' ? 'ExcluTemporairement' : 'StatutSuspendu').'</span>';
	}
	print '</div><div class="es-item-side">';
	if ($l) {
		$css = array(ELEVES_ABSENT => 'es-chip-red', ELEVES_RETARD => 'es-chip-orange', ELEVES_RENVOYE => 'es-chip-purple');
		print '<span class="es-chip '.(isset($css[(int) $l->type]) ? $css[(int) $l->type] : '').'">'.dol_escape_htmltag(eleves_presence_label(eleves_presence_code($l->type, (string) $l->heure_arrivee))).'</span>';
	}
	if ($peutRenvoyer && $e->etat_special === '' && (!$l || (int) $l->type === ELEVES_RENVOYE)) {
		$renv = $l && (int) $l->type === ELEVES_RENVOYE;
		print '<form method="POST" action="'.dol_escape_htmltag($urlSel).'" onsubmit="return confirm(\''.dol_escape_js($langs->transnoentities($renv ? 'EspConfirmAnnulerRenvoi' : 'EspConfirmRenvoi', ecole_label($e))).'\')">';
		print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="renvoi"><input type="hidden" name="eleve" value="'.espace_jeton('eleve', $eid).'">';
		print '<button type="submit" class="es-btn es-btn-sm '.($renv ? 'es-btn-light' : 'es-btn-purple').'">'.($renv ? $langs->trans('EspAnnulerRenvoi') : '<i class="fas fa-door-open"></i> '.$langs->trans('Renvoye')).'</button></form>';
	}
	print '</div></div>';
}
if (empty($eleves)) {
	print espace_msg($langs->trans('AucunEleveClasseDate'), 'info');
}
print '</div>';
if ($peutRenvoyer) {
	print '<p class="es-muted es-small">'.$langs->trans('RenvoyeCoursAide').'</p>';
}

espace_footer();
$db->close();
