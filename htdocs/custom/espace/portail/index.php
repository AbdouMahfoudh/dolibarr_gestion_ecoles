<?php
/**
 * Accueil de l'espace : le responsable voit une carte par enfant inscrit (classe, paiements, absences,
 * dernières notes) ; un élève est conduit directement à sa propre page.
 *
 * Fichier : custom/espace/portail/index.php
 */

require 'boot.php';
dol_include_once('/eleves/core/lib/paiements.lib.php');
dol_include_once('/eleves/core/lib/discipline.lib.php');
if (isModEnabled('notes')) {
	dol_include_once('/notes/core/lib/notes.lib.php');
}

$acces = espace_exiger_session($db);
if (espace_zone() === ESPACE_ZONE_PERSONNEL) {
	require dirname(__DIR__, 2).'/personnel/portail/accueil.php';
	exit;
}
$langs = espace_langs_init(espace_langue_code($db, $acces));
$eleves = espace_eleves_visibles($db, $acces->type, (int) $acces->fk_cible);

if ($acces->type === ESPACE_ELEVE) {
	$e = reset($eleves);
	header('Location: '.espace_eleve_url((int) $e->id));
	exit;
}

$resp = espace_cible($db, ESPACE_PARENT, (int) $acces->fk_cible);
$situations = eleves_situations($db, $eleves);

espace_header($langs->trans('TitreEspace'), $acces);

if (!empty($_SESSION['ecole_espace_msg'])) {
	print espace_msg(dol_escape_htmltag($_SESSION['ecole_espace_msg']), 'ok');
	unset($_SESSION['ecole_espace_msg']);
}

print '<h1 class="es-hello">'.$langs->trans('Bonjour').' '.dol_escape_htmltag(ecole_label($resp)).'</h1>';
print '<p class="es-muted">'.$langs->trans(count($eleves) > 1 ? 'VosEnfants' : 'VotreEnfant').'</p>';

$perms = espace_perms($db, $acces->type, (int) $acces->fk_cible);
print '<div class="es-cards">';
foreach ($eleves as $id => $e) {
	$classe = new EcoleClasse($db);
	$classe->fetch((int) $e->fk_classe);
	$s = $situations[$id];
	$c = eleves_compteurs_eleve($db, $id);

	print '<a class="es-card es-child" href="'.dol_escape_htmltag(espace_eleve_url($id)).'">';
	print '<div class="es-child-head">'.espace_avatar($e).'<div><div class="es-child-name">'.dol_escape_htmltag(ecole_label($e)).'</div>';
	print '<div class="es-muted es-small">'.dol_escape_htmltag(ecole_label($classe)).' · <span dir="ltr">'.dol_escape_htmltag($e->ref).'</span></div></div>';
	print '<i class="fas fa-chevron-'.(espace_rtl() ? 'left' : 'right').' es-chev"></i></div>';

	print '<div class="es-chips">';
	if (!in_array('paiements', $perms, true)) {
		// pas de rubrique Paiements : rien sur les paiements
	} elseif ($s['impaye'] > 0) {
		print '<span class="es-chip es-chip-red"><i class="fas fa-coins"></i> '.$langs->trans('Impaye').' : '.espace_montant($s['impaye']).'</span>';
	} elseif ($s['actif']) {
		print '<span class="es-chip es-chip-green"><i class="fas fa-check"></i> '.$langs->trans('PaiementsAJour').'</span>';
	}
	if ($c['absences_nj'] > 0 && in_array('absences', $perms, true)) {
		print '<span class="es-chip es-chip-orange"><i class="fas fa-user-clock"></i> '.$langs->trans('NbAbsencesNonJustifiees', $c['absences_nj']).'</span>';
	}
	if ($c['retards_nj'] > 0 && in_array('absences', $perms, true)) {
		print '<span class="es-chip es-chip-orange"><i class="fas fa-clock"></i> '.$langs->trans('NbRetardsNonJustifies', $c['retards_nj']).'</span>';
	}
	print '</div>';

	$notes = in_array('notes', $perms, true) ? espace_dernieres_notes($db, $e, 3) : array();
	if (!empty($notes)) {
		print '<div class="es-last"><div class="es-small es-muted">'.$langs->trans('DernieresNotes').'</div>';
		foreach ($notes as $n) {
			print '<div class="es-last-row"><span>'.dol_escape_htmltag(ecole_label($n)).' <span class="es-muted es-small">· '.dol_escape_htmltag(notes_evaluation_nom($n)).'</span></span>'.espace_note_html($n->code, $n->note_max).'</div>';
		}
		print '</div>';
	}
	print '</a>';
}
print '</div>';

espace_footer();
$db->close();
