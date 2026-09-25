<?php
/**
 * Lot de salaires.
 *  - préparation : période + filtres (catégories, mode de paie) → liste des employés avec leur situation (prêt,
 *    bulletin déjà fait sur ces dates, paie non renseignée) ; les employés cochés reçoivent un bulletin calculé ;
 *  - fiche : bulletins du lot et totaux ; recalculer les brouillons, valider les brouillons, payer les bulletins
 *    validés (même mode, même date), fiches de paie de tout le lot en un PDF, exports PDF / Excel.
 *
 * Fichier : custom/salaires/lot/card.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/salaires/class/ecole_salaire.class.php');
dol_include_once('/salaires/class/ecole_salaire_lot.class.php');

$langs->loadLangs(array('salaires@salaires', 'personnel@personnel', 'classes@classes', 'bills', 'banks', 'other'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');

if (!$user->hasRight('salaires', 'bulletin', 'lire')) {
	accessforbidden();
}
$cancreer = $user->hasRight('salaires', 'bulletin', 'creer');
$canvalider = $user->hasRight('salaires', 'bulletin', 'valider');
$canpayer = $user->hasRight('salaires', 'paiement', 'payer');
$canexport = $user->hasRight('salaires', 'bulletin', 'exporter');

$form = new Form($db);
$object = new EcoleSalaireLot($db);
if ($id > 0 && $object->fetch($id) <= 0) {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}
$self = $_SERVER['PHP_SELF'].'?id='.((int) $object->id);

/**
 * Applique une action à plusieurs bulletins et affiche le bilan (réussis / erreurs).
 *
 * @param  EcoleSalaire[] $bulletins Bulletins concernés
 * @param  callable       $fn        fonction(EcoleSalaire) qui renvoie > 0 si OK
 * @param  string         $okKey     Clé du message de réussite (%s = nombre)
 * @return void
 */
function salaires_lot_appliquer($bulletins, $fn, $okKey)
{
	global $langs;
	$ok = 0;
	$errors = array();
	foreach ($bulletins as $b) {
		if (call_user_func($fn, $b) > 0) {
			$ok++;
		} else {
			$errors[] = $b->ref.' : '.$b->error;
		}
	}
	if ($ok) {
		setEventMessages($langs->trans($okKey, $ok), null, 'mesgs');
	}
	if ($errors) {
		setEventMessages(null, $errors, 'errors');
	}
	if (!$ok && !$errors) {
		setEventMessages($langs->trans('AucunBulletinConcerne'), null, 'warnings');
	}
}

/*
 * Actions
 */
if ($action == 'add' && $cancreer) {
	$d1 = personnel_date_ok(GETPOST('date_debut', 'alphanohtml'));
	$d2 = personnel_date_ok(GETPOST('date_fin', 'alphanohtml'));
	$ids = array_filter(array_map('intval', (array) GETPOST('ids', 'array')));
	$mois = personnel_mois_ok(GETPOST('mois', 'alphanohtml'));
	$mois = $mois !== '' ? $mois : EcoleSalaire::moisPropose($d2);
	$err = EcoleSalaire::controlePeriode($db, 0, $d1, $d2, 0, $mois);
	if ($err !== '' || empty($ids)) {
		setEventMessages($err !== '' ? $err : $langs->trans('ErrorAucunEmployeCoche'), null, 'errors');
		$action = 'create';
	} else {
		$db->begin();
		$object->ref = $object->getNextRef();
		$object->libelle = trim(GETPOST('libelle', 'alphanohtml'));
		if ($object->libelle === '') {
			$object->libelle = $langs->transnoentities('SalairesDuMois', personnel_mois_label($mois));
		}
		$object->mois = $mois;
		$object->date_debut = personnel_date_ts($d1);
		$object->date_fin = personnel_date_ts($d2);
		$object->status = 1;
		if ($object->create($user) <= 0) {
			$db->rollback();
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'create';
		} else {
			$db->commit();
			$ok = 0;
			$errors = array();
			foreach ($ids as $eid) {
				$b = new EcoleSalaire($db);
				if ($b->creer($user, $eid, $d1, $d2, (int) $object->id, $mois) > 0) {
					$ok++;
				} else {
					$errors[] = personnel_employe_ref($db, $eid).' : '.$b->error;
				}
			}
			setEventMessages($langs->trans('LotCree', $object->ref, $ok), null, 'mesgs');
			if ($errors) {
				setEventMessages(null, $errors, 'errors');
			}
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.((int) $object->id));
			exit;
		}
	}
}
$bulletins = array();
if ($object->id > 0) {
	$bulletins = $object->getBulletins();
}
$parStatut = function ($st) use ($bulletins) {
	return array_filter($bulletins, function ($b) use ($st) {
		return (int) $b->status === $st;
	});
};
if ($object->id > 0) {
	if ($action == 'recalculer' && $cancreer) {
		salaires_lot_appliquer($parStatut(EcoleSalaire::STATUS_BROUILLON), function ($b) use ($user) {
			return $b->calculer($user);
		}, 'NbBulletinsRecalcules');
		header('Location: '.$self);
		exit;
	}
	if ($action == 'confirm_valider' && $confirm === 'yes' && $canvalider) {
		salaires_lot_appliquer($parStatut(EcoleSalaire::STATUS_BROUILLON), function ($b) use ($user) {
			return $b->valider($user);
		}, 'NbBulletinsValides');
		header('Location: '.$self);
		exit;
	}
	if ($action == 'confirm_payer' && $confirm === 'yes' && $canpayer) {
		$datep = dol_mktime(12, 0, 0, GETPOSTINT('datepmonth'), GETPOSTINT('datepday'), GETPOSTINT('datepyear'));
		$mode = GETPOSTINT('fk_mode');
		$reference = GETPOST('reference', 'alphanohtml');
		salaires_lot_appliquer($parStatut(EcoleSalaire::STATUS_VALIDE), function ($b) use ($user, $datep, $mode, $reference) {
			return $b->payer($user, $datep, $mode, $reference);
		}, 'NbBulletinsPayes');
		header('Location: '.$self);
		exit;
	}
	if ($action == 'confirm_delete' && $confirm === 'yes' && $cancreer) {
		if ($object->delete($user) > 0) {
			header('Location: '.dol_buildpath('/salaires/lot/list.php', 1));
			exit;
		}
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

/*
 * Affichage
 */
llxHeader('', $object->id > 0 ? $object->ref : $langs->trans('PreparerSalaires'), '', '', 0, 0, '', '', '', 'mod-salaires page-lot');

// Préparation d'un lot
if ($object->id <= 0) {
	if (!$cancreer) {
		accessforbidden();
	}
	list($def1, $def2) = salaires_periode_defaut();
	$d1 = personnel_date_ok(GETPOST('date_debut', 'alphanohtml'));
	$d2 = personnel_date_ok(GETPOST('date_fin', 'alphanohtml'));
	$d1 = $d1 !== '' ? $d1 : $def1;
	$d2 = $d2 !== '' ? $d2 : $def2;
	$mois = personnel_mois_ok(GETPOST('mois', 'alphanohtml'));
	$mois = $mois !== '' ? $mois : EcoleSalaire::moisPropose($d2);
	$auj = personnel_aujourdhui();
	$cats = array_values(array_filter((array) GETPOST('categories', 'array'), function ($c) {
		return $c !== '' && $c !== '-1';
	}));
	$mode = GETPOST('mode', 'aZ09');
	$cochees = GETPOSTISSET('ids') ? array_map('intval', (array) GETPOST('ids', 'array')) : null;

	print load_fiche_titre($langs->trans('PreparerSalaires'), '', 'fa-layer-group');
	print '<div class="opacitymedium marginbottomonly">'.$langs->trans('PreparerSalairesAide').'</div>';

	// Période et filtres
	print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="action" value="create">';
	print '<table class="noborder centpercent"><tr class="liste_titre"><td colspan="2">'.$langs->trans('PeriodeEtEmployes').'</td></tr>';
	print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('Periode').'</td><td>'.$langs->trans('PeriodeDu').' <input type="date" name="date_debut" class="flat" max="'.$auj.'" value="'.dol_escape_htmltag($d1).'"> ';
	print $langs->trans('PeriodeAu').' <input type="date" name="date_fin" id="date_fin" class="flat" max="'.$auj.'" value="'.dol_escape_htmltag($d2).'"></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('MoisSalaire').'</td><td><input type="month" name="mois" id="mois" class="flat" max="'.substr($auj, 0, 7).'" value="'.dol_escape_htmltag($mois).'"> <span class="opacitymedium">'.$langs->trans('MoisSalaireAide').'</span></td></tr>';
	print '<script>$(function(){ var libre = false; $("#mois").on("change", function(){ libre = true; }); $("#date_fin").on("change", function(){ if (!libre && this.value) { $("#mois").val(this.value.substr(0, 7)); } }); });</script>';
	print '<tr class="oddeven"><td>'.$langs->trans('CategoriesEmploye').'</td><td>'.$form->multiselectarray('categories', personnel_categories_choix(), $cats, 0, 0, 'minwidth300', 0, 0, '', '', $langs->trans('Toutes')).'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('ModeDePaie').'</td><td>'.$form->selectarray('mode', array('fixe' => $langs->trans('ModeFixe'), 'heure' => $langs->trans('ModeHeure')), $mode, $langs->trans('Tous')).'</td></tr>';
	print '</table>';
	print '<div class="center margintoponly"><input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('AfficherEmployes')).'"></div>';
	print '</form><br>';

	// Employés
	$err = EcoleSalaire::controlePeriode($db, 0, $d1, $d2, 0, $mois);
	if ($err !== '') {
		print info_admin(dol_escape_htmltag($err), 0, 0, 'error');
	} else {
		$candidats = salaires_candidats_lot($db, $d1, $d2, $cats, $mode, $mois);
		print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
		print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="add">';
		print '<input type="hidden" name="date_debut" value="'.dol_escape_htmltag($d1).'"><input type="hidden" name="date_fin" value="'.dol_escape_htmltag($d2).'"><input type="hidden" name="mois" value="'.dol_escape_htmltag($mois).'">';
		print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
		print '<tr class="liste_titre"><td class="center width25"><input type="checkbox" id="salaires_tous" checked title="'.dol_escape_htmltag($langs->trans('ToutCocher')).'"></td>';
		print '<td>'.$langs->trans('Employe').'</td><td>'.$langs->trans('CategoriesEmploye').'</td><td>'.$langs->trans('ModeDePaie').'</td><td class="right">'.$langs->trans('SalaireOuPrix').'</td><td>'.$langs->trans('Situation').'</td></tr>';
		$nbPret = 0;
		foreach ($candidats as $eid => $c) {
			$e = $c['emp'];
			$pret = ($c['etat'] === 'pret');
			$nbPret += $pret ? 1 : 0;
			$checked = $pret && ($cochees === null || in_array($eid, $cochees, true));
			print '<tr class="oddeven"><td class="center">'.($pret ? '<input type="checkbox" class="salaires_coche" name="ids[]" value="'.((int) $eid).'"'.($checked ? ' checked' : '').'>' : '').'</td>';
			print '<td>'.salaires_employe_lien($db, (int) $eid).'</td><td>'.personnel_categories_badges((string) $e->categories).'</td>';
			print '<td>'.($e->mode_paie === 'fixe' ? $langs->trans('ModeFixe') : ($e->mode_paie === 'heure' ? $langs->trans('ModeHeure') : '')).'</td>';
			print '<td class="right nowraponall">'.($e->mode_paie === 'fixe' ? salaires_montant($e->salaire_base) : ($e->mode_paie === 'heure' ? salaires_montant($e->taux_horaire).' / h' : '')).'</td>';
			print '<td>';
			if ($pret) {
				print '<span class="badge badge-status4">'.$langs->trans('Pret').'</span>';
			} elseif ($c['etat'] === 'existe') {
				print '<span class="badge badge-status0">'.$langs->trans('BulletinExisteDeja', dol_escape_htmltag($c['info'])).'</span>';
			} else {
				print '<span class="badge badge-warning">'.dol_escape_htmltag($c['info']).'</span> <a href="'.dol_buildpath('/personnel/employe/card.php', 1).'?id='.((int) $eid).'&action=edit">'.$langs->trans('CompleterFiche').'</a>';
			}
			print '</td></tr>';
		}
		if (empty($candidats)) {
			print '<tr class="oddeven"><td colspan="6"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
		}
		print '</table></div>';
		print '<script>$(function(){ $("#salaires_tous").on("change", function(){ $(".salaires_coche").prop("checked", this.checked); }); });</script>';
		if ($nbPret) {
			print '<table class="border centpercent margintoponly"><tr><td class="titlefieldcreate">'.$langs->trans('LibelleLot').'</td><td><input type="text" name="libelle" class="flat minwidth300" placeholder="'.dol_escape_htmltag($langs->trans('SalairesDuMois', personnel_mois_label($mois))).'"></td></tr></table>';
			print '<div class="center margintoponly"><input type="submit" class="button button-save" value="'.dol_escape_htmltag($langs->trans('CreerBulletinsCoches')).'"></div>';
		}
		print '</form>';
	}
	llxFooter();
	$db->close();
	exit;
}

// Confirmations
if ($action == 'valider' && $canvalider) {
	print $form->formconfirm($self, $langs->trans('ValiderBrouillonsLot'), $langs->trans('ConfirmValiderBrouillonsLot', count($parStatut(EcoleSalaire::STATUS_BROUILLON))), 'confirm_valider', '', 'yes', 1);
}
if ($action == 'payer' && $canpayer) {
	$modes = salaires_modes_paiement($db);
	$defmode = getDolGlobalInt('SALAIRES_MODE_DEFAUT');
	$total = 0;
	foreach ($parStatut(EcoleSalaire::STATUS_VALIDE) as $b) {
		$total += $b->net;
	}
	print $form->formconfirm($self, $langs->trans('PayerValidesLot'), $langs->trans('ConfirmPayerValidesLot', count($parStatut(EcoleSalaire::STATUS_VALIDE)), salaires_montant_texte($total)), 'confirm_payer',
		array(
			array('type' => 'date', 'name' => 'datep', 'label' => $langs->trans('DatePaiementSalaire'), 'value' => dol_now()),
			array('type' => 'select', 'name' => 'fk_mode', 'label' => $langs->trans('ModePaiement'), 'values' => $modes, 'default' => isset($modes[$defmode]) ? $defmode : ''),
			array('type' => 'text', 'name' => 'reference', 'label' => $langs->trans('ReferencePaiement'), 'morecss' => 'minwidth200'),
			array('type' => 'onecolumn', 'value' => '<span class="opacitymedium small">'.$langs->trans('NumeroCompteLotAide').'</span>'),
		), 'yes', 1, 0, 600);
}
if ($action == 'delete' && $cancreer) {
	print $form->formconfirm($self, $langs->trans('Delete'), $langs->trans('ConfirmSupprimerLot', $object->ref), 'confirm_delete', '', 'no', 1);
}

$head = array(array($self, $langs->trans('LotSalaires'), 'card'));
print dol_get_fiche_head($head, 'card', $langs->trans('LotSalaires'), -1, $object->picto);
$linkback = '<a href="'.dol_buildpath('/salaires/lot/list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
$r = $object->resume();
$morehtmlref = '<div class="refidno">'.dol_escape_htmltag((string) $object->libelle).'<br>'.($object->mois ? '<b>'.$langs->trans('SalaireDuMois', dol_escape_htmltag(personnel_mois_label((string) $object->mois))).'</b> — ' : '').$langs->trans('PeriodeDuAu', dol_print_date($object->date_debut, 'day'), dol_print_date($object->date_fin, 'day'));
$morehtmlref .= ' — <b>'.$langs->trans('TotalNet').' : '.salaires_montant($r['net']).'</b></div>';
dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'ref', $morehtmlref, '', 0, '', '<span></span>');
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('CreePar').'</td><td>'.dol_escape_htmltag(ecole_user_label($db, $object->fk_user_creat)).' <span class="opacitymedium">'.dol_print_date($object->date_creation, 'dayhour').'</span></td></tr>';
print '<tr><td>'.$langs->trans('EtatBulletins').'</td><td>'.salaires_lot_col_etat($object).'</td></tr>';
print '</table>';
print dol_get_fiche_end();

// Actions du lot
$nbBr = count($parStatut(EcoleSalaire::STATUS_BROUILLON));
$nbVal = count($parStatut(EcoleSalaire::STATUS_VALIDE));
print '<div class="tabsAction">';
print dolGetButtonAction($langs->trans('AucunBrouillon'), $langs->trans('RecalculerBrouillons').' ('.$nbBr.')', 'default', $self.'&action=recalculer&token='.newToken(), '', $cancreer && $nbBr > 0);
print dolGetButtonAction($langs->trans('AucunBrouillon'), $langs->trans('ValiderBrouillons').' ('.$nbBr.')', 'default', $self.'&action=valider&token='.newToken(), '', $canvalider && $nbBr > 0);
print dolGetButtonAction($langs->trans('AucunBulletinValide'), $langs->trans('PayerValides').' ('.$nbVal.')', 'default', $self.'&action=payer&token='.newToken(), '', $canpayer && $nbVal > 0);
print dolGetButtonAction('', $langs->trans('FichesDePaiePdf'), 'default', dol_buildpath('/salaires/lot/pdf.php', 1).'?id='.((int) $object->id), '', $canexport && count($bulletins) > 0, array('attr' => array('target' => '_blank')));
print ecole_bouton_supprimer($langs->trans('Delete'), $self.'&action=delete&token='.newToken(), $cancreer, $object->getDeleteBlockers());
print '</div>';

// Bulletins du lot
$boutons = $canexport ? ecole_export_buttons('lot_bulletins', '&id='.((int) $object->id), 'salaires') : '';
print load_fiche_titre($langs->trans('BulletinsDuLot').' ('.count($bulletins).')', $boutons, 'fa-money-check-alt');
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('NumeroBulletin').'</td><td>'.$langs->trans('Employe').'</td><td>'.$langs->trans('ModeDePaie').'</td>';
print '<td class="right">'.$langs->trans('TotalGains').'</td><td class="right">'.$langs->trans('TotalRetenues').'</td><td class="right">'.$langs->trans('NetAPayer').'</td><td class="right">'.$langs->trans('StatutBulletin').'</td></tr>';
$tot = array(0, 0, 0);
foreach ($bulletins as $b) {
	$annule = ((int) $b->status === EcoleSalaire::STATUS_ANNULE);
	if (!$annule) {
		$tot[0] += $b->total_gains;
		$tot[1] += $b->total_retenues;
		$tot[2] += $b->net;
	}
	print '<tr class="oddeven"'.($annule ? ' style="opacity:.6"' : '').'><td class="nowraponall">'.$b->getNomUrl(1).'</td><td>'.salaires_employe_lien($db, (int) $b->fk_employe).'</td>';
	print '<td>'.($b->mode_paie === 'fixe' ? $langs->trans('ModeFixe') : ($b->mode_paie === 'heure' ? $langs->trans('ModeHeure') : '')).'</td>';
	print '<td class="right nowraponall">'.salaires_montant($b->total_gains).'</td><td class="right nowraponall">'.salaires_montant($b->total_retenues).'</td>';
	print '<td class="right nowraponall"><b>'.salaires_montant($b->net).'</b></td><td class="right">'.$b->getLibStatut(5).'</td></tr>';
}
if (empty($bulletins)) {
	print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('AucunBulletin').'</span></td></tr>';
} else {
	print '<tr class="liste_total"><td colspan="3" class="right">'.$langs->trans('Total').'</td><td class="right nowraponall">'.salaires_montant($tot[0]).'</td><td class="right nowraponall">'.salaires_montant($tot[1]).'</td>';
	print '<td class="right nowraponall">'.salaires_montant($tot[2]).'</td><td></td></tr>';
}
print '</table></div>';

llxFooter();
$db->close();
