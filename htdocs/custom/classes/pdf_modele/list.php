<?php
/**
 * Liste : modèles de PDF (listes, reçus, bulletins de paie).
 * Fichier : custom/classes/pdf_modele/list.php
 */

require '../init.php';
dol_include_once('/classes/class/ecole_pdf_modele.class.php');

ecole_crud_list(new EcolePdfModele($db), ecole_crud_config('pdf_modele'));
