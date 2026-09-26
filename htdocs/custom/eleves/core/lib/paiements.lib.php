<?php
/**
 * Paiements des élèves : année scolaire, mois payants, calcul de ce que chaque élève doit
 * (frais d'inscription, mensualités après réduction), de ce qu'il a payé et de ses impayés.
 *
 * Vocabulaire : côté école on parle de frais d'inscription, mensualités, autres frais, paiements
 * et reçus. Les factures et « clients » Dolibarr ne servent qu'en coulisses (comptabilité).
 *
 * Fichier : custom/eleves/core/lib/paiements.lib.php
 */

/**
 * Année scolaire en cours (année de la rentrée : 2026 = 2026-2027). Réglage ELEVES_ANNEE_SCOLAIRE,
 * sinon calculée : à partir d'août, l'année en cours, avant, l'année précédente.
 *
 * @return int
 */
function eleves_annee_scolaire()
{
	$a = getDolGlobalInt('ELEVES_ANNEE_SCOLAIRE');
	if ($a > 2000) {
		return $a;
	}
	return ((int) date('n') >= 8) ? (int) date('Y') : (int) date('Y') - 1;
}

/**
 * Libellé d'une année scolaire : « 2026-2027 ».
 *
 * @param  int $annee Année de la rentrée
 * @return string
 */
function eleves_annee_label($annee)
{
	return $annee.'-'.($annee + 1);
}

/**
 * Mois de l'année scolaire dans l'ordre (août → juillet) : numéro du mois.
 *
 * @return int[]
 */
function eleves_mois_ordre()
{
	return array(8, 9, 10, 11, 12, 1, 2, 3, 4, 5, 6, 7);
}

/**
 * Mois payants de l'année scolaire, dans l'ordre : 'AAAA-MM' (réglage ELEVES_MOIS_PAYANTS, défaut octobre → juin).
 *
 * @return string[]
 */
function eleves_periodes()
{
	$annee = eleves_annee_scolaire();
	$mois = array_map('intval', explode(',', getDolGlobalString('ELEVES_MOIS_PAYANTS', '10,11,12,1,2,3,4,5,6')));
	$out = array();
	foreach (eleves_mois_ordre() as $m) {
		if (in_array($m, $mois, true)) {
			$out[] = sprintf('%04d-%02d', $m >= 8 ? $annee : $annee + 1, $m);
		}
	}
	return $out;
}

/**
 * Premier mois payant à partir d'un mois donné : jamais avant le premier mois payant de la configuration
 * (un élève inscrit en septembre commence en octobre si octobre est le premier mois payant).
 *
 * @param  string $depuis Mois AAAA-MM ('' = depuis le début de l'année)
 * @return string         Mois AAAA-MM, '' s'il n'y a plus de mois payant (inscrit après le dernier)
 */
function eleves_premier_mois_payant($depuis)
{
	foreach (eleves_periodes() as $per) {
		if ($depuis === '' || $per >= $depuis) {
			return $per;
		}
	}
	return '';
}

/**
 * Premier mois dû d'un élève : le mois choisi sur sa fiche, sinon le mois de son inscription, mais toujours
 * un mois payant de la configuration (voir eleves_premier_mois_payant).
 *
 * @param  EcoleEleve $e Élève
 * @return string        Mois AAAA-MM, '' si aucun mois n'est dû cette année
 */
function eleves_premier_mois_du($e)
{
	$debut = !empty($e->mois_debut) ? $e->mois_debut : ($e->date_inscription ? eleves_periode_of($e->date_inscription) : '');
	return eleves_premier_mois_payant($debut);
}

/**
 * Libellé d'un mois : « Octobre 2026 » (dans la langue de l'utilisateur).
 *
 * @param  string $periode AAAA-MM
 * @return string
 */
function eleves_periode_label($periode)
{
	global $langs;
	$p = explode('-', (string) $periode);
	if (count($p) !== 2) {
		return (string) $periode;
	}
	return $langs->transnoentitiesnoconv('Month'.sprintf('%02d', (int) $p[1])).' '.$p[0];
}

/**
 * Période (AAAA-MM) d'une date.
 *
 * @param  int $ts Horodatage
 * @return string
 */
function eleves_periode_of($ts)
{
	return dol_print_date($ts, '%Y-%m');
}

/**
 * Dernier moment pour payer un mois sans être en retard : jour limite (ELEVES_JOUR_LIMITE, défaut 10) à 23:59.
 *
 * @param  string $periode AAAA-MM
 * @return int
 */
function eleves_periode_limite($periode)
{
	$p = explode('-', $periode);
	$jour = min(28, max(1, getDolGlobalInt('ELEVES_JOUR_LIMITE', 10)));
	return dol_mktime(23, 59, 59, (int) $p[1], $jour, (int) $p[0]);
}

/**
 * Montant avec la devise.
 *
 * @param  float $v Montant
 * @return string
 */
function eleves_montant($v)
{
	global $conf, $langs;
	return price(price2num((float) $v, 'MT'), 0, $langs, 1, -1, -1, $conf->currency);
}

/**
 * Situation financière de plusieurs élèves (calculée en quelques requêtes).
 *
 * Pour chaque élève :
 *  - actif : ses mensualités comptent dans les impayés (inscrit, suspendu, ou sorti : jusqu'à son départ) ;
 *  - inscription : du / paye / reste ;
 *  - mois : période => du (mensualité de sa classe ce mois-là, réduction déduite), paye, reste, echu, etat
 *           (paye, partiel, impaye, a_venir, gratuit) ;
 *  - impaye : total en retard (inscription + mois échus non soldés), impaye_mois : périodes en retard ;
 *  - total_du, total_paye, reste (année entière), dernier : dernier reçu valide (id, ref, date).
 *
 * @param  DoliDB       $db      Handler base
 * @param  EcoleEleve[] $eleves  Élèves (objets chargés)
 * @param  int          $now     Date de référence (défaut : maintenant)
 * @return array<int,array>
 */
function eleves_situations($db, $eleves, $now = 0)
{
	$now = $now ? $now : dol_now();
	$out = array();
	if (empty($eleves)) {
		return $out;
	}
	$ids = array();
	foreach ($eleves as $e) {
		$ids[] = (int) $e->id;
	}
	$in = implode(',', $ids);
	$p = $db->prefix();

	// Tarifs des classes
	$classes = array();
	$resql = $db->query("SELECT rowid, mensualite, frais_inscription FROM ".$p."ecole_classe");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$classes[(int) $o->rowid] = array('mensualite' => (float) $o->mensualite, 'frais' => (float) $o->frais_inscription);
	}
	// Historique des classes
	$hist = array();
	$resql = $db->query("SELECT fk_eleve, fk_classe, date_debut, date_fin FROM ".$p."ecole_eleve_classe WHERE fk_eleve IN (".$in.") ORDER BY date_debut, rowid");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$hist[(int) $o->fk_eleve][] = array('classe' => (int) $o->fk_classe, 'debut' => substr($o->date_debut, 0, 10), 'fin' => $o->date_fin ? substr($o->date_fin, 0, 10) : null);
	}
	// Montants payés (reçus valides)
	$paye = array();
	$resql = $db->query("SELECT fk_eleve, type, periode, SUM(montant) as total FROM ".$p."ecole_paiement WHERE status = 1 AND fk_eleve IN (".$in.") AND type IN ('inscription', 'mensualite') GROUP BY fk_eleve, type, periode");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$k = ($o->type === 'inscription') ? 'inscription' : (string) $o->periode;
		$paye[(int) $o->fk_eleve][$k] = (float) $o->total;
	}
	// Dernier reçu valide
	$dernier = array();
	$sql = "SELECT l.fk_eleve, r.rowid, r.ref, r.date_recu FROM ".$p."ecole_paiement l INNER JOIN ".$p."ecole_recu r ON r.rowid = l.fk_recu";
	$sql .= " WHERE r.status = 1 AND l.fk_eleve IN (".$in.") ORDER BY r.date_recu, r.rowid";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$dernier[(int) $o->fk_eleve] = array('id' => (int) $o->rowid, 'ref' => $o->ref, 'date' => $db->jdate($o->date_recu));
	}

	$periodes = eleves_periodes();
	$occupe = EcoleEleve::statusOccupantPlace();
	$sortis = EcoleEleve::statusSortis();

	foreach ($eleves as $e) {
		$id = (int) $e->id;
		$st = (int) $e->status;
		$h = isset($hist[$id]) ? $hist[$id] : array();
		$pay = isset($paye[$id]) ? $paye[$id] : array();

		// Frais d'inscription : montant fixé sur la fiche, sinon celui de la classe d'inscription
		// (avant l'inscription : la classe demandée, qui peut encore changer)
		$avant = in_array($st, array(EcoleEleve::STATUS_PREINSCRIT, EcoleEleve::STATUS_ATTENTE), true);
		$classeInscr = (!$avant && !empty($h)) ? $h[0]['classe'] : (int) $e->fk_classe;
		$fraisBrut = ($e->frais_inscription_du !== null && $e->frais_inscription_du !== '') ? (float) $e->frais_inscription_du
			: (isset($classes[$classeInscr]) ? $classes[$classeInscr]['frais'] : 0);
		// Exonération (réinscription, bourse...) : le montant exonéré n'est ni dû ni compté comme impayé
		$fraisExo = method_exists($e, 'montantExonere') ? $e->montantExonere($fraisBrut) : 0;
		$fraisDu = (float) price2num(max(0, $fraisBrut - $fraisExo), 'MT');
		$fraisPaye = isset($pay['inscription']) ? $pay['inscription'] : 0;

		// Mois dus : du premier mois (réglé ou mois d'inscription) au départ éventuel
		$debut = !empty($e->mois_debut) ? $e->mois_debut : ($e->date_inscription ? eleves_periode_of($e->date_inscription) : '');
		$fin = (in_array($st, $sortis, true) && $e->date_statut) ? eleves_periode_of($e->date_statut) : null;
		$actif = in_array($st, $occupe, true) || in_array($st, $sortis, true);

		$mois = array();
		$liste = $periodes;
		foreach (array_keys($pay) as $k) {
			if ($k !== 'inscription' && !in_array($k, $liste, true)) {
				$liste[] = $k; // mois payé hors des mois payants (ex. réglage modifié) : gardé pour l'historique
			}
		}
		sort($liste);
		$impaye = 0.0;
		$impayeMois = array();
		$totalDu = $fraisDu;
		$totalPaye = $fraisPaye;
		foreach ($liste as $per) {
			$dans = in_array($per, $periodes, true) && ($debut === '' || $per >= $debut) && ($fin === null || $per <= $fin);
			$du = 0.0;
			if ($dans) {
				$classe = eleves_classe_du_mois($h, $per, (int) $e->fk_classe);
				$du = isset($classes[$classe]) ? $classes[$classe]['mensualite'] : 0;
				if ($e->reduction_type === 'pourcent') {
					$du = $du * (1 - min(100, max(0, (float) $e->reduction_valeur)) / 100);
				} elseif ($e->reduction_type === 'montant') {
					$du = max(0, $du - (float) $e->reduction_valeur);
				}
				$du = (float) price2num($du, 'MT');
			}
			$pp = isset($pay[$per]) ? $pay[$per] : 0;
			if (!$dans && $pp <= 0) {
				continue;
			}
			$reste = max(0, (float) price2num($du - $pp, 'MT'));
			$echu = $dans && $now > eleves_periode_limite($per);
			if ($dans && $du <= 0) {
				$etat = 'gratuit';
			} elseif ($reste <= 0) {
				$etat = 'paye';
			} elseif ($pp > 0) {
				$etat = $echu ? 'partiel_retard' : 'partiel';
			} else {
				$etat = $echu ? 'impaye' : 'a_venir';
			}
			if ($echu && $actif && $reste > 0) {
				$impaye += $reste;
				$impayeMois[] = $per;
			}
			$totalDu += $du;
			$totalPaye += $pp;
			$mois[$per] = array('periode' => $per, 'label' => eleves_periode_label($per), 'du' => $du, 'paye' => $pp, 'reste' => $reste, 'echu' => $echu, 'etat' => $etat, 'dans' => $dans);
		}
		$fraisReste = max(0, (float) price2num($fraisDu - $fraisPaye, 'MT'));
		if ($actif && $fraisReste > 0) {
			$impaye += $fraisReste;
		}
		$out[$id] = array(
			'actif' => $actif,
			'inscription' => array('du' => $fraisDu, 'paye' => $fraisPaye, 'reste' => $fraisReste, 'brut' => $fraisBrut, 'exonere' => $fraisExo),
			'mois' => $mois,
			'impaye' => (float) price2num($impaye, 'MT'),
			'impaye_mois' => $impayeMois,
			'total_du' => (float) price2num($totalDu, 'MT'),
			'total_paye' => (float) price2num($totalPaye, 'MT'),
			'reste' => max(0, (float) price2num($totalDu - $totalPaye, 'MT')),
			'dernier' => isset($dernier[$id]) ? $dernier[$id] : null,
		);
	}
	return $out;
}

/**
 * Libellé d'un motif d'exonération (dans la langue de l'utilisateur).
 *
 * @param  DoliDB $db Handler base
 * @param  int    $id Motif
 * @return string
 */
function eleves_motif_exo_label($db, $id)
{
	if ((int) $id <= 0) {
		return '';
	}
	$resql = $db->query("SELECT label_fr, label_ar FROM ".$db->prefix()."ecole_motif_exoneration WHERE rowid = ".((int) $id));
	return ($resql && ($o = $db->fetch_object($resql))) ? ecole_label($o) : '';
}

/**
 * État des frais d'inscription d'une situation (voir eleves_situations) : gratuit, exonere, paye, partiel, impaye, a_venir.
 *
 * @param  array $s Situation d'un élève
 * @return string
 */
function eleves_etat_inscription($s)
{
	$ins = $s['inscription'];
	if ($ins['du'] <= 0) {
		return (!empty($ins['exonere']) && $ins['exonere'] > 0) ? 'exonere' : 'gratuit';
	}
	if ($ins['reste'] <= 0) {
		return 'paye';
	}
	return $ins['paye'] > 0 ? 'partiel' : ($s['actif'] ? 'impaye' : 'a_venir');
}

/**
 * Situation financière d'un élève (voir eleves_situations).
 *
 * @param  DoliDB     $db Handler base
 * @param  EcoleEleve $e  Élève
 * @return array
 */
function eleves_situation($db, $e)
{
	$s = eleves_situations($db, array($e));
	return $s[(int) $e->id];
}

/**
 * Classe de l'élève pendant un mois : période de l'historique qui chevauche le mois et commence le plus tard.
 *
 * @param  array  $hist    Historique (classe, debut, fin) trié par date
 * @param  string $periode AAAA-MM
 * @param  int    $defaut  Classe actuelle (si pas d'historique)
 * @return int
 */
function eleves_classe_du_mois($hist, $periode, $defaut)
{
	$debutMois = $periode.'-01';
	$finMois = $periode.'-31';
	$classe = 0;
	foreach ($hist as $h) {
		if ($h['debut'] <= $finMois && ($h['fin'] === null || $h['fin'] >= $debutMois)) {
			$classe = $h['classe'];
		}
	}
	return $classe ? $classe : $defaut;
}

/**
 * États d'un mois : clé => [clé de traduction, classe du badge].
 *
 * @return array<string,array{0:string,1:string}>
 */
function eleves_etat_map()
{
	return array(
		'paye' => array('EtatPaye', 'badge-status4'),
		'gratuit' => array('EtatGratuit', 'badge-status4'),
		'exonere' => array('EtatExonere', 'badge-status4'),
		'partiel' => array('EtatPartiel', 'badge-status1'),
		'partiel_retard' => array('EtatPartielRetard', 'badge-warning'),
		'impaye' => array('EtatImpaye', 'badge-danger'),
		'a_venir' => array('EtatAVenir', 'badge-status0'),
	);
}

/**
 * État d'un mois en texte (« Payé en partie — reste 100 »), sans HTML.
 *
 * @param  string $etat  paye | partiel | partiel_retard | impaye | a_venir | gratuit | exonere
 * @param  float  $reste Reste à payer
 * @return string
 */
function eleves_etat_label($etat, $reste = 0)
{
	global $langs;
	$map = eleves_etat_map();
	$label = $langs->transnoentities(isset($map[$etat]) ? $map[$etat][0] : $etat);
	if (in_array($etat, array('partiel', 'partiel_retard'), true)) {
		$label .= ' — '.$langs->transnoentities('ResteNb', eleves_montant($reste));
	}
	return $label;
}

/**
 * Libellé et couleur de l'état d'un mois.
 *
 * @param  string $etat paye | partiel | partiel_retard | impaye | a_venir | gratuit
 * @param  float  $reste Reste à payer
 * @return string       HTML (badge)
 */
function eleves_etat_badge($etat, $reste = 0)
{
	$map = eleves_etat_map();
	return '<span class="badge '.(isset($map[$etat]) ? $map[$etat][1] : 'badge-status0').'">'.dol_escape_htmltag(eleves_etat_label($etat, $reste)).'</span>';
}

/**
 * Modes de paiement actifs de Dolibarr (encaissements) : id => libellé traduit.
 *
 * @param  DoliDB $db Handler base
 * @return array<int,string>
 */
function eleves_modes_paiement($db)
{
	global $langs;
	$langs->load('bills');
	$out = array();
	$resql = $db->query("SELECT id, code, libelle FROM ".$db->prefix()."c_paiement WHERE active = 1 AND type IN (0, 2) AND entity IN (".getEntity('c_paiement').") ORDER BY libelle");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$key = 'PaymentType'.$o->code;
		$tr = $langs->trans($key);
		$out[(int) $o->id] = ($tr !== $key) ? $tr : $o->libelle;
	}
	return $out;
}

/**
 * Libellé d'un mode de paiement.
 *
 * @param  DoliDB $db Handler base
 * @param  int    $id Id du mode
 * @return string
 */
function eleves_mode_label($db, $id)
{
	static $cache = null;
	if ($cache === null) {
		$cache = array();
		global $langs;
		$langs->load('bills');
		$resql = $db->query("SELECT id, code, libelle FROM ".$db->prefix()."c_paiement");
		while ($resql && ($o = $db->fetch_object($resql))) {
			$key = 'PaymentType'.$o->code;
			$tr = $langs->trans($key);
			$cache[(int) $o->id] = ($tr !== $key) ? $tr : $o->libelle;
		}
	}
	return isset($cache[(int) $id]) ? $cache[(int) $id] : '';
}

/**
 * Compte de trésorerie Dolibarr réglé pour un mode de paiement (0 = aucun).
 *
 * @param  int $modeid Id du mode
 * @return int
 */
function eleves_compte_mode($modeid)
{
	return getDolGlobalInt('ELEVES_COMPTE_MODE_'.((int) $modeid));
}

/**
 * Élèves auxquels on peut encaisser : l'élève donné, ou les enfants d'un responsable (sauf dossiers
 * sortis sans reste à payer).
 *
 * @param  DoliDB $db             Handler base
 * @param  int    $fk_eleve       Élève
 * @param  int    $fk_responsable Responsable
 * @return EcoleEleve[]
 */
function eleves_a_encaisser($db, $fk_eleve, $fk_responsable)
{
	$e = new EcoleEleve($db);
	if ($fk_eleve > 0) {
		$list = $e->fetchAllObjects('t.nom_fr', 'ASC', 0, 0, array('rowid' => (int) $fk_eleve));
	} else {
		$list = $e->fetchAllObjects('t.nom_fr', 'ASC', 0, 0, array('fk_responsable' => (int) $fk_responsable));
	}
	return is_array($list) ? $list : array();
}

/**
 * Formulaire d'encaissement (fiche élève ou caisse) : pour chaque élève, frais d'inscription et mensualités
 * avec leur reste, montant à payer modifiable ; autres frais ; somme reçue répartie automatiquement
 * (le plus ancien d'abord) ; mode, date, référence, note.
 *
 * @param  EcoleEleve[] $eleves  Élèves
 * @param  string       $action  URL du formulaire
 * @param  array        $hidden  Champs cachés (nom => valeur)
 * @return void
 */
function eleves_encaissement_form($eleves, $action, $hidden = array())
{
	global $db, $langs, $conf, $form;
	dol_include_once('/eleves/class/ecole_frais_type.class.php');
	if (!is_object($form)) {
		$form = new Form($db);
	}
	$situations = eleves_situations($db, $eleves);
	$ft = new EcoleFraisType($db);
	$fraisTypes = $ft->fetchActives();

	// Modes proposés : ceux reliés à un compte de trésorerie (Configuration > Paiements)
	$modes = eleves_modes_paiement($db);
	if (isModEnabled('banque')) {
		foreach (array_keys($modes) as $mid) {
			if (eleves_compte_mode($mid) <= 0) {
				unset($modes[$mid]);
			}
		}
	}
	if (empty($modes)) {
		print info_admin($langs->trans('AucunModeConfigure').' <a href="'.dol_buildpath('/eleves/admin/paiements.php', 1).'">'.$langs->trans('MenuConfigPaiements').'</a>', 0, 0, 'error');
		return;
	}
	$modesel = GETPOSTINT('fk_mode') ? GETPOSTINT('fk_mode') : (count($modes) === 1 ? (int) key($modes) : -1);

	print '<form method="POST" action="'.dol_escape_htmltag($action).'" id="encaissement">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="encaisser">';
	foreach ($hidden as $k => $v) {
		print '<input type="hidden" name="'.dol_escape_htmltag($k).'" value="'.dol_escape_htmltag($v).'">';
	}

	// Somme reçue et répartition automatique
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="4">'.img_picto('', 'fa-cash-register', 'class="pictofixedwidth"').$langs->trans('Encaissement').'</td></tr>';
	print '<tr class="oddeven"><td class="titlefieldcreate">'.$langs->trans('SommeRecue').'</td><td><input type="text" id="somme_recue" name="somme_recue" class="flat maxwidth150 right" value="'.dol_escape_htmltag(GETPOST('somme_recue', 'alpha')).'" autofocus> '.$conf->currency;
	print ' <span class="opacitymedium">'.$langs->trans('SommeRecueAide').'</span></td>';
	print '<td class="titlefieldcreate">'.$langs->trans('DatePaiement').'</td><td>'.$form->selectDate(GETPOSTISSET('datepaiementyear') ? dol_mktime(12, 0, 0, GETPOSTINT('datepaiementmonth'), GETPOSTINT('datepaiementday'), GETPOSTINT('datepaiementyear')) : dol_now(), 'datepaiement', 0, 0, 0, '', 1, 1).'</td></tr>';
	print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans('ModePaiement').'</td><td>'.$form->selectarray('fk_mode', $modes, $modesel, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td>';
	print '<td>'.$langs->trans('ReferencePaiement').'</td><td><input type="text" name="reference_paiement" class="flat minwidth200" maxlength="64" value="'.dol_escape_htmltag(GETPOST('reference_paiement', 'alphanohtml')).'" placeholder="'.dol_escape_htmltag($langs->trans('ReferencePaiementAide')).'"></td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('Note').'</td><td colspan="3"><input type="text" name="note" class="flat centpercent" maxlength="255" value="'.dol_escape_htmltag(GETPOST('note', 'alphanohtml')).'"></td></tr>';
	print '</table><br>';

	// Lignes à payer
	print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Eleve').'</td><td>'.$langs->trans('Libelle').'</td><td class="center">'.$langs->trans('Etat').'</td>';
	print '<td class="right">'.$langs->trans('MontantDu').'</td><td class="right">'.$langs->trans('DejaPaye').'</td><td class="right">'.$langs->trans('Reste').'</td><td class="right">'.$langs->trans('APayer').'</td></tr>';
	$n = 0;
	$nbclassesansprix = 0;
	foreach ($eleves as $e) {
		$s = $situations[(int) $e->id];
		$lignes = array();
		if ($s['inscription']['reste'] > 0) {
			$lignes[] = array('type' => 'inscription', 'periode' => '', 'label' => $langs->trans('FraisInscription'), 'du' => $s['inscription']['du'], 'paye' => $s['inscription']['paye'], 'reste' => $s['inscription']['reste'], 'etat' => $s['actif'] ? 'impaye' : 'a_venir');
		}
		foreach ($s['mois'] as $m) {
			if ($m['dans'] && $m['reste'] > 0) {
				$lignes[] = array('type' => 'mensualite', 'periode' => $m['periode'], 'label' => $langs->trans('MensualiteDe', $m['label']), 'du' => $m['du'], 'paye' => $m['paye'], 'reste' => $m['reste'], 'etat' => $m['etat']);
			}
		}
		$sansprix = true;
		foreach ($s['mois'] as $m) {
			if ($m['dans'] && $m['du'] > 0) {
				$sansprix = false;
			}
		}
		if ($sansprix && empty($e->reduction_type)) {
			$nbclassesansprix++;
		}
		$first = true;
		foreach ($lignes as $l) {
			$name = 'l['.$n.']';
			$val = GETPOSTISSET('l') ? (isset($_POST['l'][$n]['montant']) ? price2num($_POST['l'][$n]['montant']) : '') : '';
			print '<tr class="oddeven">';
			print '<td>'.($first ? $e->getNomUrl(1).' '.dol_escape_htmltag(ecole_label($e)) : '').'</td>';
			print '<td>'.dol_escape_htmltag($l['label']);
			print '<input type="hidden" name="'.$name.'[eleve]" value="'.((int) $e->id).'"><input type="hidden" name="'.$name.'[type]" value="'.$l['type'].'"><input type="hidden" name="'.$name.'[periode]" value="'.$l['periode'].'"></td>';
			print '<td class="center">'.($l['type'] === 'mensualite' ? eleves_etat_badge($l['etat'], $l['reste']) : '').'</td>';
			print '<td class="right">'.price($l['du']).'</td><td class="right">'.price($l['paye']).'</td><td class="right">'.price($l['reste']).'</td>';
			// Petite flèche (comme le paiement d'une facture Dolibarr) : remplit avec le reste complet de la ligne
			print '<td class="right nowraponall"><a href="#" class="eleves-remplir paddingright" title="'.dol_escape_htmltag($langs->trans('RemplirMontantComplet')).'" data-cible="'.$name.'[montant]" data-montant="'.price2num($l['reste']).'">'.img_picto($langs->trans('RemplirMontantComplet'), 'rightarrow').'</a>';
			print '<input type="text" class="flat maxwidth100 right eleves-ligne" data-reste="'.price2num($l['reste']).'" data-ordre="'.($l['type'] === 'inscription' ? '0000-00' : $l['periode']).'-'.sprintf('%04d', $n).'" name="'.$name.'[montant]" value="'.dol_escape_htmltag((string) $val).'"></td>';
			print '</tr>';
			$first = false;
			$n++;
		}
		if (empty($lignes)) {
			print '<tr class="oddeven"><td>'.$e->getNomUrl(1).' '.dol_escape_htmltag(ecole_label($e)).'</td><td colspan="6"><span class="opacitymedium">'.$langs->trans('RienADevoir').'</span></td></tr>';
		}
	}

	// Autres frais (uniforme, transport...) : 3 lignes libres
	print '<tr class="liste_titre"><td colspan="7">'.$langs->trans('AutresFrais').' <span class="opacitymedium small">'.$langs->trans('AutresFraisAide').'</span></td></tr>';
	$choixEleves = array();
	foreach ($eleves as $e) {
		$choixEleves[(int) $e->id] = $e->ref.' - '.ecole_label($e);
	}
	$choixFrais = array();
	$prixFrais = array();
	foreach ($fraisTypes as $t) {
		$choixFrais[(int) $t->id] = ecole_label($t);
		$prixFrais[(int) $t->id] = price2num($t->montant);
	}
	if (empty($fraisTypes)) {
		print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('AucunAutreFrais').'</span></td></tr>';
	}
	for ($i = 0; $i < 3 && !empty($fraisTypes); $i++) {
		$a = (GETPOSTISSET('a') && isset($_POST['a'][$i])) ? $_POST['a'][$i] : array();
		print '<tr class="oddeven">';
		print '<td>'.$form->selectarray('a['.$i.'][eleve]', $choixEleves, isset($a['eleve']) ? (int) $a['eleve'] : (count($eleves) === 1 ? (int) reset($eleves)->id : -1), count($eleves) > 1 ? 1 : 0, 0, 0, '', 0, 0, 0, '', 'maxwidth200').'</td>';
		print '<td colspan="5">'.$form->selectarray('a['.$i.'][frais]', $choixFrais, isset($a['frais']) ? (int) $a['frais'] : -1, 1, 0, 0, 'data-ligne="'.$i.'"', 0, 0, 0, '', 'minwidth200 eleves-frais').'</td>';
		print '<td class="right"><input type="text" class="flat maxwidth100 right eleves-autre" id="a_montant_'.$i.'" name="a['.$i.'][montant]" value="'.dol_escape_htmltag(isset($a['montant']) ? (string) price2num($a['montant']) : '').'"></td>';
		print '</tr>';
	}
	print '<tr class="liste_total"><td colspan="6" class="right">'.$langs->trans('TotalAEncaisser').'</td><td class="right"><b id="total_encaisse">0</b> '.$conf->currency.'</td></tr>';
	print '</table></div>';
	if ($nbclassesansprix > 0) {
		print info_admin($langs->trans('AvertissementSansPrix'), 0, 0, 'warning');
	}

	print '<div class="center"><br><input type="submit" class="button button-save" value="'.dol_escape_htmltag($langs->trans('EnregistrerPaiement')).'"></div>';
	print '</form>';

	// Répartition automatique de la somme reçue (le plus ancien d'abord) et total
	print '<script>
$(function () {
	var prix = '.json_encode($prixFrais).';
	function num(v) { v = String(v || "").replace(/\s/g, "").replace(",", "."); var n = parseFloat(v); return isNaN(n) ? 0 : n; }
	function total() {
		var t = 0;
		$(".eleves-ligne, .eleves-autre").each(function () { t += num($(this).val()); });
		$("#total_encaisse").text(t.toLocaleString());
	}
	$("#somme_recue").on("input", function () {
		var reste = num($(this).val());
		$(".eleves-autre").each(function () { reste -= num($(this).val()); });
		// Le plus ancien en premier, tous enfants confondus : inscription, puis mois par mois
		var lignes = $(".eleves-ligne").get().sort(function (a, b) { return String($(a).data("ordre")).localeCompare(String($(b).data("ordre"))); });
		$(lignes).each(function () {
			var r = num($(this).data("reste"));
			var v = Math.max(0, Math.min(r, reste));
			$(this).val(v > 0 ? v : "");
			reste -= v;
		});
		total();
	});
	$(".eleves-frais").on("change", function () {
		var i = $(this).data("ligne"), id = $(this).val();
		if (prix[id] !== undefined && num($("#a_montant_" + i).val()) === 0) { $("#a_montant_" + i).val(prix[id]); }
		total();
	});
	$(".eleves-ligne, .eleves-autre").on("input", total);
	$(".eleves-remplir").on("click", function (ev) {
		ev.preventDefault();
		$("input[name=\"" + $(this).data("cible") + "\"]").val($(this).data("montant")).trigger("input");
	});
	total();
});
</script>';
}

/**
 * Traite le formulaire d'encaissement : contrôle chaque ligne (jamais plus que le reste dû) et crée le reçu.
 *
 * @param  DoliDB       $db     Handler base
 * @param  User         $user   Utilisateur
 * @param  EcoleEleve[] $eleves Élèves proposés dans le formulaire
 * @param  int          $fk_responsable Responsable (reçu de famille) ou 0
 * @return int                  Id du reçu créé, ou -1 (messages déjà affichés)
 */
function eleves_encaissement_process($db, $user, $eleves, $fk_responsable = 0)
{
	global $langs;
	dol_include_once('/eleves/class/ecole_recu.class.php');
	dol_include_once('/eleves/class/ecole_frais_type.class.php');

	$byid = array();
	foreach ($eleves as $e) {
		$byid[(int) $e->id] = $e;
	}
	$situations = eleves_situations($db, $eleves);
	$errors = array();
	$lignes = array();

	foreach ((array) GETPOST('l', 'array') as $l) {
		$montant = (float) price2num(isset($l['montant']) ? $l['montant'] : 0, 'MT');
		if ($montant == 0) {
			continue;
		}
		$eid = isset($l['eleve']) ? (int) $l['eleve'] : 0;
		$type = isset($l['type']) ? (string) $l['type'] : '';
		$per = isset($l['periode']) ? (string) $l['periode'] : '';
		if (!isset($byid[$eid]) || !in_array($type, array('inscription', 'mensualite'), true)) {
			continue;
		}
		$s = $situations[$eid];
		if ($type === 'inscription') {
			$reste = $s['inscription']['reste'];
			$label = $langs->transnoentities('FraisInscription');
		} else {
			if (!isset($s['mois'][$per]) || !preg_match('/^\d{4}-\d{2}$/', $per)) {
				continue;
			}
			$reste = $s['mois'][$per]['reste'];
			$label = $langs->transnoentities('MensualiteDe', $s['mois'][$per]['label']);
		}
		if ($montant < 0 || $montant > $reste + 0.001) {
			$errors[] = $langs->trans('ErrorMontantSuperieurReste', $byid[$eid]->ref, $label, price($reste));
			continue;
		}
		$lignes[] = array('fk_eleve' => $eid, 'type' => $type, 'periode' => ($type === 'mensualite' ? $per : null), 'libelle' => $label, 'montant' => $montant);
	}
	$ft = new EcoleFraisType($db);
	$types = $ft->fetchActives();
	foreach ((array) GETPOST('a', 'array') as $a) {
		$montant = (float) price2num(isset($a['montant']) ? $a['montant'] : 0, 'MT');
		$fid = isset($a['frais']) ? (int) $a['frais'] : 0;
		$eid = isset($a['eleve']) ? (int) $a['eleve'] : 0;
		if ($montant == 0 && $fid <= 0) {
			continue;
		}
		if ($fid <= 0 || !isset($types[$fid]) || !isset($byid[$eid]) || $montant <= 0) {
			$errors[] = $langs->trans('ErrorLigneAutresFrais');
			continue;
		}
		$lignes[] = array('fk_eleve' => $eid, 'type' => 'autre', 'periode' => null, 'fk_frais_type' => $fid, 'libelle' => $langs->transnoentities('AutresFraisDe', ecole_label($types[$fid])), 'montant' => $montant);
	}
	if (empty($lignes) && empty($errors)) {
		$errors[] = $langs->trans('ErrorAucunMontant');
	}
	if ($errors) {
		setEventMessages($langs->trans('ErrorPaiementNonEnregistre'), $errors, 'errors');
		return -1;
	}

	$recu = new EcoleRecu($db);
	$date = dol_mktime(12, 0, 0, GETPOSTINT('datepaiementmonth'), GETPOSTINT('datepaiementday'), GETPOSTINT('datepaiementyear'));
	$res = $recu->encaisser($user, array(
		'date' => $date ? $date : dol_now(),
		'fk_mode' => GETPOSTINT('fk_mode'),
		'reference_paiement' => GETPOST('reference_paiement', 'alphanohtml'),
		'note' => GETPOST('note', 'alphanohtml'),
		'fk_responsable' => $fk_responsable,
		'lignes' => $lignes,
	));
	if ($res < 0) {
		setEventMessages($langs->trans('ErrorPaiementNonEnregistre'), $recu->errors ? $recu->errors : array($recu->error), 'errors');
		return -1;
	}
	return $res;
}

/**
 * Impayés : élèves qui ont un retard de paiement, avec les mêmes filtres que la page (classe, statut, nom).
 *
 * @param  DoliDB $db Handler base
 * @return array{lignes:array,total:float,param:string,filtres:array}
 */
function eleves_impayes($db)
{
	dol_include_once('/eleves/class/ecole_eleve.class.php');
	$remove = GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha');
	$classe = $remove ? 0 : GETPOSTINT('search_fk_classe');
	$groupe = $remove ? 'actifs' : (GETPOST('search_groupe', 'aZ09') ? GETPOST('search_groupe', 'aZ09') : 'actifs');
	$nom = $remove ? '' : trim(GETPOST('search_nom', 'alphanohtml'));
	$tri = GETPOST('tri', 'aZ09') === 'montant' ? 'montant' : 'classe';

	$conds = array();
	$param = '';
	if ($classe > 0) {
		$conds[] = 't.fk_classe = '.$classe;
		$param .= '&search_fk_classe='.$classe;
	}
	$statuts = ($groupe === 'sortis') ? EcoleEleve::statusSortis() : (($groupe === 'tous') ? array_merge(EcoleEleve::statusOccupantPlace(), EcoleEleve::statusSortis()) : EcoleEleve::statusOccupantPlace());
	$conds[] = 't.status IN ('.implode(',', $statuts).')';
	$param .= '&search_groupe='.urlencode($groupe);
	if ($nom !== '') {
		$conds[] = natural_search(array('t.nom_fr', 't.nom_ar', 't.ref'), $nom, 0, 1);
		$param .= '&search_nom='.urlencode($nom);
	}
	$param .= '&tri='.$tri;

	$e = new EcoleEleve($db);
	$list = $e->fetchAllObjects('t.nom_fr', 'ASC', 0, 0, array(), implode(' AND ', $conds));
	$list = is_array($list) ? $list : array();
	$sits = eleves_situations($db, $list);

	// Classes et responsables (libellés, téléphones)
	$classes = array();
	$resql = $db->query("SELECT c.rowid, c.ref, c.label_fr, c.label_ar, n.position FROM ".$db->prefix()."ecole_classe c LEFT JOIN ".$db->prefix()."ecole_niveau n ON n.rowid = c.fk_niveau");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$classes[(int) $o->rowid] = $o;
	}
	$resps = array();
	$resql = $db->query("SELECT rowid, nom_fr, nom_ar, telephone, whatsapp FROM ".$db->prefix()."ecole_responsable WHERE entity IN (".getEntity('ecole_responsable').")");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$resps[(int) $o->rowid] = $o;
	}

	$lignes = array();
	$total = 0;
	foreach ($list as $el) {
		$s = $sits[(int) $el->id];
		if ($s['impaye'] <= 0) {
			continue;
		}
		$mois = array();
		if ($s['inscription']['reste'] > 0) {
			$mois[] = 'inscription';
		}
		foreach ($s['impaye_mois'] as $per) {
			$mois[] = $per;
		}
		$c = isset($classes[(int) $el->fk_classe]) ? $classes[(int) $el->fk_classe] : null;
		$r = isset($resps[(int) $el->fk_responsable]) ? $resps[(int) $el->fk_responsable] : null;
		$lignes[] = array('eleve' => $el, 'situation' => $s, 'mois' => $mois, 'classe' => $c, 'responsable' => $r, 'montant' => $s['impaye'], 'pos' => $c ? sprintf('%05d-%08d', (int) $c->position, (int) $c->rowid) : '');
		$total += $s['impaye'];
	}
	usort($lignes, function ($a, $b) use ($tri) {
		if ($tri === 'montant') {
			return $b['montant'] <=> $a['montant'];
		}
		return strcmp($a['pos'].ecole_label($a['eleve']), $b['pos'].ecole_label($b['eleve']));
	});
	return array('lignes' => $lignes, 'total' => (float) price2num($total, 'MT'), 'param' => $param, 'filtres' => array('classe' => $classe, 'groupe' => $groupe, 'nom' => $nom, 'tri' => $tri));
}

/**
 * Paiements d'une classe pour un mois : les élèves de la classe (inscrits et suspendus) rangés en trois groupes,
 * avec leur responsable et son téléphone.
 *  - paye    : mensualité soldée (ou rien à payer) ;
 *  - partiel : payée en partie ;
 *  - impaye  : rien payé (en retard ou à venir).
 * Les élèves qui n'ont pas de mensualité ce mois-là (arrivés plus tard) sont seulement comptés (non_concernes).
 *
 * @param  DoliDB      $db      Handler base
 * @param  EcoleClasse $classe  Classe
 * @param  string      $periode Mois AAAA-MM
 * @return array{periode:string,groupes:array,non_concernes:int,totaux:array}
 */
function eleves_classe_paiements($db, $classe, $periode)
{
	dol_include_once('/eleves/class/ecole_eleve.class.php');
	$e = new EcoleEleve($db);
	$list = $e->fetchAllObjects('t.nom_fr', 'ASC', 0, 0, array('fk_classe' => (int) $classe->id), 't.status IN ('.implode(',', EcoleEleve::statusOccupantPlace()).')');
	$list = is_array($list) ? $list : array();
	$sits = eleves_situations($db, $list);

	$resps = array();
	$resql = $db->query("SELECT rowid, ref, nom_fr, nom_ar, telephone, whatsapp FROM ".$db->prefix()."ecole_responsable WHERE entity IN (".getEntity('ecole_responsable').")");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$resps[(int) $o->rowid] = $o;
	}

	$groupes = array('paye' => array(), 'partiel' => array(), 'impaye' => array());
	$totaux = array('du' => 0.0, 'paye' => 0.0, 'reste' => 0.0);
	$non = 0;
	foreach ($list as $el) {
		$s = $sits[(int) $el->id];
		if (empty($s['mois'][$periode]) || !$s['mois'][$periode]['dans']) {
			$non++;
			continue;
		}
		$m = $s['mois'][$periode];
		$g = in_array($m['etat'], array('paye', 'gratuit'), true) ? 'paye' : (in_array($m['etat'], array('partiel', 'partiel_retard'), true) ? 'partiel' : 'impaye');
		$groupes[$g][] = array('eleve' => $el, 'responsable' => isset($resps[(int) $el->fk_responsable]) ? $resps[(int) $el->fk_responsable] : null, 'mois' => $m);
		$totaux['du'] += $m['du'];
		$totaux['paye'] += $m['paye'];
		$totaux['reste'] += $m['reste'];
	}
	foreach ($totaux as $k => $v) {
		$totaux[$k] = (float) price2num($v, 'MT');
	}
	return array('periode' => $periode, 'groupes' => $groupes, 'non_concernes' => $non, 'totaux' => $totaux);
}

/**
 * Jeu de données « Paiements de la classe pour un mois » pour l'export PDF / Excel.
 *
 * @param  DoliDB      $db      Handler base
 * @param  EcoleClasse $classe  Classe
 * @param  string      $periode Mois AAAA-MM
 * @return array
 */
function eleves_export_dataset_classe_paiements($db, $classe, $periode)
{
	global $langs;
	dol_include_once('/classes/core/lib/ecole_pdf.lib.php');
	$data = eleves_classe_paiements($db, $classe, $periode);
	$rows = array();
	$n = 0;
	foreach ($data['groupes'] as $lignes) {
		foreach ($lignes as $l) {
			$r = $l['responsable'];
			$rows[] = array((string) (++$n), $l['eleve']->ref, ecole_label($l['eleve']), $r ? ecole_label($r) : '', $r ? (string) $r->telephone : '',
				price($l['mois']['du']), price($l['mois']['paye']), price($l['mois']['reste']), eleves_etat_label($l['mois']['etat'], $l['mois']['reste']));
		}
	}
	$t = $data['totaux'];
	$rows[] = array('', '', ecole_pdf_trans($langs, 'Total'), '', '', price($t['du']), price($t['paye']), price($t['reste']), '');
	return array(
		'title' => ecole_pdf_trans($langs, 'PaiementsClasseMois', $classe->ref, eleves_periode_label($periode)),
		'subtitle' => ecole_pdf_trans($langs, 'PaiementsClasseResume', count($data['groupes']['paye']), count($data['groupes']['partiel']), count($data['groupes']['impaye'])),
		'ref' => 'PAI-'.$classe->ref.'-'.str_replace('-', '', $periode),
		'headers' => array('#', ecole_pdf_trans($langs, 'Matricule'), ecole_pdf_trans($langs, 'NomComplet'), ecole_pdf_trans($langs, 'Responsable'), ecole_pdf_trans($langs, 'TelephoneResponsable'),
			ecole_pdf_trans($langs, 'MontantDu'), ecole_pdf_trans($langs, 'DejaPaye'), ecole_pdf_trans($langs, 'Reste'), ecole_pdf_trans($langs, 'Etat')),
		'ratios' => array(0.5, 1.1, 2.6, 2.4, 1.4, 1, 1, 1, 2),
		'aligns' => array('C', 'C', 'L', 'L', 'C', 'R', 'R', 'R', 'L'),
		'rows' => $rows,
		'filename' => 'paiements_'.$classe->ref.'_'.$periode,
	);
}

/**
 * Libellé court des retards d'un élève : « Inscription, Octobre 2026, Novembre 2026 ».
 *
 * @param  string[] $mois Périodes ('inscription' ou AAAA-MM)
 * @return string
 */
function eleves_impayes_libelle($mois)
{
	global $langs;
	$out = array();
	foreach ($mois as $m) {
		$out[] = ($m === 'inscription') ? $langs->transnoentities('FraisInscription') : eleves_periode_label($m);
	}
	return implode(', ', $out);
}

/**
 * Jeu de données « Impayés » pour l'export PDF / Excel.
 *
 * @param  DoliDB $db Handler base
 * @return array
 */
function eleves_export_dataset_impayes($db)
{
	global $langs;
	dol_include_once('/classes/core/lib/ecole_pdf.lib.php');
	$imp = eleves_impayes($db);
	$rows = array();
	$n = 0;
	foreach ($imp['lignes'] as $l) {
		$rows[] = array((string) (++$n), $l['eleve']->ref, ecole_label($l['eleve']), $l['classe'] ? $l['classe']->ref : '',
			$l['responsable'] ? ecole_label($l['responsable']) : '', $l['responsable'] ? (string) $l['responsable']->telephone : '',
			eleves_impayes_libelle($l['mois']), price($l['montant']));
	}
	$rows[] = array('', '', '', '', '', '', ecole_pdf_trans($langs, 'Total'), price($imp['total']));
	return array(
		'title' => ecole_pdf_trans($langs, 'Impayes'),
		'subtitle' => ecole_pdf_trans($langs, 'ImpayesAuJour', dol_print_date(dol_now(), 'day'), count($imp['lignes'])),
		'ref' => 'IMP-'.dol_print_date(dol_now(), '%Y%m%d'),
		'headers' => array('#', ecole_pdf_trans($langs, 'Matricule'), ecole_pdf_trans($langs, 'NomComplet'), ecole_pdf_trans($langs, 'Classe'), ecole_pdf_trans($langs, 'Responsable'),
			ecole_pdf_trans($langs, 'Telephone'), ecole_pdf_trans($langs, 'MoisEnRetard'), ecole_pdf_trans($langs, 'MontantImpaye')),
		'ratios' => array(0.5, 1.1, 2.6, 0.9, 2.2, 1.2, 3.2, 1.3),
		'aligns' => array('C', 'C', 'L', 'C', 'L', 'C', 'L', 'R'),
		'rows' => $rows,
		'filename' => 'impayes',
	);
}

/**
 * Libellé d'une ligne de paiement dans la langue d'affichage (le libellé enregistré, dans la langue
 * du caissier, ne sert qu'à la comptabilité).
 *
 * @param  object $l Ligne (type, periode, fk_frais_type, libelle)
 * @return string    Texte brut
 */
function eleves_ligne_libelle($l)
{
	global $langs, $db;
	static $frais = null;
	if ($l->type === 'inscription') {
		return $langs->transnoentities('FraisInscription');
	}
	if ($l->type === 'mensualite' && !empty($l->periode)) {
		return $langs->transnoentities('MensualiteDe', eleves_periode_label($l->periode));
	}
	if ($l->type === 'autre' && !empty($l->fk_frais_type)) {
		if ($frais === null) {
			$frais = array();
			$resql = $db->query("SELECT rowid, label_fr, label_ar FROM ".$db->prefix()."ecole_frais_type");
			while ($resql && ($o = $db->fetch_object($resql))) {
				$frais[(int) $o->rowid] = $o;
			}
		}
		if (isset($frais[(int) $l->fk_frais_type])) {
			return $langs->transnoentities('AutresFraisDe', ecole_label($frais[(int) $l->fk_frais_type]));
		}
	}
	return (string) $l->libelle;
}
