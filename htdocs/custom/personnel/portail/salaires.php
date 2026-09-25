<?php
/**
 * Mes salaires, dans l'espace d'un employé (module Salaires) :
 *  - ses bulletins de paie validés ou payés, mois par mois : mois du salaire, période, net, détail du calcul,
 *    paiement (date, mode et numéro du compte) ou « en attente de paiement », fiche de paie PDF ;
 *  - ses avances sur salaire et ses prêts en cours : montant, déjà retenu, reste, mensualités.
 * Les brouillons et les bulletins annulés ne sont jamais montrés.
 *
 *   salaires | salaires/fiche/<code du bulletin>
 *
 * Fichier : custom/personnel/portail/salaires.php
 */

require_once __DIR__.'/init_espace.php';

list($acces, $emp, $moi, $rubriques) = pe_session($db, 'salaires');
dol_include_once('/personnel/core/lib/presence.lib.php');
dol_include_once('/salaires/core/lib/salaires.lib.php');
dol_include_once('/salaires/class/ecole_salaire.class.php');
dol_include_once('/salaires/class/ecole_salaire_pret.class.php');
$langs->loadLangs(array('salaires@salaires', 'personnel@personnel'));

// Bulletins visibles : validés ou payés
$b = new EcoleSalaire($db);
$bulletins = $b->fetchAllObjects('t.mois', 'DESC', 0, 0, array(), 't.fk_employe = '.((int) $emp->id).' AND t.status IN ('.EcoleSalaire::STATUS_VALIDE.', '.EcoleSalaire::STATUS_PAYE.')');
$bulletins = is_array($bulletins) ? $bulletins : array();

// Fiche de paie PDF
if (pe_arg(0) === 'fiche') {
	$id = espace_id_par_jeton('bulletin', pe_arg(1), array_keys($bulletins));
	if ($id <= 0) {
		header('Location: '.espace_page_url('salaires'));
		exit;
	}
	dol_include_once('/salaires/core/lib/salaires_pdf.lib.php');
	salaires_pdf_output($db, array($bulletins[$id]), 'fiche_paie_'.$bulletins[$id]->ref);
	$db->close();
	exit;
}

pe_header($langs->trans('EspMesSalaires'), $acces, $emp, $rubriques, 'salaires');

print '<h2 class="es-h2"><i class="fas fa-money-check-alt"></i> '.$langs->trans('BulletinsDePaie').'</h2><div class="es-list">';
foreach ($bulletins as $bu) {
	$paye = ((int) $bu->status === EcoleSalaire::STATUS_PAYE);
	print '<details class="es-card es-item es-item-col"><summary class="es-item-top">';
	print '<span class="es-item-main"><b>'.dol_escape_htmltag($langs->transnoentities('SalaireDuMois', personnel_mois_label((string) $bu->mois))).'</b>';
	print '<span class="es-muted es-small">'.dol_escape_htmltag($langs->transnoentities('PeriodeDuAu', dol_print_date($bu->date_debut, 'day'), dol_print_date($bu->date_fin, 'day'))).'</span></span>';
	print '<span class="es-item-side"><b>'.espace_montant($bu->net).'</b>';
	if ($paye) {
		print '<span class="es-chip es-chip-green">'.dol_escape_htmltag($langs->transnoentities('EspPayeLe', dol_print_date($bu->date_paiement, 'day'))).'</span>';
	} else {
		print '<span class="es-chip es-chip-orange">'.$langs->trans('EspEnAttentePaiement').'</span>';
	}
	print '</span></summary>';
	if ($paye) {
		print '<div class="es-line"><span class="es-muted">'.$langs->trans('ModePaiement').'</span><span>'.dol_escape_htmltag(salaires_mode_numero_texte($db, (int) $bu->fk_mode, (string) $bu->numero_compte)).'</span></div>';
	}
	foreach ($bu->getLignes() as $l) {
		$det = salaires_ligne_detail($l, $langs);
		print '<div class="es-line"><span>'.dol_escape_htmltag(salaires_ligne_libelle($l, $langs)).($det !== '' ? '<br><span class="es-muted es-small">'.dol_escape_htmltag($det).'</span>' : '').'</span>';
		print '<span class="nowraponall">'.($l->type === 'retenue' ? '- ' : '').espace_montant($l->montant).'</span></div>';
	}
	print '<div class="es-line es-line-total"><b>'.$langs->trans('NetAPayer').'</b><b>'.espace_montant($bu->net).'</b></div>';
	if (trim((string) $bu->note_public) !== '') {
		print '<p class="es-muted es-small">'.dol_escape_htmltag($bu->note_public).'</p>';
	}
	print '<a class="es-btn es-btn-light es-btn-sm" href="'.dol_escape_htmltag(espace_page_url('salaires/fiche/'.espace_jeton('bulletin', (int) $bu->id))).'" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i> '.$langs->trans('FicheDePaiePdf').'</a>';
	print '</details>';
}
if (empty($bulletins)) {
	print espace_msg($langs->trans('EspAucunBulletin'), 'info');
}
print '</div>';

// Avances et prêts
foreach (array('avance', 'pret') as $type) {
	$liste = array_filter(salaires_avances_prets_employe($db, (int) $emp->id, $type), function ($a) {
		return (int) $a->status === 1;
	});
	if (empty($liste)) {
		continue;
	}
	print '<h2 class="es-h2"><i class="fas '.($type === 'pret' ? 'fa-piggy-bank' : 'fa-hand-holding-usd').'"></i> '.$langs->trans($type === 'pret' ? 'PretsPersonnel' : 'AvancesSurSalaire').'</h2><div class="es-list">';
	foreach ($liste as $a) {
		$date = dol_print_date($db->jdate($type === 'pret' ? $a->date_pret : $a->date_avance), 'day');
		print '<div class="es-card es-item"><div class="es-item-main"><b>'.dol_escape_htmltag($a->ref).' · '.dol_escape_htmltag($date).'</b>';
		print '<span class="es-muted es-small">'.$langs->trans('Montant').' : '.espace_montant($a->montant);
		if ($type === 'pret') {
			print ' — '.$langs->trans('Mensualites').' : '.((int) $a->nb_echeances).' × '.espace_montant($a->montant_echeance);
		}
		print '<br>'.dol_escape_htmltag(salaires_mode_numero_texte($db, (int) $a->fk_mode, (string) $a->numero_compte)).'</span></div>';
		print '<div class="es-item-side"><span class="es-muted es-small">'.$langs->trans('DejaRetenu').' : '.espace_montant($a->retenu).'</span>';
		print $a->reste > 0.005 ? '<b>'.$langs->trans('ResteARetenir').' : '.espace_montant($a->reste).'</b>' : '<span class="es-chip es-chip-green">'.$langs->trans($type === 'pret' ? 'Rembourse' : 'EntierementRetenu').'</span>';
		print '</div></div>';
	}
	print '</div>';
}

espace_footer();
$db->close();
