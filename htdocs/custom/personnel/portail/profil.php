<?php
/**
 * Mon dossier dans l'espace d'un employé : ses informations principales (matricule, catégories, poste, contrat,
 * contact), les matières qu'il peut enseigner et les pièces du dossier encore manquantes (noms seulement).
 * Si la direction le permet (réglage général ou fiche de l'employé), il change lui-même sa photo, son contact
 * d'urgence, son diplôme et sa spécialité ; chaque changement est gardé dans l'historique de sa fiche.
 * Pour le reste, l'employé s'adresse à l'école.
 *
 *   profil
 *
 * Fichier : custom/personnel/portail/profil.php
 */

require_once __DIR__.'/init_espace.php';

list($acces, $emp, $moi, $rubriques) = pe_session($db, 'profil');
$peutModifier = personnel_modif_profil_autorisee($emp);
$urlProfil = espace_page_url('profil');

/*
 * Actions
 */
$action = GETPOST('action', 'aZ09');
if ($action === 'savedossier' && $peutModifier) {
	$motif = $langs->transnoentities('ModifDepuisEspace');
	$erreurs = array();
	$nb = 0;

	// Contact d'urgence, diplôme, spécialité
	$sets = array();
	$changes = array();
	foreach (personnel_champs_modifiables() as $k => $max) {
		if (!GETPOSTISSET($k)) {
			continue;
		}
		$v = dol_trunc(trim(GETPOST($k, 'alphanohtml')), $max, 'right', 'UTF-8', 1);
		if ($v !== (string) $emp->$k) {
			$sets[] = $k." = ".($v !== '' ? "'".$db->escape($v)."'" : "NULL");
			$changes[$k] = array((string) $emp->$k, $v);
		}
	}
	if ($sets) {
		$db->begin();
		$ok = (bool) $db->query("UPDATE ".$db->prefix()."ecole_employe SET ".implode(', ', $sets).", fk_user_modif = ".((int) $moi->id)." WHERE rowid = ".((int) $emp->id));
		foreach ($changes as $k => $c) {
			$ok = $ok && $emp->addLog($moi, $k, $c[0], $c[1], $motif) > 0;
		}
		if ($ok) {
			$db->commit();
			$nb += count($changes);
		} else {
			$db->rollback();
			$erreurs[] = $langs->transnoentities('EspErreurEnregistrement');
		}
	}

	// Photo
	$dirPhoto = $conf->personnel->dir_output.'/'.$emp->getPhotoSubdir();
	if (GETPOST('photo_delete', 'alpha') && !empty($emp->photo)) {
		dol_delete_file($dirPhoto.'/'.$emp->photo);
		$db->query("UPDATE ".$db->prefix()."ecole_employe SET photo = NULL WHERE rowid = ".((int) $emp->id));
		$emp->addLog($moi, 'photo', (string) $emp->photo, '', $motif);
		$emp->photo = '';
		$nb++;
	}
	$photo = personnel_upload_file('photo', $emp->getPhotoSubdir(), '', true);
	if (strpos($photo, 'ERR:') === 0) {
		$erreurs[] = $langs->transnoentities('Photo').' : '.substr($photo, 4);
	} elseif ($photo !== '') {
		if (!empty($emp->photo) && $emp->photo !== $photo) {
			dol_delete_file($dirPhoto.'/'.$emp->photo);
		}
		$db->query("UPDATE ".$db->prefix()."ecole_employe SET photo = '".$db->escape($photo)."' WHERE rowid = ".((int) $emp->id));
		$emp->addLog($moi, 'photo', (string) $emp->photo, $photo, $motif);
		$nb++;
	}

	if ($erreurs) {
		pe_flash(implode(' ', $erreurs), false);
	} else {
		pe_flash($langs->transnoentities($nb > 0 ? 'EspDossierEnregistre' : 'EspAucunChangement'));
	}
	header('Location: '.$urlProfil);
	exit;
}

/*
 * Affichage
 */
pe_header($langs->trans('EspMonDossier'), $acces, $emp, $rubriques, 'profil');

$urlPhoto = !empty($emp->photo) ? espace_page_url('photo/moi', array('v' => substr(md5((string) $emp->photo), 0, 8))) : '';
print '<section class="es-card es-student">';
if ($urlPhoto !== '') {
	print '<span class="es-avatar es-avatar-lg"><img src="'.dol_escape_htmltag($urlPhoto).'" alt=""></span>';
} else {
	$ini = function_exists('mb_substr') ? mb_substr(trim(ecole_label($emp)), 0, 1, 'UTF-8') : substr(trim(ecole_label($emp)), 0, 1);
	print '<span class="es-avatar es-avatar-ini es-avatar-lg">'.dol_escape_htmltag(dol_strtoupper($ini)).'</span>';
}
print '<div class="es-student-info"><h1>'.dol_escape_htmltag(ecole_label($emp)).'</h1>';
print '<div class="es-small"><span dir="ltr">'.dol_escape_htmltag($emp->ref).'</span>'.($emp->poste ? ' · '.dol_escape_htmltag($emp->poste) : '').'</div>';
print '<div class="es-chips">';
foreach ($emp->getCategories() as $c) {
	print '<span class="es-chip es-chip-blue">'.dol_escape_htmltag(personnel_categories_texte($c)).'</span>';
}
print '<span class="es-chip">'.dol_escape_htmltag($emp->LibStatut($emp->status, 0)).'</span></div></div></section>';

print '<section class="es-card">';
$urgence = trim((string) $emp->urgence_nom.($emp->urgence_lien ? ' ('.$emp->urgence_lien.')' : '').' '.(string) $emp->urgence_telephone);
$lignes = array(
	'DateEmbauche' => $emp->date_embauche ? dol_print_date($emp->date_embauche, 'day').' ('.$emp->getAnciennete().')' : '',
	'TypeContrat' => $emp->type_contrat ? $langs->trans($emp->fields['type_contrat']['arrayofkeyval'][$emp->type_contrat]) : '',
	'DateFinContrat' => $emp->date_fin_contrat ? dol_print_date($emp->date_fin_contrat, 'day') : '',
	'Telephone' => (string) $emp->telephone,
	'WhatsApp' => (string) $emp->whatsapp,
	'Email' => (string) $emp->email,
	'ContactUrgence' => $urgence,
	'Diplome' => (string) $emp->diplome,
	'Specialite' => (string) $emp->specialite,
);
foreach ($lignes as $k => $v) {
	if ($v !== '') {
		print '<div class="es-line"><span class="es-muted">'.$langs->trans($k).'</span><span dir="auto">'.dol_escape_htmltag($v).'</span></div>';
	}
}
print '</section>';

// Modifications permises par la direction
if ($peutModifier) {
	print '<section class="es-card"><h2><i class="fas fa-pen"></i> '.$langs->trans('EspModifierDossier').'</h2>';
	print '<form method="POST" action="'.dol_escape_htmltag($urlProfil).'" class="es-form" enctype="multipart/form-data">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="savedossier">';
	print '<label for="photo">'.$langs->trans('Photo').'</label>';
	print '<input type="file" id="photo" name="photo" accept="image/*">';
	if ($urlPhoto !== '') {
		print '<label class="es-toggle"><input type="checkbox" name="photo_delete" value="1"> '.$langs->trans('SupprimerPhoto').'</label>';
	}
	$saisies = array(
		'urgence_nom' => array('UrgenceNomEmploye', 'text'),
		'urgence_telephone' => array('UrgenceTelephoneEmploye', 'tel'),
		'urgence_lien' => array('UrgenceLienEmploye', 'text'),
		'diplome' => array('Diplome', 'text'),
		'specialite' => array('Specialite', 'text'),
	);
	$max = personnel_champs_modifiables();
	foreach ($saisies as $k => $s) {
		print '<label for="'.$k.'">'.$langs->trans($s[0]).'</label>';
		print '<input type="'.$s[1].'" id="'.$k.'" name="'.$k.'" maxlength="'.((int) $max[$k]).'" value="'.dol_escape_htmltag((string) $emp->$k).'" dir="'.($s[1] === 'tel' ? 'ltr' : 'auto').'"'.($s[1] === 'tel' ? ' inputmode="tel"' : '').'>';
	}
	print '<button type="submit" class="es-btn es-btn-primary es-btn-block">'.$langs->trans('Save').'</button>';
	print '</form></section>';
}

$mats = $emp->getMatieres();
if ($mats) {
	print '<section class="es-card"><h2>'.$langs->trans('MatieresEnseignables').'</h2><div class="es-chips">';
	foreach ($mats as $m) {
		print '<span class="es-chip es-chip-blue">'.dol_escape_htmltag(ecole_label($m)).'</span>';
	}
	print '</div></section>';
}

$cls = $emp->getClassesDeclarees();
if ($cls) {
	print '<section class="es-card"><h2>'.$langs->trans('ClassesDeclarees').'</h2><div class="es-chips">';
	foreach ($cls as $c) {
		print '<span class="es-chip">'.dol_escape_htmltag($c->ref.' · '.ecole_label($c)).'</span>';
	}
	print '</div></section>';
}

$manquantes = $emp->getPiecesManquantes();
print '<section class="es-card"><h2>'.$langs->trans('PiecesDossier').'</h2>';
if ($manquantes) {
	print espace_msg($langs->trans('EspPiecesAApporter'), 'warn');
	foreach ($manquantes as $pc) {
		print '<div class="es-line"><span>'.dol_escape_htmltag(ecole_label($pc)).'</span><span class="es-chip es-chip-red">'.$langs->trans('Manquante').'</span></div>';
	}
} else {
	print '<div class="es-ok"><i class="fas fa-check-circle"></i> '.$langs->trans('DossierComplet').'</div>';
}
print '</section>';
print '<p class="es-muted es-small">'.$langs->trans($peutModifier ? 'EspAideDossierModifiable' : 'EspAideDossier').'</p>';

espace_footer();
$db->close();
