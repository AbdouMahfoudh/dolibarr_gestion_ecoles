<?php
/**
 * Onglet « Pièces du dossier » d'un employé : pour chaque pièce demandée (liste configurable, selon ses
 * catégories), case « fourni », date de remise et fichier scanné joint. Les pièces manquantes sont signalées.
 *
 * Fichier : custom/personnel/employe/documents.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/personnel/class/ecole_employe.class.php');

$langs->loadLangs(array('personnel@personnel', 'classes@classes', 'other'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$typeid = GETPOSTINT('typeid');

if (!$user->hasRight('personnel', 'employe', 'lire') || !$user->hasRight('personnel', 'document', 'lire')) {
	accessforbidden();
}
$canedit = $user->hasRight('personnel', 'document', 'modifier');
$candelete = $user->hasRight('personnel', 'document', 'supprimer');

$object = new EcoleEmploye($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$form = new Form($db);
$self = $_SERVER['PHP_SELF'].'?id='.((int) $object->id);
$dir = $conf->personnel->dir_output.'/'.$object->getDocumentsSubdir();

/*
 * Actions
 */
$pieces = $object->getPieces();

// Enregistrer les pièces : case « fourni », date, fichier scanné (un fichier joint coche automatiquement « fourni »)
if ($action == 'savepieces' && $canedit) {
	$errors = array();
	foreach ($pieces as $tid => $p) {
		$file = personnel_upload_file('file_'.$tid, $object->getDocumentsSubdir(), $p->ref);
		if (strpos($file, 'ERR:') === 0) {
			$errors[] = ecole_label($p).' : '.substr($file, 4);
			continue;
		}
		$fourni = GETPOST('fourni_'.$tid, 'alpha') || $file !== '';
		$date = dol_mktime(12, 0, 0, GETPOSTINT('date_'.$tid.'month'), GETPOSTINT('date_'.$tid.'day'), GETPOSTINT('date_'.$tid.'year'));
		if ($fourni && empty($date)) {
			$date = !empty($p->date_fourni) ? $p->date_fourni : dol_now();
		}
		$avant = array(!empty($p->fourni), !empty($p->fourni) ? dol_print_date($p->date_fourni, '%Y-%m-%d') : '');
		$apres = array($fourni, $fourni ? dol_print_date($date, '%Y-%m-%d') : '');
		if ($file === '' && $avant === $apres) {
			continue; // pièce inchangée
		}
		if ($file !== '' && !empty($p->filename) && $p->filename !== $file) {
			dol_delete_file($dir.'/'.$p->filename); // remplacé par le nouveau fichier
		}
		if ($object->setPiece($user, $tid, $fourni, $date, $file !== '' ? $file : null) < 0) {
			$errors[] = $object->error;
		}
	}
	if ($errors) {
		setEventMessages($langs->trans('ErrorPiecesNonEnregistrees'), $errors, 'errors');
	} else {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
	}
	header('Location: '.$self);
	exit;
}

// Supprimer le fichier scanné d'une pièce (la case « fourni » reste telle quelle)
if ($action == 'confirm_deletefile' && $confirm === 'yes' && $candelete && isset($pieces[$typeid]) && !empty($pieces[$typeid]->filename)) {
	$p = $pieces[$typeid];
	dol_delete_file($dir.'/'.$p->filename);
	if ($object->setPiece($user, $typeid, !empty($p->fourni), $p->date_fourni, '') > 0) {
		setEventMessages($langs->trans('FileWasRemoved', $p->filename), null, 'mesgs');
	}
	header('Location: '.$self);
	exit;
}

/*
 * Affichage
 */
llxHeader('', $object->ref.' - '.$langs->trans('PiecesDossier'), '', '', 0, 0, '', '', '', 'mod-personnel page-documents');

if ($action == 'deletefile' && $candelete && isset($pieces[$typeid])) {
	print $form->formconfirm($self.'&typeid='.$typeid, $langs->trans('DeleteFile'), $langs->trans('ConfirmDeleteFile'), 'confirm_deletefile', '', 0, 1);
}

employe_print_banner($object, 'documents');
print '<div class="underbanner clearboth"></div>';

$manquantes = $object->getPiecesManquantes();
if ($manquantes) {
	$l = array();
	foreach ($manquantes as $o) {
		$l[] = dol_escape_htmltag(ecole_label($o));
	}
	print info_admin($langs->trans('PiecesManquantesNb', count($manquantes)).' : '.implode(', ', $l), 0, 0, 'warning');
} elseif ($pieces) {
	print info_admin($langs->trans('DossierComplet'), 0, 0, 'info');
}
print '<span class="opacitymedium">'.$langs->trans('PiecesNonObligatoires').'</span><br><br>';

if ($canedit) {
	print '<form method="POST" action="'.dol_escape_htmltag($self).'" enctype="multipart/form-data">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="savepieces">';
}
print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('PieceAFournir').'</td>';
print '<td class="center">'.$langs->trans('Fourni').'</td>';
print '<td class="center">'.$langs->trans('DateRemise').'</td>';
print '<td>'.$langs->trans('FichierScanne').'</td>';
if ($canedit) {
	print '<td>'.$langs->trans('JoindreFichier').'</td>';
}
print '</tr>';

foreach ($pieces as $tid => $p) {
	print '<tr class="oddeven">';
	print '<td>'.img_picto('', 'fa-file-alt', 'class="pictofixedwidth"').dol_escape_htmltag(ecole_label($p));
	if (!$p->demandee) {
		print ' <span class="opacitymedium">('.$langs->trans('PieceNonDemandee').')</span>';
	} elseif (empty($p->fourni)) {
		print ' <span class="badge badge-danger">'.$langs->trans('Manquante').'</span>';
	}
	print '</td>';
	print '<td class="center">';
	if ($canedit) {
		print '<input type="checkbox" name="fourni_'.((int) $tid).'" value="1"'.(!empty($p->fourni) ? ' checked' : '').'>';
	} else {
		print !empty($p->fourni) ? img_picto($langs->trans('Yes'), 'tick') : '';
	}
	print '</td>';
	print '<td class="center nowraponall">';
	if ($canedit) {
		print $form->selectDate(!empty($p->date_fourni) ? $p->date_fourni : -1, 'date_'.((int) $tid), 0, 0, 1, '', 1, 0);
	} else {
		print dol_print_date($p->date_fourni, 'day');
	}
	print '</td>';
	print '<td>';
	if (!empty($p->filename) && is_file($dir.'/'.$p->filename)) {
		$rel = $object->getDocumentsSubdir().'/'.$p->filename;
		print '<a href="'.DOL_URL_ROOT.'/document.php?modulepart=personnel&entity='.((int) $conf->entity).'&file='.urlencode($rel).'" target="_blank" rel="noopener">';
		print img_mime($p->filename, '', 'pictofixedwidth').dol_escape_htmltag($p->filename).'</a>';
		if ($candelete) {
			print ' <a href="'.dol_escape_htmltag($self.'&action=deletefile&typeid='.((int) $tid).'&token='.newToken()).'">'.img_delete().'</a>';
		}
	}
	print '</td>';
	if ($canedit) {
		print '<td><input type="file" name="file_'.((int) $tid).'" class="flat maxwidth200"></td>';
	}
	print '</tr>';
}
if (empty($pieces)) {
	print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">'.$langs->trans('AucunePieceConfiguree').'</span></td></tr>';
}
print '</table></div>';
if ($canedit) {
	if ($pieces) {
		print '<div class="center"><input type="submit" class="button button-save" value="'.dol_escape_htmltag($langs->trans('Save')).'"></div>';
	}
	print '</form>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
