<?php
/**
 * Accueil du module Classes : effectifs de classes par niveau et accès rapides.
 *
 * Fichier : custom/classes/index.php
 */

require 'init.php';

$langs->loadLangs(array('classes@classes', 'other'));

if (!$user->hasRight('classes', 'lire')) {
	accessforbidden();
}

$p = MAIN_DB_PREFIX;

llxHeader('', $langs->trans('Classes'), '', '', 0, 0, '', '', '', 'mod-classes page-index');
print load_fiche_titre($langs->trans('Classes'), '', 'fa-chalkboard');

print '<div class="fichecenter"><div class="fichethirdleft">';

// Classes par niveau
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Niveau').'</td><td class="right">'.$langs->trans('EcoleClasses').'</td><td class="right">'.$langs->trans('Mensualite').' ('.$conf->currency.')</td><td class="right">'.$langs->trans('FraisInscriptionClasse').' ('.$conf->currency.')</td></tr>';
$sql = "SELECT n.rowid, n.label_fr, n.label_ar, COUNT(c.rowid) as nb, MIN(c.mensualite) as mini, MAX(c.mensualite) as maxi, MIN(c.frais_inscription) as fmini, MAX(c.frais_inscription) as fmaxi";
$sql .= " FROM ".$p."ecole_niveau n LEFT JOIN ".$p."ecole_classe c ON c.fk_niveau = n.rowid AND c.status = 1";
$sql .= " WHERE n.entity IN (".getEntity('ecole_niveau').") AND n.status = 1 GROUP BY n.rowid, n.label_fr, n.label_ar, n.position ORDER BY n.position";
$resql = $db->query($sql);
$total = 0;
if ($resql) {
	while ($o = $db->fetch_object($resql)) {
		$total += $o->nb;
		$prix = '';
		$frais = '';
		if ($o->nb > 0) {
			$prix = ((float) $o->mini === (float) $o->maxi) ? price($o->mini) : price($o->mini).' - '.price($o->maxi);
			$frais = ((float) $o->fmini === (float) $o->fmaxi) ? price($o->fmini) : price($o->fmini).' - '.price($o->fmaxi);
		}
		print '<tr class="oddeven"><td>'.dol_escape_htmltag(ecole_label($o)).'</td><td class="right">'.((int) $o->nb).'</td><td class="right">'.$prix.'</td><td class="right">'.$frais.'</td></tr>';
	}
}
print '<tr class="liste_total"><td>'.$langs->trans('Total').'</td><td class="right">'.$total.'</td><td></td><td></td></tr>';
print '</table>';

print '</div><div class="fichetwothirdright">';

// Accès rapides
$links = array(
	array('lire', '/classes/classe/list.php', 'MenuClasses'),
	array('ecrire', '/classes/classe/card.php?action=create', 'MenuNouvelleClasse'),
	array('lire', '/classes/classe/emplois.php', 'MenuEmploisDuTemps'),
	array('lire', '/classes/salle/list.php', 'MenuSalles'),
	array('lire', '/classes/salle/disponibilite.php', 'MenuDisponibilite'),
	array('lire', '/classes/matiere/list.php', 'MenuMatieres'),
	array('ecrire', '/classes/matiere/card.php?action=create', 'MenuNouvelleMatiere'),
	array('config', '/classes/niveau/list.php', 'MenuNiveaux'),
	array('config', '/classes/creneau/list.php', 'MenuCreneaux'),
	array('config', '/classes/session/list.php', 'MenuSessions'),
);
print '<table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans('AccesRapides').'</td></tr>';
foreach ($links as $l) {
	if ($user->hasRight('classes', $l[0])) {
		print '<tr class="oddeven"><td><a href="'.dol_buildpath($l[1], 1).'">'.$langs->trans($l[2]).'</a></td></tr>';
	}
}
print '</table>';

print '</div></div>';

llxFooter();
$db->close();
