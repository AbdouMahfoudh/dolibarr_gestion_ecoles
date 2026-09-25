<?php
/**
 * Ligne de l'emploi du temps des cours : une classe, un jour, un créneau, une matière,
 * un enseignant et une salle. Détecte les conflits et archive chaque modification.
 *
 * Fichier : custom/classes/class/ecole_edt_cours.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');
dol_include_once('/classes/core/lib/classes.lib.php');

/**
 * Class EcoleEdtCours
 */
class EcoleEdtCours extends EcoleObject
{
	/** @var string */
	public $element = 'ecole_edt_cours';
	/** @var string */
	public $table_element = 'ecole_edt_cours';
	/** @var string */
	public $picto = 'fa-calendar-alt';

	public $required = array('fk_classe', 'jour', 'fk_creneau', 'fk_matiere');

	public $fk_classe;
	public $jour;
	public $fk_creneau;
	public $fk_matiere;
	public $fk_user;
	public $fk_salle;

	/** @var array */
	public $fields = array(
		'fk_classe' => array('type' => 'integer', 'label' => 'Classe', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1),
		'jour' => array('type' => 'integer', 'label' => 'Jour', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20),
		'fk_creneau' => array('type' => 'integer', 'label' => 'Creneau', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 30),
		'fk_matiere' => array('type' => 'integer', 'label' => 'Matiere', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 40),
		'fk_user' => array('type' => 'integer', 'label' => 'Enseignant', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 50),
		'fk_salle' => array('type' => 'integer', 'label' => 'Salle', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 60),
	);

	/**
	 * Cette table n'a pas de statut.
	 *
	 * @return array
	 */
	protected static function commonFields()
	{
		$f = parent::commonFields();
		unset($f['status']);
		return $f;
	}

	/**
	 * Validation : matière liée à la classe, case libre, enseignant et salle non occupés au même moment.
	 *
	 * @return int
	 */
	public function validate()
	{
		global $langs;
		parent::validate();

		$p = $this->db->prefix();
		$id = (int) $this->id;
		$classe = (int) $this->fk_classe;
		$jour = (int) $this->jour;
		$creneau = (int) $this->fk_creneau;

		if ($jour < 1 || $jour > 7) {
			$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('Jour'));
		}
		if ($classe > 0 && $this->fk_matiere > 0) {
			$r = $this->db->query("SELECT rowid FROM ".$p."ecole_classe_matiere WHERE fk_classe = ".$classe." AND fk_matiere = ".((int) $this->fk_matiere));
			if (!$r || $this->db->num_rows($r) == 0) {
				$this->errors[] = $langs->trans('ErrorEcoleMatiereNonLiee');
			}
		}
		if ($creneau > 0) {
			$r = $this->db->query("SELECT rowid FROM ".$p."ecole_creneau WHERE rowid = ".$creneau." AND status = 1");
			if (!$r || $this->db->num_rows($r) == 0) {
				$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('Creneau'));
			}
		}

		ecole_check_salle_active($this->db, $this->fk_salle, $this->errors);
		if ($this->fk_user > 0 && (int) $this->fk_user !== (int) $this->getStoredValue('fk_user')) {
			ecole_check_user_categorie($this->db, $this->fk_user, array('enseignant'), $this->errors);
		}
		// Module Personnel : l'enseignant ne reçoit que les matières inscrites sur sa fiche employé
		if ($this->fk_user > 0 && isModEnabled('personnel')
			&& ((int) $this->fk_user !== (int) $this->getStoredValue('fk_user') || (int) $this->fk_matiere !== (int) $this->getStoredValue('fk_matiere'))) {
			dol_include_once('/personnel/core/lib/personnel.lib.php');
			if (function_exists('personnel_controle_matiere_edt')) {
				$err = personnel_controle_matiere_edt($this->db, (int) $this->fk_user, (int) $this->fk_matiere);
				if ($err !== '') {
					$this->errors[] = $err;
				}
			}
		}

		if ($classe > 0 && $jour > 0 && $creneau > 0) {
			// Case déjà occupée pour cette classe
			$sql = "SELECT rowid FROM ".$p."ecole_edt_cours WHERE fk_classe = ".$classe." AND jour = ".$jour." AND fk_creneau = ".$creneau;
			if ($id > 0) {
				$sql .= " AND rowid <> ".$id;
			}
			$r = $this->db->query($sql);
			if ($r && $this->db->num_rows($r) > 0) {
				$this->errors[] = $langs->trans('ErrorEcoleCaseOccupee');
			}

			// Enseignant déjà pris dans une autre classe au même moment
			if ($this->fk_user > 0) {
				$sql = "SELECT c.ref FROM ".$p."ecole_edt_cours e INNER JOIN ".$p."ecole_classe c ON c.rowid = e.fk_classe";
				$sql .= " WHERE e.fk_user = ".((int) $this->fk_user)." AND e.jour = ".$jour." AND e.fk_creneau = ".$creneau." AND e.fk_classe <> ".$classe;
				if ($id > 0) {
					$sql .= " AND e.rowid <> ".$id;
				}
				$r = $this->db->query($sql);
				if ($r && ($o = $this->db->fetch_object($r))) {
					$this->errors[] = $langs->trans('ErrorEcoleEnseignantOccupe', ecole_user_label($this->db, $this->fk_user), $o->ref);
				}
			}

			// Salle déjà prise par une autre classe au même moment
			if ($this->fk_salle > 0) {
				$sql = "SELECT c.ref FROM ".$p."ecole_edt_cours e INNER JOIN ".$p."ecole_classe c ON c.rowid = e.fk_classe";
				$sql .= " WHERE e.fk_salle = ".((int) $this->fk_salle)." AND e.jour = ".$jour." AND e.fk_creneau = ".$creneau." AND e.fk_classe <> ".$classe;
				if ($id > 0) {
					$sql .= " AND e.rowid <> ".$id;
				}
				$r = $this->db->query($sql);
				if ($r && ($o = $this->db->fetch_object($r))) {
					$this->errors[] = $langs->trans('ErrorEcoleSalleOccupee', $o->ref);
				}
			}
		}

		return $this->finishValidation();
	}

	/**
	 * Version lisible de la ligne (identifiants + libellés) pour l'historique.
	 *
	 * @return array
	 */
	public function archiveData()
	{
		global $langs;
		$jours = ecole_jours();
		$salles = ecole_salles($this->db);
		return array(
			'id' => (int) $this->id,
			'jour' => (int) $this->jour,
			'jour_label' => isset($jours[(int) $this->jour]) ? $langs->transnoentitiesnoconv($jours[(int) $this->jour]) : '',
			'fk_creneau' => (int) $this->fk_creneau,
			'creneau' => ecole_row_label($this->db, 'ecole_creneau', $this->fk_creneau),
			'fk_matiere' => (int) $this->fk_matiere,
			'matiere' => ecole_row_label($this->db, 'ecole_matiere', $this->fk_matiere),
			'fk_user' => (int) $this->fk_user,
			'enseignant' => ecole_user_label($this->db, $this->fk_user),
			'fk_salle' => (int) $this->fk_salle,
			'salle' => isset($salles[(int) $this->fk_salle]) ? $salles[(int) $this->fk_salle] : '',
		);
	}

	/**
	 * Modification : l'ancienne version est archivée.
	 *
	 * @param User $user      Utilisateur
	 * @param int  $notrigger 1 = sans trigger
	 * @return int
	 */
	public function update(User $user, $notrigger = 0)
	{
		$old = new EcoleEdtCours($this->db);
		$snapshot = ($this->id > 0 && $old->fetch($this->id) > 0) ? $old->archiveData() : null;

		$res = parent::update($user, $notrigger);
		if ($res > 0 && $snapshot) {
			ecole_archive_edt($this->db, $user, 'cours', 'update', $old->fk_classe, $snapshot);
		}
		return $res;
	}

	/**
	 * Suppression : la version supprimée est archivée.
	 *
	 * @param User $user      Utilisateur
	 * @param int  $notrigger 1 = sans trigger
	 * @return int
	 */
	public function delete(User $user, $notrigger = 0)
	{
		$snapshot = $this->archiveData();
		$classe = $this->fk_classe;

		$res = $this->deleteCommon($user, $notrigger);
		if ($res > 0) {
			ecole_archive_edt($this->db, $user, 'cours', 'delete', $classe, $snapshot);
		}
		return $res;
	}
}
