<?php
/**
 * Prêt au personnel : somme remboursée en plusieurs mensualités, retenues automatiquement sur les bulletins de
 * paie (une mensualité par bulletin, à partir du premier mois choisi).
 *
 * - Versement : écriture de sortie dans le compte de trésorerie du mode de paiement (un prêt n'est pas un salaire).
 * - Retenue : ligne « Remboursement de prêt » au calcul de chaque bulletin ; montant modifiable sur le bulletin
 *   (mois sauté, remboursement plus fort) : le reste passe aux bulletins suivants.
 * - Réglages : un seul prêt en cours par employé, mensualité au plus égale à un pourcentage du salaire, nombre de
 *   mensualités au plus (Configuration des salaires).
 * - Annulation (motif) : seulement tant que rien n'a été retenu.
 *
 * Fichier : custom/salaires/class/ecole_salaire_pret.class.php
 */

dol_include_once('/salaires/class/ecole_salaire_avance.class.php');

/**
 * Class EcoleSalairePret
 */
class EcoleSalairePret extends EcoleSalaireAvance
{
	/** @var string */
	public $element = 'ecole_salaire_pret';
	/** @var string */
	public $table_element = 'ecole_salaire_pret';
	/** @var string */
	public $picto = 'fa-piggy-bank';
	/** @var string */
	public $dirpage = 'pret';
	/** @var string */
	public $typeRetenue = 'pret';
	/** @var string */
	public $prefixe = 'PR';
	/** @var string */
	public $champDate = 'date_pret';

	public $required = array('ref', 'fk_employe', 'date_pret', 'montant', 'nb_echeances', 'premier_mois');
	public $listcolumns = array('ref', 'fk_employe', 'date_pret', 'montant', 'extra:mensualite', 'extra:retenu', 'extra:reste', 'extra:etat');

	public $date_pret;
	public $nb_echeances = 1;
	public $montant_echeance = 0;
	public $premier_mois;
	public $fk_bank;

	/**
	 * @param DoliDB $db Handler base
	 */
	public function __construct($db)
	{
		$f = $this->fields;
		unset($f['date_avance'], $f['fk_salary'], $f['fk_payment_salary']);
		$f['ref']['label'] = 'NumeroPret';
		$f['date_pret'] = array('type' => 'date', 'label' => 'DatePret', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 30);
		$f['nb_echeances'] = array('type' => 'integer', 'label' => 'NbMensualites', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '1', 'position' => 41);
		$f['montant_echeance'] = array('type' => 'price', 'label' => 'Mensualite', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 42);
		$f['premier_mois'] = array('type' => 'varchar(7)', 'label' => 'PremierMoisRetenue', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 43);
		$f['fk_bank'] = array('type' => 'integer', 'label' => 'EcritureTresorerie', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 55);
		$this->fields = $f;
		parent::__construct($db);
		$this->fields['status']['arrayofkeyval'] = array(self::STATUS_VERSE => 'PretVerse', self::STATUS_ANNULE => 'PretAnnule');
	}

	/**
	 * @return array<int,string>
	 */
	public function statusOptions()
	{
		return array(self::STATUS_VERSE => 'PretVerse', self::STATUS_ANNULE => 'PretAnnule');
	}

	/**
	 * État : en cours de remboursement, remboursé, annulé.
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
			$l = $langs->transnoentitiesnoconv('PretAnnule');
			return dolGetStatus($l, $l, '', 'status9', $mode);
		}
		if ($this->id > 0 && $this->reste() <= 0.005) {
			$l = $langs->transnoentitiesnoconv('Rembourse');
			return dolGetStatus($l, $l, '', 'status6', $mode);
		}
		$l = $langs->transnoentitiesnoconv('EnCoursDeRemboursement');
		return dolGetStatus($l, $l, '', 'status1', $mode);
	}

	/**
	 * Mensualité proposée : montant ÷ nombre de mensualités, arrondi à l'unité supérieure (la dernière est plus petite).
	 *
	 * @param  float $montant Montant du prêt
	 * @param  int   $nb      Nombre de mensualités
	 * @return float
	 */
	public static function mensualite($montant, $nb)
	{
		return (float) ceil((float) $montant / max(1, (int) $nb));
	}

	/**
	 * Contrôles : nombre de mensualités, premier mois, un seul prêt en cours, plafond de la mensualité.
	 *
	 * @param  EcoleEmploye $emp  Employé
	 * @param  array        $data Données saisies
	 * @return string             Erreur traduite, '' si tout va bien
	 */
	protected function controlesPropres($emp, $data)
	{
		global $langs;
		$nb = (int) $data['nb_echeances'];
		$max = max(1, getDolGlobalInt('SALAIRES_PRET_ECHEANCES_MAX', 24));
		if ($nb < 1 || $nb > $max) {
			return $langs->trans('ErrorNbMensualites', $max);
		}
		if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $data['premier_mois']) || $data['premier_mois'] < dol_print_date($data['date'], '%Y-%m')) {
			return $langs->trans('ErrorPremierMois');
		}
		if (getDolGlobalString('SALAIRES_PRET_UN_SEUL', '1')) {
			foreach (salaires_avances_prets_employe($this->db, (int) $emp->id, 'pret') as $p) {
				if ($p->reste > 0.005) {
					return $langs->trans('ErrorPretEnCours', $p->ref, salaires_montant_texte($p->reste));
				}
			}
		}
		$pct = (float) getDolGlobalString('SALAIRES_PRET_MAX_PCT', '0');
		$salaire = ($emp->mode_paie === 'fixe') ? (float) $emp->salaire_base : 0;
		$mens = !empty($data['montant_echeance']) ? (float) price2num($data['montant_echeance'], 'MT') : self::mensualite($data['montant'], $nb);
		if ($pct > 0 && $salaire > 0 && $mens > $salaire * $pct / 100 + 0.005) {
			return $langs->trans('ErrorPlafondMensualite', salaires_montant_texte($mens), price2num($pct), salaires_montant_texte($salaire * $pct / 100));
		}
		if ($mens <= 0 || $mens > (float) $data['montant'] + 0.005) {
			return $langs->trans('ErrorMensualite');
		}
		return '';
	}

	/**
	 * Mensualités, premier mois.
	 *
	 * @param  array $data Données saisies
	 * @return void
	 */
	protected function remplirPropres($data)
	{
		$this->nb_echeances = (int) $data['nb_echeances'];
		$this->montant_echeance = !empty($data['montant_echeance']) ? (float) price2num($data['montant_echeance'], 'MT') : self::mensualite($data['montant'], $this->nb_echeances);
		$this->premier_mois = (string) $data['premier_mois'];
	}

	/**
	 * Verse le prêt : écriture de sortie dans le compte de trésorerie.
	 *
	 * @param  User         $user   Utilisateur
	 * @param  EcoleEmploye $emp    Employé
	 * @param  int          $compte Compte
	 * @param  string       $error  (sortie) erreur
	 * @return int                  1 si OK, -1 sinon
	 */
	protected function verser(User $user, $emp, $compte, &$error)
	{
		global $langs;
		$id = salaires_verser_banque($this->db, $user, $emp, array(
			'label' => $langs->transnoentities('LibellePretBanque', $this->ref, $emp->ref).((string) $this->numero_compte !== '' ? ' — '.$this->numero_compte : ''),
			'montant' => (float) $this->montant, 'date' => $this->date_pret, 'mode' => (int) $this->fk_mode, 'compte' => $compte,
			'reference' => $this->reference_paiement !== '' ? $this->reference_paiement : $this->ref), $error);
		if ($id < 0) {
			return -1;
		}
		$this->fk_bank = $id;
		return 1;
	}

	/**
	 * Lien du versement.
	 *
	 * @return int 1 si OK, -1 sinon
	 */
	protected function updateVersement()
	{
		$sql = "UPDATE ".$this->db->prefix().$this->table_element." SET fk_bank = ".((int) $this->fk_bank > 0 ? (int) $this->fk_bank : "NULL")." WHERE rowid = ".((int) $this->id);
		return $this->db->query($sql) ? 1 : -1;
	}

	/**
	 * Retire l'écriture de trésorerie du prêt.
	 *
	 * @param  User   $user  Utilisateur
	 * @param  string $error (sortie) erreur
	 * @return int           1 si OK, -1 sinon
	 */
	protected function retirer(User $user, &$error)
	{
		return salaires_retirer_banque($this->db, $user, (int) $this->fk_bank, $error);
	}

	/**
	 * Échéancier : mensualités déjà retenues (bulletins validés ou payés) puis mensualités prévues pour le reste,
	 * mois par mois après le dernier bulletin validé (ou à partir du premier mois). Une mensualité sur un bulletin
	 * encore en brouillon est comptée dans les mensualités prévues.
	 *
	 * @return array{retenues:array,prevues:array}
	 */
	public function echeancier()
	{
		$retenues = salaires_retenues_liste($this->db, 'pret', (int) $this->id);
		$prevues = array();
		$reste = $this->reste();
		$mois = (string) $this->premier_mois;
		$retenues = array_values(array_filter($retenues, function ($r) {
			return in_array((int) $r->status, array(EcoleSalaire::STATUS_VALIDE, EcoleSalaire::STATUS_PAYE), true);
		}));
		foreach ($retenues as $r) {
			if ((float) $r->montant > 0) {
				$m = $r->mois ? (string) $r->mois : dol_print_date($this->db->jdate($r->date_fin), '%Y-%m');
				if ($m >= $mois) {
					$mois = date('Y-m', strtotime($m.'-01 +1 month'));
				}
			}
		}
		for ($i = 0; $reste > 0.005 && $i < 240; $i++) {
			$m = min((float) $this->montant_echeance, $reste);
			$prevues[] = array('mois' => $mois, 'montant' => $m);
			$reste -= $m;
			$mois = date('Y-m', strtotime($mois.'-01 +1 month'));
		}
		return array('retenues' => $retenues, 'prevues' => $prevues);
	}
}
