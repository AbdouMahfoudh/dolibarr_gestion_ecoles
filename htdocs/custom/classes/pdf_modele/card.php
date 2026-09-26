<?php
/**
 * Fiche : modèle de PDF (listes, reçus, bulletins de paie), avec duplication.
 * Fichier : custom/classes/pdf_modele/card.php
 */

require '../init.php';
dol_include_once('/classes/class/ecole_pdf_modele.class.php');

// Dupliquer un modèle : copie modifiable (jamais par défaut), puis ouverture de la copie
if (GETPOST('action', 'aZ09') === 'dupliquer' && GETPOSTINT('id') > 0 && $user->hasRight('classes', 'config')) {
	$src = new EcolePdfModele($db);
	if ($src->fetch(GETPOSTINT('id')) > 0) {
		$copie = new EcolePdfModele($db);
		foreach (array_keys($src->fields) as $k) {
			$copie->$k = $src->$k;
		}
		$base = substr((string) $src->ref, 0, 26);
		$n = 2;
		do {
			$copie->ref = $base.'_'.$n++;
			$r = $db->query("SELECT rowid FROM ".$db->prefix()."ecole_pdf_modele WHERE ref = '".$db->escape($copie->ref)."' AND entity IN (".getEntity('ecole_pdf_modele').")");
		} while ($r && $db->num_rows($r) > 0 && $n < 100);
		$copie->label_fr = $langs->transnoentities('CopieDe', $src->label_fr);
		$copie->par_defaut = 0;
		if ($copie->create($user) > 0) {
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.((int) $copie->id).'&action=edit&token='.newToken());
			exit;
		}
		setEventMessages($copie->error, $copie->errors, 'errors');
	}
}

ecole_crud_card(new EcolePdfModele($db), ecole_crud_config('pdf_modele'));
