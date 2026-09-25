<?php
/**
 * Appel des élèves (surveillant).
 *
 * Sans classe choisie : tableau du jour (classes × créneaux) « appel fait / pas fait », simple repère.
 * Avec une classe et un créneau : la liste des élèves, tous présents par défaut ; on coche « Absent » ou
 * « Retard » (heure d'arrivée facultative) ou « Renvoyé » (renvoyé du cours par le professeur : absence simple,
 * pas une sanction), puis on valide. Un appel déjà validé se corrige le jour même avec le droit « faire l'appel »,
 * plus tard avec le droit « corriger » (chaque changement est gardé). Après l'appel : boutons WhatsApp.
 *
 * Fichier : custom/eleves/appel.php
 */

require 'init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/classes/class/ecole_classe.class.php');
dol_include_once('/eleves/core/lib/discipline.lib.php');

$langs->loadLangs(array('eleves@eleves', 'classes@classes', 'other'));

$canappel = $user->hasRight('eleves', 'absence', 'appel');
$canmodif = $user->hasRight('eleves', 'absence', 'modifier');
$canlire = $user->hasRight('eleves', 'absence', 'lire');
if (!$canappel && !$canlire) {
	accessforbidden();
}

$form = new Form($db);
$self = $_SERVER['PHP_SELF'];
$action = GETPOST('action', 'aZ09');
$aujourdhui = eleves_aujourdhui();
$date = eleves_date_ok(GETPOST('date', 'alpha'));
if ($date === '' || $date > $aujourdhui) {
	$date = $aujourdhui; // pas d'appel dans le futur
}
$fk_classe = GETPOSTINT('fk_classe');
$fk_creneau = GETPOSTINT('fk_creneau');

$classe = null;
if ($fk_classe > 0) {
	$classe = new EcoleClasse($db);
	if ($classe->fetch($fk_classe) <= 0) {
		$classe = null;
		$fk_classe = 0;
	}
}
$creneauxClasse = $classe ? eleves_appel_creneaux($db, $fk_classe, $date) : array();
if ($classe && !isset($creneauxClasse[$fk_creneau]) && $action !== 'save') {
	// Créneau par défaut : celui en cours (aujourd'hui), sinon le premier de la journée
	$fk_creneau = ($date === $aujourdhui) ? eleves_creneau_courant($creneauxClasse) : (int) key($creneauxClasse);
}
$urlAppel = $self.'?date='.urlencode($date).'&fk_classe='.$fk_classe.'&fk_creneau='.$fk_creneau;

/*
 * Actions
 */
if ($action === 'save' && $classe && $fk_creneau > 0) {
	$appel = eleves_appel_fetch($db, $fk_classe, $date, $fk_creneau);
	if (($appel && !$canmodif && !($canappel && $date === $aujourdhui)) || (!$appel && !$canappel)) {
		accessforbidden();
	}
	if (!$appel && !isset($creneauxClasse[$fk_creneau])) {
		setEventMessages($langs->trans('ErrorPasDeCoursCreneau'), null, 'errors');
	} else {
		$abs = GETPOST('abs', 'array');
		$ret = GETPOST('ret', 'array');
		$ren = GETPOST('ren', 'array');
		$heures = GETPOST('heure', 'array');
		$etats = array();
		foreach (eleves_appel_eleves($db, $fk_classe, $date) as $eid => $e) {
			if ($e->etat_special !== '') {
				continue; // exclu ou suspendu : rien à relever
			}
			$type = !empty($abs[$eid]) ? ELEVES_ABSENT : (!empty($ret[$eid]) ? ELEVES_RETARD : (!empty($ren[$eid]) ? ELEVES_RENVOYE : ELEVES_PRESENT));
			$etats[$eid] = array('type' => $type, 'heure' => isset($heures[$eid]) ? trim((string) $heures[$eid]) : '');
		}
		$error = '';
		$fk_matiere = isset($creneauxClasse[$fk_creneau]) ? (int) $creneauxClasse[$fk_creneau]['fk_matiere'] : 0;
		$id = eleves_appel_enregistrer($db, $user, $fk_classe, $date, $fk_creneau, $etats, $error, $fk_matiere);
		if ($id > 0 && isModEnabled('personnel')) {
			// Présence de l'enseignant (module Personnel) : présent, absent ou en retard
			dol_include_once('/personnel/core/lib/presence.lib.php');
			$errprof = '';
			if (personnel_appel_enregistrer($db, $user, $date, $fk_classe, $fk_creneau, $errprof) < 0) {
				setEventMessages($errprof, null, 'warnings');
			}
		}
		if ($id > 0) {
			$nb = array(ELEVES_PRESENT => 0, ELEVES_ABSENT => 0, ELEVES_RETARD => 0, ELEVES_RENVOYE => 0);
			foreach ($etats as $e) {
				$nb[$e['type']]++;
			}
			setEventMessages($langs->trans($appel ? 'AppelCorrige' : 'AppelEnregistre', $nb[ELEVES_ABSENT], $nb[ELEVES_RETARD], $nb[ELEVES_RENVOYE]), null, 'mesgs');
			header('Location: '.$urlAppel.'#prevenir');
			exit;
		}
		setEventMessages($error, null, 'errors');
	}
}
if ($classe && !isset($creneauxClasse[$fk_creneau]) && !($fk_creneau > 0 && eleves_appel_fetch($db, $fk_classe, $date, $fk_creneau))) {
	// Créneau sans cours (et sans appel déjà fait) : on revient au créneau proposé par défaut
	$fk_creneau = ($date === $aujourdhui) ? eleves_creneau_courant($creneauxClasse) : (int) key($creneauxClasse);
}

/*
 * Affichage
 */
llxHeader('', $langs->trans('Appel'), '', '', 0, 0, '', '', '', 'mod-eleves page-appel');

print '<style>
/* Boutons de choix (absent / retard / renvoyé, enseignant présent / absent / retard) : la case native est cachée, tout le bouton se touche */
.appel-tog{position:relative;display:inline-flex;align-items:center;gap:7px;min-height:38px;padding:6px 14px;margin:3px 0;margin-inline-end:6px;border:1.5px solid #cfd6de;border-radius:20px;cursor:pointer;user-select:none;white-space:nowrap;background:#fff;color:#4a5560;font-size:.95em;transition:background .12s,border-color .12s}
.appel-tog:hover{border-color:#8a97a3;background:#f7f9fb}
.appel-tog input{position:absolute;opacity:0;width:0;height:0;margin:0;pointer-events:none}
.appel-tog::before{content:"";width:12px;height:12px;border-radius:50%;border:2px solid #b5bec7;background:#fff;flex:none}
.appel-tog.on{font-weight:bold}
.appel-tog.on::before{border-color:currentColor;background:currentColor;box-shadow:inset 0 0 0 2px #fff}
.appel-tog.on.appel-abs{background:#fde2e1;border-color:#d9534f;color:#a94442}
.appel-tog.on.appel-ret{background:#fff3cd;border-color:#f0ad4e;color:#8a6d3b}
.appel-tog.on.appel-ren{background:#ece4f7;border-color:#7b52ab;color:#5b3a86}
.appel-tog:not(.appel-abs):not(.appel-ret):not(.appel-ren).on{background:#e3f4e6;border-color:#4caf50;color:#2d6a2d}
.appel-heure{margin-inline-start:4px;min-height:34px}
/* Tableau des élèves */
table.appel-table{border-collapse:separate;border-spacing:0 6px}
table.appel-table tr.liste_titre td{border:0}
table.appel-table tr[data-eleve] td{background:#fff;border-top:1px solid #e3e8ee;border-bottom:1px solid #e3e8ee;padding-top:8px;padding-bottom:8px}
table.appel-table tr[data-eleve] td:first-child{border-inline-start:1px solid #e3e8ee;border-start-start-radius:10px;border-end-start-radius:10px}
table.appel-table tr[data-eleve] td:last-child{border-inline-end:1px solid #e3e8ee;border-start-end-radius:10px;border-end-end-radius:10px}
.appel-num{font-weight:bold;font-size:1.05em;color:#5b6772}
.appel-nom{cursor:pointer}
.appel-nom b{font-size:1.05em}
tr.appel-row-abs td{background:#fdf0ef !important;border-color:#f0c4c2 !important}
tr.appel-row-ret td{background:#fffaf0 !important;border-color:#f3dcae !important}
tr.appel-row-ren td{background:#f6f1fb !important;border-color:#d8c9ec !important}
/* Bandeau d information de l appel */
table.appel-head{border:1px solid #e3e8ee;border-radius:10px;background:#fafbfc;padding:6px 10px}
table.appel-head td{padding:8px 10px;border:0 !important}
/* Créneaux du jour (fait / à faire) */
.appel-cell{display:inline-block;min-width:80px;padding:7px 14px;border-radius:20px;text-decoration:none;font-weight:600;text-align:center}
.appel-fait{background:#dff0d8;color:#2d6a2d;border:1.5px solid #b9dfae}
.appel-afaire{background:#f2f2f2;color:#666;border:1.5px dashed #bbb}
.appel-cell.appel-cur{box-shadow:0 0 0 3px #b7c4d1}
/* Bouton de validation : reste visible en bas de l écran pendant la saisie */
.appel-bar{position:sticky;bottom:0;z-index:5;text-align:center;padding:10px 0;margin-top:8px;background:linear-gradient(to top,#fff 70%,rgba(255,255,255,0))}
.appel-submit{font-size:1.1em;padding:12px 30px !important;border-radius:24px !important}
@media (max-width:768px){.appel-tog{padding:6px 10px;font-size:.9em}}
</style>';

print load_fiche_titre($langs->trans('Appel'), '', 'fa-clipboard-check');

// Barre de choix : date, classe, créneau
$classesChoix = array();
$nbEleves = array();
$sql = "SELECT c.rowid, c.ref, c.label_fr, c.label_ar, (SELECT COUNT(*) FROM ".$db->prefix()."ecole_eleve e WHERE e.fk_classe = c.rowid AND e.status IN (".implode(',', EcoleEleve::statusOccupantPlace()).")) as nb";
$sql .= " FROM ".$db->prefix()."ecole_classe c LEFT JOIN ".$db->prefix()."ecole_niveau n ON n.rowid = c.fk_niveau";
$sql .= " WHERE c.entity IN (".getEntity('ecole_classe').") AND c.status = 1 ORDER BY n.position, c.rowid";
$resql = $db->query($sql);
while ($resql && ($o = $db->fetch_object($resql))) {
	$classesChoix[(int) $o->rowid] = $o->ref.' - '.ecole_label($o).' ('.((int) $o->nb).')';
	$nbEleves[(int) $o->rowid] = (int) $o->nb;
}
$veille = dol_print_date(dol_time_plus_duree(eleves_date_ts($date), -1, 'd'), '%Y-%m-%d');
$lendemain = dol_print_date(dol_time_plus_duree(eleves_date_ts($date), 1, 'd'), '%Y-%m-%d');
$nav = '&fk_classe='.$fk_classe;

print '<form method="GET" action="'.dol_escape_htmltag($self).'" class="marginbottomonly">';
print '<div class="inline-block valignmiddle marginrightonly nowraponall">';
print '<a class="button smallpaddingimp" href="'.dol_escape_htmltag($self.'?date='.$veille.$nav).'" title="'.dol_escape_htmltag($langs->trans('JourPrecedent')).'">&lsaquo;</a> ';
print '<input type="date" name="date" class="flat" max="'.$aujourdhui.'" value="'.dol_escape_htmltag($date).'" onchange="this.form.submit()"> ';
if ($date < $aujourdhui) {
	print '<a class="button smallpaddingimp" href="'.dol_escape_htmltag($self.'?date='.$lendemain.$nav).'" title="'.dol_escape_htmltag($langs->trans('JourSuivant')).'">&rsaquo;</a> ';
	print '<a class="button smallpaddingimp" href="'.dol_escape_htmltag($self.'?date='.$aujourdhui.$nav).'">'.$langs->trans('Today').'</a>';
}
print ' <b class="marginleftonly">'.dol_escape_htmltag(eleves_date_label($date)).'</b></div> ';
print '<div class="inline-block valignmiddle marginrightonly">'.img_picto('', 'fa-chalkboard', 'class="pictofixedwidth"');
print $form->selectarray('fk_classe', $classesChoix, $fk_classe, $langs->trans('ChoisirClasse'), 0, 0, 'onchange="this.form.fk_creneau && (this.form.fk_creneau.value=\'\'); this.form.submit()"', 0, 0, 0, '', 'minwidth200 maxwidth300', 1).'</div> ';
if ($classe && !empty($creneauxClasse)) {
	$choixCr = array();
	foreach ($creneauxClasse as $cid => $info) {
		$choixCr[$cid] = eleves_creneau_label($info['creneau']).($info['matiere'] !== '' ? ' — '.$info['matiere'] : '');
	}
	print '<div class="inline-block valignmiddle marginrightonly">'.img_picto('', 'fa-clock', 'class="pictofixedwidth"');
	print $form->selectarray('fk_creneau', $choixCr, $fk_creneau, 0, 0, 0, 'onchange="this.form.submit()"', 0, 0, 0, '', 'minwidth200').'</div>';
}
print '<noscript><input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Refresh')).'"></noscript>';
if ($classe) {
	print ' <a class="marginleftonly" href="'.dol_escape_htmltag($self.'?date='.$date).'">'.img_picto('', 'fa-th', 'class="pictofixedwidth"').$langs->trans('TableauDuJour').'</a>';
}
print '</form>';

$jourOuvrable = in_array((int) date('N', strtotime($date)), ecole_jours_ouvrables(), true);
if (!$jourOuvrable) {
	print '<div class="info">'.$langs->trans('JourNonOuvrable').'</div>';
}

if (!$classe) {
	/*
	 * Tableau du jour : classes × créneaux (repère seulement)
	 */
	$creneaux = ecole_creneaux_actifs($db);
	$appels = eleves_appels_du_jour($db, $date);
	$prevus = 0;
	$faits = 0;
	$totA = 0;
	$totR = 0;
	$courant = ($date === $aujourdhui) ? eleves_creneau_courant($creneaux) : 0;

	$html = '';
	$sanseleves = 0;
	foreach ($classesChoix as $cid => $clabel) {
		if (empty($nbEleves[$cid])) {
			$sanseleves++;
			continue;
		}
		$prevusClasse = eleves_appel_creneaux($db, $cid, $date);
		$html .= '<tr class="oddeven"><td class="nowraponall"><a href="'.dol_escape_htmltag($self.'?date='.$date.'&fk_classe='.$cid).'">'.dol_escape_htmltag($clabel).'</a></td>';
		foreach ($creneaux as $crid => $cr) {
			$html .= '<td class="center'.($crid === $courant ? ' liste_titre_sel' : '').'">';
			$url = $self.'?date='.$date.'&fk_classe='.$cid.'&fk_creneau='.$crid;
			if (isset($appels[$cid.'|'.$crid])) {
				$a = $appels[$cid.'|'.$crid];
				$faits++;
				$prevus += isset($prevusClasse[$crid]) ? 1 : 0;
				$totA += (int) $a->nb_absents;
				$totR += (int) $a->nb_retards;
				$html .= '<a class="appel-cell appel-fait" href="'.dol_escape_htmltag($url).'">'.img_picto('', 'fa-check').' ';
				$html .= $langs->trans('NbAbsNbRet', (int) $a->nb_absents, (int) $a->nb_retards).'</a>';
			} elseif (isset($prevusClasse[$crid])) {
				$prevus++;
				$html .= '<a class="appel-cell appel-afaire" href="'.dol_escape_htmltag($url).'">'.$langs->trans('AppelPasFait').'</a>';
			} else {
				$html .= '<span class="opacitymedium" title="'.dol_escape_htmltag($langs->trans('PasDeCoursCreneau')).'">—</span>';
			}
			$html .= '</td>';
		}
		$html .= '</tr>';
	}

	print '<div class="opacitymedium marginbottomonly">'.$langs->trans('TableauDuJourResume', $faits, $prevus, $totA, $totR).'</div>';
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Classe').' <span class="opacitymedium small">('.$langs->trans('NbEleves').')</span></td>';
	foreach ($creneaux as $crid => $cr) {
		print '<td class="center'.($crid === $courant ? ' liste_titre_sel' : '').'">'.dol_escape_htmltag($cr->ref).'<br><span class="opacitymedium small">'.$cr->heure_debut.'-'.$cr->heure_fin.'</span></td>';
	}
	print '</tr>';
	print $html !== '' ? $html : '<tr class="oddeven"><td colspan="'.(count($creneaux) + 1).'"><span class="opacitymedium">'.$langs->trans('AucuneClasseAvecEleves').'</span></td></tr>';
	print '</table></div>';
	print '<br><span class="opacitymedium small">'.$langs->trans('AideTableauDuJour').($sanseleves ? ' '.$langs->trans('ClassesSansElevesMasquees', $sanseleves) : '').'</span>';

	llxFooter();
	$db->close();
	exit;
}

/*
 * Appel d'une classe
 */
// Créneaux de la journée pour cette classe (fait / pas fait)
$appels = eleves_appels_du_jour($db, $date);
if (!empty($creneauxClasse)) {
	print '<div class="marginbottomonly">';
	foreach ($creneauxClasse as $cid => $info) {
		$fait = isset($appels[$fk_classe.'|'.$cid]);
		print '<a class="appel-cell marginrightonly '.($fait ? 'appel-fait' : 'appel-afaire').($cid === $fk_creneau ? ' appel-cur' : '').'" href="'.dol_escape_htmltag($self.'?date='.$date.'&fk_classe='.$fk_classe.'&fk_creneau='.$cid).'">';
		print ($fait ? img_picto('', 'fa-check').' ' : '').dol_escape_htmltag($info['creneau']->ref).' <span class="small">'.$info['creneau']->heure_debut.'</span></a>';
	}
	print '</div>';
}

$tousCreneaux = ecole_creneaux_actifs($db);
if ($fk_creneau <= 0 || (!isset($creneauxClasse[$fk_creneau]) && !isset($tousCreneaux[$fk_creneau]))) {
	print '<div class="warning">'.$langs->trans('PasDeCoursClasseJour').'</div>';
	llxFooter();
	$db->close();
	exit;
}

// Un appel déjà fait reste consultable même si l'emploi du temps a changé depuis
$info = isset($creneauxClasse[$fk_creneau]) ? $creneauxClasse[$fk_creneau] : array('creneau' => $tousCreneaux[$fk_creneau], 'matiere' => '', 'enseignant' => '');
$appel = eleves_appel_fetch($db, $fk_classe, $date, $fk_creneau);
$lignes = $appel ? eleves_appel_lignes($db, (int) $appel->rowid) : array();
$eleves = eleves_appel_eleves($db, $fk_classe, $date);
// Correction : le jour même avec le droit « faire l'appel » (ex. élève renvoyé pendant le cours), ensuite droit « corriger »
$editable = $appel ? ($canmodif || ($canappel && $date === $aujourdhui)) : $canappel;

// Le formulaire commence avant l'en-tête : il porte aussi la présence de l'enseignant (module Personnel)
if ($editable && !empty($eleves)) {
	print '<form method="POST" action="'.dol_escape_htmltag($self).'" id="formappel">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';
	print '<input type="hidden" name="date" value="'.dol_escape_htmltag($date).'">';
	print '<input type="hidden" name="fk_classe" value="'.((int) $fk_classe).'">';
	print '<input type="hidden" name="fk_creneau" value="'.((int) $fk_creneau).'">';
}

// En-tête de l'appel
print '<table class="centpercent tableforfield appel-head marginbottomonly"><tr><td class="titlefield">'.$langs->trans('Classe').'</td><td><b>'.dol_escape_htmltag($classe->ref.' - '.ecole_label($classe)).'</b></td></tr>';
print '<tr><td>'.$langs->trans('Creneau').'</td><td><b>'.dol_escape_htmltag(eleves_creneau_label($info['creneau'])).'</b>';
if ($info['matiere'] !== '') {
	print ' — '.dol_escape_htmltag($info['matiere']).($info['enseignant'] !== '' ? ' <span class="opacitymedium">('.dol_escape_htmltag($info['enseignant']).')</span>' : '');
}
print '</td></tr>';
if (isModEnabled('personnel') && !empty($eleves)) {
	dol_include_once('/personnel/core/lib/presence.lib.php');
	print personnel_appel_ligne($db, $date, $fk_classe, $fk_creneau, $editable);
}
print '<tr><td>'.$langs->trans('EtatAppel').'</td><td>';
if ($appel) {
	print '<span class="badge badge-status4">'.$langs->trans('AppelFait').'</span> <span class="opacitymedium">'.$langs->trans('AppelFaitPar', ecole_user_label($db, $appel->fk_user_creat), dol_print_date($db->jdate($appel->date_creation), 'dayhour', 'tzuserrel')).'</span>';
	if ($editable) {
		print '<br><span class="opacitymedium small">'.$langs->trans('AideCorrectionAppel').'</span>';
	}
} else {
	print '<span class="badge badge-status0">'.$langs->trans('AppelPasFait').'</span> <span class="opacitymedium small">'.$langs->trans('AideAppelPasFait').'</span>';
}
print '</td></tr></table>';

if (empty($eleves)) {
	print '<div class="opacitymedium">'.$langs->trans('AucunEleveClasseDate').'</div>';
	llxFooter();
	$db->close();
	exit;
}

// Liste des élèves (le formulaire est ouvert avant l'en-tête)
if ($editable) {
	print '<div class="opacitymedium marginbottomonly">'.img_picto('', 'fa-info-circle', 'class="pictofixedwidth"').$langs->trans('AideAppel').'</div>';
}
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent appel-table">';
print '<tr class="liste_titre"><td class="center" style="width:40px" title="'.dol_escape_htmltag($langs->trans('NumeroAppel')).'">'.$langs->trans('NumeroAppelCourt').'</td><td>'.$langs->trans('NomComplet').'</td><td>'.$langs->trans('Presence').'</td></tr>';
foreach ($eleves as $eid => $e) {
	$l = isset($lignes[$eid]) ? $lignes[$eid] : null;
	$type = $l ? (int) $l->type : ELEVES_PRESENT;
	$classes = array(ELEVES_PRESENT => '', ELEVES_ABSENT => ' appel-row-abs', ELEVES_RETARD => ' appel-row-ret', ELEVES_RENVOYE => ' appel-row-ren');
	$rowclass = isset($classes[$type]) ? $classes[$type] : '';
	print '<tr class="oddeven'.$rowclass.'" data-eleve="'.$eid.'">';
	print '<td class="center appel-num">'.($e->numero_appel ? (int) $e->numero_appel : '<span class="opacitymedium">—</span>').'</td>';
	print '<td class="appel-nom" title="'.dol_escape_htmltag($e->ref).'"><b>'.dol_escape_htmltag($e->nom_fr).'</b>';
	if (!empty($e->nom_ar)) {
		print ' <span dir="rtl" class="opacitymedium">'.dol_escape_htmltag($e->nom_ar).'</span>';
	}
	print '</td><td class="nowraponall">';
	if ($e->etat_special === 'exclu') {
		print '<span class="badge badge-status8">'.$langs->trans('ExcluTemporairement').'</span>';
	} elseif ($e->etat_special === 'suspendu') {
		print '<span class="badge badge-status7">'.$langs->trans('StatutSuspendu').'</span>';
	} elseif ($editable) {
		$heure = ($l && $l->heure_arrivee) ? $l->heure_arrivee : '';
		print '<label class="appel-tog appel-abs'.($type === ELEVES_ABSENT ? ' on' : '').'"><input type="checkbox" name="abs['.$eid.']" value="1"'.($type === ELEVES_ABSENT ? ' checked' : '').'>'.$langs->trans('Absent').'</label>';
		print '<label class="appel-tog appel-ret'.($type === ELEVES_RETARD ? ' on' : '').'"><input type="checkbox" name="ret['.$eid.']" value="1"'.($type === ELEVES_RETARD ? ' checked' : '').'>'.$langs->trans('Retard').'</label>';
		print '<label class="appel-tog appel-ren'.($type === ELEVES_RENVOYE ? ' on' : '').'" title="'.dol_escape_htmltag($langs->trans('RenvoyeCoursAide')).'"><input type="checkbox" name="ren['.$eid.']" value="1"'.($type === ELEVES_RENVOYE ? ' checked' : '').'>'.$langs->trans('Renvoye').'</label>';
		print '<input type="time" name="heure['.$eid.']" class="flat appel-heure" value="'.dol_escape_htmltag($heure).'" title="'.dol_escape_htmltag($langs->trans('HeureArriveeFacultative')).'"'.($type === ELEVES_RETARD ? '' : ' style="display:none"').'>';
	} else {
		print $l ? eleves_presence_badge($l) : '<span class="opacitymedium">'.$langs->trans('Present').'</span>';
	}
	print '</td></tr>';
}
print '</table></div>';

if ($editable) {
	print '<div class="appel-bar"><button type="submit" class="button button-save appel-submit" id="appel-submit">'.img_picto('', 'fa-check', 'class="pictofixedwidth"').'<span id="appel-libelle"></span></button></div>';
	print '</form>';
	$libValider = dol_escape_js($langs->transnoentities('ValiderAppel'), 2);
	$libValiderNb = dol_escape_js($langs->transnoentities('ValiderAppelNb', '%s', '%s', '%s'), 2); // nombres remplacés en direct par le script
	print '<script>
(function () {
	var form = document.getElementById("formappel");
	function maj() {
		var a = 0, r = 0, x = 0;
		form.querySelectorAll("tr[data-eleve]").forEach(function (tr) {
			var abs = tr.querySelector("input[name^=abs]"), ret = tr.querySelector("input[name^=ret]"), ren = tr.querySelector("input[name^=ren]"), h = tr.querySelector(".appel-heure");
			if (!abs) { return; }
			abs.parentNode.classList.toggle("on", abs.checked);
			ret.parentNode.classList.toggle("on", ret.checked);
			ren.parentNode.classList.toggle("on", ren.checked);
			tr.classList.toggle("appel-row-abs", abs.checked);
			tr.classList.toggle("appel-row-ret", ret.checked);
			tr.classList.toggle("appel-row-ren", ren.checked);
			h.style.display = ret.checked ? "" : "none";
			a += abs.checked ? 1 : 0;
			r += ret.checked ? 1 : 0;
			x += ren.checked ? 1 : 0;
		});
		document.getElementById("appel-libelle").textContent = (a + r + x) ? "'.$libValiderNb.'".replace("%s", a).replace("%s", r).replace("%s", x) : "'.$libValider.'";
	}
	form.addEventListener("change", function (ev) {
		var t = ev.target, tr = t.closest("tr");
		if (t.checked && tr && tr.hasAttribute("data-eleve")) {
			// Un seul choix par élève : absent, retard ou renvoyé
			["abs", "ret", "ren"].forEach(function (k) {
				if (t.name.indexOf(k) !== 0) { tr.querySelector("input[name^=" + k + "]").checked = false; }
			});
			if (t.name.indexOf("ret") === 0) {
				var h = tr.querySelector(".appel-heure");
				if (!h.value) { var d = new Date(); h.value = ("0" + d.getHours()).slice(-2) + ":" + ("0" + d.getMinutes()).slice(-2); }
			}
		}
		maj();
	});
	// Toucher le nom de l élève = cocher / décocher « Absent »
	form.querySelectorAll(".appel-nom").forEach(function (td) {
		td.addEventListener("click", function () {
			var abs = td.parentNode.querySelector("input[name^=abs]");
			if (abs) { abs.checked = !abs.checked; abs.dispatchEvent(new Event("change", {bubbles: true})); }
		});
	});
	maj();
})();
</script>';
} elseif ($appel === null) {
	print '<br><span class="opacitymedium">'.$langs->trans('PasLeDroitAppel').'</span>';
}

// Prévenir les parents (après l'appel)
if ($appel && !empty($lignes)) {
	print '<br><a id="prevenir"></a>';
	print load_fiche_titre($langs->trans('PrevenirParents'), '', 'fa-bell');
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('NomComplet').'</td><td>'.$langs->trans('Presence').'</td><td>'.$langs->trans('Responsable').'</td><td>'.$langs->trans('TelephoneResponsable').'</td><td>'.$langs->trans('Justification').'</td><td class="center">'.$langs->trans('WhatsApp').'</td></tr>';
	foreach ($eleves as $eid => $e) {
		if (!isset($lignes[$eid])) {
			continue;
		}
		$l = $lignes[$eid];
		print '<tr class="oddeven"><td><a href="'.dol_buildpath('/eleves/eleve/discipline.php', 1).'?id='.((int) $eid).'">'.dol_escape_htmltag(ecole_label($e)).'</a> <span class="opacitymedium small">'.dol_escape_htmltag($e->ref).'</span></td>';
		print '<td>'.eleves_presence_badge($l).'</td>';
		print '<td>'.dol_escape_htmltag(ecole_label((object) array('nom_fr' => (string) $e->rnom_fr, 'nom_ar' => (string) $e->rnom_ar))).'</td>';
		print '<td class="nowraponall">'.dol_print_phone((string) $e->telephone, '', 0, 0, 'AC_TEL', '&nbsp;', 'phone').'</td>';
		print '<td>'.((int) $l->justifiee ? '<span class="badge badge-status4">'.$langs->trans('Justifiee').'</span>' : '<span class="opacitymedium">'.$langs->trans('NonJustifiee').'</span>').'</td>';
		print '<td class="center">'.eleves_whatsapp_button(eleves_whatsapp_url($db, $eid, $date, (int) $l->type)).'</td></tr>';
	}
	print '</table></div>';
	print '<span class="opacitymedium small">'.$langs->trans('AidePrevenirParents').'</span>';
}

// Corrections de cet appel
if ($appel) {
	$corr = eleves_appel_corrections($db, (int) $appel->rowid);
	if (!empty($corr)) {
		print '<br><br>';
		print load_fiche_titre($langs->trans('CorrectionsAppel').' ('.count($corr).')', '', 'fa-history');
		print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
		print '<tr class="liste_titre"><td>'.$langs->trans('Date').'</td><td>'.$langs->trans('User').'</td><td>'.$langs->trans('NomComplet').'</td><td>'.$langs->trans('Avant').'</td><td>'.$langs->trans('Apres').'</td></tr>';
		foreach ($corr as $c) {
			print '<tr class="oddeven"><td class="nowraponall">'.dol_print_date($db->jdate($c->date_creation), 'dayhour', 'tzuserrel').'</td><td>'.dol_escape_htmltag(ecole_user_label($db, $c->fk_user)).'</td>';
			print '<td>'.dol_escape_htmltag(ecole_label($c)).'</td><td>'.dol_escape_htmltag(eleves_presence_label($c->avant)).'</td><td>'.dol_escape_htmltag(eleves_presence_label($c->apres)).'</td></tr>';
		}
		print '</table></div>';
	}
}

llxFooter();
$db->close();
