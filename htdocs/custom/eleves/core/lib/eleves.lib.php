<?php
/**
 * Fonctions communes du module Élèves.
 *
 * Fichier : custom/eleves/core/lib/eleves.lib.php
 */

/**
 * Paramètres des pages génériques (liste / fiche de crud.lib.php du module Classes) des objets du module.
 *
 * @param  string $name eleve | responsable | recu | frais_type | document_type
 * @return array|null
 */
function eleves_crud_config($name)
{
	$cfgs = array(
		'eleve' => array('module' => 'eleves', 'dir' => 'eleve', 'class' => 'EcoleEleve', 'title' => 'Eleves', 'ficheTitle' => 'Eleve', 'newLabel' => 'NouvelEleve',
			'perm_export' => 'eleve.exporter', 'perm_read' => 'eleve.lire', 'perm_write' => 'eleve.modifier', 'perm_create' => 'eleve.creer', 'perm_delete' => 'eleve.supprimer',
			'state_hook' => 'eleves_list_state_hook',
			'extra_columns' => array(
				'telephone' => array('label' => 'TelephoneResponsable', 'render' => 'eleve_col_telephone',
					'search' => array('type' => 'text', 'sql' => 'eleve_search_telephone')),
				'pieces' => array('label' => 'PiecesDossier', 'render' => 'eleve_col_pieces',
					'search' => array('type' => 'select', 'options' => 'eleve_search_pieces_options', 'sql' => 'eleve_search_pieces')),
			)),
		'responsable' => array('module' => 'eleves', 'dir' => 'responsable', 'class' => 'EcoleResponsable', 'title' => 'Responsables', 'ficheTitle' => 'Responsable', 'newLabel' => 'NouveauResponsable',
			'perm_export' => 'eleve.exporter', 'perm_read' => 'responsable.lire', 'perm_write' => 'responsable.modifier', 'perm_create' => 'responsable.creer', 'perm_delete' => 'responsable.supprimer',
			'extra_view' => 'responsable_extra_view',
			'extra_columns' => array('enfants' => array('label' => 'Enfants', 'render' => 'responsable_col_enfants'))),
		'recu' => array('module' => 'eleves', 'dir' => 'recu', 'class' => 'EcoleRecu', 'title' => 'Recus', 'ficheTitle' => 'Recu', 'newLabel' => 'Encaisser',
			'newurl' => '/eleves/caisse.php', 'noedit' => 1,
			'perm_export' => 'eleve.exporter', 'perm_read' => 'paiement.lire', 'perm_write' => 'paiement.encaisser', 'perm_delete' => 'paiement.annuler',
			'extra_columns' => array(
				'payeur' => array('label' => 'PayePar', 'render' => 'recu_col_payeur'),
				'eleves' => array('label' => 'Eleves', 'render' => 'recu_col_eleves'),
			)),
		'frais_type' => array('module' => 'eleves', 'dir' => 'frais_type', 'class' => 'EcoleFraisType', 'title' => 'AutresFrais', 'ficheTitle' => 'AutreFrais', 'newLabel' => 'NouveauFrais',
			'perm_export' => 'eleve.exporter', 'perm_read' => 'config.gerer', 'perm_write' => 'config.gerer', 'perm_delete' => 'config.gerer'),
		'document_type' => array('module' => 'eleves', 'dir' => 'document_type', 'class' => 'EcoleDocumentType', 'title' => 'PiecesAFournir', 'ficheTitle' => 'PieceAFournir', 'newLabel' => 'NouvellePiece',
			'perm_export' => 'eleve.exporter', 'perm_read' => 'config.gerer', 'perm_write' => 'config.gerer', 'perm_delete' => 'config.gerer'),
		'motif_absence' => array('module' => 'eleves', 'dir' => 'motif_absence', 'class' => 'EcoleMotifAbsence', 'title' => 'MotifsAbsence', 'ficheTitle' => 'MotifAbsence', 'newLabel' => 'NouveauMotif',
			'perm_export' => 'eleve.exporter', 'perm_read' => 'config.gerer', 'perm_write' => 'config.gerer', 'perm_delete' => 'config.gerer'),
		'motif_exoneration' => array('module' => 'eleves', 'dir' => 'motif_exoneration', 'class' => 'EcoleMotifExoneration', 'title' => 'MotifsExoneration', 'ficheTitle' => 'MotifExoneration', 'newLabel' => 'NouveauMotif',
			'perm_export' => 'eleve.exporter', 'perm_read' => 'config.gerer', 'perm_write' => 'config.gerer', 'perm_delete' => 'config.gerer'),
		'engagement' => array('module' => 'eleves', 'dir' => 'engagement', 'class' => 'EcoleEngagement', 'title' => 'Engagements', 'ficheTitle' => 'Engagement', 'newLabel' => 'NouvelEngagement',
			'perm_export' => 'eleve.exporter', 'perm_read' => 'config.gerer', 'perm_write' => 'config.gerer', 'perm_delete' => 'config.gerer'),
		'sanction_type' => array('module' => 'eleves', 'dir' => 'sanction_type', 'class' => 'EcoleSanctionType', 'title' => 'TypesSanction', 'ficheTitle' => 'TypeSanction', 'newLabel' => 'NouveauTypeSanction',
			'perm_export' => 'eleve.exporter', 'perm_read' => 'config.gerer', 'perm_write' => 'config.gerer', 'perm_delete' => 'config.gerer'),
	);
	return isset($cfgs[$name]) ? $cfgs[$name] : null;
}

/**
 * Filtres propres à la liste des élèves : pièces manquantes, enfants d'un responsable.
 *
 * @param  EcoleEleve $object Objet
 * @param  string[]   $conds  Conditions SQL (complétées)
 * @param  string     $param  Paramètres d'URL (complétés)
 * @return void
 */
function eleves_list_state_hook($object, &$conds, &$param)
{
	$remove = GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha');
	// Élèves archivés (anciens non revenus) : cachés, sauf si on filtre sur ce statut
	$ss = $remove ? '' : GETPOST('search_status', 'alpha');
	if ($ss === '' || $ss === '-1') {
		$conds[] = 't.status <> '.EcoleEleve::STATUS_ARCHIVE;
	}
	if (!$remove && GETPOSTINT('manquantes')) {
		$conds[] = EcoleEleve::sqlPiecesManquantes($object->db);
		$param .= '&manquantes=1';
	}
	if (!$remove && GETPOSTINT('fk_responsable') > 0) {
		$conds[] = 't.fk_responsable = '.GETPOSTINT('fk_responsable');
		$param .= '&fk_responsable='.GETPOSTINT('fk_responsable');
	}
}

/**
 * Filtre « Téléphone du parent » de la liste des élèves : cherche dans le téléphone et le WhatsApp
 * du responsable de l'élève, sans tenir compte des espaces, points ou tirets.
 *
 * @param  EcoleEleve $object Objet
 * @param  string     $value  Texte saisi
 * @return string             Condition SQL
 */
function eleve_search_telephone($object, $value)
{
	$digits = preg_replace('/[^0-9]/', '', (string) $value);
	if ($digits === '') {
		return '';
	}
	$clean = function ($col) {
		return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(r.".$col.", ' ', ''), '-', ''), '.', ''), '(', ''), ')', '')";
	};
	return "t.fk_responsable IN (SELECT r.rowid FROM ".$object->db->prefix()."ecole_responsable as r WHERE ".$clean('telephone')." LIKE '%".$digits."%' OR ".$clean('whatsapp')." LIKE '%".$digits."%')";
}

/**
 * Choix du filtre « Pièces » de la liste des élèves.
 *
 * @return array<string,string>
 */
function eleve_search_pieces_options()
{
	global $langs;
	return array('manquantes' => $langs->trans('PiecesManquantes'), 'complet' => $langs->trans('DossierComplet'));
}

/**
 * Filtre « Pièces » de la liste des élèves : dossiers complets ou avec des pièces manquantes.
 *
 * @param  EcoleEleve $object Objet
 * @param  string     $value  manquantes | complet
 * @return string             Condition SQL
 */
function eleve_search_pieces($object, $value)
{
	$sql = EcoleEleve::sqlPiecesManquantes($object->db);
	if ($value === 'manquantes') {
		return $sql;
	}
	return $value === 'complet' ? 'NOT ('.$sql.')' : '';
}

/**
 * Modes de paiement pour le filtre de la liste des reçus.
 *
 * @param  DoliDB $db Handler base
 * @return array<int,string>
 */
function eleves_search_modes($db)
{
	dol_include_once('/eleves/core/lib/paiements.lib.php');
	return eleves_modes_paiement($db);
}

/**
 * Colonne « Téléphone du parent » de la liste des élèves.
 *
 * @param  EcoleEleve $rec Élève
 * @return string
 */
function eleve_col_telephone($rec)
{
	global $db;
	static $cache = array();
	$id = (int) $rec->fk_responsable;
	if (!isset($cache[$id])) {
		$cache[$id] = '';
		$resql = $db->query("SELECT telephone FROM ".$db->prefix()."ecole_responsable WHERE rowid = ".$id);
		if ($resql && ($o = $db->fetch_object($resql))) {
			$cache[$id] = (string) $o->telephone;
		}
	}
	return dol_print_phone($cache[$id], '', 0, 0, 'AC_TEL', '&nbsp;', 'phone');
}

/**
 * Colonne « Pièces » de la liste des élèves : pièces fournies / demandées, en rouge s'il en manque.
 *
 * @param  EcoleEleve $rec Élève
 * @return string
 */
function eleve_col_pieces($rec)
{
	global $langs;
	$pieces = $rec->getPieces();
	$total = 0;
	$fournies = 0;
	foreach ($pieces as $o) {
		if ((int) $o->type_status === 1) {
			$total++;
			$fournies += empty($o->fourni) ? 0 : 1;
		}
	}
	if ($total === 0) {
		return '';
	}
	$url = dol_buildpath('/eleves/eleve/documents.php', 1).'?id='.((int) $rec->id);
	$badge = ($fournies < $total) ? 'badge-danger' : 'badge-status4';
	$title = ($fournies < $total) ? $langs->trans('PiecesManquantesNb', $total - $fournies) : $langs->trans('DossierComplet');
	return '<a href="'.$url.'" title="'.dol_escape_htmltag($title).'"><span class="badge '.$badge.'">'.$fournies.' / '.$total.'</span></a>';
}

/**
 * Colonne « Enfants » de la liste des responsables.
 *
 * @param  EcoleResponsable $rec Responsable
 * @return string
 */
function responsable_col_enfants($rec)
{
	$out = array();
	foreach ($rec->getEnfants() as $e) {
		$out[] = '<a href="'.dol_buildpath('/eleves/eleve/card.php', 1).'?id='.((int) $e->id).'" title="'.dol_escape_htmltag(ecole_label($e)).'">'.dol_escape_htmltag($e->ref).'</a>';
	}
	return implode(', ', $out);
}

/**
 * Sous la fiche d'un responsable : ses enfants (frères et sœurs) et bouton « Ajouter un enfant ».
 *
 * @param  EcoleResponsable $object Responsable
 * @return void
 */
function responsable_extra_view($object)
{
	global $langs, $user;

	$enfants = $object->getEnfants();
	$button = '';
	if ($user->hasRight('eleves', 'eleve', 'creer')) {
		$button = dolGetButtonTitle($langs->trans('AjouterEnfant'), '', 'fa fa-plus-circle', dol_buildpath('/eleves/eleve/card.php', 1).'?action=create&fk_responsable='.((int) $object->id));
	}
	print '<div class="fichecenter"><br>';
	print load_fiche_titre($langs->trans('EnfantsFratrie').' ('.count($enfants).')', $button, 'fa-user-graduate');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Matricule').'</td><td>'.$langs->trans('NomComplet').'</td><td>'.$langs->trans('Classe').'</td><td class="center">'.$langs->trans('StatutEleve').'</td></tr>';
	foreach ($enfants as $e) {
		print '<tr class="oddeven"><td>'.$e->getNomUrl(1).'</td><td>'.dol_escape_htmltag(ecole_label($e)).'</td>';
		print '<td>'.ecole_link_label($object->db, 'EcoleClasse', 'classes/class/ecole_classe.class.php', $e->fk_classe).'</td>';
		print '<td class="center">'.$e->getLibStatut(5).'</td></tr>';
	}
	if (empty($enfants)) {
		print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans('AucunEnfant').'</span></td></tr>';
	}
	print '</table></div><br>';

	// Accès à l'espace parents (module Espace)
	if (isModEnabled('espace') && $user->hasRight('espace', 'acces', 'lire')) {
		dol_include_once('/espace/core/lib/espace.lib.php');
		$langs->load('espace@espace');
		$acces = espace_acces_fetch($object->db, ESPACE_PARENT, (int) $object->id);
		$url = dol_buildpath('/espace/acces.php', 1).'?type=parent&id='.((int) $object->id);
		$button = dolGetButtonTitle($langs->trans($acces ? 'GererAcces' : 'CreerAcces'), '', 'fa fa-house-user', $url);
		print '<div class="fichecenter">';
		print load_fiche_titre($langs->trans('AccesEspaceParents'), $button, 'fa-house-user');
		print '<table class="border centpercent tableforfield">';
		print '<tr><td class="titlefield">'.$langs->trans('EtatAcces').'</td><td>'.espace_etat_badge(espace_etat($object->db, $acces));
		if ($acces && (int) $acces->mdp_provisoire) {
			print ' <span class="badge badge-status1">'.$langs->trans('MotDePasseProvisoire').'</span>';
		}
		print '</td></tr>';
		if ($acces) {
			print '<tr><td>'.$langs->trans('Identifiant').'</td><td><b>'.dol_escape_htmltag($object->ref).'</b></td></tr>';
			print '<tr><td>'.$langs->trans('DerniereConnexion').'</td><td>'.($acces->date_derniere_connexion ? dol_print_date($object->db->jdate($acces->date_derniere_connexion), 'dayhour', 'tzuserrel') : '<span class="opacitymedium">'.$langs->trans('JamaisConnecte').'</span>').'</td></tr>';
		}
		print '</table></div><br>';
	}
}

/**
 * Étiquette (catégorie client) « Élève » posée sur le Tiers de chaque élève. Créée au premier besoin.
 *
 * @param  DoliDB $db   Handler base
 * @param  User   $user Utilisateur
 * @return int          Id de la catégorie, 0 si le module Catégories n'est pas actif, -1 si erreur
 */
function eleves_categorie_tiers($db, $user)
{
	global $conf;
	static $cache = null;
	if ($cache !== null) {
		return $cache;
	}
	if (!isModEnabled('categorie')) {
		return 0;
	}
	require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

	$id = getDolGlobalInt('ELEVES_CATEGORIE_TIERS');
	if ($id > 0) {
		$cat = new Categorie($db);
		if ($cat->fetch($id) > 0) {
			return $cache = $id;
		}
	}
	// Libellé non traduit par Dolibarr : français et arabe
	$label = 'Élève - تلميذ';
	$cat = new Categorie($db);
	if ($cat->fetch(0, $label, Categorie::TYPE_CUSTOMER) > 0) {
		$id = (int) $cat->id;
	} else {
		$cat = new Categorie($db);
		$cat->label = $label;
		$cat->type = Categorie::TYPE_CUSTOMER;
		$cat->description = 'Tiers créés automatiquement par le module Élèves';
		$cat->color = '425CC7';
		$cat->visible = 1;
		$id = (int) $cat->create($user);
		if ($id <= 0) {
			return -1;
		}
	}
	dolibarr_set_const($db, 'ELEVES_CATEGORIE_TIERS', $id, 'chaine', 0, '', $conf->entity);
	return $cache = $id;
}

/**
 * Responsables pour une liste de choix : id => « Nom — téléphone » (actifs + valeur actuelle).
 *
 * @param  DoliDB $db   Handler base
 * @param  int    $keep Id à garder même s'il est inactif
 * @return array<int,string>
 */
function eleves_responsables_choix($db, $keep = 0)
{
	$out = array();
	$sql = "SELECT rowid, ref, nom_fr, nom_ar, telephone FROM ".$db->prefix()."ecole_responsable WHERE entity IN (".getEntity('ecole_responsable').")";
	$sql .= " AND (status = 1".($keep > 0 ? " OR rowid = ".((int) $keep) : "").") ORDER BY nom_fr";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[(int) $o->rowid] = ecole_label($o).($o->telephone ? ' — '.$o->telephone : '');
	}
	return $out;
}

/**
 * Classes pour une liste de choix : id => « REF - Libellé (places) » (actives + valeur actuelle).
 *
 * @param  DoliDB $db   Handler base
 * @param  int    $keep Id à garder même si elle est inactive
 * @return array<int,string>
 */
function eleves_classes_choix($db, $keep = 0)
{
	global $langs;
	dol_include_once('/eleves/class/ecole_eleve.class.php');
	$out = array();
	$sql = "SELECT c.rowid, c.ref, c.label_fr, c.label_ar FROM ".$db->prefix()."ecole_classe c LEFT JOIN ".$db->prefix()."ecole_niveau n ON n.rowid = c.fk_niveau";
	$sql .= " WHERE c.entity IN (".getEntity('ecole_classe').") AND (c.status = 1".($keep > 0 ? " OR c.rowid = ".((int) $keep) : "").")";
	$sql .= " ORDER BY n.position, c.rowid";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$p = EcoleEleve::placesClasse($db, (int) $o->rowid);
		$places = ($p['max'] === null) ? '' : ' ('.$langs->trans('PlacesLibresNb', $p['libres'], $p['max']).')';
		$out[(int) $o->rowid] = $o->ref.' - '.ecole_label($o).$places;
	}
	return $out;
}

/**
 * Lit dans le formulaire la valeur de chaque champ donné (même règles que les pages ModuleBuilder de Dolibarr).
 *
 * @param  CommonObject $object Objet à remplir
 * @param  string[]     $keys   Champs
 * @return void
 */
function eleves_fields_from_post($object, $keys)
{
	foreach ($keys as $key) {
		if (!isset($object->fields[$key])) {
			continue;
		}
		$type = $object->fields[$key]['type'];
		if ($type === 'date') {
			if (!GETPOSTISSET($key.'year') && !GETPOSTISSET($key)) {
				continue;
			}
			$object->$key = dol_mktime(12, 0, 0, GETPOSTINT($key.'month'), GETPOSTINT($key.'day'), GETPOSTINT($key.'year'));
			if ($object->$key === '') {
				$object->$key = null;
			}
			continue;
		}
		if (!GETPOSTISSET($key)) {
			continue;
		}
		if (preg_match('/^text/', $type)) {
			$object->$key = GETPOST($key, 'nohtml');
		} elseif (preg_match('/^integer/', $type)) {
			$v = GETPOST($key, 'alphanohtml');
			$object->$key = ($v === '' || $v === '-1') ? null : (int) $v;
		} else {
			$v = GETPOST($key, 'alphanohtml');
			$object->$key = ($v === '-1') ? '' : $v;
		}
	}
}

/**
 * Onglets de la fiche d'un élève.
 *
 * @param  EcoleEleve $object Élève
 * @return array
 */
function eleve_prepare_head($object)
{
	global $langs, $user;
	$langs->load('eleves@eleves');
	$id = (int) $object->id;
	$head = array();
	$h = 0;

	$head[$h][0] = dol_buildpath('/eleves/eleve/card.php', 1).'?id='.$id;
	$head[$h][1] = $langs->trans('DossierEleve');
	$head[$h][2] = 'card';
	$h++;

	// Matières et emploi du temps hérités de la classe
	$head[$h][0] = dol_buildpath('/eleves/eleve/emploi.php', 1).'?id='.$id;
	$head[$h][1] = $langs->trans('EmploiMatieres');
	$head[$h][2] = 'emploi';
	$h++;

	if ($user->hasRight('eleves', 'document', 'lire')) {
		$nb = count($object->getPiecesManquantes());
		$head[$h][0] = dol_buildpath('/eleves/eleve/documents.php', 1).'?id='.$id;
		$head[$h][1] = $langs->trans('PiecesDossier').($nb > 0 ? '<span class="badge badge-danger marginleftonlyshort" title="'.dol_escape_htmltag($langs->trans('PiecesManquantesNb', $nb)).'">'.$nb.'</span>' : '');
		$head[$h][2] = 'documents';
		$h++;
	}

	if ($user->hasRight('eleves', 'paiement', 'lire')) {
		dol_include_once('/eleves/core/lib/paiements.lib.php');
		$s = eleves_situation($object->db, $object);
		$head[$h][0] = dol_buildpath('/eleves/eleve/paiements.php', 1).'?id='.$id;
		$head[$h][1] = $langs->trans('Paiements').($s['impaye'] > 0 ? '<span class="badge badge-danger marginleftonlyshort" title="'.dol_escape_htmltag($langs->trans('Impaye').' : '.price($s['impaye'])).'">!</span>' : '');
		$head[$h][2] = 'paiements';
		$h++;
	}

	// Absences, retards et sanctions (badge « ! » si l'élève dépasse un seuil)
	if ($user->hasRight('eleves', 'absence', 'lire') || $user->hasRight('eleves', 'sanction', 'lire')) {
		dol_include_once('/eleves/core/lib/discipline.lib.php');
		$badge = '';
		if ($user->hasRight('eleves', 'absence', 'lire')) {
			$raisons = eleves_raisons_signalement(eleves_compteurs_eleve($object->db, $id));
			if (!empty($raisons)) {
				$badge = '<span class="badge badge-danger marginleftonlyshort" title="'.dol_escape_htmltag(implode(' ; ', $raisons)).'">!</span>';
			}
		}
		$head[$h][0] = dol_buildpath('/eleves/eleve/discipline.php', 1).'?id='.$id;
		$head[$h][1] = $langs->trans('AbsencesDiscipline').$badge;
		$head[$h][2] = 'discipline';
		$h++;
	}

	// Notes, moyennes, bulletins et certificat de scolarité (module Notes)
	if (isModEnabled('notes') && ($user->hasRight('notes', 'bulletin', 'lire') || $user->hasRight('notes', 'note', 'lire'))) {
		$langs->load('notes@notes');
		$head[$h][0] = dol_buildpath('/notes/eleve.php', 1).'?id='.$id;
		$head[$h][1] = $langs->trans('Notes');
		$head[$h][2] = 'notes';
		$h++;
	}

	// Accès de l'élève à l'espace parents et élèves (module Espace)
	if (isModEnabled('espace') && $user->hasRight('espace', 'acces', 'lire')) {
		$langs->load('espace@espace');
		$head[$h][0] = dol_buildpath('/espace/acces.php', 1).'?type=eleve&id='.$id;
		$head[$h][1] = $langs->trans('AccesEspaceOnglet');
		$head[$h][2] = 'espace';
		$h++;
	}

	$head[$h][0] = dol_buildpath('/eleves/eleve/historique.php', 1).'?id='.$id;
	$head[$h][1] = $langs->trans('Historique');
	$head[$h][2] = 'historique';
	$h++;

	return $head;
}

/**
 * URL de la photo d'un élève (vignette si disponible), ou '' s'il n'en a pas.
 *
 * @param  EcoleEleve $object Élève
 * @param  bool       $small  true = vignette
 * @return string
 */
function eleve_photo_url($object, $small = true)
{
	global $conf;
	if (empty($object->photo)) {
		return '';
	}
	$sub = $object->getPhotoSubdir();
	$file = $object->photo;
	if ($small) {
		$thumb = preg_replace('/(\.[^.]+)$/', '_small$1', $file);
		if (is_file($conf->eleves->dir_output.'/'.$sub.'/thumbs/'.$thumb)) {
			$file = 'thumbs/'.$thumb;
		}
	}
	if (!is_file($conf->eleves->dir_output.'/'.$sub.'/'.$file)) {
		return '';
	}
	return DOL_URL_ROOT.'/viewimage.php?modulepart=eleves&entity='.((int) $conf->entity).'&file='.urlencode($sub.'/'.$file);
}

/**
 * Photo pour la bannière de la fiche ('' sans photo : Dolibarr affiche alors l'icône de l'objet).
 * Dolibarr ajoute toujours sa case « pas de photo » après la nôtre : elle est masquée par la CSS.
 *
 * @param  EcoleEleve $object Élève
 * @return string
 */
function eleve_photo_html($object)
{
	$url = eleve_photo_url($object, true);
	if (!$url) {
		return '';
	}
	$out = '<style>.eleve-photo + .divphotoref{display:none}</style>';
	$out .= '<div class="floatleft inline-block valignmiddle divphotoref eleve-photo">';
	$out .= '<a href="'.eleve_photo_url($object, false).'" target="_blank" rel="noopener"><img class="photoref" style="object-fit:cover" src="'.$url.'" alt="'.dol_escape_htmltag($object->nom_fr).'"></a>';
	$out .= '</div>';
	return $out;
}

/**
 * En-tête d'un onglet de la fiche élève (onglets + bannière avec photo, noms, classe, statut).
 * Le code appelant ferme avec dol_get_fiche_end().
 *
 * @param  EcoleEleve $object Élève
 * @param  string     $tab    Onglet actif
 * @return void
 */
function eleve_print_banner($object, $tab)
{
	global $langs, $db;

	print dol_get_fiche_head(eleve_prepare_head($object), $tab, $langs->trans('Eleve'), -1, $object->picto);

	$linkback = '<a href="'.dol_buildpath('/eleves/eleve/list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
	$morehtmlref = '<div class="refidno">';
	$morehtmlref .= '<span class="bold" dir="auto">'.dol_escape_htmltag(ecole_label($object)).'</span>';
	$morehtmlref .= '<br>'.img_picto('', 'fa-chalkboard', 'class="pictofixedwidth"').ecole_link_label($db, 'EcoleClasse', 'classes/class/ecole_classe.class.php', $object->fk_classe);
	if (!empty($object->rip)) {
		$morehtmlref .= ' &nbsp; <span class="opacitymedium">'.$langs->trans('RIP').' :</span> '.dol_escape_htmltag($object->rip);
	}
	$morehtmlref .= '</div>';
	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref, '', 0, eleve_photo_html($object), ''); // le statut est ajouté par Dolibarr
}

/**
 * Onglets de la configuration du module.
 *
 * @return array
 */
function eleves_admin_prepare_head()
{
	global $langs;
	$langs->load('eleves@eleves');
	$head = array();
	$head[] = array(dol_buildpath('/eleves/admin/setup.php', 1), $langs->trans('MenuReglages'), 'setup');
	$head[] = array(dol_buildpath('/eleves/admin/paiements.php', 1), $langs->trans('MenuConfigPaiements'), 'paiements');
	$head[] = array(dol_buildpath('/eleves/frais_type/list.php', 1), $langs->trans('MenuAutresFrais'), 'frais');
	$head[] = array(dol_buildpath('/eleves/motif_exoneration/list.php', 1), $langs->trans('MenuMotifsExoneration'), 'exoneration');
	$head[] = array(dol_buildpath('/eleves/engagement/list.php', 1), $langs->trans('MenuEngagements'), 'engagements');
	$head[] = array(dol_buildpath('/eleves/document_type/list.php', 1), $langs->trans('MenuPiecesAFournir'), 'pieces');
	$head[] = array(dol_buildpath('/eleves/admin/discipline.php', 1), $langs->trans('MenuConfigDiscipline'), 'discipline');
	$head[] = array(dol_buildpath('/eleves/motif_absence/list.php', 1), $langs->trans('MenuMotifsAbsence'), 'motifs');
	$head[] = array(dol_buildpath('/eleves/sanction_type/list.php', 1), $langs->trans('MenuTypesSanction'), 'sanctions');
	$head[] = array(dol_buildpath('/eleves/admin/eleve_extrafields.php', 1), $langs->trans('MenuChampsSupp'), 'extrafields');
	return $head;
}

/**
 * Enregistre un fichier envoyé par formulaire dans un sous-dossier du module (nom nettoyé, vignettes pour les images).
 *
 * @param  string $input  Nom du champ <input type="file">
 * @param  string $subdir Sous-dossier relatif au dossier du module
 * @param  string $prefix Préfixe du nom de fichier (ex. type de pièce)
 * @param  bool   $image  true = image seulement
 * @return string         Nom du fichier enregistré, '' si aucun fichier, ou « ERR:message » en cas d'erreur
 */
function eleves_upload_file($input, $subdir, $prefix = '', $image = false)
{
	global $conf, $langs;
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';

	if (empty($_FILES[$input]) || empty($_FILES[$input]['name']) || (int) $_FILES[$input]['error'] === UPLOAD_ERR_NO_FILE) {
		return '';
	}
	if ((int) $_FILES[$input]['error'] !== UPLOAD_ERR_OK) {
		return 'ERR:'.$langs->trans('ErrorFileNotUploaded');
	}
	$name = dol_sanitizeFileName($_FILES[$input]['name']);
	if ($image && !image_format_supported($name)) {
		return 'ERR:'.$langs->trans('ErrorBadImageFormat');
	}
	if ($prefix !== '') {
		$name = dol_sanitizeFileName($prefix).'_'.$name;
	}
	$dir = $conf->eleves->dir_output.'/'.$subdir;
	if (dol_mkdir($dir) < 0) {
		return 'ERR:'.$langs->trans('ErrorCanNotCreateDir', $dir);
	}
	$res = dol_move_uploaded_file($_FILES[$input]['tmp_name'], $dir.'/'.$name, 1, 0, $_FILES[$input]['error']);
	if (!is_numeric($res) || $res <= 0) {
		return 'ERR:'.$langs->trans(is_string($res) ? $res : 'ErrorFileNotUploaded');
	}
	if (image_format_supported($name) > 0) {
		vignette($dir.'/'.$name, 160, 160, '_small', 80, 'thumbs');
	}
	return $name;
}

/**
 * Colonne « Payé par » de la liste des reçus.
 *
 * @param  EcoleRecu $rec Reçu
 * @return string
 */
function recu_col_payeur($rec)
{
	global $db;
	return (int) $rec->fk_responsable > 0 ? ecole_link_label($db, 'EcoleResponsable', 'eleves/class/ecole_responsable.class.php', $rec->fk_responsable) : '';
}

/**
 * Colonne « Élèves » de la liste des reçus : matricules des élèves concernés.
 *
 * @param  EcoleRecu $rec Reçu
 * @return string
 */
function recu_col_eleves($rec)
{
	global $db;
	$out = array();
	$sql = "SELECT DISTINCT e.rowid, e.ref, e.nom_fr, e.nom_ar FROM ".$db->prefix()."ecole_paiement l INNER JOIN ".$db->prefix()."ecole_eleve e ON e.rowid = l.fk_eleve WHERE l.fk_recu = ".((int) $rec->id)." ORDER BY e.ref";
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[] = '<a href="'.dol_buildpath('/eleves/eleve/paiements.php', 1).'?id='.((int) $o->rowid).'" title="'.dol_escape_htmltag(ecole_label($o)).'">'.dol_escape_htmltag($o->ref).'</a>';
	}
	return implode(', ', $out);
}

/**
 * Élèves d'une classe (inscrits, suspendus, pré-inscrits, en attente), triés par numéro d'appel puis nom,
 * avec leur responsable et son téléphone.
 *
 * @param  DoliDB $db        Handler base
 * @param  int    $fk_classe Classe
 * @return object[]
 */
function eleves_liste_classe($db, $fk_classe)
{
	$p = $db->prefix();
	$sql = "SELECT e.rowid, e.ref, e.numero_appel, e.nom_fr, e.nom_ar, e.sexe, e.status, r.nom_fr as rnom_fr, r.nom_ar as rnom_ar, r.telephone";
	$sql .= " FROM ".$p."ecole_eleve e LEFT JOIN ".$p."ecole_responsable r ON r.rowid = e.fk_responsable";
	$sql .= " WHERE e.entity IN (".getEntity('ecole_eleve').") AND e.fk_classe = ".((int) $fk_classe)." AND e.status IN (0, 1, 2, 3)";
	$sql .= " ORDER BY (e.numero_appel IS NULL), e.numero_appel, e.nom_fr";
	$out = array();
	$resql = $db->query($sql);
	while ($resql && ($o = $db->fetch_object($resql))) {
		$out[] = $o;
	}
	return $out;
}

/**
 * Jeu de données « Liste de la classe » (export PDF / Excel).
 *
 * @param  DoliDB      $db     Handler base
 * @param  EcoleClasse $classe Classe
 * @return array
 */
function eleves_export_dataset_classe_eleves($db, $classe)
{
	global $langs;
	dol_include_once('/classes/core/lib/ecole_pdf.lib.php');
	dol_include_once('/eleves/class/ecole_eleve.class.php');
	$labels = EcoleEleve::statusLabels();
	$rows = array();
	foreach (eleves_liste_classe($db, (int) $classe->id) as $e) {
		$rows[] = array($e->numero_appel ? (string) $e->numero_appel : '', $e->ref, ecole_label($e),
			$e->sexe ? ecole_pdf_trans($langs, $e->sexe === 'F' ? 'SexeF' : 'SexeM') : '',
			ecole_label((object) array('nom_fr' => (string) $e->rnom_fr, 'nom_ar' => (string) $e->rnom_ar)), (string) $e->telephone,
			isset($labels[(int) $e->status]) ? ecole_pdf_trans($langs, $labels[(int) $e->status][0]) : '');
	}
	return array(
		'title' => ecole_pdf_trans($langs, 'ListeClasseTitre', $classe->ref),
		'subtitle' => ecole_label($classe).' — '.ecole_pdf_trans($langs, 'NbEnregistrements', count($rows)),
		'ref' => 'LST-'.$classe->ref,
		'headers' => array(ecole_pdf_trans($langs, 'NumeroAppelCourt'), ecole_pdf_trans($langs, 'Matricule'), ecole_pdf_trans($langs, 'NomComplet'),
			ecole_pdf_trans($langs, 'Sexe'), ecole_pdf_trans($langs, 'Responsable'), ecole_pdf_trans($langs, 'TelephoneResponsable'), ecole_pdf_trans($langs, 'StatutEleve')),
		'ratios' => array(0.5, 1.1, 3.2, 0.7, 2.4, 1.4, 1.2),
		'aligns' => array('C', 'C', 'L', 'C', 'L', 'C', 'C'),
		'rows' => $rows,
		'filename' => 'liste_'.$classe->ref,
	);
}
