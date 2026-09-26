<?php
/**
 * Passage à l'année scolaire suivante :
 *  - classe suivante de chaque classe (proposée, corrigée par la direction) ;
 *  - proposition pour chaque élève d'après la décision de fin d'année du conseil (admis : classe suivante,
 *    redouble : même classe, dernière classe ou sans suite : ne revient pas) ;
 *  - exécution : dossier de l'année gardé (classe, réglages, montants, arriéré), élèves repris en « ancien attendu »
 *    avec la classe proposée, les autres archivés ; tarifs et emploi du temps gardés ; tarifs préparés appliqués ;
 *    nouvelle année active.
 *  - « vider les attendus » : les anciens attendus qui ne sont pas revenus sont archivés.
 *
 * Fichier : custom/eleves/core/lib/passage.lib.php
 */

dol_include_once('/eleves/class/ecole_eleve.class.php');
dol_include_once('/eleves/core/lib/paiements.lib.php');

/**
 * Classes actives dans l'ordre des niveaux : id => ligne (rowid, ref, label_fr, label_ar, fk_niveau, nlabel_fr, nlabel_ar).
 *
 * @param  DoliDB $db Handler base
 * @return array<int,object>
 */
function passage_classes($db)
{
	$p = $db->prefix();
	$sql = "SELECT c.rowid, c.ref, c.label_fr, c.label_ar, c.fk_niveau, n.label_fr as nlabel_fr, n.label_ar as nlabel_ar";
	$sql .= " FROM ".$p."ecole_classe c LEFT JOIN ".$p."ecole_niveau n ON n.rowid = c.fk_niveau";
	$sql .= " WHERE c.entity IN (".getEntity('ecole_classe').") AND c.status = 1 ORDER BY n.position, c.ref, c.rowid";
	$out = array();
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[(int) $o->rowid] = $o;
	}
	return $out;
}

/**
 * Classe suivante proposée pour chaque classe : la suivante dans l'ordre (niveau, référence), 0 pour la dernière
 * (fin de cycle). Un choix déjà fait (POST 'suivante') est gardé.
 *
 * @param  array<int,object> $classes Classes
 * @return array<int,int>             fk_classe => classe suivante (0 = fin de cycle)
 */
function passage_suivantes($classes)
{
	$out = array();
	$ids = array_keys($classes);
	$posted = GETPOST('suivante', 'array');
	foreach ($ids as $i => $cid) {
		$def = isset($ids[$i + 1]) ? (int) $ids[$i + 1] : 0;
		if (isset($posted[$cid]) && ((int) $posted[$cid] === 0 || isset($classes[(int) $posted[$cid]]))) {
			$def = (int) $posted[$cid];
		}
		$out[$cid] = $def;
	}
	return $out;
}

/**
 * Élèves de l'année (inscrits et suspendus) par classe, avec la décision de fin d'année et la proposition.
 *
 * @param  DoliDB            $db        Handler base
 * @param  array<int,object> $classes   Classes
 * @param  array<int,int>    $suivantes Classes suivantes
 * @return array<int,object[]>          fk_classe => élèves (rowid, ref, nom_fr, nom_ar, numero_appel, status, decision, moyenne, cible)
 */
function passage_eleves($db, $classes, $suivantes)
{
	$out = array();
	$notes = isModEnabled('notes') && is_readable(dol_buildpath('/notes/core/lib/calcul.lib.php'));
	if ($notes) {
		dol_include_once('/notes/core/lib/notes.lib.php');
		dol_include_once('/notes/core/lib/calcul.lib.php');
	}
	$posted = GETPOST('cible', 'array');
	$sql = "SELECT rowid, ref, nom_fr, nom_ar, numero_appel, status, fk_classe FROM ".$db->prefix()."ecole_eleve";
	$sql .= " WHERE entity IN (".getEntity('ecole_eleve').") AND status IN (".implode(',', EcoleEleve::statusOccupantPlace()).") ORDER BY numero_appel, nom_fr";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[(int) $o->fk_classe][] = $o;
	}
	foreach ($out as $cid => $liste) {
		$calc = null;
		$conseil = array();
		if ($notes && isset($classes[$cid])) {
			$calc = notes_calcul($db, $cid, 0);
			$conseil = notes_conseil($db, $cid, 0);
		}
		foreach ($liste as $k => $e) {
			$eid = (int) $e->rowid;
			$e->moyenne = ($calc && isset($calc['res'][$eid])) ? $calc['res'][$eid]['moyenne'] : null;
			$e->decision = $calc ? notes_decision($calc['regle'], isset($conseil[$eid]) ? $conseil[$eid] : null, $e->moyenne) : '';
			if ($e->decision === 'redouble') {
				$cible = $cid;
			} else {
				$cible = isset($suivantes[$cid]) ? (int) $suivantes[$cid] : 0;
			}
			if (isset($posted[$eid]) && ((int) $posted[$eid] === 0 || isset($classes[(int) $posted[$eid]]))) {
				$cible = (int) $posted[$eid];
			}
			$e->cible = $cible;
			$out[$cid][$k] = $e;
		}
	}
	return $out;
}

/**
 * Points à vérifier avant le passage : trimestres non clôturés, élèves sans décision, tarifs non préparés,
 * pré-inscriptions en cours.
 *
 * @param  DoliDB              $db      Handler base
 * @param  array<int,object>   $classes Classes
 * @param  array<int,object[]> $eleves  Élèves par classe (passage_eleves)
 * @return array{avertissements:string[],infos:string[]}
 */
function passage_verifications($db, $classes, $eleves)
{
	global $langs;
	$av = array();
	$infos = array();
	$p = $db->prefix();
	if (isModEnabled('notes') && function_exists('notes_est_cloture')) {
		$non = array();
		foreach ($classes as $cid => $c) {
			if (!empty($eleves[$cid]) && !notes_est_cloture($db, $cid, 3)) {
				$non[] = $c->ref;
			}
		}
		if ($non) {
			$av[] = $langs->trans('PassageTrimestresNonClotures', implode(', ', $non));
		}
		$sans = 0;
		foreach ($eleves as $liste) {
			foreach ($liste as $e) {
				if ($e->decision === '') {
					$sans++;
				}
			}
		}
		if ($sans) {
			$av[] = $langs->trans('PassageSansDecision', $sans);
		}
	}
	$prep = ecole_tarifs_annee($db, ecole_annee_active() + 1);
	if (empty($prep)) {
		$infos[] = $langs->trans('PassageTarifsNonPrepares', ecole_annee_label(ecole_annee_active() + 1));
	} else {
		$infos[] = $langs->trans('PassageTarifsPrepares', count($prep), ecole_annee_label(ecole_annee_active() + 1));
	}
	$resql = $db->query("SELECT COUNT(*) as nb FROM ".$p."ecole_eleve WHERE entity IN (".getEntity('ecole_eleve').") AND status IN (".EcoleEleve::STATUS_PREINSCRIT.",".EcoleEleve::STATUS_ATTENTE.")");
	$o = $resql ? $db->fetch_object($resql) : null;
	if ($o && (int) $o->nb > 0) {
		$infos[] = $langs->trans('PassagePreinscrits', (int) $o->nb);
	}
	$resql = $db->query("SELECT COUNT(*) as nb FROM ".$p."ecole_eleve WHERE entity IN (".getEntity('ecole_eleve').") AND status = ".EcoleEleve::STATUS_ANCIEN_ATTENDU);
	$o = $resql ? $db->fetch_object($resql) : null;
	if ($o && (int) $o->nb > 0) {
		$infos[] = $langs->trans('PassageAnciensNonRevenus', (int) $o->nb);
	}
	return array('avertissements' => $av, 'infos' => $infos);
}

/**
 * Garde le dossier de l'année d'un élève (classe, réglages, montants, arriéré).
 *
 * @param  DoliDB     $db       Handler base
 * @param  User       $user     Utilisateur
 * @param  EcoleEleve $e        Élève
 * @param  array      $s        Situation (eleves_situations, calculée à la fin de l'année)
 * @param  int        $annee    Année scolaire
 * @param  string     $decision admis | redouble | non_repris | sorti
 * @param  int        $suivante Classe proposée pour l'année suivante
 * @return bool
 */
function passage_garder_dossier($db, $user, $e, $s, $annee, $decision, $suivante)
{
	global $conf;
	$n = function ($v) use ($db) {
		return ($v === null || $v === '') ? 'NULL' : "'".$db->escape((string) $v)."'";
	};
	$f = function ($v) {
		return ($v === null || $v === '') ? 'NULL' : (string) ((float) $v);
	};
	// Arriéré de l'année : ce qui reste impayé à la fin de l'année, hors arriérés des années d'avant
	$arriere = max(0, (float) price2num($s['impaye'] - $s['arriere_reste'], 'MT'));
	$sql = "INSERT INTO ".$db->prefix()."ecole_eleve_annee (entity, annee, fk_eleve, fk_classe, numero_appel, status, decision, fk_classe_suivante, date_inscription, mois_debut,";
	$sql .= " reduction_type, reduction_valeur, reduction_motif, frais_inscription_du, exo_type, exo_valeur, fk_motif_exo, total_du, total_paye, arriere, date_creation, fk_user_creat)";
	$sql .= " VALUES (".((int) $conf->entity).", ".((int) $annee).", ".((int) $e->id).", ".((int) $e->fk_classe).", ".($e->numero_appel ? (int) $e->numero_appel : 'NULL').", ".((int) $e->status);
	$sql .= ", ".$n($decision).", ".($suivante > 0 ? (int) $suivante : 'NULL').", ".($e->date_inscription ? "'".$db->idate($e->date_inscription)."'" : 'NULL').", ".$n($e->mois_debut);
	$sql .= ", ".$n($e->reduction_type).", ".$f($e->reduction_valeur).", ".$n($e->reduction_motif).", ".$f($e->frais_inscription_du).", ".$n($e->exo_type).", ".$f($e->exo_valeur).", ".((int) $e->fk_motif_exo > 0 ? (int) $e->fk_motif_exo : 'NULL');
	$sql .= ", ".((float) $s['total_du']).", ".((float) $s['total_paye']).", ".$arriere.", '".$db->idate(dol_now())."', ".((int) $user->id).")";
	$sql .= " ON DUPLICATE KEY UPDATE arriere = VALUES(arriere), decision = VALUES(decision), fk_classe_suivante = VALUES(fk_classe_suivante)";
	return (bool) $db->query($sql);
}

/**
 * Passe à l'année scolaire suivante.
 *
 * @param  DoliDB         $db       Handler base
 * @param  User           $user     Utilisateur
 * @param  array<int,int> $cibles   fk_eleve => classe de la rentrée (0 = ne revient pas), pour les inscrits et suspendus
 * @param  string         $error    Message d'erreur (sortie)
 * @return array|int                Bilan (repris, archives, arrieres, montant) ou -1
 */
function passage_executer($db, $user, $cibles, &$error)
{
	global $conf, $langs;
	require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
	$p = $db->prefix();
	$annee = ecole_annee_active();
	$suivante = $annee + 1;
	list($debut, $fin) = ecole_annee_bornes($annee);
	$finTs = dol_mktime(23, 59, 59, 7, 31, $suivante);
	$date = min(dol_now(), dol_mktime(12, 0, 0, 7, 31, $suivante));
	$motif = $langs->transnoentities('MotifPassage', ecole_annee_label($annee), ecole_annee_label($suivante));
	$classes = passage_classes($db);
	$bilan = array('repris' => 0, 'archives' => 0, 'arrieres' => 0, 'montant' => 0.0);

	// Élèves concernés : inscrits, suspendus, sortis, anciens attendus de l'an dernier (jamais revenus)
	$statuts = array_merge(EcoleEleve::statusOccupantPlace(), EcoleEleve::statusSortis(), array(EcoleEleve::STATUS_ANCIEN_ATTENDU));
	$obj = new EcoleEleve($db);
	$eleves = $obj->fetchAllObjects('t.rowid', 'ASC', 0, 0, array(), 't.status IN ('.implode(',', $statuts).')');
	$eleves = is_array($eleves) ? $eleves : array();

	$db->begin();
	$ok = ecole_annee_archiver($db, $user, $annee) > 0;
	// Situations à la fin de l'année : tous les mois sont échus
	$situations = $ok ? eleves_situations($db, $eleves, $finTs) : array();
	foreach ($eleves as $e) {
		if (!$ok) {
			break;
		}
		$st = (int) $e->status;
		$s = $situations[(int) $e->id];
		$pendant = false;
		if (in_array($st, EcoleEleve::statusOccupantPlace(), true)) {
			$cible = isset($cibles[(int) $e->id]) ? (int) $cibles[(int) $e->id] : 0;
			if ($cible > 0 && !isset($classes[$cible])) {
				$cible = 0;
			}
			$decision = $cible <= 0 ? 'non_repris' : ($cible === (int) $e->fk_classe ? 'redouble' : 'admis');
			$ok = passage_garder_dossier($db, $user, $e, $s, $annee, $decision, $cible);
			if ($ok) {
				$ok = $e->passerAnnee($user, $cible > 0 ? EcoleEleve::STATUS_ANCIEN_ATTENDU : EcoleEleve::STATUS_ARCHIVE, $cible, $date, $motif) > 0;
				$bilan[$cible > 0 ? 'repris' : 'archives']++;
			}
		} else {
			// Sortis pendant l'année : dossier gardé (arriéré éventuel) ; sortis avant ou anciens non revenus : seulement archivés
			$pendant = in_array($st, EcoleEleve::statusSortis(), true) && (empty($e->date_statut) || dol_print_date($e->date_statut, '%Y-%m-%d') >= $debut);
			if ($pendant) {
				$ok = passage_garder_dossier($db, $user, $e, $s, $annee, 'sorti', 0);
			}
			if ($ok) {
				$ok = $e->passerAnnee($user, EcoleEleve::STATUS_ARCHIVE, 0, $date, $motif) > 0;
				$bilan['archives']++;
			}
		}
		if ($ok && ($s['impaye'] - $s['arriere_reste']) > 0.001 && (in_array($st, EcoleEleve::statusOccupantPlace(), true) || $pendant)) {
			$bilan['arrieres']++;
			$bilan['montant'] += (float) price2num($s['impaye'] - $s['arriere_reste'], 'MT');
		}
		if (!$ok && empty($error)) {
			$error = $e->error ? $e->error : $db->lasterror();
		}
	}
	// Pré-inscrits de la rentrée : leurs frais d'inscription déjà payés comptent pour la nouvelle année
	if ($ok) {
		$ok = (bool) $db->query("UPDATE ".$p."ecole_paiement l INNER JOIN ".$p."ecole_eleve e ON e.rowid = l.fk_eleve SET l.annee = ".$suivante
			." WHERE l.type = 'inscription' AND l.annee = ".$annee." AND e.status IN (".EcoleEleve::STATUS_PREINSCRIT.",".EcoleEleve::STATUS_ATTENTE.")");
	}
	// Tarifs préparés pour la nouvelle année
	if ($ok) {
		$ok = (bool) $db->query("UPDATE ".$p."ecole_classe c INNER JOIN ".$p."ecole_classe_tarif t ON t.fk_classe = c.rowid AND t.annee = ".$suivante
			." SET c.mensualite = t.mensualite, c.frais_inscription = t.frais_inscription, c.fk_user_modif = ".((int) $user->id));
	}
	// Nouvelle année active
	if ($ok) {
		$ok = dolibarr_set_const($db, 'ELEVES_ANNEE_SCOLAIRE', $suivante, 'chaine', 0, '', $conf->entity) > 0;
	}
	if (!$ok) {
		if (empty($error)) {
			$error = $db->lasterror();
		}
		$db->rollback();
		return -1;
	}
	$db->commit();
	$conf->global->ELEVES_ANNEE_SCOLAIRE = $suivante;
	unset($_SESSION['ecole_annee_vue']);
	$bilan['montant'] = (float) price2num($bilan['montant'], 'MT');
	return $bilan;
}

/**
 * « Vider les attendus » : les anciens élèves attendus qui ne sont pas revenus sont archivés
 * (cachés des listes, dossier, historique et matricule gardés).
 *
 * @param  DoliDB $db    Handler base
 * @param  User   $user  Utilisateur
 * @return int           Nombre d'élèves archivés, -1 si erreur
 */
function passage_vider_attendus($db, $user)
{
	global $langs;
	$obj = new EcoleEleve($db);
	$list = $obj->fetchAllObjects('t.rowid', 'ASC', 0, 0, array(), 't.status = '.EcoleEleve::STATUS_ANCIEN_ATTENDU);
	$list = is_array($list) ? $list : array();
	$db->begin();
	$n = 0;
	foreach ($list as $e) {
		if ($e->passerAnnee($user, EcoleEleve::STATUS_ARCHIVE, 0, dol_now(), $langs->transnoentities('MotifViderAttendus')) < 0) {
			$db->rollback();
			return -1;
		}
		$n++;
	}
	$db->commit();
	return $n;
}
