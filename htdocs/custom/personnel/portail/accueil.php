<?php
/**
 * Accueil de l'espace d'un employé : bonjour, cours du jour (enseignant, avec « Déclarer »), appel du jour
 * (surveillant), heures du mois, raccourcis vers les rubriques.
 *
 * Fichier : custom/personnel/portail/accueil.php
 */

require_once __DIR__.'/init_espace.php';
dol_include_once('/eleves/core/lib/discipline.lib.php');

list($acces, $emp, $moi, $rubriques) = pe_session($db);
$aujourdhui = personnel_aujourdhui();
$mois = substr($aujourdhui, 0, 7);

pe_header($langs->trans('EspAccueil'), $acces, $emp, $rubriques, 'accueil');

print '<h1 class="es-hello">'.$langs->trans('Bonjour').' '.dol_escape_htmltag(ecole_label($emp)).'</h1>';
print '<p class="es-muted">'.dol_escape_htmltag(trim(personnel_categories_texte((string) $emp->categories).($emp->poste ? ' · '.$emp->poste : ''), ' ·')).' · <span dir="ltr">'.dol_escape_htmltag($emp->ref).'</span></p>';
if ((int) $emp->status === EcoleEmploye::STATUS_SUSPENDU) {
	print espace_msg($langs->trans('EspVousEtesSuspendu'), 'warn');
}

// Cours du jour (enseignant)
if (isset($rubriques['cours'])) {
	$cours = personnel_cours_jour($db, $aujourdhui, (int) $emp->id);
	print '<section class="es-card"><h2><i class="fas fa-chalkboard-teacher"></i> '.$langs->trans('EspCoursAujourdhui').' <span class="es-muted es-small">'.dol_escape_htmltag(personnel_date_label($aujourdhui)).'</span></h2>';
	if (empty($cours)) {
		print '<div class="es-muted">'.$langs->trans('EspPasDeCoursAujourdhui').'</div>';
	}
	foreach ($cours as $c) {
		$rempl = $c->rec && (int) $c->rec->fk_remplacant === (int) $emp->id;
		$etat = $rempl ? 'remplacement' : $c->etat;
		$info = ($etat === 'retard' && $c->rec) ? '('.$langs->trans('NbMinutes', (int) $c->rec->minutes_retard).')' : '';
		print '<a class="es-course es-c'.(((int) $c->fk_classe) % 10).' es-course-link" href="'.dol_escape_htmltag(espace_page_url('cours/'.$aujourdhui.'/'.pe_jeton_cours($c->fk_classe, $c->fk_creneau))).'">';
		print '<span class="es-course-time" dir="ltr">'.dol_escape_htmltag($c->heure_debut.' - '.$c->heure_fin).'</span>';
		print '<span class="es-course-title">'.dol_escape_htmltag($c->cref.' · '.pe_label($c, 'm_')).'</span>';
		print '<span>'.pe_etat_chip($etat, $info).'</span></a>';
	}
	print '</section>';
}

// Appel du jour (surveillant)
if (isset($rubriques['appel'])) {
	$appels = eleves_appels_du_jour($db, $aujourdhui);
	$prevus = 0;
	$faits = 0;
	$resql = $db->query("SELECT c.rowid FROM ".$db->prefix()."ecole_classe c WHERE c.status = 1 AND EXISTS (SELECT 1 FROM ".$db->prefix()."ecole_eleve e WHERE e.fk_classe = c.rowid AND e.status IN (".implode(',', EcoleEleve::statusOccupantPlace())."))");
	$classes = array();
	while ($resql && ($o = $db->fetch_object($resql))) {
		$classes[] = (int) $o->rowid;
	}
	foreach ($classes as $cid) {
		foreach (array_keys(eleves_appel_creneaux($db, $cid, $aujourdhui)) as $crid) {
			$prevus++;
			$faits += isset($appels[$cid.'|'.$crid]) ? 1 : 0;
		}
	}
	print '<section class="es-card"><h2><i class="fas fa-clipboard-check"></i> '.$langs->trans('EspAppelDuJour').'</h2>';
	print '<div class="es-kpis"><div class="es-kpi"><span>'.$langs->trans('EspAppelsFaits').'</span><b>'.$faits.' / '.$prevus.'</b></div>';
	print '<div class="es-kpi"><span>'.$langs->trans('Absents').'</span><b>'.array_sum(array_map(function ($a) {
		return (int) $a->nb_absents;
	}, $appels)).'</b></div>';
	print '<div class="es-kpi"><span>'.$langs->trans('Retards').'</span><b>'.array_sum(array_map(function ($a) {
		return (int) $a->nb_retards;
	}, $appels)).'</b></div></div>';
	print '<a class="es-btn es-btn-primary es-btn-block" href="'.dol_escape_htmltag(espace_page_url('appel')).'"><i class="fas fa-clipboard-check"></i> '.$langs->trans('EspFaireAppel').'</a>';
	print '</section>';
}

// Le mois en cours
$h = personnel_heures_mois($db, $emp, $mois);
$t = $h['totaux'];
print '<section class="es-card"><h2><i class="fas fa-coins"></i> '.dol_escape_htmltag(personnel_mois_label($mois)).'</h2>';
if ($t['nb_prevues'] > 0 || $t['nb_remplacements'] > 0 || isset($rubriques['cours'])) {
	print '<div class="es-kpis">';
	print '<div class="es-kpi"><span>'.$langs->trans('CoursFaits').'</span><b>'.pe_duree($t['faites']).'</b><small>/ '.pe_duree($t['prevues']).'</small></div>';
	print '<div class="es-kpi'.($t['nb_absences'] ? ' es-kpi-red' : '').'"><span>'.$langs->trans('Absences').'</span><b>'.((int) $t['nb_absences']).'</b></div>';
	print '<div class="es-kpi"><span>'.$langs->trans('RemplacementsFaits').'</span><b>'.((int) $t['nb_remplacements']).'</b></div>';
	print '</div>';
} else {
	print '<div class="es-kpis"><div class="es-kpi"><span>'.$langs->trans('JoursOuvrables').'</span><b>'.((int) $t['jours_ouvrables']).'</b></div>';
	print '<div class="es-kpi'.($t['jours_absent_nj'] ? ' es-kpi-red' : '').'"><span>'.$langs->trans('JoursAbsence').'</span><b dir="ltr">'.price2num($t['jours_absent_j'] + $t['jours_absent_nj']).'</b></div></div>';
}
if ($h['estimation']['possible']) {
	print '<div class="es-line"><span class="es-muted">'.$langs->trans('EstimationMois').'</span>'.espace_montant($h['estimation']['total']).'</div>';
}
print '<a class="es-btn es-btn-light es-btn-block" href="'.dol_escape_htmltag(espace_page_url('heures')).'">'.$langs->trans('EspVoirDetail').'</a>';
print '</section>';

// Raccourcis
print '<div class="es-shortcuts">';
foreach ($rubriques as $k => $r) {
	if ($k === 'accueil') {
		continue;
	}
	print '<a class="es-card es-shortcut" href="'.dol_escape_htmltag(espace_page_url($r[2])).'"><i class="fas '.$r[1].'"></i><span>'.$langs->trans($r[0]).'</span></a>';
}
print '</div>';

espace_footer();
$db->close();
