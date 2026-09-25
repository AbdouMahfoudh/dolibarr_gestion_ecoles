<?php
/**
 * Fonctions communes du module Salaires.
 *
 * Fichier : custom/salaires/core/lib/salaires.lib.php
 */

/**
 * Paramètres des pages génériques (liste de crud.lib.php du module Classes) des objets du module.
 *
 * @param  string $name bulletin | lot
 * @return array|null
 */
function salaires_crud_config($name)
{
	$cfgs = array(
		'bulletin' => array('module' => 'salaires', 'dir' => 'bulletin', 'class' => 'EcoleSalaire', 'title' => 'BulletinsDePaie', 'ficheTitle' => 'BulletinDePaie', 'newLabel' => 'NouveauBulletin',
			'perm_export' => 'bulletin.exporter', 'perm_read' => 'bulletin.lire', 'perm_write' => 'bulletin.creer', 'perm_create' => 'bulletin.creer', 'perm_delete' => 'bulletin.annuler',
			'noedit' => 1),
		'lot' => array('module' => 'salaires', 'dir' => 'lot', 'class' => 'EcoleSalaireLot', 'title' => 'LotsSalaires', 'ficheTitle' => 'LotSalaires', 'newLabel' => 'PreparerSalaires',
			'perm_export' => 'bulletin.exporter', 'perm_read' => 'bulletin.lire', 'perm_write' => 'bulletin.creer', 'perm_create' => 'bulletin.creer', 'perm_delete' => 'bulletin.creer',
			'noedit' => 1,
			'extra_columns' => array(
				'bulletins' => array('label' => 'NbBulletins', 'render' => 'salaires_lot_col_bulletins'),
				'net' => array('label' => 'TotalNet', 'render' => 'salaires_lot_col_net'),
				'etat' => array('label' => 'EtatBulletins', 'render' => 'salaires_lot_col_etat'),
			)),
	);
	// Avances et prêts : mêmes colonnes calculées (déjà retenu, reste, état)
	$colonnes = array(
		'retenu' => array('label' => 'DejaRetenu', 'render' => 'salaires_col_retenu'),
		'reste' => array('label' => 'ResteARetenir', 'render' => 'salaires_col_reste'),
		'etat' => array('label' => 'Etat', 'render' => 'salaires_col_etat',
			'search' => array('type' => 'select', 'options' => 'salaires_etats_choix', 'sql' => 'salaires_search_etat')),
	);
	$cfgs['avance'] = array('module' => 'salaires', 'dir' => 'avance', 'class' => 'EcoleSalaireAvance', 'title' => 'AvancesSurSalaire', 'ficheTitle' => 'AvanceSurSalaire', 'newLabel' => 'NouvelleAvance',
		'perm_export' => 'bulletin.exporter', 'perm_read' => 'avance.lire', 'perm_write' => 'avance.donner', 'perm_create' => 'avance.donner', 'perm_delete' => 'avance.annuler',
		'noedit' => 1, 'extra_columns' => $colonnes);
	$cfgs['pret'] = array('module' => 'salaires', 'dir' => 'pret', 'class' => 'EcoleSalairePret', 'title' => 'PretsPersonnel', 'ficheTitle' => 'PretPersonnel', 'newLabel' => 'NouveauPret',
		'perm_export' => 'bulletin.exporter', 'perm_read' => 'avance.lire', 'perm_write' => 'avance.donner', 'perm_create' => 'avance.donner', 'perm_delete' => 'avance.annuler',
		'noedit' => 1, 'extra_columns' => array_merge(array('mensualite' => array('label' => 'Mensualites', 'render' => 'salaires_col_mensualite', 'sort' => 'montant_echeance')), $colonnes));
	return isset($cfgs[$name]) ? $cfgs[$name] : null;
}

/**
 * Colonne « Déjà retenu » (avance ou prêt).
 *
 * @param  EcoleSalaireAvance $rec Avance ou prêt
 * @return string
 */
function salaires_col_retenu($rec)
{
	return '<span class="nowraponall">'.salaires_montant($rec->retenu()).'</span>';
}

/**
 * Colonne « Reste à retenir » (avance ou prêt).
 *
 * @param  EcoleSalaireAvance $rec Avance ou prêt
 * @return string
 */
function salaires_col_reste($rec)
{
	$r = $rec->reste();
	return '<span class="nowraponall'.($r > 0 ? ' bold' : ' opacitymedium').'">'.salaires_montant($r).'</span>';
}

/**
 * Colonne « État » : en cours, entièrement retenu / remboursé, annulé.
 *
 * @param  EcoleSalaireAvance $rec Avance ou prêt
 * @return string
 */
function salaires_col_etat($rec)
{
	return $rec->getLibStatut(5);
}

/**
 * Colonne « Mensualités » d'un prêt : nombre × montant.
 *
 * @param  EcoleSalairePret $rec Prêt
 * @return string
 */
function salaires_col_mensualite($rec)
{
	return '<span class="nowraponall">'.((int) $rec->nb_echeances).' × '.salaires_montant($rec->montant_echeance).'</span>';
}

/**
 * Choix du filtre « État » des avances et des prêts.
 *
 * @return array<string,string>
 */
function salaires_etats_choix()
{
	global $langs;
	$langs->load('salaires@salaires');
	return array('encours' => $langs->trans('EtatEnCours'), 'solde' => $langs->trans('EtatSolde'), 'annule' => $langs->trans('EtatAnnule'));
}

/**
 * Condition SQL du filtre « État ».
 *
 * @param  EcoleSalaireAvance $object Avance ou prêt
 * @param  string             $v      encours | solde | annule
 * @return string
 */
function salaires_search_etat($object, $v)
{
	$retenu = salaires_sql_retenu($object->db, $object->typeRetenue);
	if ($v === 'annule') {
		return 't.status = 0';
	}
	if ($v === 'solde') {
		return 't.status = 1 AND t.montant <= '.$retenu.' + 0.005';
	}
	return $v === 'encours' ? 't.status = 1 AND t.montant > '.$retenu.' + 0.005' : '';
}

/* ------------------------------------------------------------------
 * Montants, liens
 * ---------------------------------------------------------------- */

/**
 * Montant dans la devise de l'établissement (HTML).
 *
 * @param  float $v Montant
 * @return string
 */
function salaires_montant($v)
{
	global $conf, $langs;
	return price(price2num((float) $v, 'MT'), 0, $langs, 1, -1, -1, $conf->currency);
}

/**
 * Montant dans la devise, en texte brut (historique, PDF).
 *
 * @param  float          $v Montant
 * @param  Translate|null $l Langue
 * @return string
 */
function salaires_montant_texte($v, $l = null)
{
	global $conf, $langs;
	return dol_html_entity_decode(strip_tags(price(price2num((float) $v, 'MT'), 0, $l ? $l : $langs, 1, -1, -1, $conf->currency)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Lien vers la fiche d'un employé : matricule + nom.
 *
 * @param  DoliDB $db Handler base
 * @param  int    $id Employé
 * @return string
 */
function salaires_employe_lien($db, $id)
{
	static $cache = array();
	if ((int) $id <= 0) {
		return '';
	}
	if (!isset($cache[(int) $id])) {
		$cache[(int) $id] = '';
		$resql = $db->query("SELECT rowid, ref, nom_fr, nom_ar, status FROM ".$db->prefix()."ecole_employe WHERE rowid = ".((int) $id));
		if ($resql && ($o = $db->fetch_object($resql))) {
			$url = dol_buildpath('/salaires/employe.php', 1).'?id='.((int) $o->rowid);
			$cache[(int) $id] = '<a href="'.$url.'" class="nowraponall">'.img_picto('', 'fa-id-badge', 'class="pictofixedwidth"').dol_escape_htmltag($o->ref).'</a> '.dol_escape_htmltag(ecole_label($o));
		}
	}
	return $cache[(int) $id];
}

/**
 * Lien vers la fiche d'un lot.
 *
 * @param  DoliDB $db Handler base
 * @param  int    $id Lot
 * @return string
 */
function salaires_lot_lien($db, $id)
{
	static $cache = array();
	if (!isset($cache[(int) $id])) {
		$cache[(int) $id] = '';
		$resql = $db->query("SELECT ref, libelle FROM ".$db->prefix()."ecole_salaire_lot WHERE rowid = ".((int) $id));
		if ($resql && ($o = $db->fetch_object($resql))) {
			$cache[(int) $id] = '<a href="'.dol_buildpath('/salaires/lot/card.php', 1).'?id='.((int) $id).'" title="'.dol_escape_htmltag((string) $o->libelle).'">'.img_picto('', 'fa-layer-group', 'class="pictofixedwidth"').dol_escape_htmltag($o->ref).'</a>';
		}
	}
	return $cache[(int) $id];
}

/* ------------------------------------------------------------------
 * Modes de paiement et comptes
 * ---------------------------------------------------------------- */

/**
 * Modes de paiement actifs (dictionnaire Dolibarr) : id => libellé traduit.
 *
 * @param  DoliDB $db Handler base
 * @return array<int,string>
 */
function salaires_modes_paiement($db)
{
	global $langs;
	$langs->load('bills');
	$out = array();
	$resql = $db->query("SELECT id, code, libelle FROM ".$db->prefix()."c_paiement WHERE active = 1 AND type IN (1, 2) AND entity IN (".getEntity('c_paiement').") ORDER BY libelle");
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
 * @param  int    $id Mode
 * @return string
 */
function salaires_mode_label($db, $id)
{
	static $cache = null;
	if ($cache === null) {
		global $langs;
		$langs->load('bills');
		$cache = array();
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
 * Compte de trésorerie réglé pour payer les salaires avec un mode de paiement (0 = aucun).
 *
 * @param  int $modeid Mode
 * @return int
 */
function salaires_compte_mode($modeid)
{
	return getDolGlobalInt('SALAIRES_COMPTE_MODE_'.((int) $modeid));
}

/**
 * Contrôle d'un versement : mode de paiement connu, compte de trésorerie réglé pour ce mode (si le module Banques
 * est actif), employé avec un compte (obligatoire pour un salaire Dolibarr).
 *
 * @param  DoliDB       $db       Handler base
 * @param  EcoleEmploye $emp      Employé
 * @param  int          $fk_mode  Mode de paiement
 * @param  bool         $salaire  true = versement par un salaire Dolibarr (compte utilisateur obligatoire)
 * @param  int          $compte   (sortie) compte de trésorerie
 * @param  string       $numero   (entrée / sortie) numéro du compte mobile : obligatoire pour les modes qui le demandent
 *                                (Bankily, Sedad, Masrvi...), repris de la fiche de l'employé s'il est vide
 * @return string                 Erreur traduite, '' si tout va bien
 */
function salaires_controle_versement($db, $emp, $fk_mode, $salaire, &$compte, &$numero = '')
{
	global $langs;
	$modes = salaires_modes_paiement($db);
	$compte = salaires_compte_mode((int) $fk_mode);
	if (!isset($modes[(int) $fk_mode])) {
		return $langs->trans('ErrorFieldRequired', $langs->transnoentities('ModePaiement'));
	}
	if (isModEnabled('banque') && $compte <= 0) {
		return $langs->trans('ErrorAucunCompteModeSalaire', $modes[(int) $fk_mode]);
	}
	if ($salaire && (int) $emp->fk_user <= 0) {
		return $langs->trans('ErrorEmployeSansCompte', $emp->ref);
	}
	$numero = trim((string) $numero);
	if (salaires_mode_numero($db, (int) $fk_mode)) {
		if ($numero === '') {
			$numero = trim((string) $emp->mobile_money);
		}
		if ($numero === '') {
			return $langs->trans('ErrorNumeroCompteObligatoire', $modes[(int) $fk_mode], $emp->ref);
		}
	}
	return '';
}

/**
 * Le mode de paiement demande-t-il le numéro du compte de l'employé (paiement mobile : Bankily, Sedad, Masrvi...) ?
 * Réglage SALAIRES_MODE_NUMERO_<id> (Configuration des salaires) ; sans réglage, deviné d'après le code du mode.
 *
 * @param  DoliDB $db Handler base
 * @param  int    $id Mode
 * @return bool
 */
function salaires_mode_numero($db, $id)
{
	static $codes = null;
	$v = getDolGlobalString('SALAIRES_MODE_NUMERO_'.((int) $id));
	if ($v !== '') {
		return $v === '1';
	}
	if ($codes === null) {
		$codes = array();
		$resql = $db->query("SELECT id, code FROM ".$db->prefix()."c_paiement");
		while ($resql && ($o = $db->fetch_object($resql))) {
			$codes[(int) $o->id] = (string) $o->code;
		}
	}
	return isset($codes[(int) $id]) && (bool) preg_match('/BNK|BANKILY|SEDAD|MSRV|MASRV|CLICK|MOBILE/i', $codes[(int) $id]);
}

/**
 * Modes de paiement qui demandent le numéro du compte (pour l'affichage des formulaires).
 *
 * @param  DoliDB $db Handler base
 * @return int[]
 */
function salaires_modes_avec_numero($db)
{
	$out = array();
	foreach (array_keys(salaires_modes_paiement($db)) as $id) {
		if (salaires_mode_numero($db, $id)) {
			$out[] = (int) $id;
		}
	}
	return $out;
}

/**
 * Mode de paiement et numéro du compte en une ligne : « Bankily n° 22xxxxxx ».
 *
 * @param  DoliDB $db     Handler base
 * @param  int    $mode   Mode
 * @param  string $numero Numéro du compte
 * @return string
 */
function salaires_mode_numero_texte($db, $mode, $numero)
{
	global $langs;
	$langs->load('salaires@salaires');
	return trim(salaires_mode_label($db, (int) $mode).((string) $numero !== '' ? ' '.$langs->transnoentities('NumeroAbrege', $numero) : ''));
}

/**
 * Mois de salaire déjà utilisés par des bulletins (filtre de la liste) : AAAA-MM => « Octobre 2026 », du plus récent.
 *
 * @param  DoliDB $db Handler base
 * @return array<string,string>
 */
function salaires_mois_choix($db)
{
	$out = array();
	$resql = $db->query("SELECT DISTINCT mois FROM ".$db->prefix()."ecole_salaire WHERE mois IS NOT NULL AND entity IN (".getEntity('ecole_salaire').") ORDER BY mois DESC");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[$o->mois] = personnel_mois_label($o->mois);
	}
	return $out;
}

/**
 * Formulaire : champ « numéro du compte », affiché seulement pour les modes qui le demandent (JS).
 *
 * @param  DoliDB $db     Handler base
 * @param  string $select Sélecteur CSS de la liste des modes
 * @param  string $champ  Sélecteur CSS du champ du numéro (sa ligne est montrée ou cachée)
 * @return string         Script
 */
function salaires_js_numero($db, $select, $champ)
{
	return '<script>$(function(){ var m = '.json_encode(salaires_modes_avec_numero($db)).';'
		.' function f(){ var v = parseInt($("'.$select.'").val() || "0"), e = $("'.$champ.'"), r = e.closest(".tagtr"); if (!r.length) { r = e.closest("tr"); } r.toggle(m.indexOf(v) >= 0); }'
		.' $(document).on("change", "'.$select.'", f); f(); setTimeout(f, 300); });</script>';
}

/**
 * Verse de l'argent à un employé par un salaire Dolibarr (salaire + paiement + écriture dans le compte de
 * trésorerie). À appeler dans une transaction.
 *
 * @param  DoliDB       $db        Handler base
 * @param  User         $user      Utilisateur
 * @param  EcoleEmploye $emp       Employé
 * @param  array        $v         label, note, montant, date, debut, fin, mode, compte, reference, salaire (salaire de référence)
 * @param  string       $error     (sortie) message d'erreur
 * @return array{0:int,1:int}|null Ids du salaire et du paiement, null si erreur
 */
function salaires_verser_salaire($db, $user, $emp, $v, &$error)
{
	global $langs;
	require_once DOL_DOCUMENT_ROOT.'/salaries/class/salary.class.php';
	require_once DOL_DOCUMENT_ROOT.'/salaries/class/paymentsalary.class.php';
	require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
	$sal = new Salary($db);
	$sal->fk_user = (int) $emp->fk_user;
	$sal->label = dol_trunc($v['label'], 250, 'right', 'UTF-8', 1);
	$sal->amount = (float) $v['montant'];
	$sal->salary = !empty($v['salaire']) ? (float) $v['salaire'] : null;
	$sal->datesp = $v['debut'];
	$sal->dateep = $v['fin'];
	$sal->type_payment = (int) $v['mode'];
	$sal->accountid = (int) $v['compte'];
	$sal->note = $v['note'];
	$salaryId = $sal->create($user);
	if ($salaryId <= 0) {
		$error = $langs->trans('ErrorPaiementSalaire').' '.$sal->error;
		return null;
	}
	$pay = new PaymentSalary($db);
	$pay->fk_salary = $salaryId;
	$pay->datep = $v['date'];
	$pay->datev = $v['date'];
	$pay->amounts = array($salaryId => (float) $v['montant']);
	$pay->fk_typepayment = (int) $v['mode'];
	$pay->num_payment = $v['reference'];
	$pay->note = $v['note'];
	$paymentId = $pay->create($user, 1);
	if ($paymentId <= 0) {
		$error = $langs->trans('ErrorPaiementSalaire').' '.$langs->trans($pay->error);
		return null;
	}
	if (isModEnabled('banque') && (int) $v['compte'] > 0) {
		if ($pay->addPaymentToBank($user, 'payment_salary', '(SalaryPayment)', (int) $v['compte'], '', '') <= 0) {
			$error = $langs->trans('ErrorPaiementSalaire').' '.$pay->error;
			return null;
		}
	}
	return array($salaryId, $paymentId);
}

/**
 * Retire un versement fait par salaire Dolibarr : paiement (et son écriture de trésorerie, si elle n'est pas
 * rapprochée) puis salaire. À appeler dans une transaction.
 *
 * @param  DoliDB $db         Handler base
 * @param  User   $user       Utilisateur
 * @param  int    $fk_salary  Salaire Dolibarr
 * @param  int    $fk_payment Paiement Dolibarr
 * @param  string $error      (sortie) message d'erreur
 * @return int                1 si OK, -1 sinon
 */
function salaires_retirer_salaire($db, $user, $fk_salary, $fk_payment, &$error)
{
	global $langs;
	require_once DOL_DOCUMENT_ROOT.'/salaries/class/salary.class.php';
	require_once DOL_DOCUMENT_ROOT.'/salaries/class/paymentsalary.class.php';
	require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php'; // AccountLine (écriture de trésorerie)
	if ((int) $fk_payment > 0) {
		$pay = new PaymentSalary($db);
		if ($pay->fetch((int) $fk_payment) > 0) {
			if ($pay->bank_line > 0) {
				$line = new AccountLine($db);
				if ($line->fetch((int) $pay->bank_line) > 0 && !empty($line->rappro)) {
					$error = $langs->trans('ErrorPaiementRapproche');
					return -1;
				}
			}
			if ($pay->delete($user) <= 0) {
				$error = $langs->trans('ErrorAnnulationPaiement').' '.$pay->error;
				return -1;
			}
		}
	}
	if ((int) $fk_salary > 0) {
		$sal = new Salary($db);
		if ($sal->fetch((int) $fk_salary) > 0 && $sal->delete($user) <= 0) {
			$error = $langs->trans('ErrorAnnulationPaiement').' '.$sal->error;
			return -1;
		}
	}
	return 1;
}

/**
 * Sortie d'argent simple dans le compte de trésorerie (prêt au personnel), liée à l'utilisateur de l'employé.
 *
 * @param  DoliDB       $db    Handler base
 * @param  User         $user  Utilisateur
 * @param  EcoleEmploye $emp   Employé
 * @param  array        $v     label, montant, date, mode, compte, reference
 * @param  string       $error (sortie) message d'erreur
 * @return int                 Id de l'écriture (0 si le module Banques n'est pas actif), -1 si erreur
 */
function salaires_verser_banque($db, $user, $emp, $v, &$error)
{
	global $langs;
	if (!isModEnabled('banque') || (int) $v['compte'] <= 0) {
		return 0;
	}
	require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
	$acc = new Account($db);
	if ($acc->fetch((int) $v['compte']) <= 0) {
		$error = $langs->trans('ErrorPaiementSalaire');
		return -1;
	}
	$id = $acc->addline($v['date'], (string) ((int) $v['mode']), dol_trunc($v['label'], 250, 'right', 'UTF-8', 1), -abs((float) $v['montant']), $v['reference'], 0, $user);
	if ($id <= 0) {
		$error = $langs->trans('ErrorPaiementSalaire').' '.$acc->error;
		return -1;
	}
	if ((int) $emp->fk_user > 0) {
		$acc->add_url_line($id, (int) $emp->fk_user, DOL_URL_ROOT.'/user/card.php?id=', $emp->ref.' '.$emp->nom_fr, 'user');
	}
	return $id;
}

/**
 * Retire une écriture de trésorerie (si elle n'est pas rapprochée).
 *
 * @param  DoliDB $db      Handler base
 * @param  User   $user    Utilisateur
 * @param  int    $fk_bank Écriture
 * @param  string $error   (sortie) message d'erreur
 * @return int             1 si OK, -1 sinon
 */
function salaires_retirer_banque($db, $user, $fk_bank, &$error)
{
	global $langs;
	if ((int) $fk_bank <= 0) {
		return 1;
	}
	require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
	$line = new AccountLine($db);
	if ($line->fetch((int) $fk_bank) <= 0) {
		return 1;
	}
	if (!empty($line->rappro)) {
		$error = $langs->trans('ErrorPaiementRapproche');
		return -1;
	}
	if ($line->delete($user) <= 0) {
		$error = $langs->trans('ErrorAnnulationPaiement').' '.$line->error;
		return -1;
	}
	return 1;
}

/* ------------------------------------------------------------------
 * Avances et prêts : retenues sur les bulletins
 * ---------------------------------------------------------------- */

/**
 * Montant retenu pour une avance ou un prêt :
 *  - $valides = true : vraiment retenu = bulletins validés ou payés (ce qui est affiché : déjà retenu, reste, état) ;
 *  - $valides = false : réservé par tous les bulletins non annulés, brouillons compris (sert au calcul, pour qu'une
 *    même somme ne soit jamais retenue par deux bulletins).
 *
 * @param  DoliDB $db      Handler base
 * @param  string $type    avance | pret
 * @param  int    $id      Avance ou prêt
 * @param  int    $exclude Bulletin à ignorer
 * @param  bool   $valides true = seulement les bulletins validés ou payés
 * @return float
 */
function salaires_retenu($db, $type, $id, $exclude = 0, $valides = false)
{
	$sql = "SELECT SUM(l.montant) as total FROM ".$db->prefix()."ecole_salaire_ligne l INNER JOIN ".$db->prefix()."ecole_salaire s ON s.rowid = l.fk_salaire";
	$sql .= " WHERE l.source_type = '".$db->escape($type)."' AND l.fk_source = ".((int) $id)." AND s.rowid <> ".((int) $exclude);
	$sql .= $valides ? " AND s.status IN (1, 2)" : " AND s.status <> 9";
	$resql = $db->query($sql);
	$o = $resql ? $db->fetch_object($resql) : null;
	return $o ? (float) price2num((float) $o->total, 'MT') : 0.0;
}

/**
 * Retenues d'une avance ou d'un prêt, bulletin par bulletin (annulés compris, pour l'historique).
 *
 * @param  DoliDB $db   Handler base
 * @param  string $type avance | pret
 * @param  int    $id   Avance ou prêt
 * @return array<int,object> Lignes (rowid bulletin, ref, date_debut, date_fin, status, montant)
 */
function salaires_retenues_liste($db, $type, $id)
{
	$out = array();
	$sql = "SELECT s.rowid, s.ref, s.date_debut, s.date_fin, s.mois, s.status, SUM(l.montant) as montant FROM ".$db->prefix()."ecole_salaire_ligne l";
	$sql .= " INNER JOIN ".$db->prefix()."ecole_salaire s ON s.rowid = l.fk_salaire WHERE l.source_type = '".$db->escape($type)."' AND l.fk_source = ".((int) $id);
	$sql .= " GROUP BY s.rowid, s.ref, s.date_debut, s.date_fin, s.mois, s.status ORDER BY s.date_debut, s.rowid";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[] = $o;
	}
	return $out;
}

/**
 * Expression SQL du montant déjà retenu (bulletins validés ou payés) pour la ligne « t » d'une liste d'avances ou de prêts.
 *
 * @param  DoliDB $db   Handler base
 * @param  string $type avance | pret
 * @return string
 */
function salaires_sql_retenu($db, $type)
{
	return "(SELECT COALESCE(SUM(zl.montant), 0) FROM ".$db->prefix()."ecole_salaire_ligne zl INNER JOIN ".$db->prefix()."ecole_salaire zs ON zs.rowid = zl.fk_salaire"
		." WHERE zl.source_type = '".$db->escape($type)."' AND zl.fk_source = t.rowid AND zs.status IN (1, 2))";
}

/**
 * Lignes de retenue des avances et des prêts en cours d'un employé pour un bulletin :
 *  - avances versées au plus tard le dernier jour de la période : le reste à retenir, en une fois ;
 *  - prêts dont le premier mois de remboursement est commencé : une mensualité (ou le reste s'il est plus petit).
 * Jamais plus que ce qui reste du salaire (le net ne devient pas négatif) : ce qui n'est pas retenu passe au
 * bulletin suivant.
 *
 * @param  DoliDB       $db         Handler base
 * @param  EcoleSalaire $b          Bulletin
 * @param  float        $disponible Net avant avances et prêts
 * @return array{lignes:array,alertes:array}
 */
function salaires_lignes_avances_prets($db, $b, $disponible)
{
	global $langs;
	$langs->load('salaires@salaires');
	$lignes = array();
	$alertes = array();
	$disponible = max(0, (float) $disponible);
	$fin = $b->au();
	$ajouter = function ($type, $o, $cle, $dcle, $dargs, $du) use (&$lignes, &$alertes, &$disponible, $langs) {
		$m = (float) price2num(min($du, $disponible), 'MT');
		if ($m < $du - 0.005) {
			$alertes[] = array('AlerteRetenueReduite', array(array('t', $o->ref), array('m', $du - $m)));
		}
		if ($m <= 0) {
			return;
		}
		$disponible -= $m;
		$lignes[] = array('code' => strtoupper($type), 'type' => 'retenue', 'cle' => $cle, 'dcle' => $dcle, 'dargs' => $dargs,
			'libelle' => $langs->transnoentities($cle), 'detail' => personnel_paie_texte($langs, $dcle, $dargs), 'montant' => $m,
			'source_type' => $type, 'fk_source' => (int) $o->rowid);
	};
	$p = $db->prefix();

	// Avances
	$sql = "SELECT rowid, ref, date_avance, montant FROM ".$p."ecole_salaire_avance WHERE entity IN (".getEntity('ecole_salaire_avance').")";
	$sql .= " AND fk_employe = ".((int) $b->fk_employe)." AND status = 1 AND date_avance <= '".$db->escape($fin)."' ORDER BY date_avance, rowid";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$reste = (float) $o->montant - salaires_retenu($db, 'avance', (int) $o->rowid, (int) $b->id);
		if ($reste > 0.005) {
			$ajouter('avance', $o, 'LigneAvance', 'DetailAvance', array(array('t', $o->ref), array('t', dol_print_date($db->jdate($o->date_avance), 'day', 'tzserver'))), $reste);
		}
	}

	// Prêts
	$sql = "SELECT rowid, ref, montant, nb_echeances, montant_echeance, premier_mois FROM ".$p."ecole_salaire_pret WHERE entity IN (".getEntity('ecole_salaire_pret').")";
	$sql .= " AND fk_employe = ".((int) $b->fk_employe)." AND status = 1 AND premier_mois <= '".$db->escape(substr($fin, 0, 7))."' ORDER BY date_pret, rowid";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$deja = salaires_retenu($db, 'pret', (int) $o->rowid, (int) $b->id);
		$reste = (float) $o->montant - $deja;
		if ($reste <= 0.005) {
			continue;
		}
		$du = min((float) $o->montant_echeance, $reste);
		$num = count(array_filter(salaires_retenues_liste($db, 'pret', (int) $o->rowid), function ($r) use ($b) {
			return (int) $r->status !== EcoleSalaire::STATUS_ANNULE && (int) $r->rowid !== (int) $b->id && (float) $r->montant > 0;
		})) + 1;
		$ajouter('pret', $o, 'LignePret', 'DetailPret', array(array('t', $o->ref), array('n', $num), array('n', (int) $o->nb_echeances), array('m', $reste - min($du, $disponible))), $du);
	}
	return array('lignes' => $lignes, 'alertes' => $alertes);
}

/**
 * Avances et prêts d'un employé avec ce qui reste à retenir.
 *
 * @param  DoliDB $db         Handler base
 * @param  int    $fk_employe Employé
 * @param  string $type       avance | pret
 * @return array<int,object>  Lignes de la table, plus « retenu » (bulletins validés ou payés), « reste » et « brouillon »
 *                            (prévu sur un bulletin en brouillon, pas encore retenu)
 */
function salaires_avances_prets_employe($db, $fk_employe, $type)
{
	$out = array();
	$table = ($type === 'pret') ? 'ecole_salaire_pret' : 'ecole_salaire_avance';
	$date = ($type === 'pret') ? 'date_pret' : 'date_avance';
	$resql = $db->query("SELECT * FROM ".$db->prefix().$table." WHERE fk_employe = ".((int) $fk_employe)." ORDER BY ".$date." DESC, rowid DESC");
	while ($resql && ($o = $db->fetch_object($resql))) {
		$o->retenu = salaires_retenu($db, $type, (int) $o->rowid, 0, true);
		$o->brouillon = max(0, salaires_retenu($db, $type, (int) $o->rowid) - $o->retenu);
		$o->reste = ((int) $o->status === 1) ? max(0, (float) $o->montant - $o->retenu) : 0;
		$out[] = $o;
	}
	return $out;
}

/* ------------------------------------------------------------------
 * Lignes et séances
 * ---------------------------------------------------------------- */

/**
 * Libellé d'une ligne dans une langue (ligne calculée : réécrite depuis sa clé de traduction).
 *
 * @param  object    $l Ligne (ecole_salaire_ligne)
 * @param  Translate $t Traductions
 * @return string
 */
function salaires_ligne_libelle($l, $t)
{
	if ((int) $l->auto && !empty($l->cle)) {
		$t->loadLangs(array('personnel@personnel', 'salaires@salaires'));
		return $t->transnoentities($l->cle);
	}
	return (string) $l->libelle;
}

/**
 * Détail d'une ligne dans une langue (heures × prix, fraction du mois...).
 *
 * @param  object    $l Ligne
 * @param  Translate $t Traductions
 * @return string
 */
function salaires_ligne_detail($l, $t)
{
	if ((int) $l->auto && !empty($l->dcle)) {
		$t->loadLangs(array('personnel@personnel', 'salaires@salaires'));
		$args = json_decode((string) $l->dargs, true);
		return personnel_paie_texte($t, $l->dcle, is_array($args) ? $args : array());
	}
	return (string) $l->detail;
}

/**
 * Libellé de l'état d'une séance (texte).
 *
 * @param  string    $etat État
 * @param  Translate $t    Traductions
 * @return string
 */
function salaires_etat_seance($etat, $t)
{
	$t->load('personnel@personnel');
	$map = array('present' => 'Present', 'declare' => 'CoursDeclare', 'retard' => 'EnRetard', 'absent' => 'Absent', 'remplacement' => 'Remplacement');
	return isset($map[$etat]) ? $t->transnoentities($map[$etat]) : $etat;
}

/* ------------------------------------------------------------------
 * Colonnes de la liste des lots
 * ---------------------------------------------------------------- */

/**
 * Nombre de bulletins (non annulés) d'un lot.
 *
 * @param  EcoleSalaireLot $rec Lot
 * @return string
 */
function salaires_lot_col_bulletins($rec)
{
	$r = $rec->resume();
	return (string) $r['nb'];
}

/**
 * Total net d'un lot.
 *
 * @param  EcoleSalaireLot $rec Lot
 * @return string
 */
function salaires_lot_col_net($rec)
{
	$r = $rec->resume();
	return '<span class="nowraponall">'.salaires_montant($r['net']).'</span>';
}

/**
 * État des bulletins d'un lot : badges « brouillon / validé / payé ».
 *
 * @param  EcoleSalaireLot $rec Lot
 * @return string
 */
function salaires_lot_col_etat($rec)
{
	global $langs;
	$r = $rec->resume();
	$out = array();
	foreach (EcoleSalaire::statusLabels() as $st => $v) {
		if ($st !== EcoleSalaire::STATUS_ANNULE && !empty($r['par_statut'][$st])) {
			$out[] = dolGetStatus($langs->trans($v[0]).' : '.$r['par_statut'][$st], '', '', $v[1], 5, '', array('badgeParams' => array('attr' => array('title' => $langs->trans($v[0])))));
		}
	}
	return implode(' ', $out);
}

/* ------------------------------------------------------------------
 * Préparation d'un lot
 * ---------------------------------------------------------------- */

/**
 * Employés à payer pour une période, avec leur situation : prêt, déjà un bulletin sur ces dates, paie non renseignée.
 * Les employés partis avant le début de la période ne sont pas proposés.
 *
 * @param  DoliDB $db         Handler base
 * @param  string $d1         Début AAAA-MM-JJ
 * @param  string $d2         Fin AAAA-MM-JJ
 * @param  array  $categories Catégories (vide = toutes)
 * @param  string $mode       fixe | heure | '' (tous)
 * @param  string $mois       Mois du salaire AAAA-MM (un employé qui a déjà un bulletin de ce mois n'est pas « prêt »)
 * @return array<int,array{emp:EcoleEmploye,etat:string,info:string}>  etat : pret | existe | incomplet
 */
function salaires_candidats_lot($db, $d1, $d2, $categories = array(), $mode = '', $mois = '')
{
	global $langs;
	$w = array("(t.status <> ".EcoleEmploye::STATUS_PARTI." OR t.date_statut > '".$db->escape($d1)."')");
	$cats = array();
	foreach ((array) $categories as $c) {
		if (isset(ecole_categories_utilisateur()[$c])) {
			$cats[] = "FIND_IN_SET('".$db->escape($c)."', t.categories) > 0";
		}
	}
	if ($cats) {
		$w[] = '('.implode(' OR ', $cats).')';
	}
	if ($mode === 'fixe' || $mode === 'heure') {
		$w[] = "t.mode_paie = '".$db->escape($mode)."'";
	}
	$e = new EcoleEmploye($db);
	$list = $e->fetchAllObjects('t.nom_fr', 'ASC', 0, 0, array(), implode(' AND ', $w));
	$out = array();
	foreach (is_array($list) ? $list : array() as $emp) {
		$etat = 'pret';
		$info = '';
		$o = EcoleSalaire::chevauchement($db, (int) $emp->id, $d1, $d2);
		if (!$o && $mois !== '') {
			$o = EcoleSalaire::bulletinDuMois($db, (int) $emp->id, $mois);
		}
		if ($o) {
			$etat = 'existe';
			$info = $o->ref;
		} elseif (!in_array($emp->mode_paie, array('fixe', 'heure'), true) || ($emp->mode_paie === 'fixe' && (float) $emp->salaire_base <= 0) || ($emp->mode_paie === 'heure' && (float) $emp->taux_horaire <= 0)) {
			$etat = 'incomplet';
			$info = $langs->trans('PaieNonRenseignee');
		}
		$out[(int) $emp->id] = array('emp' => $emp, 'etat' => $etat, 'info' => $info);
	}
	return $out;
}

/**
 * Période proposée par défaut : du premier jour du mois en cours jusqu'à aujourd'hui (jamais de date à venir).
 *
 * @return array{0:string,1:string}
 */
function salaires_periode_defaut()
{
	$auj = personnel_aujourdhui();
	return array(substr($auj, 0, 7).'-01', $auj);
}

/* ------------------------------------------------------------------
 * Configuration
 * ---------------------------------------------------------------- */

/**
 * Onglets de la configuration du module.
 *
 * @return array
 */
function salaires_admin_prepare_head()
{
	global $langs;
	$langs->loadLangs(array('salaires@salaires', 'personnel@personnel'));
	$head = array();
	$head[] = array(dol_buildpath('/salaires/admin/setup.php', 1), $langs->trans('MenuConfigSalaires'), 'setup');
	$head[] = array(dol_buildpath('/personnel/admin/regles.php', 1), $langs->trans('MenuReglesPresence'), 'regles');
	return $head;
}

/* ------------------------------------------------------------------
 * Exports
 * ---------------------------------------------------------------- */

/**
 * Jeu de données « bulletins d'un lot » (PDF / Excel).
 *
 * @param  DoliDB          $db  Handler base
 * @param  EcoleSalaireLot $lot Lot
 * @return array
 */
function salaires_export_dataset_lot($db, $lot)
{
	global $langs;
	$rows = array();
	$n = 0;
	$tot = array(0, 0, 0);
	$statuts = EcoleSalaire::statusLabels();
	foreach ($lot->getBulletins() as $b) {
		$emp = ecole_pdf_text(salaires_employe_lien($db, (int) $b->fk_employe));
		$annule = ((int) $b->status === EcoleSalaire::STATUS_ANNULE);
		if (!$annule) {
			$tot[0] += $b->total_gains;
			$tot[1] += $b->total_retenues;
			$tot[2] += $b->net;
		}
		$rows[] = array((string) (++$n), $b->ref, $emp, ecole_pdf_trans($langs, $b->mode_paie === 'heure' ? 'ModeHeure' : ($b->mode_paie === 'fixe' ? 'ModeFixe' : '')),
			price($b->total_gains), price($b->total_retenues), price($b->net), ecole_pdf_trans($langs, $statuts[(int) $b->status][0]));
	}
	$rows[] = array('', '', ecole_pdf_trans($langs, 'Total'), '', price($tot[0]), price($tot[1]), price($tot[2]), '');
	return array(
		'title' => ecole_pdf_trans($langs, 'LotSalaires').' '.$lot->ref,
		'subtitle' => trim($lot->libelle.' — '.dol_print_date($lot->date_debut, 'day').' - '.dol_print_date($lot->date_fin, 'day'), ' —'),
		'ref' => $lot->ref,
		'headers' => array('#', ecole_pdf_trans($langs, 'NumeroBulletin'), ecole_pdf_trans($langs, 'Employe'), ecole_pdf_trans($langs, 'ModeDePaie'),
			ecole_pdf_trans($langs, 'TotalGains'), ecole_pdf_trans($langs, 'TotalRetenues'), ecole_pdf_trans($langs, 'NetAPayer'), ecole_pdf_trans($langs, 'StatutBulletin')),
		'ratios' => array(0.5, 1.2, 3.2, 1.2, 1.4, 1.4, 1.4, 1.2),
		'aligns' => array('C', 'C', 'L', 'C', 'R', 'R', 'R', 'C'),
		'rows' => $rows,
		'filename' => 'salaires_'.$lot->ref,
	);
}
