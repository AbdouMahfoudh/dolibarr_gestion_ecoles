<?php
/**
 * Onglet « Emploi du temps des examens » d'une classe.
 *
 * Fonctionnement : on choisit le trimestre une seule fois (un onglet par session), on fixe la
 * période d'examen (date de début et de fin) une seule fois, puis on ajoute les épreuves en
 * choisissant le jour dans la liste des jours de cette période, les horaires et la matière.
 * Les conflits sont refusés ; chaque changement est archivé.
 *
 * Fichier : custom/classes/classe/examens.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/classes/class/ecole_classe.class.php');
dol_include_once('/classes/class/ecole_session.class.php');
dol_include_once('/classes/class/ecole_edt_examen.class.php');

$langs->loadLangs(array('classes@classes', 'other'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$lineid = GETPOSTINT('lineid');
$confirm = GETPOST('confirm', 'alpha');
$showhistory = GETPOSTINT('history');

if (!$user->hasRight('classes', 'lire')) {
	accessforbidden();
}
$canedit = $user->hasRight('classes', 'edt');
$canperiod = $canedit || $user->hasRight('classes', 'config');
if (ecole_annee_passee()) {
	$canedit = $canperiod = false; // année passée : consultation seulement
}

$object = new EcoleClasse($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$form = new Form($db);
$p = MAIN_DB_PREFIX;
$jours = ecole_jours();
$salles = ecole_salles($db);
$cats = array('enseignant', 'surveillant');

// Sessions (une par trimestre)
$sessions = array();
$resql = $db->query("SELECT rowid, ref, label_fr, label_ar, trimestre, date_debut, date_fin FROM ".$p."ecole_session WHERE entity IN (".getEntity('ecole_session').") AND status = 1 ORDER BY trimestre, date_debut");
while ($resql && ($o = $db->fetch_object($resql))) {
	$sessions[(int) $o->rowid] = $o;
}

// Session affichée : celle demandée, sinon celle en cours, sinon la prochaine, sinon la première
$sid = GETPOSTINT('sid');
if (!isset($sessions[$sid])) {
	$sid = 0;
	$today = dol_print_date(dol_now(), '%Y-%m-%d');
	foreach ($sessions as $k => $s) {
		if ($s->date_debut && $s->date_fin && $s->date_debut <= $today && $today <= $s->date_fin) {
			$sid = $k;
			break;
		}
	}
	if (!$sid) {
		foreach ($sessions as $k => $s) {
			if ($s->date_fin && $s->date_fin >= $today) {
				$sid = $k;
				break;
			}
		}
	}
	if (!$sid && !empty($sessions)) {
		$sid = (int) key($sessions);
	}
}
$self = $_SERVER['PHP_SELF'].'?id='.$id.'&sid='.$sid;

/*
 * Actions
 */
// Période d'examen de la session (commune à toutes les classes)
if ($action == 'setperiode' && $canperiod && $sid > 0) {
	$s = new EcoleSession($db);
	if ($s->fetch($sid) > 0) {
		$deb = dol_mktime(12, 0, 0, GETPOSTINT('pdebutmonth'), GETPOSTINT('pdebutday'), GETPOSTINT('pdebutyear'));
		$fin = dol_mktime(12, 0, 0, GETPOSTINT('pfinmonth'), GETPOSTINT('pfinday'), GETPOSTINT('pfinyear'));
		if (empty($deb) || empty($fin)) {
			setEventMessages($langs->trans('ErrorEcolePeriodeIncomplete'), null, 'errors');
		} else {
			$s->date_debut = $deb;
			$s->date_fin = $fin;
			if ($s->update($user) > 0) {
				setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
				header('Location: '.$self);
				exit;
			}
			setEventMessages(null, $s->errors, 'errors');
		}
	}
	$action = 'periode';
}

if ($canedit) {
	if ($action == 'saveexam') {
		$e = new EcoleEdtExamen($db);
		$ok = true;
		if ($lineid > 0) {
			$ok = ($e->fetch($lineid) > 0 && (int) $e->fk_classe === $id);
		}
		if ($ok) {
			$e->fk_classe = $id;
			$e->fk_session = $sid;
			$e->date_examen = '';
			if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', GETPOST('jourexam', 'alphanohtml'), $reg)) {
				$e->date_examen = dol_mktime(12, 0, 0, (int) $reg[2], (int) $reg[3], (int) $reg[1]);
			}
			$e->heure_debut = GETPOST('heure_debut', 'alphanohtml');
			$e->heure_fin = GETPOST('heure_fin', 'alphanohtml');
			$e->fk_matiere = GETPOSTINT('fk_matiere');
			$e->fk_user = GETPOSTINT('fk_user') > 0 ? GETPOSTINT('fk_user') : null;
			$e->fk_salle = GETPOSTINT('fk_salle') > 0 ? GETPOSTINT('fk_salle') : null;
			$res = ($lineid > 0) ? $e->update($user) : $e->create($user);
			if ($res > 0) {
				setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
				header('Location: '.$self);
				exit;
			}
			setEventMessages(null, $e->errors, 'errors');
			$action = ($lineid > 0) ? 'edit' : 'create';
		}
	}

	if ($action == 'confirm_deleteexam' && $confirm == 'yes' && $lineid > 0) {
		$e = new EcoleEdtExamen($db);
		if ($e->fetch($lineid) > 0 && (int) $e->fk_classe === $id) {
			if ($e->delete($user) > 0) {
				setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
				header('Location: '.$self);
				exit;
			}
			setEventMessages(null, $e->errors, 'errors');
		}
		$action = '';
	}
}

/*
 * Données de la session affichée
 */
$session = $sid ? $sessions[$sid] : null;
$periode = ($session && !ecole_annee_passee()) ? ecole_jours_periode($session->date_debut, $session->date_fin) : array();
list($debutAnnee, $finAnnee) = ecole_annee_bornes(ecole_annee_vue());

$matieres = array();
$resql = $db->query("SELECT m.rowid, m.ref, m.label_fr, m.label_ar FROM ".$p."ecole_classe_matiere cm INNER JOIN ".$p."ecole_matiere m ON m.rowid = cm.fk_matiere WHERE cm.fk_classe = ".((int) $id)." ORDER BY m.label_fr");
while ($resql && ($o = $db->fetch_object($resql))) {
	$matieres[(int) $o->rowid] = $o;
}

$exams = array();
$sql = "SELECT e.rowid, e.date_examen, e.heure_debut, e.heure_fin, e.fk_matiere, e.fk_salle, e.fk_user, m.label_fr, m.label_ar";
$sql .= " FROM ".$p."ecole_edt_examen e INNER JOIN ".$p."ecole_matiere m ON m.rowid = e.fk_matiere";
$sql .= " WHERE e.fk_classe = ".((int) $id)." AND e.fk_session = ".((int) $sid);
$sql .= " AND e.date_examen >= '".$debutAnnee."' AND e.date_examen <= '".$finAnnee."' ORDER BY e.date_examen, e.heure_debut";
$resql = $db->query($sql);
while ($resql && ($o = $db->fetch_object($resql))) {
	$exams[] = $o;
}

/*
 * Affichage
 */
llxHeader('', $langs->trans('EmploiDuTempsExamens'), '', '', 0, 0, '', array('/classes/css/timetable.css'), '', 'mod-classes page-examens');

if ($action == 'delete' && $lineid > 0) {
	print $form->formconfirm($self.'&lineid='.$lineid, $langs->trans('Delete'), $langs->trans('ConfirmDeleteObject'), 'confirm_deleteexam', '', 0, 1);
}

classe_print_header($object, 'examens');

print '<div class="fichecenter"><br>';
print ecole_annee_selecteur(array('id', 'sid'));

if (empty($sessions)) {
	print '<div class="warning">'.$langs->trans('AucuneSession').' <a href="'.dol_buildpath('/classes/session/card.php', 1).'?action=create">'.$langs->trans('NouvelleSession').'</a></div>';
	print '</div>';
	print dol_get_fiche_end();
	llxFooter();
	$db->close();
	exit;
}

// 1) Choix du trimestre : un onglet par session
$shead = array();
foreach ($sessions as $k => $s) {
	$shead[] = array($_SERVER['PHP_SELF'].'?id='.$id.'&sid='.$k, dol_escape_htmltag(ecole_label($s)), 's'.$k);
}
print dol_get_fiche_head($shead, 's'.$sid, '', -1);

// 2) Période de l'examen (fixée une seule fois pour la session)
$showperiodform = $canperiod && (empty($periode) || $action == 'periode');
print '<div class="ecole-periode">';
if (!empty($periode) && $action != 'periode') {
	print img_picto('', 'fa-calendar-alt', 'class="pictofixedwidth"').'<b>'.$langs->trans('PeriodeExamen').'</b> : ';
	print $langs->trans('DuAu', dol_print_date($db->jdate($session->date_debut), 'daytext'), dol_print_date($db->jdate($session->date_fin), 'daytext'));
	print ' <span class="opacitymedium">('.$langs->trans('NbJoursExamen', count($periode)).')</span>';
	if ($canperiod) {
		print ' &nbsp; <a class="editfielda" href="'.$self.'&action=periode&token='.newToken().'">'.img_edit($langs->trans('ModifierPeriode')).'</a>';
	}
} elseif (!$showperiodform && !ecole_annee_passee()) {
	print '<div class="warning">'.$langs->trans('PeriodeNonDefinie').'</div>';
}
if ($showperiodform) {
	if (empty($periode)) {
		print '<div class="info">'.$langs->trans('DefinirPeriodeAide').'</div>';
	}
	$pdeb = $session->date_debut ? $db->jdate($session->date_debut) : '';
	$pfin = $session->date_fin ? $db->jdate($session->date_fin) : '';
	print '<form method="POST" action="'.$self.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="setperiode">';
	print '<b>'.$langs->trans('PeriodeExamen').'</b> : '.$langs->trans('DateDebut').' '.$form->selectDate($pdeb, 'pdebut', 0, 0, 1, '', 1, 0);
	print ' &nbsp; '.$langs->trans('DateFin').' '.$form->selectDate($pfin, 'pfin', 0, 0, 1, '', 1, 0);
	print ' &nbsp; <input type="submit" class="button button-save smallpaddingimp" value="'.$langs->trans('EnregistrerPeriode').'">';
	if (!empty($periode)) {
		print ' <a class="button button-cancel smallpaddingimp" href="'.$self.'">'.$langs->trans('Cancel').'</a>';
	}
	print '<div class="opacitymedium small">'.$langs->trans('PeriodeCommuneAide').'</div>';
	print '</form>';
}
print '</div><br>';

if (!empty($periode) && empty($matieres)) {
	print '<div class="warning">'.$langs->trans('AucuneMatiereLiee').' <a href="'.dol_buildpath('/classes/classe/matieres.php', 1).'?id='.$id.'">'.$langs->trans('MatieresCoefficients').'</a></div>';
}
$canform = $canedit && !empty($periode) && !empty($matieres);

// 3) Ajout / modification d'une épreuve : jour choisi dans la période, horaires, matière
if ($canform && in_array($action, array('create', 'edit'))) {
	$cur = null;
	if ($action == 'edit' && $lineid > 0) {
		$cur = new EcoleEdtExamen($db);
		if ($cur->fetch($lineid) <= 0 || (int) $cur->fk_classe !== $id) {
			$cur = null;
		}
	}
	$fjour = GETPOST('jourexam', 'alphanohtml');
	if ($fjour === '') {
		$fjour = $cur ? $cur->getSqlDate() : GETPOST('d', 'alphanohtml');
	}
	$fdeb = GETPOSTISSET('heure_debut') ? GETPOST('heure_debut', 'alphanohtml') : ($cur ? $cur->heure_debut : '');
	$ffin = GETPOSTISSET('heure_fin') ? GETPOST('heure_fin', 'alphanohtml') : ($cur ? $cur->heure_fin : '');
	$fmat = GETPOSTISSET('fk_matiere') ? GETPOSTINT('fk_matiere') : ($cur ? (int) $cur->fk_matiere : 0);
	$fuser = GETPOSTISSET('fk_user') ? GETPOSTINT('fk_user') : ($cur ? (int) $cur->fk_user : 0);
	$fsalle = GETPOSTISSET('fk_salle') ? GETPOSTINT('fk_salle') : ($cur ? (int) $cur->fk_salle : (int) $object->fk_salle);

	$optjours = array();
	foreach ($periode as $d => $t) {
		$optjours[$d] = $langs->trans($jours[(int) date('N', $t)]).' '.dol_print_date($t, 'day');
	}
	$optmat = array();
	foreach ($matieres as $mid => $m) {
		$optmat[$mid] = $m->ref.' - '.ecole_label($m);
	}
	$surveillants = ecole_users_categories($db, $cats, $fuser);

	print '<form method="POST" action="'.$self.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="saveexam">';
	print '<input type="hidden" name="lineid" value="'.($cur ? (int) $cur->id : 0).'">';
	print load_fiche_titre($cur ? $langs->trans('ModifierEpreuve') : $langs->trans('AjouterEpreuve'), '', '');
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('JourExamen').'</td><td>'.$form->selectarray('jourexam', $optjours, $fjour, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('Horaire').'</td><td>'.$langs->trans('HeureDebut').' <input type="time" class="flat" name="heure_debut" value="'.dol_escape_htmltag((string) $fdeb).'"> &nbsp; ';
	print $langs->trans('HeureFin').' <input type="time" class="flat" name="heure_fin" value="'.dol_escape_htmltag((string) $ffin).'"></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('Matiere').'</td><td>'.$form->selectarray('fk_matiere', $optmat, $fmat, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
	print '<tr><td>'.$langs->trans('Salle').'</td><td>'.$form->selectarray('fk_salle', ecole_salles($db, true, true, $fsalle), $fsalle, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
	print '<tr><td>'.$langs->trans('Surveillant').'</td><td>'.$form->selectarray('fk_user', $surveillants, $fuser, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300');
	if (empty($surveillants)) {
		print ' '.ecole_users_categories_aide($cats);
	}
	print '</td></tr>';
	print '</table>';
	print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"> ';
	print '<a class="button button-cancel" href="'.$self.'">'.$langs->trans('Cancel').'</a></div>';
	print '</form><br>';
} elseif ($canform) {
	print '<div class="tabsAction">'.dolGetButtonAction('', $langs->trans('AjouterEpreuve'), 'default', $self.'&action=create', '', $canedit).'</div>';
}

// 4) Emploi du temps de la période : un jour par colonne (affiché aussi sans période s'il existe déjà des épreuves)
if (!empty($periode) || !empty($exams)) {
	$data = ecole_edt_examens_data($db, $id, $session);
	foreach ($data['columns'] as $d => $col) {
		$data['columns'][$d]['addurl'] = ($canform && isset($periode[$d])) ? $self.'&action=create&d='.$d : '';
	}
	foreach ($data['events'] as $k => $ev) {
		$data['events'][$k]['editurl'] = $canedit ? $self.'&action=edit&lineid='.$ev['rowid'].'&token='.newToken() : '';
		$data['events'][$k]['deleteurl'] = $canedit ? $self.'&action=delete&lineid='.$ev['rowid'].'&token='.newToken() : '';
	}
	print ecole_pdf_button(dol_buildpath('/classes/edt_pdf.php', 1).'?type=examens&id='.$id.'&sid='.$sid);
	ecole_timetable_render($data['columns'], $data['events'], array('addlabel' => $langs->trans('AjouterEpreuve')));
}

print dol_get_fiche_end();

// Historique des modifications
if (!$showhistory) {
	print '<a href="'.$self.'&history=1">'.$langs->trans('VoirHistorique').'</a>';
} else {
	print load_fiche_titre($langs->trans('HistoriqueExamens'), '<a href="'.$self.'">'.$langs->trans('Masquer').'</a>', '');
	$sql = "SELECT a.action, a.donnees, a.date_archive, u.login FROM ".$p."ecole_edt_archive a LEFT JOIN ".$p."user u ON u.rowid = a.fk_user";
	$sql .= " WHERE a.type = 'examen' AND a.fk_classe = ".((int) $id)." ORDER BY a.date_archive DESC, a.rowid DESC";
	$resql = $db->query($sql);
	print '<table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans('Date').'</td><td>'.$langs->trans('Utilisateur').'</td><td>'.$langs->trans('Action').'</td><td>'.$langs->trans('AncienneVersion').'</td></tr>';
	$n = 0;
	while ($resql && ($o = $db->fetch_object($resql))) {
		$d = json_decode($o->donnees, true);
		$txt = $d ? trim(($d['session'] ?? '').' : '.($d['date_examen'] ?? '').' '.($d['heure_debut'] ?? '').'-'.($d['heure_fin'] ?? '').' '.($d['matiere'] ?? '').' / '.($d['salle'] ?? '').' / '.($d['surveillant'] ?? '')) : '';
		print '<tr class="oddeven"><td>'.dol_print_date($db->jdate($o->date_archive), 'dayhour').'</td><td>'.dol_escape_htmltag((string) $o->login).'</td>';
		print '<td>'.$langs->trans($o->action == 'delete' ? 'ActionSupprime' : 'ActionModifie').'</td><td>'.dol_escape_htmltag($txt).'</td></tr>';
		$n++;
	}
	if (!$n) {
		print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans('AucunHistorique').'</span></td></tr>';
	}
	print '</table>';
}

print '</div>';
print dol_get_fiche_end();

llxFooter();
$db->close();
