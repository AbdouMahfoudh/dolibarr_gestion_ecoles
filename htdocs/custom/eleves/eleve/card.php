<?php
/**
 * Fiche élève : création (pré-inscription), consultation en 5 blocs, modification,
 * inscription (validation par la direction), statuts, changement de classe, situation financière.
 *
 * Fichier : custom/eleves/eleve/card.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/eleves/class/ecole_eleve.class.php');
dol_include_once('/eleves/core/lib/paiements.lib.php');

$langs->loadLangs(array('eleves@eleves', 'classes@classes', 'companies', 'bills', 'other'));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'alpha');

$object = new EcoleEleve($db);
$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label($object->table_element);
$form = new Form($db);

// Droits (une rubrique et une action par droit)
$can = function ($rubrique, $act) use ($user) {
	return (bool) $user->hasRight('eleves', $rubrique, $act);
};
$canread = $can('eleve', 'lire');
$cancreate = $can('eleve', 'creer');
$canedit = $can('eleve', 'modifier');
$candelete = $can('eleve', 'supprimer');
$cansanteread = $can('sante', 'lire');
$cansanteedit = $can('sante', 'modifier');
$canvalider = $can('inscription', 'valider');
$canstatut = $can('inscription', 'statut');
$canpaielire = $can('paiement', 'lire');
$canencaisser = $can('paiement', 'encaisser');
$canclasse = $can('classe', 'changer');
$candocread = $can('document', 'lire');
$canrespread = $can('responsable', 'lire');
$canrespcreate = $can('responsable', 'creer');

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

$hookmanager->initHooks(array('ecoleelevecard', 'globalcard'));

$urlcard = dol_buildpath('/eleves/eleve/card.php', 1);
$urllist = dol_buildpath('/eleves/eleve/list.php', 1);

/**
 * Champs saisis dans le formulaire (création ou modification), selon les droits et le statut.
 *
 * @param  EcoleEleve $object        Élève
 * @param  bool       $cansanteedit  Droit de modifier la santé
 * @param  bool       $create        true = création
 * @return string[]
 */
function eleve_form_keys($object, $cansanteedit, $create)
{
	$keys = array();
	foreach ($object->fields as $k => $f) {
		$bloc = isset($f['bloc']) ? $f['bloc'] : '';
		if (in_array($bloc, array('identite', 'scolarite', 'responsable'), true) && $k !== 'ref') {
			$keys[] = $k;
		} elseif (in_array($bloc, array('sante', 'urgence'), true) && $cansanteedit) {
			$keys[] = $k;
		}
	}
	// Une fois l'élève inscrit, la classe ne change plus que par « Changer de classe » (avec historique)
	if (!$create && !in_array((int) $object->status, array(EcoleEleve::STATUS_PREINSCRIT, EcoleEleve::STATUS_ATTENTE), true)) {
		$keys = array_diff($keys, array('fk_classe'));
	}
	return $keys;
}

/**
 * Date saisie dans un sélecteur de date Dolibarr (champs xxxday / xxxmonth / xxxyear), ou maintenant.
 *
 * @param  string $name Nom du champ
 * @return int
 */
function eleve_posted_date($name)
{
	$d = dol_mktime(12, 0, 0, GETPOSTINT($name.'month'), GETPOSTINT($name.'day'), GETPOSTINT($name.'year'));
	return $d ? $d : dol_now();
}

/**
 * Situation financière de l'élève (calculée une fois par page).
 *
 * @param  DoliDB     $db     Handler base
 * @param  EcoleEleve $object Élève
 * @return array
 */
function eleve_situation_cache($db, $object)
{
	static $cache = array();
	if (!isset($cache[(int) $object->id])) {
		$cache[(int) $object->id] = eleves_situation($db, $object);
	}
	return $cache[(int) $object->id];
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

	// Création (pré-inscription), avec éventuellement un nouveau responsable
	if ($action == 'add' && $cancreate) {
		$error = 0;
		eleves_fields_from_post($object, eleve_form_keys($object, $cansanteedit, true));
		if ($extrafields->setOptionalsFromPost(null, $object, '', 1) < 0) {
			$error++;
		}

		$db->begin();
		if (!$error && GETPOST('resp_mode', 'aZ09') === 'nouveau') {
			if (!$canrespcreate) {
				$error++;
				setEventMessages($langs->trans('NotEnoughPermissions'), null, 'errors');
			} else {
				$resp = new EcoleResponsable($db);
				foreach (array('nom_fr', 'nom_ar', 'lien_parente', 'telephone', 'whatsapp', 'email') as $k) {
					$resp->$k = GETPOST('resp_'.$k, 'alphanohtml');
				}
				$resp->adresse = GETPOST('resp_adresse', 'nohtml');
				if ($resp->lien_parente === '-1') {
					$resp->lien_parente = '';
				}
				if ($resp->create($user) < 0) {
					$error++;
					setEventMessages($langs->trans('Responsable').' : '.$resp->error, null, 'errors');
				} else {
					$object->fk_responsable = $resp->id;
				}
			}
		}
		if (!$error && $object->create($user) < 0) {
			$error++;
			setEventMessages($object->error, $object->errors, 'errors');
		}
		if (!$error) {
			$db->commit();
			$photo = eleves_upload_file('photo', $object->getPhotoSubdir(), '', true);
			if (strpos($photo, 'ERR:') === 0) {
				setEventMessages($langs->trans('Photo').' : '.substr($photo, 4), null, 'warnings');
			} elseif ($photo !== '') {
				$db->query("UPDATE ".$db->prefix()."ecole_eleve SET photo = '".$db->escape($photo)."' WHERE rowid = ".((int) $object->id));
			}
			setEventMessages($langs->trans('EleveCree', $object->ref), null, 'mesgs');
			header('Location: '.$urlcard.'?id='.$object->id);
			exit;
		}
		$db->rollback();
		$object->id = 0;
		$action = 'create';
	}

	// Modification
	if ($action == 'update' && $canedit && $object->id > 0) {
		eleves_fields_from_post($object, eleve_form_keys($object, $cansanteedit, false));
		$error = 0;
		if ($extrafields->setOptionalsFromPost(null, $object, '@GETPOSTISSET') < 0) {
			$error++;
		}
		if (!$error && $object->update($user) < 0) {
			$error++;
			setEventMessages($object->error, $object->errors, 'errors');
		}
		if (!$error) {
			if (GETPOST('photo_delete', 'alpha') && !empty($object->photo)) {
				dol_delete_dir_recursive($conf->eleves->dir_output.'/'.$object->getPhotoSubdir());
				$db->query("UPDATE ".$db->prefix()."ecole_eleve SET photo = NULL WHERE rowid = ".((int) $object->id));
			}
			$photo = eleves_upload_file('photo', $object->getPhotoSubdir(), '', true);
			if (strpos($photo, 'ERR:') === 0) {
				setEventMessages($langs->trans('Photo').' : '.substr($photo, 4), null, 'warnings');
			} elseif ($photo !== '') {
				if (!empty($object->photo) && $object->photo !== $photo) {
					dol_delete_file($conf->eleves->dir_output.'/'.$object->getPhotoSubdir().'/'.$object->photo);
				}
				$db->query("UPDATE ".$db->prefix()."ecole_eleve SET photo = '".$db->escape($photo)."' WHERE rowid = ".((int) $object->id));
			}
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			header('Location: '.$urlcard.'?id='.$object->id);
			exit;
		}
		$action = 'edit';
	}

	// Actions confirmées depuis les fenêtres de confirmation
	$done = null;
	if ($confirm === 'yes' && $object->id > 0) {
		$motif = GETPOST('motif', 'alphanohtml');
		if ($action == 'confirm_valider' && $canvalider) {
			$done = $object->valider($user, eleve_posted_date('datestatut'), $motif);
			$msg = 'InscriptionValidee';
		} elseif ($action == 'confirm_attente' && ($canvalider || $canstatut)) {
			$done = $object->changerStatut($user, EcoleEleve::STATUS_ATTENTE, eleve_posted_date('datestatut'), $motif);
			$msg = 'MisEnListeAttente';
		} elseif ($action == 'confirm_statut' && $canstatut) {
			$done = $object->changerStatut($user, GETPOSTINT('newstatus'), eleve_posted_date('datestatut'), $motif);
			$msg = 'StatutModifie';
		} elseif ($action == 'confirm_classe' && $canclasse) {
			$done = $object->changerClasse($user, GETPOSTINT('newclasse'), eleve_posted_date('dateclasse'), $motif);
			$msg = 'ClasseChangee';
		} elseif ($action == 'confirm_delete' && $candelete) {
			if ($object->delete($user) > 0) {
				setEventMessages($langs->trans('EleveSupprime', $object->ref), null, 'mesgs');
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
function eleve_bloc_start($title, $icon)
{
	print '<table class="noborder centpercent tableforfield eleve-bloc">';
	print '<tr class="liste_titre"><td colspan="2">'.img_picto('', $icon, 'class="pictofixedwidth"').dol_escape_htmltag($title).'</td></tr>';
}

/**
 * Ligne « libellé / valeur » d'un bloc.
 *
 * @param  string $label   Libellé traduit (HTML autorisé)
 * @param  string $value   Valeur (HTML)
 * @param  string $tdclass Classe CSS de la cellule du libellé
 * @return void
 */
function eleve_bloc_row($label, $value, $tdclass = 'titlefield')
{
	print '<tr><td class="'.$tdclass.'">'.$label.'</td><td>'.$value.'</td></tr>';
}

/**
 * Lignes de saisie des champs d'un bloc.
 *
 * @param  EcoleEleve $object Élève
 * @param  string[]   $keys   Champs à afficher
 * @param  string     $bloc   Bloc
 * @return void
 */
function eleve_form_rows($object, $keys, $bloc)
{
	global $langs;
	foreach ($object->fields as $k => $f) {
		if (!in_array($k, $keys, true) || (isset($f['bloc']) ? $f['bloc'] : '') !== $bloc) {
			continue;
		}
		$label = $langs->trans($f['label']);
		if (!empty($f['help'])) {
			$label = $GLOBALS['form']->textwithpicto($label, $langs->trans($f['help']));
		}
		$value = $object->$k;
		if ($k === 'date_inscription' && empty($value)) {
			$value = dol_now();
		}
		$css = (!empty($f['notnull']) && $f['notnull'] > 0) ? 'titlefieldcreate fieldrequired' : 'titlefieldcreate';
		print '<tr><td class="'.$css.'">'.$label.'</td><td>'.$object->showInputField($f, $k, $value, '', '', '', !empty($f['css']) ? $f['css'] : '').'</td></tr>';
	}
}

/**
 * Formulaire de création ou de modification (5 blocs + champs supplémentaires).
 *
 * @param  EcoleEleve  $object       Élève
 * @param  ExtraFields $extrafields  Champs supplémentaires
 * @param  bool        $create       true = création
 * @param  array       $rights       Droits utiles
 * @return void
 */
function eleve_print_form($object, $extrafields, $create, $rights)
{
	global $langs, $db, $form, $hookmanager, $action;

	$keys = eleve_form_keys($object, $rights['sante'], $create);

	// Bloc 1 : identité
	eleve_bloc_start($langs->trans('BlocIdentite'), 'fa-id-card');
	eleve_form_rows($object, $keys, 'identite');
	$photo = '<input type="file" name="photo" accept="image/*" class="flat">';
	if (!$create && !empty($object->photo)) {
		$url = eleve_photo_url($object, true);
		$photo = ($url ? '<img src="'.$url.'" style="max-height:60px;vertical-align:middle" class="marginrightonly">' : '')
			.'<label><input type="checkbox" name="photo_delete" value="1"> '.$langs->trans('SupprimerPhoto').'</label><br>'.$photo;
	}
	eleve_bloc_row($langs->trans('Photo'), $photo, 'titlefieldcreate');
	print '</table><br>';

	// Bloc 2 : scolarité
	eleve_bloc_start($langs->trans('BlocScolarite'), 'fa-school');
	if (!$create) {
		eleve_bloc_row($langs->trans('Matricule'), dol_escape_htmltag($object->ref), 'titlefieldcreate');
	} else {
		eleve_bloc_row($langs->trans('Matricule'), '<span class="opacitymedium">'.$langs->trans('MatriculeAuto', $object->getNextMatricule(dol_now())).'</span>', 'titlefieldcreate');
	}
	eleve_form_rows($object, $keys, 'scolarite');
	if (!$create && !in_array('fk_classe', $keys, true)) {
		eleve_bloc_row($langs->trans('Classe'), ecole_link_label($db, 'EcoleClasse', 'classes/class/ecole_classe.class.php', $object->fk_classe)
			.' <span class="opacitymedium small">'.$langs->trans('UtiliserChangerClasse').'</span>', 'titlefieldcreate');
	}
	print '</table><br>';

	// Bloc 5 : responsable
	eleve_bloc_start($langs->trans('BlocResponsable'), 'fa-user-friends');
	$respmode = GETPOST('resp_mode', 'aZ09') ? GETPOST('resp_mode', 'aZ09') : 'existant';
	if ($create && $rights['respcreate']) {
		print '<tr><td colspan="2">';
		print '<label class="marginrightonly"><input type="radio" name="resp_mode" value="existant"'.($respmode !== 'nouveau' ? ' checked' : '').'> '.$langs->trans('ResponsableExistant').'</label> ';
		print '<label><input type="radio" name="resp_mode" value="nouveau"'.($respmode === 'nouveau' ? ' checked' : '').'> '.$langs->trans('NouveauResponsable').'</label>';
		print '</td></tr>';
	}
	print '<tbody id="resp_existant">';
	$f = $object->fields['fk_responsable'];
	print '<tr><td class="titlefieldcreate fieldrequired">'.$form->textwithpicto($langs->trans('Responsable'), $langs->trans('ResponsableHelp')).'</td><td>'.$object->showInputField($f, 'fk_responsable', $object->fk_responsable).'</td></tr>';
	print '</tbody>';
	if ($create && $rights['respcreate']) {
		dol_include_once('/eleves/class/ecole_responsable.class.php');
		$resp = new EcoleResponsable($db);
		print '<tbody id="resp_nouveau">';
		foreach (array('nom_fr', 'nom_ar', 'lien_parente', 'telephone', 'whatsapp', 'email', 'adresse') as $k) {
			$rf = $resp->fields[$k];
			$v = GETPOST('resp_'.$k, $k === 'adresse' ? 'nohtml' : 'alphanohtml');
			print '<tr><td class="titlefieldcreate'.(!empty($rf['notnull']) ? ' fieldrequired' : '').'">'.$langs->trans($rf['label']).'</td>';
			print '<td>'.$resp->showInputField($rf, $k, $v, '', '', 'resp_', !empty($rf['css']) ? $rf['css'] : '').'</td></tr>';
		}
		print '</tbody>';
		print '<script>$(function(){function t(){var n=$("input[name=resp_mode]:checked").val()==="nouveau";$("#resp_nouveau").toggle(n);$("#resp_existant").toggle(!n);}$("input[name=resp_mode]").on("change",t);t();});</script>';
	}
	print '</table><br>';

	// Bloc 3 : santé + contact d'urgence
	if ($rights['sante']) {
		eleve_bloc_start($langs->trans('BlocSante'), 'fa-heartbeat');
		eleve_form_rows($object, $keys, 'sante');
		print '<tr class="liste_titre"><td colspan="2">'.img_picto('', 'fa-phone', 'class="pictofixedwidth"').$langs->trans('ContactUrgence').' <span class="opacitymedium small">'.$langs->trans('ContactUrgenceAide').'</span></td></tr>';
		eleve_form_rows($object, $keys, 'urgence');
		print '</table><br>';
	}

	// Champs supplémentaires définis par l'établissement
	if (!empty($extrafields->attributes[$object->table_element]['label'])) {
		eleve_bloc_start($langs->trans('InformationsComplementaires'), 'fa-list');
		$parameters = array('colspanvalue' => 1);
		if ($create) {
			print $object->showOptionals($extrafields, 'create', $parameters);
		} else {
			print $object->showOptionals($extrafields, 'edit', $parameters);
		}
		print '</table><br>';
	}

	// Bloc 4 : documents (renseignés après la création, dans l'onglet « Pièces du dossier »)
	eleve_bloc_start($langs->trans('BlocDocuments'), 'fa-folder-open');
	print '<tr><td colspan="2"><span class="opacitymedium">'.$langs->trans($create ? 'DocumentsApresCreation' : 'DocumentsDansOnglet').'</span></td></tr>';
	print '</table>';
}

$title = $langs->trans('Eleve');
if ($object->id > 0) {
	$title = $object->ref.' - '.ecole_label($object);
}
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-eleves page-card');
print '<style>input[name$="_ar"],textarea[name$="_ar"]{direction:rtl;text-align:right}table.eleve-bloc td.titlefield{width:35%}</style>';

$rights = array('sante' => $cansanteedit, 'respcreate' => $canrespcreate);

if ($action == 'create') {
	if (!$cancreate) {
		accessforbidden();
	}
	if (GETPOSTINT('fk_responsable') > 0 && empty($object->fk_responsable)) {
		$object->fk_responsable = GETPOSTINT('fk_responsable');
	}
	print load_fiche_titre($langs->trans('NouvelEleve'), '', $object->picto);
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" enctype="multipart/form-data">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	print dol_get_fiche_head(array(), '');
	eleve_print_form($object, $extrafields, true, $rights);
	print dol_get_fiche_end();
	print $form->buttonsSaveCancel('Create');
	print '</form>';
} elseif ($object->id > 0 && $action == 'edit' && $canedit) {
	print load_fiche_titre($langs->trans('ModifierEleve').' : '.dol_escape_htmltag($object->ref), '', $object->picto);
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" enctype="multipart/form-data">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
	print dol_get_fiche_head(array(), '');
	eleve_print_form($object, $extrafields, false, $rights);
	print dol_get_fiche_end();
	print $form->buttonsSaveCancel();
	print '</form>';
} elseif ($object->id > 0) {
	$page = $_SERVER['PHP_SELF'].'?id='.((int) $object->id);
	$today = dol_now();
	$motifq = array('type' => 'text', 'name' => 'motif', 'label' => $langs->trans('Motif'), 'morecss' => 'minwidth300');

	// Fenêtres de confirmation
	if ($action == 'valider' && $canvalider) {
		$check = $object->checkValidation();
		if ($check['ok']) {
			print $form->formconfirm($page, $langs->trans('ValiderInscription'), $langs->trans('ConfirmValiderInscription', ecole_label($object)), 'confirm_valider',
				array(array('type' => 'date', 'name' => 'datestatut', 'label' => $langs->trans('DateEffet'), 'value' => $today), $motifq), 'yes', 1, 0, 550);
		} elseif (!$check['place'] && count($check['raisons']) === 1) {
			// Seule la place manque : proposer la liste d'attente
			print $form->formconfirm($page, $langs->trans('ClassePleineTitre'), $check['raisons'][0].'<br><br>'.$langs->trans('ConfirmMettreEnAttente'), 'confirm_attente',
				array(array('type' => 'date', 'name' => 'datestatut', 'label' => $langs->trans('DateEffet'), 'value' => $today), $motifq), 'yes', 1, 0, 550);
		} else {
			setEventMessages($langs->trans('ValidationImpossible'), $check['raisons'], 'errors');
		}
	}
	if ($action == 'attente' && ($canvalider || $canstatut)) {
		print $form->formconfirm($page, $langs->trans('MettreEnAttente'), $langs->trans('ConfirmMettreEnAttente'), 'confirm_attente',
			array(array('type' => 'date', 'name' => 'datestatut', 'label' => $langs->trans('DateEffet'), 'value' => $today), $motifq), 'yes', 1, 0, 550);
	}
	if ($action == 'statut' && $canstatut) {
		$trans = EcoleEleve::transitions();
		$choix = array();
		foreach ($trans[(int) $object->status] as $s) {
			if ($s !== EcoleEleve::STATUS_ATTENTE || $canvalider || $canstatut) {
				$choix[$s] = $langs->trans(EcoleEleve::statusLabels()[$s][0]);
			}
		}
		print $form->formconfirm($page, $langs->trans('ChangerStatut'), $langs->trans('ConfirmChangerStatut'), 'confirm_statut',
			array(array('type' => 'select', 'name' => 'newstatus', 'label' => $langs->trans('NouveauStatut'), 'values' => $choix, 'select_show_empty' => 0, 'morecss' => 'minwidth200'),
				array('type' => 'date', 'name' => 'datestatut', 'label' => $langs->trans('DateEffet'), 'value' => $today), $motifq), 'yes', 1, 0, 550);
	}
	if ($action == 'classe' && $canclasse) {
		$choix = eleves_classes_choix($db);
		unset($choix[(int) $object->fk_classe]);
		print $form->formconfirm($page, $langs->trans('ChangerClasse'), $langs->trans('ConfirmChangerClasse'), 'confirm_classe',
			array(array('type' => 'select', 'name' => 'newclasse', 'label' => $langs->trans('NouvelleClasseEleve'), 'values' => $choix, 'morecss' => 'minwidth300'),
				array('type' => 'date', 'name' => 'dateclasse', 'label' => $langs->trans('DateChangement'), 'value' => $today), $motifq), 'yes', 1, 0, 600);
	}
	if ($action == 'delete' && $candelete) {
		print $form->formconfirm($page, $langs->trans('SupprimerEleve'), $langs->trans('ConfirmSupprimerEleve', $object->ref), 'confirm_delete', '', 0, 1);
	}

	eleve_print_banner($object, 'card');

	print '<div class="fichecenter"><div class="fichehalfleft">';
	print '<div class="underbanner clearboth"></div>';

	// Bloc 1 : identité
	eleve_bloc_start($langs->trans('BlocIdentite'), 'fa-id-card');
	foreach (array('nom_fr', 'nom_ar', 'date_naissance', 'lieu_naissance', 'sexe', 'fk_nationalite', 'rip') as $k) {
		$v = $object->showOutputField($object->fields[$k], $k, $object->$k);
		if ($k === 'nom_ar' && $v !== '') {
			$v = '<span dir="rtl">'.$v.'</span>';
		}
		if ($k === 'date_naissance' && $object->getAge() !== null) {
			$v .= ' <span class="opacitymedium">('.$langs->trans('AgeAns', $object->getAge()).')</span>';
		}
		eleve_bloc_row($langs->trans($object->fields[$k]['label']), $v);
	}
	print '</table><br>';

	// Bloc 2 : scolarité
	eleve_bloc_start($langs->trans('BlocScolarite'), 'fa-school');
	eleve_bloc_row($langs->trans('Matricule'), dol_escape_htmltag($object->ref));
	eleve_bloc_row($langs->trans('DateInscription'), dol_print_date($object->date_inscription, 'day'));
	$places = EcoleEleve::placesClasse($db, (int) $object->fk_classe);
	$classe = ecole_link_label($db, 'EcoleClasse', 'classes/class/ecole_classe.class.php', $object->fk_classe);
	$classe .= ' <span class="opacitymedium">('.($places['max'] === null ? $langs->trans('EffectifSansLimite', $places['occupees']) : $langs->trans('EffectifSurMax', $places['occupees'], $places['max'])).')</span>';
	eleve_bloc_row($langs->trans('Classe'), $classe);
	eleve_bloc_row($langs->trans('NumeroAppel'), $object->numero_appel ? '<b>'.((int) $object->numero_appel).'</b>' : '<span class="opacitymedium">—</span>');
	eleve_bloc_row($langs->trans('EcoleOrigine'), dol_escape_htmltag((string) $object->ecole_origine));
	$statut = $object->getLibStatut(5);
	if (!empty($object->date_statut)) {
		$statut .= ' <span class="opacitymedium">'.$langs->trans('DepuisLe', dol_print_date($object->date_statut, 'day')).'</span>';
	}
	eleve_bloc_row($langs->trans('StatutEleve'), $statut);
	$situation = $canpaielire ? eleve_situation_cache($db, $object) : null;
	if ($situation) {
		$ins = $situation['inscription'];
		if ($ins['du'] <= 0 && !empty($ins['exonere'])) {
			$frais = '<span class="badge badge-status4">'.$langs->trans('EtatExonere').'</span> <span class="opacitymedium">'.dol_escape_htmltag(eleves_motif_exo_label($db, $object->fk_motif_exo)).'</span>';
		} elseif ($ins['du'] <= 0) {
			$frais = '<span class="opacitymedium">'.$langs->trans('AucunFraisInscription').'</span>';
		} elseif ($ins['reste'] <= 0) {
			$frais = img_picto('', 'tick', 'class="pictofixedwidth"').$langs->trans('PayeTotalement', eleves_montant($ins['paye']));
		} elseif ($ins['paye'] > 0) {
			$frais = '<span class="badge badge-status1">'.$langs->trans('PayeSur', eleves_montant($ins['paye']), eleves_montant($ins['du'])).'</span>';
		} else {
			$frais = '<span class="badge badge-warning">'.$langs->trans('NonPayes').'</span> <span class="opacitymedium">'.eleves_montant($ins['du']).'</span>';
		}
		eleve_bloc_row($langs->trans('FraisInscription'), $frais);
	}
	if (!empty($object->date_validation) && in_array((int) $object->status, EcoleEleve::statusOccupantPlace(), true)) {
		eleve_bloc_row($langs->trans('DateValidationInscription'), dol_print_date($object->date_validation, 'dayhour').($object->fk_user_valid ? ' — '.dol_escape_htmltag(ecole_user_label($db, $object->fk_user_valid)) : ''));
	}
	eleve_bloc_row($langs->trans('Observations'), dol_nl2br(dol_escape_htmltag((string) $object->observations)));
	print '</table><br>';

	// Champs supplémentaires
	if (!empty($extrafields->attributes[$object->table_element]['label'])) {
		eleve_bloc_start($langs->trans('InformationsComplementaires'), 'fa-list');
		print $object->showOptionals($extrafields, 'view', array('colspanvalue' => 1));
		print '</table><br>';
	}

	print '</div><div class="fichehalfright">';
	print '<div class="underbanner clearboth"></div>';

	// Bloc 5 : responsable
	$resp = new EcoleResponsable($db);
	eleve_bloc_start($langs->trans('BlocResponsable'), 'fa-user-friends');
	if ($object->fk_responsable > 0 && $resp->fetch((int) $object->fk_responsable) > 0) {
		$nom = $canrespread ? $resp->getNomUrl(1, 'label') : dol_escape_htmltag(ecole_label($resp));
		if (!empty($resp->lien_parente)) {
			$nom .= ' <span class="opacitymedium">('.$langs->trans($resp->fields['lien_parente']['arrayofkeyval'][$resp->lien_parente]).')</span>';
		}
		eleve_bloc_row($langs->trans('Responsable'), $nom);
		eleve_bloc_row($langs->trans('Telephone'), dol_print_phone($resp->telephone, '', 0, 0, 'AC_TEL', '&nbsp;', 'phone'));
		if (!empty($resp->whatsapp)) {
			$wa = preg_replace('/[^0-9]/', '', $resp->whatsapp);
			eleve_bloc_row($langs->trans('WhatsApp'), '<a href="https://wa.me/'.$wa.'" target="_blank" rel="noopener">'.img_picto('', 'fa-whatsapp', 'class="pictofixedwidth"').dol_escape_htmltag($resp->whatsapp).'</a>');
		}
		if (!empty($resp->email)) {
			eleve_bloc_row($langs->trans('Email'), dol_print_email($resp->email, 0, 0, 1));
		}
		if (!empty($resp->adresse)) {
			eleve_bloc_row($langs->trans('Adresse'), dol_nl2br(dol_escape_htmltag($resp->adresse)));
		}
		$fratrie = array();
		foreach ($object->getFratrie() as $f) {
			$fratrie[] = $f->getNomUrl(1).' '.dol_escape_htmltag(ecole_label($f));
		}
		eleve_bloc_row($langs->trans('FreresSoeurs'), $fratrie ? implode('<br>', $fratrie) : '<span class="opacitymedium">'.$langs->trans('Aucun').'</span>');
	}
	print '</table><br>';

	// Bloc 3 : santé + contact d'urgence
	if ($cansanteread) {
		eleve_bloc_start($langs->trans('BlocSante'), 'fa-heartbeat');
		eleve_bloc_row($langs->trans('GroupeSanguin'), dol_escape_htmltag((string) $object->groupe_sanguin));
		eleve_bloc_row($langs->trans('Allergies'), dol_nl2br(dol_escape_htmltag((string) $object->allergies)));
		eleve_bloc_row($langs->trans('Maladies'), dol_nl2br(dol_escape_htmltag((string) $object->maladies)));
		if (!empty($object->urgence_nom) || !empty($object->urgence_telephone)) {
			$urg = dol_escape_htmltag((string) $object->urgence_nom);
			if (!empty($object->urgence_lien)) {
				$urg .= ' <span class="opacitymedium">('.dol_escape_htmltag($object->urgence_lien).')</span>';
			}
			$urg .= ' '.dol_print_phone((string) $object->urgence_telephone, '', 0, 0, 'AC_TEL', '&nbsp;', 'phone');
		} else {
			$urg = '<span class="opacitymedium">'.$langs->trans('UrgenceParDefaut').'</span> '.($resp->id ? dol_print_phone($resp->telephone, '', 0, 0, 'AC_TEL', '&nbsp;', 'phone') : '');
		}
		eleve_bloc_row($langs->trans('ContactUrgence'), $urg);
		print '</table><br>';
	}

	// Bloc 4 : documents
	if ($candocread) {
		$pieces = $object->getPieces();
		$manquantes = $object->getPiecesManquantes();
		$demandees = 0;
		foreach ($pieces as $o) {
			$demandees += ((int) $o->type_status === 1) ? 1 : 0;
		}
		eleve_bloc_start($langs->trans('BlocDocuments'), 'fa-folder-open');
		$lien = '<a href="'.dol_buildpath('/eleves/eleve/documents.php', 1).'?id='.((int) $object->id).'">'.$langs->trans('VoirPieces').'</a>';
		eleve_bloc_row($langs->trans('PiecesFournies'), ($demandees - count($manquantes)).' / '.$demandees.' &nbsp; '.$lien);
		if ($manquantes) {
			$l = array();
			foreach ($manquantes as $o) {
				$l[] = dol_escape_htmltag(ecole_label($o));
			}
			eleve_bloc_row('<span class="error">'.$langs->trans('PiecesManquantes').'</span>', '<span class="badge badge-danger">'.count($manquantes).'</span> '.implode(', ', $l));
		}
		print '</table><br>';
	}

	// Situation financière (les factures Dolibarr restent en coulisses)
	if ($situation) {
		eleve_bloc_start($langs->trans('SituationFinanciere'), 'fa-coins');
		$url = dol_buildpath('/eleves/eleve/paiements.php', 1).'?id='.((int) $object->id);
		eleve_bloc_row($langs->trans('TotalPaye'), eleves_montant($situation['total_paye']));
		if ($situation['impaye'] > 0) {
			$l = array();
			foreach ($situation['impaye_mois'] as $per) {
				$l[] = eleves_periode_label($per);
			}
			if ($situation['inscription']['reste'] > 0 && $situation['actif']) {
				array_unshift($l, $langs->trans('FraisInscription'));
			}
			eleve_bloc_row('<span class="error">'.$langs->trans('Impaye').'</span>', '<span class="badge badge-danger">'.eleves_montant($situation['impaye']).'</span> <span class="opacitymedium">'.dol_escape_htmltag(implode(', ', $l)).'</span>');
		} else {
			eleve_bloc_row($langs->trans('Impaye'), '<span class="badge badge-status4">'.$langs->trans('AJour').'</span>');
		}
		eleve_bloc_row($langs->trans('ResteAnnee'), eleves_montant($situation['reste']).' <span class="opacitymedium">('.$langs->trans('AnneeScolaire').' '.eleves_annee_label(eleves_annee_scolaire()).')</span>');
		if ($situation['dernier']) {
			eleve_bloc_row($langs->trans('DernierPaiement'), dol_print_date($situation['dernier']['date'], 'day').' — <a href="'.dol_buildpath('/eleves/recu/card.php', 1).'?id='.$situation['dernier']['id'].'">'.$langs->trans('RecuNumero', $situation['dernier']['ref']).'</a>');
		}
		eleve_bloc_row('', '<a href="'.$url.'">'.img_picto('', 'fa-list', 'class="pictofixedwidth"').$langs->trans('VoirPaiements').'</a>');
		print '</table>';
	}

	print '</div></div><div class="clearboth"></div>';
	print dol_get_fiche_end();

	// Boutons
	print '<div class="tabsAction">';
	$st = (int) $object->status;
	$avant = in_array($st, array(EcoleEleve::STATUS_PREINSCRIT, EcoleEleve::STATUS_ATTENTE), true);
	print dolGetButtonAction('', $langs->trans('Modify'), 'default', $page.'&action=edit&token='.newToken(), '', $canedit);
	if ($avant) {
		$check = $object->checkValidation();
		if ($check['ok'] || (!$check['place'] && count($check['raisons']) === 1)) {
			print dolGetButtonAction('', $langs->trans('ValiderInscription'), 'default', $page.'&action=valider&token='.newToken(), '', $canvalider);
		} else {
			print dolGetButtonAction(implode('<br>', $check['raisons']), $langs->trans('ValiderInscription'), 'default', '#', '', $canvalider ? -1 : 0);
		}
	}
	if ($st === EcoleEleve::STATUS_PREINSCRIT) {
		print dolGetButtonAction('', $langs->trans('MettreEnAttente'), 'default', $page.'&action=attente&token='.newToken(), '', $canvalider || $canstatut);
	}
	print dolGetButtonAction('', $langs->trans('ChangerStatut'), 'default', $page.'&action=statut&token='.newToken(), '', $canstatut);
	if (!in_array($st, EcoleEleve::statusSortis(), true)) {
		print dolGetButtonAction('', $langs->trans('ChangerClasse'), 'default', $page.'&action=classe&token='.newToken(), '', $canclasse);
	}
	if ($canpaielire) {
		print dolGetButtonAction('', $langs->trans('Encaisser'), 'default', dol_buildpath('/eleves/eleve/paiements.php', 1).'?id='.((int) $object->id).'&action=encaissement&token='.newToken(), '', $canencaisser);
	}
	print ecole_bouton_supprimer($langs->trans('Delete'), $page.'&action=delete&token='.newToken(), $candelete, $object->getDeleteBlockers());
	print '</div>';
}

llxFooter();
$db->close();
