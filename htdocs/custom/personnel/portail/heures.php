<?php
/**
 * Heures et paie dans l'espace d'un employé : résumé du mois (cours prévus et faits, absences, retards,
 * remplacements, heures supplémentaires, jours d'absence), estimation de la paie du mois, cours du mois
 * (enseignant), et lien vers « Mes salaires » (module Salaires ; sinon salaires Dolibarr versés).
 *
 *   heures[/<AAAA-MM>]
 *
 * Fichier : custom/personnel/portail/heures.php
 */

require_once __DIR__.'/init_espace.php';

list($acces, $emp, $moi, $rubriques) = pe_session($db, 'heures');
$mois = personnel_mois_ok(pe_arg(0));
if ($mois === '') {
	$mois = substr(personnel_aujourdhui(), 0, 7);
}
$h = personnel_heures_mois($db, $emp, $mois);
$t = $h['totaux'];
$enseignant = !empty($h['seances']) || $emp->aCategorie('enseignant');

pe_header($langs->trans('EspHeuresPaie'), $acces, $emp, $rubriques, 'heures');

$prec = date('Y-m', strtotime($mois.'-01 -1 month'));
$suiv = date('Y-m', strtotime($mois.'-01 +1 month'));
print '<div class="es-daynav"><a class="es-btn es-btn-light es-btn-sm" href="'.dol_escape_htmltag(espace_page_url('heures/'.$prec)).'"><i class="fas fa-chevron-'.(espace_rtl() ? 'right' : 'left').'"></i></a>';
print '<b>'.dol_escape_htmltag(personnel_mois_label($mois)).'</b>';
print '<a class="es-btn es-btn-light es-btn-sm" href="'.dol_escape_htmltag(espace_page_url('heures/'.$suiv)).'"><i class="fas fa-chevron-'.(espace_rtl() ? 'left' : 'right').'"></i></a></div>';

// Résumé
print '<section class="es-card">';
if ($enseignant) {
	print '<div class="es-kpis es-kpis-4">';
	print '<div class="es-kpi"><span>'.$langs->trans('CoursPrevus').'</span><b>'.pe_duree($t['prevues']).'</b><small>'.$langs->trans('NbSeances', $t['nb_prevues']).'</small></div>';
	print '<div class="es-kpi es-kpi-green"><span>'.$langs->trans('CoursFaits').'</span><b>'.pe_duree($t['faites']).'</b><small>'.$langs->trans('NbDeclares', $t['nb_declares']).'</small></div>';
	print '<div class="es-kpi'.($t['nb_absences'] ? ' es-kpi-red' : '').'"><span>'.$langs->trans('Absences').'</span><b>'.((int) $t['nb_absences']).'</b></div>';
	print '<div class="es-kpi"><span>'.$langs->trans('Retards').'</span><b>'.((int) $t['nb_retards']).'</b>'.($t['retard_min'] ? '<small>'.$langs->trans('NbMinutes', $t['retard_min']).'</small>' : '').'</div>';
	print '</div>';
	if ($t['nb_remplacements']) {
		print '<div class="es-line"><span class="es-muted">'.$langs->trans('RemplacementsFaits').'</span><span>'.$t['nb_remplacements'].' · '.pe_duree($t['remplacement']).'</span></div>';
	}
	if ($t['heures_sup']) {
		print '<div class="es-line"><span class="es-muted">'.$langs->trans('HeuresSup').'</span>'.pe_duree($t['heures_sup']).'</div>';
	}
	if ($t['avenir']) {
		print '<div class="es-line"><span class="es-muted">'.$langs->trans('AVenir').'</span>'.pe_duree($t['avenir']).'</div>';
	}
}
print '<div class="es-line"><span class="es-muted">'.$langs->trans('JoursOuvrables').'</span><b>'.((int) $t['jours_ouvrables']).'</b></div>';
print '<div class="es-line"><span class="es-muted">'.$langs->trans('JoursAbsence').'</span><span>'.dol_escape_htmltag($langs->transnoentities('AbsJustNonJust', price2num($t['jours_absent_j']), price2num($t['jours_absent_nj']))).'</span></div>';
print '</section>';

// Estimation de la paie
$est = $h['estimation'];
print '<section class="es-card"><h2><i class="fas fa-coins"></i> '.$langs->trans('EstimationMois').'</h2>';
if (!$est['possible']) {
	print '<div class="es-muted">'.$langs->trans('EspEstimationIndisponible').'</div>';
} else {
	foreach ($est['lignes'] as $l) {
		print '<div class="es-line"><span>'.dol_escape_htmltag($l[0]).'</span>'.espace_montant($l[1]).'</div>';
	}
	print '<div class="es-line es-line-total"><b>'.$langs->trans('TotalEstime').'</b><b>'.espace_montant($est['total']).'</b></div>';
}
print '<p class="es-muted es-small">'.$langs->trans('EstimationAide').'</p></section>';

// Cours du mois
if ($enseignant && !empty($h['seances'])) {
	print '<details class="es-card"><summary><b>'.$langs->trans('CoursDuMois').'</b> <span class="es-muted es-small">('.count($h['seances']).')</span></summary>';
	foreach ($h['seances'] as $s) {
		$info = ($s->etat === 'retard') ? '('.$langs->trans('NbMinutes', $s->retard).')' : '';
		print '<div class="es-line"><span><span class="es-small">'.dol_escape_htmltag(personnel_date_label($s->date)).'</span><br><span class="es-muted es-small" dir="ltr">'.dol_escape_htmltag($s->heure_debut).'</span> '.dol_escape_htmltag($s->cref.' · '.$s->matiere).'</span>'.pe_etat_chip($s->etat, $info).'</div>';
	}
	print '</details>';
}

// Salaires versés : rubrique « Mes salaires » (module Salaires), sinon salaires Dolibarr
if (isset($rubriques['salaires'])) {
	$langs->load('salaires@salaires');
	print '<a class="es-card es-item" href="'.dol_escape_htmltag(espace_page_url('salaires')).'"><span class="es-item-main"><b><i class="fas fa-money-check-alt"></i> '.$langs->trans('EspMesSalaires').'</b>';
	print '<span class="es-muted es-small">'.$langs->trans('EspMesSalairesAide').'</span></span><i class="fas fa-chevron-'.(espace_rtl() ? 'left' : 'right').'"></i></a>';
} elseif ((int) $emp->fk_user > 0) {
	$resql = $db->query("SELECT label, datesp, dateep, amount, paye FROM ".$db->prefix()."salary WHERE fk_user = ".((int) $emp->fk_user)." ORDER BY datesp DESC, rowid DESC LIMIT 24");
	print '<h2 class="es-h2"><i class="fas fa-money-check-alt"></i> '.$langs->trans('EspSalairesVerses').'</h2><div class="es-list">';
	$n = 0;
	while ($resql && ($o = $db->fetch_object($resql))) {
		$n++;
		print '<div class="es-card es-item"><div class="es-item-main"><b>'.dol_escape_htmltag($o->label ? $o->label : $langs->trans('Salaire')).'</b>';
		print '<span class="es-muted es-small">'.dol_escape_htmltag($langs->transnoentities('DuAu', dol_print_date($db->jdate($o->datesp), 'day'), dol_print_date($db->jdate($o->dateep), 'day'))).'</span></div>';
		print '<div class="es-item-side">'.espace_montant($o->amount).'<span class="es-chip '.((int) $o->paye ? 'es-chip-green' : 'es-chip-orange').'">'.$langs->trans((int) $o->paye ? 'EspPaye' : 'EspNonPaye').'</span></div></div>';
	}
	if (!$n) {
		print espace_msg($langs->trans('EspAucunSalaire'), 'info');
	}
	print '</div>';
}

espace_footer();
$db->close();
