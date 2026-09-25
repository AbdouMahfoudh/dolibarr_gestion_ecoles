<?php
/**
 * Avance sur salaire : argent donné à un employé avant la paie, retenu sur son prochain bulletin.
 *
 * - Versement : salaire natif de Dolibarr « Avance sur salaire » payé tout de suite, dans le compte de trésorerie
 *   du mode de paiement (l'avance fait partie du salaire de l'employé).
 * - Retenue : ligne « Avance sur salaire » ajoutée automatiquement au calcul du premier bulletin dont la période
 *   finit après la date de l'avance ; le comptable peut n'en retenir qu'une partie : le reste passe au bulletin suivant.
 * - Annulation (motif) : seulement tant que rien n'a été retenu ; le versement est retiré de la trésorerie.
 *
 * Fichier : custom/salaires/class/ecole_salaire_avance.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');
dol_include_once('/personnel/class/ecole_employe.class.php');
dol_include_once('/salaires/core/lib/salaires.lib.php');

/**
 * Class EcoleSalaireAvance
 */
class EcoleSalaireAvance extends EcoleObject
{
	/** @var string */
	public $module = 'salaires';
	/** @var string */
	public $element = 'ecole_salaire_avance';
	/** @var string */
	public $table_element = 'ecole_salaire_avance';
	/** @var string */
	public $picto = 'fa-hand-holding-usd';
	/** @var string */
	public $dirpage = 'avance';
	/** @var string avance | pret (retenues sur les bulletins) */
	public $typeRetenue = 'avance';
	/** @var string Préfixe des numéros */
	public $prefixe = 'AV';
	/** @var string Colonne de la date */
	public $champDate = 'date_avance';

	const STATUS_ANNULE = 0;
	const STATUS_VERSE = 1;

	public $required = array('ref', 'fk_employe', 'date_avance', 'montant');
	public $unique = array('ref');
	public $listcolumns = array('ref', 'fk_employe', 'date_avance', 'montant', 'extra:retenu', 'extra:reste', 'fk_mode', 'extra:etat');
	public $sortdefault = 'rowid';

	public $ref;
	public $fk_employe;
	public $date_avance;
	public $montant = 0;
	public $fk_mode;
	public $fk_bank_account;
	public $reference_paiement;
	public $numero_compte;
	public $fk_salary;
	public $fk_payment_salary;
	public $note;
	public $motif_annulation;
	public $date_annulation;
	public $fk_user_annulation;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'NumeroAvance', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1),
		'fk_employe' => array('type' => 'integer:EcoleEmploye:personnel/class/ecole_employe.class.php', 'label' => 'Employe', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20,
			'searchtext' => array('table' => 'ecole_employe', 'cols' => array('ref', 'nom_fr', 'nom_ar'))),
		'date_avance' => array('type' => 'date', 'label' => 'DateAvance', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 30),
		'montant' => array('type' => 'price', 'label' => 'Montant', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 40, 'isameasure' => 1),
		'fk_mode' => array('type' => 'integer', 'label' => 'ModePaiement', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 50, 'searchoptions' => 'salaires_modes_paiement'),
		'fk_bank_account' => array('type' => 'integer', 'label' => 'CompteTresorerie', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 51),
		'reference_paiement' => array('type' => 'varchar(64)', 'label' => 'ReferencePaiement', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 52),
		'numero_compte' => array('type' => 'varchar(64)', 'label' => 'NumeroCompte', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 53),
		'fk_salary' => array('type' => 'integer', 'label' => 'SalaireDolibarr', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 53),
		'fk_payment_salary' => array('type' => 'integer', 'label' => 'PaiementDolibarr', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 54),
		'note' => array('type' => 'varchar(255)', 'label' => 'Note', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 60),
		'motif_annulation' => array('type' => 'varchar(255)', 'label' => 'MotifAnnulation', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 80),
		'date_annulation' => array('type' => 'datetime', 'label' => 'DateAnnulation', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 81),
		'fk_user_annulation' => array('type' => 'integer', 'label' => 'AnnulePar', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 82),
	);

	/**
	 * Statut : versée / annulée.
	 *
	 * @return array
	 */
	protected static function commonFields()
	{
		$f = parent::commonFields();
		$f['status']['label'] = 'Statut';
		$f['status']['arrayofkeyval'] = array(self::STATUS_VERSE => 'AvanceVersee', self::STATUS_ANNULE => 'AvanceAnnulee');
		return $f;
	}

	/**
	 * @param DoliDB $db Handler base
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->fields['fk_mode']['arrayofkeyval'] = salaires_modes_paiement($db);
	}

	/**
	 * @return array<int,string>
	 */
	public function statusOptions()
	{
		return array(self::STATUS_VERSE => 'AvanceVersee', self::STATUS_ANNULE => 'AvanceAnnulee');
	}

	/**
	 * État : en cours de retenue, entièrement retenue, annulée.
	 *
	 * @param int $status Statut
	 * @param int $mode   Mode d'affichage Dolibarr
	 * @return string
	 */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;
		$langs->load('salaires@salaires');
		if ((int) $status === self::STATUS_ANNULE) {
			$l = $langs->transnoentitiesnoconv('AvanceAnnulee');
			return dolGetStatus($l, $l, '', 'status9', $mode);
		}
		if ($this->id > 0 && $this->reste() <= 0.005) {
			$l = $langs->transnoentitiesnoconv('EntierementRetenu');
			return dolGetStatus($l, $l, '', 'status6', $mode);
		}
		$l = $langs->transnoentitiesnoconv('EnCoursDeRetenue');
		return dolGetStatus($l, $l, '', 'status1', $mode);
	}

	/**
	 * Affichage dans les listes : employé, montant, mode de paiement.
	 *
	 * @param array  $val       Définition du champ
	 * @param string $key       Nom du champ
	 * @param mixed  $value     Valeur
	 * @param string $moreparam Paramètres
	 * @param string $keysuffix Suffixe
	 * @param string $keyprefix Préfixe
	 * @param string $morecss   CSS
	 * @return string
	 */
	public function showOutputField($val, $key, $value, $moreparam = '', $keysuffix = '', $keyprefix = '', $morecss = '')
	{
		if ($key === 'fk_employe') {
			return salaires_employe_lien($this->db, (int) $value);
		}
		if ($key === 'fk_mode') {
			return dol_escape_htmltag(salaires_mode_label($this->db, (int) $value));
		}
		if ($key === 'montant') {
			return '<span class="nowraponall">'.salaires_montant($value).'</span>';
		}
		return parent::showOutputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss);
	}

	/**
	 * Lien vers la fiche (barré si annulée).
	 *
	 * @param int    $withpicto 1 = avec picto
	 * @param string $option    Non utilisé
	 * @param int    $notooltip Non utilisé
	 * @param string $morecss   CSS
	 * @return string
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '')
	{
		$url = dol_buildpath('/salaires/'.$this->dirpage.'/card.php', 1).'?id='.((int) $this->id);
		$out = '<a href="'.$url.'" class="'.$morecss.'"'.((int) $this->status === self::STATUS_ANNULE ? ' style="text-decoration:line-through"' : '').'>';
		if ($withpicto) {
			$out .= img_picto('', $this->picto, 'class="pictofixedwidth"');
		}
		return $out.dol_escape_htmltag((string) $this->ref).'</a>';
	}

	/**
	 * Prochain numéro : préfixe + compteur sur 5 chiffres.
	 *
	 * @return string
	 */
	public function getNextRef()
	{
		$sql = "SELECT MAX(CAST(RIGHT(ref, 5) AS UNSIGNED)) as maxi FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE entity IN (".getEntity($this->element).") AND ref REGEXP '[0-9]{5}$'";
		$resql = $this->db->query($sql);
		$o = $resql ? $this->db->fetch_object($resql) : null;
		return $this->prefixe.str_pad((string) (($o ? (int) $o->maxi : 0) + 1), 5, '0', STR_PAD_LEFT);
	}

	/**
	 * Montant vraiment retenu : bulletins validés ou payés (un brouillon ne retient encore rien).
	 *
	 * @return float
	 */
	public function retenu()
	{
		return salaires_retenu($this->db, $this->typeRetenue, (int) $this->id, 0, true);
	}

	/**
	 * Montant prévu sur des bulletins encore en brouillon (pas encore retenu).
	 *
	 * @return float
	 */
	public function prevuBrouillon()
	{
		return max(0, (float) price2num(salaires_retenu($this->db, $this->typeRetenue, (int) $this->id) - $this->retenu(), 'MT'));
	}

	/**
	 * Bulletins en brouillon qui prévoient une retenue (numéros).
	 *
	 * @return string[]
	 */
	public function brouillonsConcernes()
	{
		$out = array();
		foreach (salaires_retenues_liste($this->db, $this->typeRetenue, (int) $this->id) as $r) {
			if ((int) $r->status === EcoleSalaire::STATUS_BROUILLON && (float) $r->montant > 0) {
				$out[] = $r->ref;
			}
		}
		return $out;
	}

	/**
	 * Reste à retenir.
	 *
	 * @return float
	 */
	public function reste()
	{
		if ((int) $this->status === self::STATUS_ANNULE) {
			return 0.0;
		}
		return max(0, (float) price2num((float) $this->montant - $this->retenu(), 'MT'));
	}

	/**
	 * Contrôles propres (plafond de l'avance) ; à compléter par les sous-classes.
	 *
	 * @param  EcoleEmploye $emp  Employé
	 * @param  array        $data Données saisies
	 * @return string             Erreur traduite, '' si tout va bien
	 */
	protected function controlesPropres($emp, $data)
	{
		global $langs;
		$pct = (float) getDolGlobalString('SALAIRES_AVANCE_MAX_PCT', '0');
		if ($pct > 0 && $emp->mode_paie === 'fixe' && (float) $emp->salaire_base > 0) {
			$plafond = (float) $emp->salaire_base * $pct / 100;
			$enCours = 0;
			foreach (salaires_avances_prets_employe($this->db, (int) $emp->id, 'avance') as $a) {
				$enCours += $a->reste;
			}
			if ((float) $data['montant'] + $enCours > $plafond + 0.005) {
				return $langs->trans('ErrorPlafondAvance', price2num($pct), salaires_montant_texte($plafond), salaires_montant_texte($enCours));
			}
		}
		return '';
	}

	/**
	 * Remplit les champs propres avant l'enregistrement (sous-classes).
	 *
	 * @param  array $data Données saisies
	 * @return void
	 */
	protected function remplirPropres($data)
	{
	}

	/**
	 * Verse l'argent (salaire Dolibarr pour une avance) ; à redéfinir pour un prêt.
	 *
	 * @param  User         $user   Utilisateur
	 * @param  EcoleEmploye $emp    Employé
	 * @param  int          $compte Compte de trésorerie
	 * @param  string       $error  (sortie) erreur
	 * @return int                  1 si OK, -1 sinon
	 */
	protected function verser(User $user, $emp, $compte, &$error)
	{
		global $langs;
		$ids = salaires_verser_salaire($this->db, $user, $emp, array(
			'label' => $langs->transnoentities('LibelleAvanceDolibarr', $this->ref, $emp->ref),
			'note' => trim((string) $this->note.((string) $this->numero_compte !== '' ? ' — '.$langs->transnoentities('NumeroCompte').' : '.$this->numero_compte : ''), ' —'),
			'montant' => (float) $this->montant, 'date' => $this->date_avance, 'debut' => $this->date_avance, 'fin' => $this->date_avance,
			'mode' => (int) $this->fk_mode, 'compte' => $compte, 'reference' => $this->reference_paiement !== '' ? $this->reference_paiement : $this->ref, 'salaire' => 0), $error);
		if ($ids === null) {
			return -1;
		}
		$this->fk_salary = $ids[0];
		$this->fk_payment_salary = $ids[1];
		return 1;
	}

	/**
	 * Retire le versement (annulation) ; à redéfinir pour un prêt.
	 *
	 * @param  User   $user  Utilisateur
	 * @param  string $error (sortie) erreur
	 * @return int           1 si OK, -1 sinon
	 */
	protected function retirer(User $user, &$error)
	{
		return salaires_retirer_salaire($this->db, $user, (int) $this->fk_salary, (int) $this->fk_payment_salary, $error);
	}

	/**
	 * Donne l'avance (ou le prêt) : contrôles, numéro, versement dans le compte du mode de paiement.
	 *
	 * @param  User  $user Utilisateur
	 * @param  array $data fk_employe, date (horodatage), montant, fk_mode, reference, note (+ champs propres)
	 * @return int         Id, <0 si erreur
	 */
	public function donner(User $user, $data)
	{
		global $langs;
		$langs->loadLangs(array('salaires@salaires', 'errors'));
		$emp = new EcoleEmploye($this->db);
		if ((int) $data['fk_employe'] <= 0 || $emp->fetch((int) $data['fk_employe']) <= 0) {
			$this->error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('Employe'));
			return -1;
		}
		if (!$emp->estPresent()) {
			$this->error = $langs->trans('ErrorEmployeParti', $emp->ref);
			return -1;
		}
		$data['montant'] = (float) price2num($data['montant'], 'MT');
		if ($data['montant'] <= 0) {
			$this->error = $langs->trans('ErrorMontantPositif');
			return -1;
		}
		if (empty($data['date'])) {
			$this->error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('Date'));
			return -1;
		}
		if (dol_print_date($data['date'], '%Y-%m-%d') > personnel_aujourdhui()) {
			$this->error = $langs->trans('ErrorDateFuture');
			return -1;
		}
		$compte = 0;
		$numero = isset($data['numero_compte']) ? (string) $data['numero_compte'] : '';
		$err = salaires_controle_versement($this->db, $emp, (int) $data['fk_mode'], $this->typeRetenue === 'avance', $compte, $numero);
		if ($err === '') {
			$err = $this->controlesPropres($emp, $data);
		}
		if ($err !== '') {
			$this->error = $err;
			return -1;
		}

		$this->db->begin();
		$this->ref = $this->getNextRef();
		$this->fk_employe = (int) $emp->id;
		$champ = $this->champDate;
		$d = dol_getdate($data['date']);
		$this->$champ = dol_mktime(12, 0, 0, $d['mon'], $d['mday'], $d['year']);
		$this->montant = $data['montant'];
		$this->fk_mode = (int) $data['fk_mode'];
		$this->fk_bank_account = $compte > 0 ? $compte : null;
		$this->reference_paiement = trim((string) $data['reference']);
		$this->numero_compte = $numero;
		$this->note = trim((string) $data['note']);
		$this->status = self::STATUS_VERSE;
		$this->remplirPropres($data);
		$error = '';
		if ($this->createCommon($user) <= 0) {
			$this->db->rollback();
			$this->error = $this->error ? $this->error : implode('<br>', $this->errors);
			return -1;
		}
		if ($this->verser($user, $emp, $compte, $error) < 0 || $this->updateVersement() < 0) {
			$this->db->rollback();
			$this->error = $error ? $error : $this->db->lasterror();
			$this->id = 0;
			return -1;
		}
		$this->db->commit();
		return $this->id;
	}

	/**
	 * Enregistre les liens du versement (salaire et paiement Dolibarr, ou écriture de trésorerie).
	 *
	 * @return int 1 si OK, -1 sinon
	 */
	protected function updateVersement()
	{
		$sql = "UPDATE ".$this->db->prefix().$this->table_element." SET fk_salary = ".((int) $this->fk_salary > 0 ? (int) $this->fk_salary : "NULL");
		$sql .= ", fk_payment_salary = ".((int) $this->fk_payment_salary > 0 ? (int) $this->fk_payment_salary : "NULL")." WHERE rowid = ".((int) $this->id);
		return $this->db->query($sql) ? 1 : -1;
	}

	/**
	 * Annule (erreur de saisie) : seulement si rien n'a été retenu sur un bulletin non annulé. Le versement est
	 * retiré de la trésorerie ; la fiche reste visible (barrée) avec son motif.
	 *
	 * @param  User   $user  Utilisateur
	 * @param  string $motif Motif
	 * @return int           1 si OK, <0 sinon
	 */
	public function annuler(User $user, $motif)
	{
		global $langs;
		$langs->loadLangs(array('salaires@salaires', 'errors'));
		$motif = trim((string) $motif);
		if ((int) $this->status !== self::STATUS_VERSE) {
			$this->error = $langs->trans('ErrorDejaAnnule');
			return -1;
		}
		if ($motif === '') {
			$this->error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('Motif'));
			return -1;
		}
		if ($this->retenu() > 0) {
			$this->error = $langs->trans('ErrorDejaRetenu');
			return -1;
		}
		if ($this->prevuBrouillon() > 0) {
			// Une retenue est prévue sur un brouillon : elle serait faite à la validation de ce bulletin
			$this->error = $langs->trans('ErrorRetenueBrouillon', implode(', ', $this->brouillonsConcernes()));
			return -1;
		}
		$this->db->begin();
		$error = '';
		if ($this->retirer($user, $error) < 0) {
			$this->db->rollback();
			$this->error = $error;
			return -1;
		}
		$sql = "UPDATE ".$this->db->prefix().$this->table_element." SET status = ".self::STATUS_ANNULE.", motif_annulation = '".$this->db->escape(dol_trunc($motif, 250, 'right', 'UTF-8', 1))."'";
		$sql .= ", date_annulation = '".$this->db->idate(dol_now())."', fk_user_annulation = ".((int) $user->id).", fk_user_modif = ".((int) $user->id)." WHERE rowid = ".((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->db->rollback();
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->db->commit();
		$this->fetch($this->id);
		return 1;
	}

	/**
	 * Ne se supprime jamais (s'annule).
	 *
	 * @param User $user      Utilisateur
	 * @param int  $notrigger 1 = sans trigger
	 * @return int
	 */
	public function delete(User $user, $notrigger = 0)
	{
		global $langs;
		$this->error = $langs->trans('ErrorNonSupprimable');
		return -1;
	}
}
