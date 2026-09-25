<?php
/**
 * Clôture des trimestres (direction).
 *
 * Pour le trimestre choisi, chaque classe avec l'avancement des notes (compositions saisies, notes manquantes).
 * Clôturer : plus aucune modification des notes (sauf droit « corriger », avec motif), bulletins imprimables.
 * Rouvrir : avec un motif, gardé.
 *
 * Fichier : custom/notes/cloture.php
 */

require 'init.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

$langs->loadLangs(array('notes@notes', 'eleves@eleves', 'classes@classes', 'other'));

$cangerer = $user->hasRight('notes', 'cloture', 'gerer');
if (!$cangerer && !$user->hasRight('notes', 'note', 'lire')) {
	accessforbidden();
}

$form = new Form($db);
$self = $_SERVER['PHP_SELF'];
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$trimestre = GETPOSTINT('trimestre');
if (!in_array($trimestre, array(1, 2, 3), true)) {
	// Trimestre proposé : le premier qui n'est pas clôturé dans toutes les classes actives
	$r = $db->query("SELECT COUNT(*) as nb FROM ".$db->prefix()."ecole_classe WHERE status = 1");
	$nbClasses = ($r && ($o = $db->fetch_object($r))) ? (int) $o->nb : 0;
	$fermees = array(1 => 0, 2 => 0, 3 => 0);
	$r = $db->query("SELECT q.trimestre, COUNT(*) as nb FROM ".$db->prefix()."ecole_note_cloture q INNER JOIN ".$db->prefix()."ecole_classe c ON c.rowid = q.fk_classe AND c.status = 1 WHERE q.status = 1 GROUP BY q.trimestre");
	while ($r && ($o = $db->fetch_object($r))) {
		$fermees[(int) $o->trimestre] = (int) $o->nb;
	}
	$trimestre = 3;
	for ($t = 3; $t >= 1; $t--) {
		$trimestre = ($fermees[$t] < $nbClasses) ? $t : $trimestre;
	}
}
$fk_classe = GETPOSTINT('fk_classe');
$url = $self.'?trimestre='.$trimestre;

$classes = notes_classes($db, $user);

/*
 * Actions
 */
if ($cangerer && $fk_classe > 0 && isset($classes[$fk_classe])) {
	if ($action === 'confirm_cloturer' && $confirm === 'yes') {
		if (notes_cloturer($db, $user, $fk_classe, $trimestre) > 0) {
			setEventMessages($langs->trans('TrimestreClotureOk', notes_trimestre_label($trimestre), $classes[$fk_classe]->ref), null, 'mesgs');
			header('Location: '.$url);
			exit;
		}
		setEventMessages($db->lasterror(), null, 'errors');
	}
	if ($action === 'rouvrir') {
		$error = '';
		if (notes_rouvrir($db, $user, $fk_classe, $trimestre, GETPOST('motif', 'alphanohtml'), $error) > 0) {
			setEventMessages($langs->trans('TrimestreRouvertOk', notes_trimestre_label($trimestre), $classes[$fk_classe]->ref), null, 'mesgs');
			header('Location: '.$url);
			exit;
		}
		setEventMessages($error, null, 'errors');
		$action = 'askrouvrir';
	}
}

/*
 * Affichage
 */
llxHeader('', $langs->trans('ClotureTrimestres'), '', '', 0, 0, '', '', '', 'mod-notes page-cloture');
print load_fiche_titre($langs->trans('ClotureTrimestres'), ecole_export_buttons('cloture', '&trimestre='.$trimestre, 'notes'), 'fa-lock');

print '<style>.notes-trim{display:inline-block;padding:5px 12px;margin-right:4px;border:1px solid #bbb;border-radius:4px;text-decoration:none;background:#fff}.notes-trim.on{background:#e8eefb;border-color:#6a84c4;font-weight:bold}</style>';
print '<div class="marginbottomonly">';
for ($t = 1; $t <= 3; $t++) {
	print '<a class="notes-trim'.($t === $trimestre ? ' on' : '').'" href="'.dol_escape_htmltag($self.'?trimestre='.$t).'">'.notes_trimestre_label($t).'</a>';
}
print '</div>';

if ($action === 'cloturer' && $cangerer && isset($classes[$fk_classe])) {
	print $form->formconfirm($url.'&fk_classe='.$fk_classe, $langs->trans('CloturerTrimestre'), $langs->trans('ConfirmCloturer', notes_trimestre_label($trimestre), $classes[$fk_classe]->ref), 'confirm_cloturer', '', 0, 1);
}

// Avancement : compositions et notes manquantes par classe
$p = $db->prefix();
$av = array();
$sql = "SELECT v.fk_classe, v.rowid, v.type, (SELECT COUNT(*) FROM ".$p."ecole_note n WHERE n.fk_evaluation = v.rowid) as nb";
$sql .= " FROM ".$p."ecole_evaluation v WHERE v.status = 1 AND v.trimestre = ".$trimestre;
$resql = $db->query($sql);
while ($resql && ($o = $db->fetch_object($resql))) {
	$c = (int) $o->fk_classe;
	if (!isset($av[$c])) {
		$av[$c] = array('devoirs' => 0, 'compos' => 0, 'notes_compo' => 0);
	}
	if ((int) $o->type === NOTES_COMPO) {
		$av[$c]['compos']++;
		$av[$c]['notes_compo'] += (int) $o->nb;
	} else {
		$av[$c]['devoirs']++;
	}
}
$nbEleves = array();
$resql = $db->query("SELECT fk_classe, COUNT(*) as nb FROM ".$p."ecole_eleve WHERE status IN (".implode(',', EcoleEleve::statusOccupantPlace()).") GROUP BY fk_classe");
while ($resql && ($o = $db->fetch_object($resql))) {
	$nbEleves[(int) $o->fk_classe] = (int) $o->nb;
}
$nbMatieres = array();
$resql = $db->query("SELECT fk_classe, COUNT(*) as nb FROM ".$p."ecole_classe_matiere GROUP BY fk_classe");
while ($resql && ($o = $db->fetch_object($resql))) {
	$nbMatieres[(int) $o->fk_classe] = (int) $o->nb;
}
$clotures = notes_clotures($db);

print '<div class="div-table-responsive-no-min"><table class="noborder centpercent notes-filtrable">';
print notes_filtres_ligne(array('text', 'range', 'range', 'range', 'range', array('ouvert' => $langs->trans('Ouvert'), 'cloture' => $langs->trans('Cloture'), 'rouvert' => $langs->trans('Rouvert')), null));
print '<tr class="liste_titre"><td>'.$langs->trans('Classe').'</td><td class="center">'.$langs->trans('NbElevesCol').'</td><td class="center">'.$langs->trans('Devoirs').'</td>';
print '<td class="center">'.$langs->trans('CompositionsSaisies').'</td><td class="center">'.$langs->trans('NotesCompoManquantes').'</td><td>'.$langs->trans('Etat').'</td><td></td></tr>';
foreach ($classes as $cid => $c) {
	$a = isset($av[$cid]) ? $av[$cid] : array('devoirs' => 0, 'compos' => 0, 'notes_compo' => 0);
	$nbE = isset($nbEleves[$cid]) ? $nbEleves[$cid] : 0;
	$nbM = isset($nbMatieres[$cid]) ? $nbMatieres[$cid] : 0;
	$manquantes = max(0, $nbE * $nbM - $a['notes_compo']);
	$cl = isset($clotures[$cid.'|'.$trimestre]) ? $clotures[$cid.'|'.$trimestre] : null;
	$closed = $cl && (int) $cl->status === 1;

	print '<tr class="oddeven"><td class="nowraponall"><a href="'.dol_escape_htmltag(dol_buildpath('/notes/saisie.php', 1).'?fk_classe='.$cid.'&trimestre='.$trimestre).'">'.dol_escape_htmltag($c->ref.' - '.ecole_label($c)).'</a></td>';
	print '<td class="center">'.$nbE.'</td><td class="center">'.$a['devoirs'].'</td>';
	print '<td class="center" data-f="'.$a['compos'].'"><span class="badge '.($nbM > 0 && $a['compos'] >= $nbM ? 'badge-status4' : 'badge-status1').'">'.$a['compos'].' / '.$nbM.'</span></td>';
	print '<td class="center" data-f="'.$manquantes.'">'.($manquantes > 0 ? '<span class="badge badge-danger">'.$manquantes.'</span>' : '<span class="opacitymedium">0</span>').'</td>';
	print '<td data-f="'.($closed ? 'cloture' : 'ouvert'.($cl && $cl->date_reouverture ? ' rouvert' : '')).'">';
	if ($closed) {
		print '<span class="badge badge-status6">'.img_picto('', 'fa-lock', 'class="pictofixedwidth"').$langs->trans('Cloture').'</span> <span class="opacitymedium small">'.$langs->trans('ParLe', ecole_user_label($db, $cl->fk_user_cloture), dol_print_date($db->jdate($cl->date_cloture), 'dayhour', 'tzuserrel')).'</span>';
	} else {
		print '<span class="badge badge-status0">'.$langs->trans('Ouvert').'</span>';
		if ($cl && $cl->date_reouverture) {
			print ' <span class="opacitymedium small" title="'.dol_escape_htmltag((string) $cl->motif_reouverture).'">'.$langs->trans('RouvertPar', ecole_user_label($db, $cl->fk_user_reouverture), dol_print_date($db->jdate($cl->date_reouverture), 'dayhour', 'tzuserrel')).'</span>';
		}
	}
	print '</td><td class="right nowraponall">';
	if ($cangerer) {
		if ($closed && $action === 'askrouvrir' && $fk_classe === $cid) {
			print '<form method="POST" action="'.dol_escape_htmltag($self).'" class="inline-block">';
			print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="rouvrir">';
			print '<input type="hidden" name="trimestre" value="'.$trimestre.'"><input type="hidden" name="fk_classe" value="'.$cid.'">';
			print '<input type="text" name="motif" class="flat minwidth200" maxlength="250" required autofocus placeholder="'.dol_escape_htmltag($langs->trans('MotifReouverture')).'"> ';
			print '<input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Rouvrir')).'"> <a href="'.dol_escape_htmltag($url).'">'.$langs->trans('Cancel').'</a></form>';
		} elseif ($closed) {
			print '<a class="button smallpaddingimp" href="'.dol_escape_htmltag($url.'&fk_classe='.$cid.'&action=askrouvrir').'">'.img_picto('', 'fa-lock-open', 'class="pictofixedwidth"').$langs->trans('Rouvrir').'</a>';
		} else {
			print '<a class="button smallpaddingimp" href="'.dol_escape_htmltag($url.'&fk_classe='.$cid.'&action=cloturer&token='.newToken()).'">'.img_picto('', 'fa-lock', 'class="pictofixedwidth"').$langs->trans('Cloturer').'</a>';
		}
	}
	print '</td></tr>';
}
if (empty($classes)) {
	print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('AucuneClasse').'</span></td></tr>';
}
print '</table></div>';
print '<span class="opacitymedium small">'.$langs->trans('AideCloture').'</span>';

llxFooter();
$db->close();
