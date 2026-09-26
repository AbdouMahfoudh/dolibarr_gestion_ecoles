<?php
/**
 * Documents PDF de l'espace, dans la langue de l'espace, pour un élève visible avec l'accès connecté :
 *  - certificat de scolarité ;
 *  - bulletin du trimestre ou relevé annuel (seulement après la clôture) ;
 *  - reçu de paiement (seulement un reçu valide qui concerne cet élève).
 *
 *   pdf.php?doc=certificat&id=<élève>
 *   pdf.php?doc=bulletin&id=<élève>&periode=<0..3>
 *   pdf.php?doc=recu&id=<élève>&recu=<reçu>
 *
 * Fichier : custom/espace/portail/pdf.php
 */

require 'boot.php';

$acces = espace_exiger_session($db);
$langs = espace_langs_init(espace_langue_code($db, $acces));
$eleves = espace_eleves_visibles($db, $acces->type, (int) $acces->fk_cible);
$id = espace_id_par_jeton('eleve', GETPOST('jeton', 'alphanohtml'), array_keys($eleves));
$doc = GETPOST('doc', 'aZ09');
if (!isset($eleves[$id])) {
	header('Location: '.espace_page_url(''));
	exit;
}
$e = $eleves[$id];
$erreur = '';
// Documents selon les permissions du compte (certificat, bulletins, reçus)
$permDoc = array('certificat' => 'certificat_pdf', 'bulletin' => 'bulletin_pdf', 'recu' => 'recu_pdf');
if (!isset($permDoc[$doc]) || !espace_perm($db, $acces->type, (int) $acces->fk_cible, $permDoc[$doc])) {
	$doc = '';
}

if ($doc === 'certificat' && isModEnabled('notes')) {
	dol_include_once('/notes/core/lib/bulletin_pdf.lib.php');
	notes_pdf_certificat($db, $e);
} elseif ($doc === 'bulletin' && isModEnabled('notes')) {
	dol_include_once('/notes/core/lib/bulletin_pdf.lib.php');
	$periode = GETPOSTINT('periode');
	$fk_classe = (int) $e->fk_classe;
	$resql = $db->query("SELECT rowid, ref, label_fr, label_ar FROM ".$db->prefix()."ecole_classe WHERE rowid = ".$fk_classe);
	$classe = $resql ? $db->fetch_object($resql) : null;
	if (!$classe || !in_array($periode, array(0, 1, 2, 3), true) || !notes_periode_close($db, $fk_classe, $periode)) {
		$erreur = $langs->trans($periode === 0 ? 'BilanAnnuelApresCloture' : 'MoyennesApresCloture');
	} else {
		$erreur = notes_pdf_bulletins($db, $classe, $periode, $id, 0);
	}
} elseif ($doc === 'recu') {
	dol_include_once('/eleves/class/ecole_recu.class.php');
	dol_include_once('/classes/core/lib/export.lib.php');
	dol_include_once('/eleves/core/lib/recu_pdf.lib.php');
	$recus = array();
	$resql = $db->query("SELECT DISTINCT fk_recu FROM ".$db->prefix()."ecole_paiement WHERE fk_eleve = ".((int) $id));
	while ($resql && ($r = $db->fetch_object($resql))) {
		$recus[] = (int) $r->fk_recu;
	}
	$recuid = espace_id_par_jeton('recu', GETPOST('recu', 'alphanohtml'), $recus);
	$resql = $db->query("SELECT COUNT(*) as nb FROM ".$db->prefix()."ecole_paiement WHERE fk_recu = ".$recuid." AND fk_eleve = ".$id);
	$o = $resql ? $db->fetch_object($resql) : null;
	$recu = new EcoleRecu($db);
	if (!$o || !(int) $o->nb || $recu->fetch($recuid) <= 0 || (int) $recu->status === EcoleRecu::STATUS_ANNULE) {
		$erreur = $langs->trans('DocumentIndisponible');
	} else {
		eleves_pdf_recu($db, $recu);
	}
} else {
	$erreur = $langs->trans('DocumentIndisponible');
}

if ($erreur !== '') {
	$langs = espace_langs_init(espace_langue_code($db, $acces)); // la création du PDF a pu changer la langue
	espace_header($langs->trans('Document'), $acces, espace_eleve_url($id));
	print espace_msg(dol_escape_htmltag($erreur), 'warn');
	espace_footer();
}
$db->close();
