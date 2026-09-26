<?php
/**
 * Permissions de l'espace (personnel, parents, élèves) : une permission pour chaque chose que le compte peut
 * consulter ou faire. La direction les donne ou les retire compte par compte (onglet « Accès espace ») ;
 * sans réglage, le compte suit le modèle de sa catégorie (enseignant, surveillant... ; parent ; élève).
 * Permission retirée = la rubrique disparaît du menu (et la page ne s'ouvre plus).
 *
 * Fichier : custom/espace/core/lib/permissions.lib.php
 */

/**
 * Catalogue des permissions d'un type de compte : clé => [clé de traduction, icône, groupe].
 *
 * @param  string $type parent | eleve | employe
 * @return array<string,array{0:string,1:string,2:string}>
 */
function espace_perm_catalogue($type)
{
	if ($type === ESPACE_EMPLOYE) {
		$out = array(
			'cours_voir' => array('PermCoursVoir', 'fa-chalkboard-teacher', 'PermGroupeEnseignant'),
			'cours_declarer' => array('PermCoursDeclarer', 'fa-check-double', 'PermGroupeEnseignant'),
			'edt' => array('PermEdt', 'fa-calendar-alt', 'PermGroupeEnseignant'),
			'notes_voir' => array('PermNotesVoir', 'fa-star', 'PermGroupeEnseignant'),
			'notes_saisir' => array('PermNotesSaisir', 'fa-pen', 'PermGroupeEnseignant'),
			'classes' => array('PermClasses', 'fa-users', 'PermGroupeEnseignant'),
			'examens' => array('PermExamens', 'fa-file-signature', 'PermGroupeEnseignant'),
			'appel' => array('PermAppel', 'fa-clipboard-check', 'PermGroupeSurveillant'),
			'appel_corriger' => array('PermAppelCorriger', 'fa-edit', 'PermGroupeSurveillant'),
			'mesappels' => array('PermMesAppels', 'fa-history', 'PermGroupeSurveillant'),
			'whatsapp' => array('PermWhatsapp', 'fa-whatsapp', 'PermGroupeSurveillant'),
			'signales' => array('PermSignales', 'fa-flag', 'PermGroupeSurveillant'),
			'heures' => array('PermHeures', 'fa-coins', 'PermGroupeTous'),
			'salaires' => array('PermSalaires', 'fa-money-check-alt', 'PermGroupeTous'),
			'absences' => array('PermAbsences', 'fa-user-clock', 'PermGroupeTous'),
			'profil_modifier' => array('PermProfilModifier', 'fa-id-card', 'PermGroupeTous'),
		);
		if (!isModEnabled('notes')) {
			unset($out['notes_voir'], $out['notes_saisir']);
		}
		if (!isModEnabled('salaires')) {
			unset($out['salaires']);
		}
		return $out;
	}
	$out = array(
		'notes' => array('PermNotes', 'fa-star', 'PermGroupeRubriques'),
		'edt' => array('PermEdt', 'fa-calendar-alt', 'PermGroupeRubriques'),
		'absences' => array('PermAbsencesSanctions', 'fa-user-clock', 'PermGroupeRubriques'),
		'paiements' => array('PermPaiements', 'fa-coins', 'PermGroupeRubriques'),
		'dossier' => array('PermDossier', 'fa-folder-open', 'PermGroupeRubriques'),
		'bulletin_pdf' => array('PermBulletinPdf', 'fa-file-pdf', 'PermGroupeDocuments'),
		'recu_pdf' => array('PermRecuPdf', 'fa-receipt', 'PermGroupeDocuments'),
		'certificat_pdf' => array('PermCertificatPdf', 'fa-certificate', 'PermGroupeDocuments'),
	);
	if (!isModEnabled('notes')) {
		unset($out['notes'], $out['bulletin_pdf'], $out['certificat_pdf']);
	}
	if ($type === ESPACE_ELEVE) {
		unset($out['dossier']); // pièces du dossier : responsable seulement
	}
	return $out;
}

/**
 * Modèle par défaut d'un compte (sans réglage de la direction).
 *  - parent : tout ; élève : notes, bulletins, emploi du temps, absences (pas les paiements ni les reçus) ;
 *  - employé : permissions communes + celles de ses catégories (enseignant, surveillant), en gardant les
 *    anciens interrupteurs de sa fiche (saisie des notes, WhatsApp, modification du dossier).
 *
 * @param  string      $type  parent | eleve | employe
 * @param  object|null $cible Responsable, élève ou employé
 * @return string[]
 */
function espace_perm_modele($type, $cible = null)
{
	if ($type === ESPACE_PARENT) {
		$perms = array('notes', 'edt', 'absences', 'paiements', 'dossier', 'bulletin_pdf', 'recu_pdf', 'certificat_pdf');
	} elseif ($type === ESPACE_ELEVE) {
		$perms = array('notes', 'edt', 'absences', 'bulletin_pdf');
	} else {
		$cats = ($cible && method_exists($cible, 'getCategories')) ? $cible->getCategories() : array();
		$perms = array('heures', 'salaires', 'absences');
		if (in_array('enseignant', $cats, true)) {
			$perms = array_merge($perms, array('cours_voir', 'cours_declarer', 'edt', 'notes_voir', 'notes_saisir', 'classes', 'examens'));
		}
		if (in_array('surveillant', $cats, true)) {
			$perms = array_merge($perms, array('appel', 'appel_corriger', 'mesappels', 'examens', 'whatsapp', 'signales'));
		}
		// Anciens interrupteurs de la fiche employé (avant les permissions de l'espace)
		if ($cible && isset($cible->saisie_notes) && $cible->saisie_notes !== null && $cible->saisie_notes !== '' && (int) $cible->saisie_notes === 0) {
			$perms = array_diff($perms, array('notes_saisir'));
		}
		if ($cible && isset($cible->envoi_whatsapp) && $cible->envoi_whatsapp !== null && $cible->envoi_whatsapp !== '' && (int) $cible->envoi_whatsapp === 0) {
			$perms = array_diff($perms, array('whatsapp'));
		}
		$modif = ($cible && isset($cible->modif_profil) && $cible->modif_profil !== null && $cible->modif_profil !== '') ? ((int) $cible->modif_profil === 1) : (getDolGlobalInt('PERSONNEL_ESPACE_MODIF_PROFIL') === 1);
		if ($modif) {
			$perms[] = 'profil_modifier';
		}
	}
	return array_values(array_intersect(array_keys(espace_perm_catalogue($type)), array_unique($perms)));
}

/**
 * Réglage enregistré par la direction pour un compte : liste des permissions, ou null (= modèle).
 *
 * @param  DoliDB $db    Handler base
 * @param  string $type  Type
 * @param  int    $cible Id de la cible
 * @return string[]|null
 */
function espace_perm_regle($db, $type, $cible)
{
	static $cache = array();
	$k = $type.'-'.((int) $cible);
	if (!array_key_exists($k, $cache)) {
		$cache[$k] = null;
		$resql = $db->query("SELECT perms FROM ".$db->prefix()."ecole_espace_perm WHERE entity IN (".getEntity('ecole_acces').") AND type = '".$db->escape($type)."' AND fk_cible = ".((int) $cible));
		if ($resql && ($o = $db->fetch_object($resql))) {
			$cache[$k] = array_values(array_filter(explode(',', (string) $o->perms)));
		}
	}
	return $cache[$k];
}

/**
 * Permissions effectives d'un compte : réglage de la direction, sinon modèle de sa catégorie.
 *
 * @param  DoliDB      $db     Handler base
 * @param  string      $type   parent | eleve | employe
 * @param  int         $cible  Id de la cible
 * @param  object|null $objet  Cible chargée (employé : pour le modèle selon ses catégories)
 * @return string[]
 */
function espace_perms($db, $type, $cible, $objet = null)
{
	$r = espace_perm_regle($db, $type, $cible);
	if ($r === null) {
		if ($objet === null && $type === ESPACE_EMPLOYE) {
			$objet = espace_cible($db, $type, $cible);
		}
		return espace_perm_modele($type, $objet);
	}
	return array_values(array_intersect(array_keys(espace_perm_catalogue($type)), $r));
}

/**
 * Le compte a-t-il la permission ?
 *
 * @param  DoliDB      $db    Handler base
 * @param  string      $type  Type
 * @param  int         $cible Id de la cible
 * @param  string      $perm  Clé de la permission
 * @param  object|null $objet Cible chargée
 * @return bool
 */
function espace_perm($db, $type, $cible, $perm, $objet = null)
{
	return in_array($perm, espace_perms($db, $type, $cible, $objet), true);
}

/**
 * Enregistre les permissions d'un compte ($perms = null : revenir au modèle de sa catégorie).
 *
 * @param  DoliDB        $db    Handler base
 * @param  User          $user  Utilisateur (direction)
 * @param  string        $type  Type
 * @param  int           $cible Id de la cible
 * @param  string[]|null $perms Permissions accordées
 * @return int                  1 si OK, -1 sinon
 */
function espace_perm_enregistrer($db, $user, $type, $cible, $perms)
{
	global $conf;
	$p = $db->prefix();
	if ($perms === null) {
		$ok = $db->query("DELETE FROM ".$p."ecole_espace_perm WHERE entity = ".((int) $conf->entity)." AND type = '".$db->escape($type)."' AND fk_cible = ".((int) $cible));
		return $ok ? 1 : -1;
	}
	$perms = array_values(array_intersect(array_keys(espace_perm_catalogue($type)), (array) $perms));
	$val = "'".$db->escape(implode(',', $perms))."'";
	$sql = "INSERT INTO ".$p."ecole_espace_perm (entity, type, fk_cible, perms, fk_user_modif) VALUES (".((int) $conf->entity).", '".$db->escape($type)."', ".((int) $cible).", ".$val.", ".((int) $user->id).")";
	$sql .= " ON DUPLICATE KEY UPDATE perms = ".$val.", fk_user_modif = ".((int) $user->id);
	return $db->query($sql) ? 1 : -1;
}
