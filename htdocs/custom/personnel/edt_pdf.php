<?php
/**
 * PDF de l'emploi du temps d'un enseignant (A4 paysage), dans la langue de l'utilisateur, avec l'en-tête
 * choisi dans la configuration du module Classes. Le contenu est exactement celui affiché à l'écran.
 *
 *   edt_pdf.php?id=<employé>   Langue forçable : &lang=fr ou &lang=ar
 *
 * Fichier : custom/personnel/edt_pdf.php
 */

require 'init.php';
dol_include_once('/classes/core/lib/ecole_pdf.lib.php');
dol_include_once('/personnel/core/lib/presence.lib.php');

if (!$user->hasRight('personnel', 'employe', 'lire')) {
	accessforbidden();
}
$object = new EcoleEmploye($db);
if (GETPOSTINT('id') <= 0 || $object->fetch(GETPOSTINT('id')) <= 0) {
	accessforbidden();
}

list($pdf, $outputlangs, $rtl) = ecole_pdf_create($langs, 'L');
$outputlangs->loadLangs(array('personnel@personnel', 'classes@classes'));
$data = personnel_edt_enseignant_data($db, (int) $object->fk_user);

$title = ecole_pdf_trans($outputlangs, 'EmploiDuTempsEnseignant').' — '.$object->ref;
$subtitle = ecole_label($object).' — '.ecole_pdf_trans($outputlangs, 'HeuresParSemaine', ecole_duree($data['minutes']));
ecole_pdf_start($pdf, $title, $subtitle, 'EDT-'.$object->ref);
if (empty($data['events'])) {
	ecole_pdf_font($pdf, 'I', 10, $rtl);
	$pdf->Cell(0, 10, ecole_pdf_trans($outputlangs, 'AucunCoursEdt'), 0, 1, 'C');
} else {
	ecole_pdf_timetable($pdf, $data['columns'], $data['events'], array('bands' => $data['bands']));
}
$pdf->Output('emploi_du_temps_'.preg_replace('/[^a-zA-Z0-9_-]/', '_', $object->ref).'.pdf', 'I');

$db->close();
