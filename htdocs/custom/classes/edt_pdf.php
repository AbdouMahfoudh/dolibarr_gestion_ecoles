<?php
/**
 * PDF d'un emploi du temps (A4 paysage), dans la langue de l'utilisateur, avec l'en-tête choisi
 * dans la configuration. Le contenu est exactement celui affiché à l'écran.
 *
 *   edt_pdf.php?type=cours&id=<classe>            emploi du temps des cours d'une classe
 *   edt_pdf.php?type=examens&id=<classe>&sid=<session>   emploi du temps des examens
 *   edt_pdf.php?type=salle&id=<salle>             occupation d'une salle
 *   Langue forçable : &lang=fr ou &lang=ar
 *
 * Fichier : custom/classes/edt_pdf.php
 */

require 'init.php';
dol_include_once('/classes/core/lib/ecole_pdf.lib.php');
dol_include_once('/classes/class/ecole_classe.class.php');
dol_include_once('/classes/class/ecole_salle.class.php');

$type = GETPOST('type', 'aZ09');
$id = GETPOSTINT('id');

if (!$user->hasRight('classes', 'lire')) {
	accessforbidden();
}

list($pdf, $outputlangs, $rtl) = ecole_pdf_create($langs, 'L');
$opts = array();

if ($type === 'salle') {
	$salle = new EcoleSalle($db);
	if ($id <= 0 || $salle->fetch($id) <= 0) {
		accessforbidden();
	}
	$data = ecole_edt_salle_data($db, $id);
	$refs = array();
	foreach ($salle->getClassesAttitrees() as $c) {
		$refs[] = $c->ref;
	}
	$title = ecole_pdf_trans($outputlangs, 'OccupationSalle').' — '.$salle->ref;
	$subtitle = ecole_label($salle).($refs ? ' — '.ecole_pdf_trans($outputlangs, 'ClasseAttitree').' : '.implode(', ', $refs) : '');
	$ref = 'SAL-'.$salle->ref;
	$opts = array('bands' => $data['bands'], 'freelabel' => $outputlangs->transnoentities('Libre'));
	$filename = 'occupation_'.$salle->ref;
} else {
	$classe = new EcoleClasse($db);
	if ($id <= 0 || $classe->fetch($id) <= 0) {
		accessforbidden();
	}
	if ($type === 'examens') {
		$r = $db->query("SELECT rowid, ref, label_fr, label_ar, date_debut, date_fin FROM ".MAIN_DB_PREFIX."ecole_session WHERE rowid = ".GETPOSTINT('sid'));
		$session = $r ? $db->fetch_object($r) : null;
		if (!$session) {
			accessforbidden();
		}
		$data = ecole_edt_examens_data($db, $id, $session);
		$title = ecole_pdf_trans($outputlangs, 'EmploiDuTempsExamens').' — '.$classe->ref;
		$subtitle = ecole_label($classe).' — '.ecole_label($session);
		if ($session->date_debut && $session->date_fin) {
			$subtitle .= ' ('.ecole_pdf_trans($outputlangs, 'DuAu', dol_print_date($db->jdate($session->date_debut), 'day', 'tzserver', $outputlangs), dol_print_date($db->jdate($session->date_fin), 'day', 'tzserver', $outputlangs)).')';
		}
		$ref = 'EXA-'.$classe->ref.'-'.$session->ref;
		$filename = 'examens_'.$classe->ref.'_'.$session->ref;
	} else {
		$data = ecole_edt_classe_data($db, $id);
		$salles = ecole_salles($db);
		$title = ecole_pdf_trans($outputlangs, 'EmploiDuTempsCours').' — '.$classe->ref;
		$subtitle = ecole_label($classe).(isset($salles[(int) $classe->fk_salle]) ? ' — '.ecole_pdf_trans($outputlangs, 'SalleAttitree').' : '.$salles[(int) $classe->fk_salle] : '');
		$ref = 'EDT-'.$classe->ref;
		$opts = array('bands' => $data['bands']);
		$filename = 'emploi_du_temps_'.$classe->ref;
	}
}

if (ecole_annee_passee()) {
	$subtitle .= ' — '.ecole_pdf_trans($outputlangs, 'AnneeScolaire').' '.ecole_annee_label(ecole_annee_vue());
}
ecole_pdf_start($pdf, $title, $subtitle, $ref);
if (empty($data['columns'])) {
	ecole_pdf_font($pdf, 'I', 10, $rtl);
	$pdf->Cell(0, 10, ecole_pdf_trans($outputlangs, 'AucuneEpreuve'), 0, 1, 'C');
} else {
	ecole_pdf_timetable($pdf, $data['columns'], $data['events'], $opts);
}
$pdf->Output(preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename).'.pdf', 'I');

$db->close();
