<?php
/**
 * Lot de salaires : les bulletins de plusieurs employés préparés ensemble pour la même période, pour les recalculer,
 * valider, payer et imprimer en une fois. Chaque bulletin du lot reste un bulletin ordinaire.
 *
 * Fichier : custom/salaires/class/ecole_salaire_lot.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');
dol_include_once('/salaires/class/ecole_salaire.class.php');

/**
 * Class EcoleSalaireLot
 */
class EcoleSalaireLot extends EcoleObject
{
	/** @var string */
	public $module = 'salaires';
	/** @var string */
	public $element = 'ecole_salaire_lot';
	/** @var string */
	public $table_element = 'ecole_salaire_lot';
	/** @var string */
	public $picto = 'fa-layer-group';
	/** @var string */
	public $dirpage = 'lot';

	public $required = array('ref', 'date_debut', 'date_fin');
	public $unique = array('ref');
	public $listcolumns = array('ref', 'libelle', 'mois', 'date_debut', 'date_fin', 'extra:bulletins', 'extra:net', 'extra:etat');
	public $sortdefault = 'rowid';

	public $ref;
	public $libelle;
	public $date_debut;
	public $date_fin;
	public $mois;
	public $note;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(10)', 'label' => 'RefLot', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1),
		'libelle' => array('type' => 'varchar(255)', 'label' => 'Libelle', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 20, 'searchall' => 1),
		'date_debut' => array('type' => 'date', 'label' => 'PeriodeDu', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 30),
		'date_fin' => array('type' => 'date', 'label' => 'PeriodeAu', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 31),
		'mois' => array('type' => 'varchar(7)', 'label' => 'MoisSalaire', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 25, 'searchoptions' => 'salaires_mois_choix'),
		'note' => array('type' => 'text', 'label' => 'Note', 'enabled' => '1', 'visible' => 0, 'notnull' => 0, 'position' => 40),
	);

	/**
	 * Prochain numéro de lot : LOT + compteur sur 5 chiffres.
	 *
	 * @return string
	 */
	public function getNextRef()
	{
		$sql = "SELECT MAX(CAST(RIGHT(ref, 5) AS UNSIGNED)) as maxi FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE entity IN (".getEntity($this->element).") AND ref REGEXP '[0-9]{5}$'";
		$resql = $this->db->query($sql);
		$o = $resql ? $this->db->fetch_object($resql) : null;
		return 'LOT'.str_pad((string) (($o ? (int) $o->maxi : 0) + 1), 5, '0', STR_PAD_LEFT);
	}

	/**
	 * Lien vers la fiche du lot.
	 *
	 * @param int    $withpicto 1 = avec picto
	 * @param string $option    Non utilisé
	 * @param int    $notooltip Non utilisé
	 * @param string $morecss   CSS
	 * @return string
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '')
	{
		$url = dol_buildpath('/salaires/lot/card.php', 1).'?id='.((int) $this->id);
		$out = '<a href="'.$url.'" class="'.$morecss.'" title="'.dol_escape_htmltag((string) $this->libelle).'">';
		if ($withpicto) {
			$out .= img_picto('', $this->picto, 'class="pictofixedwidth"');
		}
		return $out.dol_escape_htmltag((string) $this->ref).'</a>';
	}

	/**
	 * Affichage : mois du salaire en toutes lettres.
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
		if ($key === 'mois') {
			return (string) $value !== '' ? dol_escape_htmltag(personnel_mois_label((string) $value)) : '';
		}
		return parent::showOutputField($val, $key, $value, $moreparam, $keysuffix, $keyprefix, $morecss);
	}

	/**
	 * Un lot n'a pas de statut propre (l'état est celui de ses bulletins).
	 *
	 * @param int $status Statut
	 * @param int $mode   Mode d'affichage Dolibarr
	 * @return string
	 */
	public function LibStatut($status, $mode = 0)
	{
		return '';
	}

	/**
	 * Bulletins du lot (annulés compris), par nom d'employé.
	 *
	 * @return EcoleSalaire[]
	 */
	public function getBulletins()
	{
		$b = new EcoleSalaire($this->db);
		$sql = "t.fk_lot = ".((int) $this->id);
		$list = $b->fetchAllObjects('t.ref', 'ASC', 0, 0, array(), $sql);
		return is_array($list) ? $list : array();
	}

	/**
	 * Nombre de bulletins par statut et total net (bulletins non annulés).
	 *
	 * @return array{nb:int,net:float,par_statut:array<int,int>}
	 */
	public function resume()
	{
		$out = array('nb' => 0, 'net' => 0.0, 'par_statut' => array());
		$sql = "SELECT status, COUNT(*) as nb, SUM(net) as net FROM ".$this->db->prefix()."ecole_salaire WHERE fk_lot = ".((int) $this->id)." GROUP BY status";
		$resql = $this->db->query($sql);
		while ($resql && ($o = $this->db->fetch_object($resql))) {
			$out['par_statut'][(int) $o->status] = (int) $o->nb;
			if ((int) $o->status !== EcoleSalaire::STATUS_ANNULE) {
				$out['nb'] += (int) $o->nb;
				$out['net'] += (float) $o->net;
			}
		}
		return $out;
	}

	/**
	 * Un lot contenant des bulletins ne se supprime pas (ses bulletins s'annulent un par un).
	 *
	 * @param User $user      Utilisateur
	 * @param int  $notrigger 1 = sans trigger
	 * @return int
	 */
	public function delete(User $user, $notrigger = 0)
	{
		$this->errors = $this->getDeleteBlockers();
		if ($this->errors) {
			$this->error = implode('<br>', $this->errors);
			return -1;
		}
		return $this->deleteCommon($user, $notrigger);
	}

	/**
	 * Causes qui empêchent la suppression : le lot contient des bulletins.
	 *
	 * @return string[]
	 */
	public function getDeleteBlockers()
	{
		global $langs;
		$langs->load('salaires@salaires');
		$r = $this->resume();
		return array_sum($r['par_statut']) > 0 ? array($langs->trans('ErrorLotNonVide')) : array();
	}
}
