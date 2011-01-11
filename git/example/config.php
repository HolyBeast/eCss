<?php

//=====================
// Configuration eCss
//=====================
// cache        : true       (cache activé)
//              : false      (cache désactivé)
// disable      : false      (module activé)
//              : true       (module désactivé)
// eCssFiles    : array      (liste des fichiers à compiler)
// master       : string     (nom du fichier unique)
//              : null|false (pas de fichier unique)
// output       : string     (mode de rendu du fichier .css)
//              : nested     (sélecteur étendu sur plusieurs lignes)
//              : expanded   (propriétés étendues sur plusieurs lignes)
//              : compact    (un sélecteur par ligne)
//              : compressed (tous les sélecteurs sur une ligne)
// path         : string     (chemin vers les fichiers .ecss)

require_once './ecss.php';

$eCssConfig = array(
	'cache'      => true,
	'disable'    => false,
	'eCssFiles'  => array(
		'sample'
	),
	'master'     => 'master',
	'output'     => 'nested',
	'path'       => './css/'
);

eCssCompile();

?>
