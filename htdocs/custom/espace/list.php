<?php
/**
 * Liste des accès à l'espace parents et élèves : type, identifiant, nom, élèves visibles, état
 * (actif, jamais connecté, coupé, désactivé), mot de passe (provisoire ou personnel), dernière connexion.
 * Un filtre par colonne ; exports PDF et Excel avec les mêmes filtres.
 *
 * Fichier : custom/espace/list.php
 */

require 'init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

$langs->loadLangs(array('espace@espace', 'eleves@eleves', 'classes@classes'));
if (isModEnabled('personnel')) {
	$langs->load('personnel@personnel');
	dol_include_once('/personnel/core/lib/personnel.lib.php');
}

if (!$user->hasRight('espace', 'acces', 'lire')) {
	accessforbidden();
}
$form = new Form($db);
$action = GETPOST('action', 'aZ09');
// Nouveaux codes pour tout le personnel dont le code a expiré (passage d'année)
if ($action === 'confirm_renouvperso' && GETPOST('confirm', 'alpha') === 'yes' && $user->hasRight('espace', 'acces', 'gerer')) {
	$n = espace_renouveler_personnel($db, $user);
	setEventMessages($langs->trans('CodesPersonnelRenouveles', $n), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF'].'?search_type='.ESPACE_EMPLOYE.'&search_mdp=provisoire');
	exit;
}

if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}
$fl = espace_liste_filtres();
$f = $fl['f'];
$sortfield = GETPOST('sortfield', 'aZ09') ? GETPOST('sortfield', 'aZ09') : 'ref';
$sortorder = GETPOST('sortorder', 'aZ09') === 'DESC' ? 'DESC' : 'ASC';
$limit = GETPOSTINT('limit') > 0 ? GETPOSTINT('limit') : $conf->liste_limit;
$page = max(0, GETPOSTINT('page'));
$offset = $limit * $page;

$total = 0;
$lignes = espace_acces_liste($db, $f, $sortfield, $sortorder, $limit, $offset, $total);
$el = espace_liste_eleves($db, $lignes);

$param = $fl['param'].($limit != $conf->liste_limit ? '&limit='.$limit : '');

llxHeader('', $langs->trans('AccesEspace'), '', '', 0, 0, '', '', '', 'mod-espace page-list');
if ($action === 'renouvperso') {
	print $form->formconfirm($_SERVER['PHP_SELF'], $langs->trans('RenouvelerCodesPersonnel'), $langs->trans('ConfirmRenouvelerCodesPersonnel', ecole_annee_label(ecole_annee_active())), 'confirm_renouvperso', '', 'yes', 1);
}
// Codes du personnel expirés (nouvelle année) : un bouton pour les renouveler tous
if (in_array(ESPACE_EMPLOYE, espace_types(), true) && $user->hasRight('espace', 'acces', 'gerer')) {
	$r = $db->query("SELECT COUNT(*) as nb FROM ".$db->prefix()."ecole_acces WHERE entity IN (".getEntity('ecole_acces').") AND type = '".ESPACE_EMPLOYE."' AND annee > 0 AND annee < ".((int) ecole_annee_active()));
	$o = $r ? $db->fetch_object($r) : null;
	if ($o && (int) $o->nb > 0) {
		print '<div class="warning">'.$langs->trans('CodesExpiresInfo', ecole_annee_label(ecole_annee_active()));
		print ' <a class="butAction smallpaddingimp" href="'.$_SERVER['PHP_SELF'].'?action=renouvperso&token='.newToken().'">'.$langs->trans('RenouvelerCodesPersonnel').'</a></div>';
	}
}

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'"><input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
$boutons = ecole_export_buttons('acces', $param.'&sortfield='.urlencode($sortfield).'&sortorder='.urlencode($sortorder), 'espace');
print_barre_liste($langs->trans('AccesEspace'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, $boutons, count($lignes), $total, 'fa-house-user', 0, '', '', $limit, 0, 0, 1);

print '<div class="opacitymedium" style="margin-bottom:8px">'.img_picto('', 'fa-link', 'class="pictofixedwidth"').$langs->trans('AdresseEspace').' : <a href="'.dol_escape_htmltag(espace_url()).'" target="_blank" rel="noopener">'.dol_escape_htmltag(espace_url()).'</a>';
if (isModEnabled('personnel')) {
	print ' &nbsp;·&nbsp; '.$langs->trans('AdresseEspacePersonnel').' : <a href="'.dol_escape_htmltag(espace_url(ESPACE_EMPLOYE)).'" target="_blank" rel="noopener">'.dol_escape_htmltag(espace_url(ESPACE_EMPLOYE)).'</a>';
}
print ' &nbsp;·&nbsp; '.$langs->trans('AideListeAcces').'</div>';

print '<div class="div-table-responsive"><table class="tagtable nobottomiftotal liste">';

// Filtres (un par colonne)
$types = array(ESPACE_PARENT => $langs->trans('TypeParent'), ESPACE_ELEVE => $langs->trans('TypeEleve'));
if (in_array(ESPACE_EMPLOYE, espace_types(), true)) {
	$types[ESPACE_EMPLOYE] = $langs->trans('TypeEmploye');
}
$icones = array(ESPACE_PARENT => 'fa-user-friends', ESPACE_ELEVE => 'fa-user-graduate', ESPACE_EMPLOYE => 'fa-id-badge');
$etats = array('actif' => $langs->trans('EtatAccesActif'), 'jamais' => $langs->trans('EtatAccesJamais'), 'coupe' => $langs->trans('EtatAccesCoupe'), 'expire' => $langs->trans('EtatAccesExpire'), 'desactive' => $langs->trans('EtatAccesDesactive'));
$mdps = array('provisoire' => $langs->trans('MotDePasseProvisoire'), 'personnel' => $langs->trans('MotDePassePersonnel'));
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre">'.$form->selectarray('search_type', $types, $f['type'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100').'</td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth75" name="search_ref" value="'.dol_escape_htmltag($f['ref']).'"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth150" name="search_nom" value="'.dol_escape_htmltag($f['nom']).'"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth150" name="search_eleves" value="'.dol_escape_htmltag($f['eleves']).'"></td>';
print '<td class="liste_titre center">'.$form->selectarray('search_etat', $etats, $f['etat'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
print '<td class="liste_titre center">'.$form->selectarray('search_mdp', $mdps, $f['mdp'], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre center nowraponall"><input type="text" class="flat width25" name="search_conn_min" placeholder="min" value="'.dol_escape_htmltag($f['conn_min']).'"> <input type="text" class="flat width25" name="search_conn_max" placeholder="max" value="'.dol_escape_htmltag($f['conn_max']).'"></td>';
print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons().'</td>';
print '</tr>';

// Titres triables
print '<tr class="liste_titre">';
print_liste_field_titre('TypeAcces', $_SERVER['PHP_SELF'], 'type', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Identifiant', $_SERVER['PHP_SELF'], 'ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('NomComplet', $_SERVER['PHP_SELF'], 'nom', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('ElevesVisibles', $_SERVER['PHP_SELF'], '', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('EtatAcces', $_SERVER['PHP_SELF'], 'etat', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre('MotDePasse', $_SERVER['PHP_SELF'], 'mdp', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre('DerniereConnexion', $_SERVER['PHP_SELF'], 'conn', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre('Connexions', $_SERVER['PHP_SELF'], 'nb', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre('', $_SERVER['PHP_SELF'], '', '', $param, '', $sortfield, $sortorder, 'maxwidthsearch ');
print '</tr>';

foreach ($lignes as $l) {
	$lien = dol_buildpath('/espace/acces.php', 1).'?type='.urlencode($l->type).'&id='.((int) $l->fk_cible);
	print '<tr class="oddeven">';
	print '<td>'.img_picto('', $icones[$l->type], 'class="pictofixedwidth"').(isset($types[$l->type]) ? $types[$l->type] : dol_escape_htmltag($l->type)).'</td>';
	print '<td class="nowraponall"><a href="'.dol_escape_htmltag($lien).'">'.dol_escape_htmltag($l->ref).'</a></td>';
	print '<td>'.dol_escape_htmltag(ecole_label($l)).'</td>';
	if ($l->type === ESPACE_EMPLOYE) {
		print '<td class="small">'.(function_exists('personnel_categories_badges') ? personnel_categories_badges((string) $l->categories) : '').'</td>';
	} else {
		print '<td class="small">'.(isset($el[(int) $l->rowid]) ? dol_escape_htmltag(implode(', ', $el[(int) $l->rowid])) : '<span class="opacitymedium">—</span>').'</td>';
	}
	print '<td class="center">'.espace_etat_badge($l->etat).'</td>';
	print '<td class="center">'.((int) $l->mdp_provisoire ? '<span class="badge badge-status1">'.$langs->trans('MotDePasseProvisoire').'</span>' : '<span class="opacitymedium">'.$langs->trans('MotDePassePersonnel').'</span>').'</td>';
	print '<td class="center nowraponall">'.($l->date_derniere_connexion ? dol_print_date($db->jdate($l->date_derniere_connexion), 'dayhour', 'tzuserrel') : '<span class="opacitymedium">—</span>').'</td>';
	print '<td class="center">'.((int) $l->nb_connexions).'</td>';
	print '<td class="center"><a href="'.dol_escape_htmltag($lien).'" title="'.dol_escape_htmltag($langs->trans('GererAcces')).'">'.img_picto($langs->trans('GererAcces'), 'fa-cog').'</a></td>';
	print '</tr>';
}
if (empty($lignes)) {
	print '<tr class="oddeven"><td colspan="9"><span class="opacitymedium">'.$langs->trans('AucunAcces').'</span></td></tr>';
}
print '</table></div></form>';

llxFooter();
$db->close();
