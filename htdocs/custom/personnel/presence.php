<?php
/**
 * Présence des enseignants, jour par jour.
 *
 * Tous les cours de l'emploi du temps de la journée avec l'état de l'enseignant : fait par défaut, déclaré,
 * absent ou en retard (marqués pendant l'appel ou par la direction), remplacé. En choisissant un cours :
 *  - l'enseignant (droit « déclarer ses propres cours ») déclare son cours, avec le sujet traité (facultatif) ;
 *  - la direction (droit « gérer ») marque absent / en retard, justifie, corrige (chaque changement est gardé) ;
 *  - la direction (droit « remplacements ») choisit le remplaçant d'un enseignant absent, même à l'avance.
 *
 * Fichier : custom/personnel/presence.php
 */

require 'init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/personnel/core/lib/presence.lib.php');

$langs->loadLangs(array('personnel@personnel', 'classes@classes', 'other'));

$canlire = $user->hasRight('personnel', 'presence', 'lire');
$cangerer = $user->hasRight('personnel', 'presence', 'gerer');
$canremp = $user->hasRight('personnel', 'presence', 'remplacer');
$candecl = $user->hasRight('personnel', 'presence', 'declarer');
if (!$canlire && !$candecl) {
	accessforbidden();
}
$moi = personnel_employe_de($db, $user);
if (!$canlire && !$moi) {
	accessforbidden($langs->trans('ErrorPasDeFicheEmploye'));
}

$form = new Form($db);
$action = GETPOST('action', 'aZ09');
$aujourdhui = personnel_aujourdhui();
$date = personnel_date_ok(GETPOST('date', 'alpha'));
if ($date === '') {
	$date = $aujourdhui;
}
$fk_classe = GETPOSTINT('fk_classe');
$fk_creneau = GETPOSTINT('fk_creneau');
$f_employe = $canlire ? max(0, GETPOSTINT('f_employe')) : (int) $moi->id;
$self = $_SERVER['PHP_SELF'];
$urlJour = $self.'?date='.urlencode($date).($f_employe && $canlire ? '&f_employe='.$f_employe : '');
$urlCours = $urlJour.'&fk_classe='.$fk_classe.'&fk_creneau='.$fk_creneau;

/*
 * Actions
 */
if ($fk_classe > 0 && $fk_creneau > 0 && in_array($action, array('declarer', 'save', 'defaut'), true)) {
	$error = '';
	if ($action === 'declarer' && ($candecl || $cangerer)) {
		$res = personnel_cours_declarer($db, $user, $date, $fk_creneau, $fk_classe, GETPOST('sujet', 'alphanohtml'), $error);
		$msg = 'CoursDeclareOk';
	} elseif ($action === 'save' && ($cangerer || $canremp)) {
		$data = array();
		if ($cangerer) {
			$data['etat'] = GETPOSTINT('etat');
			$data['minutes_retard'] = GETPOSTINT('minutes_retard');
			$data['justifiee'] = GETPOST('justifiee', 'alpha') ? 1 : 0;
			$data['fk_motif'] = GETPOSTINT('fk_motif');
			$data['note'] = GETPOST('note', 'alphanohtml');
			$data['sujet'] = GETPOST('sujet', 'alphanohtml');
		}
		if ($canremp) {
			$data['fk_remplacant'] = max(0, GETPOSTINT('fk_remplacant'));
			if ($data['fk_remplacant'] > 0) {
				$data['etat'] = PERSONNEL_ABSENT; // un remplaçant : le titulaire est absent
			}
		}
		$res = personnel_cours_enregistrer($db, $user, $date, $fk_creneau, $fk_classe, $data, 'direction', $error);
		$msg = 'RecordSaved';
	} elseif ($action === 'defaut' && $cangerer && GETPOST('confirm', 'alpha') === 'yes') {
		// Retour à l'état par défaut (présent, sans déclaration ni remarque) : la correction est gardée
		$res = personnel_cours_enregistrer($db, $user, $date, $fk_creneau, $fk_classe, array('etat' => PERSONNEL_PRESENT, 'declare_cours' => 0, 'sujet' => '', 'note' => '', 'fk_remplacant' => 0), 'direction', $error);
		$msg = 'CoursRemisParDefaut';
	} else {
		accessforbidden();
	}
	if ($res >= 0) {
		setEventMessages($langs->trans($msg), null, 'mesgs');
	} else {
		setEventMessages($error, null, 'errors');
	}
	header('Location: '.$urlCours.'#cours');
	exit;
}

/*
 * Affichage
 */
llxHeader('', $langs->trans('PresenceEnseignants'), '', '', 0, 0, '', '', '', 'mod-personnel page-presence');

print '<style>
.pres-tog{display:inline-block;padding:6px 12px;margin:2px 4px 2px 0;border:1px solid #bbb;border-radius:4px;cursor:pointer;user-select:none;white-space:nowrap;background:#fff}
.pres-tog input{margin:0 5px 0 0;vertical-align:middle}
.pres-tog.on{background:#dff0d8;border-color:#5cb85c;font-weight:bold}
.pres-tog.on.pres-abs{background:#fde2e1;border-color:#d9534f;color:#a94442}
.pres-tog.on.pres-ret{background:#fff3cd;border-color:#f0ad4e;color:#8a6d3b}
tr.pres-sel td{background:#eef3fb !important}
</style>';

$titre = $canlire ? $langs->trans('PresenceEnseignants') : $langs->trans('MesCours');
print load_fiche_titre($titre, '', 'fa-chalkboard-teacher');

// Barre de choix : date, enseignant
$veille = dol_print_date(dol_time_plus_duree(personnel_date_ts($date), -1, 'd'), '%Y-%m-%d');
$lendemain = dol_print_date(dol_time_plus_duree(personnel_date_ts($date), 1, 'd'), '%Y-%m-%d');
$nav = ($f_employe && $canlire) ? '&f_employe='.$f_employe : '';
print '<form method="GET" action="'.dol_escape_htmltag($self).'" class="marginbottomonly">';
print '<div class="inline-block valignmiddle marginrightonly nowraponall">';
print '<a class="button smallpaddingimp" href="'.dol_escape_htmltag($self.'?date='.$veille.$nav).'" title="'.dol_escape_htmltag($langs->trans('JourPrecedent')).'">&lsaquo;</a> ';
print '<input type="date" name="date" class="flat" value="'.dol_escape_htmltag($date).'" onchange="this.form.submit()"> ';
print '<a class="button smallpaddingimp" href="'.dol_escape_htmltag($self.'?date='.$lendemain.$nav).'" title="'.dol_escape_htmltag($langs->trans('JourSuivant')).'">&rsaquo;</a> ';
if ($date !== $aujourdhui) {
	print '<a class="button smallpaddingimp" href="'.dol_escape_htmltag($self.'?date='.$aujourdhui.$nav).'">'.$langs->trans('Today').'</a>';
}
print ' <b class="marginleftonly">'.dol_escape_htmltag(personnel_date_label($date)).'</b></div> ';
if ($canlire) {
	print '<div class="inline-block valignmiddle marginrightonly">'.img_picto('', 'fa-user', 'class="pictofixedwidth"');
	print $form->selectarray('f_employe', personnel_employes_choix($db, 'enseignant', $f_employe), $f_employe, $langs->trans('TousLesEnseignants'), 0, 0, 'onchange="this.form.submit()"', 0, 0, 0, '', 'minwidth200', 1).'</div>';
}
print '<noscript><input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Refresh')).'"></noscript>';
print '</form>';

$sanscours = personnel_jours_sans_cours($db, $date, $date);
if (isset($sanscours[$date])) {
	print '<div class="info">'.$langs->trans('JourSansCoursInfo', dol_escape_htmltag($sanscours[$date])).'</div>';
}
if (!in_array((int) date('N', strtotime($date)), ecole_jours_ouvrables(), true)) {
	print '<div class="info">'.$langs->trans('JourNonOuvrable').'</div>';
}

$cours = personnel_cours_jour($db, $date, $f_employe);

// Cours choisi : formulaire de déclaration / gestion
$sel = ($fk_classe > 0 && $fk_creneau > 0 && isset($cours[$fk_creneau.'|'.$fk_classe])) ? $cours[$fk_creneau.'|'.$fk_classe] : null;
if ($sel) {
	$rec = $sel->rec;
	$estMien = $moi && (int) $sel->fk_employe === (int) $moi->id;
	print '<a id="cours"></a>';
	print '<div class="div-table-responsive-no-min"><table class="border centpercent tableforfield marginbottomonly">';
	print '<tr class="liste_titre"><td colspan="2">'.img_picto('', 'fa-chalkboard', 'class="pictofixedwidth"').dol_escape_htmltag($sel->crref.' ('.$sel->heure_debut.'-'.$sel->heure_fin.') — '.$sel->cref.' — '.ecole_label((object) array('label_fr' => $sel->m_fr, 'label_ar' => $sel->m_ar)))
		.' <a class="floatright" href="'.dol_escape_htmltag($urlJour).'">'.img_picto($langs->trans('Close'), 'close_title').'</a></td></tr>';
	print '<tr><td class="titlefield">'.$langs->trans('Enseignant').'</td><td>'.((int) $sel->fk_employe > 0 ? '<a href="'.dol_buildpath('/personnel/employe/presence.php', 1).'?id='.((int) $sel->fk_employe).'&mois='.substr($date, 0, 7).'">'.dol_escape_htmltag(ecole_label($sel)).'</a> <span class="opacitymedium">'.dol_escape_htmltag($sel->eref).'</span>' : '<span class="opacitymedium">'.$langs->trans('EnseignantNonRelie').'</span>').'</td></tr>';
	$info = ($sel->etat === 'retard' && $rec) ? '('.$langs->trans('NbMinutes', (int) $rec->minutes_retard).')' : '';
	print '<tr><td>'.$langs->trans('Presence').'</td><td>'.personnel_etat_badge($sel->etat, $info);
	if ($rec && $rec->source) {
		print ' <span class="opacitymedium small">'.$langs->trans('Source_'.$rec->source).' — '.dol_escape_htmltag(ecole_user_label($db, $rec->fk_user_modif ? $rec->fk_user_modif : $rec->fk_user_creat)).'</span>';
	}
	if ($sel->absence && (!$rec || (int) $rec->etat !== PERSONNEL_ABSENT)) {
		print ' <span class="opacitymedium small">'.$langs->trans('AbsenceDuPersonnelSaisie').'</span>';
	}
	print '</td></tr>';
	if ($rec && (int) $rec->declare_cours) {
		print '<tr><td>'.$langs->trans('Declaration').'</td><td>'.$langs->trans('DeclarePar', dol_escape_htmltag(ecole_user_label($db, $rec->fk_user_declare)), dol_print_date($db->jdate($rec->date_declaration), 'dayhour', 'tzuserrel')).'</td></tr>';
	}
	if ($rec && trim((string) $rec->sujet) !== '') {
		print '<tr><td>'.$langs->trans('SujetTraite').'</td><td>'.dol_escape_htmltag($rec->sujet).'</td></tr>';
	}
	print '</table></div>';

	// Déclaration par l'enseignant (son cours, pas absent, pas à venir)
	if (($candecl && $estMien) || $cangerer) {
		if ($sel->etat === 'absent') {
			print '<div class="opacitymedium marginbottomonly">'.img_picto('', 'fa-info-circle', 'class="pictofixedwidth"').$langs->trans('DeclarationImpossibleAbsent').'</div>';
		} elseif ($date > $aujourdhui) {
			print '<div class="opacitymedium marginbottomonly">'.img_picto('', 'fa-info-circle', 'class="pictofixedwidth"').$langs->trans('ErrorCoursAVenir').'</div>';
		} elseif (!$rec || !(int) $rec->declare_cours) {
			print '<form method="POST" action="'.dol_escape_htmltag($self).'" class="marginbottomonly">';
			print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="declarer">';
			print '<input type="hidden" name="date" value="'.$date.'"><input type="hidden" name="fk_classe" value="'.$fk_classe.'"><input type="hidden" name="fk_creneau" value="'.$fk_creneau.'">';
			print '<input type="text" name="sujet" class="flat minwidth300" maxlength="250" placeholder="'.dol_escape_htmltag($langs->trans('SujetTraiteFacultatif')).'" value="'.dol_escape_htmltag($rec ? (string) $rec->sujet : '').'"> ';
			print '<button type="submit" class="button button-save">'.img_picto('', 'fa-check-double', 'class="pictofixedwidth"').$langs->trans('DeclarerCours').'</button>';
			print '</form>';
		}
	}

	// Gestion par la direction : état, justification, remplaçant
	if ($cangerer || $canremp) {
		$e = $rec ? (int) $rec->etat : ($sel->absence ? PERSONNEL_ABSENT : PERSONNEL_PRESENT);
		print '<form method="POST" action="'.dol_escape_htmltag($self).'" id="formcours">';
		print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save">';
		print '<input type="hidden" name="date" value="'.$date.'"><input type="hidden" name="fk_classe" value="'.$fk_classe.'"><input type="hidden" name="fk_creneau" value="'.$fk_creneau.'">';
		if ($f_employe) {
			print '<input type="hidden" name="f_employe" value="'.$f_employe.'">';
		}
		print '<table class="border centpercent tableforfield">';
		if ($cangerer) {
			print '<tr><td class="titlefield">'.$langs->trans('Presence').'</td><td>';
			foreach (array(PERSONNEL_PRESENT => array('Present', ''), PERSONNEL_ABSENT => array('Absent', 'pres-abs'), PERSONNEL_RETARD => array('EnRetard', 'pres-ret')) as $v => $l) {
				print '<label class="pres-tog '.$l[1].($e === $v ? ' on' : '').'"><input type="radio" name="etat" value="'.$v.'"'.($e === $v ? ' checked' : '').'>'.$langs->trans($l[0]).'</label>';
			}
			$min = ($rec && (int) $rec->etat === PERSONNEL_RETARD) ? (int) $rec->minutes_retard : '';
			print '<span id="bloc-retard"'.($e === PERSONNEL_RETARD ? '' : ' style="display:none"').'><input type="number" name="minutes_retard" min="1" max="600" class="flat maxwidth75" value="'.dol_escape_htmltag((string) $min).'"> '.$langs->trans('Minutes').'</span>';
			print '</td></tr>';
			print '<tr class="row-just"'.($e === PERSONNEL_PRESENT ? ' style="display:none"' : '').'><td>'.$langs->trans('Justification').'</td><td>';
			print '<label class="marginrightonly"><input type="checkbox" name="justifiee" value="1"'.($rec && (int) $rec->justifiee ? ' checked' : '').'> '.$langs->trans('Justifiee').'</label> ';
			print $form->selectarray('fk_motif', EcoleEmployeMotif::choix($db, 'absence', $rec ? (int) $rec->fk_motif : 0), $rec ? (int) $rec->fk_motif : 0, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200');
			print '</td></tr>';
			print '<tr><td>'.$langs->trans('SujetTraite').'</td><td><input type="text" name="sujet" class="flat minwidth300" maxlength="250" value="'.dol_escape_htmltag($rec ? (string) $rec->sujet : '').'"></td></tr>';
			print '<tr><td>'.$langs->trans('Remarque').'</td><td><input type="text" name="note" class="flat minwidth300" maxlength="250" value="'.dol_escape_htmltag($rec ? (string) $rec->note : '').'"></td></tr>';
		}
		if ($canremp) {
			$choix = personnel_employes_choix($db, 'enseignant', $rec ? (int) $rec->fk_remplacant : 0);
			unset($choix[(int) $sel->fk_employe]);
			print '<tr class="row-remp"'.($cangerer && $e !== PERSONNEL_ABSENT ? ' style="display:none"' : '').'><td class="titlefield">'.$form->textwithpicto($langs->trans('Remplacant'), $langs->trans('RemplacantAide')).'</td><td>';
			print $form->selectarray('fk_remplacant', $choix, $rec ? (int) $rec->fk_remplacant : 0, $langs->trans('AucunRemplacant'), 0, 0, '', 0, 0, 0, '', 'minwidth300', 1).'</td></tr>';
		}
		print '</table>';
		print '<div class="center"><input type="submit" class="button button-save" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
		if ($cangerer && $rec) {
			print ' &nbsp; <a class="button button-cancel" href="'.dol_escape_htmltag($urlCours.'&action=defaut&confirm=yes&token='.newToken()).'" onclick="return confirm(\''.dol_escape_js($langs->transnoentities('ConfirmRemettreParDefaut')).'\')">'.$langs->trans('RemettreParDefaut').'</a>';
		}
		print '</div></form>';
		print '<script>$(function(){var f=$("#formcours");f.on("change","input[name=etat]",function(){var v=f.find("input[name=etat]:checked").val();f.find("input[name=etat]").each(function(){$(this).parent().toggleClass("on",this.checked);});$("#bloc-retard").toggle(v==="'.PERSONNEL_RETARD.'");f.find(".row-just").toggle(v!=="'.PERSONNEL_PRESENT.'");f.find(".row-remp").toggle(v==="'.PERSONNEL_ABSENT.'");});});</script>';
	}

	// Corrections de ce cours
	if ($rec) {
		$corr = personnel_cours_corrections($db, (int) $rec->rowid);
		if ($corr) {
			print '<br>'.load_fiche_titre($langs->trans('CorrectionsCours').' ('.count($corr).')', '', 'fa-history');
			print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
			print '<tr class="liste_titre"><td>'.$langs->trans('Date').'</td><td>'.$langs->trans('User').'</td><td>'.$langs->trans('Avant').'</td><td>'.$langs->trans('Apres').'</td></tr>';
			foreach ($corr as $c) {
				print '<tr class="oddeven"><td class="nowraponall">'.dol_print_date($db->jdate($c->date_creation), 'dayhour', 'tzuserrel').'</td><td>'.dol_escape_htmltag(ecole_user_label($db, $c->fk_user)).'</td>';
				print '<td>'.dol_escape_htmltag(personnel_cours_code_label($c->avant)).'</td><td>'.dol_escape_htmltag(personnel_cours_code_label($c->apres)).'</td></tr>';
			}
			print '</table></div>';
		}
	}
	print '<br>';
}

// Résumé de la journée
$nb = array('total' => 0, 'present' => 0, 'declare' => 0, 'absent' => 0, 'retard' => 0, 'remplace' => 0, 'avenir' => 0);
foreach ($cours as $c) {
	if ($c->etat === 'sanscours') {
		continue;
	}
	$nb['total']++;
	$nb[$c->etat] = (isset($nb[$c->etat]) ? $nb[$c->etat] : 0) + 1;
	$nb['remplace'] += ($c->rec && (int) $c->rec->fk_remplacant > 0) ? 1 : 0;
}
print '<div class="opacitymedium marginbottomonly">'.$langs->trans('ResumeJourPresence', $nb['total'], $nb['present'] + $nb['declare'], $nb['declare'], $langs->transnoentities('NbAbsencesRemplacees', $nb['absent'], $nb['remplace'], $nb['retard'])).'</div>';

// Cours de la journée
print '<div class="div-table-responsive"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Creneau').'</td><td>'.$langs->trans('Classe').'</td><td>'.$langs->trans('Matiere').'</td><td>'.$langs->trans('Enseignant').'</td>';
print '<td>'.$langs->trans('Presence').'</td><td>'.$langs->trans('SujetOuRemarque').'</td><td>'.$langs->trans('Remplacant').'</td><td></td></tr>';
foreach ($cours as $k => $c) {
	$estMien = $moi && (int) $c->fk_employe === (int) $moi->id;
	$url = $self.'?date='.$date.($f_employe && $canlire ? '&f_employe='.$f_employe : '').'&fk_classe='.$c->fk_classe.'&fk_creneau='.$c->fk_creneau.'#cours';
	$rec = $c->rec;
	$info = ($c->etat === 'retard' && $rec) ? '('.$langs->trans('NbMinutes', (int) $rec->minutes_retard).')' : '';
	print '<tr class="oddeven'.($sel && $k === $fk_creneau.'|'.$fk_classe ? ' pres-sel' : '').'">';
	print '<td class="nowraponall">'.dol_escape_htmltag($c->crref).' <span class="opacitymedium small">'.$c->heure_debut.'-'.$c->heure_fin.'</span></td>';
	print '<td>'.dol_escape_htmltag($c->cref).'</td>';
	print '<td>'.dol_escape_htmltag(ecole_label((object) array('label_fr' => $c->m_fr, 'label_ar' => $c->m_ar))).'</td>';
	print '<td>'.((int) $c->fk_employe > 0 ? dol_escape_htmltag(ecole_label($c)) : '<span class="opacitymedium">'.$langs->trans('EnseignantNonRelie').'</span>').'</td>';
	print '<td class="nowraponall">'.personnel_etat_badge($c->etat, $info).'</td>';
	print '<td>'.dol_escape_htmltag($rec ? trim((string) $rec->sujet.((string) $rec->note !== '' ? ' — '.$rec->note : '')) : '').'</td>';
	print '<td>'.($rec && (int) $rec->fk_remplacant > 0 ? dol_escape_htmltag(personnel_employe_nom($db, (int) $rec->fk_remplacant)) : '').'</td>';
	print '<td class="center nowraponall">';
	if ($cangerer || $canremp) {
		print '<a class="button smallpaddingimp" href="'.dol_escape_htmltag($url).'">'.$langs->trans('Gerer').'</a>';
	} elseif ($candecl && $estMien && $c->etat !== 'absent' && $date <= $aujourdhui && !($rec && (int) $rec->declare_cours)) {
		print '<a class="button smallpaddingimp" href="'.dol_escape_htmltag($url).'">'.$langs->trans('Declarer').'</a>';
	} else {
		print '<a href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('Voir'), 'fa-eye').'</a>';
	}
	print '</td></tr>';
}
if (empty($cours)) {
	print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('AucunCoursJour').'</span></td></tr>';
}
print '</table></div>';
print '<br><span class="opacitymedium small">'.$langs->trans('AidePresenceEnseignants').'</span>';

llxFooter();
$db->close();
