<?php
/**
 * Fiche employé : création (utilisateur Dolibarr créé ou relié automatiquement), consultation en blocs
 * (identité, contact et urgence, contrat, paie et banque, diplômes et matières, compte, dossier),
 * modification, statuts (fin d'essai, suspension / congé, départ, reprise) avec motif, suppression.
 *
 * Fichier : custom/personnel/employe/card.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/personnel/class/ecole_employe.class.php');
dol_include_once('/personnel/class/ecole_employe_motif.class.php');

$langs->loadLangs(array('personnel@personnel', 'classes@classes', 'companies', 'users', 'other'));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'alpha');

$object = new EcoleEmploye($db);
$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label($object->table_element);
$form = new Form($db);

$can = function ($rubrique, $act) use ($user) {
	return (bool) $user->hasRight('personnel', $rubrique, $act);
};
$canread = $can('employe', 'lire');
$cancreate = $can('employe', 'creer');
$canedit = $can('employe', 'modifier');
$candelete = $can('employe', 'supprimer');
$canpaielire = $can('paie', 'lire');
$canpaieedit = $can('paie', 'modifier');
$canstatut = $can('statut', 'changer');
$candocread = $can('document', 'lire');

if (!$canread) {
	accessforbidden();
}

if ($id > 0 || ($ref !== '' && $action !== 'create' && $action !== 'add')) {
	if ($object->fetch($id, $ref !== '' ? $ref : null) <= 0) {
		accessforbidden($langs->trans('ErrorRecordNotFound'));
	}
	$object->fetch_optionals();
	$id = (int) $object->id;
}

$hookmanager->initHooks(array('ecoleemployecard', 'globalcard'));

$urlcard = dol_buildpath('/personnel/employe/card.php', 1);
$urllist = dol_buildpath('/personnel/employe/list.php', 1);

/**
 * Champs saisis dans le formulaire (création ou modification), selon les droits.
 *
 * @param  EcoleEmploye $object      Employé
 * @param  bool         $canpaieedit Droit de modifier la paie
 * @return string[]
 */
function employe_form_keys($object, $canpaieedit)
{
	$keys = array();
	foreach ($object->fields as $k => $f) {
		$bloc = isset($f['bloc']) ? $f['bloc'] : '';
		if (in_array($bloc, array('identite', 'contact', 'urgence', 'contrat', 'diplomes', 'espace'), true)) {
			$keys[] = $k;
		} elseif (in_array($bloc, array('paie', 'banque'), true) && $canpaieedit) {
			$keys[] = $k;
		}
	}
	return $keys;
}

/*
 * Actions
 */
$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	if ($cancel) {
		header('Location: '.($object->id > 0 ? $urlcard.'?id='.$object->id : $urllist));
		exit;
	}

	// Création : utilisateur Dolibarr créé automatiquement, ou utilisateur existant relié
	if ($action == 'add' && $cancreate) {
		$error = 0;
		personnel_fields_from_post($object, employe_form_keys($object, $canpaieedit));
		$object->status = (GETPOSTINT('statut_initial') === EcoleEmploye::STATUS_ESSAI) ? EcoleEmploye::STATUS_ESSAI : EcoleEmploye::STATUS_ACTIF;
		if (GETPOST('compte_mode', 'aZ09') === 'relier') {
			$object->link_user = GETPOSTINT('link_user');
			if ($object->link_user <= 0) {
				$error++;
				setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('UtilisateurARelier')), null, 'errors');
			}
		}
		if (!$error && $extrafields->setOptionalsFromPost(null, $object, '', 1) < 0) {
			$error++;
		}
		$db->begin();
		if (!$error && $object->create($user) < 0) {
			$error++;
			setEventMessages($object->error, $object->errors, 'errors');
		}
		if (!$error && $object->setMatieres($user, GETPOST('matieres', 'array')) < 0) {
			$error++;
			setEventMessages($object->error, $object->errors, 'errors');
		}
		if (!$error && $object->setClassesDeclarees($user, GETPOST('classes_decl', 'array')) < 0) {
			$error++;
			setEventMessages($object->error, $object->errors, 'errors');
		}
		if (!$error) {
			$db->commit();
			$photo = personnel_upload_file('photo', $object->getPhotoSubdir(), '', true);
			if (strpos($photo, 'ERR:') === 0) {
				setEventMessages($langs->trans('Photo').' : '.substr($photo, 4), null, 'warnings');
			} elseif ($photo !== '') {
				$db->query("UPDATE ".$db->prefix()."ecole_employe SET photo = '".$db->escape($photo)."' WHERE rowid = ".((int) $object->id));
			}
			setEventMessages($langs->trans('EmployeCree', $object->ref), null, 'mesgs');
			header('Location: '.$urlcard.'?id='.$object->id);
			exit;
		}
		$db->rollback();
		$object->id = 0;
		$object->fk_user = null;
		$action = 'create';
	}

	// Modification
	if ($action == 'update' && $canedit && $object->id > 0) {
		personnel_fields_from_post($object, employe_form_keys($object, $canpaieedit));
		$error = 0;
		if ($extrafields->setOptionalsFromPost(null, $object, '@GETPOSTISSET') < 0) {
			$error++;
		}
		$db->begin();
		if (!$error && $object->update($user) < 0) {
			$error++;
			setEventMessages($object->error, $object->errors, 'errors');
		}
		if (!$error && GETPOSTISSET('matieres_present') && $object->setMatieres($user, GETPOST('matieres', 'array')) < 0) {
			$error++;
			setEventMessages($object->error, $object->errors, 'errors');
		}
		if (!$error && GETPOSTISSET('matieres_present') && $object->setClassesDeclarees($user, GETPOST('classes_decl', 'array')) < 0) {
			$error++;
			setEventMessages($object->error, $object->errors, 'errors');
		}
		if (!$error) {
			$db->commit();
			$hors = $object->matieresEdtHorsFiche();
			if ($hors) {
				setEventMessages($langs->trans('WarningMatieresEdtHorsFiche', implode(', ', $hors)), null, 'warnings');
			}
			if (GETPOST('photo_delete', 'alpha') && !empty($object->photo)) {
				dol_delete_dir_recursive($conf->personnel->dir_output.'/'.$object->getPhotoSubdir());
				$db->query("UPDATE ".$db->prefix()."ecole_employe SET photo = NULL WHERE rowid = ".((int) $object->id));
			}
			$photo = personnel_upload_file('photo', $object->getPhotoSubdir(), '', true);
			if (strpos($photo, 'ERR:') === 0) {
				setEventMessages($langs->trans('Photo').' : '.substr($photo, 4), null, 'warnings');
			} elseif ($photo !== '') {
				if (!empty($object->photo) && $object->photo !== $photo) {
					dol_delete_file($conf->personnel->dir_output.'/'.$object->getPhotoSubdir().'/'.$object->photo);
				}
				$db->query("UPDATE ".$db->prefix()."ecole_employe SET photo = '".$db->escape($photo)."' WHERE rowid = ".((int) $object->id));
			}
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			header('Location: '.$urlcard.'?id='.$object->id);
			exit;
		}
		$db->rollback();
		$action = 'edit';
	}

	// Actions confirmées
	if ($confirm === 'yes' && $object->id > 0) {
		$done = null;
		if ($action == 'confirm_statut' && $canstatut) {
			$datefin = GETPOSTISSET('datefinmonth') ? personnel_posted_date('datefin') : null;
			$done = $object->changerStatut($user, GETPOSTINT('newstatus'), personnel_posted_date('datestatut', dol_now()), GETPOSTINT('fk_motif'), GETPOST('motif', 'alphanohtml'), $datefin);
			$msg = 'StatutModifie';
		} elseif ($action == 'confirm_sync' && $canedit) {
			$db->begin();
			$done = $object->syncUser($user);
			if ($done > 0) {
				$db->commit();
			} else {
				$db->rollback();
			}
			$msg = 'CompteMisAJour';
		} elseif ($action == 'confirm_delete' && $candelete) {
			if ($object->delete($user) > 0) {
				setEventMessages($langs->trans('EmployeSupprime', $object->ref), null, 'mesgs');
				header('Location: '.$urllist);
				exit;
			}
			$done = -1;
		}
		if ($done !== null) {
			if ($done > 0) {
				setEventMessages($langs->trans($msg), null, 'mesgs');
			} else {
				setEventMessages($object->error, $object->errors, 'errors');
			}
			header('Location: '.$urlcard.'?id='.$object->id);
			exit;
		}
	}
}

/*
 * Affichage
 */

/**
 * Début d'un bloc de la fiche (tableau avec titre).
 *
 * @param  string $title Titre traduit
 * @param  string $icon  Picto
 * @return void
 */
function employe_bloc_start($title, $icon)
{
	print '<table class="noborder centpercent tableforfield employe-bloc">';
	print '<tr class="liste_titre"><td colspan="2">'.img_picto('', $icon, 'class="pictofixedwidth"').dol_escape_htmltag($title).'</td></tr>';
}

/**
 * Ligne « libellé / valeur » d'un bloc.
 *
 * @param  string $label   Libellé (HTML autorisé)
 * @param  string $value   Valeur (HTML)
 * @param  string $tdclass Classe CSS de la cellule du libellé
 * @return void
 */
function employe_bloc_row($label, $value, $tdclass = 'titlefield')
{
	print '<tr><td class="'.$tdclass.'">'.$label.'</td><td>'.$value.'</td></tr>';
}

/**
 * Lignes de saisie des champs d'un bloc.
 *
 * @param  EcoleEmploye $object Employé
 * @param  string[]     $keys   Champs à afficher
 * @param  string       $bloc   Bloc
 * @return void
 */
function employe_form_rows($object, $keys, $bloc)
{
	global $langs, $form;
	foreach ($object->fields as $k => $f) {
		if (!in_array($k, $keys, true) || (isset($f['bloc']) ? $f['bloc'] : '') !== $bloc) {
			continue;
		}
		$label = $langs->trans($f['label']);
		if (!empty($f['help'])) {
			$label = $form->textwithpicto($label, $langs->trans($f['help']));
		}
		$value = $object->$k;
		if (in_array($k, array('salaire_base', 'taux_horaire', 'taux_heure_sup', 'heures_semaine'), true) && $value !== null && $value !== '') {
			$value = price2num((float) $value);
		}
		$css = (!empty($f['notnull']) && $f['notnull'] > 0) ? 'titlefieldcreate fieldrequired' : 'titlefieldcreate';
		print '<tr class="field_'.$k.'"><td class="'.$css.'">'.$label.'</td><td>'.$object->showInputField($f, $k, $value, '', '', '', !empty($f['css']) ? $f['css'] : '').'</td></tr>';
	}
}

/**
 * Formulaire de création ou de modification.
 *
 * @param  EcoleEmploye $object      Employé
 * @param  ExtraFields  $extrafields Champs supplémentaires
 * @param  bool         $create      true = création
 * @param  bool         $canpaieedit Droit de modifier la paie
 * @return void
 */
function employe_print_form($object, $extrafields, $create, $canpaieedit)
{
	global $langs, $db, $form;

	$keys = employe_form_keys($object, $canpaieedit);

	print '<div class="fichecenter"><div class="fichehalfleft">';

	// Identité
	employe_bloc_start($langs->trans('BlocIdentite'), 'fa-id-card');
	if ($create) {
		employe_bloc_row($langs->trans('MatriculeEmploye'), '<span class="opacitymedium">'.$langs->trans('MatriculeAuto', $object->getNextMatricule()).'</span>', 'titlefieldcreate');
	} else {
		employe_bloc_row($langs->trans('MatriculeEmploye'), dol_escape_htmltag($object->ref), 'titlefieldcreate');
	}
	employe_form_rows($object, $keys, 'identite');
	$photo = '<input type="file" name="photo" accept="image/*" class="flat">';
	if (!$create && !empty($object->photo)) {
		$url = employe_photo_url($object, true);
		$photo = ($url ? '<img src="'.$url.'" style="max-height:60px;vertical-align:middle" class="marginrightonly">' : '')
			.'<label><input type="checkbox" name="photo_delete" value="1"> '.$langs->trans('SupprimerPhoto').'</label><br>'.$photo;
	}
	employe_bloc_row($langs->trans('Photo'), $photo, 'titlefieldcreate');
	print '</table><br>';

	// Contrat
	employe_bloc_start($langs->trans('BlocContrat'), 'fa-file-signature');
	if ($create) {
		$choix = array(EcoleEmploye::STATUS_ACTIF => $langs->trans('StatutActif'), EcoleEmploye::STATUS_ESSAI => $langs->trans('StatutEssai'));
		$v = GETPOSTINT('statut_initial') ? GETPOSTINT('statut_initial') : EcoleEmploye::STATUS_ACTIF;
		employe_bloc_row($langs->trans('StatutInitial'), $form->selectarray('statut_initial', $choix, $v, 0), 'titlefieldcreate');
	}
	employe_form_rows($object, $keys, 'contrat');
	print '</table><br>';

	// Diplômes, expérience et matières qu'il peut enseigner
	employe_bloc_start($langs->trans('BlocDiplomes'), 'fa-graduation-cap');
	employe_form_rows($object, array_diff($keys, array('observations')), 'diplomes');
	$sel = GETPOSTISSET('matieres_present') ? GETPOST('matieres', 'array') : array_keys($create ? array() : $object->getMatieres());
	$label = $form->textwithpicto($langs->trans('MatieresEnseignables'), $langs->trans('MatieresEnseignablesHelp'));
	employe_bloc_row($label, '<input type="hidden" name="matieres_present" value="1">'.$form->multiselectarray('matieres', personnel_matieres_choix($db, $sel), $sel, 0, 0, 'minwidth300', 0, '100%'), 'titlefieldcreate');
	$selc = GETPOSTISSET('matieres_present') ? GETPOST('classes_decl', 'array') : array_keys($create ? array() : $object->getClassesDeclarees());
	$label = $form->textwithpicto($langs->trans('ClassesDeclarees'), $langs->trans('ClassesDeclareesHelp'));
	employe_bloc_row($label, $form->multiselectarray('classes_decl', personnel_classes_choix($db), $selc, 0, 0, 'minwidth300', 0, '100%'), 'titlefieldcreate');
	$f = $object->fields['observations'];
	employe_bloc_row($langs->trans('Observations'), $object->showInputField($f, 'observations', $object->observations, '', '', '', 'minwidth300'), 'titlefieldcreate');
	print '</table><br>';

	print '</div><div class="fichehalfright">';

	// Contact et urgence
	employe_bloc_start($langs->trans('BlocContact'), 'fa-phone');
	employe_form_rows($object, $keys, 'contact');
	print '<tr class="liste_titre"><td colspan="2">'.img_picto('', 'fa-ambulance', 'class="pictofixedwidth"').$langs->trans('ContactUrgence').'</td></tr>';
	employe_form_rows($object, $keys, 'urgence');
	print '</table><br>';

	// Paie et banque (droit « modifier la paie »)
	if ($canpaieedit) {
		employe_bloc_start($langs->trans('BlocPaie'), 'fa-coins');
		employe_form_rows($object, $keys, 'paie');
		print '<tr><td colspan="2"><span class="opacitymedium small">'.$langs->trans('PaieModifAide').'</span></td></tr>';
		print '<tr class="liste_titre"><td colspan="2">'.img_picto('', 'fa-university', 'class="pictofixedwidth"').$langs->trans('BlocBanque').'</td></tr>';
		employe_form_rows($object, $keys, 'banque');
		print '</table><br>';
		print '<script>$(function(){function t(){var m=$("select[name=mode_paie]").val();$("[name=salaire_base],[name=heures_semaine],[name=taux_heure_sup]").closest("tr").toggle(m!=="heure");}$("select[name=mode_paie]").on("change",t);t();});</script>';
	}

	// Compte utilisateur Dolibarr
	employe_bloc_start($langs->trans('BlocCompte'), 'fa-user-lock');
	if ($create) {
		$mode = GETPOST('compte_mode', 'aZ09') === 'relier' ? 'relier' : 'creer';
		print '<tr><td colspan="2">';
		print '<label class="marginrightonly"><input type="radio" name="compte_mode" value="creer"'.($mode === 'creer' ? ' checked' : '').'> '.$langs->trans('CompteCreerAuto').'</label><br>';
		print '<label><input type="radio" name="compte_mode" value="relier"'.($mode === 'relier' ? ' checked' : '').'> '.$langs->trans('CompteRelierExistant').'</label>';
		print '</td></tr>';
		print '<tr id="row_link_user"><td class="titlefieldcreate">'.$langs->trans('UtilisateurARelier').'</td><td>'.$form->selectarray('link_user', personnel_users_reliables($db), GETPOSTINT('link_user'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth300', 1).'</td></tr>';
		print '<tr><td colspan="2"><span class="opacitymedium small">'.$langs->trans('CompteAide').'</span></td></tr>';
		print '<script>$(function(){function t(){$("#row_link_user").toggle($("input[name=compte_mode]:checked").val()==="relier");}$("input[name=compte_mode]").on("change",t);t();});</script>';
	} else {
		$u = personnel_user_row($db, (int) $object->fk_user);
		employe_bloc_row($langs->trans('CompteUtilisateur'), $u ? dol_escape_htmltag($u->login) : '<span class="opacitymedium">'.$langs->trans('AucunCompte').'</span>', 'titlefieldcreate');
		print '<tr><td colspan="2"><span class="opacitymedium small">'.$langs->trans('CompteAideModif').'</span></td></tr>';
	}
	print '<tr class="liste_titre"><td colspan="2">'.img_picto('', 'fa-mobile-alt', 'class="pictofixedwidth"').$langs->trans('BlocEspacePersonnel').'</td></tr>';
	employe_form_rows($object, $keys, 'espace');
	print '</table><br>';
	// « Saisie des notes » seulement pour un enseignant, « Envoi WhatsApp » seulement pour un surveillant (selon les catégories choisies)
	print '<script>$(function(){function t(){var c=$("#categories").val()||[];$("tr.field_saisie_notes").toggle(c.indexOf("enseignant")>=0);$("tr.field_envoi_whatsapp").toggle(c.indexOf("surveillant")>=0);}$("#categories").on("change",t);t();});</script>';

	// Champs supplémentaires définis par l'établissement
	if (!empty($extrafields->attributes[$object->table_element]['label'])) {
		employe_bloc_start($langs->trans('InformationsComplementaires'), 'fa-list');
		print $object->showOptionals($extrafields, $create ? 'create' : 'edit', array('colspanvalue' => 1));
		print '</table><br>';
	}

	print '</div></div><div class="clearboth"></div>';
}

$title = $langs->trans('Employe');
if ($object->id > 0) {
	$title = $object->ref.' - '.$object->nom_fr;
}
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-personnel page-card');
print '<style>input[name$="_ar"],textarea[name$="_ar"]{direction:rtl;text-align:right}table.employe-bloc td.titlefield{width:35%}</style>';

if ($action == 'create') {
	if (!$cancreate) {
		accessforbidden();
	}
	print load_fiche_titre($langs->trans('NouvelEmploye'), '', $object->picto);
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" enctype="multipart/form-data">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	print dol_get_fiche_head(array(), '');
	print '<span class="opacitymedium">'.$langs->trans('ChampsFacultatifs').'</span><br><br>';
	employe_print_form($object, $extrafields, true, $canpaieedit);
	print dol_get_fiche_end();
	print $form->buttonsSaveCancel('Create');
	print '</form>';
} elseif ($object->id > 0 && $action == 'edit' && $canedit) {
	print load_fiche_titre($langs->trans('ModifierEmploye').' : '.dol_escape_htmltag($object->ref), '', $object->picto);
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" enctype="multipart/form-data">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
	print dol_get_fiche_head(array(), '');
	employe_print_form($object, $extrafields, false, $canpaieedit);
	print dol_get_fiche_end();
	print $form->buttonsSaveCancel();
	print '</form>';
} elseif ($object->id > 0) {
	$page = $_SERVER['PHP_SELF'].'?id='.((int) $object->id);
	$today = dol_now();

	// Fenêtres de confirmation des changements de statut (un bouton par cas)
	$cas = GETPOST('cas', 'aZ09');
	$statuts = array('finessai' => EcoleEmploye::STATUS_ACTIF, 'suspendre' => EcoleEmploye::STATUS_SUSPENDU, 'depart' => EcoleEmploye::STATUS_PARTI,
		'reprise' => EcoleEmploye::STATUS_ACTIF, 'reembaucher' => EcoleEmploye::STATUS_ACTIF);
	if ($action == 'statut' && $canstatut && isset($statuts[$cas])) {
		$new = $statuts[$cas];
		$q = array(array('type' => 'hidden', 'name' => 'newstatus', 'value' => $new),
			array('type' => 'date', 'name' => 'datestatut', 'label' => $langs->trans('DateEffet'), 'value' => $today));
		$usage = EcoleEmploye::usageMotifStatut($new);
		if ($usage !== '') {
			$q[] = array('type' => 'select', 'name' => 'fk_motif', 'label' => $langs->trans('Motif'), 'values' => EcoleEmployeMotif::choix($db, $usage), 'morecss' => 'minwidth200');
		}
		if ($new === EcoleEmploye::STATUS_SUSPENDU) {
			$q[] = array('type' => 'date', 'name' => 'datefin', 'label' => $langs->trans('FinPrevue'), 'value' => -1);
		}
		$q[] = array('type' => 'text', 'name' => 'motif', 'label' => $langs->trans('Commentaire'), 'morecss' => 'minwidth300');
		print $form->formconfirm($page, $langs->trans('StatutCas_'.$cas), $langs->trans('ConfirmStatutCas_'.$cas, $object->nom_fr), 'confirm_statut', $q, 'yes', 1, 0, 600);
	}
	if ($action == 'sync' && $canedit) {
		print $form->formconfirm($page, $langs->trans('MettreAJourCompte'), $langs->trans('ConfirmMettreAJourCompte'), 'confirm_sync', '', 'yes', 1);
	}
	if ($action == 'delete' && $candelete) {
		$raisons = $object->utilisations();
		if ($raisons) {
			setEventMessages($langs->trans('ErrorEmployeUtilise', implode(', ', $raisons)), null, 'errors');
		} else {
			print $form->formconfirm($page, $langs->trans('SupprimerEmploye'), $langs->trans('ConfirmSupprimerEmploye', $object->ref), 'confirm_delete', '', 0, 1);
		}
	}

	employe_print_banner($object, 'card');

	print '<div class="fichecenter"><div class="fichehalfleft">';
	print '<div class="underbanner clearboth"></div>';

	// Identité
	employe_bloc_start($langs->trans('BlocIdentite'), 'fa-id-card');
	foreach (array('nom_fr', 'nom_ar', 'categories', 'date_naissance', 'lieu_naissance', 'sexe', 'fk_nationalite', 'nni', 'situation_familiale', 'nb_enfants', 'adresse') as $k) {
		$v = $object->showOutputField($object->fields[$k], $k, $object->$k);
		if ($k === 'nom_ar' && $v !== '') {
			$v = '<span dir="rtl">'.$v.'</span>';
		}
		if ($k === 'date_naissance' && $object->getAge() !== null) {
			$v .= ' <span class="opacitymedium">('.$langs->trans('AgeAns', $object->getAge()).')</span>';
		}
		if ($k === 'adresse') {
			$v = dol_nl2br(dol_escape_htmltag((string) $object->adresse));
		}
		employe_bloc_row($langs->trans($object->fields[$k]['label']), $v);
	}
	print '</table><br>';

	// Contrat et statut
	employe_bloc_start($langs->trans('BlocContrat'), 'fa-file-signature');
	employe_bloc_row($langs->trans('MatriculeEmploye'), dol_escape_htmltag($object->ref));
	foreach (array('poste', 'type_contrat', 'date_embauche', 'date_fin_contrat', 'date_fin_essai') as $k) {
		$v = $object->showOutputField($object->fields[$k], $k, $object->$k);
		if ($k === 'date_embauche' && $object->getAnciennete() !== '') {
			$v .= ' <span class="opacitymedium">('.dol_escape_htmltag($object->getAnciennete()).')</span>';
		}
		if ($k === 'date_fin_essai' && (int) $object->status !== EcoleEmploye::STATUS_ESSAI && empty($object->date_fin_essai)) {
			continue;
		}
		employe_bloc_row($langs->trans($object->fields[$k]['label']), $v);
	}
	$statut = $object->getLibStatut(5);
	$der = $object->getDernierStatut();
	if (!empty($object->date_statut)) {
		$statut .= ' <span class="opacitymedium">'.$langs->trans('DepuisLe', dol_print_date($object->date_statut, 'day')).'</span>';
	}
	if ($der && ($der->fk_motif || $der->motif)) {
		$statut .= '<br><span class="opacitymedium">'.dol_escape_htmltag(trim(($der->fk_motif ? ecole_label($der) : '').($der->fk_motif && $der->motif ? ' — ' : '').(string) $der->motif)).'</span>';
	}
	if ($der && $der->date_fin_prevue && (int) $object->status === EcoleEmploye::STATUS_SUSPENDU) {
		$statut .= '<br><span class="opacitymedium">'.$langs->trans('FinPrevueLe', dol_print_date($db->jdate($der->date_fin_prevue), 'day')).'</span>';
	}
	employe_bloc_row($langs->trans('StatutEmploye'), $statut);
	print '</table><br>';

	// Diplômes et matières
	employe_bloc_start($langs->trans('BlocDiplomes'), 'fa-graduation-cap');
	foreach (array('diplome', 'specialite', 'experience') as $k) {
		$v = $object->showOutputField($object->fields[$k], $k, $object->$k);
		if ($k === 'experience' && $object->experience !== null && $object->experience !== '') {
			$v = $langs->trans('NbAns', (int) $object->experience);
		}
		employe_bloc_row($langs->trans($object->fields[$k]['label']), $v);
	}
	$mat = array();
	foreach ($object->getMatieres() as $m) {
		$mat[] = '<span class="badge badge-status1 marginrightonly" style="font-weight:normal" title="'.dol_escape_htmltag($m->ref).'">'.dol_escape_htmltag(ecole_label($m)).'</span>';
	}
	employe_bloc_row($langs->trans('MatieresEnseignables'), $mat ? implode('', $mat) : '<span class="opacitymedium">—</span>');
	$cls = array();
	foreach ($object->getClassesDeclarees() as $c) {
		$cls[] = '<span class="badge badge-status0 marginrightonly" style="font-weight:normal" title="'.dol_escape_htmltag(ecole_label($c)).'">'.dol_escape_htmltag($c->ref).'</span>';
	}
	employe_bloc_row($langs->trans('ClassesDeclarees'), $cls ? implode('', $cls) : '<span class="opacitymedium">—</span>');
	$hors = $object->matieresEdtHorsFiche();
	if ($hors) {
		employe_bloc_row('<span class="error">'.$langs->trans('MatieresEdtHorsFiche').'</span>', '<span class="badge badge-warning">'.dol_escape_htmltag(implode(', ', $hors)).'</span>');
	}
	employe_bloc_row($langs->trans('Observations'), dol_nl2br(dol_escape_htmltag((string) $object->observations)));
	print '</table><br>';

	// Champs supplémentaires
	if (!empty($extrafields->attributes[$object->table_element]['label'])) {
		employe_bloc_start($langs->trans('InformationsComplementaires'), 'fa-list');
		print $object->showOptionals($extrafields, 'view', array('colspanvalue' => 1));
		print '</table><br>';
	}

	print '</div><div class="fichehalfright">';
	print '<div class="underbanner clearboth"></div>';

	// Contact et urgence
	employe_bloc_start($langs->trans('BlocContact'), 'fa-phone');
	employe_bloc_row($langs->trans('Telephone'), dol_print_phone((string) $object->telephone, '', 0, 0, 'AC_TEL', '&nbsp;', 'phone'));
	if (!empty($object->whatsapp)) {
		employe_bloc_row($langs->trans('WhatsApp'), '<a href="'.dol_escape_htmltag(personnel_whatsapp_url($object->whatsapp)).'" target="_blank" rel="noopener">'.img_picto('', 'fa-whatsapp', 'class="pictofixedwidth"').dol_escape_htmltag($object->whatsapp).'</a>');
	}
	employe_bloc_row($langs->trans('Email'), !empty($object->email) ? dol_print_email($object->email, 0, 0, 1) : '');
	$urg = '';
	if (!empty($object->urgence_nom) || !empty($object->urgence_telephone)) {
		$urg = dol_escape_htmltag((string) $object->urgence_nom);
		if (!empty($object->urgence_lien)) {
			$urg .= ' <span class="opacitymedium">('.dol_escape_htmltag($object->urgence_lien).')</span>';
		}
		$urg .= ' '.dol_print_phone((string) $object->urgence_telephone, '', 0, 0, 'AC_TEL', '&nbsp;', 'phone');
	}
	employe_bloc_row($langs->trans('ContactUrgence'), $urg);
	print '</table><br>';

	// Paie et banque
	if ($canpaielire) {
		employe_bloc_start($langs->trans('BlocPaie'), 'fa-coins');
		employe_bloc_row($langs->trans('ModePaie'), $object->mode_paie ? $object->showOutputField($object->fields['mode_paie'], 'mode_paie', $object->mode_paie) : '<span class="opacitymedium">'.$langs->trans('NonRenseigne').'</span>');
		if ($object->mode_paie !== 'heure') {
			employe_bloc_row($langs->trans('SalaireBase'), $object->showOutputField($object->fields['salaire_base'], 'salaire_base', $object->salaire_base));
		}
		employe_bloc_row($langs->trans('TauxHoraire'), $object->showOutputField($object->fields['taux_horaire'], 'taux_horaire', $object->taux_horaire));
		if ($object->mode_paie === 'fixe' && (float) $object->heures_semaine > 0) {
			employe_bloc_row($langs->trans('HeuresSemaine'), $object->showOutputField($object->fields['heures_semaine'], 'heures_semaine', $object->heures_semaine));
			employe_bloc_row($langs->trans('TauxHeureSup'), $object->showOutputField($object->fields['taux_heure_sup'], 'taux_heure_sup', $object->taux_heure_sup ? $object->taux_heure_sup : $object->taux_horaire));
		}
		$banque = trim(dol_escape_htmltag((string) $object->banque_nom).' '.dol_escape_htmltag((string) $object->banque_compte));
		employe_bloc_row($langs->trans('BlocBanque'), $banque);
		employe_bloc_row($langs->trans('MobileMoney'), dol_escape_htmltag((string) $object->mobile_money));
		print '</table><br>';
	}

	// Compte utilisateur Dolibarr (géré automatiquement)
	employe_bloc_start($langs->trans('BlocCompte'), 'fa-user-lock');
	$u = personnel_user_row($db, (int) $object->fk_user);
	if ($u) {
		$lien = $user->hasRight('user', 'user', 'lire') ? '<a href="'.DOL_URL_ROOT.'/user/card.php?id='.((int) $u->rowid).'">'.img_picto('', 'user', 'class="pictofixedwidth"').dol_escape_htmltag($u->login).'</a>' : dol_escape_htmltag($u->login);
		employe_bloc_row($langs->trans('Identifiant'), $lien.' '.((int) $u->statut === 1 ? '<span class="badge badge-status4">'.$langs->trans('CompteActif').'</span>' : '<span class="badge badge-status5">'.$langs->trans('CompteDesactive').'</span>'));
		employe_bloc_row($langs->trans('DerniereConnexion'), $u->datelastlogin ? dol_print_date($db->jdate($u->datelastlogin), 'dayhour', 'tzuserrel') : '<span class="opacitymedium">'.$langs->trans('JamaisConnecte').'</span>');
	} else {
		employe_bloc_row($langs->trans('CompteUtilisateur'), '<span class="badge badge-warning">'.$langs->trans('AucunCompte').'</span>');
	}
	print '<tr><td colspan="2"><span class="opacitymedium small">'.$langs->trans('CompteAideVue').'</span></td></tr>';
	// Ce que la direction permet dans l'espace du personnel
	print '<tr class="liste_titre"><td colspan="2">'.img_picto('', 'fa-mobile-alt', 'class="pictofixedwidth"').$langs->trans('BlocEspacePersonnel').'</td></tr>';
	$permis = function ($ok) use ($langs) {
		return $ok ? '<span class="badge badge-status4">'.$langs->trans('Autorisee').'</span>' : '<span class="badge badge-status8">'.$langs->trans('Bloquee').'</span>';
	};
	if (in_array('enseignant', $object->getCategories(), true)) {
		employe_bloc_row($form->textwithpicto($langs->trans('SaisieNotesEspace'), $langs->trans('SaisieNotesEspaceHelp')), $permis(personnel_saisie_notes_autorisee($object)));
	}
	if (in_array('surveillant', $object->getCategories(), true)) {
		employe_bloc_row($form->textwithpicto($langs->trans('EnvoiWhatsappEspace'), $langs->trans('EnvoiWhatsappEspaceHelp')), $permis(personnel_whatsapp_autorise($object)));
	}
	$mp = $permis(personnel_modif_profil_autorisee($object));
	if ($object->modif_profil === null || $object->modif_profil === '') {
		$mp .= ' <span class="opacitymedium">'.$langs->trans('ReglageGeneral').'</span>';
	}
	employe_bloc_row($form->textwithpicto($langs->trans('ModifProfilEspace'), $langs->trans('ModifProfilEspaceHelp')), $mp);
	print '</table><br>';

	// Pièces du dossier
	if ($candocread) {
		$pieces = $object->getPieces();
		$manquantes = $object->getPiecesManquantes();
		$demandees = 0;
		foreach ($pieces as $o) {
			$demandees += $o->demandee ? 1 : 0;
		}
		employe_bloc_start($langs->trans('BlocDocuments'), 'fa-folder-open');
		$lien = '<a href="'.dol_buildpath('/personnel/employe/documents.php', 1).'?id='.((int) $object->id).'">'.$langs->trans('VoirPieces').'</a>';
		employe_bloc_row($langs->trans('PiecesFournies'), ($demandees - count($manquantes)).' / '.$demandees.' &nbsp; '.$lien);
		if ($manquantes) {
			$l = array();
			foreach ($manquantes as $o) {
				$l[] = dol_escape_htmltag(ecole_label($o));
			}
			employe_bloc_row('<span class="error">'.$langs->trans('PiecesManquantes').'</span>', '<span class="badge badge-danger">'.count($manquantes).'</span> '.implode(', ', $l));
		}
		print '</table>';
	}

	print '</div></div><div class="clearboth"></div>';
	print dol_get_fiche_end();

	// Boutons
	print '<div class="tabsAction">';
	$st = (int) $object->status;
	print dolGetButtonAction('', $langs->trans('Modify'), 'default', $page.'&action=edit&token='.newToken(), '', $canedit);
	$boutons = array(
		EcoleEmploye::STATUS_ESSAI => array('finessai', 'suspendre', 'depart'),
		EcoleEmploye::STATUS_ACTIF => array('suspendre', 'depart'),
		EcoleEmploye::STATUS_SUSPENDU => array('reprise', 'depart'),
		EcoleEmploye::STATUS_PARTI => array('reembaucher'),
	);
	foreach (isset($boutons[$st]) ? $boutons[$st] : array() as $c) {
		print dolGetButtonAction('', $langs->trans('StatutCas_'.$c), 'default', $page.'&action=statut&cas='.$c.'&token='.newToken(), '', $canstatut);
	}
	print dolGetButtonAction($langs->trans('MettreAJourCompteAide'), $langs->trans('MettreAJourCompte'), 'default', $page.'&action=sync&token='.newToken(), '', $canedit);
	print ecole_bouton_supprimer($langs->trans('Delete'), $page.'&action=delete&token='.newToken(), $candelete, $object->getDeleteBlockers());
	print '</div>';
}

llxFooter();
$db->close();
