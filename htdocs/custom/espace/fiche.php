<?php
/**
 * Fiche d'accès à remettre au parent (ou à l'élève), en PDF A5 : adresse de l'espace, identifiant,
 * mot de passe provisoire et marche à suivre. Disponible tant que le mot de passe provisoire n'a pas été changé.
 * Langue de l'utilisateur, ou forcée par &lang=fr / &lang=ar.
 *
 *   fiche.php?acces=<id>[&lang=fr|ar]
 *
 * Fichier : custom/espace/fiche.php
 */

require 'init.php';
dol_include_once('/classes/core/lib/ecole_pdf.lib.php');
dol_include_once('/classes/core/lib/export.lib.php');
dol_include_once('/eleves/core/lib/recu_pdf.lib.php');

if (!$user->hasRight('espace', 'acces', 'gerer')) {
	accessforbidden();
}
$acces = espace_acces_fetch_id($db, GETPOSTINT('acces'));
$cible = $acces ? espace_cible($db, $acces->type, (int) $acces->fk_cible) : null;
$mdp = espace_mdp_fiche($acces);
if (!$cible || $mdp === '') {
	accessforbidden($langs->trans('FicheAccesIndisponible'));
}
$eleves = espace_eleves_visibles($db, $acces->type, (int) $acces->fk_cible);

list($pdf, $outputlangs, $rtl) = ecole_pdf_create($langs, 'P', 'A5');
$outputlangs->loadLangs(array('espace@espace', 'eleves@eleves'));
$parent = ($acces->type === ESPACE_PARENT);
$employe = ($acces->type === ESPACE_EMPLOYE);
if ($employe) {
	$outputlangs->load('personnel@personnel');
}
ecole_pdf_start($pdf, ecole_pdf_trans($outputlangs, $parent ? 'FicheAccesParent' : ($employe ? 'FicheAccesEmploye' : 'FicheAccesEleve')), '', $cible->ref);
$m = $pdf->getMargins();
$w = $pdf->getPageWidth() - $m['left'] - $m['right'];
$align = $rtl ? 'R' : 'L';
$pdf->SetTextColor(40, 40, 50);

eleves_pdf_kv($pdf, ecole_pdf_trans($outputlangs, $parent ? 'Responsable' : ($employe ? 'Employe' : 'Eleve')), eleves_pdf_ltr(ecole_label($cible), $rtl), 10, 'B');
if ($employe && !empty($cible->poste)) {
	eleves_pdf_kv($pdf, ecole_pdf_trans($outputlangs, 'PosteEmploye'), eleves_pdf_ltr((string) $cible->poste, $rtl), 10);
}
if ($parent) {
	$noms = array();
	foreach ($eleves as $e) {
		$resql = $db->query("SELECT label_fr, label_ar FROM ".$db->prefix()."ecole_classe WHERE rowid = ".((int) $e->fk_classe));
		$cl = $resql ? $db->fetch_object($resql) : null;
		$noms[] = eleves_pdf_ltr(ecole_label($e), $rtl).($cl ? ' ('.eleves_pdf_ltr(ecole_pdf_text(ecole_label($cl)), $rtl).')' : '');
	}
	eleves_pdf_kv($pdf, ecole_pdf_trans($outputlangs, 'Enfants'), implode("\n", $noms), 10);
}
$pdf->Ln(4);

// Encadré : adresse, identifiant, mot de passe
$y = $pdf->GetY();
$pdf->SetDrawColor(31, 95, 139);
$pdf->SetLineWidth(0.4);
$pdf->SetFillColor(240, 245, 250);
$h = 44;
$pdf->RoundedRect($m['left'], $y, $w, $h, 3, '1111', 'DF');
$pdf->SetY($y + 4);
$lignes = array(
	array('AdresseEspace', espace_url($acces->type), 9),
	array('Identifiant', $cible->ref, 14),
	array('MotDePasseProvisoire', $mdp, 14),
);
foreach ($lignes as $l) {
	ecole_pdf_font($pdf, '', 9, $rtl);
	$pdf->SetX($m['left'] + 4);
	$pdf->Cell($w - 8, 5, ecole_pdf_trans($outputlangs, $l[0]), 0, 1, $align);
	// Adresse, identifiant et mot de passe : toujours de gauche à droite, police latine
	$pdf->setRTL(false);
	$pdf->SetFont('helvetica', 'B', $l[2]);
	$pdf->SetX($m['left'] + 4);
	$pdf->Cell($w - 8, $l[2] > 10 ? 7 : 5, $l[1], 0, 1, $rtl ? 'R' : 'L');
	$pdf->Ln(1);
}
$pdf->SetY($y + $h + 5);

// Marche à suivre
ecole_pdf_font($pdf, 'B', 10, $rtl);
$pdf->Cell($w, 6, ecole_pdf_trans($outputlangs, 'MarcheASuivre'), 0, 1, $align);
ecole_pdf_font($pdf, '', 9.5, $rtl);
for ($i = 1; $i <= 4; $i++) {
	$pdf->MultiCell($w, 5, $i.'. '.ecole_pdf_trans($outputlangs, 'EtapeFiche'.$i.($employe && $i === 4 ? 'Employe' : '')), 0, $align, false, 1, $m['left']);
}
$pdf->Ln(3);
ecole_pdf_font($pdf, '', 8.5, $rtl);
$pdf->SetTextColor(110, 110, 120);
$pdf->MultiCell($w, 4.5, ecole_pdf_trans($outputlangs, 'FicheConfidentielle'), 0, $align, false, 1, $m['left']);

$pdf->Output(ecole_export_filename('acces_'.$cible->ref, 'pdf'), 'I');
$db->close();
