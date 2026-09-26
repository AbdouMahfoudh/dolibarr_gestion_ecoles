<?php
/**
 * Épreuve de l'emploi du temps des examens : une classe, une session, une date,
 * des heures, une matière, une salle et un surveillant.
 *
 * Fichier : custom/classes/class/ecole_edt_examen.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');
dol_include_once('/classes/core/lib/classes.lib.php');

/**
 * Class EcoleEdtExamen
 */
class EcoleEdtExamen extends EcoleObject
{
	/** @var string */
	public $element = 'ecole_edt_examen';
	/** @var string */
	public $table_element = 'ecole_edt_examen';
	/** @var string */
	public $picto = 'fa-file-signature';

	public $required = array('fk_classe', 'fk_session', 'date_examen', 'heure_debut', 'heure_fin', 'fk_matiere');

	public $fk_classe;
	public $fk_session;
	public $date_examen;
	public $heure_debut;
	public $heure_fin;
	public $fk_matiere;
	public $fk_salle;
	public $fk_user;

	/** @var array */
	public $fields = array(
		'fk_classe' => array('type' => 'integer', 'label' => 'Classe', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1),
		'fk_session' => array('type' => 'integer', 'label' => 'SessionExamen', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20),
		'date_examen' => array('type' => 'date', 'label' => 'DateExamen', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 30),
		'heure_debut' => array('type' => 'varchar(5)', 'label' => 'HeureDebut', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 40),
		'heure_fin' => array('type' => 'varchar(5)', 'label' => 'HeureFin', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 50),
		'fk_matiere' => array('type' => 'integer', 'label' => 'Matiere', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 60),
		'fk_salle' => array('type' => 'integer', 'label' => 'Salle', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 70),
		'fk_user' => array('type' => 'integer', 'label' => 'Surveillant', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 80),
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
	 * Date de l'épreuve au format SQL (AAAA-MM-JJ), telle qu'enregistrée en base.
	 *
	 * @return string
	 */
	public function getSqlDate()
	{
		if (empty($this->date_examen)) {
			return '';
		}
		if (is_numeric($this->date_examen)) {
			return substr($this->db->idate($this->date_examen), 0, 10);
		}
		return substr((string) $this->date_examen, 0, 10);
	}

	/**
	 * Validation : heures, matière liée à la classe, date dans la session, pas de chevauchement
	 * pour la classe, le surveillant ni la salle.
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
		$date = $this->getSqlDate();

		$okdeb = ecole_hhmm_ok($this->heure_debut);
		$okfin = ecole_hhmm_ok($this->heure_fin);
		if (!empty($this->heure_debut) && !$okdeb) {
			$this->errors[] = $langs->trans('ErrorEcoleBadTime', $langs->transnoentities('HeureDebut'));
		}
		if (!empty($this->heure_fin) && !$okfin) {
			$this->errors[] = $langs->trans('ErrorEcoleBadTime', $langs->transnoentities('HeureFin'));
		}
		if ($okdeb && $okfin && $this->heure_fin <= $this->heure_debut) {
			$this->errors[] = $langs->trans('ErrorEcoleFinAvantDebut');
			$okfin = false;
		}

		ecole_check_salle_active($this->db, $this->fk_salle, $this->errors);
		if ($this->fk_user > 0 && (int) $this->fk_user !== (int) $this->getStoredValue('fk_user')) {
			ecole_check_user_categorie($this->db, $this->fk_user, array('enseignant', 'surveillant'), $this->errors);
		}

		if ($classe > 0 && $this->fk_matiere > 0) {
			$r = $this->db->query("SELECT rowid FROM ".$p."ecole_classe_matiere WHERE fk_classe = ".$classe." AND fk_matiere = ".((int) $this->fk_matiere));
			if (!$r || $this->db->num_rows($r) == 0) {
				$this->errors[] = $langs->trans('ErrorEcoleMatiereNonLiee');
			}
		}

		// Une seule épreuve par matière, pour une classe et une session (trimestre)
		if ($classe > 0 && $this->fk_matiere > 0 && $this->fk_session > 0) {
			$sql = "SELECT e.date_examen, s.label_fr, s.label_ar, m.label_fr as mfr, m.label_ar as mar FROM ".$p."ecole_edt_examen e";
			$sql .= " INNER JOIN ".$p."ecole_session s ON s.rowid = e.fk_session";
			$sql .= " INNER JOIN ".$p."ecole_matiere m ON m.rowid = e.fk_matiere";
			$sql .= " WHERE e.fk_classe = ".$classe." AND e.fk_matiere = ".((int) $this->fk_matiere)." AND e.fk_session = ".((int) $this->fk_session);
			if ($id > 0) {
				$sql .= " AND e.rowid <> ".$id;
			}
			$r = $this->db->query($sql);
			if ($r && ($o = $this->db->fetch_object($r))) {
				$this->errors[] = $langs->trans('ErrorEcoleMatiereDejaExamen',
					ecole_label((object) array('label_fr' => $o->mfr, 'label_ar' => $o->mar)),
					ecole_label($o),
					dol_print_date($this->db->jdate($o->date_examen), 'day'));
			}
		}

		if ($this->fk_session > 0 && $date !== '') {
			$r = $this->db->query("SELECT date_debut, date_fin FROM ".$p."ecole_session WHERE rowid = ".((int) $this->fk_session));
			if ($r && ($o = $this->db->fetch_object($r))) {
				if (($o->date_debut && $date < $o->date_debut) || ($o->date_fin && $date > $o->date_fin)) {
					$this->errors[] = $langs->trans('ErrorEcoleDateHorsSession', $o->date_debut, $o->date_fin);
				}
			} else {
				$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities('SessionExamen'));
			}
		}

		if ($okdeb && $okfin && $date !== '') {
			$over = " AND e.date_examen = '".$this->db->escape($date)."'";
			$over .= " AND e.heure_debut < '".$this->db->escape($this->heure_fin)."' AND e.heure_fin > '".$this->db->escape($this->heure_debut)."'";
			if ($id > 0) {
				$over .= " AND e.rowid <> ".$id;
			}
			$base = "SELECT c.ref FROM ".$p."ecole_edt_examen e INNER JOIN ".$p."ecole_classe c ON c.rowid = e.fk_classe WHERE ";

			if ($classe > 0) {
				$r = $this->db->query($base."e.fk_classe = ".$classe.$over);
				if ($r && $this->db->num_rows($r) > 0) {
					$this->errors[] = $langs->trans('ErrorEcoleClasseExamenChevauche');
				}
			}
			if ($this->fk_user > 0) {
				$r = $this->db->query($base."e.fk_user = ".((int) $this->fk_user)." AND e.fk_classe <> ".$classe.$over);
				if ($r && ($o = $this->db->fetch_object($r))) {
					$this->errors[] = $langs->trans('ErrorEcoleSurveillantOccupe', ecole_user_label($this->db, $this->fk_user), $o->ref);
				}
			}
			if ($this->fk_salle > 0) {
				$r = $this->db->query($base."e.fk_salle = ".((int) $this->fk_salle)." AND e.fk_classe <> ".$classe.$over);
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
		$salles = ecole_salles($this->db);
		return array(
			'id' => (int) $this->id,
			'fk_session' => (int) $this->fk_session,
			'session' => ecole_row_label($this->db, 'ecole_session', $this->fk_session),
			'date_examen' => $this->getSqlDate(),
			'heure_debut' => $this->heure_debut,
			'heure_fin' => $this->heure_fin,
			'fk_matiere' => (int) $this->fk_matiere,
			'matiere' => ecole_row_label($this->db, 'ecole_matiere', $this->fk_matiere),
			'fk_salle' => (int) $this->fk_salle,
			'salle' => isset($salles[(int) $this->fk_salle]) ? $salles[(int) $this->fk_salle] : '',
			'fk_user' => (int) $this->fk_user,
			'surveillant' => ecole_user_label($this->db, $this->fk_user),
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
		$old = new EcoleEdtExamen($this->db);
		$snapshot = ($this->id > 0 && $old->fetch($this->id) > 0) ? $old->archiveData() : null;

		$res = parent::update($user, $notrigger);
		if ($res > 0 && $snapshot) {
			ecole_archive_edt($this->db, $user, 'examen', 'update', $old->fk_classe, $snapshot);
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
			ecole_archive_edt($this->db, $user, 'examen', 'delete', $classe, $snapshot);
		}
		return $res;
	}
}
