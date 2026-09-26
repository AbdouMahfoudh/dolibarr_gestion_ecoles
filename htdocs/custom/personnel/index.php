<?php
/**
 * Accueil du module Personnel : effectifs par catégorie et par statut, présence des enseignants aujourd'hui,
 * employés absents aujourd'hui, fins de période d'essai et de contrat proches, dossiers incomplets.
 *
 * Fichier : custom/personnel/index.php
 */

require 'init.php';
dol_include_once('/personnel/core/lib/presence.lib.php');
dol_include_once('/classes/core/lib/dashboard.lib.php');

$langs->loadLangs(array('personnel@personnel', 'classes@classes', 'other'));

if (!$user->hasRight('personnel', 'employe', 'lire')) {
	accessforbidden();
}
$p = $db->prefix();
$aujourdhui = personnel_aujourdhui();

llxHeader('', $langs->trans('EdTableauPersonnel'), '', '', 0, 0, '', '', '', 'mod-personnel page-index');
print ecole_dash_hero($langs->trans('EdTableauPersonnel'));

// Chiffres clés et graphiques
$st = ecole_dash_personnel();
$auj = ecole_dash_aujourdhui();
$cats = personnel_categories_choix();
$urlL = dol_buildpath('/personnel/employe/list.php', 1);
$kpis = array(
	array($langs->trans('EdEmployesActifs'), $st['actifs'], 'fa-id-badge', 'blue', $urlL.'?search_status=1,2'),
	array($langs->trans('EdEnseignants'), isset($st['categories']['enseignant']) ? $st['categories']['enseignant'] : 0, 'fa-chalkboard-teacher', 'purple', $urlL.'?search_extra_categories=enseignant&search_status=1,2,3'),
	array($langs->trans('EdEnPeriodeEssai'), isset($st['statut'][2]) ? $st['statut'][2] : 0, 'fa-user-clock', 'orange', $urlL.'?search_status=2'),
	array($langs->trans('EdAbsentsAujourdhui'), $auj['profs_absents'], 'fa-user-times', $auj['profs_absents'] ? 'red' : 'green', dol_buildpath('/personnel/presence.php', 1)),
	array($langs->trans('EdCoursAujourdhui'), $auj['cours'], 'fa-calendar-day', 'teal', dol_buildpath('/personnel/presence.php', 1)),
);
if (isModEnabled('salaires') && $user->hasRight('salaires', 'bulletin', 'lire')) {
	$sal = ecole_dash_salaires();
	$kpis[] = array($langs->trans('EdMasseSalarialeMois'), ecole_dash_montant($sal['net']), 'fa-money-check-alt', 'pink', dol_buildpath('/salaires/index.php', 1), $langs->trans('EdBulletinsPayes', $sal['paye'], $sal['brouillon'] + $sal['valide'] + $sal['paye']));
}
print ecole_dash_kpis($kpis);
$data = array();
foreach ($st['categories'] as $k => $n) {
	$data[] = array(isset($cats[$k]) ? $cats[$k] : $k, $n);
}
print '<div class="ed-cols">'.ecole_dash_box($langs->trans('EffectifsParCategorie'), 'fa-chart-pie', ecole_dash_graph('perso_cat', 'pie', $data));
print ecole_dash_box($langs->trans('AccesRapides'), 'fa-bolt', ecole_dash_links(array(
	array($user->hasRight('personnel', 'employe', 'creer'), dol_buildpath('/personnel/employe/card.php', 1).'?action=create', $langs->trans('NouvelEmploye'), 'fa-plus'),
	array($user->hasRight('personnel', 'presence', 'lire'), dol_buildpath('/personnel/presence.php', 1), $langs->trans('EdPresenceDuJour'), 'fa-clipboard-check'),
	array($user->hasRight('personnel', 'presence', 'lire'), dol_buildpath('/personnel/heures.php', 1), $langs->trans('EdHeuresDuMois'), 'fa-clock'),
	array($user->hasRight('personnel', 'absence', 'lire'), dol_buildpath('/personnel/absence/list.php', 1), $langs->trans('EdAbsencesPersonnel'), 'fa-user-clock'),
	array(isModEnabled('salaires') && $user->hasRight('salaires', 'bulletin', 'lire'), dol_buildpath('/salaires/index.php', 1), $langs->trans('MenuSalaires'), 'fa-money-check-alt'),
))).'</div>';

print '<div class="fichecenter"><div class="fichethirdleft">';

// Effectifs par catégorie (employés présents à l'école)
$urlListe = dol_buildpath('/personnel/employe/list.php', 1);
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('EffectifsParCategorie').'</td><td class="right">'.$langs->trans('Nombre').'</td></tr>';
$total = 0;
foreach (personnel_categories_choix() as $k => $l) {
	$resql = $db->query("SELECT COUNT(*) as nb FROM ".$p."ecole_employe WHERE entity IN (".getEntity('ecole_employe').") AND status <> 4 AND FIND_IN_SET('".$db->escape($k)."', categories) > 0");
	$o = $resql ? $db->fetch_object($resql) : null;
	$nb = $o ? (int) $o->nb : 0;
	if ($nb) {
		print '<tr class="oddeven"><td><a href="'.$urlListe.'?search_extra_categories='.$k.'&search_status=1,2,3">'.dol_escape_htmltag($l).'</a></td><td class="right">'.$nb.'</td></tr>';
	}
}
$resql = $db->query("SELECT status, COUNT(*) as nb FROM ".$p."ecole_employe WHERE entity IN (".getEntity('ecole_employe').") GROUP BY status");
$parstatut = array();
while ($resql && ($o = $db->fetch_object($resql))) {
	$parstatut[(int) $o->status] = (int) $o->nb;
}
print '<tr class="liste_titre"><td>'.$langs->trans('StatutEmploye').'</td><td></td></tr>';
$e = new EcoleEmploye($db);
foreach (EcoleEmploye::statusLabels() as $s => $l) {
	print '<tr class="oddeven"><td><a href="'.$urlListe.'?search_status='.$s.'">'.$e->LibStatut($s, 5).'</a></td><td class="right">'.(isset($parstatut[$s]) ? $parstatut[$s] : 0).'</td></tr>';
	$total += ($s !== EcoleEmploye::STATUS_PARTI && isset($parstatut[$s])) ? $parstatut[$s] : 0;
}
print '<tr class="liste_total"><td>'.$langs->trans('EmployesPresents').'</td><td class="right">'.$total.'</td></tr>';
print '</table></div><br>';

// Dossiers incomplets
if ($user->hasRight('personnel', 'document', 'lire')) {
	$resql = $db->query("SELECT COUNT(*) as nb FROM ".$p."ecole_employe t WHERE t.entity IN (".getEntity('ecole_employe').") AND t.status <> 4 AND ".EcoleEmploye::sqlPiecesManquantes($db));
	$o = $resql ? $db->fetch_object($resql) : null;
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans('BlocDocuments').'</td><td></td></tr>';
	print '<tr class="oddeven"><td><a href="'.$urlListe.'?search_extra_pieces=manquantes&search_status=1,2,3">'.$langs->trans('DossiersIncomplets').'</a></td><td class="right">'.($o && $o->nb ? '<span class="badge badge-danger">'.((int) $o->nb).'</span>' : '0').'</td></tr>';
	print '</table></div>';
}

print '</div><div class="fichetwothirdright">';

// Présence des enseignants aujourd'hui
if ($user->hasRight('personnel', 'presence', 'lire')) {
	$cours = personnel_cours_jour($db, $aujourdhui);
	$nb = array('total' => 0, 'absent' => 0, 'retard' => 0, 'declare' => 0, 'remplace' => 0);
	$absents = array();
	foreach ($cours as $c) {
		if ($c->etat === 'sanscours') {
			continue;
		}
		$nb['total']++;
		if (isset($nb[$c->etat])) {
			$nb[$c->etat]++;
		}
		if ($c->rec && (int) $c->rec->fk_remplacant > 0) {
			$nb['remplace']++;
		}
		if ($c->etat === 'absent') {
			$absents[] = $c;
		}
	}
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="4">'.img_picto('', 'fa-chalkboard-teacher', 'class="pictofixedwidth"').$langs->trans('PresenceEnseignantsAujourdhui').' <a class="floatright" href="'.dol_buildpath('/personnel/presence.php', 1).'">'.$langs->trans('VoirTout').'</a></td></tr>';
	print '<tr class="oddeven"><td colspan="4">'.$langs->trans('ResumeJourPresence', $nb['total'], $nb['total'] - $nb['absent'], $nb['declare'], $langs->transnoentities('NbAbsencesRemplacees', $nb['absent'], $nb['remplace'], $nb['retard'])).'</td></tr>';
	foreach ($absents as $c) {
		print '<tr class="oddeven"><td class="nowraponall">'.dol_escape_htmltag($c->crref).' <span class="opacitymedium small">'.$c->heure_debut.'</span></td><td>'.dol_escape_htmltag($c->cref).'</td>';
		print '<td>'.dol_escape_htmltag(ecole_label($c)).'</td><td>'.($c->rec && (int) $c->rec->fk_remplacant > 0 ? '<span class="small">'.$langs->trans('RemplacePar', dol_escape_htmltag(personnel_employe_nom($db, (int) $c->rec->fk_remplacant))).'</span>'
			: '<a class="button smallpaddingimp" href="'.dol_buildpath('/personnel/presence.php', 1).'?date='.$aujourdhui.'&fk_classe='.$c->fk_classe.'&fk_creneau='.$c->fk_creneau.'#cours">'.$langs->trans('Gerer').'</a>').'</td></tr>';
	}
	print '</table></div><br>';
}

// Employés absents aujourd'hui
if ($user->hasRight('personnel', 'absence', 'lire')) {
	$total = 0;
	$abs = personnel_absences_liste($db, array('du' => $aujourdhui, 'au' => $aujourdhui, 'employe' => '', 'fk_employe' => 0, 'categorie' => '', 'duree' => '', 'motif' => 0, 'etat' => '', 'statut' => ''), 0, 0, $total);
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="3">'.img_picto('', 'fa-user-clock', 'class="pictofixedwidth"').$langs->trans('AbsentsAujourdhui').' ('.count($abs).') <a class="floatright" href="'.dol_buildpath('/personnel/absence/list.php', 1).'">'.$langs->trans('VoirTout').'</a></td></tr>';
	foreach ($abs as $a) {
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($a->ref.' '.ecole_label($a)).'</td><td>'.dol_escape_htmltag(personnel_absence_periode_label($a)).'</td>';
		print '<td>'.((int) $a->justifiee ? '<span class="badge badge-status4">'.$langs->trans('Justifiee').'</span>' : '<span class="badge badge-danger">'.$langs->trans('NonJustifiee').'</span>').'</td></tr>';
	}
	if (empty($abs)) {
		print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans('PersonneAbsent').'</span></td></tr>';
	}
	print '</table></div><br>';
}

// Échéances : fins de période d'essai et de contrat dans les 30 jours
$dans30 = dol_print_date(dol_time_plus_duree(dol_now(), 30, 'd'), '%Y-%m-%d');
$sql = "SELECT rowid, ref, nom_fr, nom_ar, status, date_fin_essai, date_fin_contrat FROM ".$p."ecole_employe WHERE entity IN (".getEntity('ecole_employe').") AND status <> 4";
$sql .= " AND ((status = 2 AND date_fin_essai IS NOT NULL AND date_fin_essai <= '".$dans30."') OR (date_fin_contrat IS NOT NULL AND date_fin_contrat <= '".$dans30."')) ORDER BY LEAST(IFNULL(date_fin_essai, '9999-12-31'), IFNULL(date_fin_contrat, '9999-12-31'))";
$resql = $db->query($sql);
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="3">'.img_picto('', 'fa-calendar-check', 'class="pictofixedwidth"').$langs->trans('EcheancesProches').'</td></tr>';
$n = 0;
while ($resql && ($o = $db->fetch_object($resql))) {
	$n++;
	$essai = ((int) $o->status === 2 && $o->date_fin_essai && substr($o->date_fin_essai, 0, 10) <= $dans30);
	$d = $essai ? $o->date_fin_essai : $o->date_fin_contrat;
	$passe = substr($d, 0, 10) < $aujourdhui;
	print '<tr class="oddeven"><td><a href="'.dol_buildpath('/personnel/employe/card.php', 1).'?id='.((int) $o->rowid).'">'.dol_escape_htmltag($o->ref).'</a> '.dol_escape_htmltag(ecole_label($o)).'</td>';
	print '<td>'.$langs->trans($essai ? 'FinEssai' : 'FinContrat').'</td><td class="nowraponall">'.($passe ? '<span class="badge badge-danger">' : '').dol_print_date($db->jdate($d), 'day').($passe ? '</span>' : '').'</td></tr>';
}
if (!$n) {
	print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans('AucuneEcheance').'</span></td></tr>';
}
print '</table></div>';

print '</div></div>';

llxFooter();
$db->close();
