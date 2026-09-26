<?php
/**
 * Affichage des résultats (tableau des moyennes d'une classe, suivi des classes, élèves en difficulté)
 * et jeux de données pour les exports PDF / Excel.
 *
 * Fichier : custom/notes/core/lib/resultats.lib.php
 */

dol_include_once('/notes/core/lib/calcul.lib.php');

/**
 * Classes visibles dans les résultats : toutes les classes actives avec le droit « résultats »,
 * sinon celles de l'utilisateur (voir notes_classes).
 *
 * @param  DoliDB $db   Handler base
 * @param  User   $user Utilisateur
 * @return array<int,object>
 */
function notes_classes_resultats($db, $user)
{
	if ($user->hasRight('notes', 'bulletin', 'lire')) {
		$p = $db->prefix();
		$sql = "SELECT c.rowid, c.ref, c.label_fr, c.label_ar, c.fk_niveau FROM ".$p."ecole_classe c LEFT JOIN ".$p."ecole_niveau n ON n.rowid = c.fk_niveau";
		$sql .= " WHERE c.entity IN (".getEntity('ecole_classe').")".notes_sql_classes_annee($db)." ORDER BY n.position, c.rowid";
		$out = array();
		$resql = $db->query($sql);
		while ($resql && ($o = $db->fetch_object($resql))) {
			$out[(int) $o->rowid] = $o;
		}
		return $out;
	}
	return notes_classes($db, $user);
}

/**
 * Onglets des périodes : 1er, 2e, 3e trimestre, Année (cadenas = clôturé pour la classe).
 *
 * @param  DoliDB $db        Handler base
 * @param  string $url       Adresse de base (sans période)
 * @param  int    $periode   Période active
 * @param  int    $fk_classe Classe (0 = aucune)
 * @param  bool   $annee     Proposer « Année »
 * @return string
 */
function notes_periode_tabs($db, $url, $periode, $fk_classe, $annee = true)
{
	global $langs;
	$out = '<style>.notes-trim{display:inline-block;padding:5px 12px;margin:0 4px 4px 0;border:1px solid #bbb;border-radius:4px;text-decoration:none;background:#fff}.notes-trim.on{background:#e8eefb;border-color:#6a84c4;font-weight:bold}</style>';
	$out .= '<div class="marginbottomonly">';
	foreach ($annee ? array(1, 2, 3, 0) : array(1, 2, 3) as $p) {
		$lock = ($fk_classe > 0 && notes_periode_close($db, $fk_classe, $p)) ? ' '.img_picto($langs->trans('TrimestreCloture'), 'fa-lock') : '';
		$out .= '<a class="notes-trim'.($p === (int) $periode ? ' on' : '').'" href="'.dol_escape_htmltag($url.(strpos($url, '?') === false ? '?' : '&').'periode='.$p).'">'.dol_escape_htmltag(notes_periode_label($p)).$lock.'</a>';
	}
	return $out.'</div>';
}

/**
 * Période lue dans la requête (0 = année ; par défaut le trimestre proposé pour la classe).
 *
 * @param  DoliDB $db        Handler base
 * @param  int    $fk_classe Classe
 * @return int
 */
function notes_periode_requete($db, $fk_classe)
{
	if (GETPOSTISSET('periode') && in_array(GETPOSTINT('periode'), array(0, 1, 2, 3), true)) {
		return GETPOSTINT('periode');
	}
	return notes_trimestre_defaut($db, $fk_classe);
}

/**
 * Élèves triés par rang (non classés à la fin, puis par numéro d'appel).
 *
 * @param  array $calc Résultats
 * @return int[]
 */
function notes_ordre_rang($calc)
{
	$ids = array_keys($calc['eleves']);
	usort($ids, function ($a, $b) use ($calc) {
		$ra = $calc['res'][$a]['rang'];
		$rb = $calc['res'][$b]['rang'];
		if ($ra !== $rb) {
			return ($ra === null ? 9999 : $ra) - ($rb === null ? 9999 : $rb);
		}
		return ((int) $calc['eleves'][$a]->numero_appel) - ((int) $calc['eleves'][$b]->numero_appel);
	});
	return $ids;
}

/**
 * Tableau des moyennes d'une classe pour une période : rang, élève, moyenne de chaque matière,
 * moyenne générale, mention, distinction (et décision pour l'année), avec un filtre par colonne.
 *
 * @param  DoliDB $db      Handler base
 * @param  User   $user    Utilisateur
 * @param  object $classe  Classe (rowid, ref, label_fr, label_ar)
 * @param  int    $periode 0 à 3
 * @return void
 */
function notes_resultats_html($db, $user, $classe, $periode)
{
	global $langs;
	$cid = (int) $classe->rowid;
	$calc = notes_calcul($db, $cid, $periode);
	$close = notes_periode_close($db, $cid, $periode);
	$conseil = notes_conseil($db, $cid, $periode);
	$regle = $calc['regle'];
	$annee = ((int) $periode === 0);

	if (empty($calc['matieres'])) {
		print '<div class="info">'.$langs->trans('AucuneMatiereClasse').'</div>';
		return;
	}
	if (empty($calc['eleves'])) {
		print '<div class="info">'.$langs->trans('AucunEleveClasse').'</div>';
		return;
	}

	// Barre d'actions : bulletins de la classe, conseil, exports
	$peutImprimer = $user->hasRight('notes', 'bulletin', 'lire');
	print '<div class="marginbottomonly">';
	if (!$close) {
		print '<span class="badge badge-status1">'.$langs->trans($annee ? 'ResultatsProvisoiresAnnee' : 'ResultatsProvisoires').'</span> ';
	}
	if ($peutImprimer) {
		dol_include_once('/notes/class/ecole_bulletin_modele.class.php');
		$modeles = EcoleBulletinModele::choix($db);
		$m = notes_modele_pour($db, $cid, 0);
		print '<form method="GET" action="'.dol_buildpath('/notes/bulletin.php', 1).'" target="_blank" class="inline-block marginleftonly">';
		print '<input type="hidden" name="fk_classe" value="'.$cid.'"><input type="hidden" name="periode" value="'.((int) $periode).'">';
		print img_picto('', 'fa-file-pdf', 'class="pictofixedwidth"').'<select name="modele" class="flat">';
		foreach ($modeles as $mid => $ml) {
			print '<option value="'.$mid.'"'.($m && (int) $m->id === $mid ? ' selected' : '').'>'.dol_escape_htmltag($ml).'</option>';
		}
		print '</select> ';
		$lib = $langs->trans($annee ? 'ImprimerRelevesClasse' : 'ImprimerBulletinsClasse');
		if ($close) {
			print '<input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($lib).'">';
		} else {
			print '<input type="submit" class="button smallpaddingimp" disabled value="'.dol_escape_htmltag($lib).'" title="'.dol_escape_htmltag($langs->trans($annee ? 'BulletinsApresClotureAnnee' : 'BulletinsApresCloture')).'">';
		}
		// Aperçu du modèle choisi dans la liste (données d'exemple)
		$urlApercu = dol_buildpath('/notes/modele/apercu.php', 1).'?type='.($annee ? 'releve' : 'bulletin').'&id=';
		print ' <a href="'.dol_escape_htmltag($urlApercu.($m ? (int) $m->id : 0)).'" target="_blank" onclick="this.href = this.getAttribute(\'data-base\') + this.parentNode.querySelector(\'select[name=modele]\').value;" data-base="'.dol_escape_htmltag($urlApercu).'" title="'.dol_escape_htmltag($langs->trans('ApercuModeles')).'">'.img_picto('', 'fa-eye', 'class="pictofixedwidth"').$langs->trans('Apercu').'</a>';
		print '</form> ';
	}
	if ($user->hasRight('notes', 'bulletin', 'conseil')) {
		print '<a class="button smallpaddingimp marginleftonly" href="'.dol_buildpath('/notes/conseil.php', 1).'?fk_classe='.$cid.'&periode='.((int) $periode).'">'.img_picto('', 'fa-users', 'class="pictofixedwidth"').$langs->trans('ConseilDeClasse').'</a> ';
	}
	print '<span class="marginleftonly">'.ecole_export_buttons('resultats', '&fk_classe='.$cid.'&periode='.((int) $periode), 'notes').'</span>';
	print '</div>';

	$mentions = array();
	foreach (notes_mentions($db) as $mo) {
		$mentions[$mo->ref] = ecole_label($mo);
	}
	$dists = array();
	foreach (notes_distinctions($db) as $do) {
		$dists[$do->ref] = ecole_label($do);
	}

	// Filtres : rang, n°, élève, matières, moyenne, mention, distinction, [décision], bulletin
	$filtres = array('range', null, 'text');
	foreach ($calc['matieres'] as $m) {
		$filtres[] = 'range';
	}
	$filtres[] = 'range';
	$filtres[] = $mentions;
	$filtres[] = $dists + array('-' => $langs->trans('Aucune'));
	if ($annee && $regle['decision'] !== 'aucune') {
		$dec = array();
		foreach (notes_decisions() as $k => $l) {
			$dec[$k] = $langs->trans($l);
		}
		$filtres[] = $dec;
	}
	$filtres[] = null;

	print '<div class="div-table-responsive"><table class="noborder centpercent notes-filtrable">';
	print notes_filtres_ligne($filtres);
	print '<tr class="liste_titre"><td class="center">'.$langs->trans('Rang').'</td><td class="center">'.$langs->trans('NumeroAppelCourt').'</td><td>'.$langs->trans('Eleve').'</td>';
	foreach ($calc['matieres'] as $m) {
		print '<td class="center" title="'.dol_escape_htmltag(ecole_label($m).' - '.$langs->trans('Coefficient').' '.notes_fmt($m->coefficient)).'">'.dol_escape_htmltag(dol_trunc(ecole_label($m), 14)).'<br><span class="opacitymedium small">×'.notes_fmt($m->coefficient).'</span></td>';
	}
	print '<td class="center"><b>'.$langs->trans($annee ? 'MoyenneAnnuelle' : 'MoyenneGenerale').'</b></td><td>'.$langs->trans('Mention').'</td><td>'.$langs->trans('Distinction').'</td>';
	if ($annee && $regle['decision'] !== 'aucune') {
		print '<td>'.$langs->trans('DecisionFinAnneeCourt').'</td>';
	}
	print '<td></td></tr>';

	$seuil = (float) getDolGlobalString('NOTES_SEUIL_DIFFICULTE', '10');
	foreach (notes_ordre_rang($calc) as $eid) {
		$e = $calc['eleves'][$eid];
		$r = $calc['res'][$eid];
		$c = isset($conseil[$eid]) ? $conseil[$eid] : null;
		$mention = notes_mention($db, $r['moyenne']);
		$dist = notes_distinction($db, $c, $r['moyenne']);
		print '<tr class="oddeven">';
		print '<td class="center" data-f="'.($r['rang'] !== null ? (int) $r['rang'] : '').'"><b>'.dol_escape_htmltag(notes_rang_label($r['rang'], $r['exaequo'])).'</b></td>';
		print '<td class="center">'.($e->numero_appel ? (int) $e->numero_appel : '').'</td>';
		print '<td class="nowraponall"><a href="'.dol_buildpath('/notes/eleve.php', 1).'?id='.$eid.'&periode='.((int) $periode).'" title="'.dol_escape_htmltag($e->ref).'" dir="auto">'.dol_escape_htmltag(ecole_label($e)).'</a></td>';
		foreach ($calc['matieres'] as $mid => $m) {
			$v = $r['matieres'][$mid]['moyenne'];
			print '<td class="center'.($v !== null && $v < 10 ? ' error' : '').'" data-f="'.($v !== null ? round($v, 2) : '').'">'.notes_moy($v).'</td>';
		}
		print '<td class="center" data-f="'.($r['moyenne'] !== null ? round($r['moyenne'], 2) : '').'"><b'.($r['moyenne'] !== null && $r['moyenne'] < $seuil ? ' class="error"' : '').'>'.notes_moy($r['moyenne']).'</b></td>';
		print '<td data-f="'.($mention ? dol_escape_htmltag($mention->ref) : '').'">'.($mention ? dol_escape_htmltag(ecole_label($mention)) : '').'</td>';
		print '<td data-f="'.($dist ? dol_escape_htmltag($dist->ref) : '-').'">'.($dist ? '<span class="badge badge-status4">'.dol_escape_htmltag(ecole_label($dist)).'</span>'.($c && (int) $c->distinction_forcee ? ' '.img_picto($langs->trans('ChoisieParConseil'), 'fa-user-edit') : '') : '').'</td>';
		if ($annee && $regle['decision'] !== 'aucune') {
			$decision = notes_decision($regle, $c, $r['moyenne']);
			print '<td data-f="'.$decision.'">'.($decision !== '' ? '<span class="badge '.($decision === 'admis' ? 'badge-status4' : 'badge-status8').'">'.$langs->trans(notes_decisions()[$decision]).'</span>' : '').'</td>';
		}
		print '<td class="right nowraponall">';
		if ($peutImprimer && $close) {
			print '<a href="'.dol_buildpath('/notes/bulletin.php', 1).'?fk_classe='.$cid.'&periode='.((int) $periode).'&fk_eleve='.$eid.'" target="_blank" title="'.dol_escape_htmltag($langs->trans($annee ? 'ReleveAnnuel' : 'BulletinDeNotes')).'">'.img_picto('', 'fa-file-pdf').'</a>';
		}
		print '</td></tr>';
	}

	// Statistiques de la classe
	foreach (array('moy' => 'MoyenneClasse', 'max' => 'PlusForte', 'min' => 'PlusFaible') as $k => $lib) {
		print '<tr class="liste_total"><td></td><td></td><td>'.$langs->trans($lib).'</td>';
		foreach ($calc['matieres'] as $mid => $m) {
			print '<td class="center">'.notes_moy($calc['stats']['matieres'][$mid][$k]).'</td>';
		}
		print '<td class="center"><b>'.notes_moy($calc['stats']['generale'][$k]).'</b></td><td></td><td></td>'.($annee && $regle['decision'] !== 'aucune' ? '<td></td>' : '').'<td></td></tr>';
	}
	print '</table></div>';
	print '<span class="opacitymedium small">'.$langs->trans($annee ? 'AideResultatsAnnee' : 'AideResultats').'</span>';
}

/**
 * Jeu de données « Tableau des moyennes » d'une classe (export PDF / Excel).
 *
 * @param  DoliDB $db      Handler base
 * @param  object $classe  Classe
 * @param  int    $periode 0 à 3
 * @return array
 */
function notes_dataset_resultats($db, $classe, $periode)
{
	global $langs;
	$calc = notes_calcul($db, (int) $classe->rowid, $periode);
	$conseil = notes_conseil($db, (int) $classe->rowid, $periode);
	$annee = ((int) $periode === 0);
	$headers = array(ecole_pdf_trans($langs, 'Rang'), ecole_pdf_trans($langs, 'Eleve'));
	$ratios = array(0.7, 2.6);
	$aligns = array('C', 'L');
	foreach ($calc['matieres'] as $m) {
		$headers[] = ecole_label($m);
		$ratios[] = 1;
		$aligns[] = 'C';
	}
	array_push($headers, ecole_pdf_trans($langs, $annee ? 'MoyenneAnnuelle' : 'MoyenneGenerale'), ecole_pdf_trans($langs, 'Mention'), ecole_pdf_trans($langs, 'Distinction'));
	array_push($ratios, 1.1, 1.3, 1.4);
	array_push($aligns, 'C', 'C', 'C');
	if ($annee && $calc['regle']['decision'] !== 'aucune') {
		$headers[] = ecole_pdf_trans($langs, 'DecisionFinAnneeCourt');
		$ratios[] = 1.1;
		$aligns[] = 'C';
	}
	$rows = array();
	foreach (notes_ordre_rang($calc) as $eid) {
		$e = $calc['eleves'][$eid];
		$r = $calc['res'][$eid];
		$c = isset($conseil[$eid]) ? $conseil[$eid] : null;
		$row = array(ecole_pdf_text(notes_rang_label($r['rang'], $r['exaequo'])), ecole_label($e));
		foreach ($calc['matieres'] as $mid => $m) {
			$row[] = notes_moy($r['matieres'][$mid]['moyenne']);
		}
		$mention = notes_mention($db, $r['moyenne']);
		$dist = notes_distinction($db, $c, $r['moyenne']);
		array_push($row, notes_moy($r['moyenne']), $mention ? ecole_label($mention) : '', $dist ? ecole_label($dist) : '');
		if ($annee && $calc['regle']['decision'] !== 'aucune') {
			$decision = notes_decision($calc['regle'], $c, $r['moyenne']);
			$row[] = $decision !== '' ? ecole_pdf_trans($langs, notes_decisions()[$decision]) : '';
		}
		$rows[] = $row;
	}
	$row = array('', ecole_pdf_trans($langs, 'MoyenneClasse'));
	foreach ($calc['matieres'] as $mid => $m) {
		$row[] = notes_moy($calc['stats']['matieres'][$mid]['moy']);
	}
	$row[] = notes_moy($calc['stats']['generale']['moy']);
	while (count($row) < count($headers)) {
		$row[] = '';
	}
	$rows[] = $row;
	return array(
		'title' => ecole_pdf_trans($langs, 'TableauMoyennesTitre', $classe->ref),
		'subtitle' => ecole_label($classe).' — '.ecole_pdf_text(notes_periode_label($periode)),
		'ref' => 'RES-'.$classe->ref.'-'.((int) $periode === 0 ? 'A' : 'T'.((int) $periode)),
		'headers' => $headers,
		'ratios' => $ratios,
		'aligns' => $aligns,
		'rows' => $rows,
		'filename' => 'moyennes_'.$classe->ref.'_'.((int) $periode === 0 ? 'annee' : 'T'.((int) $periode)),
	);
}

/* ------------------------------------------------------------------
 * Élèves en difficulté
 * ---------------------------------------------------------------- */

/**
 * Message WhatsApp par défaut (français et arabe) pour un élève en difficulté.
 *
 * @return string
 */
function notes_msg_difficulte_defaut()
{
	return "السلام عليكم،\nنحيطكم علما بأن معدل التلميذ(ة) {eleve_ar} ({classe}) في {periode_ar} هو {moyenne}/20. المواد التي تحتاج إلى دعم : {matieres_ar}.\nنرجو متابعته(ا) عن قرب.\n\nBonjour,\nNous vous informons que la moyenne de {eleve} ({classe}) au {periode} est de {moyenne}/20. Matières à renforcer : {matieres}.\nMerci de suivre son travail de près.\n\n{ecole}";
}

/**
 * Élèves dont la moyenne générale est en dessous du seuil, pour une période (toutes les classes ou une).
 *
 * @param  DoliDB $db        Handler base
 * @param  User   $user      Utilisateur
 * @param  int    $periode   0 à 3
 * @param  float  $seuil     Seuil (sur 20)
 * @param  int    $fk_classe Classe (0 = toutes)
 * @return array[]           Lignes : classe, eleve, res, matieres (libellés sous 10), responsable
 */
function notes_difficultes($db, $user, $periode, $seuil, $fk_classe = 0)
{
	$p = $db->prefix();
	$out = array();
	foreach (notes_classes_resultats($db, $user) as $cid => $classe) {
		if ($fk_classe > 0 && $cid !== (int) $fk_classe) {
			continue;
		}
		$calc = notes_calcul($db, $cid, $periode);
		foreach (notes_ordre_rang($calc) as $eid) {
			$r = $calc['res'][$eid];
			if ($r['moyenne'] === null || round($r['moyenne'], 2) >= $seuil) {
				continue;
			}
			$faibles = array();
			$faiblesAr = array();
			foreach ($calc['matieres'] as $mid => $m) {
				$v = $r['matieres'][$mid]['moyenne'];
				if ($v !== null && $v < 10) {
					$faibles[] = $m->label_fr.' ('.notes_moy($v).')';
					$faiblesAr[] = ($m->label_ar ? $m->label_ar : $m->label_fr).' ('.notes_moy($v).')';
				}
			}
			$resp = null;
			$resql = $db->query("SELECT r.nom_fr, r.nom_ar, r.telephone, r.whatsapp FROM ".$p."ecole_eleve e LEFT JOIN ".$p."ecole_responsable r ON r.rowid = e.fk_responsable WHERE e.rowid = ".((int) $eid));
			if ($resql) {
				$resp = $db->fetch_object($resql);
			}
			$out[] = array('classe' => $classe, 'eleve' => $calc['eleves'][$eid], 'res' => $r, 'nb' => $calc['nb_classes'], 'faibles' => $faibles, 'faibles_ar' => $faiblesAr, 'resp' => $resp);
		}
	}
	return $out;
}

/**
 * Lien WhatsApp vers le responsable d'un élève en difficulté, avec le message prêt.
 *
 * @param  array $l       Ligne de notes_difficultes
 * @param  int   $periode 0 à 3
 * @return string         Adresse, '' sans numéro
 */
function notes_whatsapp_difficulte($l, $periode)
{
	global $mysoc, $conf;
	dol_include_once('/eleves/core/lib/discipline.lib.php');
	if (!$l['resp']) {
		return '';
	}
	$num = eleves_whatsapp_numero((string) $l['resp']->whatsapp, (string) $l['resp']->telephone);
	if ($num === '') {
		return '';
	}
	$ar = new Translate('', $conf);
	$ar->setDefaultLang('ar_SA');
	$ar->loadLangs(array('classes@classes', 'notes@notes'));
	$fr = new Translate('', $conf);
	$fr->setDefaultLang('fr_FR');
	$fr->loadLangs(array('classes@classes', 'notes@notes'));
	$per = function ($t) use ($periode) {
		return ((int) $periode === 0) ? $t->transnoentities('Annee') : $t->transnoentities('Trimestre'.((int) $periode));
	};
	$msg = getDolGlobalString('NOTES_MSG_DIFFICULTE', notes_msg_difficulte_defaut());
	$msg = strtr($msg, array(
		'{eleve}' => $l['eleve']->nom_fr,
		'{eleve_ar}' => $l['eleve']->nom_ar ? $l['eleve']->nom_ar : $l['eleve']->nom_fr,
		'{classe}' => $l['classe']->ref,
		'{periode}' => $per($fr),
		'{periode_ar}' => $per($ar),
		'{moyenne}' => notes_moy($l['res']['moyenne']),
		'{matieres}' => empty($l['faibles']) ? '-' : implode(', ', $l['faibles']),
		'{matieres_ar}' => empty($l['faibles_ar']) ? '-' : implode('، ', $l['faibles_ar']),
		'{ecole}' => ecole_nom_etablissement(false),
		'{ecole_ar}' => ecole_nom_etablissement(true),
	));
	return 'https://wa.me/'.$num.'?text='.rawurlencode($msg);
}

/**
 * Jeu de données « Élèves en difficulté » (export PDF / Excel).
 *
 * @param  DoliDB $db        Handler base
 * @param  User   $user      Utilisateur
 * @param  int    $periode   0 à 3
 * @param  float  $seuil     Seuil
 * @param  int    $fk_classe Classe (0 = toutes)
 * @return array
 */
function notes_dataset_difficultes($db, $user, $periode, $seuil, $fk_classe)
{
	global $langs;
	$rows = array();
	foreach (notes_difficultes($db, $user, $periode, $seuil, $fk_classe) as $l) {
		$rows[] = array($l['classe']->ref, ecole_label($l['eleve']), notes_moy($l['res']['moyenne']), ecole_pdf_text(notes_rang_label($l['res']['rang'], $l['res']['exaequo'])).' / '.$l['nb'],
			implode(', ', $l['faibles']), $l['resp'] ? ecole_label((object) array('nom_fr' => (string) $l['resp']->nom_fr, 'nom_ar' => (string) $l['resp']->nom_ar)) : '', $l['resp'] ? (string) $l['resp']->telephone : '');
	}
	return array(
		'title' => ecole_pdf_trans($langs, 'ElevesEnDifficulte'),
		'subtitle' => ecole_pdf_text(notes_periode_label($periode)).' — '.ecole_pdf_trans($langs, 'MoyenneInferieureA', notes_fmt($seuil)).' — '.ecole_pdf_trans($langs, 'NbEnregistrements', count($rows)),
		'ref' => 'DIFF-'.((int) $periode === 0 ? 'A' : 'T'.((int) $periode)),
		'headers' => array(ecole_pdf_trans($langs, 'Classe'), ecole_pdf_trans($langs, 'Eleve'), ecole_pdf_trans($langs, 'MoyenneGenerale'), ecole_pdf_trans($langs, 'Rang'),
			ecole_pdf_trans($langs, 'MatieresSous10'), ecole_pdf_trans($langs, 'Responsable'), ecole_pdf_trans($langs, 'TelephoneResponsable')),
		'ratios' => array(0.8, 2.2, 1, 1, 3.4, 1.8, 1.3),
		'aligns' => array('C', 'L', 'C', 'C', 'L', 'L', 'C'),
		'rows' => $rows,
		'filename' => 'difficultes_'.((int) $periode === 0 ? 'annee' : 'T'.((int) $periode)),
	);
}
