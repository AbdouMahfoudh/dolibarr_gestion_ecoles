<?php
/**
 * Bulletin de paie d'un employé pour une période libre (du … au …).
 *
 * - Deux bulletins non annulés d'un même employé n'ont jamais de dates communes (une séance n'est jamais payée
 *   deux fois) ; un trou entre deux bulletins est seulement signalé.
 * - Calcul depuis la présence du module Personnel (personnel_heures_periode + personnel_paie_lignes) selon les
 *   règles de l'employé : salaire fixe ou heures faites, heures supplémentaires, remplacements, retenues.
 *   Les lignes ajoutées à la main et les montants modifiés à la main sont gardés au recalcul.
 * - Brouillon → validé (visible par l'employé) → payé (salaire natif de Dolibarr + écriture dans le compte de
 *   trésorerie du mode de paiement). Rouvrir un bulletin validé, annuler un paiement ou un bulletin : avec motif.
 *   Rien n'est effacé, tout est gardé dans l'historique.
 *
 * Fichier : custom/salaires/class/ecole_salaire.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');
dol_include_once('/personnel/class/ecole_employe.class.php');
dol_include_once('/personnel/core/lib/presence.lib.php');
dol_include_once('/salaires/core/lib/salaires.lib.php');

/**
 * Class EcoleSalaire
 */
class EcoleSalaire extends EcoleObject
{
	/** @var string */
	public $module = 'salaires';
	/** @var string */
	public $element = 'ecole_salaire';
	/** @var string */
	public $table_element = 'ecole_salaire';
	/** @var string */
	public $picto = 'fa-money-check-alt';
	/** @var string */
	public $dirpage = 'bulletin';

	const STATUS_BROUILLON = 0;
	const STATUS_VALIDE = 1;
	const STATUS_PAYE = 2;
	const STATUS_ANNULE = 9;

	/** Période la plus longue d'un bulletin (jours) */
	const DUREE_MAX = 366;

	public $required = array('ref', 'fk_employe', 'date_debut', 'date_fin');
	public $unique = array('ref');
	public $listcolumns = array('ref', 'fk_employe', 'mois', 'date_debut', 'date_fin', 'mode_paie', 'total_gains', 'total_retenues', 'net', 'fk_lot', 'status');
	public $sortdefault = 'rowid';

	public $ref;
	public $fk_employe;
	public $fk_lot;
	public $date_debut;
	public $date_fin;
	public $mois;
	public $numero_compte;
	public $mode_paie;
	public $salaire_base;
	public $taux_horaire;
	public $heures_semaine;
	public $taux_heure_sup;
	public $minutes_prevues;
	public $minutes_faites;
	public $minutes_payees;
	public $minutes_absent_nj;
	public $minutes_absent_j;
	public $minutes_retard;
	public $minutes_hsup;
	public $minutes_rempl;
	public $nb_remplacements;
	public $jours_ouvrables;
	public $jours_absent_nj;
	public $jours_absent_j;
	public $calcul;
	public $total_gains = 0;
	public $total_retenues = 0;
	public $net = 0;
	public $date_calcul;
	public $date_validation;
	public $fk_user_valid;
	public $date_paiement;
	public $fk_mode;
	public $fk_bank_account;
	public $reference_paiement;
	public $fk_salary;
	public $fk_payment_salary;
	public $fk_user_paie;
	public $motif_annulation;
	public $date_annulation;
	public $fk_user_annulation;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'NumeroBulletin', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1),
		'fk_employe' => array('type' => 'integer:EcoleEmploye:personnel/class/ecole_employe.class.php', 'label' => 'Employe', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20,
			'searchtext' => array('table' => 'ecole_employe', 'cols' => array('ref', 'nom_fr', 'nom_ar'))),
		'fk_lot' => array('type' => 'integer:EcoleSalaireLot:salaires/class/ecole_salaire_lot.class.php', 'label' => 'LotSalaires', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 25),
		'date_debut' => array('type' => 'date', 'label' => 'PeriodeDu', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 30),
		'date_fin' => array('type' => 'date', 'label' => 'PeriodeAu', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 31),
		'mois' => array('type' => 'varchar(7)', 'label' => 'MoisSalaire', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 29, 'searchoptions' => 'salaires_mois_choix'),
		'mode_paie' => array('type' => 'varchar(8)', 'label' => 'ModeDePaie', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 40,
			'arrayofkeyval' => array('fixe' => 'ModeFixe', 'heure' => 'ModeHeure')),
		'salaire_base' => array('type' => 'price', 'label' => 'SalaireMensuel', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 41),
		'taux_horaire' => array('type' => 'price', 'label' => 'PrixHeure', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 42),
		'heures_semaine' => array('type' => 'double(24,8)', 'label' => 'HeuresPrevuesSemaine', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 43),
		'taux_heure_sup' => array('type' => 'price', 'label' => 'PrixHeureSup', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 44),
		'minutes_prevues' => array('type' => 'integer', 'label' => 'CoursPrevus', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 50),
		'minutes_faites' => array('type' => 'integer', 'label' => 'CoursFaits', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 51),
		'minutes_payees' => array('type' => 'integer', 'label' => 'HeuresPayees', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 52),
		'minutes_absent_nj' => array('type' => 'integer', 'label' => 'AbsencesNonJustifiees', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 53),
		'minutes_absent_j' => array('type' => 'integer', 'label' => 'AbsencesJustifiees', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 54),
		'minutes_retard' => array('type' => 'integer', 'label' => 'Retards', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 55),
		'minutes_hsup' => array('type' => 'integer', 'label' => 'HeuresSupplementaires', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 56),
		'minutes_rempl' => array('type' => 'integer', 'label' => 'Remplacements', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 57),
		'nb_remplacements' => array('type' => 'integer', 'label' => 'Remplacements', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 58),
		'jours_ouvrables' => array('type' => 'integer', 'label' => 'JoursOuvrables', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 59),
		'jours_absent_nj' => array('type' => 'double(24,8)', 'label' => 'JoursAbsenceNonJustifies', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 60),
		'jours_absent_j' => array('type' => 'double(24,8)', 'label' => 'JoursAbsenceJustifies', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 61),
		'calcul' => array('type' => 'text', 'label' => 'Calcul', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 62),
		'total_gains' => array('type' => 'price', 'label' => 'TotalGains', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 70, 'isameasure' => 1),
		'total_retenues' => array('type' => 'price', 'label' => 'TotalRetenues', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 71, 'isameasure' => 1),
		'net' => array('type' => 'price', 'label' => 'NetAPayer', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 72, 'isameasure' => 1),
		'note_public' => array('type' => 'text', 'label' => 'NotePublicBulletin', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 80),
		'note_private' => array('type' => 'text', 'label' => 'NotePrivateBulletin', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 81),
		'date_calcul' => array('type' => 'datetime', 'label' => 'DateCalcul', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 90),
		'date_validation' => array('type' => 'datetime', 'label' => 'DateValidation', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 91),
		'fk_user_valid' => array('type' => 'integer', 'label' => 'ValidePar', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 92),
		'date_paiement' => array('type' => 'date', 'label' => 'DatePaiementSalaire', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 100),
		'fk_mode' => array('type' => 'integer', 'label' => 'ModePaiement', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 101),
		'fk_bank_account' => array('type' => 'integer', 'label' => 'CompteTresorerie', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 102),
		'reference_paiement' => array('type' => 'varchar(64)', 'label' => 'ReferencePaiement', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 103),
		'numero_compte' => array('type' => 'varchar(64)', 'label' => 'NumeroCompte', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 104),
		'fk_salary' => array('type' => 'integer', 'label' => 'SalaireDolibarr', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 104),
		'fk_payment_salary' => array('type' => 'integer', 'label' => 'PaiementDolibarr', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 105),
		'fk_user_paie' => array('type' => 'integer', 'label' => 'PayePar', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 106),
		'motif_annulation' => array('type' => 'varchar(255)', 'label' => 'MotifAnnulation', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 110),
		'date_annulation' => array('type' => 'datetime', 'label' => 'DateAnnulation', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 111),
		'fk_user_annulation' => array('type' => 'integer', 'label' => 'AnnulePar', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 112),
	);

	/**
	 * Statut : brouillon, validé, payé, annulé.
	 *
	 * @return array
	 */
	protected static function commonFields()
	{
		$f = parent::commonFields();
		$f['status']['label'] = 'StatutBulletin';
		$f['status']['default'] = '0';
		$f['status']['arrayofkeyval'] = array();
		foreach (self::statusLabels() as $k => $v) {
			$f['status']['arrayofkeyval'][$k] = $v[0];
		}
		return $f;
	}

	/**
	 * Statuts : valeur => [clé de traduction, couleur Dolibarr].
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	public static function statusLabels()
	{
		return array(
			self::STATUS_BROUILLON => array('BulletinBrouillon', 'status0'),
			self::STATUS_VALIDE => array('BulletinValide', 'status1'),
			self::STATUS_PAYE => array('BulletinPaye', 'status6'),
			self::STATUS_ANNULE => array('BulletinAnnule', 'status9'),
		);
	}

	/**
	 * @return array<int,string>
	 */
	public function statusOptions()
	{
		$out = array();
		foreach (self::statusLabels() as $k => $v) {
			$out[$k] = $v[0];
		}
		return $out;
	}

	/**
	 * @param int $status Statut
	 * @param int $mode   Mode d'affichage Dolibarr
	 * @return string
	 */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;
		$langs->load('salaires@salaires');
		$all = self::statusLabels();
		$s = isset($all[(int) $status]) ? $all[(int) $status] : array('Unknown', 'status0');
		$label = $langs->transnoentitiesnoconv($s[0]);
		return dolGetStatus($label, $label, '', $s[1], $mode);
	}

	/**
	 * @param DoliDB $db Handler base
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->status = self::STATUS_BROUILLON;
	}

	/* ------------------------------------------------------------------
	 * Affichage
	 * ---------------------------------------------------------------- */

	/**
	 * Lien vers la fiche du bulletin (barré s'il est annulé).
	 *
	 * @param int    $withpicto 1 = avec picto
	 * @param string $option    Non utilisé
	 * @param int    $notooltip Non utilisé
	 * @param string $morecss   CSS
	 * @return string
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '')
	{
		$url = dol_buildpath('/salaires/bulletin/card.php', 1).'?id='.((int) $this->id);
		$out = '<a href="'.$url.'" class="'.$morecss.'"'.((int) $this->status === self::STATUS_ANNULE ? ' style="text-decoration:line-through"' : '').'>';
		if ($withpicto) {
			$out .= img_picto('', $this->picto, 'class="pictofixedwidth"');
		}
		return $out.dol_escape_htmltag((string) $this->ref).'</a>';
	}

	/**
	 * Affichage d'un champ dans les listes : employé (matricule + nom), montants dans la devise.
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
		if ($key === 'mois') {
			return (string) $value !== '' ? dol_escape_htmltag(personnel_mois_label((string) $value)) : '';
		}
		if ($key === 'fk_lot') {
			return (int) $value > 0 ? salaires_lot_lien($this->db, (int) $value) : '';
		}
		if (in_array($key, array('total_gains', 'total_retenues', 'net'), true)) {
			return '<span class="nowraponall'.($key === 'net' ? ' bold' : '').'">'.salaires_montant($value).'</span>';
		}
		return parent::showOutputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss);
	}

	/* ------------------------------------------------------------------
	 * Numérotation, période
	 * ---------------------------------------------------------------- */

	/**
	 * Prochain numéro : préfixe (SALAIRES_REF_PREFIXE, défaut BP) + compteur (SALAIRES_REF_LONGUEUR chiffres, défaut 6).
	 *
	 * @return string
	 */
	public function getNextRef()
	{
		$len = min(8, max(3, getDolGlobalInt('SALAIRES_REF_LONGUEUR', 6)));
		$sql = "SELECT MAX(CAST(RIGHT(ref, ".$len.") AS UNSIGNED)) as maxi FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE entity IN (".getEntity($this->element).") AND ref REGEXP '[0-9]{".$len."}$'";
		$resql = $this->db->query($sql);
		$o = $resql ? $this->db->fetch_object($resql) : null;
		return getDolGlobalString('SALAIRES_REF_PREFIXE', 'BP').str_pad((string) (($o ? (int) $o->maxi : 0) + 1), $len, '0', STR_PAD_LEFT);
	}

	/**
	 * Début de la période (AAAA-MM-JJ).
	 *
	 * @return string
	 */
	public function du()
	{
		return dol_print_date($this->date_debut, '%Y-%m-%d');
	}

	/**
	 * Fin de la période (AAAA-MM-JJ).
	 *
	 * @return string
	 */
	public function au()
	{
		return dol_print_date($this->date_fin, '%Y-%m-%d');
	}

	/**
	 * Bulletin non annulé du même employé qui a des dates communes avec la période.
	 *
	 * @param  DoliDB $db         Handler base
	 * @param  int    $fk_employe Employé
	 * @param  string $d1         Début AAAA-MM-JJ
	 * @param  string $d2         Fin AAAA-MM-JJ
	 * @param  int    $exclude    Bulletin à ignorer
	 * @return object|null        Ligne (rowid, ref, date_debut, date_fin, status)
	 */
	public static function chevauchement($db, $fk_employe, $d1, $d2, $exclude = 0)
	{
		$sql = "SELECT rowid, ref, date_debut, date_fin, status FROM ".$db->prefix()."ecole_salaire WHERE entity IN (".getEntity('ecole_salaire').")";
		$sql .= " AND fk_employe = ".((int) $fk_employe)." AND status <> ".self::STATUS_ANNULE." AND rowid <> ".((int) $exclude);
		$sql .= " AND date_debut <= '".$db->escape($d2)."' AND date_fin >= '".$db->escape($d1)."' ORDER BY date_debut LIMIT 1";
		$resql = $db->query($sql);
		return ($resql && ($o = $db->fetch_object($resql))) ? $o : null;
	}

	/**
	 * Dernier bulletin non annulé d'un employé qui se termine avant une date (pour signaler un trou, proposer le début).
	 *
	 * @param  DoliDB $db         Handler base
	 * @param  int    $fk_employe Employé
	 * @param  string $avant      Date AAAA-MM-JJ ('' = le dernier de tous)
	 * @param  int    $exclude    Bulletin à ignorer
	 * @return object|null        Ligne (rowid, ref, date_debut, date_fin)
	 */
	public static function precedent($db, $fk_employe, $avant = '', $exclude = 0)
	{
		$sql = "SELECT rowid, ref, date_debut, date_fin FROM ".$db->prefix()."ecole_salaire WHERE entity IN (".getEntity('ecole_salaire').")";
		$sql .= " AND fk_employe = ".((int) $fk_employe)." AND status <> ".self::STATUS_ANNULE." AND rowid <> ".((int) $exclude);
		if ($avant !== '') {
			$sql .= " AND date_fin < '".$db->escape($avant)."'";
		}
		$sql .= " ORDER BY date_fin DESC LIMIT 1";
		$resql = $db->query($sql);
		return ($resql && ($o = $db->fetch_object($resql))) ? $o : null;
	}

	/**
	 * Contrôle d'une période pour un employé : dates valides, durée, pas de dates communes avec un autre bulletin.
	 *
	 * @param  DoliDB $db         Handler base
	 * @param  int    $fk_employe Employé
	 * @param  string $d1         Début
	 * @param  string $d2         Fin
	 * @param  int    $exclude    Bulletin à ignorer
	 * @param  string $mois       Mois du salaire AAAA-MM ('' = pas de contrôle du mois)
	 * @return string             Message d'erreur traduit, '' si la période est possible
	 */
	public static function controlePeriode($db, $fk_employe, $d1, $d2, $exclude = 0, $mois = '')
	{
		global $langs;
		if (personnel_date_ok($d1) === '' || personnel_date_ok($d2) === '') {
			return $langs->trans('ErrorPeriodeInvalide');
		}
		if ($d2 < $d1) {
			return $langs->trans('ErrorPeriodeInversee');
		}
		// La période ne va jamais plus loin qu'aujourd'hui
		if ($d2 > personnel_aujourdhui()) {
			return $langs->trans('ErrorFinPeriodeFuture', dol_print_date(dol_now(), 'day'));
		}
		if ($mois !== '') {
			if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mois) || $mois < substr($d1, 0, 7) || $mois > substr($d2, 0, 7)) {
				return $langs->trans('ErrorMoisHorsPeriode');
			}
			if ($fk_employe > 0) {
				$o = self::bulletinDuMois($db, $fk_employe, $mois, $exclude);
				if ($o) {
					return $langs->trans('ErrorMoisDejaFait', personnel_mois_label($mois), $o->ref);
				}
			}
		}
		if ((strtotime($d2) - strtotime($d1)) / 86400 + 1 > self::DUREE_MAX) {
			return $langs->trans('ErrorPeriodeTropLongue', self::DUREE_MAX);
		}
		$o = self::chevauchement($db, $fk_employe, $d1, $d2, $exclude);
		if ($o) {
			return $langs->trans('ErrorPeriodeChevauche', $o->ref, dol_print_date($db->jdate($o->date_debut), 'day'), dol_print_date($db->jdate($o->date_fin), 'day'));
		}
		return '';
	}

	/**
	 * Bulletin non annulé d'un employé pour un mois de salaire (un seul par mois).
	 *
	 * @param  DoliDB $db         Handler base
	 * @param  int    $fk_employe Employé
	 * @param  string $mois       AAAA-MM
	 * @param  int    $exclude    Bulletin à ignorer
	 * @return object|null        Ligne (rowid, ref)
	 */
	public static function bulletinDuMois($db, $fk_employe, $mois, $exclude = 0)
	{
		$sql = "SELECT rowid, ref FROM ".$db->prefix()."ecole_salaire WHERE entity IN (".getEntity('ecole_salaire').")";
		$sql .= " AND fk_employe = ".((int) $fk_employe)." AND mois = '".$db->escape($mois)."' AND status <> ".self::STATUS_ANNULE." AND rowid <> ".((int) $exclude);
		$resql = $db->query($sql);
		return ($resql && ($o = $db->fetch_object($resql))) ? $o : null;
	}

	/**
	 * Mois du salaire proposé pour une période : le mois de la fin de la période (du 25/09 au 25/10 = octobre).
	 *
	 * @param  string $d2 Fin AAAA-MM-JJ
	 * @return string     AAAA-MM
	 */
	public static function moisPropose($d2)
	{
		return substr((string) $d2, 0, 7);
	}

	/* ------------------------------------------------------------------
	 * Création et calcul
	 * ---------------------------------------------------------------- */

	/**
	 * Crée le bulletin (brouillon) d'un employé pour une période, puis le calcule.
	 *
	 * @param  User   $user       Utilisateur
	 * @param  int    $fk_employe Employé
	 * @param  string $d1         Début AAAA-MM-JJ
	 * @param  string $d2         Fin AAAA-MM-JJ
	 * @param  int    $fk_lot     Lot (0 = aucun)
	 * @param  string $mois       Mois du salaire AAAA-MM ('' = mois de la fin de la période)
	 * @return int                Id, <0 si erreur ($this->error)
	 */
	public function creer(User $user, $fk_employe, $d1, $d2, $fk_lot = 0, $mois = '')
	{
		global $langs;
		$langs->loadLangs(array('salaires@salaires', 'errors'));
		$emp = new EcoleEmploye($this->db);
		if ((int) $fk_employe <= 0 || $emp->fetch((int) $fk_employe) <= 0) {
			$this->error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('Employe'));
			return -1;
		}
		$mois = ((string) $mois !== '') ? (string) $mois : self::moisPropose($d2);
		$err = self::controlePeriode($this->db, (int) $emp->id, $d1, $d2, 0, $mois);
		if ($err !== '') {
			$this->error = $err;
			return -1;
		}

		$this->db->begin();
		$this->mois = $mois;
		$this->ref = $this->getNextRef();
		$this->fk_employe = (int) $emp->id;
		$this->fk_lot = $fk_lot > 0 ? (int) $fk_lot : null;
		$this->date_debut = personnel_date_ts($d1);
		$this->date_fin = personnel_date_ts($d2);
		$this->status = self::STATUS_BROUILLON;
		if ($this->createCommon($user) <= 0) {
			$this->db->rollback();
			$this->error = $this->error ? $this->error : implode('<br>', $this->errors);
			return -1;
		}
		// Relecture : après createCommon, la date de création est un texte, qu'un nouvel enregistrement décalerait
		$this->fetch($this->id);
		if ($this->calculer($user, $emp, false) < 0) {
			$this->db->rollback();
			$this->id = 0;
			return -1;
		}
		$this->addLog($user, 'creation', dol_print_date($this->date_debut, 'day').' - '.dol_print_date($this->date_fin, 'day'));
		$this->db->commit();
		return $this->id;
	}

	/**
	 * Calcule (ou recalcule) un bulletin en brouillon depuis la présence et la fiche de l'employé.
	 * Les lignes calculées sont réécrites, sauf celles dont le montant a été modifié à la main (leur montant
	 * calculé est seulement mis à jour) ; les lignes ajoutées à la main ne changent pas.
	 *
	 * @param  User              $user Utilisateur
	 * @param  EcoleEmploye|null $emp  Employé déjà chargé
	 * @param  bool              $log  Garder une ligne « recalcul » dans l'historique
	 * @return int                     1 si OK, <0 sinon
	 */
	public function calculer(User $user, $emp = null, $log = true)
	{
		global $langs;
		$langs->loadLangs(array('salaires@salaires', 'personnel@personnel'));
		if ((int) $this->status !== self::STATUS_BROUILLON) {
			$this->error = $langs->trans('ErrorBulletinNonBrouillon');
			return -1;
		}
		if (!$emp) {
			$emp = new EcoleEmploye($this->db);
			if ($emp->fetch((int) $this->fk_employe) <= 0) {
				$this->error = $langs->trans('ErrorRecordNotFound');
				return -1;
			}
		}
		$h = personnel_heures_periode($this->db, $emp, $this->du(), $this->au());
		$t = $h['totaux'];
		$calc = personnel_paie_lignes($emp, $t, $h['regles']);
		$p = $this->db->prefix();
		$now = $this->db->idate(dol_now());

		// Lignes calculées modifiées à la main (elles gardent leur montant) et net des lignes ajoutées à la main
		$modifiees = array();
		$montantsModifies = array();
		$netManuel = 0;
		$resql = $this->db->query("SELECT rowid, code, type, montant, auto, modifie, fk_source FROM ".$p."ecole_salaire_ligne WHERE fk_salaire = ".((int) $this->id)." AND (modifie = 1 OR auto = 0)");
		while ($resql && ($o = $this->db->fetch_object($resql))) {
			if ((int) $o->auto) {
				$modifiees[$o->code.'|'.((int) $o->fk_source)] = (int) $o->rowid;
				$montantsModifies[$o->code.'|'.((int) $o->fk_source)] = (float) $o->montant;
			} else {
				$netManuel += ($o->type === 'retenue' ? -1 : 1) * (float) $o->montant;
			}
		}
		// Net disponible avant avances et prêts, puis retenues des avances et des prêts en cours
		$disponible = $netManuel;
		foreach ($calc['lignes'] as $l) {
			$k = $l['code'].'|0';
			$disponible += ($l['type'] === 'retenue' ? -1 : 1) * (isset($montantsModifies[$k]) ? $montantsModifies[$k] : $l['montant']);
		}
		$ap = salaires_lignes_avances_prets($this->db, $this, $disponible);
		$calc['lignes'] = array_merge($calc['lignes'], $ap['lignes']);
		$calc['alertes'] = array_merge($calc['alertes'], $ap['alertes']);

		$this->db->begin();
		$error = 0;
		if (!$this->db->query("DELETE FROM ".$p."ecole_salaire_ligne WHERE fk_salaire = ".((int) $this->id)." AND auto = 1 AND modifie = 0")) {
			$error++;
		}
		$pos = 0;
		foreach ($calc['lignes'] as $l) {
			$pos++;
			$dargs = json_encode($l['dargs']);
			$srcType = !empty($l['source_type']) ? "'".$this->db->escape($l['source_type'])."'" : "NULL";
			$srcId = !empty($l['fk_source']) ? (int) $l['fk_source'] : "NULL";
			$k = $l['code'].'|'.(!empty($l['fk_source']) ? (int) $l['fk_source'] : 0);
			if (isset($modifiees[$k])) {
				$sql = "UPDATE ".$p."ecole_salaire_ligne SET montant_calcule = ".((float) $l['montant']).", detail = '".$this->db->escape(dol_trunc($l['detail'], 250, 'right', 'UTF-8', 1))."'";
				$sql .= ", dcle = '".$this->db->escape($l['dcle'])."', dargs = '".$this->db->escape($dargs)."', position = ".$pos." WHERE rowid = ".$modifiees[$k];
			} else {
				$sql = "INSERT INTO ".$p."ecole_salaire_ligne (fk_salaire, type, code, libelle, detail, cle, dcle, dargs, source_type, fk_source, montant, montant_calcule, auto, modifie, position, date_creation, fk_user_creat)";
				$sql .= " VALUES (".((int) $this->id).", '".$this->db->escape($l['type'])."', '".$this->db->escape($l['code'])."', '".$this->db->escape(dol_trunc($l['libelle'], 250, 'right', 'UTF-8', 1))."'";
				$sql .= ", '".$this->db->escape(dol_trunc($l['detail'], 250, 'right', 'UTF-8', 1))."', '".$this->db->escape($l['cle'])."', '".$this->db->escape($l['dcle'])."', '".$this->db->escape($dargs)."'";
				$sql .= ", ".$srcType.", ".$srcId.", ".((float) $l['montant']).", ".((float) $l['montant']).", 1, 0, ".$pos.", '".$now."', ".((int) $user->id).")";
			}
			if (!$this->db->query($sql)) {
				$error++;
			}
		}
		// Lignes ajoutées à la main : après les lignes calculées
		$this->db->query("UPDATE ".$p."ecole_salaire_ligne SET position = position + 100 WHERE fk_salaire = ".((int) $this->id)." AND auto = 0 AND position < 100");

		// Séances retenues (copie pour la fiche de paie)
		if (!$this->db->query("DELETE FROM ".$p."ecole_salaire_seance WHERE fk_salaire = ".((int) $this->id))) {
			$error++;
		}
		foreach ($h['seances'] as $s) {
			if (!in_array($s->etat, array('present', 'declare', 'retard', 'absent', 'remplacement'), true)) {
				continue;
			}
			$payees = ($s->etat === 'remplacement') ? (int) $s->minutes : (int) $s->compte;
			$sql = "INSERT INTO ".$p."ecole_salaire_seance (fk_salaire, date_seance, heure_debut, heure_fin, creneau, classe, matiere, etat, minutes, minutes_payees, retard, justifiee)";
			$sql .= " VALUES (".((int) $this->id).", '".$this->db->escape($s->date)."', '".$this->db->escape((string) $s->heure_debut)."', '".$this->db->escape((string) $s->heure_fin)."'";
			$sql .= ", '".$this->db->escape(dol_trunc((string) $s->crref, 30, 'right', 'UTF-8', 1))."', '".$this->db->escape(dol_trunc((string) $s->cref, 30, 'right', 'UTF-8', 1))."'";
			$sql .= ", '".$this->db->escape(dol_trunc((string) $s->matiere, 250, 'right', 'UTF-8', 1))."', '".$this->db->escape($s->etat)."', ".((int) $s->minutes).", ".$payees;
			$sql .= ", ".((int) $s->retard).", ".((int) $s->justifiee).")";
			if (!$this->db->query($sql)) {
				$error++;
			}
		}

		// Éléments de paie et résumé de la présence
		$this->mode_paie = $emp->mode_paie;
		$this->salaire_base = $emp->salaire_base;
		$this->taux_horaire = $emp->taux_horaire;
		$this->heures_semaine = $emp->heures_semaine;
		$this->taux_heure_sup = $emp->taux_heure_sup;
		$this->minutes_prevues = (int) $t['prevues'];
		$this->minutes_faites = (int) $t['faites'];
		$this->minutes_payees = (int) $t['payees'];
		$this->minutes_absent_nj = (int) $t['absent_nj'];
		$this->minutes_absent_j = (int) $t['absent_j'];
		$this->minutes_retard = (int) $t['retard_min'];
		$this->minutes_hsup = (int) $t['heures_sup'];
		$this->minutes_rempl = (int) $t['remplacement'];
		$this->nb_remplacements = (int) $t['nb_remplacements'];
		$this->jours_ouvrables = (int) $t['jours_ouvrables'];
		$this->jours_absent_nj = (float) $t['jours_absent_nj'];
		$this->jours_absent_j = (float) $t['jours_absent_j'];
		$this->calcul = json_encode(array(
			'possible' => $calc['possible'],
			'alertes' => $calc['alertes'],
			'regles' => $h['regles'],
			'fraction_mois' => round((float) $t['fraction_mois'], 4),
			'jours_payes' => (int) $t['jours_payes'],
			'jours_periode' => (int) $t['jours_periode'],
			'avenir' => (int) $t['avenir'],
			'nb_prevues' => (int) $t['nb_prevues'],
			'nb_faites' => (int) $t['nb_faites'],
			'nb_absences' => (int) $t['nb_absences'],
			'nb_retards' => (int) $t['nb_retards'],
			'nb_declares' => (int) $t['nb_declares'],
			'hsup_semaines' => $t['hsup_semaines'],
		));
		$this->date_calcul = dol_now();
		if (!$error && $this->updateCommon($user) <= 0) {
			$error++;
		}
		if (!$error && $this->recalculerTotaux() < 0) {
			$error++;
		}
		if ($error) {
			$this->db->rollback();
			$this->error = $this->error ? $this->error : $this->db->lasterror();
			return -1;
		}
		if ($log) {
			$this->addLog($user, 'recalcul', $langs->transnoentities('NetApres', salaires_montant_texte($this->net)));
		}
		$this->db->commit();
		return 1;
	}

	/**
	 * Totaux du bulletin depuis ses lignes (gains, retenues, net).
	 *
	 * @return int 1 si OK, -1 sinon
	 */
	public function recalculerTotaux()
	{
		$gains = 0;
		$retenues = 0;
		foreach ($this->getLignes() as $l) {
			if ($l->type === 'retenue') {
				$retenues += (float) $l->montant;
			} else {
				$gains += (float) $l->montant;
			}
		}
		$this->total_gains = (float) price2num($gains, 'MT');
		$this->total_retenues = (float) price2num($retenues, 'MT');
		$this->net = (float) price2num($gains - $retenues, 'MT');
		$sql = "UPDATE ".$this->db->prefix().$this->table_element." SET total_gains = ".$this->total_gains.", total_retenues = ".$this->total_retenues.", net = ".$this->net;
		$sql .= " WHERE rowid = ".((int) $this->id);
		return $this->db->query($sql) ? 1 : -1;
	}

	/* ------------------------------------------------------------------
	 * Lignes
	 * ---------------------------------------------------------------- */

	/**
	 * Lignes du bulletin (gains puis retenues, dans l'ordre du calcul).
	 *
	 * @return array<int,object>
	 */
	public function getLignes()
	{
		$out = array();
		$sql = "SELECT rowid, type, code, libelle, detail, cle, dcle, dargs, source_type, fk_source, montant, montant_calcule, auto, modifie, position FROM ".$this->db->prefix()."ecole_salaire_ligne";
		$sql .= " WHERE fk_salaire = ".((int) $this->id)." ORDER BY FIELD(type, 'gain', 'retenue'), position, rowid";
		$resql = $this->db->query($sql);
		while ($resql && ($o = $this->db->fetch_object($resql))) {
			$out[(int) $o->rowid] = $o;
		}
		return $out;
	}

	/**
	 * Ajoute une ligne à la main (gain ou retenue) sur un brouillon.
	 *
	 * @param  User   $user    Utilisateur
	 * @param  string $type    gain | retenue
	 * @param  string $libelle Libellé
	 * @param  float  $montant Montant (> 0)
	 * @return int             1 si OK, <0 sinon
	 */
	public function ajouterLigne(User $user, $type, $libelle, $montant)
	{
		global $langs;
		$libelle = trim((string) $libelle);
		$montant = (float) price2num($montant, 'MT');
		if ((int) $this->status !== self::STATUS_BROUILLON) {
			$this->error = $langs->trans('ErrorBulletinNonBrouillon');
			return -1;
		}
		if (!in_array($type, array('gain', 'retenue'), true) || $libelle === '' || $montant <= 0) {
			$this->error = $langs->trans('ErrorLigneIncomplete');
			return -1;
		}
		$this->db->begin();
		$sql = "INSERT INTO ".$this->db->prefix()."ecole_salaire_ligne (fk_salaire, type, code, libelle, montant, auto, modifie, position, date_creation, fk_user_creat)";
		$sql .= " VALUES (".((int) $this->id).", '".$this->db->escape($type)."', 'MANUEL', '".$this->db->escape(dol_trunc($libelle, 250, 'right', 'UTF-8', 1))."', ".$montant.", 0, 0, 1000, '".$this->db->idate(dol_now())."', ".((int) $user->id).")";
		if (!$this->db->query($sql) || $this->recalculerTotaux() < 0) {
			$this->db->rollback();
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->addLog($user, 'ligne_ajout', $langs->transnoentities($type === 'gain' ? 'Gain' : 'Retenue').' : '.$libelle.' = '.salaires_montant_texte($montant));
		$this->db->commit();
		return 1;
	}

	/**
	 * Modifie le montant d'une ligne (brouillon). Ligne calculée : le montant saisi remplace le calcul et reste au
	 * recalcul (sauf s'il redevient égal au calcul). Ligne ajoutée à la main : libellé et montant.
	 *
	 * @param  User   $user    Utilisateur
	 * @param  int    $idligne Ligne
	 * @param  float  $montant Nouveau montant (>= 0)
	 * @param  string $libelle Nouveau libellé (lignes ajoutées à la main)
	 * @return int             1 si OK, <0 sinon
	 */
	public function modifierLigne(User $user, $idligne, $montant, $libelle = '')
	{
		global $langs;
		$lignes = $this->getLignes();
		if ((int) $this->status !== self::STATUS_BROUILLON || !isset($lignes[(int) $idligne])) {
			$this->error = $langs->trans('ErrorBulletinNonBrouillon');
			return -1;
		}
		$l = $lignes[(int) $idligne];
		$montant = (float) price2num($montant, 'MT');
		if ($montant < 0) {
			$this->error = $langs->trans('ErrorLigneIncomplete');
			return -1;
		}
		$sql = "UPDATE ".$this->db->prefix()."ecole_salaire_ligne SET montant = ".$montant;
		if ((int) $l->auto) {
			$sql .= ", modifie = ".(abs($montant - (float) $l->montant_calcule) < 0.005 ? 0 : 1);
		} else {
			$libelle = trim((string) $libelle);
			if ($libelle === '' || $montant <= 0) {
				$this->error = $langs->trans('ErrorLigneIncomplete');
				return -1;
			}
			$sql .= ", libelle = '".$this->db->escape(dol_trunc($libelle, 250, 'right', 'UTF-8', 1))."'";
		}
		$sql .= " WHERE rowid = ".((int) $l->rowid);
		$this->db->begin();
		if (!$this->db->query($sql) || $this->recalculerTotaux() < 0) {
			$this->db->rollback();
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->addLog($user, 'ligne_modif', salaires_ligne_libelle($l, $langs).' : '.salaires_montant_texte($l->montant).' → '.salaires_montant_texte($montant));
		$this->db->commit();
		return 1;
	}

	/**
	 * Supprime une ligne ajoutée à la main, ou rend son montant calculé à une ligne calculée (brouillon).
	 *
	 * @param  User $user    Utilisateur
	 * @param  int  $idligne Ligne
	 * @return int           1 si OK, <0 sinon
	 */
	public function supprimerLigne(User $user, $idligne)
	{
		global $langs;
		$lignes = $this->getLignes();
		if ((int) $this->status !== self::STATUS_BROUILLON || !isset($lignes[(int) $idligne])) {
			$this->error = $langs->trans('ErrorBulletinNonBrouillon');
			return -1;
		}
		$l = $lignes[(int) $idligne];
		if ((int) $l->auto) {
			$sql = "UPDATE ".$this->db->prefix()."ecole_salaire_ligne SET montant = montant_calcule, modifie = 0 WHERE rowid = ".((int) $l->rowid);
			$detail = salaires_ligne_libelle($l, $langs).' : '.salaires_montant_texte($l->montant).' → '.salaires_montant_texte($l->montant_calcule);
			$action = 'ligne_retablie';
		} else {
			$sql = "DELETE FROM ".$this->db->prefix()."ecole_salaire_ligne WHERE rowid = ".((int) $l->rowid);
			$detail = $l->libelle.' = '.salaires_montant_texte($l->montant);
			$action = 'ligne_suppr';
		}
		$this->db->begin();
		if (!$this->db->query($sql) || $this->recalculerTotaux() < 0) {
			$this->db->rollback();
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->addLog($user, $action, $detail);
		$this->db->commit();
		return 1;
	}

	/**
	 * Séances retenues pour le bulletin.
	 *
	 * @return array<int,object>
	 */
	public function getSeances()
	{
		$out = array();
		$resql = $this->db->query("SELECT * FROM ".$this->db->prefix()."ecole_salaire_seance WHERE fk_salaire = ".((int) $this->id)." ORDER BY date_seance, heure_debut, rowid");
		while ($resql && ($o = $this->db->fetch_object($resql))) {
			$out[] = $o;
		}
		return $out;
	}

	/**
	 * Détail du calcul enregistré (règles, alertes, semaines des heures supplémentaires...).
	 *
	 * @return array
	 */
	public function getCalcul()
	{
		$c = json_decode((string) $this->calcul, true);
		return is_array($c) ? $c : array();
	}

	/* ------------------------------------------------------------------
	 * Validation, réouverture, annulation
	 * ---------------------------------------------------------------- */

	/**
	 * Valide le bulletin : il ne change plus et devient visible par l'employé.
	 *
	 * @param  User $user Utilisateur
	 * @return int        1 si OK, <0 sinon
	 */
	public function valider(User $user)
	{
		global $langs;
		if ((int) $this->status !== self::STATUS_BROUILLON) {
			$this->error = $langs->trans('ErrorBulletinNonBrouillon');
			return -1;
		}
		if ((float) $this->net < 0) {
			$this->error = $langs->trans('ErrorNetNegatif', salaires_montant_texte($this->net));
			return -1;
		}
		return $this->changerStatut($user, self::STATUS_VALIDE, 'validation', salaires_montant_texte($this->net), '',
			", date_validation = '".$this->db->idate(dol_now())."', fk_user_valid = ".((int) $user->id));
	}

	/**
	 * Remet un bulletin validé (non payé) en brouillon, avec un motif.
	 *
	 * @param  User   $user  Utilisateur
	 * @param  string $motif Motif (obligatoire)
	 * @return int           1 si OK, <0 sinon
	 */
	public function rouvrir(User $user, $motif)
	{
		global $langs;
		if ((int) $this->status !== self::STATUS_VALIDE) {
			$this->error = $langs->trans((int) $this->status === self::STATUS_PAYE ? 'ErrorAnnulerPaiementDabord' : 'ErrorBulletinNonValide');
			return -1;
		}
		if (trim((string) $motif) === '') {
			$this->error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('Motif'));
			return -1;
		}
		return $this->changerStatut($user, self::STATUS_BROUILLON, 'reouverture', '', $motif, ", date_validation = NULL, fk_user_valid = NULL");
	}

	/**
	 * Annule un bulletin non payé (brouillon ou validé), avec un motif. Il reste visible (barré) ; ses dates
	 * redeviennent libres pour un nouveau bulletin.
	 *
	 * @param  User   $user  Utilisateur
	 * @param  string $motif Motif (obligatoire)
	 * @return int           1 si OK, <0 sinon
	 */
	public function annuler(User $user, $motif)
	{
		global $langs;
		$motif = trim((string) $motif);
		if (!in_array((int) $this->status, array(self::STATUS_BROUILLON, self::STATUS_VALIDE), true)) {
			$this->error = $langs->trans((int) $this->status === self::STATUS_PAYE ? 'ErrorAnnulerPaiementDabord' : 'ErrorBulletinDejaAnnule');
			return -1;
		}
		if ($motif === '') {
			$this->error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('Motif'));
			return -1;
		}
		$res = $this->changerStatut($user, self::STATUS_ANNULE, 'annulation', '', $motif,
			", motif_annulation = '".$this->db->escape(dol_trunc($motif, 250, 'right', 'UTF-8', 1))."', date_annulation = '".$this->db->idate(dol_now())."', fk_user_annulation = ".((int) $user->id));
		if ($res > 0) {
			$this->motif_annulation = $motif;
		}
		return $res;
	}

	/**
	 * Change le statut (avec historique).
	 *
	 * @param  User   $user   Utilisateur
	 * @param  int    $status Nouveau statut
	 * @param  string $action Action de l'historique
	 * @param  string $detail Détail
	 * @param  string $motif  Motif
	 * @param  string $more   Colonnes à mettre à jour en plus (SQL sûr, commençant par une virgule)
	 * @return int            1 si OK, <0 sinon
	 */
	protected function changerStatut(User $user, $status, $action, $detail, $motif, $more = '')
	{
		$this->db->begin();
		$sql = "UPDATE ".$this->db->prefix().$this->table_element." SET status = ".((int) $status).", fk_user_modif = ".((int) $user->id).$more;
		$sql .= " WHERE rowid = ".((int) $this->id)." AND status = ".((int) $this->status);
		if (!$this->db->query($sql) || $this->db->affected_rows($sql) < 1) {
			$this->db->rollback();
			$this->error = $this->db->lasterror() ? $this->db->lasterror() : 'ErrorRecordNotFound';
			return -1;
		}
		$this->addLog($user, $action, $detail, $motif);
		$this->db->commit();
		$this->status = (int) $status;
		return 1;
	}

	/* ------------------------------------------------------------------
	 * Paiement
	 * ---------------------------------------------------------------- */

	/**
	 * Paie un bulletin validé : salaire Dolibarr (employé, période, montant net), paiement et écriture dans le compte
	 * de trésorerie du mode de paiement. Net nul : le bulletin est seulement marqué payé.
	 *
	 * @param  User   $user      Utilisateur
	 * @param  int    $date      Date du paiement (horodatage)
	 * @param  int    $fk_mode   Mode de paiement (dictionnaire Dolibarr)
	 * @param  string $reference Référence du paiement (numéro de transaction, de chèque...)
	 * @param  string $numero    Numéro du compte mobile (Bankily, Sedad...) ; vide = celui de la fiche de l'employé
	 * @return int               1 si OK, <0 sinon
	 */
	public function payer(User $user, $date, $fk_mode, $reference = '', $numero = '')
	{
		global $langs;
		$langs->loadLangs(array('salaires@salaires', 'banks', 'bills', 'errors'));

		if ((int) $this->status !== self::STATUS_VALIDE) {
			$this->error = $langs->trans('ErrorBulletinNonValide');
			return -1;
		}
		$emp = new EcoleEmploye($this->db);
		if ($emp->fetch((int) $this->fk_employe) <= 0) {
			$this->error = $langs->trans('ErrorRecordNotFound');
			return -1;
		}
		$fk_mode = (int) $fk_mode;
		$net = (float) price2num($this->net, 'MT');
		$compte = 0;
		$err = salaires_controle_versement($this->db, $emp, $fk_mode, $net > 0, $compte, $numero);
		if ($err !== '' && ($net > 0 || !isset(salaires_modes_paiement($this->db)[$fk_mode]))) {
			$this->error = $err;
			return -1;
		}
		$d = dol_getdate($date ? $date : dol_now());
		$date = dol_mktime(12, 0, 0, $d['mon'], $d['mday'], $d['year']);
		$reference = trim((string) $reference);

		$this->db->begin();
		$ids = array(0, 0);
		if ($net > 0) {
			$error = '';
			$ids = salaires_verser_salaire($this->db, $user, $emp, array(
				'label' => $langs->transnoentities('LibelleSalaireDolibarr', personnel_mois_label((string) $this->mois), $emp->ref, $this->ref),
				'note' => $langs->transnoentities('NoteSalaireDolibarr', $this->ref).($numero !== '' ? ' — '.$langs->transnoentities('NumeroCompte').' : '.$numero : ''),
				'montant' => $net, 'date' => $date, 'debut' => $this->date_debut, 'fin' => $this->date_fin, 'mode' => $fk_mode, 'compte' => $compte,
				'reference' => $reference !== '' ? $reference : $this->ref, 'salaire' => (float) $this->salaire_base), $error);
			if ($ids === null) {
				$this->db->rollback();
				$this->error = $error;
				return -1;
			}
		}
		$more = ", date_paiement = '".$this->db->idate($date)."', fk_mode = ".$fk_mode.", fk_bank_account = ".($compte > 0 && $net > 0 ? $compte : "NULL");
		$more .= ", reference_paiement = ".($reference !== '' ? "'".$this->db->escape(dol_trunc($reference, 60, 'right', 'UTF-8', 1))."'" : "NULL");
		$more .= ", numero_compte = ".($numero !== '' ? "'".$this->db->escape(dol_trunc($numero, 60, 'right', 'UTF-8', 1))."'" : "NULL");
		$more .= ", fk_salary = ".($ids[0] > 0 ? $ids[0] : "NULL").", fk_payment_salary = ".($ids[1] > 0 ? $ids[1] : "NULL").", fk_user_paie = ".((int) $user->id);
		$sql = "UPDATE ".$this->db->prefix().$this->table_element." SET status = ".self::STATUS_PAYE.", fk_user_modif = ".((int) $user->id).$more;
		$sql .= " WHERE rowid = ".((int) $this->id)." AND status = ".self::STATUS_VALIDE;
		if (!$this->db->query($sql)) {
			$this->db->rollback();
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->addLog($user, 'paiement', salaires_montant_texte($net).' — '.salaires_mode_label($this->db, $fk_mode).($numero !== '' ? ' '.$numero : '').($reference !== '' ? ' ('.$reference.')' : '').' — '.dol_print_date($date, 'day'));
		$this->db->commit();
		$this->fetch($this->id);
		return 1;
	}

	/**
	 * Annule le paiement (erreur) : le paiement et le salaire Dolibarr sont retirés (l'écriture de trésorerie aussi,
	 * si elle n'est pas rapprochée), le bulletin redevient « validé ». Motif obligatoire.
	 *
	 * @param  User   $user  Utilisateur
	 * @param  string $motif Motif
	 * @return int           1 si OK, <0 sinon
	 */
	public function annulerPaiement(User $user, $motif)
	{
		global $langs;
		$langs->loadLangs(array('salaires@salaires', 'banks', 'errors'));
		$motif = trim((string) $motif);
		if ((int) $this->status !== self::STATUS_PAYE) {
			$this->error = $langs->trans('ErrorBulletinNonPaye');
			return -1;
		}
		if ($motif === '') {
			$this->error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('Motif'));
			return -1;
		}
		$this->db->begin();
		$error = '';
		if (salaires_retirer_salaire($this->db, $user, (int) $this->fk_salary, (int) $this->fk_payment_salary, $error) < 0) {
			$this->db->rollback();
			$this->error = $error;
			return -1;
		}
		$avant = salaires_montant_texte($this->net).' — '.salaires_mode_label($this->db, (int) $this->fk_mode).' — '.dol_print_date($this->date_paiement, 'day');
		$sql = "UPDATE ".$this->db->prefix().$this->table_element." SET status = ".self::STATUS_VALIDE.", fk_user_modif = ".((int) $user->id);
		$sql .= ", date_paiement = NULL, fk_mode = NULL, fk_bank_account = NULL, reference_paiement = NULL, numero_compte = NULL, fk_salary = NULL, fk_payment_salary = NULL, fk_user_paie = NULL";
		$sql .= " WHERE rowid = ".((int) $this->id)." AND status = ".self::STATUS_PAYE;
		if (!$this->db->query($sql)) {
			$this->db->rollback();
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->addLog($user, 'paiement_annule', $avant, $motif);
		$this->db->commit();
		$this->fetch($this->id);
		return 1;
	}

	/**
	 * Causes qui empêchent l'annulation du paiement : l'écriture de trésorerie est déjà rapprochée avec le
	 * relevé de banque (Dolibarr refuse alors de la retirer). Sert à griser le bouton « Annuler le paiement ».
	 *
	 * @return string[]
	 */
	public function getCancelPaymentBlockers()
	{
		global $langs;
		$langs->load('salaires@salaires');
		if ((int) $this->fk_payment_salary <= 0) {
			return array();
		}
		$sql = "SELECT COUNT(*) as nb FROM ".$this->db->prefix()."payment_salary as ps";
		$sql .= " INNER JOIN ".$this->db->prefix()."bank as b ON b.rowid = ps.fk_bank";
		$sql .= " WHERE ps.rowid = ".((int) $this->fk_payment_salary)." AND b.rappro = 1";
		$resql = $this->db->query($sql);
		$o = $resql ? $this->db->fetch_object($resql) : null;
		return ($o && (int) $o->nb > 0) ? array($langs->trans('ErrorPaiementRapproche')) : array();
	}

	/**
	 * Un bulletin ne se supprime jamais (il s'annule).
	 *
	 * @param User $user      Utilisateur
	 * @param int  $notrigger 1 = sans trigger
	 * @return int
	 */
	public function delete(User $user, $notrigger = 0)
	{
		global $langs;
		$this->error = $langs->trans('ErrorBulletinNonSupprimable');
		return -1;
	}

	/* ------------------------------------------------------------------
	 * Historique
	 * ---------------------------------------------------------------- */

	/**
	 * Ajoute une ligne à l'historique du bulletin.
	 *
	 * @param  User   $user   Utilisateur
	 * @param  string $action Action
	 * @param  string $detail Détail
	 * @param  string $motif  Motif
	 * @return int            1 si OK, -1 sinon
	 */
	public function addLog(User $user, $action, $detail = '', $motif = '')
	{
		global $conf;
		$sql = "INSERT INTO ".$this->db->prefix()."ecole_salaire_log (entity, fk_salaire, action, detail, motif, fk_user, date_creation)";
		$sql .= " VALUES (".((int) $conf->entity).", ".((int) $this->id).", '".$this->db->escape($action)."'";
		$sql .= ", ".($detail !== '' ? "'".$this->db->escape(dol_trunc($detail, 250, 'right', 'UTF-8', 1))."'" : "NULL");
		$sql .= ", ".($motif !== '' ? "'".$this->db->escape(dol_trunc($motif, 250, 'right', 'UTF-8', 1))."'" : "NULL");
		$sql .= ", ".((int) $user->id).", '".$this->db->idate(dol_now())."')";
		return $this->db->query($sql) ? 1 : -1;
	}

	/**
	 * Historique du bulletin (plus récent en premier).
	 *
	 * @return array<int,object>
	 */
	public function getLogs()
	{
		$out = array();
		$resql = $this->db->query("SELECT rowid, action, detail, motif, fk_user, date_creation FROM ".$this->db->prefix()."ecole_salaire_log WHERE fk_salaire = ".((int) $this->id)." ORDER BY date_creation DESC, rowid DESC");
		while ($resql && ($o = $this->db->fetch_object($resql))) {
			$out[] = $o;
		}
		return $out;
	}
}
