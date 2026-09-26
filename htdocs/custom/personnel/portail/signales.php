<?php
/**
 * Élèves signalés dans l'espace du personnel (permission « Voir les élèves signalés ») : élèves inscrits ou
 * suspendus qui atteignent un seuil d'absences ou de retards non justifiés ; téléphone du responsable
 * (et WhatsApp si la permission « Envoi WhatsApp aux parents » est donnée). Mêmes données que la page du module Élèves.
 *
 *   signales
 *
 * Fichier : custom/personnel/portail/signales.php
 */

require_once __DIR__.'/init_espace.php';
dol_include_once('/eleves/core/lib/discipline.lib.php');

list($acces, $emp, $moi, $rubriques) = pe_session($db, 'signales');
$langs->load('eleves@eleves');
$lignes = eleves_signales($db);
$s = eleves_seuils();
$wa = personnel_whatsapp_autorise($emp);

pe_header($langs->trans('EspElevesSignales'), $acces, $emp, $rubriques, 'signales');
print '<p class="es-muted es-small">'.$langs->trans('SeuilsResume', $s['absences'] ?: '-', $s['retards'] ?: '-').'</p>';
print '<div class="es-list">';
foreach ($lignes as $l) {
	$e = $l['eleve'];
	$c = $l['compteurs'];
	print '<div class="es-card es-item"><div class="es-item-main"><b dir="auto">'.dol_escape_htmltag(ecole_label($e)).'</b>';
	print '<span class="es-muted es-small">'.dol_escape_htmltag($e->cref).' · <span dir="auto">'.dol_escape_htmltag(ecole_label((object) array('nom_fr' => $e->rnom_fr, 'nom_ar' => $e->rnom_ar))).'</span></span>';
	print '<span class="es-chips"><span class="es-chip'.($s['absences'] > 0 && $c['absences_nj'] >= $s['absences'] ? ' es-chip-red' : '').'" dir="ltr">'.dol_escape_htmltag($langs->trans('AbsencesNonJustifiees')).' : '.$c['absences_nj'].'</span>';
	print '<span class="es-chip'.($s['retards'] > 0 && $c['retards_nj'] >= $s['retards'] ? ' es-chip-orange' : '').'" dir="ltr">'.dol_escape_htmltag($langs->trans('RetardsNonJustifies')).' : '.$c['retards_nj'].'</span></span>';
	print '</div><div class="es-item-side">';
	if (!empty($e->telephone)) {
		print '<a class="es-btn es-btn-sm es-btn-light" href="tel:'.dol_escape_htmltag(preg_replace('/[^0-9+]/', '', (string) $e->telephone)).'"><i class="fas fa-phone"></i></a> ';
	}
	$num = $wa ? eleves_whatsapp_numero((string) $e->whatsapp, (string) $e->telephone) : '';
	if ($num !== '') {
		print '<a class="es-btn es-btn-sm es-btn-wa" href="https://wa.me/'.$num.'" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i></a>';
	}
	print '</div></div>';
}
print '</div>';
if (empty($lignes)) {
	print espace_msg($langs->trans(($s['absences'] <= 0 && $s['retards'] <= 0) ? 'AucunSeuilRegle' : 'AucunEleveSignale'), 'info');
}

espace_footer();
$db->close();
