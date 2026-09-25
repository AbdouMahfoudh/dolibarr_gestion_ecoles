<?php
/**
 * Onglet « Emploi du temps des cours » d'une classe : grille jours x créneaux.
 * Chaque case : matière (liée à la classe), enseignant, salle.
 * Les conflits (enseignant ou salle déjà pris) sont refusés ; chaque changement est archivé.
 *
 * Fichier : custom/classes/classe/edt.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/classes/class/ecole_classe.class.php');
dol_include_once('/classes/class/ecole_edt_cours.class.php');

$langs->loadLangs(array('classes@classes', 'other', 'agenda'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$lineid = GETPOSTINT('lineid');
$confirm = GETPOST('confirm', 'alpha');
$showhistory = GETPOSTINT('history');

if (!$user->hasRight('classes', 'lire')) {
	accessforbidden();
}
$canedit = $user->hasRight('classes', 'edt');

$object = new EcoleClasse($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$form = new Form($db);
$self = $_SERVER['PHP_SELF'].'?id='.$id;
$p = MAIN_DB_PREFIX;

$jours = ecole_jours();
$joursOuvrables = ecole_jours_ouvrables();
$salles = ecole_salles($db);

/*
 * Actions
 */
if ($canedit) {
	if ($action == 'saveslot') {
		$e = new EcoleEdtCours($db);
		$ok = true;
		if ($lineid > 0) {
			$ok = ($e->fetch($lineid) > 0 && (int) $e->fk_classe === $id);
		}
		if ($ok) {
			$e->fk_classe = $id;
			$e->jour = GETPOSTINT('jour');
			$e->fk_creneau = GETPOSTINT('fk_creneau');
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

	if ($action == 'confirm_deleteslot' && $confirm == 'yes' && $lineid > 0) {
		$e = new EcoleEdtCours($db);
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
 * Données
 */
$creneaux = array();
$resql = $db->query("SELECT rowid, ref, label_fr, label_ar, heure_debut, heure_fin FROM ".$p."ecole_creneau WHERE entity IN (".getEntity('ecole_creneau').") AND status = 1 ORDER BY heure_debut");
if ($resql) {
	while ($o = $db->fetch_object($resql)) {
		$creneaux[(int) $o->rowid] = $o;
	}
}

$matieres = array();
$resql = $db->query("SELECT m.rowid, m.ref, m.code, m.label_fr, m.label_ar FROM ".$p."ecole_classe_matiere cm INNER JOIN ".$p."ecole_matiere m ON m.rowid = cm.fk_matiere WHERE cm.fk_classe = ".((int) $id)." ORDER BY m.label_fr");
if ($resql) {
	while ($o = $db->fetch_object($resql)) {
		$matieres[(int) $o->rowid] = $o;
	}
}

/*
 * Affichage
 */
llxHeader('', $langs->trans('EmploiDuTempsCours'), '', '', 0, 0, '', array('/classes/css/timetable.css'), '', 'mod-classes page-edt');

if ($action == 'delete' && $lineid > 0) {
	print $form->formconfirm($self.'&lineid='.$lineid, $langs->trans('Delete'), $langs->trans('ConfirmDeleteObject'), 'confirm_deleteslot', '', 0, 1);
}

classe_print_header($object, 'edt');

print '<div class="fichecenter"><br>';

if (empty($creneaux)) {
	print '<div class="warning">'.$langs->trans('AucunCreneau').' <a href="'.dol_buildpath('/classes/creneau/card.php', 1).'?action=create">'.$langs->trans('NouveauCreneau').'</a></div>';
} elseif (empty($matieres)) {
	print '<div class="warning">'.$langs->trans('AucuneMatiereLiee').' <a href="'.dol_buildpath('/classes/classe/matieres.php', 1).'?id='.$id.'">'.$langs->trans('MatieresCoefficients').'</a></div>';
}

// Formulaire d'ajout / modification d'une case
if ($canedit && in_array($action, array('create', 'edit')) && !empty($creneaux) && !empty($matieres)) {
	$cur = null;
	if ($action == 'edit' && $lineid > 0) {
		$cur = new EcoleEdtCours($db);
		if ($cur->fetch($lineid) <= 0 || (int) $cur->fk_classe !== $id) {
			$cur = null;
		}
	}
	$fjour = GETPOSTISSET('jour') ? GETPOSTINT('jour') : ($cur ? (int) $cur->jour : GETPOSTINT('jour'));
	$fcren = GETPOSTISSET('fk_creneau') ? GETPOSTINT('fk_creneau') : ($cur ? (int) $cur->fk_creneau : GETPOSTINT('fk_creneau'));
	$fmat = GETPOSTISSET('fk_matiere') ? GETPOSTINT('fk_matiere') : ($cur ? (int) $cur->fk_matiere : 0);
	$fuser = GETPOSTISSET('fk_user') ? GETPOSTINT('fk_user') : ($cur ? (int) $cur->fk_user : 0);
	$fsalle = GETPOSTISSET('fk_salle') ? GETPOSTINT('fk_salle') : ($cur ? (int) $cur->fk_salle : (int) $object->fk_salle);

	$optjours = array();
	foreach ($joursOuvrables as $j) {
		$optjours[$j] = $langs->trans($jours[$j]);
	}
	$optcren = array();
	foreach ($creneaux as $cid => $c) {
		$optcren[$cid] = ecole_label($c).' ('.$c->heure_debut.' - '.$c->heure_fin.')';
	}
	$optmat = array();
	foreach ($matieres as $mid => $m) {
		$optmat[$mid] = $m->ref.' - '.ecole_label($m);
	}

	print '<form method="POST" action="'.$self.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="saveslot">';
	print '<input type="hidden" name="lineid" value="'.($cur ? (int) $cur->id : 0).'">';
	print load_fiche_titre($cur ? $langs->trans('ModifierCase') : $langs->trans('AjouterCase'), '', '');
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('Jour').'</td><td>'.$form->selectarray('jour', $optjours, $fjour, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('Creneau').'</td><td>'.$form->selectarray('fk_creneau', $optcren, $fcren, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('Matiere').'</td><td>'.$form->selectarray('fk_matiere', $optmat, $fmat, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
	$enseignants = ecole_users_categories($db, array('enseignant'), $fuser);
	print '<tr><td>'.$langs->trans('Enseignant').'</td><td>'.$form->selectarray('fk_user', $enseignants, $fuser, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300');
	if (empty($enseignants)) {
		print ' '.ecole_users_categories_aide(array('enseignant'));
	}
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('Salle').'</td><td>'.$form->selectarray('fk_salle', ecole_salles($db, true, true, $fsalle), $fsalle, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
	print '</table>';
	print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"> ';
	print '<a class="button button-cancel" href="'.$self.'">'.$langs->trans('Cancel').'</a></div>';
	print '</form><br>';
}

// Bouton d'ajout (en plus du « + » dans chaque case libre)
if ($canedit && !empty($creneaux) && !empty($matieres) && !in_array($action, array('create', 'edit'))) {
	print '<div class="tabsAction">'.dolGetButtonAction('', $langs->trans('AjouterCase'), 'default', $self.'&action=create', '', $canedit).'</div>';
	print '<div class="opacitymedium">'.$langs->trans('AideEmploiDuTemps').'</div>';
}

// Emploi du temps (affichage visuel : jours en colonnes, créneaux en lignes)
if (!empty($creneaux)) {
	$data = ecole_edt_classe_data($db, $id);
	foreach ($data['events'] as $k => $ev) {
		$data['events'][$k]['editurl'] = $canedit ? $self.'&action=edit&lineid='.$ev['rowid'].'&token='.newToken() : '';
		$data['events'][$k]['deleteurl'] = $canedit ? $self.'&action=delete&lineid='.$ev['rowid'].'&token='.newToken() : '';
	}
	$bandaddurl = null;
	if ($canedit && !empty($matieres)) {
		$bandids = $data['bandids'];
		$bandaddurl = function ($jour, $i) use ($self, $bandids) {
			return $self.'&action=create&jour='.((int) $jour).'&fk_creneau='.((int) $bandids[$i]);
		};
	}
	print ecole_pdf_button(dol_buildpath('/classes/edt_pdf.php', 1).'?type=cours&id='.$id);
	ecole_timetable_render($data['columns'], $data['events'], array('bands' => $data['bands'], 'bandaddurl' => $bandaddurl, 'addlabel' => $langs->trans('AjouterCase')));
}

// Historique des modifications
print '<br>';
if (!$showhistory) {
	print '<a href="'.$self.'&history=1">'.$langs->trans('VoirHistorique').'</a>';
} else {
	print load_fiche_titre($langs->trans('HistoriqueEmploiDuTemps'), '<a href="'.$self.'">'.$langs->trans('Masquer').'</a>', '');
	$sql = "SELECT a.action, a.donnees, a.date_archive, u.login FROM ".$p."ecole_edt_archive a LEFT JOIN ".$p."user u ON u.rowid = a.fk_user";
	$sql .= " WHERE a.type = 'cours' AND a.fk_classe = ".((int) $id)." ORDER BY a.date_archive DESC, a.rowid DESC";
	$resql = $db->query($sql);
	print '<table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans('Date').'</td><td>'.$langs->trans('Utilisateur').'</td><td>'.$langs->trans('Action').'</td><td>'.$langs->trans('AncienneVersion').'</td></tr>';
	$n = 0;
	if ($resql) {
		while ($o = $db->fetch_object($resql)) {
			$d = json_decode($o->donnees, true);
			$txt = $d ? trim(($d['jour_label'] ?? '').' '.($d['creneau'] ?? '').' : '.($d['matiere'] ?? '').' / '.($d['enseignant'] ?? '').' / '.($d['salle'] ?? '')) : '';
			print '<tr class="oddeven"><td>'.dol_print_date($db->jdate($o->date_archive), 'dayhour').'</td><td>'.dol_escape_htmltag((string) $o->login).'</td>';
			print '<td>'.$langs->trans($o->action == 'delete' ? 'ActionSupprime' : 'ActionModifie').'</td><td>'.dol_escape_htmltag($txt).'</td></tr>';
			$n++;
		}
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
