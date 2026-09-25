<?php
/**
 * Calculs du module Notes : note de devoirs, note du trimestre par matière, moyenne générale, rangs,
 * statistiques de la classe, moyenne annuelle, mentions, distinctions, décisions, conseil de classe.
 * Tout est calculé à la demande à partir des notes saisies et des règles du niveau (rien n'est figé),
 * donc toujours à jour.
 *
 * Toutes les moyennes sont sur 20. Une note saisie sur un autre maximum (ex. 45/50) est ramenée sur 20.
 * Moyenne générale = somme (moyenne × coefficient) ÷ somme des coefficients des matières notées,
 * c'est-à-dire total des points ÷ total des maximums × 20.
 *
 * Fichier : custom/notes/core/lib/calcul.lib.php
 */

dol_include_once('/notes/core/lib/notes.lib.php');
dol_include_once('/notes/class/ecole_bulletin_modele.class.php');

/**
 * Note de devoirs d'un élève (sur 20) : moyenne ou meilleure note des devoirs comptés.
 *
 * @param  array  $codes  Codes des devoirs (fk_evaluation => code)
 * @param  array  $max    Maximum de chaque devoir (fk_evaluation => note_max)
 * @param  string $calcul 'moyenne' | 'meilleure'
 * @param  string $absent 'ignore' | 'zero' (devoir manqué)
 * @return float|null     null = aucun devoir compté
 */
function notes_calc_devoirs($codes, $max, $calcul, $absent)
{
	$vals = array();
	foreach ($codes as $eid => $c) {
		if ($c === '' || $c === NOTES_DISP) {
			continue;
		}
		if ($c === NOTES_ABS) {
			if ($absent === 'zero') {
				$vals[] = 0.0;
			}
			continue;
		}
		$vals[] = (float) $c / max(0.01, (float) $max[$eid]) * 20;
	}
	if (empty($vals)) {
		return null;
	}
	return ($calcul === 'meilleure') ? max($vals) : array_sum($vals) / count($vals);
}

/**
 * Note du trimestre d'une matière (sur 20) à partir de la note de devoirs et de la composition.
 * Une partie manquante (pas de devoir, composition non passée) : la note est faite avec l'autre.
 *
 * @param  float|null $devoirs Note de devoirs
 * @param  float|null $compo   Note de composition
 * @param  array      $regle   Règles du niveau
 * @return float|null
 */
function notes_calc_trimestre_matiere($devoirs, $compo, $regle)
{
	$pd = (float) $regle['poids_devoirs'];
	$pc = (float) $regle['poids_compo'];
	if ($devoirs !== null && $compo !== null && $pd + $pc > 0) {
		return ($devoirs * $pd + $compo * $pc) / ($pd + $pc);
	}
	if ($compo !== null) {
		return $compo;
	}
	return $devoirs;
}

/**
 * Rangs : 1 pour la plus forte valeur. Ex aequo : même rang (le suivant saute des places),
 * départagés par $departage si fourni (valeur plus forte = mieux classé).
 *
 * @param  array<int,float|null> $valeurs   id => valeur (null = non classé)
 * @param  array<int,float|null> $departage id => valeur de départage (facultatif)
 * @return array<int,array{rang:int,exaequo:bool}>
 */
function notes_calc_rangs($valeurs, $departage = array())
{
	$ids = array();
	foreach ($valeurs as $id => $v) {
		if ($v !== null) {
			$ids[] = $id;
		}
	}
	$cle = function ($id) use ($valeurs, $departage) {
		return array(round((float) $valeurs[$id], 4), isset($departage[$id]) && $departage[$id] !== null ? round((float) $departage[$id], 4) : -1.0);
	};
	usort($ids, function ($a, $b) use ($cle) {
		$ka = $cle($a);
		$kb = $cle($b);
		return ($kb[0] <=> $ka[0]) ?: ($kb[1] <=> $ka[1]);
	});
	$out = array();
	$prev = null;
	$rang = 0;
	foreach ($ids as $i => $id) {
		$k = $cle($id);
		if ($prev === null || $k !== $prev) {
			$rang = $i + 1;
		}
		$out[$id] = array('rang' => $rang, 'exaequo' => false);
		$prev = $k;
	}
	// Marque les ex aequo
	$compte = array();
	foreach ($out as $r) {
		$compte[$r['rang']] = isset($compte[$r['rang']]) ? $compte[$r['rang']] + 1 : 1;
	}
	foreach ($out as $id => $r) {
		$out[$id]['exaequo'] = $compte[$r['rang']] > 1;
	}
	return $out;
}

/**
 * Minimum, maximum, moyenne et nombre d'une série (valeurs null ignorées).
 *
 * @param  array $valeurs Valeurs
 * @return array{nb:int,moy:float|null,min:float|null,max:float|null}
 */
function notes_calc_stats($valeurs)
{
	$v = array_values(array_filter($valeurs, function ($x) {
		return $x !== null;
	}));
	if (empty($v)) {
		return array('nb' => 0, 'moy' => null, 'min' => null, 'max' => null);
	}
	return array('nb' => count($v), 'moy' => array_sum($v) / count($v), 'min' => min($v), 'max' => max($v));
}

/**
 * Élèves classés d'une classe : inscrits et suspendus actuellement dans la classe, par numéro d'appel.
 *
 * @param  DoliDB $db        Handler base
 * @param  int    $fk_classe Classe
 * @return array<int,object>
 */
function notes_calc_eleves($db, $fk_classe)
{
	$out = array();
	foreach (notes_eleves($db, $fk_classe, array()) as $eid => $e) {
		if (!$e->ancien) {
			$out[$eid] = $e;
		}
	}
	return $out;
}

/**
 * Résultats d'une classe pour un trimestre.
 *
 * @param  DoliDB $db        Handler base
 * @param  int    $fk_classe Classe
 * @param  int    $trimestre Trimestre (1 à 3)
 * @return array  'regle', 'matieres' (fk_matiere => ligne), 'eleves' (id => élève), 'res' (id => résultats),
 *                'stats' (générale + par matière), 'nb_classes' (élèves classés)
 */
function notes_calcul_trimestre($db, $fk_classe, $trimestre)
{
	static $cache = array();
	$key = ((int) $fk_classe).'|'.((int) $trimestre);
	if (isset($cache[$key])) {
		return $cache[$key];
	}
	$regle = notes_regle_classe($db, $fk_classe);
	$matieres = notes_matieres_classe($db, null, $fk_classe);
	$eleves = notes_calc_eleves($db, $fk_classe);
	$exceptions = notes_exceptions_classe($db, $fk_classe);

	// Évaluations du trimestre par matière
	$evals = array();
	$max = array();
	$resql = $db->query("SELECT rowid, fk_matiere, type, note_max FROM ".$db->prefix()."ecole_evaluation WHERE fk_classe = ".((int) $fk_classe)." AND trimestre = ".((int) $trimestre)." AND status = 1");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$evals[(int) $o->fk_matiere][(int) $o->type][] = (int) $o->rowid;
		$max[(int) $o->rowid] = (float) $o->note_max;
	}
	$valeurs = notes_valeurs($db, array_keys($max));

	$res = array();
	$parMatiere = array();
	foreach ($eleves as $eid => $e) {
		$r = array('matieres' => array(), 'moyenne' => null, 'moy_compo' => null, 'points' => 0.0, 'max' => 0.0, 'coef' => 0.0, 'nb_compos' => 0, 'abs_compos' => 0);
		$sommeCompo = 0.0;
		$coefCompo = 0.0;
		foreach ($matieres as $mid => $m) {
			$calcul = isset($exceptions[$mid]) ? $exceptions[$mid] : $regle['calcul_devoirs'];
			$codesD = array();
			foreach (isset($evals[$mid][NOTES_DEVOIR]) ? $evals[$mid][NOTES_DEVOIR] : array() as $evid) {
				$codesD[$evid] = isset($valeurs[$evid][$eid]) ? $valeurs[$evid][$eid] : '';
			}
			$devoirs = notes_calc_devoirs($codesD, $max, $calcul, $regle['devoir_absent']);
			$compo = null;
			$compoCode = '';
			if (!empty($evals[$mid][NOTES_COMPO])) {
				$cid = $evals[$mid][NOTES_COMPO][0];
				$compoCode = isset($valeurs[$cid][$eid]) ? $valeurs[$cid][$eid] : '';
				$r['nb_compos']++;
				if ($compoCode === NOTES_ABS) {
					$r['abs_compos']++;
					$compo = ($regle['compo_absent'] === 'zero') ? 0.0 : null;
				} elseif ($compoCode !== '' && $compoCode !== NOTES_DISP) {
					$compo = (float) $compoCode / max(0.01, $max[$cid]) * 20;
				}
			}
			$moy = notes_calc_trimestre_matiere($devoirs, $compo, $regle);
			$r['matieres'][$mid] = array('devoirs' => $devoirs, 'compo' => $compo, 'compo_code' => $compoCode, 'moyenne' => $moy, 'rang' => null, 'exaequo' => false);
			$parMatiere[$mid][$eid] = $moy;
			if ($moy !== null) {
				$r['points'] += $moy * (float) $m->coefficient;
				$r['max'] += 20 * (float) $m->coefficient;
				$r['coef'] += (float) $m->coefficient;
			}
			if ($compo !== null) {
				$sommeCompo += $compo * (float) $m->coefficient;
				$coefCompo += (float) $m->coefficient;
			}
		}
		$r['moyenne'] = $r['coef'] > 0 ? $r['points'] / $r['coef'] : null;
		$r['moy_compo'] = $coefCompo > 0 ? $sommeCompo / $coefCompo : null;
		// Absent à toutes les compositions du trimestre (au moins une composition prévue)
		$r['absent_compos'] = ($r['nb_compos'] > 0 && $r['abs_compos'] === $r['nb_compos']);
		$res[$eid] = $r;
	}

	// Rangs généraux et par matière, statistiques de la classe
	$moyennes = array();
	$compos = array();
	foreach ($res as $eid => $r) {
		$moyennes[$eid] = $r['moyenne'];
		$compos[$eid] = $r['moy_compo'];
	}
	foreach (notes_calc_rangs($moyennes, $regle['rang_exaequo'] === 'compo' ? $compos : array()) as $eid => $rg) {
		$res[$eid]['rang'] = $rg['rang'];
		$res[$eid]['exaequo'] = $rg['exaequo'];
	}
	$stats = array('generale' => notes_calc_stats($moyennes), 'matieres' => array());
	foreach ($matieres as $mid => $m) {
		$vals = isset($parMatiere[$mid]) ? $parMatiere[$mid] : array();
		$stats['matieres'][$mid] = notes_calc_stats($vals);
		foreach (notes_calc_rangs($vals) as $eid => $rg) {
			$res[$eid]['matieres'][$mid]['rang'] = $rg['rang'];
			$res[$eid]['matieres'][$mid]['exaequo'] = $rg['exaequo'];
		}
	}
	foreach ($res as $eid => $r) {
		if (!isset($res[$eid]['rang'])) {
			$res[$eid]['rang'] = null;
			$res[$eid]['exaequo'] = false;
		}
	}

	return $cache[$key] = array('regle' => $regle, 'matieres' => $matieres, 'eleves' => $eleves, 'res' => $res, 'stats' => $stats, 'nb_classes' => $stats['generale']['nb'], 'trimestre' => (int) $trimestre);
}

/**
 * Résultats annuels d'une classe : moyenne de chaque trimestre, moyenne annuelle par matière et générale
 * (poids des trimestres du niveau ; un trimestre où l'élève a manqué toutes les compositions peut être exclu).
 *
 * @param  DoliDB $db        Handler base
 * @param  int    $fk_classe Classe
 * @return array  Même forme que notes_calcul_trimestre, avec pour chaque élève 'trimestres' (1..3 => moyenne|null)
 *                et pour chaque matière 't' (1..3 => moyenne|null)
 */
function notes_calcul_annuel($db, $fk_classe)
{
	$regle = notes_regle_classe($db, $fk_classe);
	$trims = array();
	for ($t = 1; $t <= 3; $t++) {
		$trims[$t] = notes_calcul_trimestre($db, $fk_classe, $t);
	}
	$matieres = $trims[1]['matieres'];
	$eleves = $trims[1]['eleves'];
	$poids = array(1 => (float) $regle['poids_t1'], 2 => (float) $regle['poids_t2'], 3 => (float) $regle['poids_t3']);

	$res = array();
	$parMatiere = array();
	foreach ($eleves as $eid => $e) {
		$r = array('matieres' => array(), 'trimestres' => array(), 'exclus' => array(), 'moyenne' => null, 'moy_compo' => null);
		$compte = array();
		for ($t = 1; $t <= 3; $t++) {
			$rt = isset($trims[$t]['res'][$eid]) ? $trims[$t]['res'][$eid] : null;
			$r['trimestres'][$t] = $rt ? $rt['moyenne'] : null;
			$exclu = $rt && $rt['absent_compos'] && $regle['trimestre_sans_compo'] === 'exclure';
			$r['exclus'][$t] = $exclu;
			$compte[$t] = !$exclu && $rt && $rt['moyenne'] !== null && $poids[$t] > 0;
		}
		// Moyenne annuelle générale : moyenne pondérée des trimestres comptés
		$s = 0.0;
		$p = 0.0;
		for ($t = 1; $t <= 3; $t++) {
			if ($compte[$t]) {
				$s += $r['trimestres'][$t] * $poids[$t];
				$p += $poids[$t];
			}
		}
		$r['moyenne'] = $p > 0 ? $s / $p : null;
		// Départage éventuel : moyenne des moyennes de composition des trimestres comptés
		$sc = 0.0;
		$pc = 0.0;
		for ($t = 1; $t <= 3; $t++) {
			$mc = isset($trims[$t]['res'][$eid]) ? $trims[$t]['res'][$eid]['moy_compo'] : null;
			if ($compte[$t] && $mc !== null) {
				$sc += $mc * $poids[$t];
				$pc += $poids[$t];
			}
		}
		$r['moy_compo'] = $pc > 0 ? $sc / $pc : null;
		// Par matière
		foreach ($matieres as $mid => $m) {
			$mt = array();
			$sm = 0.0;
			$pm = 0.0;
			for ($t = 1; $t <= 3; $t++) {
				$v = isset($trims[$t]['res'][$eid]['matieres'][$mid]) ? $trims[$t]['res'][$eid]['matieres'][$mid]['moyenne'] : null;
				$mt[$t] = $v;
				if ($v !== null && !$r['exclus'][$t] && $poids[$t] > 0) {
					$sm += $v * $poids[$t];
					$pm += $poids[$t];
				}
			}
			$moy = $pm > 0 ? $sm / $pm : null;
			$r['matieres'][$mid] = array('t' => $mt, 'moyenne' => $moy, 'rang' => null, 'exaequo' => false);
			$parMatiere[$mid][$eid] = $moy;
		}
		$res[$eid] = $r;
	}

	$moyennes = array();
	$compos = array();
	foreach ($res as $eid => $r) {
		$moyennes[$eid] = $r['moyenne'];
		$compos[$eid] = $r['moy_compo'];
	}
	foreach ($res as $eid => $r) {
		$res[$eid]['rang'] = null;
		$res[$eid]['exaequo'] = false;
	}
	foreach (notes_calc_rangs($moyennes, $regle['rang_exaequo'] === 'compo' ? $compos : array()) as $eid => $rg) {
		$res[$eid]['rang'] = $rg['rang'];
		$res[$eid]['exaequo'] = $rg['exaequo'];
	}
	$stats = array('generale' => notes_calc_stats($moyennes), 'matieres' => array(), 'trimestres' => array());
	foreach ($matieres as $mid => $m) {
		$vals = isset($parMatiere[$mid]) ? $parMatiere[$mid] : array();
		$stats['matieres'][$mid] = notes_calc_stats($vals);
		foreach (notes_calc_rangs($vals) as $eid => $rg) {
			$res[$eid]['matieres'][$mid]['rang'] = $rg['rang'];
			$res[$eid]['matieres'][$mid]['exaequo'] = $rg['exaequo'];
		}
	}
	for ($t = 1; $t <= 3; $t++) {
		$stats['trimestres'][$t] = $trims[$t]['stats']['generale'];
	}
	return array('regle' => $regle, 'matieres' => $matieres, 'eleves' => $eleves, 'res' => $res, 'stats' => $stats, 'nb_classes' => $stats['generale']['nb'], 'trimestre' => 0, 'trims' => $trims);
}

/**
 * Résultats d'une période : trimestre 1 à 3, ou 0 = année.
 *
 * @param  DoliDB $db        Handler base
 * @param  int    $fk_classe Classe
 * @param  int    $periode   0 à 3
 * @return array
 */
function notes_calcul($db, $fk_classe, $periode)
{
	return ((int) $periode === 0) ? notes_calcul_annuel($db, $fk_classe) : notes_calcul_trimestre($db, $fk_classe, $periode);
}

/* ------------------------------------------------------------------
 * Mentions et distinctions
 * ---------------------------------------------------------------- */

/**
 * Mentions actives, de la plus haute à la plus basse.
 *
 * @param  DoliDB $db Handler base
 * @return object[]
 */
function notes_mentions($db)
{
	static $cache = null;
	if ($cache !== null) {
		return $cache;
	}
	$cache = array();
	$resql = $db->query("SELECT rowid, ref, label_fr, label_ar, seuil FROM ".$db->prefix()."ecole_note_mention WHERE entity IN (".getEntity('ecole_note_mention').") AND status = 1 ORDER BY seuil DESC");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$cache[] = $o;
	}
	return $cache;
}

/**
 * Mention d'une moyenne (celle du seuil le plus haut atteint), null si aucune.
 *
 * @param  DoliDB     $db      Handler base
 * @param  float|null $moyenne Moyenne sur 20
 * @return object|null
 */
function notes_mention($db, $moyenne)
{
	if ($moyenne === null) {
		return null;
	}
	foreach (notes_mentions($db) as $m) {
		if (round($moyenne, 2) >= (float) $m->seuil) {
			return $m;
		}
	}
	return null;
}

/**
 * Distinctions actives dans l'ordre : id => ligne.
 *
 * @param  DoliDB $db Handler base
 * @return array<int,object>
 */
function notes_distinctions($db)
{
	static $cache = null;
	if ($cache !== null) {
		return $cache;
	}
	$cache = array();
	$resql = $db->query("SELECT rowid, ref, label_fr, label_ar, seuil_min, seuil_max FROM ".$db->prefix()."ecole_note_distinction WHERE entity IN (".getEntity('ecole_note_distinction').") AND status = 1 ORDER BY position, rowid");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$cache[(int) $o->rowid] = $o;
	}
	return $cache;
}

/**
 * Distinction proposée automatiquement pour une moyenne (première qui convient), null si aucune.
 *
 * @param  DoliDB     $db      Handler base
 * @param  float|null $moyenne Moyenne sur 20
 * @return object|null
 */
function notes_distinction_auto($db, $moyenne)
{
	if ($moyenne === null) {
		return null;
	}
	$m = round($moyenne, 2);
	foreach (notes_distinctions($db) as $d) {
		if (($d->seuil_min === null || $m >= (float) $d->seuil_min) && ($d->seuil_max === null || $m < (float) $d->seuil_max)) {
			return $d;
		}
	}
	return null;
}

/**
 * Choix du conseil de classe (observation, distinction, décision) d'une période : fk_eleve => ligne.
 *
 * @param  DoliDB $db        Handler base
 * @param  int    $fk_classe Classe
 * @param  int    $periode   0 à 3
 * @return array<int,object>
 */
function notes_conseil($db, $fk_classe, $periode)
{
	$out = array();
	$resql = $db->query("SELECT * FROM ".$db->prefix()."ecole_bulletin WHERE fk_classe = ".((int) $fk_classe)." AND trimestre = ".((int) $periode));
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[(int) $o->fk_eleve] = $o;
	}
	return $out;
}

/**
 * Distinction retenue d'un élève : choix du conseil, sinon proposition automatique.
 *
 * @param  DoliDB      $db      Handler base
 * @param  object|null $conseil Ligne du conseil
 * @param  float|null  $moyenne Moyenne
 * @return object|null
 */
function notes_distinction($db, $conseil, $moyenne)
{
	if ($conseil && (int) $conseil->distinction_forcee === 1) {
		$all = notes_distinctions($db);
		if ((int) $conseil->fk_distinction > 0) {
			if (isset($all[(int) $conseil->fk_distinction])) {
				return $all[(int) $conseil->fk_distinction];
			}
			// distinction désactivée depuis : on la relit quand même
			$resql = $db->query("SELECT rowid, ref, label_fr, label_ar, seuil_min, seuil_max FROM ".$db->prefix()."ecole_note_distinction WHERE rowid = ".((int) $conseil->fk_distinction));
			$o = $resql ? $db->fetch_object($resql) : null;
			return $o ? $o : null;
		}
		return null;
	}
	return notes_distinction_auto($db, $moyenne);
}

/**
 * Décisions de fin d'année possibles : code => clé de traduction.
 *
 * @return array<string,string>
 */
function notes_decisions()
{
	return array('admis' => 'DecisionAdmis', 'redouble' => 'DecisionRedouble');
}

/**
 * Décision de fin d'année retenue : choix du conseil, sinon proposition selon le seuil (mode « auto »).
 *
 * @param  array       $regle   Règles du niveau
 * @param  object|null $conseil Ligne du conseil (période 0)
 * @param  float|null  $moyenne Moyenne annuelle
 * @return string               '' | 'admis' | 'redouble'
 */
function notes_decision($regle, $conseil, $moyenne)
{
	if ($regle['decision'] === 'aucune') {
		return '';
	}
	if ($conseil && !empty($conseil->decision) && isset(notes_decisions()[$conseil->decision])) {
		return (string) $conseil->decision;
	}
	if ($regle['decision'] === 'auto' && $moyenne !== null) {
		return (round($moyenne, 2) >= (float) $regle['seuil_passage']) ? 'admis' : 'redouble';
	}
	return '';
}

/**
 * Enregistre le choix du conseil de classe pour un élève et une période.
 *
 * @param  DoliDB      $db            Handler base
 * @param  User        $user          Utilisateur
 * @param  int         $fk_eleve      Élève
 * @param  int         $fk_classe     Classe
 * @param  int         $periode       0 à 3
 * @param  string      $observation   Observation de la direction
 * @param  string      $distinction   'auto' | '' (aucune) | id de distinction
 * @param  string|null $decision      Décision (période 0) : '' = automatique, 'admis', 'redouble' ; null = inchangée
 * @return int                        1 si OK, -1 sinon
 */
function notes_conseil_enregistrer($db, $user, $fk_eleve, $fk_classe, $periode, $observation, $distinction, $decision = null)
{
	global $conf;
	$forcee = ($distinction === 'auto') ? 0 : 1;
	$fkd = ($forcee && (int) $distinction > 0) ? (string) ((int) $distinction) : 'NULL';
	$obs = trim((string) $observation);
	$obsSql = $obs !== '' ? "'".$db->escape(dol_trunc($obs, 2000, 'right', 'UTF-8', 1))."'" : 'NULL';
	$dec = 'NULL';
	if ($decision !== null && isset(notes_decisions()[$decision])) {
		$dec = "'".$db->escape($decision)."'";
	}
	$sql = "INSERT INTO ".$db->prefix()."ecole_bulletin (entity, fk_eleve, fk_classe, trimestre, observation, distinction_forcee, fk_distinction, decision, date_creation, fk_user_creat)";
	$sql .= " VALUES (".((int) $conf->entity).", ".((int) $fk_eleve).", ".((int) $fk_classe).", ".((int) $periode).", ".$obsSql.", ".$forcee.", ".$fkd.", ".$dec.", '".$db->idate(dol_now())."', ".((int) $user->id).")";
	$sql .= " ON DUPLICATE KEY UPDATE fk_classe = ".((int) $fk_classe).", observation = ".$obsSql.", distinction_forcee = ".$forcee.", fk_distinction = ".$fkd;
	if ($decision !== null) {
		$sql .= ", decision = ".$dec;
	}
	$sql .= ", fk_user_modif = ".((int) $user->id);
	return $db->query($sql) ? 1 : -1;
}

/* ------------------------------------------------------------------
 * Affichage
 * ---------------------------------------------------------------- */

/**
 * Moyenne sur 20 avec 2 décimales (« 12,45 »), « — » si vide.
 *
 * @param  float|null $v Valeur
 * @return string
 */
function notes_moy($v)
{
	global $langs;
	if ($v === null) {
		return '—';
	}
	$s = number_format(round((float) $v, 2), 2, '.', '');
	$sep = $langs->transnoentitiesnoconv('SeparatorDecimal');
	return ($sep && $sep !== 'SeparatorDecimal') ? str_replace('.', $sep, $s) : $s;
}

/**
 * Rang affiché : « 3e », « 1er », « 3e ex æquo » (selon la langue).
 *
 * @param  int|null  $rang    Rang
 * @param  bool      $exaequo Ex aequo
 * @param  Translate $l       Traductions (facultatif)
 * @return string
 */
function notes_rang_label($rang, $exaequo = false, $l = null)
{
	global $langs;
	$l = $l ? $l : $langs;
	if ($rang === null) {
		return '—';
	}
	$txt = ((int) $rang === 1) ? $l->transnoentities('RangPremier') : $l->transnoentities('RangNieme', (int) $rang);
	return $txt.($exaequo ? ' '.$l->transnoentities('ExAequo') : '');
}

/**
 * Libellé de période : « 1er trimestre »... ou « Année ».
 *
 * @param  int $p 0 à 3
 * @return string
 */
function notes_periode_label($p)
{
	global $langs;
	return ((int) $p === 0) ? $langs->trans('Annee') : notes_trimestre_label($p);
}

/**
 * La période est-elle clôturée pour la classe ? L'année l'est quand les trois trimestres le sont.
 *
 * @param  DoliDB $db        Handler base
 * @param  int    $fk_classe Classe
 * @param  int    $periode   0 à 3
 * @return bool
 */
function notes_periode_close($db, $fk_classe, $periode)
{
	if ((int) $periode > 0) {
		return notes_est_cloture($db, $fk_classe, $periode);
	}
	for ($t = 1; $t <= 3; $t++) {
		if (!notes_est_cloture($db, $fk_classe, $t)) {
			return false;
		}
	}
	return true;
}

/**
 * Modèle de bulletin à utiliser : celui demandé, sinon celui du niveau de la classe, sinon le premier actif.
 *
 * @param  DoliDB $db        Handler base
 * @param  int    $fk_classe Classe
 * @param  int    $fk_modele Modèle demandé (0 = celui du niveau)
 * @return EcoleBulletinModele|null
 */
function notes_modele_pour($db, $fk_classe, $fk_modele = 0)
{
	$ids = array();
	if ($fk_modele > 0) {
		$ids[] = (int) $fk_modele;
	}
	$regle = notes_regle_classe($db, $fk_classe);
	if (!empty($regle['fk_modele'])) {
		$ids[] = (int) $regle['fk_modele'];
	}
	$ids = array_merge($ids, array_keys(EcoleBulletinModele::choix($db)));
	foreach ($ids as $id) {
		$m = new EcoleBulletinModele($db);
		if ($m->fetch($id) > 0) {
			return $m;
		}
	}
	return null;
}
