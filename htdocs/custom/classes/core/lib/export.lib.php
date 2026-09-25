<?php
/**
 * Export des listes du module Classes en PDF ou Excel.
 *
 * Une liste est d'abord transformée en « jeu de données » (titre, colonnes, lignes de texte),
 * puis écrite en PDF (ecole_export_pdf) ou en Excel (ecole_export_excel). Les textes sont dans
 * la langue détectée (arabe / français) et les colonnes suivent le sens de lecture.
 *
 * Fichier : custom/classes/core/lib/export.lib.php
 */

/**
 * Jeu de données d'une liste générique (niveaux, sections, créneaux, sessions, matières, classes, salles),
 * avec les mêmes filtres et le même tri que la liste affichée.
 *
 * @param  EcoleObject $object Objet
 * @param  array       $cfg    Configuration de la liste (ecole_crud_config)
 * @return array
 */
function ecole_export_dataset_list($object, $cfg)
{
	global $langs;

	$state = ecole_crud_list_state($object, $cfg);
	$records = $object->fetchAllObjects($state['sortfield'], $state['sortorder'], 0, 0, array(), $state['customsql']);
	if (!is_array($records)) {
		$records = array();
	}
	$extras = !empty($cfg['extra_columns']) ? $cfg['extra_columns'] : array();

	$headers = array('#');
	$ratios = array(0.5);
	$aligns = array('C');
	foreach ($object->listcolumns as $k) {
		if ($k === 'label') {
			$headers[] = ecole_pdf_trans($langs, $object->labeltitle);
			$ratios[] = 3;
			$aligns[] = 'L';
		} elseif (strpos($k, 'extra:') === 0) {
			$headers[] = ecole_pdf_trans($langs, $extras[substr($k, 6)]['label']);
			$ratios[] = 1.4;
			$aligns[] = 'C';
		} else {
			$headers[] = ecole_pdf_trans($langs, $object->fields[$k]['label']);
			$ratios[] = ($k === 'ref') ? 1.1 : 1.4;
			$aligns[] = in_array($k, array('ref', 'status')) || preg_match('/^(integer|price|double|boolean|date)/', $object->fields[$k]['type']) ? 'C' : 'L';
		}
	}

	$rows = array();
	$n = 0;
	foreach ($records as $rec) {
		$row = array((string) (++$n));
		foreach ($object->listcolumns as $k) {
			if ($k === 'ref') {
				$row[] = (string) $rec->ref;
			} elseif ($k === 'label') {
				$row[] = ecole_label($rec);
			} elseif ($k === 'status') {
				$statuses = $rec->statusOptions();
				$row[] = isset($statuses[(int) $rec->status]) ? ecole_pdf_trans($langs, $statuses[(int) $rec->status]) : (string) $rec->status;
			} elseif (strpos($k, 'extra:') === 0) {
				$row[] = ecole_pdf_text(call_user_func($extras[substr($k, 6)]['render'], $rec));
			} elseif ($object->fields[$k]['type'] === 'boolean') {
				$row[] = ecole_pdf_trans($langs, $rec->$k ? 'Yes' : 'No');
			} else {
				$row[] = ecole_pdf_text($rec->showOutputField($rec->fields[$k], $k, $rec->$k, ''));
			}
		}
		$rows[] = $row;
	}

	return array(
		'title' => ecole_pdf_trans($langs, $cfg['title']),
		'subtitle' => ecole_pdf_trans($langs, 'NbEnregistrements', count($rows)),
		'ref' => strtoupper($cfg['dir']).'-'.dol_print_date(dol_now(), '%Y%m%d'),
		'headers' => $headers,
		'ratios' => $ratios,
		'aligns' => $aligns,
		'rows' => $rows,
		'filename' => $cfg['dir'],
	);
}

/**
 * Jeu de données « Matières et coefficients » d'une classe.
 *
 * @param  DoliDB      $db     Handler base
 * @param  EcoleClasse $classe Classe
 * @return array
 */
function ecole_export_dataset_classe_matieres($db, $classe)
{
	global $langs;
	$baremes = array('20' => ecole_pdf_trans($langs, 'BaremeSur20'), 'coef20' => ecole_pdf_trans($langs, 'BaremeSurCoef20'));

	$sql = "SELECT m.ref, m.label_fr, m.label_ar, cm.coefficient, cm.bareme, cm.note_max";
	$sql .= " FROM ".$db->prefix()."ecole_classe_matiere cm INNER JOIN ".$db->prefix()."ecole_matiere m ON m.rowid = cm.fk_matiere";
	$sql .= " WHERE cm.fk_classe = ".((int) $classe->id)." ORDER BY m.label_fr";
	$resql = $db->query($sql);
	$rows = array();
	$n = 0;
	$totcoef = 0;
	$totmax = 0;
	while ($resql && ($o = $db->fetch_object($resql))) {
		$totcoef += $o->coefficient;
		$totmax += $o->note_max;
		$rows[] = array((string) (++$n), $o->ref, ecole_label($o), price2num($o->coefficient), isset($baremes[$o->bareme]) ? $baremes[$o->bareme] : $o->bareme, price2num($o->note_max));
	}
	if ($n) {
		$rows[] = array('', '', ecole_pdf_trans($langs, 'Total'), price2num($totcoef), '', price2num($totmax));
	}

	return array(
		'title' => ecole_pdf_trans($langs, 'MatieresCoefficients'),
		'subtitle' => $classe->ref.' - '.ecole_label($classe),
		'ref' => 'MAT-'.$classe->ref,
		'headers' => array('#', ecole_pdf_trans($langs, 'Ref'), ecole_pdf_trans($langs, 'Matiere'), ecole_pdf_trans($langs, 'Coefficient'), ecole_pdf_trans($langs, 'Bareme'), ecole_pdf_trans($langs, 'NoteMax')),
		'ratios' => array(0.5, 1.2, 3.5, 1.2, 2.4, 1.2),
		'aligns' => array('C', 'C', 'L', 'C', 'L', 'C'),
		'rows' => $rows,
		'filename' => 'matieres_'.$classe->ref,
	);
}

/**
 * Nom de fichier sûr.
 *
 * @param  string $name Nom
 * @param  string $ext  Extension
 * @return string
 */
function ecole_export_filename($name, $ext)
{
	return preg_replace('/[^a-zA-Z0-9_-]/', '_', $name).'_'.dol_print_date(dol_now(), '%Y-%m-%d').'.'.$ext;
}

/**
 * Écrit un jeu de données en PDF (affiché dans le navigateur).
 *
 * @param  array $ds Jeu de données
 * @return void
 */
function ecole_export_pdf($ds)
{
	global $langs;
	list($pdf) = ecole_pdf_create($langs, count($ds['headers']) > 7 ? 'L' : 'P');
	ecole_pdf_start($pdf, $ds['title'], $ds['subtitle'], $ds['ref']);
	ecole_pdf_table($pdf, $ds['headers'], $ds['ratios'], $ds['aligns'], $ds['rows']);
	$pdf->Output(ecole_export_filename($ds['filename'], 'pdf'), 'I');
}

/**
 * Écrit un jeu de données en Excel (.xlsx téléchargé). Feuille de droite à gauche en arabe.
 *
 * @param  array  $ds   Jeu de données
 * @param  string $lang 'ar' ou 'fr'
 * @return void
 */
function ecole_export_excel($ds, $lang)
{
	require_once DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/autoloader.php';
	require_once DOL_DOCUMENT_ROOT.'/includes/Psr/autoloader.php';
	require_once PHPEXCELNEW_PATH.'Spreadsheet.php';

	$company = ecole_pdf_company();
	$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
	$spreadsheet->getProperties()->setCreator($company['name'])->setTitle($ds['title']);
	$sheet = $spreadsheet->getActiveSheet();
	$sheet->setTitle(mb_substr(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/u', ' ', $ds['title']), 0, 31));
	if ($lang === 'ar') {
		$sheet->setRightToLeft(true);
	}

	$nbcols = count($ds['headers']);
	$lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($nbcols);
	$set = function ($col, $row, $value) use ($sheet) {
		$sheet->setCellValueExplicit(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col).$row, (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
	};

	// Titre, établissement, sous-titre
	$set(1, 1, $ds['title']);
	$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
	$set(1, 2, trim($company['name'].' — '.$ds['subtitle'], ' —'));
	$sheet->getStyle('A2')->getFont()->setItalic(true);

	// En-têtes de colonnes
	$hr = 4;
	foreach ($ds['headers'] as $i => $h) {
		$set($i + 1, $hr, $h);
	}
	$headStyle = $sheet->getStyle('A'.$hr.':'.$lastCol.$hr);
	$headStyle->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
	$headStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('425CC7');

	// Lignes
	$r = $hr + 1;
	foreach ($ds['rows'] as $row) {
		foreach (array_values($row) as $i => $v) {
			$set($i + 1, $r, $v);
		}
		$r++;
	}
	if ($r > $hr + 1) {
		$sheet->getStyle('A'.$hr.':'.$lastCol.($r - 1))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
	}
	for ($i = 1; $i <= $nbcols; $i++) {
		$sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
	}
	$sheet->freezePane('A'.($hr + 1));

	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment; filename="'.ecole_export_filename($ds['filename'], 'xlsx').'"');
	header('Cache-Control: max-age=0');
	$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
	$writer->save('php://output');
	$spreadsheet->disconnectWorksheets();
}
