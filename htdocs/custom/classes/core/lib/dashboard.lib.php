<?php
/**
 * Tableaux de bord de l'école : briques d'affichage communes (cartes de chiffres clés, graphiques Dolibarr,
 * blocs, fil des derniers événements) et calculs par domaine (élèves, aujourd'hui, paiements, notes,
 * personnel, salaires, espace). Utilisé par l'accueil de Dolibarr et par l'accueil de chaque module.
 * Tout suit les droits de l'utilisateur et le thème Dolibarr (clair / sombre).
 *
 * Fichier : custom/classes/core/lib/dashboard.lib.php
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';

/* ----------------------------------------------------------------------
 * Affichage
 * -------------------------------------------------------------------- */

/**
 * Styles des tableaux de bord (une seule fois par page).
 *
 * @return string
 */
function ecole_dash_css()
{
	static $fait = false;
	if ($fait) {
		return '';
	}
	$fait = true;
	return '<style>
.ed-hero{display:flex;align-items:center;gap:16px;padding:18px 22px;margin:0 0 18px;border-radius:14px;color:#fff;background:linear-gradient(120deg,#263c5c 0%,#3a6ea5 55%,#4f9bd4 100%);box-shadow:0 4px 14px rgba(20,40,80,.18)}
.ed-hero img{height:58px;width:58px;object-fit:contain;background:#fff;border-radius:12px;padding:4px}
.ed-hero h1{margin:0;font-size:1.45em;font-weight:600;color:#fff}.ed-hero .ed-sub{opacity:.9;margin-top:4px}
.ed-hero .ed-date{margin-inline-start:auto;text-align:end;font-size:.95em;opacity:.95}
.ed-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:14px;margin:0 0 18px}
.ed-kpi{position:relative;display:flex;gap:12px;align-items:center;padding:14px 16px;border-radius:12px;background:var(--colorbacktabcard1,#fff);border:1px solid var(--colortopbordertitle1,#e3e6ec);box-shadow:0 1px 3px rgba(0,0,0,.05);text-decoration:none!important;color:inherit!important;transition:transform .12s,box-shadow .12s}
a.ed-kpi:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(0,0,0,.10)}
.ed-kpi .ed-ic{flex:0 0 44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.25em;color:#fff}
.ed-kpi .ed-v{font-size:1.55em;font-weight:700;line-height:1.1}.ed-kpi .ed-l{opacity:.75;font-size:.9em;margin-top:2px}
.ed-kpi .ed-s{font-size:.8em;opacity:.65;margin-top:2px}
.ed-c-blue .ed-ic{background:#3a6ea5}.ed-c-green .ed-ic{background:#2e9e5b}.ed-c-orange .ed-ic{background:#e08a1c}.ed-c-red .ed-ic{background:#d04747}
.ed-c-purple .ed-ic{background:#8a4fc4}.ed-c-teal .ed-ic{background:#1b9a9a}.ed-c-grey .ed-ic{background:#6b7280}.ed-c-pink .ed-ic{background:#d23f7a}
.ed-cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));gap:16px;margin-bottom:16px}
.ed-box{background:var(--colorbacktabcard1,#fff);border:1px solid var(--colortopbordertitle1,#e3e6ec);border-radius:12px;padding:0;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.05)}
.ed-box-h{display:flex;align-items:center;gap:8px;padding:11px 14px;font-weight:600;border-bottom:1px solid var(--colortopbordertitle1,#eef0f4)}
.ed-box-h .ed-more{margin-inline-start:auto;font-weight:normal;font-size:.9em}
.ed-box-b{padding:10px 14px}
.ed-feed{list-style:none;margin:0;padding:0}.ed-feed li{display:flex;gap:10px;align-items:flex-start;padding:8px 0;border-bottom:1px dashed var(--colortopbordertitle1,#eceff3)}
.ed-feed li:last-child{border-bottom:0}.ed-feed .ed-fi{flex:0 0 30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.85em}
.ed-feed .ed-ft{flex:1}.ed-feed .ed-fd{font-size:.8em;opacity:.65;white-space:nowrap}
.ed-bar{height:8px;border-radius:5px;background:var(--colortopbordertitle1,#eceff3);overflow:hidden}.ed-bar span{display:block;height:100%;border-radius:5px;background:#3a6ea5}
.ed-row{display:flex;justify-content:space-between;gap:10px;padding:6px 0;border-bottom:1px solid var(--colortopbordertitle1,#f1f2f5)}.ed-row:last-child{border-bottom:0}
.ed-empty{opacity:.6;padding:10px 0}
.ed-pager{display:flex;align-items:center;justify-content:center;gap:14px;padding-top:8px;border-top:1px solid var(--colortopbordertitle1,#eef0f4);margin-top:4px}
.ed-pager a{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;border:1px solid var(--colortopbordertitle1,#dfe3ea);text-decoration:none}
.ed-pager a.disabled{opacity:.35;pointer-events:none}.ed-pnum{font-size:.9em;opacity:.8}
[dir=rtl] .ed-pager .fa-chevron-left,[dir=rtl] .ed-pager .fa-chevron-right{transform:scaleX(-1)}
.ed-links{display:flex;flex-wrap:wrap;gap:8px}.ed-links a{padding:6px 11px;border-radius:18px;border:1px solid var(--colortopbordertitle1,#dfe3ea);text-decoration:none;font-size:.92em}
.ed-links a:hover{background:rgba(58,110,165,.08)}
</style>';
}

/**
 * Bandeau d'accueil (logo, nom de l'école, titre, date du jour).
 *
 * @param  string $titre    Titre
 * @param  string $soustitre Sous-titre
 * @return string
 */
function ecole_dash_hero($titre, $soustitre = '')
{
	global $mysoc, $conf, $langs;
	$logo = '';
	if (is_object($mysoc) && !empty($mysoc->logo_small) && is_readable($conf->mycompany->dir_output.'/logos/thumbs/'.$mysoc->logo_small)) {
		$logo = '<img src="'.DOL_URL_ROOT.'/viewimage.php?modulepart=mycompany&file='.urlencode('logos/thumbs/'.$mysoc->logo_small).'" alt="">';
	}
	$date = dol_print_date(dol_now(), '%A %d %B %Y', 'tzuser');
	return ecole_dash_css().'<div class="ed-hero">'.$logo.'<div><h1>'.dol_escape_htmltag($titre).'</h1>'.($soustitre !== '' ? '<div class="ed-sub">'.$soustitre.'</div>' : '').'</div>'
		.'<div class="ed-date"><i class="far fa-calendar"></i> '.dol_escape_htmltag(ucfirst($date)).'</div></div>';
}

/**
 * Grille de cartes de chiffres clés.
 *
 * @param  array $kpis Liste de [libellé, valeur, icône fa, couleur (blue, green...), url, sous-texte]
 * @return string
 */
function ecole_dash_kpis($kpis)
{
	$out = ecole_dash_css().'<div class="ed-grid">';
	foreach ($kpis as $k) {
		$tag = !empty($k[4]) ? 'a href="'.dol_escape_htmltag($k[4]).'"' : 'div';
		$out .= '<'.$tag.' class="ed-kpi ed-c-'.(!empty($k[3]) ? $k[3] : 'blue').'"><div class="ed-ic"><i class="fas '.$k[2].'"></i></div><div>';
		$out .= '<div class="ed-v">'.$k[1].'</div><div class="ed-l">'.dol_escape_htmltag($k[0]).'</div>'.(!empty($k[5]) ? '<div class="ed-s">'.$k[5].'</div>' : '').'</div>';
		$out .= '</'.(!empty($k[4]) ? 'a' : 'div').'>';
	}
	return $out.'</div>';
}

/**
 * Bloc avec titre (et lien « voir tout »).
 *
 * @param  string $titre Titre
 * @param  string $icone Icône fa
 * @param  string $html  Contenu
 * @param  string $url   Lien « voir tout » ('' = aucun)
 * @return string
 */
function ecole_dash_box($titre, $icone, $html, $url = '')
{
	global $langs;
	return '<div class="ed-box"><div class="ed-box-h"><i class="fas '.$icone.'"></i> '.dol_escape_htmltag($titre)
		.($url !== '' ? '<a class="ed-more" href="'.dol_escape_htmltag($url).'">'.$langs->trans('EdVoirTout').' <i class="fas fa-angle-right"></i></a>' : '')
		.'</div><div class="ed-box-b">'.$html.'</div></div>';
}

/**
 * Graphique Dolibarr (barres, courbes, anneau...).
 *
 * @param  string   $id      Identifiant unique
 * @param  string   $type    bars | lines | pie | horizontalbars
 * @param  array    $data    array(array('libellé', v1[, v2...]), ...)
 * @param  string[] $legende Légende des séries
 * @param  int      $hauteur Hauteur (px)
 * @return string
 */
function ecole_dash_graph($id, $type, $data, $legende = array(), $hauteur = 220)
{
	global $langs;
	if (empty($data)) {
		return '<div class="ed-empty">'.$langs->trans('EdAucuneDonnee').'</div>';
	}
	$px = new DolGraph();
	if ($px->isGraphKo()) {
		return '';
	}
	$px->SetData($data);
	$px->SetType(array($type));
	if (!empty($legende)) {
		$px->SetLegend($legende);
	}
	if ($type === 'pie') {
		$px->setShowLegend(2);
		$px->setShowPercent(1);
		$px->SetDataColor(array(array(58, 110, 165), array(210, 63, 122), array(46, 158, 91), array(224, 138, 28), array(138, 79, 196), array(27, 154, 154), array(107, 114, 128)));
	} else {
		$px->SetDataColor(array(array(58, 110, 165), array(46, 158, 91), array(224, 138, 28)));
		$px->SetMinValue(0);
		$px->SetMaxValue($px->GetCeilMaxValue());
	}
	$px->SetWidth('100%');
	$px->SetHeight($hauteur);
	$px->SetCssPrefix('cssboxes');
	$px->draw('ecoledash_'.preg_replace('/[^a-z0-9_]/i', '', $id));
	return $px->show();
}

/**
 * Lignes « libellé — valeur » (avec barre de progression facultative).
 *
 * @param  array $lignes Liste de [libellé (HTML), valeur (HTML), pourcentage ou null]
 * @return string
 */
function ecole_dash_rows($lignes)
{
	global $langs;
	if (empty($lignes)) {
		return '<div class="ed-empty">'.$langs->trans('EdAucuneDonnee').'</div>';
	}
	$out = '';
	foreach ($lignes as $l) {
		$out .= '<div class="ed-row"><span>'.$l[0].'</span><b>'.$l[1].'</b></div>';
		if (isset($l[2]) && $l[2] !== null) {
			$out .= '<div class="ed-bar"><span style="width:'.max(0, min(100, (int) $l[2])).'%"></span></div>';
		}
	}
	return $out;
}

/**
 * Raccourcis (pastilles).
 *
 * @param  array $liens Liste de [droit (bool), url, libellé, icône]
 * @return string
 */
function ecole_dash_links($liens)
{
	$out = '<div class="ed-links">';
	foreach ($liens as $l) {
		if ($l[0]) {
			$out .= '<a href="'.dol_escape_htmltag($l[1]).'"><i class="fas '.$l[3].'"></i> '.dol_escape_htmltag($l[2]).'</a>';
		}
	}
	return $out.'</div>';
}

/* ----------------------------------------------------------------------
 * Calculs par domaine
 * -------------------------------------------------------------------- */

/**
 * La table existe-t-elle (module facultatif) ?
 *
 * @param  string $table Table sans préfixe
 * @return bool
 */
function ecole_dash_table($table)
{
	global $db;
	return function_exists('ecole_table_exists') && ecole_table_exists($db, $table);
}

/**
 * Premier résultat numérique d'une requête.
 *
 * @param  string $sql Requête
 * @return float
 */
function ecole_dash_val($sql)
{
	global $db;
	$resql = $db->query($sql);
	$o = $resql ? $db->fetch_row($resql) : null;
	return $o ? (float) $o[0] : 0.0;
}

/**
 * Élèves : effectifs par statut, filles / garçons, par niveau, pièces manquantes, classes pleines.
 *
 * @return array
 */
function ecole_dash_eleves()
{
	global $db;
	$p = $db->prefix();
	$ent = getEntity('ecole_eleve');
	$out = array('statut' => array(), 'filles' => 0, 'garcons' => 0, 'niveaux' => array(), 'incomplets' => 0, 'pleines' => 0, 'classes' => 0, 'nouveaux' => 0);
	$resql = $db->query("SELECT status, COUNT(*) as nb FROM ".$p."ecole_eleve WHERE entity IN (".$ent.") GROUP BY status");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out['statut'][(int) $o->status] = (int) $o->nb;
	}
	$resql = $db->query("SELECT sexe, COUNT(*) as nb FROM ".$p."ecole_eleve WHERE entity IN (".$ent.") AND status IN (1,3) GROUP BY sexe");
	while ($resql && ($o = $db->fetch_object($resql))) {
		if ($o->sexe === 'F') {
			$out['filles'] = (int) $o->nb;
		} elseif ($o->sexe === 'M') {
			$out['garcons'] = (int) $o->nb;
		}
	}
	$sql = "SELECT n.label_fr, n.label_ar, COUNT(e.rowid) as nb FROM ".$p."ecole_niveau n LEFT JOIN ".$p."ecole_classe c ON c.fk_niveau = n.rowid";
	$sql .= " LEFT JOIN ".$p."ecole_eleve e ON e.fk_classe = c.rowid AND e.status IN (1,3) WHERE n.entity IN (".getEntity('ecole_niveau').") AND n.status = 1";
	$sql .= " GROUP BY n.rowid, n.label_fr, n.label_ar, n.position ORDER BY n.position";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out['niveaux'][] = array(ecole_label($o), (int) $o->nb);
	}
	$sql = "SELECT c.rowid, c.effectif_max, (SELECT COUNT(*) FROM ".$p."ecole_eleve e WHERE e.fk_classe = c.rowid AND e.status IN (1,3)) as nb";
	$sql .= " FROM ".$p."ecole_classe c WHERE c.entity IN (".getEntity('ecole_classe').") AND c.status = 1";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out['classes']++;
		if ((int) $o->effectif_max > 0 && (int) $o->nb >= (int) $o->effectif_max) {
			$out['pleines']++;
		}
	}
	if (ecole_dash_table('ecole_document_type')) {
		$out['incomplets'] = (int) ecole_dash_val("SELECT COUNT(DISTINCT e.rowid) FROM ".$p."ecole_eleve e CROSS JOIN ".$p."ecole_document_type t"
			." LEFT JOIN ".$p."ecole_eleve_document d ON d.fk_eleve = e.rowid AND d.fk_document_type = t.rowid"
			." WHERE e.entity IN (".$ent.") AND e.status IN (0,1,2,3) AND t.status = 1 AND (d.rowid IS NULL OR d.fourni = 0)");
	}
	$out['nouveaux'] = (int) ecole_dash_val("SELECT COUNT(*) FROM ".$p."ecole_eleve WHERE entity IN (".$ent.") AND date_creation >= '".$db->idate(dol_now() - 30 * 86400)."'");
	return $out;
}

/**
 * Aujourd'hui : appels faits / prévus, absents, retards, renvoyés, cours prévus, enseignants absents, examens à venir.
 *
 * @return array
 */
function ecole_dash_aujourdhui()
{
	global $db;
	$p = $db->prefix();
	$jour = dol_print_date(dol_now(), '%Y-%m-%d', 'tzuser');
	$dow = (int) date('N', strtotime($jour)); // 1 = lundi ... 7 = dimanche (comme l'emploi du temps)
	$out = array('date' => $jour, 'cours' => 0, 'appels' => 0, 'absents' => 0, 'retards' => 0, 'renvoyes' => 0, 'profs_absents' => 0, 'examens' => array());
	// Cours de l'emploi du temps ce jour-là (classes actives), aucun un jour non ouvrable
	if (!function_exists('ecole_jours_ouvrables') || in_array($dow, ecole_jours_ouvrables(), true)) {
		$out['cours'] = (int) ecole_dash_val("SELECT COUNT(*) FROM ".$p."ecole_edt_cours e INNER JOIN ".$p."ecole_classe c ON c.rowid = e.fk_classe AND c.status = 1 WHERE e.entity IN (".getEntity('ecole_classe').") AND e.jour = ".$dow);
	}
	if (ecole_dash_table('ecole_appel')) {
		$out['appels'] = (int) ecole_dash_val("SELECT COUNT(*) FROM ".$p."ecole_appel WHERE entity IN (".getEntity('ecole_appel').") AND date_appel = '".$db->escape($jour)."'");
		$resql = $db->query("SELECT type, COUNT(DISTINCT fk_eleve) as nb FROM ".$p."ecole_absence WHERE date_appel = '".$db->escape($jour)."' GROUP BY type");
		while ($resql && ($o = $db->fetch_object($resql))) {
			$k = array(1 => 'absents', 2 => 'retards', 3 => 'renvoyes');
			if (isset($k[(int) $o->type])) {
				$out[$k[(int) $o->type]] = (int) $o->nb;
			}
		}
	}
	if (ecole_dash_table('ecole_absence_perso')) {
		$out['profs_absents'] = (int) ecole_dash_val("SELECT COUNT(DISTINCT fk_employe) FROM ".$p."ecole_absence_perso WHERE status = 1 AND date_debut <= '".$db->escape($jour)." 23:59:59' AND date_fin >= '".$db->escape($jour)."'");
	}
	if (ecole_dash_table('ecole_cours_prof')) {
		$out['profs_absents'] = max($out['profs_absents'], (int) ecole_dash_val("SELECT COUNT(DISTINCT fk_employe) FROM ".$p."ecole_cours_prof WHERE date_cours = '".$db->escape($jour)."' AND etat = 'absent'"));
	}
	$sql = "SELECT e.date_examen, e.heure_debut, c.ref as cref, m.label_fr, m.label_ar FROM ".$p."ecole_edt_examen e INNER JOIN ".$p."ecole_classe c ON c.rowid = e.fk_classe";
	$sql .= " INNER JOIN ".$p."ecole_matiere m ON m.rowid = e.fk_matiere WHERE e.date_examen >= '".$db->escape($jour)."' AND e.date_examen <= '".$db->escape(dol_print_date(dol_now() + 7 * 86400, '%Y-%m-%d', 'tzuser'))."'";
	$sql .= " ORDER BY e.date_examen, e.heure_debut LIMIT 8";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out['examens'][] = $o;
	}
	return $out;
}

/**
 * Paiements scolaires : encaissé aujourd'hui / ce mois / cette année, impayés, encaissements par mois, derniers reçus.
 *
 * @return array
 */
function ecole_dash_paiements()
{
	global $db;
	dol_include_once('/eleves/core/lib/paiements.lib.php');
	$p = $db->prefix();
	$ent = getEntity('ecole_recu');
	$now = dol_now();
	$jour = dol_print_date($now, '%Y-%m-%d', 'tzuser');
	$mois = dol_print_date($now, '%Y-%m', 'tzuser');
	$annee = eleves_annee_scolaire();
	$debutAnnee = sprintf('%04d-09-01', $annee);
	$out = array('jour' => 0, 'mois' => 0, 'annee' => 0, 'nb_jour' => 0, 'impaye' => 0, 'nb_impayes' => 0, 'par_mois' => array(), 'derniers' => array());
	$out['jour'] = ecole_dash_val("SELECT SUM(montant) FROM ".$p."ecole_recu WHERE entity IN (".$ent.") AND status = 1 AND DATE(date_recu) = '".$db->escape($jour)."'");
	$out['nb_jour'] = (int) ecole_dash_val("SELECT COUNT(*) FROM ".$p."ecole_recu WHERE entity IN (".$ent.") AND status = 1 AND DATE(date_recu) = '".$db->escape($jour)."'");
	$out['mois'] = ecole_dash_val("SELECT SUM(montant) FROM ".$p."ecole_recu WHERE entity IN (".$ent.") AND status = 1 AND DATE_FORMAT(date_recu, '%Y-%m') = '".$db->escape($mois)."'");
	$out['annee'] = ecole_dash_val("SELECT SUM(montant) FROM ".$p."ecole_recu WHERE entity IN (".$ent.") AND status = 1 AND date_recu >= '".$db->escape($debutAnnee)."'");
	$resql = $db->query("SELECT DATE_FORMAT(date_recu, '%Y-%m') as m, SUM(montant) as t FROM ".$p."ecole_recu WHERE entity IN (".$ent.") AND status = 1 AND date_recu >= '".$db->escape($debutAnnee)."' GROUP BY DATE_FORMAT(date_recu, '%Y-%m') ORDER BY m");
	$parMois = array();
	while ($resql && ($o = $db->fetch_object($resql))) {
		$parMois[$o->m] = (float) $o->t;
	}
	for ($i = 0; $i < 12; $i++) {
		$ts = dol_mktime(12, 0, 0, 9 + $i > 12 ? 9 + $i - 12 : 9 + $i, 1, 9 + $i > 12 ? $annee + 1 : $annee);
		$k = dol_print_date($ts, '%Y-%m');
		if ($k > $mois) {
			break;
		}
		$out['par_mois'][] = array(dol_print_date($ts, '%b'), isset($parMois[$k]) ? $parMois[$k] : 0);
	}
	if (function_exists('eleves_impayes')) {
		$imp = eleves_impayes($db);
		$out['impaye'] = (float) $imp['total'];
		$out['nb_impayes'] = count($imp['lignes']);
	}
	$resql = $db->query("SELECT rowid, ref, date_recu, montant FROM ".$p."ecole_recu WHERE entity IN (".$ent.") AND status = 1 ORDER BY date_recu DESC, rowid DESC LIMIT 6");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out['derniers'][] = $o;
	}
	return $out;
}

/**
 * Notes : trimestre en cours, évaluations et notes saisies, classes clôturées.
 *
 * @return array
 */
function ecole_dash_notes()
{
	global $db;
	$p = $db->prefix();
	$out = array('evaluations' => 0, 'notes' => 0, 'cloturees' => array(1 => 0, 2 => 0, 3 => 0), 'classes' => 0, 'par_trimestre' => array());
	if (!ecole_dash_table('ecole_evaluation')) {
		return $out;
	}
	$out['classes'] = (int) ecole_dash_val("SELECT COUNT(*) FROM ".$p."ecole_classe WHERE entity IN (".getEntity('ecole_classe').") AND status = 1");
	$out['evaluations'] = (int) ecole_dash_val("SELECT COUNT(*) FROM ".$p."ecole_evaluation WHERE entity IN (".getEntity('ecole_evaluation').") AND status = 1".ecole_annee_sql());
	$out['notes'] = (int) ecole_dash_val("SELECT COUNT(*) FROM ".$p."ecole_note n INNER JOIN ".$p."ecole_evaluation v ON v.rowid = n.fk_evaluation WHERE v.status = 1".ecole_annee_sql('v.annee'));
	$resql = $db->query("SELECT trimestre, COUNT(*) as nb FROM ".$p."ecole_note_cloture WHERE status = 1".ecole_annee_sql()." GROUP BY trimestre");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out['cloturees'][(int) $o->trimestre] = (int) $o->nb;
	}
	$resql = $db->query("SELECT trimestre, COUNT(*) as nb FROM ".$p."ecole_evaluation WHERE entity IN (".getEntity('ecole_evaluation').") AND status = 1".ecole_annee_sql()." GROUP BY trimestre ORDER BY trimestre");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out['par_trimestre'][] = array('T'.(int) $o->trimestre, (int) $o->nb);
	}
	return $out;
}

/**
 * Personnel : effectif par catégorie et par statut, absents aujourd'hui.
 *
 * @return array
 */
function ecole_dash_personnel()
{
	global $db;
	$p = $db->prefix();
	$out = array('actifs' => 0, 'categories' => array(), 'statut' => array());
	if (!ecole_dash_table('ecole_employe')) {
		return $out;
	}
	$resql = $db->query("SELECT status, categories FROM ".$p."ecole_employe WHERE entity IN (".getEntity('ecole_employe').")");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$st = (int) $o->status;
		$out['statut'][$st] = (isset($out['statut'][$st]) ? $out['statut'][$st] : 0) + 1;
		if (in_array($st, array(1, 2), true)) {
			$out['actifs']++;
			foreach (array_filter(explode(',', (string) $o->categories)) as $c) {
				$out['categories'][$c] = (isset($out['categories'][$c]) ? $out['categories'][$c] : 0) + 1;
			}
		}
	}
	arsort($out['categories']);
	return $out;
}

/**
 * Salaires du mois : bulletins par statut et montants.
 *
 * @return array
 */
function ecole_dash_salaires()
{
	global $db;
	$p = $db->prefix();
	$mois = dol_print_date(dol_now(), '%Y-%m', 'tzuser');
	$out = array('mois' => $mois, 'brouillon' => 0, 'valide' => 0, 'paye' => 0, 'net' => 0, 'net_paye' => 0, 'par_mois' => array());
	if (!ecole_dash_table('ecole_salaire')) {
		return $out;
	}
	$resql = $db->query("SELECT status, COUNT(*) as nb, SUM(net) as net FROM ".$p."ecole_salaire WHERE entity IN (".getEntity('ecole_salaire').") AND mois = '".$db->escape($mois)."' AND status <> 9 GROUP BY status");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$k = array(0 => 'brouillon', 1 => 'valide', 2 => 'paye');
		if (isset($k[(int) $o->status])) {
			$out[$k[(int) $o->status]] = (int) $o->nb;
		}
		$out['net'] += (float) $o->net;
		if ((int) $o->status === 2) {
			$out['net_paye'] += (float) $o->net;
		}
	}
	$resql = $db->query("SELECT mois, SUM(net) as net FROM ".$p."ecole_salaire WHERE entity IN (".getEntity('ecole_salaire').") AND status IN (1,2) GROUP BY mois ORDER BY mois DESC LIMIT 6");
	$tmp = array();
	while ($resql && ($o = $db->fetch_object($resql))) {
		$tmp[] = array(dol_print_date(dol_mktime(12, 0, 0, (int) substr($o->mois, 5, 2), 1, (int) substr($o->mois, 0, 4)), '%b %y'), (float) $o->net);
	}
	$out['par_mois'] = array_reverse($tmp);
	return $out;
}

/**
 * Espace : accès par type et par état, connexions des 7 derniers jours.
 *
 * @return array
 */
function ecole_dash_espace()
{
	global $db;
	$p = $db->prefix();
	$out = array('types' => array(), 'actifs' => 0, 'desactives' => 0, 'jamais' => 0, 'connectes7' => 0);
	if (!ecole_dash_table('ecole_acces')) {
		return $out;
	}
	$resql = $db->query("SELECT type, status, date_derniere_connexion FROM ".$p."ecole_acces WHERE entity IN (".getEntity('ecole_acces').")");
	$limite = $db->idate(dol_now() - 7 * 86400);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out['types'][$o->type] = (isset($out['types'][$o->type]) ? $out['types'][$o->type] : 0) + 1;
		if ((int) $o->status === 1) {
			$out['actifs']++;
		} else {
			$out['desactives']++;
		}
		if (empty($o->date_derniere_connexion)) {
			$out['jamais']++;
		} elseif ($o->date_derniere_connexion >= $limite) {
			$out['connectes7']++;
		}
	}
	return $out;
}

/**
 * Derniers événements de l'école, du plus récent au plus ancien, chacun avec son lien (selon les droits) :
 * inscriptions, paiements, absences et retards, sanctions, évaluations, bulletins de paie, employés, accès à l'espace.
 *
 * @param  int $limite Nombre d'événements
 * @return array       Liste de [horodatage, icône, couleur, texte HTML, url]
 */
function ecole_dash_evenements($limite = 20)
{
	global $db, $user, $langs;
	$p = $db->prefix();
	$ev = array();
	$ajoute = function ($sql, $fn) use ($db, &$ev) {
		$resql = $db->query($sql);
		while ($resql && ($o = $db->fetch_object($resql))) {
			$r = $fn($o);
			if ($r) {
				$ev[] = $r;
			}
		}
	};
	$n = (int) $limite;
	if (isModEnabled('eleves') && $user->hasRight('eleves', 'eleve', 'lire')) {
		$ajoute("SELECT rowid, ref, nom_fr, nom_ar, status, date_creation FROM ".$p."ecole_eleve WHERE entity IN (".getEntity('ecole_eleve').") ORDER BY date_creation DESC LIMIT ".$n,
			function ($o) use ($db, $langs) {
				return array($db->jdate($o->date_creation), 'fa-user-plus', '#3a6ea5', $langs->trans('EdEvInscription', '<b>'.dol_escape_htmltag(ecole_label($o)).'</b>', dol_escape_htmltag($o->ref)), dol_buildpath('/eleves/eleve/card.php', 1).'?id='.((int) $o->rowid));
			});
		if ($user->hasRight('eleves', 'paiement', 'lire')) {
			$ajoute("SELECT r.rowid, r.ref, r.montant, r.date_creation, r.status FROM ".$p."ecole_recu r WHERE r.entity IN (".getEntity('ecole_recu').") ORDER BY r.date_creation DESC LIMIT ".$n,
				function ($o) use ($db, $langs) {
					$annule = (int) $o->status === 0;
					return array($db->jdate($o->date_creation), $annule ? 'fa-ban' : 'fa-receipt', $annule ? '#6b7280' : '#2e9e5b',
						$langs->trans($annule ? 'EdEvRecuAnnule' : 'EdEvRecu', '<b>'.price($o->montant, 0, $langs, 1, -1, -1, 'auto').'</b>', dol_escape_htmltag($o->ref)), dol_buildpath('/eleves/recu/card.php', 1).'?id='.((int) $o->rowid));
				});
		}
		if ($user->hasRight('eleves', 'absence', 'lire') && ecole_dash_table('ecole_appel')) {
			$ajoute("SELECT a.fk_classe, a.date_appel, a.nb_absents, a.nb_retards, a.date_creation, c.ref as cref FROM ".$p."ecole_appel a LEFT JOIN ".$p."ecole_classe c ON c.rowid = a.fk_classe"
				." WHERE a.entity IN (".getEntity('ecole_appel').") AND (a.nb_absents > 0 OR a.nb_retards > 0) ORDER BY a.date_creation DESC LIMIT ".$n,
				function ($o) use ($db, $langs) {
					return array($db->jdate($o->date_creation), 'fa-user-clock', '#e08a1c', $langs->trans('EdEvAppel', dol_escape_htmltag($o->cref), (int) $o->nb_absents, (int) $o->nb_retards), dol_buildpath('/eleves/absence/list.php', 1));
				});
		}
		if ($user->hasRight('eleves', 'sanction', 'lire') && ecole_dash_table('ecole_sanction')) {
			$ajoute("SELECT s.fk_eleve, s.date_creation, e.nom_fr, e.nom_ar, t.label_fr as tfr, t.label_ar as tar FROM ".$p."ecole_sanction s LEFT JOIN ".$p."ecole_eleve e ON e.rowid = s.fk_eleve"
				." LEFT JOIN ".$p."ecole_sanction_type t ON t.rowid = s.fk_type WHERE s.entity IN (".getEntity('ecole_sanction').") AND s.status = 1 ORDER BY s.date_creation DESC LIMIT ".$n,
				function ($o) use ($db, $langs) {
					return array($db->jdate($o->date_creation), 'fa-gavel', '#d04747', $langs->trans('EdEvSanction', dol_escape_htmltag(ecole_label((object) array('label_fr' => $o->tfr, 'label_ar' => $o->tar))), '<b>'.dol_escape_htmltag(ecole_label($o)).'</b>'), dol_buildpath('/eleves/eleve/discipline.php', 1).'?id='.((int) $o->fk_eleve));
				});
		}
	}
	if (isModEnabled('notes') && ecole_dash_table('ecole_evaluation') && ($user->hasRight('notes', 'note', 'lire') || $user->hasRight('notes', 'note', 'saisirtout'))) {
		$ajoute("SELECT v.fk_classe, v.trimestre, v.date_creation, c.ref as cref, m.label_fr, m.label_ar FROM ".$p."ecole_evaluation v LEFT JOIN ".$p."ecole_classe c ON c.rowid = v.fk_classe"
			." LEFT JOIN ".$p."ecole_matiere m ON m.rowid = v.fk_matiere WHERE v.entity IN (".getEntity('ecole_evaluation').") AND v.status = 1".ecole_annee_sql('v.annee')." ORDER BY v.date_creation DESC LIMIT ".$n,
			function ($o) use ($db, $langs) {
				return array($db->jdate($o->date_creation), 'fa-star', '#8a4fc4', $langs->trans('EdEvEvaluation', dol_escape_htmltag(ecole_label($o)), dol_escape_htmltag($o->cref), (int) $o->trimestre), dol_buildpath('/notes/saisie.php', 1).'?fk_classe='.((int) $o->fk_classe).'&trimestre='.((int) $o->trimestre));
			});
	}
	if (isModEnabled('personnel') && ecole_dash_table('ecole_employe') && $user->hasRight('personnel', 'employe', 'lire')) {
		$ajoute("SELECT rowid, ref, nom_fr, nom_ar, date_creation FROM ".$p."ecole_employe WHERE entity IN (".getEntity('ecole_employe').") ORDER BY date_creation DESC LIMIT ".$n,
			function ($o) use ($db, $langs) {
				return array($db->jdate($o->date_creation), 'fa-id-badge', '#1b9a9a', $langs->trans('EdEvEmploye', '<b>'.dol_escape_htmltag(ecole_label($o)).'</b>'), dol_buildpath('/personnel/employe/card.php', 1).'?id='.((int) $o->rowid));
			});
	}
	if (isModEnabled('salaires') && ecole_dash_table('ecole_salaire') && $user->hasRight('salaires', 'bulletin', 'lire')) {
		$ajoute("SELECT s.rowid, s.ref, s.net, s.status, s.tms, e.nom_fr, e.nom_ar FROM ".$p."ecole_salaire s LEFT JOIN ".$p."ecole_employe e ON e.rowid = s.fk_employe"
			." WHERE s.entity IN (".getEntity('ecole_salaire').") AND s.status IN (1,2) ORDER BY s.tms DESC LIMIT ".$n,
			function ($o) use ($db, $langs) {
				return array($db->jdate($o->tms), 'fa-money-check-alt', '#d23f7a', $langs->trans((int) $o->status === 2 ? 'EdEvPaiePayee' : 'EdEvPaieValidee', dol_escape_htmltag($o->ref), '<b>'.dol_escape_htmltag(ecole_label($o)).'</b>'), dol_buildpath('/salaires/bulletin/card.php', 1).'?id='.((int) $o->rowid));
			});
	}
	usort($ev, function ($a, $b) {
		return $b[0] <=> $a[0];
	});
	return array_slice($ev, 0, $n);
}

/**
 * Date courte d'un événement : heure seule aujourd'hui, « hier HH:MM », sinon jour/mois (et année si autre année)
 * avec l'heure, jamais les secondes.
 *
 * @param  int $ts Horodatage
 * @return string
 */
function ecole_dash_date_courte($ts)
{
	global $langs;
	$jour = dol_print_date($ts, '%Y-%m-%d', 'tzuserrel');
	$auj = dol_print_date(dol_now(), '%Y-%m-%d', 'tzuserrel');
	$hier = dol_print_date(dol_now() - 86400, '%Y-%m-%d', 'tzuserrel');
	$heure = dol_print_date($ts, '%H:%M', 'tzuserrel');
	if ($jour === $auj) {
		return $heure;
	}
	if ($jour === $hier) {
		return $langs->trans('EdHier').' '.$heure;
	}
	return dol_print_date($ts, substr($jour, 0, 4) === substr($auj, 0, 4) ? '%d/%m' : '%d/%m/%Y', 'tzuserrel').' '.$heure;
}

/**
 * Fil des événements, avec pagination légère côté navigateur ($parPage > 0 : n événements par page,
 * boutons précédent / suivant, sans recharger la page).
 *
 * @param  array $ev      Événements (ecole_dash_evenements)
 * @param  int   $parPage Événements par page (0 = tout afficher)
 * @return string
 */
function ecole_dash_feed($ev, $parPage = 0)
{
	global $langs;
	static $n = 0;
	if (empty($ev)) {
		return '<div class="ed-empty">'.$langs->trans('EdAucunEvenement').'</div>';
	}
	$id = 'edfeed'.(++$n);
	$pages = ($parPage > 0) ? (int) ceil(count($ev) / $parPage) : 1;
	$out = '<ul class="ed-feed" id="'.$id.'">';
	foreach (array_values($ev) as $i => $e) {
		$page = ($parPage > 0) ? intdiv($i, $parPage) : 0;
		$out .= '<li data-page="'.$page.'"'.($page > 0 ? ' style="display:none"' : '').'><span class="ed-fi" style="background:'.$e[2].'"><i class="fas '.$e[1].'"></i></span><span class="ed-ft"><a href="'.dol_escape_htmltag($e[4]).'">'.$e[3].'</a></span>';
		$out .= '<span class="ed-fd" title="'.dol_escape_htmltag(dol_print_date($e[0], 'dayhour', 'tzuserrel')).'">'.dol_escape_htmltag(ecole_dash_date_courte($e[0])).'</span></li>';
	}
	$out .= '</ul>';
	if ($pages > 1) {
		$out .= '<div class="ed-pager" data-feed="'.$id.'" data-pages="'.$pages.'"><a href="#" class="ed-prev disabled"><i class="fas fa-chevron-left"></i></a>'
			.'<span class="ed-pnum">1 / '.$pages.'</span><a href="#" class="ed-next"><i class="fas fa-chevron-right"></i></a></div>';
		$out .= '<script>(function(){var b=document.querySelector(\'.ed-pager[data-feed="'.$id.'"]\'),f=document.getElementById("'.$id.'"),n='.$pages.',p=0;'
			.'function go(k){p=Math.max(0,Math.min(n-1,k));f.querySelectorAll("li").forEach(function(li){li.style.display=(+li.dataset.page===p)?"":"none";});'
			.'b.querySelector(".ed-pnum").textContent=(p+1)+" / "+n;b.querySelector(".ed-prev").classList.toggle("disabled",p===0);b.querySelector(".ed-next").classList.toggle("disabled",p===n-1);}'
			.'b.querySelector(".ed-prev").addEventListener("click",function(e){e.preventDefault();go(p-1);});b.querySelector(".ed-next").addEventListener("click",function(e){e.preventDefault();go(p+1);});})();</script>';
	}
	return $out;
}

/**
 * Montant court avec la devise.
 *
 * @param  float $v Montant
 * @return string
 */
function ecole_dash_montant($v)
{
	global $langs, $conf;
	return price(price2num($v, 'MT'), 0, $langs, 1, -1, 0, $conf->currency);
}
