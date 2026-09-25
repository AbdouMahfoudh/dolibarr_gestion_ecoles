<?php
/**
 * Fiche d'un employé (enseignant, surveillant, secrétariat, comptable, agent, chauffeur...).
 *
 * - ref = matricule automatique (préfixe + compteur, ex. P00001), jamais modifié ensuite ; sert aussi
 *   d'identifiant de connexion pour les nouveaux comptes ;
 * - catégories (plusieurs possibles) : les mêmes que le champ « ecole_categorie » des utilisateurs ;
 * - statuts : actif, en période d'essai, suspendu / en congé, parti — avec motif (liste configurable) et historique ;
 * - un utilisateur Dolibarr est créé (ou un utilisateur existant relié) et mis à jour automatiquement
 *   (sens unique fiche → utilisateur) : nom, téléphone, emploi, dates, salaire, catégories, compte actif ou non ;
 * - modifications de la paie (mode, salaire, prix de l'heure...) gardées dans l'historique.
 *
 * Fichier : custom/personnel/class/ecole_employe.class.php
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/classes/class/ecole_object.class.php');
dol_include_once('/personnel/core/lib/personnel.lib.php');

/**
 * Class EcoleEmploye
 */
class EcoleEmploye extends EcoleObject
{
	/** @var string */
	public $module = 'personnel';
	/** @var string */
	public $element = 'ecole_employe';
	/** @var string */
	public $table_element = 'ecole_employe';
	/** @var string */
	public $picto = 'fa-id-badge';
	/** @var string */
	public $dirpage = 'employe';
	/** @var int Champs supplémentaires ajoutés par l'établissement */
	public $isextrafieldmanaged = 1;

	const STATUS_ACTIF = 1;
	const STATUS_ESSAI = 2;
	const STATUS_SUSPENDU = 3;
	const STATUS_PARTI = 4;

	/** Champs de paie : chaque modification est gardée dans l'historique */
	const CHAMPS_PAIE = array('mode_paie', 'salaire_base', 'taux_horaire', 'heures_semaine', 'taux_heure_sup');

	public $required = array('nom_fr');
	public $unique = array('ref');
	public $usage = array();
	public $listcolumns = array('ref', 'label', 'extra:categories', 'poste', 'telephone', 'mode_paie', 'date_embauche', 'extra:pieces', 'extra:compte', 'status');
	public $sortdefault = 'nom_fr';
	public $labelfields = array('nom_fr', 'nom_ar');
	public $labeltitle = 'NomComplet';

	public $ref;
	public $nom_fr;
	public $nom_ar;
	public $photo;
	public $categories;
	public $date_naissance;
	public $lieu_naissance;
	public $sexe;
	public $fk_nationalite;
	public $nni;
	public $situation_familiale;
	public $nb_enfants;
	public $adresse;
	public $telephone;
	public $whatsapp;
	public $email;
	public $urgence_nom;
	public $urgence_telephone;
	public $urgence_lien;
	public $poste;
	public $date_embauche;
	public $type_contrat;
	public $date_fin_contrat;
	public $date_fin_essai;
	public $mode_paie;
	public $salaire_base;
	public $taux_horaire;
	public $heures_semaine;
	public $taux_heure_sup;
	public $banque_nom;
	public $banque_compte;
	public $mobile_money;
	public $diplome;
	public $specialite;
	public $experience;
	public $observations;
	public $fk_user;
	public $date_statut;
	public $regle_retenue;
	public $regle_retard;
	public $regle_hsup;
	public $regle_remplacement;

	/** @var int Utilisateur Dolibarr existant à relier à la création (0 = en créer un nouveau) */
	public $link_user = 0;

	/**
	 * Champs. La clé « bloc » range chaque champ dans un bloc de la fiche :
	 * identite, contact, urgence, contrat, paie, banque, diplomes ; « technique » = géré par le programme.
	 *
	 * @var array
	 */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'MatriculeEmploye', 'enabled' => '1', 'visible' => 5, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'bloc' => 'technique'),
		// Identité
		'nom_fr' => array('type' => 'varchar(255)', 'label' => 'NomCompletFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300', 'bloc' => 'identite'),
		'nom_ar' => array('type' => 'varchar(255)', 'label' => 'NomCompletAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 21, 'searchall' => 1, 'css' => 'minwidth300', 'bloc' => 'identite'),
		'categories' => array('type' => 'varchar(255)', 'label' => 'CategoriesEmploye', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 22, 'bloc' => 'identite', 'help' => 'CategoriesEmployeHelp'),
		'date_naissance' => array('type' => 'date', 'label' => 'DateNaissance', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 23, 'bloc' => 'identite'),
		'lieu_naissance' => array('type' => 'varchar(128)', 'label' => 'LieuNaissance', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 24, 'css' => 'minwidth200', 'bloc' => 'identite'),
		'sexe' => array('type' => 'varchar(1)', 'label' => 'Sexe', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 25, 'bloc' => 'identite',
			'arrayofkeyval' => array('M' => 'SexeHomme', 'F' => 'SexeFemme')),
		'fk_nationalite' => array('type' => 'integer', 'label' => 'Nationalite', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 26, 'bloc' => 'identite'),
		'nni' => array('type' => 'varchar(32)', 'label' => 'NNI', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 27, 'searchall' => 1, 'css' => 'maxwidth200', 'help' => 'NNIHelp', 'bloc' => 'identite'),
		'situation_familiale' => array('type' => 'varchar(16)', 'label' => 'SituationFamiliale', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 28, 'bloc' => 'identite',
			'arrayofkeyval' => array('celibataire' => 'SitCelibataire', 'marie' => 'SitMarie', 'divorce' => 'SitDivorce', 'veuf' => 'SitVeuf')),
		'nb_enfants' => array('type' => 'integer', 'label' => 'NombreEnfants', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 29, 'css' => 'maxwidth75', 'bloc' => 'identite'),
		'adresse' => array('type' => 'text', 'label' => 'Adresse', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 30, 'css' => 'minwidth300', 'bloc' => 'identite'),
		'photo' => array('type' => 'varchar(255)', 'label' => 'Photo', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 31, 'bloc' => 'technique'),
		// Contact
		'telephone' => array('type' => 'phone', 'label' => 'Telephone', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 40, 'searchall' => 1, 'css' => 'minwidth200', 'bloc' => 'contact'),
		'whatsapp' => array('type' => 'phone', 'label' => 'WhatsApp', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 41, 'css' => 'minwidth200', 'bloc' => 'contact'),
		'email' => array('type' => 'email', 'label' => 'Email', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 42, 'css' => 'minwidth300', 'bloc' => 'contact'),
		'urgence_nom' => array('type' => 'varchar(255)', 'label' => 'UrgenceNomEmploye', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 45, 'css' => 'minwidth300', 'bloc' => 'urgence'),
		'urgence_telephone' => array('type' => 'phone', 'label' => 'UrgenceTelephoneEmploye', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 46, 'css' => 'minwidth200', 'bloc' => 'urgence'),
		'urgence_lien' => array('type' => 'varchar(64)', 'label' => 'UrgenceLienEmploye', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 47, 'css' => 'minwidth200', 'bloc' => 'urgence'),
		// Contrat
		'poste' => array('type' => 'varchar(128)', 'label' => 'PosteEmploye', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 50, 'searchall' => 1, 'css' => 'minwidth300', 'help' => 'PosteHelp', 'bloc' => 'contrat'),
		'date_embauche' => array('type' => 'date', 'label' => 'DateEmbauche', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 51, 'bloc' => 'contrat'),
		'type_contrat' => array('type' => 'varchar(16)', 'label' => 'TypeContrat', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 52, 'bloc' => 'contrat',
			'arrayofkeyval' => array('cdi' => 'ContratCDI', 'cdd' => 'ContratCDD', 'vacataire' => 'ContratVacataire', 'stagiaire' => 'ContratStagiaire', 'autre' => 'ContratAutre')),
		'date_fin_contrat' => array('type' => 'date', 'label' => 'DateFinContrat', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 53, 'bloc' => 'contrat'),
		'date_fin_essai' => array('type' => 'date', 'label' => 'DateFinEssai', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 54, 'help' => 'DateFinEssaiHelp', 'bloc' => 'contrat'),
		// Paie (droit « paie »)
		'mode_paie' => array('type' => 'varchar(8)', 'label' => 'ModePaie', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 60, 'bloc' => 'paie',
			'arrayofkeyval' => array('fixe' => 'PaieFixe', 'heure' => 'PaieHeure')),
		'salaire_base' => array('type' => 'price', 'label' => 'SalaireBase', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 61, 'css' => 'maxwidth150', 'help' => 'SalaireBaseHelp', 'bloc' => 'paie'),
		'taux_horaire' => array('type' => 'price', 'label' => 'TauxHoraire', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 62, 'css' => 'maxwidth150', 'help' => 'TauxHoraireHelp', 'bloc' => 'paie'),
		'heures_semaine' => array('type' => 'double(24,8)', 'label' => 'HeuresSemaine', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 63, 'css' => 'maxwidth75', 'help' => 'HeuresSemaineHelp', 'bloc' => 'paie'),
		'taux_heure_sup' => array('type' => 'price', 'label' => 'TauxHeureSup', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 64, 'css' => 'maxwidth150', 'help' => 'TauxHeureSupHelp', 'bloc' => 'paie'),
		'banque_nom' => array('type' => 'varchar(128)', 'label' => 'BanqueNom', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 65, 'css' => 'minwidth200', 'bloc' => 'banque'),
		'banque_compte' => array('type' => 'varchar(64)', 'label' => 'BanqueCompte', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 66, 'css' => 'minwidth200', 'bloc' => 'banque'),
		'mobile_money' => array('type' => 'varchar(64)', 'label' => 'MobileMoney', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 67, 'css' => 'minwidth200', 'help' => 'MobileMoneyHelp', 'bloc' => 'banque'),
		// Diplômes et expérience
		'diplome' => array('type' => 'varchar(255)', 'label' => 'Diplome', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 70, 'css' => 'minwidth300', 'bloc' => 'diplomes'),
		'specialite' => array('type' => 'varchar(255)', 'label' => 'Specialite', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 71, 'css' => 'minwidth300', 'bloc' => 'diplomes'),
		'experience' => array('type' => 'integer', 'label' => 'AnneesExperience', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 72, 'css' => 'maxwidth75', 'bloc' => 'diplomes'),
		'saisie_notes' => array('type' => 'integer', 'label' => 'SaisieNotesEspace', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 85, 'help' => 'SaisieNotesEspaceHelp', 'bloc' => 'espace'),
		'envoi_whatsapp' => array('type' => 'integer', 'label' => 'EnvoiWhatsappEspace', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 87, 'help' => 'EnvoiWhatsappEspaceHelp', 'bloc' => 'espace'),
		'modif_profil' => array('type' => 'integer', 'label' => 'ModifProfilEspace', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 86, 'help' => 'ModifProfilEspaceHelp', 'bloc' => 'espace'),
		'observations' => array('type' => 'text', 'label' => 'Observations', 'enabled' => '1', 'visible' => 3, 'notnull' => 0, 'position' => 80, 'css' => 'minwidth300', 'bloc' => 'diplomes'),
		// Règles de paie propres à l'employé (NULL = règle générale), modifiées depuis l'onglet « Salaires »
		'regle_retenue' => array('type' => 'varchar(8)', 'label' => 'RegleRetenue', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 88, 'bloc' => 'regles'),
		'regle_retard' => array('type' => 'varchar(8)', 'label' => 'RegleRetard', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 89, 'bloc' => 'regles'),
		'regle_hsup' => array('type' => 'integer', 'label' => 'RegleHeuresSup', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 90, 'bloc' => 'regles'),
		'regle_remplacement' => array('type' => 'varchar(8)', 'label' => 'RegleRemplacement', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 91, 'bloc' => 'regles'),
		// Géré par le programme
		'fk_user' => array('type' => 'integer', 'label' => 'CompteUtilisateur', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 90, 'bloc' => 'technique'),
		'date_statut' => array('type' => 'date', 'label' => 'DateStatut', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 91, 'bloc' => 'technique'),
	);

	/**
	 * @param DoliDB $db Handler base
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->status = self::STATUS_ACTIF;
		$this->saisie_notes = 1;
		$this->envoi_whatsapp = 1;
	}

	/**
	 * Champs techniques : le statut de l'employé remplace « actif / inactif ».
	 *
	 * @return array
	 */
	protected static function commonFields()
	{
		$f = parent::commonFields();
		$f['status']['default'] = '1';
		$f['status']['label'] = 'StatutEmploye';
		$f['status']['arrayofkeyval'] = array();
		foreach (self::statusLabels() as $k => $v) {
			$f['status']['arrayofkeyval'][$k] = $v[0];
		}
		return $f;
	}

	/* ------------------------------------------------------------------
	 * Statuts
	 * ---------------------------------------------------------------- */

	/**
	 * Statuts : valeur => [clé de traduction, couleur Dolibarr].
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	public static function statusLabels()
	{
		return array(
			self::STATUS_ACTIF => array('StatutActif', 'status4'),
			self::STATUS_ESSAI => array('StatutEssai', 'status1'),
			self::STATUS_SUSPENDU => array('StatutSuspenduConge', 'status7'),
			self::STATUS_PARTI => array('StatutPartiEmploye', 'status6'),
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
		$langs->load('personnel@personnel');
		$all = self::statusLabels();
		$s = isset($all[(int) $status]) ? $all[(int) $status] : array('Unknown', 'status0');
		$label = $langs->transnoentitiesnoconv($s[0]);
		return dolGetStatus($label, $label, '', $s[1], $mode);
	}

	/**
	 * Changements de statut possibles : statut actuel => statuts possibles.
	 *
	 * @return array<int,int[]>
	 */
	public static function transitions()
	{
		return array(
			self::STATUS_ESSAI => array(self::STATUS_ACTIF, self::STATUS_SUSPENDU, self::STATUS_PARTI),
			self::STATUS_ACTIF => array(self::STATUS_SUSPENDU, self::STATUS_PARTI),
			self::STATUS_SUSPENDU => array(self::STATUS_ACTIF, self::STATUS_PARTI),
			self::STATUS_PARTI => array(self::STATUS_ACTIF, self::STATUS_ESSAI),
		);
	}

	/**
	 * Usage des motifs de chaque statut ('' = pas de liste de motifs, commentaire libre seulement).
	 *
	 * @param  int $status Statut
	 * @return string      'suspension' | 'depart' | ''
	 */
	public static function usageMotifStatut($status)
	{
		if ((int) $status === self::STATUS_SUSPENDU) {
			return 'suspension';
		}
		return ((int) $status === self::STATUS_PARTI) ? 'depart' : '';
	}

	/**
	 * L'employé travaille-t-il actuellement à l'école (tous les statuts sauf « parti ») ?
	 *
	 * @return bool
	 */
	public function estPresent()
	{
		return (int) $this->status !== self::STATUS_PARTI;
	}

	/* ------------------------------------------------------------------
	 * Matricule
	 * ---------------------------------------------------------------- */

	/**
	 * Construit un matricule : préfixe + compteur sur N chiffres.
	 *
	 * @param  int    $numero   Numéro
	 * @param  string $prefixe  Préfixe
	 * @param  int    $longueur Nombre de chiffres
	 * @return string
	 */
	public static function formatMatricule($numero, $prefixe, $longueur)
	{
		return $prefixe.str_pad((string) ((int) $numero), max(1, (int) $longueur), '0', STR_PAD_LEFT);
	}

	/**
	 * Prochain matricule selon la configuration. Le compteur augmente à chaque employé et n'est jamais
	 * remis à zéro (il repart du plus grand numéro déjà attribué).
	 *
	 * @return string
	 */
	public function getNextMatricule()
	{
		$len = max(1, getDolGlobalInt('PERSONNEL_MATRICULE_LONGUEUR', 5));
		$sql = "SELECT MAX(CAST(RIGHT(ref, ".$len.") AS UNSIGNED)) as maxi FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE entity IN (".getEntity($this->element).") AND ref REGEXP '[0-9]{".$len."}$'";
		$resql = $this->db->query($sql);
		$o = $resql ? $this->db->fetch_object($resql) : null;
		$next = max(($o ? (int) $o->maxi : 0) + 1, getDolGlobalInt('PERSONNEL_MATRICULE_DEBUT', 1));
		return self::formatMatricule($next, getDolGlobalString('PERSONNEL_MATRICULE_PREFIXE', 'P'), $len);
	}

	/* ------------------------------------------------------------------
	 * Catégories
	 * ---------------------------------------------------------------- */

	/**
	 * Catégories de l'employé (clés : enseignant, surveillant...).
	 *
	 * @return string[]
	 */
	public function getCategories()
	{
		$out = array();
		foreach (explode(',', (string) $this->categories) as $c) {
			$c = trim($c);
			if ($c !== '') {
				$out[] = $c;
			}
		}
		return $out;
	}

	/**
	 * L'employé a-t-il cette catégorie ?
	 *
	 * @param  string $cat Catégorie
	 * @return bool
	 */
	public function aCategorie($cat)
	{
		return in_array($cat, $this->getCategories(), true);
	}

	/* ------------------------------------------------------------------
	 * Création, modification, suppression
	 * ---------------------------------------------------------------- */

	/**
	 * Validation des champs.
	 *
	 * @return int
	 */
	public function validate()
	{
		global $langs;
		$langs->loadLangs(array('personnel@personnel', 'errors'));

		foreach (array('sexe', 'situation_familiale', 'type_contrat', 'mode_paie') as $f) {
			if ($this->$f === '' || $this->$f === '-1') {
				$this->$f = null;
			}
		}
		if (empty($this->fk_nationalite) || (int) $this->fk_nationalite < 0) {
			$this->fk_nationalite = null;
		}
		foreach (array('nom_ar', 'lieu_naissance', 'nni', 'telephone', 'whatsapp', 'email', 'urgence_nom', 'urgence_telephone', 'urgence_lien', 'poste',
			'banque_nom', 'banque_compte', 'mobile_money', 'diplome', 'specialite') as $f) {
			if (is_string($this->$f)) {
				$this->$f = trim($this->$f);
			}
		}
		foreach (array('salaire_base', 'taux_horaire', 'heures_semaine', 'taux_heure_sup') as $f) {
			$this->$f = ($this->$f === '' || $this->$f === null) ? null : (float) price2num($this->$f);
		}
		// Catégories : seulement les clés connues, sans doublon
		$cats = array();
		$connues = ecole_categories_utilisateur();
		foreach ($this->getCategories() as $c) {
			if (isset($connues[$c])) {
				$cats[$c] = $c;
			}
		}
		$this->categories = $cats ? implode(',', $cats) : null;

		parent::validate();

		foreach (array('sexe', 'situation_familiale', 'type_contrat', 'mode_paie') as $f) {
			if (!empty($this->$f) && !isset($this->fields[$f]['arrayofkeyval'][$this->$f])) {
				$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities($this->fields[$f]['label']));
			}
		}
		if (!empty($this->date_naissance) && $this->date_naissance > dol_now()) {
			$this->errors[] = $langs->trans('ErrorDateNaissanceFuture');
		}
		if (!empty($this->email) && !isValidEmail($this->email)) {
			$this->errors[] = $langs->trans('ErrorBadEMail', $this->email);
		}
		foreach (array('salaire_base', 'taux_horaire', 'heures_semaine', 'taux_heure_sup') as $f) {
			if ($this->$f !== null && $this->$f < 0) {
				$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities($this->fields[$f]['label']));
			}
		}
		if ($this->nb_enfants !== null && $this->nb_enfants !== '' && ((int) $this->nb_enfants < 0 || (int) $this->nb_enfants > 30)) {
			$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('NombreEnfants'));
		}
		if (!empty($this->date_embauche) && !empty($this->date_fin_contrat) && $this->date_fin_contrat < $this->date_embauche) {
			$this->errors[] = $langs->trans('ErrorFinContratAvantEmbauche');
		}
		// Utilisateur relié : pas déjà relié à un autre employé, pas un compte de l'espace parents
		$fku = (int) ($this->link_user > 0 ? $this->link_user : $this->fk_user);
		if ($fku > 0) {
			$autre = self::employeDeUtilisateur($this->db, $fku, (int) $this->id);
			if ($autre) {
				$this->errors[] = $langs->trans('ErrorUtilisateurDejaRelie', ecole_user_label($this->db, $fku), $autre->ref.' '.$autre->nom_fr);
			} elseif ($this->link_user > 0 && personnel_est_compte_espace($this->db, $fku)) {
				$this->errors[] = $langs->trans('ErrorCompteEspace');
			}
		}

		return $this->finishValidation();
	}

	/**
	 * Crée l'employé : matricule automatique, historique de statut, utilisateur Dolibarr (nouveau ou relié).
	 *
	 * @param User $user      Utilisateur
	 * @param int  $notrigger 1 = sans trigger
	 * @return int            >0 = id, <0 = erreur
	 */
	public function create(User $user, $notrigger = 0)
	{
		$this->ref = $this->getNextMatricule();
		if (!in_array((int) $this->status, array(self::STATUS_ACTIF, self::STATUS_ESSAI), true)) {
			$this->status = self::STATUS_ACTIF;
		}
		$this->date_statut = !empty($this->date_embauche) ? $this->date_embauche : dol_now();
		if ($this->link_user > 0) {
			$this->fk_user = (int) $this->link_user;
		}
		if ($this->validate() < 0) {
			return -1;
		}

		$this->db->begin();
		$res = $this->createCommon($user, $notrigger);
		if ($res > 0) {
			$res = $this->addHistoriqueStatut($user, null, (int) $this->status, $this->date_statut, 0, '', null);
		}
		if ($res > 0) {
			$res = $this->syncUser($user);
		}
		if ($res > 0 && $this->fk_user > 0 && $this->link_user > 0) {
			$res = $this->addLog($user, 'fk_user', '', ecole_user_label($this->db, $this->fk_user), '');
		}
		if ($res > 0) {
			$this->db->commit();
			return $this->id;
		}
		$this->db->rollback();
		$this->id = 0;
		return -1;
	}

	/**
	 * Enregistre les modifications, garde l'historique des changements de paie et de catégories,
	 * puis met à jour l'utilisateur Dolibarr.
	 *
	 * @param User $user      Utilisateur
	 * @param int  $notrigger 1 = sans trigger
	 * @return int            >0 = OK, <0 = erreur
	 */
	public function update(User $user, $notrigger = 0)
	{
		if ($this->validate() < 0) {
			return -1;
		}
		$old = array();
		foreach (array_merge(self::CHAMPS_PAIE, array('categories', 'saisie_notes', 'envoi_whatsapp', 'modif_profil')) as $f) {
			$old[$f] = $this->getStoredValue($f);
		}

		$this->db->begin();
		$res = $this->updateCommon($user, $notrigger);
		foreach ($old as $f => $v) {
			if ($res <= 0) {
				break;
			}
			$avant = self::valeurLog($f, $v);
			$apres = self::valeurLog($f, $this->$f);
			if ($avant !== $apres) {
				$res = $this->addLog($user, $f, $avant, $apres, '');
			}
		}
		if ($res > 0) {
			$res = $this->syncUser($user);
		}
		if ($res > 0) {
			$this->db->commit();
			return 1;
		}
		$this->db->rollback();
		return -1;
	}

	/**
	 * Valeur d'un champ telle qu'elle est gardée dans l'historique.
	 *
	 * @param  string $f Champ
	 * @param  mixed  $v Valeur
	 * @return string
	 */
	protected static function valeurLog($f, $v)
	{
		if ($v === null || $v === '') {
			return '';
		}
		if (in_array($f, array('salaire_base', 'taux_horaire', 'heures_semaine', 'taux_heure_sup'), true)) {
			return (string) price2num((float) $v);
		}
		return (string) $v;
	}

	/**
	 * Suppression : impossible si l'employé a déjà des cours, des absences ou des salaires enregistrés
	 * (il faut alors le passer « parti »). L'utilisateur Dolibarr n'est pas supprimé : il est désactivé.
	 *
	 * @param User $user      Utilisateur
	 * @param int  $notrigger 1 = sans trigger
	 * @return int            >0 = OK, <0 = erreur
	 */
	public function delete(User $user, $notrigger = 0)
	{
		global $conf, $langs;
		$langs->load('personnel@personnel');

		$this->errors = $this->getDeleteBlockers();
		if ($this->errors) {
			$this->error = implode('<br>', $this->errors);
			return -1;
		}

		$p = $this->db->prefix();
		$this->db->begin();
		$ok = $this->db->query("DELETE FROM ".$p."ecole_employe_statut WHERE fk_employe = ".((int) $this->id))
			&& $this->db->query("DELETE FROM ".$p."ecole_employe_log WHERE fk_employe = ".((int) $this->id))
			&& $this->db->query("DELETE FROM ".$p."ecole_employe_matiere WHERE fk_employe = ".((int) $this->id))
			&& $this->db->query("DELETE FROM ".$p."ecole_employe_classe WHERE fk_employe = ".((int) $this->id))
			&& $this->db->query("DELETE FROM ".$p."ecole_employe_document WHERE fk_employe = ".((int) $this->id));
		if ($ok && $this->deleteCommon($user, $notrigger) <= 0) {
			$ok = false;
		}
		if ($ok && $this->fk_user > 0 && (int) $this->fk_user !== (int) $user->id) {
			$ok = (bool) $this->db->query("UPDATE ".$p."user SET statut = 0 WHERE rowid = ".((int) $this->fk_user)." AND admin = 0");
		}
		if (!$ok) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();

		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
		foreach (array('employe', 'document') as $part) {
			$dir = $conf->personnel->dir_output.'/'.$part.'/'.dol_sanitizeFileName($this->ref);
			if (is_dir($dir)) {
				dol_delete_dir_recursive($dir);
			}
		}
		return 1;
	}

	/**
	 * Causes qui empêchent la suppression (message traduit, vide = supprimable) : voir utilisations().
	 *
	 * @return string[]
	 */
	public function getDeleteBlockers()
	{
		global $langs;
		$langs->load('personnel@personnel');
		$raisons = $this->utilisations();
		return $raisons ? array($langs->trans('ErrorEmployeUtilise', implode(', ', $raisons))) : array();
	}

	/**
	 * Ce qui empêche la suppression : cours dans l'emploi du temps, présence, absences, salaires Dolibarr,
	 * bulletins de paie, avances et prêts du module Salaires.
	 *
	 * @return string[] Raisons traduites (vide = supprimable)
	 */
	public function utilisations()
	{
		global $langs;
		$langs->load('personnel@personnel');
		$p = $this->db->prefix();
		$out = array();
		foreach (array('ecole_salaire' => 'EmployeUsageBulletins', 'ecole_salaire_avance' => 'EmployeUsageAvances', 'ecole_salaire_pret' => 'EmployeUsagePrets') as $table => $key) {
			$nb = $this->countUsage($table, 'fk_employe');
			if ($nb > 0) {
				$out[] = $langs->transnoentitiesnoconv($key).' ('.$nb.')';
			}
		}
		$checks = array(
			array("SELECT COUNT(*) as nb FROM ".$p."ecole_cours_prof WHERE fk_employe = ".((int) $this->id)." OR fk_remplacant = ".((int) $this->id), 'PresenceEnseignant'),
			array("SELECT COUNT(*) as nb FROM ".$p."ecole_absence_perso WHERE fk_employe = ".((int) $this->id), 'AbsencesPersonnel'),
		);
		if ($this->fk_user > 0) {
			$checks[] = array("SELECT COUNT(*) as nb FROM ".$p."ecole_edt_cours WHERE fk_user = ".((int) $this->fk_user), 'EmploiDuTemps');
			$checks[] = array("SELECT COUNT(*) as nb FROM ".$p."salary WHERE fk_user = ".((int) $this->fk_user), 'Salaires');
		}
		foreach ($checks as $c) {
			$resql = $this->db->query($c[0]);
			$o = $resql ? $this->db->fetch_object($resql) : null;
			if ($o && (int) $o->nb > 0) {
				$out[] = $langs->transnoentitiesnoconv($c[1]).' ('.((int) $o->nb).')';
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Utilisateur Dolibarr (sens unique : fiche employé → utilisateur)
	 * ---------------------------------------------------------------- */

	/**
	 * Employé relié à un utilisateur Dolibarr.
	 *
	 * @param  DoliDB $db      Handler base
	 * @param  int    $fk_user Utilisateur
	 * @param  int    $exclude Employé à ignorer
	 * @return object|null     Ligne (rowid, ref, nom_fr, nom_ar, status)
	 */
	public static function employeDeUtilisateur($db, $fk_user, $exclude = 0)
	{
		if ((int) $fk_user <= 0) {
			return null;
		}
		$sql = "SELECT rowid, ref, nom_fr, nom_ar, status FROM ".$db->prefix()."ecole_employe WHERE entity IN (".getEntity('ecole_employe').")";
		$sql .= " AND fk_user = ".((int) $fk_user)." AND rowid <> ".((int) $exclude);
		$resql = $db->query($sql);
		return ($resql && ($o = $db->fetch_object($resql))) ? $o : null;
	}

	/**
	 * Crée ou met à jour l'utilisateur Dolibarr de l'employé : nom, téléphone, e-mail (s'il n'est pas déjà pris),
	 * emploi, dates d'embauche et de fin, salaire / prix de l'heure / heures par semaine, catégories de l'école,
	 * compte actif (désactivé quand l'employé est parti). L'identifiant d'un nouveau compte est le matricule ;
	 * celui d'un compte existant relié n'est jamais changé.
	 *
	 * @param  User $user Utilisateur
	 * @return int        1 si OK, -1 sinon
	 */
	public function syncUser(User $user)
	{
		global $langs, $conf;
		require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
		$langs->loadLangs(array('personnel@personnel', 'errors', 'users'));

		$u = new User($this->db);
		$exists = ($this->fk_user > 0 && $u->fetch((int) $this->fk_user) > 0);
		if (!$exists) {
			$u = new User($this->db);
			$u->login = $this->ref;
			$u->entity = $conf->entity;
			$u->admin = 0;
		}
		$u->lastname = (string) $this->nom_fr;
		$u->firstname = '';
		$u->employee = 1;
		$u->gender = ($this->sexe === 'F') ? 'woman' : (($this->sexe === 'M') ? 'man' : -1);
		$u->birth = !empty($this->date_naissance) ? $this->date_naissance : '';
		$u->user_mobile = (string) $this->telephone;
		$u->personal_mobile = (string) $this->whatsapp;
		$u->address = (string) $this->adresse;
		$u->job = (string) ($this->poste ? $this->poste : '');
		$u->national_registration_number = (string) $this->nni;
		$u->dateemployment = !empty($this->date_embauche) ? $this->date_embauche : '';
		$u->dateemploymentend = !empty($this->date_fin_contrat) ? $this->date_fin_contrat : '';
		$u->salary = ($this->mode_paie === 'fixe' && $this->salaire_base !== null) ? price2num($this->salaire_base) : '';
		$u->thm = ($this->taux_horaire !== null) ? price2num($this->taux_horaire) : '';
		$u->weeklyhours = ($this->heures_semaine !== null && $this->heures_semaine > 0) ? price2num($this->heures_semaine) : '';
		// E-mail : seulement s'il n'appartient pas déjà à un autre utilisateur (Dolibarr refuse les doublons)
		if (!empty($this->email) && !$this->emailPrisParAutreUtilisateur($this->email, $exists ? (int) $u->id : 0)) {
			$u->email = $this->email;
		} elseif (!$exists) {
			$u->email = '';
		}
		$u->array_options['options_ecole_categorie'] = (string) $this->categories;

		if ($exists) {
			$res = ($u->update($user, 1) >= 0) ? 1 : -1; // 0 = rien n'a changé
		} else {
			$res = $u->create($user);
			if ($res == -6) {
				$this->error = $langs->trans('ErrorLoginDejaPris', $this->ref);
				$this->errors = array($this->error);
				return -1;
			}
			if ($res > 0) {
				$this->fk_user = (int) $u->id;
				$res = $this->db->query("UPDATE ".$this->db->prefix().$this->table_element." SET fk_user = ".((int) $u->id)." WHERE rowid = ".((int) $this->id)) ? 1 : -1;
				if ($res > 0) {
					$res = ($u->update($user, 1) >= 0) ? 1 : -1; // champs d'emploi et catégories (non enregistrés par la création)
				}
			}
		}
		if ($res > 0) {
			// Compte désactivé quand l'employé est parti, réactivé à son retour (jamais son propre compte ni un administrateur)
			$actif = $this->estPresent() ? 1 : 0;
			if ((int) $u->statut !== $actif && (int) $u->id !== (int) $user->id && !($actif === 0 && !empty($u->admin))) {
				$res = $this->db->query("UPDATE ".$this->db->prefix()."user SET statut = ".$actif." WHERE rowid = ".((int) $u->id)) ? 1 : -1;
			}
		}
		if ($res <= 0) {
			$this->error = $langs->trans('ErrorSyncUtilisateur').' '.($u->error ? $langs->trans($u->error) : '');
			foreach ((array) $u->errors as $e) {
				$this->error .= ' '.$langs->trans($e);
			}
			$this->errors = array($this->error);
			return -1;
		}
		return 1;
	}

	/**
	 * Cet e-mail est-il déjà celui d'un autre utilisateur Dolibarr ?
	 *
	 * @param  string $email   E-mail
	 * @param  int    $exclude Utilisateur à ignorer
	 * @return bool
	 */
	protected function emailPrisParAutreUtilisateur($email, $exclude)
	{
		$sql = "SELECT COUNT(*) as nb FROM ".$this->db->prefix()."user WHERE email = '".$this->db->escape($email)."' AND rowid <> ".((int) $exclude);
		$resql = $this->db->query($sql);
		$o = $resql ? $this->db->fetch_object($resql) : null;
		return $o && (int) $o->nb > 0;
	}

	/**
	 * Crée une fiche employé reliée pour chaque utilisateur actif qui a déjà une catégorie de l'école
	 * ou un cours dans l'emploi du temps et qui n'a pas encore de fiche (activation du module).
	 * Les comptes de l'espace parents et élèves sont ignorés.
	 *
	 * @param  DoliDB $db   Handler base
	 * @param  User   $user Utilisateur
	 * @return int          Nombre de fiches créées
	 */
	public static function importerUtilisateurs($db, $user)
	{
		$p = $db->prefix();
		$sql = "SELECT u.rowid, u.firstname, u.lastname, u.login, u.email, u.user_mobile, u.personal_mobile, u.job, u.gender, u.birth, u.dateemployment, u.salary, u.thm, ef.ecole_categorie";
		$sql .= " FROM ".$p."user u LEFT JOIN ".$p."user_extrafields ef ON ef.fk_object = u.rowid";
		$sql .= " WHERE u.entity IN (".getEntity('user').") AND u.statut = 1";
		$sql .= " AND ((ef.ecole_categorie IS NOT NULL AND ef.ecole_categorie <> '') OR u.rowid IN (SELECT fk_user FROM ".$p."ecole_edt_cours WHERE fk_user IS NOT NULL))";
		$sql .= " AND u.rowid NOT IN (SELECT fk_user FROM ".$p."ecole_employe WHERE fk_user IS NOT NULL)";
		if (personnel_table_existe($db, 'ecole_acces')) {
			$sql .= " AND u.rowid NOT IN (SELECT fk_user FROM ".$p."ecole_acces WHERE fk_user IS NOT NULL)";
		}
		$resql = $db->query($sql);
		$rows = array();
		while ($resql && ($o = $db->fetch_object($resql))) {
			$rows[] = $o;
		}
		$n = 0;
		foreach ($rows as $o) {
			$e = new EcoleEmploye($db);
			$e->nom_fr = trim($o->firstname.' '.$o->lastname) !== '' ? trim($o->firstname.' '.$o->lastname) : $o->login;
			$e->link_user = (int) $o->rowid;
			$e->categories = (string) $o->ecole_categorie;
			if ($e->categories === '') {
				$e->categories = 'enseignant'; // enseignant dans l'emploi du temps
			}
			$e->telephone = (string) $o->user_mobile;
			$e->whatsapp = (string) $o->personal_mobile;
			$e->email = (string) $o->email;
			$e->poste = (string) $o->job;
			$e->sexe = ($o->gender === 'woman') ? 'F' : (($o->gender === 'man') ? 'M' : null);
			$e->date_naissance = $o->birth ? $db->jdate($o->birth) : null;
			$e->date_embauche = $o->dateemployment ? $db->jdate($o->dateemployment) : null;
			if ((float) $o->salary > 0) {
				$e->mode_paie = 'fixe';
				$e->salaire_base = (float) $o->salary;
			}
			if ((float) $o->thm > 0) {
				$e->taux_horaire = (float) $o->thm;
			}
			if ($e->create($user) > 0) {
				$n++;
			}
		}
		return $n;
	}

	/* ------------------------------------------------------------------
	 * Statuts et historique
	 * ---------------------------------------------------------------- */

	/**
	 * Change le statut (fin de période d'essai, suspension / congé, départ, reprise, réembauche).
	 *
	 * @param  User     $user      Utilisateur
	 * @param  int      $status    Nouveau statut
	 * @param  int      $date      Date d'effet
	 * @param  int      $fk_motif  Motif de la liste (0 = aucun)
	 * @param  string   $motif     Commentaire
	 * @param  int|null $datefin   Fin prévue (suspension / congé)
	 * @return int                 1 si OK, -1 sinon
	 */
	public function changerStatut(User $user, $status, $date, $fk_motif = 0, $motif = '', $datefin = null)
	{
		global $langs;
		$langs->load('personnel@personnel');
		if (!$this->checkId()) {
			return -1;
		}
		$trans = self::transitions();
		if (!isset($trans[(int) $this->status]) || !in_array((int) $status, $trans[(int) $this->status], true)) {
			$this->error = $langs->trans('ErrorChangementStatutImpossible', $this->getLibStatut(1), $this->LibStatut($status, 1));
			$this->errors = array($this->error);
			return -1;
		}
		if (empty($date)) {
			$this->error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('DateEffet'));
			$this->errors = array($this->error);
			return -1;
		}
		if ($datefin && $datefin < $date) {
			$this->error = $langs->trans('ErrorDateFinAvantDebut');
			$this->errors = array($this->error);
			return -1;
		}
		$old = (int) $this->status;
		$this->db->begin();
		$sql = "UPDATE ".$this->db->prefix().$this->table_element." SET status = ".((int) $status).", date_statut = '".$this->db->idate($date)."', fk_user_modif = ".((int) $user->id);
		if ((int) $status === self::STATUS_PARTI) {
			// Départ : fin du contrat à la date de départ (reprise dans l'utilisateur Dolibarr)
			$sql .= ", date_fin_contrat = '".$this->db->idate($date)."'";
		} elseif ($old === self::STATUS_PARTI) {
			// Retour après un départ : nouvelle date d'embauche, plus de date de fin
			$sql .= ", date_embauche = '".$this->db->idate($date)."', date_fin_contrat = NULL";
		}
		$sql .= " WHERE rowid = ".((int) $this->id);
		$res = $this->db->query($sql) ? 1 : -1;
		if ($res > 0) {
			$res = $this->addHistoriqueStatut($user, $old, (int) $status, $date, (int) $fk_motif, (string) $motif, $datefin);
		}
		if ($res > 0) {
			$this->status = (int) $status;
			$this->date_statut = $date;
			if ((int) $status === self::STATUS_PARTI) {
				$this->date_fin_contrat = $date;
			} elseif ($old === self::STATUS_PARTI) {
				$this->date_embauche = $date;
				$this->date_fin_contrat = null;
			}
			$res = $this->syncUser($user);
		}
		if ($res > 0) {
			$this->db->commit();
			return 1;
		}
		if (empty($this->error)) {
			$this->error = $this->db->lasterror();
		}
		$this->db->rollback();
		return -1;
	}

	/**
	 * Ajoute une ligne à l'historique des statuts.
	 *
	 * @param  User     $user     Utilisateur
	 * @param  int|null $old      Ancien statut (null à la création)
	 * @param  int      $new      Nouveau statut
	 * @param  int      $date     Date d'effet
	 * @param  int      $fk_motif Motif de la liste
	 * @param  string   $motif    Commentaire
	 * @param  int|null $datefin  Fin prévue
	 * @return int                1 si OK, -1 sinon
	 */
	protected function addHistoriqueStatut(User $user, $old, $new, $date, $fk_motif, $motif, $datefin)
	{
		global $conf;
		$sql = "INSERT INTO ".$this->db->prefix()."ecole_employe_statut (entity, fk_employe, status_old, status_new, date_statut, date_fin_prevue, fk_motif, motif, fk_user, date_creation)";
		$sql .= " VALUES (".((int) $conf->entity).", ".((int) $this->id).", ".($old === null ? "NULL" : (int) $old).", ".((int) $new).", '".$this->db->idate($date)."'";
		$sql .= ", ".($datefin ? "'".$this->db->idate($datefin)."'" : "NULL").", ".($fk_motif > 0 ? (int) $fk_motif : "NULL");
		$sql .= ", ".($motif !== '' ? "'".$this->db->escape(dol_trunc($motif, 250, 'right', 'UTF-8', 1))."'" : "NULL").", ".((int) $user->id).", '".$this->db->idate(dol_now())."')";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Historique des statuts (plus récent en premier), avec le libellé du motif.
	 *
	 * @return array<int,object>
	 */
	public function getHistoriqueStatuts()
	{
		$out = array();
		$sql = "SELECT h.rowid, h.status_old, h.status_new, h.date_statut, h.date_fin_prevue, h.fk_motif, h.motif, h.fk_user, h.date_creation, m.label_fr, m.label_ar";
		$sql .= " FROM ".$this->db->prefix()."ecole_employe_statut h LEFT JOIN ".$this->db->prefix()."ecole_employe_motif m ON m.rowid = h.fk_motif";
		$sql .= " WHERE h.fk_employe = ".((int) $this->id)." ORDER BY h.date_statut DESC, h.rowid DESC";
		$resql = $this->db->query($sql);
		while ($resql && ($o = $this->db->fetch_object($resql))) {
			$out[] = $o;
		}
		return $out;
	}

	/**
	 * Dernier changement de statut (motif et fin prévue du statut actuel).
	 *
	 * @return object|null
	 */
	public function getDernierStatut()
	{
		$h = $this->getHistoriqueStatuts();
		return $h ? $h[0] : null;
	}

	/**
	 * Périodes où l'employé était suspendu / en congé ou parti (aucun cours attendu) : [début, fin] en AAAA-MM-JJ,
	 * fin '' = toujours en cours.
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	public function periodesSansTravail()
	{
		$lignes = array_reverse($this->getHistoriqueStatuts()); // plus ancien d'abord
		$out = array();
		$debut = null;
		foreach ($lignes as $h) {
			$d = dol_print_date($this->db->jdate($h->date_statut), '%Y-%m-%d');
			$arret = in_array((int) $h->status_new, array(self::STATUS_SUSPENDU, self::STATUS_PARTI), true);
			if ($arret && $debut === null) {
				$debut = $d;
			} elseif (!$arret && $debut !== null) {
				$out[] = array($debut, dol_print_date(dol_time_plus_duree(strtotime($d.' 12:00:00'), -1, 'd'), '%Y-%m-%d'));
				$debut = null;
			}
		}
		if ($debut !== null) {
			$out[] = array($debut, '');
		}
		return $out;
	}

	/**
	 * Périodes d'arrêt avec leur nature (suspension / congé, ou départ) : [début, fin, statut] en AAAA-MM-JJ,
	 * fin '' = toujours en cours. Sert au salaire de base (une suspension peut être payée ou non, un départ jamais).
	 *
	 * @return array<int,array{0:string,1:string,2:int}>
	 */
	public function periodesArret()
	{
		$out = array();
		$cur = null;
		foreach (array_reverse($this->getHistoriqueStatuts()) as $h) {
			$d = dol_print_date($this->db->jdate($h->date_statut), '%Y-%m-%d');
			$st = (int) $h->status_new;
			$arret = in_array($st, array(self::STATUS_SUSPENDU, self::STATUS_PARTI), true);
			if ($cur !== null && (!$arret || $st !== $cur[1])) {
				$out[] = array($cur[0], dol_print_date(dol_time_plus_duree(strtotime($d.' 12:00:00'), -1, 'd'), '%Y-%m-%d'), $cur[1]);
				$cur = null;
			}
			if ($arret && $cur === null) {
				$cur = array($d, $st);
			}
		}
		if ($cur !== null) {
			$out[] = array($cur[0], '', $cur[1]);
		}
		return $out;
	}

	/**
	 * Ajoute une ligne à l'historique des modifications (paie, catégories, compte relié...).
	 *
	 * @param  User   $user  Utilisateur
	 * @param  string $champ Champ
	 * @param  string $avant Ancienne valeur
	 * @param  string $apres Nouvelle valeur
	 * @param  string $motif Motif
	 * @return int           1 si OK, -1 sinon
	 */
	public function addLog(User $user, $champ, $avant, $apres, $motif)
	{
		global $conf;
		$sql = "INSERT INTO ".$this->db->prefix()."ecole_employe_log (entity, fk_employe, champ, avant, apres, motif, fk_user, date_creation)";
		$sql .= " VALUES (".((int) $conf->entity).", ".((int) $this->id).", '".$this->db->escape($champ)."'";
		$sql .= ", ".($avant !== '' ? "'".$this->db->escape(dol_trunc($avant, 250, 'right', 'UTF-8', 1))."'" : "NULL");
		$sql .= ", ".($apres !== '' ? "'".$this->db->escape(dol_trunc($apres, 250, 'right', 'UTF-8', 1))."'" : "NULL");
		$sql .= ", ".($motif !== '' ? "'".$this->db->escape(dol_trunc($motif, 250, 'right', 'UTF-8', 1))."'" : "NULL").", ".((int) $user->id).", '".$this->db->idate(dol_now())."')";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Historique des modifications (plus récent en premier).
	 *
	 * @return array<int,object>
	 */
	public function getLogs()
	{
		$out = array();
		$resql = $this->db->query("SELECT rowid, champ, avant, apres, motif, fk_user, date_creation FROM ".$this->db->prefix()."ecole_employe_log WHERE fk_employe = ".((int) $this->id)." ORDER BY date_creation DESC, rowid DESC");
		while ($resql && ($o = $this->db->fetch_object($resql))) {
			$out[] = $o;
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Matières qu'il peut enseigner
	 * ---------------------------------------------------------------- */

	/**
	 * Matières que l'employé peut enseigner : id => ligne (ref, label_fr, label_ar).
	 *
	 * @return array<int,object>
	 */
	public function getMatieres()
	{
		$out = array();
		$sql = "SELECT m.rowid, m.ref, m.label_fr, m.label_ar FROM ".$this->db->prefix()."ecole_employe_matiere em";
		$sql .= " INNER JOIN ".$this->db->prefix()."ecole_matiere m ON m.rowid = em.fk_matiere WHERE em.fk_employe = ".((int) $this->id)." ORDER BY m.label_fr";
		$resql = $this->db->query($sql);
		while ($resql && ($o = $this->db->fetch_object($resql))) {
			$out[(int) $o->rowid] = $o;
		}
		return $out;
	}

	/**
	 * Enregistre la liste des matières que l'employé peut enseigner (changement gardé dans l'historique).
	 *
	 * @param  User  $user Utilisateur
	 * @param  int[] $ids  Matières
	 * @return int         1 si OK, -1 sinon
	 */
	public function setMatieres(User $user, $ids)
	{
		global $conf;
		if (!$this->checkId()) {
			return -1;
		}
		$ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
		$avant = array_keys($this->getMatieres());
		sort($ids);
		sort($avant);
		if ($ids === $avant) {
			return 1;
		}
		$p = $this->db->prefix();
		$ok = (bool) $this->db->query("DELETE FROM ".$p."ecole_employe_matiere WHERE fk_employe = ".((int) $this->id));
		foreach ($ids as $mid) {
			$ok = $ok && $this->db->query("INSERT INTO ".$p."ecole_employe_matiere (entity, fk_employe, fk_matiere) VALUES (".((int) $conf->entity).", ".((int) $this->id).", ".$mid.")");
		}
		if (!$ok) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$refs = function ($list) use ($p) {
			if (!$list) {
				return '';
			}
			$out = array();
			$resql = $this->db->query("SELECT ref FROM ".$p."ecole_matiere WHERE rowid IN (".implode(',', $list).") ORDER BY ref");
			while ($resql && ($o = $this->db->fetch_object($resql))) {
				$out[] = $o->ref;
			}
			return implode(', ', $out);
		};
		return $this->addLog($user, 'matieres', $refs($avant), $refs($ids), '');
	}

	/**
	 * Matières que l'enseignant a dans l'emploi du temps mais qui ne sont pas (ou plus) sur sa fiche :
	 * id => « REF - Libellé ». Sert à prévenir quand une matière est retirée de la fiche.
	 *
	 * @return array<int,string>
	 */
	public function matieresEdtHorsFiche()
	{
		$out = array();
		if ((int) $this->fk_user <= 0) {
			return $out;
		}
		$fiche = array_keys($this->getMatieres());
		$sql = "SELECT DISTINCT m.rowid, m.ref, m.label_fr FROM ".$this->db->prefix()."ecole_edt_cours e INNER JOIN ".$this->db->prefix()."ecole_matiere m ON m.rowid = e.fk_matiere";
		$sql .= " WHERE e.fk_user = ".((int) $this->fk_user);
		$resql = $this->db->query($sql);
		while ($resql && ($o = $this->db->fetch_object($resql))) {
			if (!in_array((int) $o->rowid, $fiche, true)) {
				$out[(int) $o->rowid] = $o->ref.' - '.$o->label_fr;
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Classes où il enseigne (déclarées sur la fiche, sans blocage)
	 * ---------------------------------------------------------------- */

	/**
	 * Classes déclarées sur la fiche : id => ligne (ref, label_fr, label_ar).
	 *
	 * @return array<int,object>
	 */
	public function getClassesDeclarees()
	{
		$out = array();
		$sql = "SELECT c.rowid, c.ref, c.label_fr, c.label_ar FROM ".$this->db->prefix()."ecole_employe_classe ec";
		$sql .= " INNER JOIN ".$this->db->prefix()."ecole_classe c ON c.rowid = ec.fk_classe LEFT JOIN ".$this->db->prefix()."ecole_niveau n ON n.rowid = c.fk_niveau";
		$sql .= " WHERE ec.fk_employe = ".((int) $this->id)." ORDER BY n.position, c.rowid";
		$resql = $this->db->query($sql);
		while ($resql && ($o = $this->db->fetch_object($resql))) {
			$out[(int) $o->rowid] = $o;
		}
		return $out;
	}

	/**
	 * Enregistre les classes déclarées (changement gardé dans l'historique).
	 *
	 * @param  User  $user Utilisateur
	 * @param  int[] $ids  Classes
	 * @return int         1 si OK, -1 sinon
	 */
	public function setClassesDeclarees(User $user, $ids)
	{
		global $conf;
		if (!$this->checkId()) {
			return -1;
		}
		$ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
		$avant = array_keys($this->getClassesDeclarees());
		sort($ids);
		sort($avant);
		if ($ids === $avant) {
			return 1;
		}
		$p = $this->db->prefix();
		$ok = (bool) $this->db->query("DELETE FROM ".$p."ecole_employe_classe WHERE fk_employe = ".((int) $this->id));
		foreach ($ids as $cid) {
			$ok = $ok && $this->db->query("INSERT INTO ".$p."ecole_employe_classe (entity, fk_employe, fk_classe) VALUES (".((int) $conf->entity).", ".((int) $this->id).", ".$cid.")");
		}
		if (!$ok) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$refs = function ($list) use ($p) {
			if (!$list) {
				return '';
			}
			$out = array();
			$resql = $this->db->query("SELECT ref FROM ".$p."ecole_classe WHERE rowid IN (".implode(',', $list).") ORDER BY ref");
			while ($resql && ($o = $this->db->fetch_object($resql))) {
				$out[] = $o->ref;
			}
			return implode(', ', $out);
		};
		return $this->addLog($user, 'classes', $refs($avant), $refs($ids), '');
	}

	/* ------------------------------------------------------------------
	 * Pièces du dossier
	 * ---------------------------------------------------------------- */

	/**
	 * Pièces demandées à cet employé (types actifs pour ses catégories) avec leur état, plus les pièces
	 * déjà renseignées d'un type désactivé ou d'une autre catégorie.
	 *
	 * @return array<int,object> id du type => (ref, label_fr, label_ar, demandee, fourni, date_fourni, filename, fk_user)
	 */
	public function getPieces()
	{
		$out = array();
		$cats = $this->getCategories();
		$sql = "SELECT t.rowid as typeid, t.ref, t.label_fr, t.label_ar, t.categories, t.status as type_status, d.rowid as did, d.fourni, d.date_fourni, d.filename, d.fk_user";
		$sql .= " FROM ".$this->db->prefix()."ecole_employe_doc_type t";
		$sql .= " LEFT JOIN ".$this->db->prefix()."ecole_employe_document d ON d.fk_doc_type = t.rowid AND d.fk_employe = ".((int) $this->id);
		$sql .= " WHERE t.entity IN (".getEntity('ecole_employe_doc_type').")";
		$sql .= " ORDER BY t.position, t.ref";
		$resql = $this->db->query($sql);
		while ($resql && ($o = $this->db->fetch_object($resql))) {
			$tcats = array_filter(array_map('trim', explode(',', (string) $o->categories)));
			$o->demandee = ((int) $o->type_status === 1) && (empty($tcats) || array_intersect($tcats, $cats));
			if (!$o->demandee && empty($o->did)) {
				continue;
			}
			$o->date_fourni = $this->db->jdate($o->date_fourni);
			$out[(int) $o->typeid] = $o;
		}
		return $out;
	}

	/**
	 * Pièces demandées non fournies.
	 *
	 * @return array<int,object>
	 */
	public function getPiecesManquantes()
	{
		$out = array();
		foreach ($this->getPieces() as $id => $o) {
			if ($o->demandee && empty($o->fourni)) {
				$out[$id] = $o;
			}
		}
		return $out;
	}

	/**
	 * Condition SQL (table t = ecole_employe) : employés auxquels il manque au moins une pièce demandée.
	 *
	 * @param  DoliDB $db Handler base
	 * @return string
	 */
	public static function sqlPiecesManquantes($db)
	{
		$p = $db->prefix();
		return "EXISTS (SELECT 1 FROM ".$p."ecole_employe_doc_type dt WHERE dt.status = 1 AND dt.entity IN (".getEntity('ecole_employe_doc_type').")"
			." AND (dt.categories IS NULL OR dt.categories = '' OR EXISTS (SELECT 1 FROM (SELECT 'direction' c UNION SELECT 'enseignant' UNION SELECT 'surveillant' UNION SELECT 'secretariat'"
			." UNION SELECT 'comptable' UNION SELECT 'agent' UNION SELECT 'chauffeur' UNION SELECT 'autre') k WHERE FIND_IN_SET(k.c, dt.categories) AND FIND_IN_SET(k.c, t.categories)))"
			." AND NOT EXISTS (SELECT 1 FROM ".$p."ecole_employe_document dd WHERE dd.fk_employe = t.rowid AND dd.fk_doc_type = dt.rowid AND dd.fourni = 1))";
	}

	/**
	 * Enregistre l'état d'une pièce (fourni, date, fichier).
	 *
	 * @param  User        $user     Utilisateur
	 * @param  int         $typeid   Type de pièce
	 * @param  bool        $fourni   Fournie ?
	 * @param  int|null    $date     Date de remise
	 * @param  string|null $filename Nom du fichier (null = inchangé, '' = aucun)
	 * @return int                   1 si OK, -1 sinon
	 */
	public function setPiece(User $user, $typeid, $fourni, $date, $filename = null)
	{
		global $conf;
		if (!$this->checkId()) {
			return -1;
		}
		$p = $this->db->prefix();
		$resql = $this->db->query("SELECT rowid FROM ".$p."ecole_employe_document WHERE fk_employe = ".((int) $this->id)." AND fk_doc_type = ".((int) $typeid));
		$o = $resql ? $this->db->fetch_object($resql) : null;
		$d = ($fourni && $date) ? "'".$this->db->idate($date)."'" : "NULL";
		if ($o) {
			$sql = "UPDATE ".$p."ecole_employe_document SET fourni = ".($fourni ? 1 : 0).", date_fourni = ".$d.", fk_user = ".((int) $user->id);
			if ($filename !== null) {
				$sql .= ", filename = ".($filename !== '' ? "'".$this->db->escape($filename)."'" : "NULL");
			}
			$sql .= " WHERE rowid = ".((int) $o->rowid);
		} else {
			$sql = "INSERT INTO ".$p."ecole_employe_document (entity, fk_employe, fk_doc_type, fourni, date_fourni, filename, fk_user)";
			$sql .= " VALUES (".((int) $conf->entity).", ".((int) $this->id).", ".((int) $typeid).", ".($fourni ? 1 : 0).", ".$d;
			$sql .= ", ".($filename ? "'".$this->db->escape($filename)."'" : "NULL").", ".((int) $user->id).")";
		}
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Dossier des fichiers scannés (relatif au dossier du module) : « document/MATRICULE »
	 * (accès aux fichiers : droit « Documents : voir »).
	 *
	 * @return string
	 */
	public function getDocumentsSubdir()
	{
		return 'document/'.dol_sanitizeFileName($this->ref);
	}

	/**
	 * Dossier de la photo (relatif au dossier du module) : « employe/MATRICULE/photo » (droit « Employés : voir »).
	 *
	 * @return string
	 */
	public function getPhotoSubdir()
	{
		return 'employe/'.dol_sanitizeFileName($this->ref).'/photo';
	}

	/* ------------------------------------------------------------------
	 * Divers
	 * ---------------------------------------------------------------- */

	/**
	 * L'employé est-il enregistré ?
	 *
	 * @return bool
	 */
	protected function checkId()
	{
		global $langs;
		if ((int) $this->id > 0) {
			return true;
		}
		$this->error = $langs->trans('ErrorRecordNotFound');
		$this->errors = array($this->error);
		return false;
	}

	/**
	 * Âge en années à aujourd'hui.
	 *
	 * @return int|null
	 */
	public function getAge()
	{
		if (empty($this->date_naissance)) {
			return null;
		}
		$b = dol_getdate($this->date_naissance);
		$n = dol_getdate(dol_now());
		$age = $n['year'] - $b['year'];
		if ($n['mon'] < $b['mon'] || ($n['mon'] == $b['mon'] && $n['mday'] < $b['mday'])) {
			$age--;
		}
		return $age;
	}

	/**
	 * Ancienneté à aujourd'hui : « 2 ans 3 mois ».
	 *
	 * @return string
	 */
	public function getAnciennete()
	{
		global $langs;
		if (empty($this->date_embauche) || $this->date_embauche > dol_now()) {
			return '';
		}
		$b = dol_getdate($this->date_embauche);
		$fin = ((int) $this->status === self::STATUS_PARTI && !empty($this->date_fin_contrat)) ? $this->date_fin_contrat : dol_now();
		$n = dol_getdate($fin);
		$mois = ($n['year'] - $b['year']) * 12 + ($n['mon'] - $b['mon']) - ($n['mday'] < $b['mday'] ? 1 : 0);
		$mois = max(0, $mois);
		return $langs->trans('AncienneteAnsMois', intdiv($mois, 12), $mois % 12);
	}

	/**
	 * Affichage d'un champ : nationalité (pays), catégories (badges), montants.
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
		if ($key === 'fk_nationalite') {
			require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
			return (int) $value > 0 ? dol_escape_htmltag(getCountry((int) $value, '0')) : '';
		}
		if ($key === 'categories') {
			return personnel_categories_badges((string) $value);
		}
		if (in_array($key, array('salaire_base', 'taux_horaire', 'taux_heure_sup'), true)) {
			return ($value === null || $value === '') ? '' : price((float) $value, 0, '', 1, -1, -1, getDolGlobalString('MAIN_MONNAIE'));
		}
		if ($key === 'heures_semaine') {
			return ($value === null || $value === '' || (float) $value <= 0) ? '' : dol_escape_htmltag(price2num((float) $value).' h');
		}
		return parent::showOutputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss);
	}

	/**
	 * Champ de saisie : nationalité (liste des pays), catégories (choix multiple), réglages de l'espace.
	 *
	 * @param array  $val         Définition du champ
	 * @param string $key         Nom du champ
	 * @param mixed  $value       Valeur
	 * @param string $moreparam   Paramètres HTML
	 * @param string $keysuffix   Suffixe
	 * @param string $keyprefix   Préfixe
	 * @param mixed  $morecss     CSS
	 * @param int    $nonewbutton Sans bouton « nouveau »
	 * @return string
	 */
	public function showInputField($val, $key, $value, $moreparam = '', $keysuffix = '', $keyprefix = '', $morecss = 0, $nonewbutton = 0)
	{
		global $form, $mysoc, $langs;
		if (!is_object($form)) {
			require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
			$form = new Form($this->db);
		}
		if ($key === 'fk_nationalite') {
			return $form->select_country((int) $value > 0 ? (int) $value : (is_object($mysoc) ? $mysoc->country_id : ''), $keyprefix.$key.$keysuffix, '', 0, 'minwidth200');
		}
		if ($key === 'saisie_notes' || $key === 'envoi_whatsapp') {
			$v = ($value === null || $value === '') ? '1' : (string) ((int) $value);
			return $form->selectarray($keyprefix.$key.$keysuffix, array('1' => $langs->trans('Autorisee'), '0' => $langs->trans('Bloquee')), $v, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200');
		}
		if ($key === 'modif_profil') {
			$general = getDolGlobalInt('PERSONNEL_ESPACE_MODIF_PROFIL') ? 'Autorisee' : 'Bloquee';
			$v = ($value === null || $value === '') ? '' : (string) ((int) $value);
			return $form->selectarray($keyprefix.$key.$keysuffix, array('1' => $langs->trans('Autorisee'), '0' => $langs->trans('Bloquee')), $v, $langs->trans('SelonReglageGeneral', $langs->transnoentities($general)), 0, 0, '', 0, 0, 0, '', 'minwidth300');
		}
		if ($key === 'categories') {
			$sel = array_filter(array_map('trim', explode(',', (string) $value)));
			$out = $form->multiselectarray($keyprefix.$key.$keysuffix, personnel_categories_choix(), $sel, 0, 0, 'minwidth300', 0, '100%');
			return $out;
		}
		return parent::showInputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss, $nonewbutton);
	}
}
