<?php
/**
 * Configuration « Règles de calcul » : pour chaque niveau, calcul de la note de devoirs, poids devoirs /
 * composition, absences, poids des trimestres, rang des ex aequo, décision de fin d'année.
 * En dessous : exceptions pour une matière d'une classe (calcul de la note de devoirs).
 *
 * Fichier : custom/notes/admin/regles.php
 */

require '../init.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

$langs->loadLangs(array('admin', 'notes@notes', 'classes@classes'));

if (!$user->hasRight('notes', 'config', 'gerer') && !$user->admin) {
	accessforbidden();
}
$form = new Form($db);
$self = $_SERVER['PHP_SELF'];
$action = GETPOST('action', 'aZ09');

// Niveaux
$niveaux = array();
$resql = $db->query("SELECT rowid, ref, label_fr, label_ar FROM ".$db->prefix()."ecole_niveau WHERE entity IN (".getEntity('ecole_niveau').") AND status = 1 ORDER BY position, rowid");
while ($resql && ($o = $db->fetch_object($resql))) {
	$niveaux[(int) $o->rowid] = $o;
}
$fk_niveau = GETPOSTINT('fk_niveau');
if (!isset($niveaux[$fk_niveau])) {
	$fk_niveau = (int) key($niveaux);
}
$url = $self.'?fk_niveau='.$fk_niveau;
$choix = notes_regle_choix();

/*
 * Actions
 */
if ($action === 'save' && $fk_niveau > 0) {
	$data = array();
	foreach (array_keys(notes_regle_defaut()) as $k) {
		$data[$k] = GETPOST($k, 'alphanohtml');
	}
	$data['fk_modele'] = GETPOSTINT('fk_modele');
	$error = '';
	$tous = GETPOSTINT('tous_niveaux');
	$cibles = $tous ? array_keys($niveaux) : array($fk_niveau);
	$ok = true;
	foreach ($cibles as $nid) {
		if (notes_regle_enregistrer($db, $user, $nid, $data, $error) < 0) {
			$ok = false;
			break;
		}
	}
	if ($ok) {
		setEventMessages($langs->trans($tous ? 'ReglesEnregistreesTous' : 'SetupSaved'), null, 'mesgs');
		header('Location: '.$url);
		exit;
	}
	setEventMessages($error, null, 'errors');
}

if ($action === 'saveexceptions' && $fk_niveau > 0) {
	$ex = GETPOST('ex', 'array');
	$error = 0;
	foreach ($ex as $key => $v) {
		$p = explode('_', (string) $key);
		if (count($p) === 2 && notes_exception_enregistrer($db, $user, (int) $p[0], (int) $p[1], (string) $v) < 0) {
			$error++;
		}
	}
	if (!$error) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		header('Location: '.$url.'#exceptions');
		exit;
	}
	setEventMessages($db->lasterror(), null, 'errors');
}

/*
 * Affichage
 */
llxHeader('', $langs->trans('MenuReglesCalcul'), '', '', 0, 0, '', '', '', 'mod-notes page-admin');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('ConfigurationNotes'), $linkback, 'title_setup');
print dol_get_fiche_head(notes_admin_prepare_head(), 'regles', '', -1, '');

if (empty($niveaux)) {
	print '<div class="warning">'.$langs->trans('AucunNiveau').'</div>';
	print dol_get_fiche_end();
	llxFooter();
	$db->close();
	exit;
}

print '<style>.notes-trim{display:inline-block;padding:5px 12px;margin:0 4px 4px 0;border:1px solid #bbb;border-radius:4px;text-decoration:none;background:#fff}.notes-trim.on{background:#e8eefb;border-color:#6a84c4;font-weight:bold}.notes-formule{background:#f4f6fa;border-radius:4px;padding:4px 8px;display:inline-block}</style>';
print '<div class="opacitymedium marginbottomonly">'.$langs->trans('AideReglesNiveau').'</div>';
print '<div class="marginbottomonly">';
foreach ($niveaux as $nid => $n) {
	$r = notes_regle($db, $nid);
	print '<a class="notes-trim'.($nid === $fk_niveau ? ' on' : '').'" href="'.dol_escape_htmltag($self.'?fk_niveau='.$nid).'">'.dol_escape_htmltag(ecole_label($n)).($r['enregistre'] ? '' : ' <span class="opacitymedium small">('.$langs->trans('ParDefaut').')</span>').'</a>';
}
print '</div>';

$r = notes_regle($db, $fk_niveau);
if ($action === 'save') {
	foreach (array_keys(notes_regle_defaut()) as $k) {
		$r[$k] = GETPOST($k, 'alphanohtml'); // saisie refusée : on réaffiche ce qui a été tapé
	}
}
$sel = function ($k) use ($form, $choix, $r, $langs) {
	$opts = array();
	foreach ($choix[$k] as $v => $l) {
		$opts[$v] = $langs->trans($l);
	}
	return $form->selectarray($k, $opts, $r[$k], 0, 0, 0, '', 0, 0, 0, '', 'minwidth300');
};
$num = function ($k, $step = '0.5') use ($r) {
	return '<input type="number" name="'.$k.'" min="0" max="100" step="'.$step.'" class="flat maxwidth75 right" value="'.dol_escape_htmltag(notes_code($r[$k], '')).'">';
};

print '<form method="POST" action="'.dol_escape_htmltag($self).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<input type="hidden" name="fk_niveau" value="'.$fk_niveau.'">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('NoteDevoirs').'</td></tr>';
print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('CalculDevoirs').'</td><td>'.$sel('calcul_devoirs').' <span class="opacitymedium">'.$langs->trans('CalculDevoirsAide').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DevoirAbsent').'</td><td>'.$sel('devoir_absent').'</td></tr>';

print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('NoteTrimestre').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PoidsDevoirsCompo').'</td><td>'.$langs->trans('Devoirs').' × '.$num('poids_devoirs').' &nbsp; + &nbsp; '.$langs->trans('Composition').' × '.$num('poids_compo');
print ' &nbsp; <span class="notes-formule" id="formule"></span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('CompoAbsent').'</td><td>'.$sel('compo_absent').'</td></tr>';
print '<tr class="oddeven"><td class="tdtop"></td><td><span class="opacitymedium">'.$langs->trans('AideDispense').'</span></td></tr>';

print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('MoyenneAnnuelle').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('PoidsTrimestres').'</td><td>'.$langs->trans('Trimestre1').' × '.$num('poids_t1').' &nbsp; '.$langs->trans('Trimestre2').' × '.$num('poids_t2').' &nbsp; '.$langs->trans('Trimestre3').' × '.$num('poids_t3').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('TrimestreSansCompo').'</td><td>'.$sel('trimestre_sans_compo').'</td></tr>';

print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('RangEtDecision').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('RangExaequo').'</td><td>'.$sel('rang_exaequo').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DecisionFinAnnee').'</td><td>'.$sel('decision').' &nbsp; <span id="seuil">'.$langs->trans('SeuilPassage').' '.$num('seuil_passage', '0.25').' / 20</span></td></tr>';

print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('BulletinDeNotes').'</td></tr>';
dol_include_once('/notes/class/ecole_bulletin_modele.class.php');
$modeles = EcoleBulletinModele::choix($db);
print '<tr class="oddeven"><td>'.$langs->trans('ModeleParDefaut').'</td><td>'.$form->selectarray('fk_modele', $modeles, (int) $r['fk_modele'], $langs->trans('PremierModele'), 0, 0, '', 0, 0, 0, '', 'minwidth300');
print ' <a href="'.dol_buildpath('/notes/modele/list.php', 1).'">'.$langs->trans('MenuModelesBulletin').'</a> <span class="opacitymedium">'.$langs->trans('ModeleParDefautAide').'</span></td></tr>';
print '</table>';

print '<div class="center"><br><label><input type="checkbox" name="tous_niveaux" value="1"> '.$langs->trans('AppliquerTousNiveaux').'</label><br><br>';
print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
print '</form>';

$libDevoirs = dol_escape_js($langs->transnoentities('Devoirs'), 2);
$libCompo = dol_escape_js($langs->transnoentities('Composition'), 2);
print '<script>
(function () {
	var d = document.querySelector("input[name=poids_devoirs]"), c = document.querySelector("input[name=poids_compo]"), f = document.getElementById("formule");
	var dec = document.querySelector("select[name=decision]"), s = document.getElementById("seuil");
	function maj() {
		var a = parseFloat(d.value) || 0, b = parseFloat(c.value) || 0;
		f.textContent = "= (" + "'.$libDevoirs.'" + " × " + a + " + " + "'.$libCompo.'" + " × " + b + ") ÷ " + (a + b);
		s.style.display = dec.value === "auto" ? "" : "none";
	}
	d.addEventListener("input", maj); c.addEventListener("input", maj); dec.addEventListener("change", maj);
	maj();
})();
</script>';

// Exceptions : calcul de la note de devoirs pour une matière d'une classe du niveau
print '<br><a id="exceptions"></a>';
print load_fiche_titre($langs->trans('ExceptionsMatieres', ecole_label($niveaux[$fk_niveau])), '', 'fa-book');
$p = $db->prefix();
$sql = "SELECT c.rowid as fk_classe, c.ref as cref, m.rowid as fk_matiere, m.label_fr, m.label_ar, x.calcul_devoirs";
$sql .= " FROM ".$p."ecole_classe c INNER JOIN ".$p."ecole_classe_matiere cm ON cm.fk_classe = c.rowid INNER JOIN ".$p."ecole_matiere m ON m.rowid = cm.fk_matiere";
$sql .= " LEFT JOIN ".$p."ecole_note_exception x ON x.fk_classe = c.rowid AND x.fk_matiere = m.rowid";
$sql .= " WHERE c.fk_niveau = ".$fk_niveau." AND c.status = 1 ORDER BY c.rowid, m.label_fr";
$lignes = array();
$resql = $db->query($sql);
while ($resql && ($o = $db->fetch_object($resql))) {
	$lignes[] = $o;
}
if (empty($lignes)) {
	print '<span class="opacitymedium">'.$langs->trans('AucuneMatiereNiveau').'</span>';
} else {
	$opts = array('' => $langs->trans('RegleDuNiveau', $langs->transnoentities($choix['calcul_devoirs'][$r['calcul_devoirs']])));
	foreach ($choix['calcul_devoirs'] as $v => $l) {
		$opts[$v] = $langs->trans($l);
	}
	print '<form method="POST" action="'.dol_escape_htmltag($self).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="saveexceptions">';
	print '<input type="hidden" name="fk_niveau" value="'.$fk_niveau.'">';
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Classe').'</td><td>'.$langs->trans('Matiere').'</td><td>'.$langs->trans('CalculDevoirs').'</td></tr>';
	foreach ($lignes as $l) {
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($l->cref).'</td><td>'.dol_escape_htmltag(ecole_label($l)).'</td>';
		print '<td>'.$form->selectarray('ex['.((int) $l->fk_classe).'_'.((int) $l->fk_matiere).']', $opts, (string) $l->calcul_devoirs, 0, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
	}
	print '</table></div>';
	print '<div class="center"><br><input type="submit" class="button button-save" value="'.$langs->trans('Save').'"></div>';
	print '</form>';
}

print dol_get_fiche_end();
llxFooter();
$db->close();
