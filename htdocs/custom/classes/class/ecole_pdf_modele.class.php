<?php
/**
 * Modèle de PDF paramétrable pour les listes, les reçus de paiement et les bulletins de paie :
 * en-tête, couleur, orientation (listes), taille du texte, filigrane (texte ou image, angle, transparence).
 * Un modèle par défaut par type de document ; les autres se choisissent à l'impression.
 *
 * Fichier : custom/classes/class/ecole_pdf_modele.class.php
 */

dol_include_once('/classes/class/ecole_object.class.php');

/**
 * Class EcolePdfModele
 */
class EcolePdfModele extends EcoleObject
{
	/** @var string */
	public $module = 'classes';
	/** @var string */
	public $element = 'ecole_pdf_modele';
	/** @var string */
	public $table_element = 'ecole_pdf_modele';
	/** @var string */
	public $picto = 'fa-file-pdf';
	/** @var string */
	public $dirpage = 'pdf_modele';

	public $required = array('ref', 'label_fr', 'type_doc');
	public $unique = array('ref');
	public $listcolumns = array('ref', 'label', 'type_doc', 'style', 'couleur', 'langue', 'filigrane', 'par_defaut', 'extra:apercu', 'status');
	public $sortdefault = 'type_doc,position,ref';

	public $ref;
	public $label_fr;
	public $label_ar;
	public $type_doc = 'liste';
	public $entete;
	public $couleur = 'bleu';
	public $orientation = 'auto';
	public $taille_police = 8;
	public $filigrane = 'defaut';
	public $fil_texte;
	public $fil_angle = 35;
	public $fil_opacite = 10;
	public $fil_taille = 50;
	public $fil_couleur = 'gris';
	public $style = 'classique';
	public $langue = 'auto';
	public $colonnes_masquees;
	public $opt_numeroter = 0;
	public $opt_total = 1;
	public $opt_date = 1;
	public $opt_signature = 0;
	public $opt_situation = 1;
	public $opt_caissier = 1;
	public $opt_signature_recu = 1;
	public $opt_lettres_recu = 0;
	public $opt_souche = 0;
	public $opt_seances = 1;
	public $opt_presence = 1;
	public $opt_avances = 1;
	public $opt_signatures_paie = 1;
	public $opt_lettres_paie = 0;
	public $par_defaut = 0;
	public $position = 0;
	public $description;

	/** @var array */
	public $fields = array(
		'ref' => array('type' => 'varchar(32)', 'label' => 'Ref', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 10, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'maxwidth150', 'autofocusoncreate' => 1, 'help' => 'RefCourteHelp'),
		'label_fr' => array('type' => 'varchar(128)', 'label' => 'LabelFr', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'position' => 20, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300'),
		'label_ar' => array('type' => 'varchar(128)', 'label' => 'LabelAr', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 30, 'searchall' => 1, 'css' => 'minwidth300'),
		'type_doc' => array('type' => 'varchar(8)', 'label' => 'PdfTypeDoc', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => 'liste', 'position' => 35,
			'arrayofkeyval' => array('liste' => 'PdfTypeListe', 'recu' => 'PdfTypeRecu', 'paie' => 'PdfTypePaie')),
		'entete' => array('type' => 'varchar(32)', 'label' => 'PdfEntete', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 40, 'help' => 'PdfEnteteHelp',
			'arrayofkeyval' => array('bandeau_bleu' => 'PdfHeaderBandeauBleu', 'bandeau_sombre' => 'PdfHeaderBandeauSombre', 'minimal' => 'PdfHeaderMinimal', 'compact' => 'PdfHeaderCompact', 'classique' => 'PdfHeaderClassique', 'image' => 'PdfHeaderImage')),
		'style' => array('type' => 'varchar(16)', 'label' => 'PdfStyle', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => 'classique', 'position' => 42, 'help' => 'PdfStyleHelp',
			'arrayofkeyval' => array('classique' => 'PdfStyleClassique', 'lignes' => 'PdfStyleLignes', 'encadre' => 'PdfStyleEncadre')),
		'langue' => array('type' => 'varchar(4)', 'label' => 'PdfLangue', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => 'auto', 'position' => 44,
			'arrayofkeyval' => array('auto' => 'PdfLangueAuto', 'fr' => 'PdfLangueFr', 'ar' => 'PdfLangueAr')),
		'couleur' => array('type' => 'varchar(16)', 'label' => 'PdfCouleur', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => 'bleu', 'position' => 45,
			'arrayofkeyval' => array('bleu' => 'PdfCouleurBleu', 'vert' => 'PdfCouleurVert', 'bordeaux' => 'PdfCouleurBordeaux', 'violet' => 'PdfCouleurViolet', 'orange' => 'PdfCouleurOrange', 'gris' => 'PdfCouleurGris', 'noir' => 'PdfCouleurNoir', 'aucune' => 'PdfCouleurAucune')),
		'orientation' => array('type' => 'varchar(4)', 'label' => 'PdfOrientation', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => 'auto', 'position' => 50, 'help' => 'PdfOrientationHelp',
			'arrayofkeyval' => array('auto' => 'PdfOrientationAuto', 'P' => 'PdfOrientationPortrait', 'L' => 'PdfOrientationPaysage')),
		'taille_police' => array('type' => 'double(4,1)', 'label' => 'PdfTaillePolice', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '8', 'position' => 55, 'help' => 'PdfTaillePoliceHelp', 'css' => 'maxwidth75'),
		'filigrane' => array('type' => 'varchar(8)', 'label' => 'PdfFiligrane', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => 'defaut', 'position' => 60, 'help' => 'PdfFiligraneHelp',
			'arrayofkeyval' => array('defaut' => 'PdfFiligraneDefaut', 'aucun' => 'PdfFiligraneAucun', 'texte' => 'PdfFiligraneTexte', 'image' => 'PdfFiligraneImage')),
		'fil_texte' => array('type' => 'varchar(64)', 'label' => 'PdfFilTexte', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 61, 'css' => 'minwidth200'),
		'fil_angle' => array('type' => 'integer', 'label' => 'PdfFilAngle', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '35', 'position' => 62, 'css' => 'maxwidth75'),
		'fil_opacite' => array('type' => 'integer', 'label' => 'PdfFilOpacite', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '10', 'position' => 63, 'css' => 'maxwidth75'),
		'fil_taille' => array('type' => 'integer', 'label' => 'PdfFilTaille', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '50', 'position' => 64, 'css' => 'maxwidth75'),
		'fil_couleur' => array('type' => 'varchar(16)', 'label' => 'PdfFilCouleur', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => 'gris', 'position' => 65,
			'arrayofkeyval' => array('gris' => 'PdfCouleurGris', 'bleu' => 'PdfCouleurBleu', 'vert' => 'PdfCouleurVert', 'bordeaux' => 'PdfCouleurBordeaux', 'rouge' => 'PdfCouleurRouge', 'noir' => 'PdfCouleurNoir')),
		'colonnes_masquees' => array('type' => 'varchar(255)', 'label' => 'PdfColonnesMasquees', 'enabled' => '1', 'visible' => 1, 'notnull' => 0, 'position' => 56, 'css' => 'minwidth300', 'help' => 'PdfColonnesMasqueesHelp'),
		'opt_numeroter' => array('type' => 'boolean', 'label' => 'PdfOptNumeroter', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 100, 'help' => 'PdfPourListes'),
		'opt_total' => array('type' => 'boolean', 'label' => 'PdfOptTotal', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '1', 'position' => 101, 'help' => 'PdfPourListes'),
		'opt_date' => array('type' => 'boolean', 'label' => 'PdfOptDate', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '1', 'position' => 102, 'help' => 'PdfPourListes'),
		'opt_signature' => array('type' => 'boolean', 'label' => 'PdfOptSignature', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 103, 'help' => 'PdfPourListes'),
		'opt_situation' => array('type' => 'boolean', 'label' => 'PdfOptSituation', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '1', 'position' => 104, 'help' => 'PdfPourRecus'),
		'opt_caissier' => array('type' => 'boolean', 'label' => 'PdfOptCaissier', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '1', 'position' => 105, 'help' => 'PdfPourRecus'),
		'opt_signature_recu' => array('type' => 'boolean', 'label' => 'PdfOptSignatureRecu', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '1', 'position' => 106, 'help' => 'PdfPourRecus'),
		'opt_lettres_recu' => array('type' => 'boolean', 'label' => 'PdfOptLettres', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 107, 'help' => 'PdfPourRecus'),
		'opt_souche' => array('type' => 'boolean', 'label' => 'PdfOptSouche', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 108, 'help' => 'PdfPourRecus'),
		'opt_seances' => array('type' => 'boolean', 'label' => 'PdfOptSeances', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '1', 'position' => 109, 'help' => 'PdfPourPaie'),
		'opt_presence' => array('type' => 'boolean', 'label' => 'PdfOptPresence', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '1', 'position' => 110, 'help' => 'PdfPourPaie'),
		'opt_avances' => array('type' => 'boolean', 'label' => 'PdfOptAvances', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '1', 'position' => 111, 'help' => 'PdfPourPaie'),
		'opt_signatures_paie' => array('type' => 'boolean', 'label' => 'PdfOptSignaturesPaie', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '1', 'position' => 112, 'help' => 'PdfPourPaie'),
		'opt_lettres_paie' => array('type' => 'boolean', 'label' => 'PdfOptLettres', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 113, 'help' => 'PdfPourPaie'),
		'par_defaut' => array('type' => 'boolean', 'label' => 'PdfParDefaut', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 70, 'help' => 'PdfParDefautHelp'),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => '1', 'visible' => 1, 'notnull' => 1, 'default' => '0', 'position' => 130),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => '1', 'visible' => 3, 'notnull' => 0, 'position' => 140, 'css' => 'minwidth300'),
	);

	/**
	 * Validation : choix parmi les valeurs proposées, bornes des réglages du filigrane et du texte.
	 *
	 * @return int
	 */
	public function validate()
	{
		global $langs;
		if (empty($this->entete) || $this->entete === '-1') {
			$this->entete = null;
		}
		parent::validate();
		foreach (array('type_doc', 'style', 'langue', 'couleur', 'orientation', 'filigrane', 'fil_couleur', 'entete') as $k) {
			if ($this->$k !== null && !isset($this->fields[$k]['arrayofkeyval'][$this->$k])) {
				$this->errors[] = $langs->trans('ErrorEcoleBadValue', $langs->transnoentities($this->fields[$k]['label']));
			}
		}
		$bornes = array('taille_police' => array(6, 12), 'fil_angle' => array(-90, 90), 'fil_opacite' => array(1, 100), 'fil_taille' => array(10, 150));
		foreach ($bornes as $k => $b) {
			if ((float) $this->$k < $b[0] || (float) $this->$k > $b[1]) {
				$this->errors[] = $langs->trans('ErrorEcoleEntre', $langs->transnoentities($this->fields[$k]['label']), $b[0], $b[1]);
			}
		}
		if ($this->filigrane === 'texte' && trim((string) $this->fil_texte) === '') {
			$this->errors[] = $langs->trans('ErrorFieldRequired', $langs->transnoentities('PdfFilTexte'));
		}
		return $this->finishValidation();
	}

	/**
	 * Un seul modèle par défaut par type de document.
	 *
	 * @param  User $user      Utilisateur
	 * @param  int  $notrigger Sans déclencheurs
	 * @return int
	 */
	public function create(User $user, $notrigger = 0)
	{
		$res = parent::create($user, $notrigger);
		if ($res > 0) {
			$this->uniqueDefaut();
		}
		return $res;
	}

	/**
	 * @param  User $user      Utilisateur
	 * @param  int  $notrigger Sans déclencheurs
	 * @return int
	 */
	public function update(User $user, $notrigger = 0)
	{
		$res = parent::update($user, $notrigger);
		if ($res > 0) {
			$this->uniqueDefaut();
		}
		return $res;
	}

	/**
	 * Retire « par défaut » aux autres modèles du même type quand celui-ci l'est.
	 *
	 * @return void
	 */
	protected function uniqueDefaut()
	{
		if ((int) $this->par_defaut === 1) {
			$this->db->query("UPDATE ".$this->db->prefix()."ecole_pdf_modele SET par_defaut = 0 WHERE entity IN (".getEntity('ecole_pdf_modele').") AND type_doc = '".$this->db->escape($this->type_doc)."' AND rowid <> ".((int) $this->id));
		}
	}

	/**
	 * Modèles actifs d'un type : id => libellé (le modèle par défaut en premier).
	 *
	 * @param  DoliDB $db   Handler base
	 * @param  string $type liste | recu | paie
	 * @return array<int,string>
	 */
	public static function choix($db, $type)
	{
		$out = array();
		if (!ecole_table_exists($db, 'ecole_pdf_modele')) {
			return $out;
		}
		$sql = "SELECT rowid, ref, label_fr, label_ar FROM ".$db->prefix()."ecole_pdf_modele WHERE entity IN (".getEntity('ecole_pdf_modele').") AND status = 1";
		$sql .= " AND type_doc = '".$db->escape($type)."' ORDER BY par_defaut DESC, position, ref";
		$resql = $db->query($sql);
		while ($resql && ($o = $db->fetch_object($resql))) {
			$out[(int) $o->rowid] = ecole_label($o);
		}
		return $out;
	}

	/**
	 * Modèle à utiliser : celui demandé (actif, du bon type), sinon le modèle par défaut du type, sinon null.
	 *
	 * @param  DoliDB $db   Handler base
	 * @param  string $type liste | recu | paie
	 * @param  int    $id   Modèle demandé (0 = par défaut)
	 * @return EcolePdfModele|null
	 */
	public static function charger($db, $type, $id = 0)
	{
		$choix = self::choix($db, $type);
		if (empty($choix)) {
			return null;
		}
		$id = isset($choix[(int) $id]) ? (int) $id : (int) key($choix);
		$m = new self($db);
		return ($m->fetch($id) > 0) ? $m : null;
	}
}
