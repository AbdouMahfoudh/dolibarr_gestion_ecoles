<?php
/**
 * Accès à l'espace d'un responsable ou d'un élève : état, identifiant, mot de passe provisoire, connexions ;
 * créer l'accès, imprimer la fiche d'accès, l'envoyer par WhatsApp, réinitialiser le mot de passe,
 * désactiver ou réactiver. Pour un élève, la page est un onglet de sa fiche.
 *
 *   acces.php?type=<parent|eleve|employe>&id=<responsable|élève|employé>
 * Pour un employé, la page est un onglet de sa fiche (module Personnel). Direction, secrétariat et comptabilité
 * gardent leur mot de passe Dolibarr et ouvrent leur espace par « Mon espace ».
 *
 * Fichier : custom/espace/acces.php
 */

require 'init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

$langs->loadLangs(array('espace@espace', 'eleves@eleves', 'classes@classes', 'users'));
if (isModEnabled('personnel')) {
	$langs->load('personnel@personnel');
	dol_include_once('/personnel/core/lib/personnel.lib.php');
}

$type = GETPOST('type', 'aZ09');
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
if (!in_array($type, espace_types(), true) || !$user->hasRight('espace', 'acces', 'lire')) {
	accessforbidden();
}
$peutGerer = $user->hasRight('espace', 'acces', 'gerer');
$cible = espace_cible($db, $type, $id);
if (!$cible) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$self = $_SERVER['PHP_SELF'].'?type='.$type.'&id='.$id;
$acces = espace_acces_fetch($db, $type, $id);
$form = new Form($db);

/*
 * Actions
 */
if ($peutGerer) {
	$error = '';
	if ($action === 'confirm_reinit' && $type === ESPACE_EMPLOYE && espace_employe_gestion($db, $cible)) {
		accessforbidden(); // mot de passe Dolibarr : il se change dans la fiche utilisateur
	}
	if ($action === 'addacces' && !$acces) {
		if (espace_creer($db, $user, $type, $id, $error) > 0) {
			setEventMessages($langs->trans('AccesCree'), null, 'mesgs');
		} else {
			setEventMessages($error, null, 'errors');
		}
		header('Location: '.$self);
		exit;
	}
	if ($action === 'confirm_reinit' && GETPOST('confirm', 'alpha') === 'yes' && $acces) {
		if (espace_reinitialiser($db, $user, $acces, $error) > 0) {
			setEventMessages($langs->trans('MotDePasseReinitialise'), null, 'mesgs');
		} else {
			setEventMessages($error, null, 'errors');
		}
		header('Location: '.$self);
		exit;
	}
	if ($action === 'confirm_disable' && GETPOST('confirm', 'alpha') === 'yes' && $acces) {
		espace_set_status($db, $acces, 0);
		setEventMessages($langs->trans('AccesDesactiveMsg'), null, 'mesgs');
		header('Location: '.$self);
		exit;
	}
	// Permissions de l'espace : la direction les donne ou les retire, compte par compte
	if ($action === 'setperms') {
		$choix = GETPOST('perms', 'array');
		$perms = array();
		foreach (array_keys(espace_perm_catalogue($type)) as $k) {
			if (!empty($choix[$k])) {
				$perms[] = $k;
			}
		}
		if (espace_perm_enregistrer($db, $user, $type, $id, $perms) > 0) {
			setEventMessages($langs->trans('PermissionsEnregistrees'), null, 'mesgs');
		} else {
			setEventMessages($db->lasterror(), null, 'errors');
		}
		header('Location: '.$self);
		exit;
	}
	if ($action === 'confirm_permmodele' && GETPOST('confirm', 'alpha') === 'yes') {
		espace_perm_enregistrer($db, $user, $type, $id, null);
		setEventMessages($langs->trans('PermissionsModeleRetabli'), null, 'mesgs');
		header('Location: '.$self);
		exit;
	}
	if ($action === 'setenable' && $acces) {
		espace_set_status($db, $acces, 1);
		setEventMessages($langs->trans('AccesReactiveMsg'), null, 'mesgs');
		header('Location: '.$self);
		exit;
	}
}

/*
 * Affichage
 */
$titres = array(ESPACE_PARENT => 'AccesEspaceParents', ESPACE_ELEVE => 'AccesEspaceEleve', ESPACE_EMPLOYE => 'AccesEspaceEmploye');
$titre = $langs->trans($titres[$type]);
$gestion = ($type === ESPACE_EMPLOYE) && espace_employe_gestion($db, $cible);
llxHeader('', $cible->ref.' - '.$titre, '', '', 0, 0, '', '', '', 'mod-espace page-acces');

if ($action === 'reinit' && $peutGerer) {
	print $form->formconfirm($self, $langs->trans('ReinitialiserMotDePasse'), $langs->trans('ConfirmReinitialiser'), 'confirm_reinit', '', 'yes', 1);
}
if ($action === 'permmodele' && $peutGerer) {
	print $form->formconfirm($self, $langs->trans('RetablirModele'), $langs->trans('ConfirmRetablirModele'), 'confirm_permmodele', '', 'yes', 1);
}
if ($action === 'disable' && $peutGerer) {
	print $form->formconfirm($self, $langs->trans('DesactiverAcces'), $langs->trans('ConfirmDesactiver'), 'confirm_disable', '', 'yes', 1);
}

if ($type === ESPACE_ELEVE) {
	eleve_print_banner($cible, 'espace');
	print '<div class="underbanner clearboth"></div>';
} elseif ($type === ESPACE_EMPLOYE) {
	employe_print_banner($cible, 'espace');
	print '<div class="underbanner clearboth"></div>';
} else {
	$lien = $user->hasRight('eleves', 'responsable', 'lire') ? '<a href="'.dol_buildpath('/eleves/responsable/card.php', 1).'?id='.$id.'">'.$langs->trans('RetourFicheResponsable').'</a>' : '';
	print load_fiche_titre($titre.' — '.dol_escape_htmltag($cible->ref.' '.ecole_label($cible)), $lien, 'fa-house-user');
}

$etat = espace_etat($db, $acces);
$mdp = $peutGerer ? espace_mdp_fiche($acces) : '';
$eleves = espace_eleves_visibles($db, $type, $id);

print '<div class="fichecenter"><div class="fichehalfleft">';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('EtatAcces').'</td><td>'.espace_etat_badge($etat);
$aides = array('aucun' => 'AideEtatAucun', 'coupe' => ($type === ESPACE_PARENT ? 'AideEtatCoupeParent' : ($type === ESPACE_EMPLOYE ? 'AideEtatCoupeEmploye' : 'AideEtatCoupeEleve')), 'desactive' => ($type === ESPACE_EMPLOYE ? 'AideEtatDesactiveEmploye' : 'AideEtatDesactive'));
if (isset($aides[$etat])) {
	print '<br><span class="opacitymedium small">'.$langs->trans($aides[$etat]).'</span>';
}
print '</td></tr>';
print '<tr><td>'.$langs->trans('Identifiant').'</td><td><b>'.dol_escape_htmltag($cible->ref).'</b></td></tr>';
if ($gestion) {
	print '<tr><td>'.$langs->trans('MotDePasse').'</td><td><span class="badge badge-status4">'.$langs->trans('MotDePasseDolibarr').'</span><br><span class="opacitymedium small">'.$langs->trans('AideAccesGestion').'</span></td></tr>';
}
if ($acces && !$gestion) {
	print '<tr><td>'.$langs->trans('MotDePasse').'</td><td>';
	if ((int) $acces->mdp_provisoire) {
		print '<span class="badge badge-status1">'.$langs->trans('MotDePasseProvisoire').'</span>';
		if ($mdp !== '') {
			print ' &nbsp; <code style="font-size:1.1em">'.dol_escape_htmltag($mdp).'</code>';
		}
		print '<br><span class="opacitymedium small">'.$langs->trans('AideMotDePasseProvisoireEcole').'</span>';
	} else {
		print '<span class="badge badge-status4">'.$langs->trans('MotDePassePersonnel').'</span>';
	}
	print '</td></tr>';
}
if ($acces) {
	print '<tr><td>'.$langs->trans('DerniereConnexion').'</td><td>'.($acces->date_derniere_connexion ? dol_print_date($db->jdate($acces->date_derniere_connexion), 'dayhour', 'tzuserrel').' <span class="opacitymedium">('.$langs->trans('NbConnexions', (int) $acces->nb_connexions).')</span>' : '<span class="badge badge-status1">'.$langs->trans('JamaisConnecte').'</span>').'</td></tr>';
	print '<tr><td>'.$langs->trans('AccesCreeLe').'</td><td>'.dol_print_date($db->jdate($acces->date_creation), 'dayhour', 'tzuserrel').' — '.dol_escape_htmltag(ecole_user_label($db, $acces->fk_user_creat)).'</td></tr>';
	if ($acces->date_reinit) {
		print '<tr><td>'.$langs->trans('DerniereReinitialisation').'</td><td>'.dol_print_date($db->jdate($acces->date_reinit), 'dayhour', 'tzuserrel').' — '.dol_escape_htmltag(ecole_user_label($db, $acces->fk_user_reinit)).'</td></tr>';
	}
	if ($acces->langue) {
		print '<tr><td>'.$langs->trans('LangueChoisie').'</td><td>'.($acces->langue === 'ar_SA' ? 'العربية' : 'Français').'</td></tr>';
	}
}
print '<tr><td>'.$langs->trans($type === ESPACE_EMPLOYE ? 'AdresseEspacePersonnel' : 'AdresseEspace').'</td><td><a href="'.dol_escape_htmltag(espace_url($type)).'" target="_blank" rel="noopener">'.dol_escape_htmltag(espace_url($type)).'</a></td></tr>';
print '</table>';
print '</div><div class="fichehalfright">';

if ($type === ESPACE_EMPLOYE) {
	// Ce que l'employé voit dans son espace, selon ses permissions (réglées plus bas)
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.img_picto('', 'fa-th-large', 'class="pictofixedwidth"').$langs->trans('RubriquesEspace').'</td></tr>';
	print '<tr class="oddeven"><td>'.personnel_categories_badges((string) $cible->categories).'</td></tr>';
	foreach (espace_rubriques_employe($cible) as $r) {
		print '<tr class="oddeven"><td>'.img_picto('', $r[1], 'class="pictofixedwidth"').dol_escape_htmltag($langs->trans($r[0])).'</td></tr>';
	}
	print '</table>';
	$eleves = array('employe');
} else {
// Élèves visibles avec cet accès
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="3">'.img_picto('', 'fa-user-graduate', 'class="pictofixedwidth"').$langs->trans('ElevesVisibles').'</td></tr>';
foreach ($eleves as $e) {
	print '<tr class="oddeven"><td>'.$e->getNomUrl(1).'</td><td>'.dol_escape_htmltag(ecole_label($e)).'</td><td>'.ecole_link_label($db, 'EcoleClasse', 'classes/class/ecole_classe.class.php', $e->fk_classe).'</td></tr>';
}
if (empty($eleves)) {
	print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans($type === ESPACE_PARENT ? 'AucunEnfantInscrit' : 'EleveNonInscrit').'</span></td></tr>';
}
print '</table>';
print '<span class="opacitymedium small">'.$langs->trans('AideElevesVisibles').'</span>';
}
print '</div></div><div class="clearboth"></div>';

// Permissions de l'espace : une case par chose que le compte peut consulter ou faire
$catalogue = espace_perm_catalogue($type);
$accordees = espace_perms($db, $type, $id, $cible);
$regle = espace_perm_regle($db, $type, $id) !== null;
$modele = espace_perm_modele($type, $cible);
print '<br>';
if ($peutGerer) {
	print '<form method="POST" action="'.dol_escape_htmltag($self).'">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="setperms">';
}
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="3">'.img_picto('', 'fa-user-shield', 'class="pictofixedwidth"').$langs->trans('PermissionsEspace');
print ' &nbsp; <span class="badge '.($regle ? 'badge-status1' : 'badge-status4').'">'.$langs->trans($regle ? 'PermReglageDirection' : 'PermModeleCategorie').'</span></td></tr>';
$groupe = '';
foreach ($catalogue as $k => $def) {
	if ($def[2] !== $groupe) {
		$groupe = $def[2];
		print '<tr class="liste_titre"><td colspan="3" class="small">'.$langs->trans($groupe).'</td></tr>';
	}
	$on = in_array($k, $accordees, true);
	print '<tr class="oddeven"><td class="width25 center"><input type="checkbox" id="perm_'.$k.'" name="perms['.$k.']" value="1"'.($on ? ' checked' : '').($peutGerer ? '' : ' disabled').'></td>';
	print '<td><label for="perm_'.$k.'">'.img_picto('', (strpos($def[1], 'whatsapp') !== false ? 'fab ' : '').$def[1], 'class="pictofixedwidth"').$langs->trans($def[0]).'</label></td>';
	print '<td class="right opacitymedium small">'.(in_array($k, $modele, true) ? $langs->trans('DansLeModele') : '').'</td></tr>';
}
print '</table>';
print '<span class="opacitymedium small">'.$langs->trans('AidePermissionsEspace').'</span>';
if ($peutGerer) {
	print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
	if ($regle) {
		print ' &nbsp; <a class="button button-cancel" href="'.$self.'&action=permmodele&token='.newToken().'">'.$langs->trans('RetablirModele').'</a>';
	}
	print '</div></form>';
}

print dol_get_fiche_end();

// Boutons
print '<div class="tabsAction">';
if (!$acces) {
	$raison = '';
	$ok = espace_peut_creer($db, $type, $id, $raison);
	if ($peutGerer && $ok) {
		print dolGetButtonAction('', $langs->trans('CreerAcces'), 'default', $self.'&action=addacces&token='.newToken(), '', 1);
	} else {
		print dolGetButtonAction($ok ? $langs->trans('NotEnoughPermissions') : $raison, $langs->trans('CreerAcces'), 'default', '#', '', 0);
	}
} else {
	if ($mdp !== '') {
		print dolGetButtonAction('', $langs->trans('FicheAcces'), 'default', dol_buildpath('/espace/fiche.php', 1).'?acces='.((int) $acces->rowid), '', 1, array('attr' => array('target' => '_blank')));
		$wa = espace_whatsapp_url($db, $type, $cible, $mdp);
		if ($wa !== '') {
			print '<a class="butAction wa-btn" href="'.dol_escape_htmltag($wa).'" target="_blank" rel="noopener" style="background:#25d366;color:#fff;border-color:#25d366">'.img_picto('', 'fab fa-whatsapp', 'class="pictofixedwidth"').$langs->trans('EnvoyerWhatsApp').'</a>';
		}
	}
	if (!$gestion) {
		print dolGetButtonAction('', $langs->trans('ReinitialiserMotDePasse'), 'default', $self.'&action=reinit&token='.newToken(), '', $peutGerer);
	}
	if ((int) $acces->status) {
		print dolGetButtonAction('', $langs->trans('DesactiverAcces'), 'danger', $self.'&action=disable&token='.newToken(), '', $peutGerer);
	} else {
		print dolGetButtonAction('', $langs->trans('ReactiverAcces'), 'default', $self.'&action=setenable&token='.newToken(), '', $peutGerer);
	}
}
print '</div>';

llxFooter();
$db->close();
