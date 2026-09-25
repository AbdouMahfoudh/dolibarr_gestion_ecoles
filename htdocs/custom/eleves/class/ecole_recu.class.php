<?php
/**
 * Reçu de paiement : ce qu'un parent a payé en une fois, pour un élève ou pour toute la fratrie.
 *
 * Côté école : frais d'inscription, mensualités, autres frais, reçu numéroté.
 * En coulisses (comptabilité Dolibarr) : pour chaque élève du reçu, une facture déjà payée sur le
 * Tiers de l'élève, un paiement enregistré dans le compte de trésorerie du mode de paiement.
 * Une erreur se corrige en annulant le reçu (avec motif) : rien n'est effacé.
 *
 * Fichier : custom/eleves/class/ecole_recu.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');
dol_include_once('/eleves/class/ecole_eleve.class.php');
dol_include_once('/eleves/core/lib/paiements.lib.php');

/**
 * Class EcoleRecu
 */
class EcoleRecu extends EcoleObject
{
	/** @var string */
	public $module = 'eleves';
	/** @var string */
	public $element = 'ecole_recu';
	/** @var string */
	public $table_element = 'ecole_recu';
	/** @var string */
	public $picto = 'fa-receipt';
	/** @var string */
	public $dirpage = 'recu';

	const STATUS_ANNULE = 0;
	const STATUS_VALIDE = 1;

	public $required = array('ref', 'date_recu', 'fk_mode');
	public $unique = array('ref');
	public $listcolumns = array('ref', 'date_recu', 'extra:payeur', 'extra:eleves', 'fk_mode', 'montant', 'status');
	public $searchcolumns = array('ref', 'fk_mode');
	public $sortdefault = 'rowid';

	public $ref;
	public $date_recu;
	public $fk_responsable;
	public $fk_mode;
	public $fk_bank_account;
	public $reference_paiement;
	public $montant = 0;
	public $note;
	public $motif_annulation;
	public $date_annulation;
	public $fk_user_annulation;

	/** @var array Lignes du reçu (objets SQL) */
	public $lignes = array();

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'NumeroRecu', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1),
		'date_recu' => array('type' => 'date', 'label' => 'DatePaiement', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20),
		'fk_responsable' => array('type' => 'integer:EcoleResponsable:eleves/class/ecole_responsable.class.php', 'label' => 'Responsable', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 30),
		'fk_mode' => array('type' => 'integer', 'label' => 'ModePaiement', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 40, 'searchoptions' => 'eleves_search_modes'),
		'fk_bank_account' => array('type' => 'integer', 'label' => 'CompteTresorerie', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 45),
		'reference_paiement' => array('type' => 'varchar(64)', 'label' => 'ReferencePaiement', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 50),
		'montant' => array('type' => 'price', 'label' => 'Montant', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 60, 'isameasure' => 1),
		'note' => array('type' => 'varchar(255)', 'label' => 'Note', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 70),
		'motif_annulation' => array('type' => 'varchar(255)', 'label' => 'MotifAnnulation', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 80),
		'date_annulation' => array('type' => 'datetime', 'label' => 'DateAnnulation', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 81),
		'fk_user_annulation' => array('type' => 'integer', 'label' => 'AnnulePar', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 82),
	);

	/**
	 * Statut : valide / annulé.
	 *
	 * @return array
	 */
	protected static function commonFields()
	{
		$f = parent::commonFields();
		$f['status']['label'] = 'StatutRecu';
		$f['status']['arrayofkeyval'] = array(self::STATUS_VALIDE => 'RecuValide', self::STATUS_ANNULE => 'RecuAnnule');
		return $f;
	}

	/**
	 * @return array<int,string>
	 */
	public function statusOptions()
	{
		return array(self::STATUS_VALIDE => 'RecuValide', self::STATUS_ANNULE => 'RecuAnnule');
	}

	/**
	 * @param int $status Statut
	 * @param int $mode   Mode d'affichage Dolibarr
	 * @return string
	 */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;
		$langs->load('eleves@eleves');
		$label = $langs->transnoentitiesnoconv(((int) $status === self::STATUS_VALIDE) ? 'RecuValide' : 'RecuAnnule');
		return dolGetStatus($label, $label, '', ((int) $status === self::STATUS_VALIDE) ? 'status4' : 'status8', $mode);
	}

	/**
	 * Affichage : mode de paiement.
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
		if ($key === 'fk_mode') {
			return dol_escape_htmltag(eleves_mode_label($this->db, (int) $value));
		}
		if ($key === 'montant') {
			return eleves_montant($value);
		}
		return parent::showOutputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss);
	}

	/**
	 * @param DoliDB $db Handler base
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		// Filtre de la liste : modes de paiement en liste déroulante
		$this->fields['fk_mode']['arrayofkeyval'] = eleves_modes_paiement($db);
	}

	/**
	 * Prochain numéro de reçu : préfixe (ELEVES_RECU_PREFIXE, défaut R) + compteur (ELEVES_RECU_LONGUEUR chiffres, défaut 6).
	 *
	 * @return string
	 */
	public function getNextRef()
	{
		$len = min(8, max(3, getDolGlobalInt('ELEVES_RECU_LONGUEUR', 6)));
		$sql = "SELECT MAX(CAST(RIGHT(ref, ".$len.") AS UNSIGNED)) as maxi FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE entity IN (".getEntity($this->element).") AND ref REGEXP '[0-9]{".$len."}$'";
		$resql = $this->db->query($sql);
		$o = $resql ? $this->db->fetch_object($resql) : null;
		return getDolGlobalString('ELEVES_RECU_PREFIXE', 'R').str_pad((string) (($o ? (int) $o->maxi : 0) + 1), $len, '0', STR_PAD_LEFT);
	}

	/**
	 * Charge le reçu et ses lignes.
	 *
	 * @param int         $id  Id
	 * @param string|null $ref Numéro
	 * @return int
	 */
	public function fetch($id, $ref = null)
	{
		$res = parent::fetch($id, $ref);
		if ($res > 0) {
			$this->fetchLignes();
		}
		return $res;
	}

	/**
	 * Lignes du reçu, groupées ensuite par élève à l'affichage.
	 *
	 * @return void
	 */
	public function fetchLignes()
	{
		$this->lignes = array();
		$sql = "SELECT l.rowid, l.fk_eleve, l.type, l.periode, l.fk_frais_type, l.libelle, l.montant, l.status, e.ref as eleve_ref, e.nom_fr, e.nom_ar, e.fk_classe";
		$sql .= " FROM ".$this->db->prefix()."ecole_paiement l LEFT JOIN ".$this->db->prefix()."ecole_eleve e ON e.rowid = l.fk_eleve";
		$sql .= " WHERE l.fk_recu = ".((int) $this->id)." ORDER BY e.nom_fr, l.fk_eleve, FIELD(l.type, 'inscription', 'mensualite', 'autre'), l.periode, l.rowid";
		$resql = $this->db->query($sql);
		while ($resql && ($o = $this->db->fetch_object($resql))) {
			$this->lignes[] = $o;
		}
	}

	/**
	 * Enregistre un encaissement : reçu + lignes, et en coulisses pour chaque élève une facture Dolibarr
	 * déjà payée (paiement dans le compte de trésorerie du mode).
	 *
	 * @param  User  $user Utilisateur
	 * @param  array $data date, fk_mode, reference_paiement, note, fk_responsable,
	 *                     lignes = liste de (fk_eleve, type, periode, fk_frais_type, libelle, montant)
	 * @return int         Id du reçu, <0 si erreur
	 */
	public function encaisser(User $user, $data)
	{
		global $conf, $langs;
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
		require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php'; // AccountLine (écritures de trésorerie)
		$langs->loadLangs(array('eleves@eleves', 'bills', 'banks'));
		$this->errors = array();

		$modes = eleves_modes_paiement($this->db);
		$mode = (int) $data['fk_mode'];
		if (!isset($modes[$mode])) {
			$this->errors[] = $langs->trans('ErrorFieldRequired', $langs->transnoentities('ModePaiement'));
		}
		$compte = eleves_compte_mode($mode);
		if (isModEnabled('banque') && $mode > 0 && $compte <= 0) {
			$this->errors[] = $langs->trans('ErrorAucunCompteMode', isset($modes[$mode]) ? $modes[$mode] : $mode);
		}
		// Lignes groupées par élève
		$parEleve = array();
		foreach ($data['lignes'] as $l) {
			if ((float) $l['montant'] > 0) {
				$parEleve[(int) $l['fk_eleve']][] = $l;
			}
		}
		if (empty($parEleve)) {
			$this->errors[] = $langs->trans('ErrorAucunMontant');
		}
		$eleves = array();
		foreach (array_keys($parEleve) as $eid) {
			$e = new EcoleEleve($this->db);
			if ($e->fetch($eid) <= 0) {
				$this->errors[] = $langs->trans('ErrorRecordNotFound');
				continue;
			}
			if (empty($e->fk_soc) && $e->syncTiers($user) < 0) {
				$this->errors[] = $e->error;
			}
			$eleves[$eid] = $e;
		}
		if ($this->errors) {
			$this->error = implode('<br>', $this->errors);
			return -1;
		}

		$this->db->begin();
		$this->ref = $this->getNextRef();
		$d = dol_getdate($data['date'] ? $data['date'] : dol_now());
		$this->date_recu = dol_mktime(12, 0, 0, $d['mon'], $d['mday'], $d['year']); // date sans heure : midi, comme Dolibarr
		$this->fk_mode = $mode;
		$this->fk_bank_account = $compte > 0 ? $compte : null;
		$this->fk_responsable = !empty($data['fk_responsable']) ? (int) $data['fk_responsable'] : null;
		$this->reference_paiement = (string) $data['reference_paiement'];
		$this->note = (string) $data['note'];
		$this->status = self::STATUS_VALIDE;
		$total = 0;
		foreach ($parEleve as $lignes) {
			foreach ($lignes as $l) {
				$total += (float) $l['montant'];
			}
		}
		$this->montant = price2num($total, 'MT');
		if (empty($this->fk_responsable) && count($eleves) === 1) {
			$this->fk_responsable = (int) reset($eleves)->fk_responsable;
		}

		$error = 0;
		if ($this->createCommon($user) <= 0) {
			$error++;
		}
		foreach ($parEleve as $eid => $lignes) {
			if ($error) {
				break;
			}
			$e = $eleves[$eid];
			// Facture déjà payée sur le Tiers de l'élève (comptabilité, invisible côté école)
			$fac = new Facture($this->db);
			$fac->socid = (int) $e->fk_soc;
			$fac->type = Facture::TYPE_STANDARD;
			$fac->date = $this->date_recu;
			$fac->mode_reglement_id = $mode;
			$fac->cond_reglement_id = 1;
			$fac->ref_client = $this->ref;
			$fac->note_private = $langs->transnoentities('NoteFactureRecu', $this->ref, $e->ref);
			if ($compte > 0) {
				$fac->fk_account = $compte;
			}
			if ($fac->create($user) <= 0) {
				$this->errors = array_merge(array($langs->trans('ErrorCreationComptable')), (array) $fac->errors, array($fac->error));
				$error++;
				break;
			}
			foreach ($lignes as $l) {
				if ($fac->addline($l['libelle'], (float) $l['montant'], 1, 0) <= 0) {
					$this->errors = array($langs->trans('ErrorCreationComptable'), $fac->error);
					$error++;
					break 2;
				}
			}
			if ($fac->validate($user) <= 0) {
				$this->errors = array_merge(array($langs->trans('ErrorCreationComptable')), (array) $fac->errors, array($fac->error));
				$error++;
				break;
			}
			$fac->fetch($fac->id);
			$pay = new Paiement($this->db);
			$pay->datepaye = $this->date_recu;
			$pay->amounts = array($fac->id => (float) price2num($fac->total_ttc, 'MT'));
			$pay->multicurrency_amounts = array();
			$pay->paiementid = $mode;
			$pay->num_payment = $this->reference_paiement !== '' ? $this->reference_paiement : $this->ref;
			$pay->note_private = $langs->transnoentities('NoteFactureRecu', $this->ref, $e->ref);
			$pid = $pay->create($user, 1);
			if ($pid <= 0) {
				$this->errors = array_merge(array($langs->trans('ErrorCreationComptable')), (array) $pay->errors, array($pay->error));
				$error++;
				break;
			}
			if (isModEnabled('banque') && $compte > 0) {
				if ($pay->addPaymentToBank($user, 'payment', '(CustomerInvoicePayment)', $compte, '', '') <= 0) {
					$this->errors = array_merge(array($langs->trans('ErrorCreationComptable')), (array) $pay->errors, array($pay->error));
					$error++;
					break;
				}
			}
			foreach ($lignes as $l) {
				$sql = "INSERT INTO ".$this->db->prefix()."ecole_paiement (entity, fk_recu, fk_eleve, type, periode, fk_frais_type, libelle, montant, fk_facture, fk_paiement, status)";
				$sql .= " VALUES (".((int) $conf->entity).", ".((int) $this->id).", ".((int) $eid).", '".$this->db->escape($l['type'])."'";
				$sql .= ", ".(!empty($l['periode']) ? "'".$this->db->escape($l['periode'])."'" : "NULL");
				$sql .= ", ".(!empty($l['fk_frais_type']) ? (int) $l['fk_frais_type'] : "NULL");
				$sql .= ", '".$this->db->escape(dol_trunc($l['libelle'], 250, 'right', 'UTF-8', 1))."', ".((float) price2num($l['montant'], 'MT'));
				$sql .= ", ".((int) $fac->id).", ".((int) $pid).", 1)";
				if (!$this->db->query($sql)) {
					$this->errors = array($this->db->lasterror());
					$error++;
					break 2;
				}
			}
		}

		if ($error) {
			$this->db->rollback();
			$this->error = implode('<br>', array_filter($this->errors));
			$this->id = 0;
			return -1;
		}
		$this->db->commit();
		$this->fetchLignes();
		return $this->id;
	}

	/**
	 * Annule le reçu (erreur de saisie) : les paiements Dolibarr sont retirés de la trésorerie, les factures
	 * correspondantes sont classées « abandonnées » avec le motif, les mois redeviennent impayés.
	 * Le reçu reste visible (barré) avec son motif.
	 *
	 * @param  User   $user  Utilisateur
	 * @param  string $motif Motif (obligatoire)
	 * @return int           1 si OK, <0 sinon
	 */
	public function annuler(User $user, $motif)
	{
		global $langs;
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
		require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php'; // AccountLine (écritures de trésorerie)
		$langs->loadLangs(array('eleves@eleves', 'bills', 'banks', 'errors'));

		$motif = trim((string) $motif);
		if ((int) $this->status !== self::STATUS_VALIDE) {
			$this->error = $langs->trans('ErrorRecuDejaAnnule');
			return -1;
		}
		if ($motif === '') {
			$this->error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('MotifAnnulation'));
			return -1;
		}
		$couples = array();
		$resql = $this->db->query("SELECT DISTINCT fk_facture, fk_paiement FROM ".$this->db->prefix()."ecole_paiement WHERE fk_recu = ".((int) $this->id));
		while ($resql && ($o = $this->db->fetch_object($resql))) {
			$couples[] = $o;
		}

		$this->db->begin();
		foreach ($couples as $c) {
			$fac = new Facture($this->db);
			if ($c->fk_facture > 0 && $fac->fetch((int) $c->fk_facture) > 0 && (int) $fac->status === Facture::STATUS_CLOSED) {
				if ($fac->setUnpaid($user) < 0) {
					$this->db->rollback();
					$this->error = $langs->trans('ErrorAnnulationImpossible').' '.$fac->error;
					return -1;
				}
			}
			$pay = new Paiement($this->db);
			if ($c->fk_paiement > 0 && $pay->fetch((int) $c->fk_paiement) > 0) {
				if ($pay->delete($user) <= 0) {
					$this->db->rollback();
					$this->error = $langs->trans('ErrorAnnulationImpossible').' '.$langs->trans($pay->error);
					return -1;
				}
			}
			if ($c->fk_facture > 0 && $fac->fetch((int) $c->fk_facture) > 0 && (int) $fac->status === Facture::STATUS_VALIDATED) {
				if ($fac->setCanceled($user, Facture::CLOSECODE_ABANDONED, $langs->transnoentities('NoteRecuAnnule', $this->ref, $motif)) <= 0) {
					$this->db->rollback();
					$this->error = $langs->trans('ErrorAnnulationImpossible').' '.$fac->error;
					return -1;
				}
			}
		}
		$ok = $this->db->query("UPDATE ".$this->db->prefix()."ecole_paiement SET status = 0 WHERE fk_recu = ".((int) $this->id));
		$sql = "UPDATE ".$this->db->prefix().$this->table_element." SET status = ".self::STATUS_ANNULE.", motif_annulation = '".$this->db->escape(dol_trunc($motif, 250, 'right', 'UTF-8', 1))."'";
		$sql .= ", date_annulation = '".$this->db->idate(dol_now())."', fk_user_annulation = ".((int) $user->id)." WHERE rowid = ".((int) $this->id);
		if (!$ok || !$this->db->query($sql)) {
			$this->db->rollback();
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->db->commit();
		$this->status = self::STATUS_ANNULE;
		$this->motif_annulation = $motif;
		return 1;
	}

	/**
	 * Causes qui empêchent l'annulation du reçu : un de ses paiements est déjà rapproché en banque
	 * (Dolibarr refuse alors de le retirer). Sert à griser le bouton « Annuler le reçu ».
	 *
	 * @return string[]
	 */
	public function getCancelBlockers()
	{
		global $langs;
		$langs->load('eleves@eleves');
		$sql = "SELECT COUNT(*) as nb FROM ".$this->db->prefix()."ecole_paiement as ep";
		$sql .= " INNER JOIN ".$this->db->prefix()."paiement as p ON p.rowid = ep.fk_paiement";
		$sql .= " INNER JOIN ".$this->db->prefix()."bank as b ON b.rowid = p.fk_bank";
		$sql .= " WHERE ep.fk_recu = ".((int) $this->id)." AND b.rappro = 1";
		$resql = $this->db->query($sql);
		$o = $resql ? $this->db->fetch_object($resql) : null;
		return ($o && (int) $o->nb > 0) ? array($langs->trans('ErrorRecuRapproche')) : array();
	}

	/**
	 * Un reçu ne se supprime jamais (il s'annule).
	 *
	 * @param User $user      Utilisateur
	 * @param int  $notrigger 1 = sans trigger
	 * @return int
	 */
	public function delete(User $user, $notrigger = 0)
	{
		global $langs;
		$this->error = $langs->trans('ErrorRecuNonSupprimable');
		return -1;
	}

	/**
	 * Lien vers la fiche du reçu (barré s'il est annulé).
	 *
	 * @param int    $withpicto 1 = avec picto
	 * @param string $option    Non utilisé
	 * @param int    $notooltip Non utilisé
	 * @param string $morecss   CSS
	 * @return string
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '')
	{
		$url = dol_buildpath('/eleves/recu/card.php', 1).'?id='.((int) $this->id);
		$out = '<a href="'.$url.'" class="'.$morecss.'"'.((int) $this->status === self::STATUS_ANNULE ? ' style="text-decoration:line-through"' : '').'>';
		if ($withpicto) {
			$out .= img_picto('', $this->picto, 'class="pictofixedwidth"');
		}
		return $out.dol_escape_htmltag((string) $this->ref).'</a>';
	}
}
