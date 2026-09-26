<?php
/**
 * Tarifs des classes par année scolaire : année en cours (ceux de la fiche de la classe) et année suivante
 * (préparés à l'avance, appliqués au passage à la nouvelle année). Une année passée est affichée en lecture seule.
 *
 * Fichier : custom/classes/classe/tarifs.php
 */

require '../init.php';

$langs->loadLangs(array('classes@classes', 'other'));

if (!$user->hasRight('classes', 'lire')) {
	accessforbidden();
}
$p = MAIN_DB_PREFIX;
$action = GETPOST('action', 'aZ09');
$active = ecole_annee_active();
$passee = ecole_annee_passee();
$canedit = $user->hasRight('classes', 'ecrire') && !$passee;

// Classes : actives pour l'année en cours, celles qui avaient un tarif pour une année passée
$sql = "SELECT c.rowid, c.ref, c.label_fr, c.label_ar, n.rowid as nid, n.label_fr as nlabel_fr, n.label_ar as nlabel_ar";
$sql .= " FROM ".$p."ecole_classe c INNER JOIN ".$p."ecole_niveau n ON n.rowid = c.fk_niveau";
$sql .= " WHERE c.entity IN (".getEntity('ecole_classe').")";
if ($passee) {
	$sql .= " AND c.rowid IN (SELECT t.fk_classe FROM ".$p."ecole_classe_tarif t WHERE t.annee = ".ecole_annee_vue().")";
} else {
	$sql .= " AND c.status = 1";
}
$sql .= " ORDER BY n.position, c.rowid";
$classes = array();
$resql = $db->query($sql);
while ($resql && ($o = $db->fetch_object($resql))) {
	$classes[(int) $o->rowid] = $o;
}

/*
 * Actions
 */
if ($action === 'save' && $canedit) {
	$mens = GETPOST('mens', 'array');
	$insc = GETPOST('insc', 'array');
	$db->begin();
	$err = 0;
	foreach (array($active, $active + 1) as $a) {
		foreach ($classes as $cid => $c) {
			if (!isset($mens[$a][$cid]) && !isset($insc[$a][$cid])) {
				continue;
			}
			$m = trim((string) $mens[$a][$cid]);
			$i = trim((string) $insc[$a][$cid]);
			if ($a !== $active && $m === '' && $i === '') {
				// année suivante laissée vide : pas de tarif préparé
				$db->query("DELETE FROM ".$p."ecole_classe_tarif WHERE annee = ".((int) $a)." AND fk_classe = ".((int) $cid)." AND frais_reinscription IS NULL");
				continue;
			}
			$actuel = ecole_classe_tarif($db, $cid, $a);
			if (ecole_tarif_enregistrer($db, $user, $cid, $a, (float) price2num($m === '' ? '0' : $m), (float) price2num($i === '' ? '0' : $i), $actuel->frais_reinscription) < 0) {
				$err++;
			}
		}
	}
	if ($err) {
		$db->rollback();
		setEventMessages($langs->trans('ErrorTarifsInvalides'), null, 'errors');
	} else {
		$db->commit();
		setEventMessages($langs->trans('RecordSaved'), null);
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
}

/*
 * Affichage
 */
llxHeader('', $langs->trans('TarifsParAnnee'), '', '', 0, 0, '', '', '', 'mod-classes page-tarifs');
print load_fiche_titre($langs->trans('TarifsParAnnee'), '', 'fa-money-bill-wave');
print ecole_annee_selecteur();
print '<div class="opacitymedium">'.$langs->trans($passee ? 'AideTarifsPasses' : 'AideTarifsAnnees', ecole_annee_label($active + 1)).'</div><br>';

$annees = $passee ? array(ecole_annee_vue()) : array($active, $active + 1);
$suivants = $passee ? array() : ecole_tarifs_annee($db, $active + 1);

if ($canedit) {
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';
}
print '<div class="div-table-responsive"><table class="noborder centpercent">';
print '<tr class="liste_titre"><td rowspan="2">'.$langs->trans('Classe').'</td><td rowspan="2">'.$langs->trans('EcoleLibelle').'</td>';
foreach ($annees as $a) {
	$lib = ecole_annee_label($a);
	if (!$passee) {
		$lib .= ' <span class="opacitymedium">('.$langs->trans($a === $active ? 'AnneeEnCours' : 'AnneeSuivante').')</span>';
	}
	print '<td colspan="2" class="center">'.$lib.'</td>';
}
print '</tr><tr class="liste_titre">';
foreach ($annees as $a) {
	print '<td class="right">'.$langs->trans('Mensualite').'</td><td class="right">'.$langs->trans('FraisInscriptionClasse').'</td>';
}
print '</tr>';

$curniv = 0;
$ncol = 2 + 2 * count($annees);
foreach ($classes as $cid => $o) {
	if ((int) $o->nid !== $curniv) {
		$curniv = (int) $o->nid;
		print '<tr class="liste_titre_sub"><td colspan="'.$ncol.'"><b>'.dol_escape_htmltag(ecole_label((object) array('label_fr' => $o->nlabel_fr, 'label_ar' => $o->nlabel_ar))).'</b></td></tr>';
	}
	print '<tr class="oddeven"><td class="nowraponall"><a href="'.dol_buildpath('/classes/classe/card.php', 1).'?id='.$cid.'">'.img_picto('', 'fa-chalkboard', 'class="pictofixedwidth"').dol_escape_htmltag($o->ref).'</a></td>';
	print '<td>'.dol_escape_htmltag(ecole_label($o)).'</td>';
	foreach ($annees as $a) {
		$t = ecole_classe_tarif($db, $cid, $a);
		$vide = ($a !== $active && !$passee && !isset($suivants[$cid]));
		foreach (array('mens' => 'mensualite', 'insc' => 'frais_inscription') as $name => $champ) {
			print '<td class="right nowraponall">';
			if ($canedit) {
				$val = $vide ? '' : price2num($t->$champ);
				$ph = $vide ? price2num(ecole_classe_tarif($db, $cid, $active)->$champ) : '';
				print '<input type="text" class="flat right width75" name="'.$name.'['.$a.']['.$cid.']" value="'.dol_escape_htmltag($val).'"'.($ph !== '' ? ' placeholder="'.dol_escape_htmltag($ph).'"' : '').'>';
			} else {
				print $vide ? '<span class="opacitymedium">—</span>' : price($t->$champ).' '.$conf->currency;
			}
			print '</td>';
		}
	}
	print '</tr>';
}
if (empty($classes)) {
	print '<tr class="oddeven"><td colspan="'.$ncol.'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div>';
if ($canedit && !empty($classes)) {
	print '<div class="center"><input type="submit" class="button button-save" value="'.dol_escape_htmltag($langs->trans('Save')).'"></div>';
	print '</form>';
}

llxFooter();
$db->close();
