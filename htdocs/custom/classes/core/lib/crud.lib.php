<?php
/**
 * Pages génériques « liste » et « fiche » du module Classes.
 *
 * Chaque objet (niveau, section, créneau, session, matière, classe) décrit ses champs
 * dans sa classe ; ces deux fonctions affichent la liste et la fiche à partir de cette
 * description. Une modification d'apparence se fait donc ici, en un seul endroit.
 *
 * Fichier : custom/classes/core/lib/crud.lib.php
 *
 * Paramètres $cfg :
 *   dir        sous-dossier des pages (ex. 'niveau')
 *   title      clé de traduction du titre de la liste
 *   ficheTitle clé de traduction du titre de la fiche
 *   newLabel   clé de traduction du bouton « Nouveau »
 *   module     (facultatif) module propriétaire des pages et des droits (défaut « classes »)
 *   perm_read / perm_write / perm_delete  droits : « action » ou « rubrique.action » (sous $user->rights->module)
 *   perm_create (facultatif) droit de création s'il diffère de perm_write
 *   perm_export (facultatif) droit supplémentaire pour les boutons d'export PDF / Excel
 *   newurl     (facultatif) adresse du bouton « Nouveau » (relative au dossier custom, ex. '/eleves/caisse.php')
 *   noedit     (facultatif) 1 = pas de lien de modification sur chaque ligne de la liste
 *   head       (facultatif) fonction qui renvoie les onglets de la fiche
 *   extra_view (facultatif) fonction appelée sous la fiche pour ajouter des informations
 *   state_hook (facultatif) fonction ($object, &$conds, &$param) qui ajoute des filtres propres à la liste
 */

/**
 * Module propriétaire d'une configuration de pages.
 *
 * @param  array  $cfg Paramètres
 * @return string
 */
function ecole_crud_module($cfg)
{
	return !empty($cfg['module']) ? $cfg['module'] : 'classes';
}

/**
 * L'utilisateur a-t-il le droit donné par la configuration ($cfg['perm_read'], 'perm_write'...) ?
 *
 * @param  array  $cfg Paramètres
 * @param  string $key perm_read | perm_write | perm_create | perm_delete
 * @return bool
 */
function ecole_crud_can($cfg, $key)
{
	global $user;
	if ($key === 'perm_create' && empty($cfg['perm_create'])) {
		$key = 'perm_write';
	}
	$parts = explode('.', (string) $cfg[$key]);
	return count($parts) > 1 ? (bool) $user->hasRight(ecole_crud_module($cfg), $parts[0], $parts[1]) : (bool) $user->hasRight(ecole_crud_module($cfg), $parts[0]);
}

/**
 * Choix possibles du filtre d'une colonne en liste déroulante : liste de valeurs du champ,
 * ou objets liés d'un champ « integer:ClasseEcole:chemin ». Null si la colonne se filtre en texte.
 *
 * @param  EcoleObject $object Objet
 * @param  string      $k      Colonne
 * @return array<int|string,string>|null
 */
function ecole_crud_filter_options($object, $k)
{
	global $langs;
	static $cache = array();
	$f = isset($object->fields[$k]) ? $object->fields[$k] : null;
	if (!$f) {
		return null;
	}
	if (!empty($f['searchtext'])) {
		return null; // recherche en texte dans l'objet lié
	}
	if (!empty($f['searchoptions']) && is_callable($f['searchoptions'])) {
		return call_user_func($f['searchoptions'], $object->db); // fonction qui renvoie id => libellé
	}
	if ($f['type'] === 'boolean') {
		return array(1 => $langs->trans('Yes'), 0 => $langs->trans('No'));
	}
	if (!empty($f['arrayofkeyval'])) {
		$out = array();
		foreach ($f['arrayofkeyval'] as $v => $l) {
			$out[$v] = $langs->trans($l);
		}
		return $out;
	}
	if (preg_match('/^integer:(Ecole\w+):([^:]+)/', $f['type'], $reg)) {
		if (!isset($cache[$reg[1]])) {
			dol_include_once('/'.$reg[2]);
			$cache[$reg[1]] = array();
			if (class_exists($reg[1])) {
				$o = new $reg[1]($object->db);
				$list = $o->fetchAllObjects('t.'.str_replace(',', ',t.', $o->sortdefault), 'ASC');
				foreach (is_array($list) ? $list : array() as $id => $rec) {
					$cache[$reg[1]][$id] = $rec->ref;
				}
			}
		}
		return $cache[$reg[1]];
	}
	return null;
}

/**
 * Nom du champ de filtre d'une colonne, sans « : » (les colonnes calculées « extra:xxx » donnent search_extra_xxx) :
 * un « : » dans un nom de champ casse les listes déroulantes de Dolibarr (sélecteur jQuery invalide).
 *
 * @param  string $k Colonne
 * @return string
 */
function ecole_crud_nom($k)
{
	return str_replace(':', '_', (string) $k);
}

/**
 * Filtre de recherche d'une colonne de la liste. Chaque colonne a le sien :
 *  - text   : recherche d'un morceau de texte ;
 *  - select : choix dans une liste (valeurs du champ, objet lié, oui / non) ;
 *  - range  : minimum et maximum (nombres, montants, dates) ;
 *  - status : statut de l'objet.
 * Les colonnes calculées (« extra:nom ») déclarent leur filtre dans la configuration de la page :
 * 'search' => array('type' => 'text', 'sql' => fonction($object, $valeur) qui renvoie une condition SQL)
 *           | array('type' => 'select', 'options' => tableau ou fonction, 'sql' => fonction($object, $valeur))
 *           | array('type' => 'multiselect', 'options' => ..., 'sql' => fonction($object, $valeurs)) : plusieurs choix
 *             (dans un lien : valeurs séparées par des virgules, ex. search_extra_categories=enseignant,surveillant)
 *           | array('type' => 'range', 'kind' => 'number'|'date', 'expr' => expression SQL, ex. 't.effectif_max').
 *
 * @param  EcoleObject $object Objet
 * @param  array       $cfg    Configuration de la page
 * @param  string      $k      Colonne de la liste
 * @return array|null          null = pas de filtre pour cette colonne
 */
function ecole_crud_search_spec($object, $cfg, $k)
{
	if ($k === 'status') {
		return isset($object->fields['status']) ? array('type' => 'status') : null;
	}
	if ($k === 'label') {
		return array('type' => 'text');
	}
	if (strpos($k, 'extra:') === 0) {
		$extras = !empty($cfg['extra_columns']) ? $cfg['extra_columns'] : array();
		$e = isset($extras[substr($k, 6)]) ? $extras[substr($k, 6)] : array();
		return !empty($e['search']) ? $e['search'] : null;
	}
	if (!isset($object->fields[$k])) {
		return null;
	}
	$f = $object->fields[$k];
	if (!empty($f['searchtext']) || ecole_crud_filter_options($object, $k) !== null) {
		return array('type' => !empty($f['searchtext']) ? 'text' : 'select');
	}
	if (preg_match('/^date/', $f['type'])) {
		return array('type' => 'range', 'kind' => 'date', 'expr' => 't.'.$k);
	}
	if (preg_match('/^(integer|price|double|smallint|real)/', $f['type'])) {
		return array('type' => 'range', 'kind' => 'number', 'expr' => 't.'.$k);
	}
	return array('type' => 'text');
}

/**
 * Conditions SQL d'un filtre « minimum / maximum ».
 *
 * @param  string $expr Expression SQL de la colonne
 * @param  string $kind 'number' ou 'date'
 * @param  string $min  Minimum saisi
 * @param  string $max  Maximum saisi
 * @return string[]
 */
function ecole_crud_range_conds($expr, $kind, $min, $max)
{
	$conds = array();
	foreach (array('>=' => $min, '<=' => $max) as $op => $v) {
		$v = trim((string) $v);
		if ($v === '') {
			continue;
		}
		if ($kind === 'date') {
			if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
				$conds[] = $expr.' '.$op." '".$v.($op === '>=' ? ' 00:00:00' : ' 23:59:59')."'";
			}
		} elseif (is_numeric(price2num($v))) {
			$conds[] = $expr.' '.$op.' '.((float) price2num($v));
		}
	}
	return $conds;
}

/**
 * Paramètres des pages de chaque objet (titres, droits, onglets).
 *
 * @param  string $name niveau | section | creneau | session | matiere | classe
 * @return array
 */
function ecole_crud_config($name)
{
	$cfgs = array(
		'niveau' => array('dir' => 'niveau', 'class' => 'EcoleNiveau', 'title' => 'Niveaux', 'ficheTitle' => 'Niveau', 'newLabel' => 'NouveauNiveau',
			'perm_read' => 'lire', 'perm_write' => 'config', 'perm_delete' => 'config'),
		'section' => array('dir' => 'section', 'class' => 'EcoleSection', 'title' => 'Sections', 'ficheTitle' => 'Section', 'newLabel' => 'NouvelleSection',
			'perm_read' => 'lire', 'perm_write' => 'config', 'perm_delete' => 'config'),
		'creneau' => array('dir' => 'creneau', 'class' => 'EcoleCreneau', 'title' => 'Creneaux', 'ficheTitle' => 'Creneau', 'newLabel' => 'NouveauCreneau',
			'perm_read' => 'lire', 'perm_write' => 'config', 'perm_delete' => 'config'),
		'session' => array('dir' => 'session', 'class' => 'EcoleSession', 'title' => 'SessionsExamen', 'ficheTitle' => 'SessionExamen', 'newLabel' => 'NouvelleSession',
			'perm_read' => 'lire', 'perm_write' => 'config', 'perm_delete' => 'config'),
		'matiere' => array('dir' => 'matiere', 'class' => 'EcoleMatiere', 'title' => 'Matieres', 'ficheTitle' => 'Matiere', 'newLabel' => 'NouvelleMatiere',
			'perm_read' => 'lire', 'perm_write' => 'ecrire', 'perm_delete' => 'supprimer'),
		'classe' => array('dir' => 'classe', 'class' => 'EcoleClasse', 'title' => 'Classes', 'ficheTitle' => 'Classe', 'newLabel' => 'NouvelleClasse',
			'perm_read' => 'lire', 'perm_write' => 'ecrire', 'perm_delete' => 'supprimer',
			'head' => 'classe_prepare_head', 'extra_view' => 'classe_extra_view',
			'extra_columns' => array(
				// « inscrits / maximum » avec lien vers les élèves de la classe ; triable et filtrable sur le maximum
				'effectif' => array('label' => 'NombreMax', 'render' => 'classe_col_effectif', 'sort' => 'effectif_max',
					'search' => array('type' => 'range', 'kind' => 'number', 'expr' => 't.effectif_max')),
				'edt' => array('label' => 'EcoleEmploiDuTemps', 'render' => 'classe_col_edt'),
			)),
		'salle' => array('dir' => 'salle', 'class' => 'EcoleSalle', 'title' => 'Salles', 'ficheTitle' => 'Salle', 'newLabel' => 'NouvelleSalle',
			'perm_read' => 'lire', 'perm_write' => 'ecrire', 'perm_delete' => 'supprimer',
			'head' => 'salle_prepare_head', 'extra_view' => 'salle_extra_view',
			'extra_columns' => array(
				'attitree' => array('label' => 'ClasseAttitree', 'render' => 'salle_col_attitree'),
				'heures' => array('label' => 'OccupationSalle', 'render' => 'salle_col_heures'),
			)),
	);
	return isset($cfgs[$name]) ? $cfgs[$name] : null;
}

/**
 * Tri et filtres d'une liste, lus dans la requête (utilisés par la liste et par ses exports).
 *
 * @param  EcoleObject $object Objet
 * @return array{sortfield:string,sortorder:string,labelcol:string,search:array,search_status:string,customsql:string,param:string}
 */
function ecole_crud_list_state($object, $cfg = array())
{
	global $langs;

	$sortfield = GETPOST('sortfield', 'aZ09comma');
	$sortorder = GETPOST('sortorder', 'aZ09');
	if (!preg_match('/^t\.([a-z_]+)$/', (string) $sortfield, $m) || !isset($object->fields[$m[1]])) {
		$sortfield = 't.'.str_replace(',', ',t.', $object->sortdefault); // tri par défaut, éventuellement sur plusieurs colonnes
	}
	if (!in_array(strtoupper((string) $sortorder), array('ASC', 'DESC'), true)) {
		$sortorder = 'ASC';
	}

	$search = array();
	$conds = array();
	$param = '';
	$remove = GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha');
	foreach ($object->listcolumns as $k) {
		$spec = ecole_crud_search_spec($object, $cfg, $k);
		if ($spec === null || $spec['type'] === 'status') {
			continue;
		}
		if ($spec['type'] === 'range') {
			$vals = array();
			foreach (array('min', 'max') as $b) {
				$vals[$b] = $remove ? '' : trim(GETPOST('search_'.ecole_crud_nom($k).'_'.$b, 'alphanohtml'));
				$search[$k.'_'.$b] = $vals[$b];
				if ($vals[$b] !== '') {
					$param .= '&search_'.ecole_crud_nom($k).'_'.$b.'='.urlencode($vals[$b]);
				}
			}
			$conds = array_merge($conds, ecole_crud_range_conds($spec['expr'], $spec['kind'], $vals['min'], $vals['max']));
			continue;
		}
		if ($spec['type'] === 'multiselect') {
			// plusieurs choix : tableau (formulaire) ou valeurs séparées par des virgules (lien de menu)
			$vals = array();
			foreach (($remove ? array() : (array) GETPOST('search_'.ecole_crud_nom($k), 'array')) as $v) {
				$v = trim((string) $v);
				if ($v !== '' && $v !== '-1' && !in_array($v, $vals, true)) {
					$vals[] = $v;
					$param .= '&search_'.ecole_crud_nom($k).'[]='.urlencode($v);
				}
			}
			$search[$k] = $vals;
			if ($vals && !empty($spec['sql'])) {
				$c = call_user_func($spec['sql'], $object, $vals);
				if ($c !== '') {
					$conds[] = $c;
				}
			}
			continue;
		}
		$search[$k] = $remove ? '' : GETPOST('search_'.ecole_crud_nom($k), 'alphanohtml');
		if ($search[$k] === '' || $search[$k] === '-1') {
			$search[$k] = '';
			continue;
		}
		$v = $search[$k];
		$param .= '&search_'.ecole_crud_nom($k).'='.urlencode($v);
		$field = isset($object->fields[$k]) ? $object->fields[$k] : array();
		if (!empty($spec['sql'])) {
			$c = call_user_func($spec['sql'], $object, $v); // colonne calculée
			if ($c !== '') {
				$conds[] = $c;
			}
		} elseif ($k === 'label') {
			$conds[] = natural_search(array('t.'.$object->labelfields[0], 't.'.$object->labelfields[1]), $v, 0, 1);
		} elseif (!empty($field['searchtext'])) {
			// recherche dans l'objet lié (ex. nom, téléphone ou référence du responsable)
			$cols = array();
			foreach ($field['searchtext']['cols'] as $c) {
				$cols[] = 'r.'.$c;
			}
			$conds[] = 't.'.$k.' IN (SELECT r.rowid FROM '.$object->db->prefix().$field['searchtext']['table'].' as r WHERE '.natural_search($cols, $v, 0, 1).')';
		} elseif ($spec['type'] === 'select') {
			$conds[] = 't.'.$k." = '".$object->db->escape($v)."'"; // choix dans une liste déroulante
		} else {
			$conds[] = natural_search('t.'.$k, $v, 0, 1);
		}
	}
	// Statut : une valeur, ou plusieurs séparées par des virgules (liens de menu)
	$search_status = $remove ? '' : GETPOST('search_status', 'alpha');
	if ($search_status !== '' && $search_status !== '-1' && isset($object->fields['status'])) {
		$conds[] = 't.status IN ('.implode(',', array_map('intval', explode(',', $search_status))).')';
		$param .= '&search_status='.urlencode($search_status);
	} else {
		$search_status = '';
	}
	if (!empty($cfg['state_hook'])) {
		call_user_func_array($cfg['state_hook'], array($object, &$conds, &$param));
	}
	$labelcol = (strpos((string) $langs->defaultlang, 'ar') === 0) ? $object->labelfields[1] : $object->labelfields[0];

	return array(
		'sortfield' => $sortfield,
		'sortorder' => $sortorder,
		'labelcol' => $labelcol, // colonne « Libellé »
		'search' => $search,
		'search_status' => $search_status,
		'customsql' => implode(' AND ', $conds),
		'param' => $param,
	);
}

/**
 * Boutons « PDF » et « Excel » d'une liste (ouvrent l'export dans un nouvel onglet).
 *
 * @param  string $obj    Clé de l'objet ou du jeu de données exporté (ex. 'classe')
 * @param  string $param  Paramètres de filtre/tri de la liste (commençant par &)
 * @param  string $module Module qui porte la page export.php
 * @return string         HTML
 */
function ecole_export_buttons($obj, $param, $module = 'classes')
{
	global $langs;
	$base = dol_buildpath('/'.$module.'/export.php', 1).'?obj='.urlencode($obj).$param;
	$out = dolGetButtonTitle($langs->trans('ExportPdf'), '', 'fa fa-file-pdf', $base.'&format=pdf', '', 1, array('attr' => array('target' => '_blank')));
	$out .= dolGetButtonTitle($langs->trans('ExportExcel'), '', 'fa fa-file-excel', $base.'&format=excel', '', 1, array('attr' => array('target' => '_blank')));
	return $out;
}

/**
 * Affiche la liste d'un objet.
 *
 * @param  EcoleObject $object Objet
 * @param  array       $cfg    Paramètres (voir en-tête)
 * @return void
 */
function ecole_crud_list($object, $cfg)
{
	global $db, $user, $langs, $conf;

	require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
	$mod = ecole_crud_module($cfg);
	$langs->loadLangs(array('classes@classes', $mod.'@'.$mod, 'other'));
	$form = new Form($db);

	if (!ecole_crud_can($cfg, 'perm_read')) {
		accessforbidden();
	}
	$canwrite = ecole_crud_can($cfg, 'perm_write');

	// Tri, pagination et filtres (mêmes règles que l'export PDF / Excel)
	$limit = GETPOSTINT('limit') > 0 ? GETPOSTINT('limit') : $conf->liste_limit;
	$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
	if (empty($page) || $page < 0) {
		$page = 0;
	}
	$offset = $limit * $page;
	$extras = !empty($cfg['extra_columns']) ? $cfg['extra_columns'] : array();
	$state = ecole_crud_list_state($object, $cfg);
	$sortfield = $state['sortfield'];
	$sortorder = $state['sortorder'];
	$labelcol = $state['labelcol'];
	$search = $state['search'];
	$search_status = $state['search_status'];
	$customsql = $state['customsql'];
	$param = '&limit='.((int) $limit).$state['param'];

	$nbtotal = $object->countAll($customsql);
	$records = $object->fetchAllObjects($sortfield, $sortorder, $limit, $offset, array(), $customsql);
	if (!is_array($records)) {
		dol_print_error($db, $object->error);
		return;
	}

	/*
	 * Affichage
	 */
	llxHeader('', $langs->trans($cfg['title']), '', '', 0, 0, '', '', '', 'mod-'.$mod.' page-list');

	$urlnew = !empty($cfg['newurl']) ? dol_buildpath($cfg['newurl'], 1) : dol_buildpath('/'.$mod.'/'.$cfg['dir'].'/card.php', 1).'?action=create';
	$newbutton = '';
	if (empty($cfg['perm_export']) || ecole_crud_can($cfg, 'perm_export')) {
		$newbutton .= ecole_export_buttons($cfg['dir'], $state['param'].'&sortfield='.urlencode($sortfield).'&sortorder='.urlencode($sortorder), $mod);
	}
	$newbutton .= dolGetButtonTitle($langs->trans($cfg['newLabel']), '', 'fa fa-plus-circle', $urlnew, '', ecole_crud_can($cfg, 'perm_create'));

	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" name="formfilter">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
	print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';

	print_barre_liste($langs->trans($cfg['title']), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', count($records), $nbtotal, $object->picto, 0, $newbutton, '', $limit, 0, 0, 1);

	print '<div class="div-table-responsive">';
	print '<table class="tagtable nobottomiftotal liste">'."\n";

	// Ligne de filtres
	print '<tr class="liste_titre_filter">';
	foreach ($object->listcolumns as $k) {
		print '<td class="liste_titre">';
		$spec = ecole_crud_search_spec($object, $cfg, $k);
		if ($spec === null) {
			// colonne sans filtre
		} elseif ($spec['type'] === 'status') {
			$options = array();
			foreach ($object->statusOptions() as $v => $l) {
				$options[$v] = $langs->trans($l);
			}
			if ($search_status !== '' && !isset($options[$search_status])) {
				$options[$search_status] = $langs->trans('EcoleFiltreStatuts'); // plusieurs statuts (lien de menu)
			}
			print $form->selectarray('search_status', $options, $search_status, 1, 0, 0, '', 0, 0, 0, '', 'minwidth75');
		} elseif ($spec['type'] === 'range') {
			// minimum et maximum (nombres, montants, dates)
			$inputtype = ($spec['kind'] === 'date') ? 'date' : 'number';
			foreach (array('min', 'max') as $b) {
				print '<div class="nowraponall"><span class="opacitymedium small">'.$langs->trans($b === 'min' ? 'FiltreMin' : 'FiltreMax').'</span> ';
				print '<input type="'.$inputtype.'"'.($inputtype === 'number' ? ' step="any"' : '').' class="flat '.($inputtype === 'date' ? 'maxwidth125' : 'maxwidth75').'" name="search_'.ecole_crud_nom($k).'_'.$b.'" value="'.dol_escape_htmltag($search[$k.'_'.$b]).'"></div>';
			}
		} elseif ($spec['type'] === 'multiselect') {
			$options = isset($spec['options']) ? (is_callable($spec['options']) ? call_user_func($spec['options'], $db) : $spec['options']) : ecole_crud_filter_options($object, $k);
			print $form->multiselectarray('search_'.ecole_crud_nom($k), (array) $options, (array) $search[$k], 0, 0, 'minwidth150 maxwidth200', 0, 0);
		} elseif ($spec['type'] === 'select') {
			$options = isset($spec['options']) ? (is_callable($spec['options']) ? call_user_func($spec['options'], $db) : $spec['options']) : ecole_crud_filter_options($object, $k);
			print $form->selectarray('search_'.ecole_crud_nom($k), (array) $options, $search[$k], 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150');
		} else {
			print '<input type="text" class="flat maxwidth100" name="search_'.ecole_crud_nom($k).'" value="'.dol_escape_htmltag($search[$k]).'">';
		}
		print '</td>';
	}
	print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons().'</td>';
	print '</tr>'."\n";

	// Titres
	print '<tr class="liste_titre">';
	foreach ($object->listcolumns as $k) {
		if ($k === 'label') {
			print_liste_field_titre($object->labeltitle, $_SERVER['PHP_SELF'], 't.'.$labelcol, '', $param, '', $sortfield, $sortorder);
		} elseif (strpos($k, 'extra:') === 0) {
			$ex = $extras[substr($k, 6)];
			// triable si 'sort' donne la colonne réelle ; titre en entier (« Téléphone du parent » ne doit pas être coupé)
			print_liste_field_titre($ex['label'], $_SERVER['PHP_SELF'], !empty($ex['sort']) ? 't.'.$ex['sort'] : '', '', $param, '', $sortfield, $sortorder, '', '', 1);
		} else {
			print_liste_field_titre($object->fields[$k]['label'], $_SERVER['PHP_SELF'], 't.'.$k, '', $param, '', $sortfield, $sortorder);
		}
	}
	print_liste_field_titre('', $_SERVER['PHP_SELF'], '', '', '', '', '', '', 'center maxwidthsearch ');
	print '</tr>'."\n";

	// Lignes
	foreach ($records as $rec) {
		print '<tr class="oddeven">';
		foreach ($object->listcolumns as $k) {
			print '<td>';
			if ($k === 'ref') {
				print $rec->getNomUrl(1);
			} elseif ($k === 'label') {
				print '<span'.($labelcol === $object->labelfields[1] && !empty($rec->$labelcol) ? ' dir="rtl"' : '').'>'.dol_escape_htmltag(ecole_label($rec)).'</span>';
			} elseif (strpos($k, 'extra:') === 0) {
				print call_user_func($extras[substr($k, 6)]['render'], $rec);
			} elseif ($k === 'status') {
				print $rec->getLibStatut(5);
			} else {
				print $rec->showOutputField($rec->fields[$k], $k, $rec->$k, '');
			}
			print '</td>';
		}
		print '<td class="center nowraponall">';
		if ($canwrite && empty($cfg['noedit'])) {
			print '<a class="editfielda" href="'.dol_buildpath('/'.$mod.'/'.$cfg['dir'].'/card.php', 1).'?id='.((int) $rec->id).'&action=edit&token='.newToken().'">'.img_edit().'</a>';
		}
		print '</td>';
		print '</tr>'."\n";
	}
	if (empty($records)) {
		print '<tr class="oddeven"><td colspan="'.(count($object->listcolumns) + 1).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}

	print '</table>';
	print '</div>';
	print '</form>';

	llxFooter();
	$db->close();
}

/**
 * Affiche et traite la fiche d'un objet (création, consultation, modification, suppression).
 *
 * @param  EcoleObject $object Objet
 * @param  array       $cfg    Paramètres (voir en-tête)
 * @return void
 */
function ecole_crud_card($object, $cfg)
{
	global $db, $user, $langs, $conf, $hookmanager;

	require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
	$mod = ecole_crud_module($cfg);
	$langs->loadLangs(array('classes@classes', $mod.'@'.$mod, 'other'));
	$form = new Form($db);
	$extrafields = new ExtraFields($db);

	$id = GETPOSTINT('id');
	$ref = GETPOST('ref', 'alpha');
	$action = GETPOST('action', 'aZ09');
	$confirm = GETPOST('confirm', 'alpha');
	$cancel = GETPOST('cancel', 'alpha');
	$backtopage = GETPOST('backtopage', 'alpha');
	$backtopageforcancel = '';
	$noback = '';

	$permissiontoread = ecole_crud_can($cfg, 'perm_read');
	$permissiontoadd = ecole_crud_can($cfg, in_array($action, array('create', 'add')) ? 'perm_create' : 'perm_write');
	$permissiontodelete = ecole_crud_can($cfg, 'perm_delete');
	if (!$permissiontoread) {
		accessforbidden();
	}

	$hookmanager->initHooks(array($object->element.'card', 'globalcard'));

	$backurlforlist = dol_buildpath('/'.$mod.'/'.$cfg['dir'].'/list.php', 1);
	$urlcard = dol_buildpath('/'.$mod.'/'.$cfg['dir'].'/card.php', 1);

	// À la création, « ref » vient du formulaire : ce n'est pas une demande de lecture d'une fiche existante
	if ($id > 0 || ($ref && !in_array($action, array('create', 'add')))) {
		if ($object->fetch($id, $ref ? $ref : null) <= 0) {
			accessforbidden($langs->trans('ErrorRecordNotFound'));
		}
	} else {
		// Création : on retourne à la fiche créée ; l'annulation retourne à la liste
		$backtopage = $urlcard.'?id=__ID__';
		$backtopageforcancel = $backurlforlist;
	}

	/*
	 * Actions
	 */
	include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';

	/*
	 * Affichage
	 */
	$title = $langs->trans($cfg['ficheTitle']);
	llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-'.$mod.' page-card');
	print '<style>input[name$="_ar"],textarea[name$="_ar"]{direction:rtl;text-align:right}</style>';

	if ($action == 'create') {
		// Valeur proposée à la création (clé 'createdefault' du champ) : le formulaire Dolibarr ne pré-remplit pas les nombres
		foreach ($object->fields as $k => $f) {
			if (isset($f['createdefault']) && !GETPOSTISSET($k)) {
				$_GET[$k] = is_callable($f['createdefault']) ? call_user_func($f['createdefault']) : $f['createdefault'];
			}
		}
		print load_fiche_titre($langs->trans($cfg['newLabel']), '', 'object_'.$object->picto);

		print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="add">';

		print dol_get_fiche_head(array(), '');
		print '<table class="border centpercent tableforfield">'."\n";
		include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_add.tpl.php';
		print '</table>'."\n";
		print dol_get_fiche_end();

		print $form->buttonsSaveCancel('Create');
		print '</form>';
	} elseif ($object->id > 0 && $action == 'edit') {
		print load_fiche_titre($title, '', 'object_'.$object->picto);

		print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update">';
		print '<input type="hidden" name="id" value="'.((int) $object->id).'">';

		print dol_get_fiche_head(array(), '');
		print '<table class="border centpercent tableforfield">'."\n";
		include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_edit.tpl.php';
		print '</table>'."\n";
		print dol_get_fiche_end();

		print $form->buttonsSaveCancel();
		print '</form>';
	} elseif ($object->id > 0) {
		$head = !empty($cfg['head']) ? call_user_func($cfg['head'], $object) : array();

		if ($action == 'delete') {
			print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.((int) $object->id), $langs->trans('Delete'), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', '', 0, 1);
		}

		print dol_get_fiche_head($head, 'card', $title, -1, $object->picto);

		$linkback = '<a href="'.$backurlforlist.'">'.$langs->trans('BackToList').'</a>';
		dol_banner_tab($object, 'id', $linkback, 0, 'rowid', 'ref', '<div class="refidno">'.dol_escape_htmltag(ecole_label($object)).'</div>', '', 0, '', ''); // le statut est ajouté par Dolibarr

		print '<div class="fichecenter">';
		print '<div class="underbanner clearboth"></div>';
		print '<table class="border centpercent tableforfield">'."\n";
		$object->fields['ref']['visible'] = 0; // déjà dans la bannière
		$object->fields['status']['visible'] = 0;
		include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_view.tpl.php';
		print '</table>';
		print '</div>';

		print dol_get_fiche_end();

		if (!empty($cfg['extra_view'])) {
			call_user_func($cfg['extra_view'], $object);
		}

		print '<div class="tabsAction">';
		print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER['PHP_SELF'].'?id='.((int) $object->id).'&action=edit&token='.newToken(), '', $permissiontoadd);
		print ecole_bouton_supprimer($langs->trans('Delete'), $_SERVER['PHP_SELF'].'?id='.((int) $object->id).'&action=delete&token='.newToken(), $permissiontodelete, $object->getDeleteBlockers());
		print '</div>';
	}

	llxFooter();
	$db->close();
}
